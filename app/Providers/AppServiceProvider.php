<?php

namespace App\Providers;

use App\Jobs\CleanOldLogsJob;
use App\Jobs\UpdateBillingStatusJob;
use App\Modules\Gateways\Core\GatewayConfigSchema;
use App\Modules\Gateways\Core\GatewayFeatureResolver;
use App\Modules\Gateways\Core\GatewayFeeCalculator;
use App\Modules\Gateways\Core\GatewaySplitManager;
use App\Modules\Gateways\Core\GatewayValidationService;
use App\Modules\Gateways\Core\GatewayWebhookManager;
use App\Modules\Settings\Services\SettingsManager;
use App\Models\WhatsAppWebhookLog;
use App\Services\CronManager;
use App\Support\Addons\AddonAssets;
use App\Support\Addons\AddonLayout;
use App\Support\Addons\AddonMenu;
use App\Support\Addons\AddonPermissions;
use App\Support\Addons\AddonTheme;
use App\Support\Addons\AddonUI;
use App\Support\Theme\ThemeManager;
use App\Support\Theme\ThemeResolver;
use App\Support\Theme\ThemeVariables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeVariables::class);
        $this->app->singleton(ThemeResolver::class);
        $this->app->singleton(ThemeManager::class);

        $this->app->singleton(AddonLayout::class);
        $this->app->singleton(AddonTheme::class);
        $this->app->singleton(AddonMenu::class);
        $this->app->singleton(AddonAssets::class);
        $this->app->singleton(AddonPermissions::class);
        $this->app->singleton(AddonUI::class);

        $this->app->singleton(GatewayConfigSchema::class);
        $this->app->singleton(GatewayFeatureResolver::class);
        $this->app->singleton(GatewayWebhookManager::class);
        $this->app->singleton(GatewayFeeCalculator::class);
        $this->app->singleton(GatewaySplitManager::class);
        $this->app->singleton(GatewayValidationService::class);
    }

    public function boot(): void
    {
        $this->loadApplicationMigrations();
        $this->applyDynamicSettingsOverrides();

        // Forcar UTF-8 e timezone Sao Paulo
        date_default_timezone_set(config('app.timezone', 'America/Sao_Paulo'));
        Carbon::setLocale(config('app.locale', 'pt_BR'));
        setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

        // HTTPS em producao
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Strict models
        Model::shouldBeStrict(! $this->app->isProduction());

        // Pagination - usa Bootstrap 5 por padrao em todas as paginas
        Paginator::useBootstrapFive();

        // Diretivas Blade para mascaras (renderizam atributos data-mask)
        Blade::directive('mask', function ($expression) {
            return "<?php echo 'data-mask=\"' . $expression . '\"'; ?>";
        });

        CronManager::register(
            'billing.status.update',
            fn () => UpdateBillingStatusJob::dispatch(),
            15,
            'Atualiza status de cobrancas vencidas.'
        );

        CronManager::register(
            'logs.clean_old',
            fn () => CleanOldLogsJob::dispatch((int) config('modules.logs.retention_days', 90)),
            1440,
            'Remove logs antigos de WhatsApp, webhooks e auditoria.'
        );

        CronManager::register(
            'queue.database.work',
            fn () => Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => 45,
                '--tries' => 3,
            ]),
            1,
            'Processa filas pendentes em hospedagem compartilhada sem supervisor.'
        );
    }

    private function loadApplicationMigrations(): void
    {
        $paths = [];
        $sanctumMigrations = base_path('vendor/laravel/sanctum/database/migrations');

        if (is_dir($sanctumMigrations)) {
            $paths[] = $sanctumMigrations;
        }

        foreach (glob(app_path('Modules/*/database/migrations'), GLOB_ONLYDIR) ?: [] as $path) {
            $paths[] = $path;
        }

        if ($paths !== []) {
            $this->loadMigrationsFrom($paths);
        }
    }

    private function applyDynamicSettingsOverrides(): void
    {
        try {
            $settings = app(SettingsManager::class);

            foreach ($settings->group('whatsapp_anti_ban') as $key => $value) {
                config()->set('whatsapp_anti_ban.'.$key, $value);
            }
        } catch (\Throwable) {
            // Falha em settings nao pode impedir o bootstrap.
        }
    }
}
