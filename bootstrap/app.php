<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\SystemAuditFullCommand::class,
        \App\Console\Commands\WhatsAppMetaDiagnoseCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin'             => \App\Http\Middleware\EnsureAdminAuthenticated::class,
            'admin.cache'       => \App\Http\Middleware\NoStoreAdminCache::class,
            'maintenance'       => \App\Modules\Maintenance\Http\Middleware\MaintenanceGate::class,
            'cron.token'        => \App\Http\Middleware\VerifyCronToken::class,
            'module.permission' => \App\Modules\Permissions\Http\Middleware\CheckModulePermission::class,
            'role'              => \App\Modules\Permissions\Http\Middleware\CheckRole::class,
            'installed'         => \App\Modules\Installer\Http\Middleware\RedirectIfNotInstalled::class,
            'webhook.ip'        => \App\Http\Middleware\ValidateWebhookIp::class,
            '2fa'               => \App\Http\Middleware\Ensure2FA::class,
            'throttle.route'    => \App\Http\Middleware\RateLimitByRoute::class,
            'public.api.access' => \App\Modules\PublicApi\Http\Middleware\EnsurePublicApiAccess::class,
            'public.api.throttle' => \App\Modules\PublicApi\Http\Middleware\ThrottlePublicApiRequests::class,
            'tenant.addon'      => \App\Modules\Marketplace\Http\Middleware\CheckTenantAddonAccess::class,
        ]);

        $middleware->append(\App\Http\Middleware\CronHeartbeat::class);

        $middleware->validateCsrfTokens(except: [
            'webhook',
            'api/webhook',
            'api/v1/webhook',
            'webhooks/*',
            'api/webhooks/*',
            'api/gestao-financeira/open-finance-webhook',
            'gestao-financeira/open-finance-webhook',
            // Página pública de fatura — acesso sem sessão autenticada
            'fatura/*/pix',
            'fatura/*/cartao',
        ]);

        $middleware->web(prepend: [
            \App\Modules\Installer\Http\Middleware\RedirectIfNotInstalled::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\SecureSession::class,
            \App\Http\Middleware\RateLimitByRoute::class,
        ]);

        $middleware->web(append: [
            \App\Modules\Maintenance\Http\Middleware\MaintenanceGate::class,
            \App\Http\Middleware\AuditTrail::class,
            \App\Http\Middleware\TrackSystemVisits::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\CorsPolicy::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\RateLimitByRoute::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        // Core
        \App\Providers\AppServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\ModuleServiceProvider::class,

        // Infraestrutura
        \App\Modules\Installer\Providers\InstallerServiceProvider::class,
        \App\Modules\Permissions\Providers\PermissionsServiceProvider::class,
        \App\Modules\I18n\Providers\I18nServiceProvider::class,

        // Modulos oficiais
        \App\Modules\Settings\Providers\SettingsServiceProvider::class,
        \App\Modules\Seo\Providers\SeoServiceProvider::class,
        \App\Modules\Maintenance\Providers\MaintenanceServiceProvider::class,
        \App\Modules\Pwa\Providers\PwaServiceProvider::class,
        \App\Modules\WhatsApp\Providers\WhatsAppServiceProvider::class,
        \App\Modules\Templates\Providers\TemplatesServiceProvider::class,
        \App\Modules\Billing\Providers\BillingServiceProvider::class,
        \App\Modules\Crm\Providers\CrmServiceProvider::class,
        \App\Modules\Gateways\MercadoPago\Providers\MercadoPagoServiceProvider::class,
        \App\Modules\Gateways\EfiPro\Providers\EfiProServiceProvider::class,
        \App\Modules\Gateways\SumUp\Providers\SumUpServiceProvider::class,
        \App\Modules\Security\Providers\SecurityServiceProvider::class,
        \App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
        \App\Modules\InvoiceEditor\Providers\InvoiceEditorServiceProvider::class,
        \App\Modules\Tenancy\Providers\TenancyServiceProvider::class,
        \App\Modules\Analytics\Providers\AnalyticsServiceProvider::class,
        \App\Modules\HealthCheck\Providers\HealthCheckServiceProvider::class,
        \App\Modules\Backup\Providers\BackupServiceProvider::class,
        \App\Modules\CustomerImport\Providers\CustomerImportServiceProvider::class,
        \App\Modules\Onboarding\Providers\OnboardingServiceProvider::class,
        \App\Modules\WebhookMonitor\Providers\WebhookMonitorServiceProvider::class,
        \App\Modules\LogViewer\Providers\LogViewerServiceProvider::class,
        \App\Modules\InvoicePdf\Providers\InvoicePdfServiceProvider::class,
        \App\Modules\Affiliates\Providers\AffiliatesServiceProvider::class,
        \App\Modules\WhmcsIntegration\Providers\WhmcsServiceProvider::class,
        \App\Modules\CpanelIntegration\Providers\CpanelServiceProvider::class,
        \App\Modules\AaPanel\Providers\AaPanelServiceProvider::class,
        \App\Modules\Holidays\Providers\HolidaysServiceProvider::class,
        \App\Modules\DataTable\Providers\DataTableServiceProvider::class,
        \App\Modules\EmailBilling\Providers\EmailBillingServiceProvider::class,
        \App\Modules\Contracts\Providers\ContractsServiceProvider::class,

        // Novos modulos (v1.8.0)
        \App\Modules\Chatbot\Providers\ChatbotServiceProvider::class,
        \App\Modules\PublicApi\Providers\PublicApiServiceProvider::class,
        \App\Modules\Marketplace\Providers\MarketplaceServiceProvider::class,
        \App\Modules\WhiteLabel\Providers\WhiteLabelServiceProvider::class,
        \App\Modules\Subscription\Providers\SubscriptionServiceProvider::class,
        \App\Modules\Reports\Providers\ReportsServiceProvider::class,
        \App\Modules\CrmAdvanced\Providers\CrmAdvancedServiceProvider::class,
        \App\Modules\OpenGraph\Providers\OpenGraphServiceProvider::class,
        \App\Modules\CacheManager\Providers\CacheManagerServiceProvider::class,
        \App\Modules\Marketing\Providers\MarketingServiceProvider::class,
    ])
    ->create();
