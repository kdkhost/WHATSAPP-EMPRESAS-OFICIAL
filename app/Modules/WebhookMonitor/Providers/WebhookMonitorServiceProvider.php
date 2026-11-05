<?php

namespace App\Modules\WebhookMonitor\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WebhookMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/WebhookMonitor/Resources/views'), 'webhook-monitor');

        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/webhook-monitor')->name('admin.webhook-monitor.')->group(function () {
            $ctrl = \App\Modules\WebhookMonitor\Http\Controllers\WebhookMonitorController::class;
            Route::get('/',       [$ctrl, 'index'])->name('index');
            Route::get('/data',   [$ctrl, 'data'])->name('data');
            Route::get('/recent', [$ctrl, 'recent'])->name('recent');
            Route::get('/{id}',   [$ctrl, 'show'])->name('show');
        });
    }
}
