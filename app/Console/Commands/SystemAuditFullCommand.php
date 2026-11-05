<?php

namespace App\Console\Commands;

use App\Services\RouteAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SystemAuditFullCommand extends Command
{
    protected $signature = 'system:audit-full {--write-docs : Atualiza docs/graphify com snapshot da auditoria}';

    protected $description = 'Executa auditoria completa do sistema (rotas, controllers, services, tenant scope, PWA, seguranca e performance).';

    public function handle(RouteAuditService $routeAudit): int
    {
        $startedAt = now();
        $this->info('Iniciando auditoria completa do sistema...');

        $reportDir = storage_path('reports');
        $logDir = storage_path('logs');
        File::ensureDirectoryExists($reportDir);
        File::ensureDirectoryExists($logDir);

        $routeReport = $routeAudit->run();
        $codeAudit = $this->runCodeAudit();
        $routeSummary = $this->routeSummary();

        $systemReport = $this->buildSystemAuditReport($startedAt->toIso8601String(), $routeReport, $routeSummary, $codeAudit);
        $securityReport = $this->buildSecurityReport($codeAudit);
        $performanceReport = $this->buildPerformanceReport($codeAudit);
        $databaseReport = $this->buildDatabaseReport($codeAudit);
        $pwaReport = $this->buildPwaReport($codeAudit);
        $evolutionReport = $this->buildEvolutionReport($codeAudit);
        $multiTenantReport = $this->buildMultiTenantReport($codeAudit);
        $addonsReport = $this->buildAddonsReport($codeAudit);

        File::put($reportDir.'/SYSTEM_AUDIT_REPORT.md', $systemReport);
        File::put($reportDir.'/SECURITY_REPORT.md', $securityReport);
        File::put($reportDir.'/PERFORMANCE_REPORT.md', $performanceReport);
        File::put($reportDir.'/DATABASE_REPORT.md', $databaseReport);
        File::put($reportDir.'/PWA_REPORT.md', $pwaReport);
        File::put($reportDir.'/EVOLUTION_REPORT.md', $evolutionReport);
        File::put($reportDir.'/MULTITENANT_REPORT.md', $multiTenantReport);
        File::put($reportDir.'/ADDONS_REPORT.md', $addonsReport);

        $systemLog = $this->buildSystemAuditLog($startedAt->toIso8601String(), $routeSummary, $codeAudit, $routeReport);
        File::append($logDir.'/system_audit.log', $systemLog."\n");

        $this->dispatchAuditAlerts($routeSummary, $codeAudit);

        if ($this->option('write-docs')) {
            $this->writeGraphifyDocs($routeSummary, $codeAudit);
        }

        $this->info('Auditoria concluida.');
        $this->line('Relatorios: '.str_replace(base_path().'\\', '', $reportDir));
        $this->line('Log: '.str_replace(base_path().'\\', '', $logDir.'/system_audit.log'));

        return self::SUCCESS;
    }

    protected function runCodeAudit(): array
    {
        $controllers = File::allFiles(app_path('Http/Controllers'));
        $models = File::allFiles(app_path('Models'));
        $services = File::allFiles(app_path('Services'));
        $jobs = File::allFiles(app_path('Jobs'));
        $middlewares = File::allFiles(app_path('Http/Middleware'));
        $modules = File::allFiles(app_path('Modules'));
        $migrations = File::allFiles(database_path('migrations'));
        $views = File::allFiles(resource_path('views'));

        $phpFiles = collect([
            ...File::allFiles(app_path()),
            ...File::allFiles(base_path('routes')),
            ...File::allFiles(database_path()),
            ...File::allFiles(resource_path('views')),
        ])->filter(fn ($file) => Str::endsWith($file->getFilename(), ['.php', '.blade.php']));

        $emptyMethods = [];
        $debugLeftovers = [];
        $weakCatches = [];
        $tenantScopeFindings = [];

        foreach ($phpFiles as $file) {
            $content = File::get($file->getPathname());
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (preg_match_all('/public function\s+(\w+)\s*\([^)]*\)\s*\{\s*\}/m', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $method = $match[1] ?? '';
                    if (in_array($method, ['__construct', '__destruct', '__invoke'], true)) {
                        continue;
                    }
                    $emptyMethods[] = ['file' => $relative, 'snippet' => trim($match[0])];
                }
            }

            if (preg_match_all('/\b(dd|dump|var_dump|print_r)\s*\(/m', $content, $matches)) {
                foreach ($matches[0] as $hit) {
                    $debugLeftovers[] = ['file' => $relative, 'snippet' => trim($hit)];
                }
            }

            if (preg_match_all('/catch\s*\(\s*\\\\?Exception\s+\$\w+\s*\)\s*\{[^}]*return\s+false\s*;/ms', $content, $matches)) {
                foreach ($matches[0] as $hit) {
                    $weakCatches[] = ['file' => $relative, 'snippet' => Str::limit(preg_replace('/\s+/', ' ', trim($hit)), 220)];
                }
            }
        }

        foreach (File::allFiles(app_path('Modules')) as $file) {
            if (! Str::endsWith($file->getFilename(), '.php')) {
                continue;
            }

            $content = File::get($file->getPathname());
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (str_contains($content, '::all(') && str_contains($relative, 'Tenancy')) {
                $tenantScopeFindings[] = [
                    'file' => $relative,
                    'issue' => 'uso de ::all() em contexto de tenancy',
                ];
            }
        }

        $pwaServiceWorkerPath = public_path('sw.js');
        $pwaSwContent = File::exists($pwaServiceWorkerPath) ? File::get($pwaServiceWorkerPath) : '';

        return [
            'counts' => [
                'controllers' => count($controllers),
                'models' => count($models),
                'services' => count($services),
                'jobs' => count($jobs),
                'middlewares' => count($middlewares),
                'modules' => count($modules),
                'migrations' => count($migrations),
                'views' => count($views),
                'routes_files' => count(File::allFiles(base_path('routes'))),
            ],
            'empty_methods' => $emptyMethods,
            'debug_leftovers' => $debugLeftovers,
            'weak_catches' => $weakCatches,
            'tenant_scope_findings' => $tenantScopeFindings,
            'pwa' => [
                'service_worker_exists' => File::exists($pwaServiceWorkerPath),
                'service_worker_network_first' => str_contains($pwaSwContent, 'networkFirst') || str_contains($pwaSwContent, 'fetch('),
                'service_worker_cache_first' => str_contains($pwaSwContent, 'cacheFirst') || str_contains($pwaSwContent, 'caches.match('),
                'cache_exclusions_login' => str_contains($pwaSwContent, '/login'),
                'cache_exclusions_api' => str_contains($pwaSwContent, '/api'),
            ],
            'evolution' => [
                'client_exists' => File::exists(app_path('Modules/WhatsApp/Services/EvolutionClient.php'))
                    || File::exists(app_path('Services/EvolutionApiService.php'))
                    || File::exists(app_path('Services/EvolutionGoWebhookService.php')),
                'webhook_controller_exists' => File::exists(app_path('Http/Controllers/WebhookController.php'))
                    || File::exists(app_path('Modules/WhatsApp/Http/Controllers/WebhookController.php'))
                    || File::exists(app_path('Http/Controllers/Api/EvolutionGoWebhookController.php')),
            ],
            'addons' => [
                'installer_service_exists' => File::exists(app_path('Modules/Marketplace/Services/AddonInstallerService.php'))
                    || File::exists(app_path('Services/AddonInstallerService.php'))
                    || File::exists(app_path('Services/AddonManagerService.php'))
                    || File::exists(app_path('Modules/Marketplace/Services/AddonMarketplaceService.php')),
            ],
        ];
    }

    protected function routeSummary(): array
    {
        $allRoutes = Route::getRoutes();
        $duplicates = [];
        $grouped = [];
        $whitelistedNoMiddleware = [
            'up',
            'health',
            'api/openapi.json',
            'manifest.json',
            'sw.js',
            'robots.txt',
            'sitemap.xml',
        ];

        foreach ($allRoutes as $route) {
            $key = implode('|', $route->methods()).' '.$route->uri();
            $grouped[$key] = ($grouped[$key] ?? 0) + 1;
        }

        foreach ($grouped as $key => $count) {
            if ($count > 1) {
                $duplicates[] = ['route' => $key, 'count' => $count];
            }
        }

        $orphanRoutes = [];
        foreach ($allRoutes as $route) {
            $action = $route->getActionName();
            if ($action === 'Closure') {
                continue;
            }

            if ($action === 'Illuminate\Routing\ViewController') {
                $view = $route->getAction('view') ?: $route->defaults['view'] ?? null;
                if ($view && ! view()->exists($view)) {
                    $orphanRoutes[] = [
                        'uri' => $route->uri(),
                        'action' => $action,
                        'issue' => 'view inexistente',
                    ];
                }
                continue;
            }

            if (! str_contains($action, '@') && ! class_exists($action)) {
                $orphanRoutes[] = [
                    'uri' => $route->uri(),
                    'action' => $action,
                    'issue' => 'controller inexistente',
                ];
            }
        }

        return [
            'total' => count($allRoutes),
            'duplicates' => $duplicates,
            'orphans' => $orphanRoutes,
            'without_middleware' => collect($allRoutes)
                ->filter(function (IlluminateRoute $route) use ($whitelistedNoMiddleware): bool {
                    if (count($route->middleware()) > 0) {
                        return false;
                    }

                    $action = $route->getActionName();
                    if ($action === 'Closure' || str_starts_with($route->uri(), '_ignition')) {
                        return false;
                    }

                    $uri = trim($route->uri(), '/');
                    if (in_array($uri, $whitelistedNoMiddleware, true)) {
                        return false;
                    }

                    if (str_starts_with($uri, 'webhooks/')) {
                        return false;
                    }

                    return true;
                })
                ->map(fn (IlluminateRoute $route) => ['uri' => $route->uri(), 'methods' => $route->methods()])
                ->values()
                ->all(),
        ];
    }

    protected function buildSystemAuditReport(string $generatedAt, array $routeReport, array $routeSummary, array $codeAudit): string
    {
        $lines = [];
        $lines[] = '# SYSTEM_AUDIT_REPORT';
        $lines[] = '';
        $lines[] = '- Gerado em: '.$generatedAt;
        $lines[] = '- Rotas mapeadas: '.$routeSummary['total'];
        $lines[] = '- Achados criticos de rota: '.($routeReport['summary']['critical'] ?? 0);
        $lines[] = '- Controllers: '.$codeAudit['counts']['controllers'];
        $lines[] = '- Models: '.$codeAudit['counts']['models'];
        $lines[] = '- Services: '.$codeAudit['counts']['services'];
        $lines[] = '- Middlewares: '.$codeAudit['counts']['middlewares'];
        $lines[] = '';
        $lines[] = '## Achados principais';
        $lines[] = '';
        $lines[] = '- Metodos vazios: '.count($codeAudit['empty_methods']);
        $lines[] = '- Restos de debug: '.count($codeAudit['debug_leftovers']);
        $lines[] = '- Catches fracos (Exception + return false): '.count($codeAudit['weak_catches']);
        $lines[] = '- Possiveis riscos de escopo tenant: '.count($codeAudit['tenant_scope_findings']);
        $lines[] = '- Rotas duplicadas: '.count($routeSummary['duplicates']);
        $lines[] = '- Rotas sem middleware: '.count($routeSummary['without_middleware']);
        $lines[] = '';
        $lines[] = '## Referencias';
        $lines[] = '';
        $lines[] = '- Auditoria detalhada de rotas: '.($routeReport['report_path'] ?? '-');

        return implode("\n", $lines)."\n";
    }

    protected function buildSecurityReport(array $audit): string
    {
        return $this->simpleSectionReport('SECURITY_REPORT', [
            'Restos de debug detectados: '.count($audit['debug_leftovers']),
            'Catches fracos detectados: '.count($audit['weak_catches']),
            'Service Worker protegido para /api: '.($audit['pwa']['cache_exclusions_api'] ? 'sim' : 'nao'),
        ], [
            'debug_leftovers' => $audit['debug_leftovers'],
            'weak_catches' => $audit['weak_catches'],
        ]);
    }

    protected function buildPerformanceReport(array $audit): string
    {
        return $this->simpleSectionReport('PERFORMANCE_REPORT', [
            'Migrations encontradas: '.$audit['counts']['migrations'],
            'Jobs encontrados: '.$audit['counts']['jobs'],
            'Service Worker com cacheFirst: '.($audit['pwa']['service_worker_cache_first'] ? 'sim' : 'nao'),
            'Service Worker com networkFirst: '.($audit['pwa']['service_worker_network_first'] ? 'sim' : 'nao'),
        ]);
    }

    protected function buildDatabaseReport(array $audit): string
    {
        return $this->simpleSectionReport('DATABASE_REPORT', [
            'Models auditados: '.$audit['counts']['models'],
            'Migrations auditadas: '.$audit['counts']['migrations'],
            'Riscos de vazamento tenant por escopo: '.count($audit['tenant_scope_findings']),
        ], [
            'tenant_scope_findings' => $audit['tenant_scope_findings'],
        ]);
    }

    protected function buildPwaReport(array $audit): string
    {
        return $this->simpleSectionReport('PWA_REPORT', [
            'Service worker existe: '.($audit['pwa']['service_worker_exists'] ? 'sim' : 'nao'),
            'Exclusao de cache em /login: '.($audit['pwa']['cache_exclusions_login'] ? 'sim' : 'nao'),
            'Exclusao de cache em /api: '.($audit['pwa']['cache_exclusions_api'] ? 'sim' : 'nao'),
        ]);
    }

    protected function buildEvolutionReport(array $audit): string
    {
        return $this->simpleSectionReport('EVOLUTION_REPORT', [
            'EvolutionClient presente: '.($audit['evolution']['client_exists'] ? 'sim' : 'nao'),
            'Webhook Controller presente: '.($audit['evolution']['webhook_controller_exists'] ? 'sim' : 'nao'),
        ]);
    }

    protected function buildMultiTenantReport(array $audit): string
    {
        return $this->simpleSectionReport('MULTITENANT_REPORT', [
            'Possiveis riscos de escopo tenant: '.count($audit['tenant_scope_findings']),
        ], [
            'tenant_scope_findings' => $audit['tenant_scope_findings'],
        ]);
    }

    protected function buildAddonsReport(array $audit): string
    {
        return $this->simpleSectionReport('ADDONS_REPORT', [
            'AddonInstallerService presente: '.($audit['addons']['installer_service_exists'] ? 'sim' : 'nao'),
        ]);
    }

    protected function simpleSectionReport(string $title, array $summaryLines, array $examples = []): string
    {
        $lines = [];
        $lines[] = '# '.$title;
        $lines[] = '';
        foreach ($summaryLines as $line) {
            $lines[] = '- '.$line;
        }

        if ($examples !== []) {
            $lines[] = '';
            $lines[] = '## Amostras';
            $lines[] = '';
            foreach ($examples as $key => $items) {
                $lines[] = '### '.$key;
                $lines[] = '';
                if ($items === []) {
                    $lines[] = '- sem ocorrencias';
                    $lines[] = '';
                    continue;
                }
                foreach (array_slice($items, 0, 20) as $item) {
                    $lines[] = '- `'.($item['file'] ?? '-').'`: '.($item['issue'] ?? ($item['snippet'] ?? 'verificar'));
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines)."\n";
    }

    protected function buildSystemAuditLog(string $generatedAt, array $routeSummary, array $codeAudit, array $routeReport): string
    {
        $payload = [
            'generated_at' => $generatedAt,
            'routes' => [
                'total' => $routeSummary['total'],
                'duplicates' => count($routeSummary['duplicates']),
                'orphans' => count($routeSummary['orphans']),
                'without_middleware' => count($routeSummary['without_middleware']),
                'critical' => $routeReport['summary']['critical'] ?? 0,
            ],
            'code' => [
                'empty_methods' => count($codeAudit['empty_methods']),
                'debug_leftovers' => count($codeAudit['debug_leftovers']),
                'weak_catches' => count($codeAudit['weak_catches']),
                'tenant_scope_findings' => count($codeAudit['tenant_scope_findings']),
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function writeGraphifyDocs(array $routeSummary, array $codeAudit): void
    {
        $dir = base_path('docs/graphify');
        File::ensureDirectoryExists($dir);

        $lines = [];
        $lines[] = '# Graphify Snapshot';
        $lines[] = '';
        $lines[] = '- Atualizado em: '.now()->toIso8601String();
        $lines[] = '- Rotas totais: '.$routeSummary['total'];
        $lines[] = '- Rotas duplicadas: '.count($routeSummary['duplicates']);
        $lines[] = '- Controllers: '.$codeAudit['counts']['controllers'];
        $lines[] = '- Services: '.$codeAudit['counts']['services'];
        $lines[] = '- Modules: '.$codeAudit['counts']['modules'];
        $lines[] = '';
        $lines[] = '## Referencias';
        $lines[] = '';
        $lines[] = '- `graphify-out/graph.json`';
        $lines[] = '- `graphify-out/GRAPH_REPORT.md`';

        File::put($dir.'/index.md', implode("\n", $lines)."\n");
    }

    protected function dispatchAuditAlerts(array $routeSummary, array $codeAudit): void
    {
        $criticalCount = (int) ($routeSummary['orphans'] ? count($routeSummary['orphans']) : 0)
            + (int) count($routeSummary['duplicates'])
            + (int) count($routeSummary['without_middleware'])
            + (int) count($codeAudit['empty_methods'])
            + (int) count($codeAudit['debug_leftovers'])
            + (int) count($codeAudit['weak_catches'])
            + (int) count($codeAudit['tenant_scope_findings']);

        try {
            if ($criticalCount > 0) {
                $alreadySentToday = DB::table('admin_notifications')
                    ->where('group', 'system')
                    ->where('type', 'system_audit_alert')
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if (! $alreadySentToday) {
                    app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                        'channels' => ['in_app'],
                        'group' => 'system',
                        'type' => 'system_audit_alert',
                        'title' => 'Auditoria detectou pendencias tecnicas',
                        'message' => 'A auditoria automatica encontrou '.$criticalCount.' pendencia(s). Acesse o painel de auditoria para detalhes.',
                        'url' => '/admin/system/audit',
                        'icon' => 'bi-shield-exclamation',
                        'color' => 'warning',
                        'recipients' => ['all_admins'],
                    ]);
                }

                return;
            }

            $hadOpenAlert = DB::table('admin_notifications')
                ->where('group', 'system')
                ->where('type', 'system_audit_alert')
                ->whereNull('read_at')
                ->exists();

            $alreadyResolvedToday = DB::table('admin_notifications')
                ->where('group', 'system')
                ->where('type', 'system_audit_ok')
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($hadOpenAlert && ! $alreadyResolvedToday) {
                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels' => ['in_app'],
                    'group' => 'system',
                    'type' => 'system_audit_ok',
                    'title' => 'Auditoria normalizada',
                    'message' => 'Todas as metricas criticas da auditoria voltaram para zero.',
                    'url' => '/admin/system/audit',
                    'icon' => 'bi-shield-check',
                    'color' => 'success',
                    'recipients' => ['all_admins'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[system:audit-full] Falha ao registrar alerta de auditoria: '.$e->getMessage());
        }
    }
}
