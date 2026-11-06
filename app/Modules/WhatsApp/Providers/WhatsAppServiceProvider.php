<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\WhatsApp\Providers;

use App\Modules\WhatsApp\Http\Controllers\WhatsAppAdminController;
use App\Modules\WhatsApp\Services\MetaCloudApiClient;
use App\Modules\WhatsApp\Services\WhatsAppMetaClient;
use App\Modules\WhatsApp\Services\WhatsAppQuarantineService;
use App\Modules\WhatsApp\Services\WhatsAppQueuePlanner;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetaCloudApiClient::class);

        $this->app->singleton(WhatsAppMetaClient::class, function ($app) {
            return new WhatsAppMetaClient(
                metaClient: $app->make(MetaCloudApiClient::class)
            );
        });

        $this->app->singleton(WhatsAppSafetyService::class);
        $this->app->singleton(WhatsAppQuarantineService::class);
        $this->app->singleton(WhatsAppQueuePlanner::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/WhatsApp/Resources/views'), 'whatsapp');
        $this->loadMigrationsFrom(app_path('Modules/WhatsApp/database/migrations'));

        Route::middleware(['web', 'admin', '2fa', 'admin.cache'])->prefix('admin/whatsapp')->name('admin.whatsapp.')->group(function () {
            Route::get('/instance',               [WhatsAppAdminController::class, 'instance'])->name('instance');
            Route::get('/messages',               [WhatsAppAdminController::class, 'messages'])->name('messages');
            Route::get('/safety',                 [WhatsAppAdminController::class, 'safety'])->name('safety');
            Route::get('/safety/{instance}',      [WhatsAppAdminController::class, 'safetyDetail'])->name('safety.detail');
            Route::get('/status',                 [WhatsAppAdminController::class, 'status'])->name('status');
            Route::get('/diagnose',               [WhatsAppAdminController::class, 'diagnose'])->name('diagnose');
            Route::post('/config',                [WhatsAppAdminController::class, 'saveConfig'])->name('config');
            Route::post('/safety-config',         [WhatsAppAdminController::class, 'saveSafetyConfig'])->name('safety.config');
            Route::post('/test',                  [WhatsAppAdminController::class, 'testConnection'])->name('test');
            Route::post('/connect',               [WhatsAppAdminController::class, 'connect'])->name('connect');
            Route::post('/disconnect',            [WhatsAppAdminController::class, 'disconnect'])->name('disconnect');
            Route::post('/restart',               [WhatsAppAdminController::class, 'restart'])->name('restart');
            Route::post('/send-test',             [WhatsAppAdminController::class, 'sendTest'])->name('send-test');
            Route::post('/send-manual',           [WhatsAppAdminController::class, 'sendManual'])->name('send-manual');
            Route::get('/customers/search',       [WhatsAppAdminController::class, 'customerSearch'])->name('customers.search');
            Route::post('/render-template',       [WhatsAppAdminController::class, 'renderTemplate'])->name('render-template');
            Route::get('/messages/{id}/detail',   [WhatsAppAdminController::class, 'messageDetail'])->name('messages.detail');
            Route::post('/messages/{id}/resend',  [WhatsAppAdminController::class, 'resend'])->name('messages.resend');
            Route::post('/instances/{instance}/controls', [WhatsAppAdminController::class, 'updateSafetyControls'])->name('controls.update');
            Route::post('/instances/{instance}/quarantine/release', [WhatsAppAdminController::class, 'releaseQuarantine'])->name('quarantine.release');
            Route::post('/instances/{instance}/reset-warmup', [WhatsAppAdminController::class, 'resetWarmup'])->name('reset-warmup');
            Route::post('/instances/{instance}/disconnect', [WhatsAppAdminController::class, 'disconnectTenantInstance'])->name('instances.disconnect');
            Route::post('/instances/{instance}/config', [WhatsAppAdminController::class, 'saveInstanceConfig'])->name('instances.config');
            Route::get('/export-report',          [WhatsAppAdminController::class, 'exportReport'])->name('export-report');
            Route::get('/export-report/download', [WhatsAppAdminController::class, 'downloadExportReport'])->name('export-report.download');
        });
    }
}
