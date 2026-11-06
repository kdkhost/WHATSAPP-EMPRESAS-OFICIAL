<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Permissions\Models\Permission;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Modulo de Notificacoes - central de envio multi-canal.
 *
 * Canais suportados:
 *   - in_app (sininho do admin)
 *   - push (WebPush via PWA)
 *   - email (SMTP/Mailgun/SES)
 *   - whatsapp (via Meta WhatsApp Cloud API Oficial)
 *   - sms (extensivel via addon)
 *
 * Cada modulo/addon pode disparar notificacoes via:
 *   app(NotificationDispatcher::class)->send($notification);
 */
class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationDispatcher::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Notifications/Resources/views'), 'notifications');

        // Registra permissions
        try {
            Permission::registerCrud('notifications', 'Notificacoes');
            Permission::register('notifications', 'send', 'Enviar notificacoes manualmente', 'Notificacoes');
            Permission::register('notifications', 'configure', 'Configurar canais de notificacao', 'Notificacoes');
        } catch (\Throwable) {}

        // Rotas admin
        Route::middleware(['web', 'admin', '2fa', 'module.permission:notifications.view'])
            ->prefix('admin/notifications-module')
            ->name('admin.notifications-module.')
            ->group(function () {
                $ctrl = \App\Modules\Notifications\Http\Controllers\NotificationsAdminController::class;
                Route::get('/',          [$ctrl, 'index'])->name('index');
                Route::get('/channels',  [$ctrl, 'channels'])->name('channels');
                Route::post('/channels', [$ctrl, 'updateChannels'])->name('channels.update');
                Route::get('/history',   [$ctrl, 'history'])->name('history');
                Route::post('/send',     [$ctrl, 'sendManual'])->name('send');
                Route::get('/templates', [$ctrl, 'templates'])->name('templates');
                Route::post('/templates',[$ctrl, 'storeTemplate'])->name('templates.store');
            });

        // Cron: processa fila de notificacoes pendentes
        CronManager::register(
            name: 'notifications.process_queue',
            callback: fn () => app(NotificationDispatcher::class)->processQueue(),
            intervalMinutes: 1,
            description: 'Processa fila de notificacoes pendentes (push, email, whatsapp).'
        );

        // Cron: limpeza de notificacoes antigas (>90 dias)
        CronManager::register(
            name: 'notifications.cleanup',
            callback: fn () => app(NotificationDispatcher::class)->cleanup(),
            intervalMinutes: 1440,
            description: 'Remove notificacoes lidas com mais de 90 dias.'
        );
    }
}
