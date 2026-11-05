<?php

namespace App\Modules\CpanelIntegration\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CpanelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/CpanelIntegration/Resources/views'), 'cpanel');

        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/cpanel')->name('admin.cpanel.')->group(function () {
            $ctrl = \App\Modules\CpanelIntegration\Http\Controllers\CpanelController::class;
            Route::get('/',                       [$ctrl, 'index'])->name('index');
            Route::post('/',                      [$ctrl, 'store'])->name('store');
            Route::delete('/{id}',                [$ctrl, 'destroy'])->name('destroy');
            Route::get('/{id}/accounts',          [$ctrl, 'listAccounts'])->name('accounts');
            Route::post('/{id}/suspend',          [$ctrl, 'suspend'])->name('suspend');
            Route::post('/{id}/unsuspend',        [$ctrl, 'unsuspend'])->name('unsuspend');
            Route::post('/{id}/create-account',   [$ctrl, 'createAccount'])->name('create');
            Route::get('/{id}/logs',              [$ctrl, 'logs'])->name('logs');
        });

        // Painel do Tenant
        Route::middleware(['web', 'auth', '2fa', 'tenant.addon:cpanel'])->prefix('painel/cpanel')->name('tenant.cpanel.')->group(function () {
            $ctrl = \App\Modules\CpanelIntegration\Http\Controllers\CpanelController::class;
            Route::get('/',                       [$ctrl, 'index'])->name('index');
            Route::post('/',                      [$ctrl, 'store'])->name('store');
            Route::delete('/{id}',                [$ctrl, 'destroy'])->name('destroy');
            Route::get('/{id}/accounts',          [$ctrl, 'listAccounts'])->name('accounts');
            Route::post('/{id}/suspend',          [$ctrl, 'suspend'])->name('suspend');
            Route::post('/{id}/unsuspend',        [$ctrl, 'unsuspend'])->name('unsuspend');
            Route::post('/{id}/create-account',   [$ctrl, 'createAccount'])->name('create');
            Route::get('/{id}/logs',              [$ctrl, 'logs'])->name('logs');
        });

        // Rotas internas do addon (logs, configuracoes)
        Route::middleware(['web', 'auth', '2fa', 'tenant.addon:cpanel'])
            ->prefix('painel/addons/cpanel')
            ->name('tenant.addons.cpanel.')
            ->group(function () {
                $ctrl = \App\Modules\CpanelIntegration\Http\Controllers\Tenant\CpanelTenantController::class;
                Route::get('/logs', [$ctrl, 'logs'])->name('logs');
                Route::get('/configuracoes', [$ctrl, 'settings'])->name('settings');
            });
    }
}
