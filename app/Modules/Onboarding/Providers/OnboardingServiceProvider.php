<?php

namespace App\Modules\Onboarding\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OnboardingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Onboarding/Resources/views'), 'onboarding');

        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/onboarding')->name('tenant.onboarding.')->group(function () {
            $ctrl = \App\Modules\Onboarding\Http\Controllers\OnboardingController::class;
            Route::get('/',       [$ctrl, 'index'])->name('index');
            Route::get('/status', [$ctrl, 'status'])->name('status');
            Route::post('/skip',  [$ctrl, 'skip'])->name('skip');
        });
    }
}
