<?php

namespace App\Modules\Analytics\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Analytics/Resources/views'), 'analytics');

        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/analytics')->name('admin.analytics.')->group(function () {
            $ctrl = \App\Modules\Analytics\Http\Controllers\AnalyticsController::class;
            Route::get('/',                             [$ctrl, 'index'])->name('index');
            Route::get('/data',                         [$ctrl, 'data'])->name('data');
            Route::get('/live-visitors',                [$ctrl, 'liveVisitors'])->name('live-visitors');
            Route::post('/sessions/terminate',         [$ctrl, 'terminateSession'])->name('sessions.terminate');
            Route::post('/sessions/terminate-others',  [$ctrl, 'terminateOtherSessions'])->name('sessions.terminate-others');
            Route::post('/sessions/toggle-concurrent', [$ctrl, 'toggleConcurrentSessions'])->name('sessions.toggle-concurrent');
        });
    }
}
