<?php

namespace App\Modules\CacheManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CacheManagerController extends Controller
{
    /**
     * Tipos de cache disponiveis e seus comandos artisan.
     */
    protected array $caches = [
        'config' => [
            'label' => 'Configuracoes',
            'icon'  => 'bi-gear',
            'cmd'   => 'config:clear',
            'desc'  => 'Limpa o cache de configuracoes do Laravel',
        ],
        'route' => [
            'label' => 'Rotas',
            'icon'  => 'bi-signpost',
            'cmd'   => 'route:clear',
            'desc'  => 'Limpa o cache de rotas',
        ],
        'view' => [
            'label' => 'Views Compiladas',
            'icon'  => 'bi-file-earmark-code',
            'cmd'   => 'view:clear',
            'desc'  => 'Remove views Blade compiladas',
        ],
        'app' => [
            'label' => 'Cache da Aplicacao',
            'icon'  => 'bi-database',
            'cmd'   => 'cache:clear',
            'desc'  => 'Limpa o cache da aplicacao (Cache::*)',
        ],
        'event' => [
            'label' => 'Eventos',
            'icon'  => 'bi-broadcast',
            'cmd'   => 'event:clear',
            'desc'  => 'Limpa o cache de eventos',
        ],
        'compiled' => [
            'label' => 'Classes Compiladas',
            'icon'  => 'bi-code-slash',
            'cmd'   => 'clear-compiled',
            'desc'  => 'Remove classes compiladas (bootstrap/cache)',
        ],
        'opcache' => [
            'label' => 'OPcache PHP',
            'icon'  => 'bi-cpu',
            'cmd'   => 'opcache',
            'desc'  => 'Reinicia o OPcache quando a extensao estiver disponivel',
        ],
    ];

    public function isOpcacheAvailable(): bool
    {
        return extension_loaded('Zend OPcache')
            && function_exists('opcache_get_status')
            && is_array(@opcache_get_status(false));
    }

    public function index()
    {
        $opcacheAvailable = $this->isOpcacheAvailable();
        $caches = $this->caches;
        if (!$opcacheAvailable) {
            unset($caches['opcache']);
        }

        $stats = $this->getStats();
        $lastClear = Cache::get('cache_manager.last_clear');
        $autoEnabled = $this->autoClearEnabled();

        return view('cachemanager::index', compact('caches', 'stats', 'lastClear', 'autoEnabled', 'opcacheAvailable'));
    }

