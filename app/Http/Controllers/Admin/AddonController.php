<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Services\AddonManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Painel de gerenciamento de addons de terceiros.
 * Apenas superadmin pode instalar/desinstalar/ativar/desativar addons.
 */
class AddonController extends Controller
{
    public function __construct(protected AddonManagerService $addons) {}

    public function index()
    {
        $marketplace = $this->fetchMarketplace();
        $this->refreshInstalledBundledMarketplaceAddons($marketplace);
        
        $hiddenCoreModules = ['whmcs', 'cpanel', 'aapanel'];
        
        $list = collect($this->addons->discover())
            ->filter(fn ($addon) => !in_array($addon['slug'], $hiddenCoreModules))
            ->values()
            ->all();

        $localAddons = collect($list)->keyBy('slug');
        $marketplace = collect($marketplace)
            ->filter(fn ($item) => !in_array($item['slug'], $hiddenCoreModules))
            ->map(function ($item) use ($localAddons) {
                $local = $localAddons->get($item['slug']);
                $item['available_local'] = (bool) $local;
                $item['installed_local'] = (bool) ($local['installed'] ?? false);
                $item['enabled_local'] = (bool) ($local['db_enabled'] ?? false);
                $item['installable'] = (bool) (($item['download_url'] ?? null) || ($item['bundled'] ?? false));

                return $item;
            })
            ->values()
            ->all();

        return view('admin.addons.index', compact('list', 'marketplace'));
    }

    public function show(string $slug)
    {
        $addon = $this->addons->findBySlug($slug);
        abort_if(! $addon, 404);

        return view('admin.addons.show', compact('addon'));
    }

