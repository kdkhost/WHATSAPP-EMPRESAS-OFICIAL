<?php

namespace App\Modules\HealthCheck\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::get('/health', \App\Modules\HealthCheck\Http\Controllers\HealthController::class)
            ->name('health.check');
    }
}