    /**
     * Limpar um cache especifico (AJAX).
     */
    public function clear(Request $request, string $type): JsonResponse
    {
        if (!isset($this->caches[$type])) {
            return response()->json(['ok' => false, 'message' => 'Tipo de cache invalido.'], 422);
        }

        if ($type === 'opcache' && !$this->isOpcacheAvailable()) {
            return response()->json(['ok' => false, 'message' => 'OPcache não está disponível neste ambiente PHP.'], 400);
        }

        try {
            $start = microtime(true);
            $output = $this->runCacheCommand($type);
            $duration = round((microtime(true) - $start) * 1000, 2);

            $this->logClear($type);
            $this->audit($request, 'cache_clear', ['type' => $type]);

            return response()->json([
                'ok' => true,
                'message' => $this->caches[$type]['label'] . ' limpo!',
                'duration_ms' => $duration,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Limpar TODOS os caches (AJAX).
     */
    public function clearAll(): JsonResponse
    {
        try {
            $start = microtime(true);
            $results = [];

            foreach ($this->caches as $type => $cfg) {
                if ($type === 'opcache' && !$this->isOpcacheAvailable()) {
                    continue;
                }

                try {
                    $this->runCacheCommand($type);
                    $results[$type] = ['ok' => true, 'label' => $cfg['label']];
                } catch (\Throwable $e) {
                    $results[$type] = ['ok' => false, 'label' => $cfg['label'], 'error' => $e->getMessage()];
                }
            }

            $duration = round((microtime(true) - $start) * 1000, 2);
            $this->logClear('all');
            $this->audit(request(), 'cache_clear_all', ['results' => $results]);

            return response()->json([
                'ok' => true,
                'message' => 'Todos os caches foram limpos!',
                'duration_ms' => $duration,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Otimizar (rebuild de caches em producao).
     */
    public function optimize(): JsonResponse
    {
        try {
            $start = microtime(true);
            Artisan::call('optimize');
            $duration = round((microtime(true) - $start) * 1000, 2);

            $this->logClear('optimize');
            $this->audit(request(), 'cache_optimize', []);

            return response()->json([
                'ok' => true,
                'message' => 'Sistema otimizado!',
                'duration_ms' => $duration,
                'output' => trim(Artisan::output()),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle limpeza automatica (cron).
     */
    public function toggleAuto(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        Cache::forever('cache_manager.auto_enabled', $enabled);

        // Registrar/atualizar job no cron_jobs usando o schema real do CronManager.
        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')->updateOrInsert(
                ['name' => 'cache:auto-clear'],
                [
                    'description' => 'Limpa caches do Laravel automaticamente',
                    'interval_min' => 1440,
                    'enabled' => $enabled,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->audit($request, 'cache_auto_toggle', ['enabled' => $enabled]);

        return response()->json([
            'ok' => true,
            'message' => $enabled ? 'Limpeza automatica ATIVADA (todo dia as 3h).' : 'Limpeza automatica DESATIVADA.',
        ]);
    }

    /**
     * Estatisticas do cache (tamanhos em disco).
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->getStats());
    }

    protected function getStats(): array
    {
        $config = $this->dirSize(base_path('bootstrap/cache'));
        $view = $this->dirSize(storage_path('framework/views'));
        $app = $this->dirSize(storage_path('framework/cache/data'));
        $logs = $this->dirSize(storage_path('logs'));
        $sessions = $this->dirSize(storage_path('framework/sessions'));

        $totalBytes = $config['size'] + $view['size'] + $app['size'] + $logs['size'] + $sessions['size'];
        $totalFiles = $config['files'] + $view['files'] + $app['files'] + $logs['files'] + $sessions['files'];

        $opcacheAvailable = $this->isOpcacheAvailable();
        $opcacheData = [
            'available' => false,
            'size' => 0,
            'human' => 'Indisponível',
            'files' => 0,
        ];

        if ($opcacheAvailable) {
            $status = @opcache_get_status(false);
            $usedMem = $status['memory_usage']['used_memory'] ?? 0;
            $cachedScripts = $status['opcache_statistics']['num_cached_scripts'] ?? 0;
            $opcacheData = [
                'available' => true,
                'size' => $usedMem,
                'human' => $this->humanSize($usedMem),
                'files' => $cachedScripts,
            ];
        }

        return [
            'config'   => $config,
            'view'     => $view,
            'app'      => $app,
            'logs'     => $logs,
            'sessions' => $sessions,
            'total'    => [
                'size'  => $totalBytes,
                'human' => $this->humanSize($totalBytes),
                'files' => $totalFiles,
            ],
            'opcache'  => $opcacheData,
        ];
    }

    protected function runCacheCommand(string $type): string
    {
        if ($type === 'opcache') {
            if (!function_exists('opcache_reset')) {
                return 'OPcache indisponivel neste PHP.';
            }

            return opcache_reset() ? 'OPcache reiniciado.' : 'OPcache nao estava ativo ou nao pode ser reiniciado.';
        }

        Artisan::call($this->caches[$type]['cmd']);

        return trim(Artisan::output());
    }

    protected function dirSize(string $dir): array
    {
        if (!is_dir($dir)) {
            return ['size' => 0, 'human' => '0 B', 'files' => 0];
        }

        $size = 0;
        $files = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $files++;
            }
        }

        return [
            'size' => $size,
            'human' => $this->humanSize($size),
            'files' => $files,
        ];
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    protected function logClear(string $type): void
    {
        $history = Cache::get('cache_manager.history', []);
        array_unshift($history, [
            'type' => $type,
            'at' => now()->toDateTimeString(),
            'user' => auth('admin')->user()->name ?? 'sistema',
        ]);
        $history = array_slice($history, 0, 20);
        Cache::put('cache_manager.history', $history, now()->addDays(30));
        Cache::put('cache_manager.last_clear', now()->toDateTimeString(), now()->addYears(1));
    }

    protected function audit(Request $request, string $action, array $changes): void
    {
        try {
            if (!Schema::hasTable('audit_logs')) {
                return;
            }

            $admin = auth('admin')->user();
            DB::table('audit_logs')->insert([
                'admin_user_id' => $admin?->id,
                'action' => $action,
                'subject_type' => 'cache_manager',
                'subject_id' => null,
                'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {}
    }

    protected function autoClearEnabled(): bool
    {
        if (!Schema::hasTable('cron_jobs')) {
            return (bool) Cache::get('cache_manager.auto_enabled', false);
        }

        $enabled = DB::table('cron_jobs')
            ->where('name', 'cache:auto-clear')
            ->value('enabled');

        if ($enabled === null) {
            return (bool) Cache::get('cache_manager.auto_enabled', false);
        }

        Cache::forever('cache_manager.auto_enabled', (bool) $enabled);

        return (bool) $enabled;
    }
}