    public function install(string $slug): JsonResponse
    {
        $result = $this->addons->install($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function uninstall(string $slug): JsonResponse
    {
        $result = $this->addons->uninstall($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function enable(string $slug): JsonResponse
    {
        $result = $this->addons->enable($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function activate(string $slug): JsonResponse
    {
        $result = $this->addons->activate($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function disable(string $slug): JsonResponse
    {
        $result = $this->addons->disable($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function deactivate(string $slug): JsonResponse
    {
        $result = $this->addons->deactivate($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function update(string $slug): JsonResponse
    {
        $result = $this->addons->update($slug);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function migrate(string $slug): JsonResponse
    {
        $result = $this->addons->runMigrations($slug);
        $this->addons->clearSystemCache();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function reloadSidebar(): JsonResponse
    {
        $settingsManager = app(SettingsManager::class);

        return response()->json([
            'ok' => true,
            'html' => view('admin.partials.sidebar', compact('settingsManager'))->render(),
            'addons' => Schema::hasTable('addon_menus')
                ? DB::table('addon_menus')->where('active', true)->distinct('module_slug')->count('module_slug')
                : 0,
        ]);
    }

    /**
     * Marketplace - busca catalogo de addons disponiveis para download.
     * Usa um catalogo remoto (config) ou um catalogo local de exemplo.
     */
    public function marketplace(): JsonResponse
    {
        return response()->json($this->fetchMarketplace());
    }

    /**
     * Download e instala addon do marketplace remoto.
     */
    public function downloadFromMarketplace(string $slug): JsonResponse
    {
        $catalog = $this->fetchMarketplace();
        $addon = collect($catalog)->firstWhere('slug', $slug);

        if (! $addon) {
            return response()->json(['ok' => false, 'message' => 'Addon nao encontrado no marketplace.'], 404);
        }

        $url = $addon['download_url'] ?? null;
        if (! $url && ($addon['bundled'] ?? false)) {
            $result = $this->installBundledMarketplaceAddon($addon);

            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        if (! $url) {
            return response()->json(['ok' => false, 'message' => 'Este addon ainda nao possui pacote instalavel no marketplace.'], 422);
        }

        $targetDir = app_path('Addons/'.$this->addonDirectoryName($slug));
        if (is_dir($targetDir)) {
            return response()->json(['ok' => false, 'message' => 'Addon ja instalado localmente.'], 422);
        }

        try {
            $tmp = tempnam(sys_get_temp_dir(), 'addon_');
            $resp = Http::timeout(60)->get($url);
            if (! $resp->successful()) {
                return response()->json(['ok' => false, 'message' => 'Falha ao baixar addon.'], 502);
            }
            file_put_contents($tmp, $resp->body());

            $zip = new \ZipArchive;
            if ($zip->open($tmp) !== true) {
                @unlink($tmp);

                return response()->json(['ok' => false, 'message' => 'Pacote ZIP invalido.'], 422);
            }

            $extractDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'addon_extract_'.Str::random(12);
            File::ensureDirectoryExists($extractDir);
            $zip->extractTo($extractDir);
            $zip->close();
            @unlink($tmp);

            $sourceDir = $this->normalizedExtractedAddonDir($extractDir);
            if (! $sourceDir) {
                File::deleteDirectory($extractDir);
                return response()->json(['ok' => false, 'message' => 'Pacote sem addon.json ou module.json valido.'], 422);
            }

            File::ensureDirectoryExists(dirname($targetDir));
            File::moveDirectory($sourceDir, $targetDir);
            File::deleteDirectory($extractDir);

            // Auto-instala
            $result = $this->addons->install($slug);

            return response()->json([
                'ok' => $result['ok'] ?? false,
                'message' => $result['message'] ?? 'Addon baixado e instalado!',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Erro: '.$e->getMessage()], 500);
        }
    }

    /**
     * Catalogo do marketplace - cacheado por 1h.
     * Usa URL configurada no config ou catalogo embutido.
     */
    protected function fetchMarketplace(): array
    {
        return Cache::remember('addons.marketplace', 3600, function () {
            $url = config('modules.addons.marketplace_url');
            if ($url) {
                try {
                    $resp = Http::timeout(10)->get($url);
                    if ($resp->successful()) {
                        return $resp->json() ?: [];
                    }
                } catch (\Throwable) {
                }
            }

            // Catalogo embutido (exemplo) - extensivel via config
            return [
                [
                    'slug' => 'analytics-pro',
                    'name' => 'Analytics Pro',
                    'version' => '1.0.0',
                    'author' => 'Cobranca WhatsApp',
                    'description' => 'Relatorios avancados com graficos interativos, comparacao de periodos e exportacao PDF.',
                    'category' => 'Analytics',
                    'price' => 0,
                    'billing_cycle' => 'monthly',
                    'commercial' => true,
                    'audience' => 'Superadmin/Admin/Tenant',
                    'rating' => 4.8,
                    'downloads' => 1250,
                    'icon' => 'bi-graph-up-arrow',
                    'color' => 'success',
                    'download_url' => null,
                    'bundled' => true,
                ],
                [
                    'slug' => 'crm-deals',
                    'name' => 'CRM Deals',
                    'version' => '2.1.0',
                    'author' => 'Community',
                    'description' => 'Gestao de oportunidades de vendas (deals) com pipeline kanban e previsao de receita.',
                    'category' => 'CRM',
                    'price' => 49.90,
                    'billing_cycle' => 'monthly',
                    'commercial' => true,
                    'audience' => 'Superadmin/Admin/Tenant',
                    'rating' => 4.5,
                    'downloads' => 320,
                    'icon' => 'bi-briefcase',
                    'color' => 'primary',
                    'download_url' => null,
                    'bundled' => true,
                ],
                [
                    'slug' => 'sms-gateway',
                    'name' => 'SMS Gateway',
                    'version' => '1.2.3',
                    'author' => 'Community',
                    'description' => 'Envia cobrancas tambem por SMS via Twilio, Zenvia ou TotalVoice.',
                    'category' => 'Comunicacao',
                    'price' => 29.90,
                    'billing_cycle' => 'monthly',
                    'commercial' => true,
                    'audience' => 'Superadmin/Admin/Tenant',
                    'rating' => 4.2,
                    'downloads' => 580,
                    'icon' => 'bi-chat-text',
                    'color' => 'info',
                    'download_url' => null,
                    'bundled' => true,
                ],
                [
                    'slug' => 'asaas-gateway',
                    'name' => 'Asaas Gateway',
                    'version' => '1.0.5',
                    'author' => 'Community',
                    'description' => 'Integracao com gateway Asaas para PIX, Boleto e Cartao.',
                    'category' => 'Gateway',
                    'price' => 0,
                    'billing_cycle' => 'monthly',
                    'commercial' => true,
                    'audience' => 'Superadmin/Admin/Tenant',
                    'rating' => 4.7,
                    'downloads' => 890,
                    'icon' => 'bi-credit-card',
                    'color' => 'warning',
                    'download_url' => null,
                    'bundled' => true,
                ],
                [
                    'slug' => 'export-excel',
                    'name' => 'Export Excel Avancado',
                    'version' => '1.1.0',
                    'author' => 'Cobranca WhatsApp',
                    'description' => 'Exportacao Excel com multiplas abas, formatacao condicional e graficos embutidos.',
                    'category' => 'Utilitarios',
                    'price' => 0,
                    'billing_cycle' => 'monthly',
                    'commercial' => true,
                    'audience' => 'Superadmin/Admin/Tenant',
                    'rating' => 4.9,
                    'downloads' => 2100,
                    'icon' => 'bi-file-earmark-spreadsheet',
                    'color' => 'success',
                    'download_url' => null,
                    'bundled' => true,
                ],
            ];
        });
    }

    protected function refreshInstalledBundledMarketplaceAddons(array $marketplace): void
    {
        foreach ($marketplace as $addon) {
            if (! ($addon['bundled'] ?? false)) {
                continue;
            }

            $slug = Str::slug((string) ($addon['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $path = $this->installedAddonDirectory($slug);
            if ($path && $this->bundledAddonNeedsRefresh($path)) {
                $this->installBundledMarketplaceAddon($addon);
            }
        }
    }

    protected function installBundledMarketplaceAddon(array $addon): array
    {
        $slug = Str::slug((string) ($addon['slug'] ?? ''));
        if ($slug === '') {
            return ['ok' => false, 'message' => 'Slug do addon invalido.'];
        }

        $dirName = $this->addonDirectoryName($slug);
        $targetDir = app_path('Addons/'.$dirName);
        $legacyDir = app_path('Addons/'.$slug);
        if ($legacyDir !== $targetDir && is_dir($legacyDir) && ! is_dir($targetDir)) {
            File::deleteDirectory($legacyDir);
        }
        $name = (string) ($addon['name'] ?? Str::headline($slug));
        $description = (string) ($addon['description'] ?? 'Addon instalado pelo marketplace.');
        $version = (string) ($addon['version'] ?? '1.0.0');
        $author = (string) ($addon['author'] ?? config('app.name'));
        $icon = (string) ($addon['icon'] ?? 'bi-puzzle');
        $category = (string) ($addon['category'] ?? 'Marketplace');
        $price = (float) ($addon['price'] ?? 0);
        $billingCycle = (string) ($addon['billing_cycle'] ?? 'monthly');
        $audience = (string) ($addon['audience'] ?? 'Superadmin/Admin/Tenant');
        $namespace = 'App\\Addons\\'.$dirName;
        $routeBase = 'admin.addons.generated.'.str_replace('-', '_', $slug);
        $routeName = $routeBase.'.index';
        $settingsRouteName = $routeBase.'.settings';
        $reportsRouteName = $routeBase.'.reports';
        $recordsRouteName = $routeBase.'.records';
        $viewNamespace = 'addon-'.$slug;
        $tableName = 'addon_'.$dirName.'_records';
        $phpSlug = var_export($slug, true);
        $phpTableName = var_export($tableName, true);
        $phpName = var_export($name, true);
        $phpDescription = var_export($description, true);
        $phpVersion = var_export($version, true);
        $phpAuthor = var_export($author, true);
        $phpIcon = var_export($icon, true);
        $phpCategory = var_export($category, true);
        $phpBillingCycle = var_export($billingCycle, true);
        $phpAudience = var_export($audience, true);

        File::ensureDirectoryExists($targetDir.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers');
        File::ensureDirectoryExists($targetDir.DIRECTORY_SEPARATOR.'Jobs');
        File::ensureDirectoryExists($targetDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations');
        File::ensureDirectoryExists($targetDir.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views');

        File::put($targetDir.DIRECTORY_SEPARATOR.'addon.json', json_encode([
            'name' => $name,
            'slug' => $slug,
            'version' => $version,
            'author' => $author,
            'description' => $description,
            'provider' => $namespace.'\\AddonServiceProvider',
            'enabled' => false,
            'permissions' => [
                ['action' => 'view', 'label' => 'Visualizar '.$name],
                ['action' => 'manage', 'label' => 'Gerenciar '.$name],
            ],
            'menu' => [
                'title' => $name,
                'icon' => $icon,
                'route' => $routeName,
                'order' => 120,
                'items' => [
                    [
                        'title' => 'Dashboard',
                        'icon' => 'bi-speedometer2',
                        'route' => $routeName,
                        'order' => 120,
                    ],
                    [
                        'title' => 'Registros',
                        'icon' => 'bi-list-check',
                        'route' => $recordsRouteName,
                        'order' => 121,
                    ],
                    [
                        'title' => 'Configuracoes',
                        'icon' => 'bi-sliders',
                        'route' => $settingsRouteName,
                        'order' => 122,
                    ],
                    [
                        'title' => 'Relatorios',
                        'icon' => 'bi-graph-up',
                        'route' => $reportsRouteName,
                        'order' => 123,
                    ],
                ],
            ],
            'marketplace' => [
                'bundled' => true,
                'commercial' => true,
                'category' => $category,
                'price' => $price,
                'billing_cycle' => $billingCycle,
                'audience' => $audience,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        File::put($targetDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.date('Y_m_d_His').'_create_'.$dirName.'_records_table.php', <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('{$tableName}')) {
            Schema::create('{$tableName}', function (Blueprint \$table) {
                \$table->id();
                \$table->string('title');
                \$table->string('status')->default('pending')->index();
                \$table->decimal('amount', 12, 2)->default(0);
                \$table->json('meta')->nullable();
                \$table->timestamp('processed_at')->nullable();
                \$table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP);

        File::put($targetDir.DIRECTORY_SEPARATOR.'AddonServiceProvider.php', <<<PHP
<?php

namespace {$namespace};

use App\Services\CronManager;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use {$namespace}\Http\Controllers\DashboardController;
use {$namespace}\Jobs\ProcessAddonJob;

class AddonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \$this->loadViewsFrom(__DIR__.'/Resources/views', '{$viewNamespace}');

        CronManager::register(
            name: 'addons.{$slug}.sync',
            callback: fn () => app(ProcessAddonJob::class)->handle(),
            intervalMinutes: (int) app(SettingsManager::class)->get('addons_{$slug}.cron_interval', 60),
            description: 'Processa rotinas automaticas do addon {$name}.'
        );

        Route::middleware(['web', 'admin'])
            ->prefix('admin/addons/{$slug}')
            ->name('{$routeBase}.')
            ->group(function () {
                Route::get('/', [DashboardController::class, 'index'])->name('index');
                Route::get('/registros', [DashboardController::class, 'records'])->name('records');
                Route::post('/registros', [DashboardController::class, 'storeRecord'])->name('records.store');
                Route::delete('/registros/{id}', [DashboardController::class, 'destroyRecord'])->name('records.destroy');
                Route::get('/configuracoes', [DashboardController::class, 'settings'])->name('settings');
                Route::post('/configuracoes', [DashboardController::class, 'saveSettings'])->name('settings.save');
                Route::get('/relatorios', [DashboardController::class, 'reports'])->name('reports');
                Route::post('/cron/run', [DashboardController::class, 'runCron'])->name('cron.run');
            });
    }
}
PHP);

        File::put($targetDir.DIRECTORY_SEPARATOR.'Jobs'.DIRECTORY_SEPARATOR.'ProcessAddonJob.php', <<<PHP
<?php

namespace {$namespace}\Jobs;

use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcessAddonJob
{
    public function handle(): void
    {
        \$settings = app(SettingsManager::class);
        if (! (bool) \$settings->get('addons_{$slug}.cron_enabled', true)) {
            return;
        }

        \$settings->set('addons_{$slug}.last_cron_at', now()->toDateTimeString(), 'string');

        if (Schema::hasTable('{$tableName}')) {
            DB::table('{$tableName}')->insert([
                'title' => 'Execucao automatica do cron',
                'status' => 'processed',
                'amount' => 0,
                'meta' => json_encode(['source' => 'cron', 'module' => '{$slug}'], JSON_UNESCAPED_UNICODE),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
PHP);

        File::put($targetDir.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.'DashboardController.php', <<<PHP
<?php

namespace {$namespace}\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Services\CronManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected array \$module = [
        'slug' => {$phpSlug},
        'name' => {$phpName},
        'description' => {$phpDescription},
        'version' => {$phpVersion},
        'author' => {$phpAuthor},
        'icon' => {$phpIcon},
        'category' => {$phpCategory},
        'price' => {$price},
        'billing_cycle' => {$phpBillingCycle},
        'audience' => {$phpAudience},
        'table' => {$phpTableName},
    ];

    public function index(SettingsManager \$settings): View
    {
        return \$this->render('dashboard', \$settings);
    }

    public function records(SettingsManager \$settings): View
    {
        return \$this->render('records', \$settings);
    }

    public function settings(SettingsManager \$settings): View
    {
        return \$this->render('settings', \$settings);
    }

    public function reports(SettingsManager \$settings): View
    {
        return \$this->render('reports', \$settings);
    }

    public function saveSettings(Request \$request, SettingsManager \$settings): JsonResponse
    {
        \$data = \$request->validate([
            'enabled_for_sale' => ['nullable', 'boolean'],
            'monthly_fee' => ['nullable', 'string', 'max:20'],
            'public_description' => ['nullable', 'string', 'max:500'],
            'api_provider' => ['nullable', 'string', 'max:80'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'cron_enabled' => ['nullable', 'boolean'],
            'cron_interval' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        \$settings->set('addons_{$slug}.enabled_for_sale', (bool) (\$data['enabled_for_sale'] ?? false), 'bool');
        \$settings->set('addons_{$slug}.monthly_fee', (string) (\$data['monthly_fee'] ?? ''), 'string');
        \$settings->set('addons_{$slug}.public_description', (string) (\$data['public_description'] ?? ''), 'string');
        \$settings->set('addons_{$slug}.api_provider', (string) (\$data['api_provider'] ?? ''), 'string');
        \$settings->set('addons_{$slug}.api_key', (string) (\$data['api_key'] ?? ''), 'string');
        \$settings->set('addons_{$slug}.webhook_url', (string) (\$data['webhook_url'] ?? ''), 'string');
        \$settings->set('addons_{$slug}.cron_enabled', (bool) (\$data['cron_enabled'] ?? false), 'bool');
        \$settings->set('addons_{$slug}.cron_interval', (int) (\$data['cron_interval'] ?? 60), 'int');

        return response()->json(['ok' => true, 'message' => 'Configuracoes do modulo salvas.']);
    }

    public function storeRecord(Request \$request): JsonResponse
    {
        \$data = \$request->validate([
            'title' => ['required', 'string', 'max:160'],
            'status' => ['required', 'string', 'in:pending,processing,processed,failed'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Schema::hasTable({$phpTableName})) {
            return response()->json(['ok' => false, 'message' => 'Tabela operacional do addon nao foi migrada.'], 422);
        }

        DB::table({$phpTableName})->insert([
            'title' => \$data['title'],
            'status' => \$data['status'],
            'amount' => (float) (\$data['amount'] ?? 0),
            'meta' => json_encode(['notes' => \$data['notes'] ?? null], JSON_UNESCAPED_UNICODE),
            'processed_at' => \$data['status'] === 'processed' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Registro salvo no modulo.']);
    }

    public function destroyRecord(int \$id): JsonResponse
    {
        if (! Schema::hasTable({$phpTableName})) {
            return response()->json(['ok' => false, 'message' => 'Tabela operacional do addon nao foi migrada.'], 422);
        }

        DB::table({$phpTableName})->where('id', \$id)->delete();

        return response()->json(['ok' => true, 'message' => 'Registro removido.']);
    }

    public function runCron(CronManager \$cron): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Cron do modulo executado.',
            'result' => \$cron->runJob('addons.{$slug}.sync'),
        ]);
    }

    protected function render(string \$screen, SettingsManager \$settings): View
    {
        \$config = [
            'enabled_for_sale' => (bool) \$settings->get('addons_{$slug}.enabled_for_sale', true),
            'monthly_fee' => (string) \$settings->get('addons_{$slug}.monthly_fee', number_format({$price}, 2, ',', '.')),
            'public_description' => (string) \$settings->get('addons_{$slug}.public_description', {$phpDescription}),
            'api_provider' => (string) \$settings->get('addons_{$slug}.api_provider', ''),
            'api_key' => (string) \$settings->get('addons_{$slug}.api_key', ''),
            'webhook_url' => (string) \$settings->get('addons_{$slug}.webhook_url', ''),
            'cron_enabled' => (bool) \$settings->get('addons_{$slug}.cron_enabled', true),
            'cron_interval' => (int) \$settings->get('addons_{$slug}.cron_interval', 60),
            'last_cron_at' => (string) \$settings->get('addons_{$slug}.last_cron_at', ''),
        ];
        if (Schema::hasTable({$phpTableName})) {
            \$records = DB::table({$phpTableName})->latest('id')->limit(50)->get();
            \$stats = [
                'total' => DB::table({$phpTableName})->count(),
                'pending' => DB::table({$phpTableName})->where('status', 'pending')->count(),
                'processed' => DB::table({$phpTableName})->where('status', 'processed')->count(),
                'failed' => DB::table({$phpTableName})->where('status', 'failed')->count(),
                'amount' => (float) DB::table({$phpTableName})->sum('amount'),
            ];
        } else {
            \$records = collect();
            \$stats = ['total' => 0, 'pending' => 0, 'processed' => 0, 'failed' => 0, 'amount' => 0.0];
        }
        \$cronState = Schema::hasTable('cron_jobs')
            ? DB::table('cron_jobs')->where('name', 'addons.{$slug}.sync')->first()
            : null;

        return view('{$viewNamespace}::index', [
            'screen' => \$screen,
            'module' => \$this->module,
            'config' => \$config,
            'records' => \$records,
            'stats' => \$stats,
            'cronState' => \$cronState,
        ]);
    }

}
PHP);

        File::put($targetDir.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'index.blade.php', <<<BLADE
@extends('admin.layouts.app')

@section('title', \$module['name'])

@section('content')
<div class="container-fluid admin-module-shell addon-module-shell">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi {{ \$module['icon'] }} me-2 text-primary"></i>{{ \$module['name'] }}</h3>
            <p class="text-muted mb-0">{{ \$module['description'] }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Marketplace</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle">Comercializavel</span>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">R$ {{ \$config['monthly_fee'] }}/mes</span>
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link {{ \$screen === 'dashboard' ? 'active' : '' }}" href="{{ route('{$routeName}') }}"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link {{ \$screen === 'records' ? 'active' : '' }}" href="{{ route('{$recordsRouteName}') }}"><i class="bi bi-list-check me-1"></i> Registros</a></li>
        <li class="nav-item"><a class="nav-link {{ \$screen === 'settings' ? 'active' : '' }}" href="{{ route('{$settingsRouteName}') }}"><i class="bi bi-sliders me-1"></i> Configuracoes</a></li>
        <li class="nav-item"><a class="nav-link {{ \$screen === 'reports' ? 'active' : '' }}" href="{{ route('{$reportsRouteName}') }}"><i class="bi bi-graph-up me-1"></i> Relatorios</a></li>
    </ul>

    @if(\$screen === 'settings')
    <div class="card">
        <div class="card-header"><strong>Configuracao comercial do modulo</strong></div>
        <div class="card-body">
            <form class="addon-generated-settings" method="POST" action="{{ route('{$routeBase}.settings.save') }}">
                @csrf
                <input type="hidden" name="enabled_for_sale" value="0">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="enabled_for_sale" value="1" id="enabled_for_sale" {{ \$config['enabled_for_sale'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="enabled_for_sale">Disponibilizar para comercializacao</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Valor mensal</label>
                        <input type="text" class="form-control money-mask" name="monthly_fee" value="{{ \$config['monthly_fee'] }}" placeholder="0,00">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Descricao publica</label>
                        <input type="text" class="form-control" name="public_description" value="{{ \$config['public_description'] }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Provedor/API</label>
                        <input type="text" class="form-control" name="api_provider" value="{{ \$config['api_provider'] }}" placeholder="Ex: Twilio, Asaas, interno">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Chave da API</label>
                        <input type="password" class="form-control" name="api_key" value="{{ \$config['api_key'] }}" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Webhook/Callback</label>
                        <input type="url" class="form-control" name="webhook_url" value="{{ \$config['webhook_url'] }}" placeholder="https://">
                    </div>
                    <div class="col-md-4">
                        <input type="hidden" name="cron_enabled" value="0">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="cron_enabled" value="1" id="cron_enabled" {{ \$config['cron_enabled'] ? 'checked' : '' }}>
                            <label class="form-check-label" for="cron_enabled">Cron interno ativo</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Intervalo cron (minutos)</label>
                        <input type="number" min="1" max="1440" class="form-control" name="cron_interval" value="{{ \$config['cron_interval'] }}">
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar configuracoes</button>
                </div>
            </form>
        </div>
    </div>
    @elseif(\$screen === 'records')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Novo registro operacional</strong></div>
                <div class="card-body">
                    <form class="addon-record-form" method="POST" action="{{ route('{$routeBase}.records.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Titulo</label>
                            <input type="text" class="form-control" name="title" required maxlength="160">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="pending">Pendente</option>
                                <option value="processing">Processando</option>
                                <option value="processed">Processado</option>
                                <option value="failed">Falhou</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <input type="number" min="0" step="0.01" class="form-control" name="amount" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observacoes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                        <button class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i> Salvar registro</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Registros recentes</strong>
                    <button class="btn btn-sm btn-outline-primary addon-run-cron" data-url="{{ route('{$routeBase}.cron.run') }}"><i class="bi bi-play-circle me-1"></i> Rodar cron</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Titulo</th><th>Status</th><th class="text-end">Valor</th><th></th></tr></thead>
                        <tbody>
                        @forelse(\$records as \$record)
                            <tr>
                                <td>{{ \$record->title }}<div class="small text-muted">{{ \Illuminate\Support\Carbon::parse(\$record->created_at)->format('d/m/Y H:i') }}</div></td>
                                <td><span class="badge bg-secondary">{{ \$record->status }}</span></td>
                                <td class="text-end">R$ {{ number_format((float) \$record->amount, 2, ',', '.') }}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger addon-delete-record" data-url="{{ route('{$routeBase}.records.destroy', \$record->id) }}"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Nenhum registro operacional criado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @elseif(\$screen === 'reports')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Relatorios do modulo</strong>
            <button class="btn btn-sm btn-outline-primary addon-run-cron" data-url="{{ route('{$routeBase}.cron.run') }}"><i class="bi bi-play-circle me-1"></i> Executar cron</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Indicador</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Registros totais</td><td><span class="badge bg-success">Disponivel</span></td><td class="text-end">{{ \$stats['total'] }}</td></tr>
                    <tr><td>Pendentes</td><td><span class="badge bg-warning">Fila operacional</span></td><td class="text-end">{{ \$stats['pending'] }}</td></tr>
                    <tr><td>Processados</td><td><span class="badge bg-primary">Cron/manual</span></td><td class="text-end">{{ \$stats['processed'] }}</td></tr>
                    <tr><td>Falhas</td><td><span class="badge bg-danger">Acompanhar</span></td><td class="text-end">{{ \$stats['failed'] }}</td></tr>
                    <tr><td>Volume financeiro</td><td><span class="badge bg-info">R$</span></td><td class="text-end">R$ {{ number_format(\$stats['amount'], 2, ',', '.') }}</td></tr>
                    <tr><td>Ultimo cron</td><td><span class="badge bg-secondary">{{ \$cronState?->last_status ?? 'sem execucao' }}</span></td><td class="text-end">{{ \$config['last_cron_at'] ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Registros</div><div class="fs-4 fw-bold text-success">{{ \$stats['total'] }}</div><small>Operacoes monitoradas pelo modulo.</small></div></div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Comercializacao</div><div class="fs-4 fw-bold">{{ \$config['enabled_for_sale'] ? 'Liberada' : 'Pausada' }}</div><small>Controle feito em Configuracoes.</small></div></div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Valor</div><div class="fs-4 fw-bold">R$ {{ \$config['monthly_fee'] }}</div><small>Cobranca mensal sugerida.</small></div></div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Versao</div><div class="fs-4 fw-bold">v{{ \$module['version'] }}</div><small>{{ \$module['author'] }}</small></div></div>
        </div>
    </div>
    <div class="card mt-3">
        <div class="card-header"><strong>Operacao do modulo</strong></div>
        <div class="card-body">
            <p class="text-muted mb-3">{{ \$config['public_description'] }}</p>
            <div class="row g-3">
                <div class="col-md-4"><div class="border rounded p-3 h-100"><i class="bi bi-shield-check text-success me-1"></i> Permissoes registradas para visualizacao e gerenciamento.</div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><i class="bi bi-clock-history text-primary me-1"></i> Cron interno: {{ \$config['cron_enabled'] ? 'ativo' : 'pausado' }} a cada {{ \$config['cron_interval'] }} minutos.</div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><i class="bi bi-plug text-warning me-1"></i> API: {{ \$config['api_provider'] ?: 'nao configurada' }}.</div></div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.addon-generated-settings').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"], button:not([type])');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });
            const data = await response.json();
            if (data.ok) {
                window.toast.success(data.message || 'Configuracoes salvas.');
            } else {
                window.toast.error(data.message || 'Nao foi possivel salvar.');
            }
        } catch (e) {
            window.toast.error('Erro de rede.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
});

document.querySelectorAll('.addon-record-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"], button:not([type])');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });
            const data = await response.json();
            if (data.ok) {
                window.toast.success(data.message || 'Registro salvo.');
                window.location.reload();
            } else {
                window.toast.error(data.message || 'Nao foi possivel salvar.');
            }
        } catch (e) {
            window.toast.error('Erro de rede.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
});

document.querySelectorAll('.addon-delete-record').forEach((button) => {
    button.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Remover registro?',
            text: 'Esta acao remove apenas o registro operacional deste addon.',
            showCancelButton: true,
            confirmButtonText: 'Remover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!ok.isConfirmed) return;

        try {
            const response = await fetch(button.dataset.url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.ok) {
                window.toast.success(data.message || 'Registro removido.');
                window.location.reload();
            } else {
                window.toast.error(data.message || 'Nao foi possivel remover.');
            }
        } catch (e) {
            window.toast.error('Erro de rede.');
        }
    });
});

document.querySelectorAll('.addon-run-cron').forEach((button) => {
    button.addEventListener('click', async () => {
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Executando...';
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.ok) {
                window.toast.success(data.message || 'Cron executado.');
                window.location.reload();
            } else {
                window.toast.error(data.message || 'Nao foi possivel executar.');
            }
        } catch (e) {
            window.toast.error('Erro de rede.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
});
</script>
@endpush
BLADE);

        $result = $this->addons->install($slug);

        return [
            'ok' => $result['ok'] ?? false,
            'message' => $result['message'] ?? 'Addon instalado pelo marketplace.',
            'reload_menu' => true,
        ];
    }

    protected function normalizedExtractedAddonDir(string $extractDir): ?string
    {
        foreach (File::directories($extractDir) as $dir) {
            if (is_file($dir.DIRECTORY_SEPARATOR.'addon.json') || is_file($dir.DIRECTORY_SEPARATOR.'module.json')) {
                return $dir;
            }
        }

        if (is_file($extractDir.DIRECTORY_SEPARATOR.'addon.json') || is_file($extractDir.DIRECTORY_SEPARATOR.'module.json')) {
            return $extractDir;
        }

        return null;
    }

    protected function addonDirectoryName(string $slug): string
    {
        return Str::snake(str_replace('-', '_', $slug));
    }

    protected function installedAddonDirectory(string $slug): ?string
    {
        foreach (array_unique([$this->addonDirectoryName($slug), $slug]) as $dir) {
            $path = app_path('Addons/'.$dir);
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function bundledAddonNeedsRefresh(string $path): bool
    {
        $manifest = null;
        foreach (['module.json', 'addon.json'] as $file) {
            $candidate = $path.DIRECTORY_SEPARATOR.$file;
            if (is_file($candidate)) {
                $manifest = json_decode(File::get($candidate), true) ?: [];
                break;
            }
        }

        if (! is_array($manifest)) {
            return true;
        }

        return ! data_get($manifest, 'marketplace.commercial')
            || count((array) data_get($manifest, 'menu.items', [])) < 3
            || ! is_file($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'index.blade.php');
    }
}
