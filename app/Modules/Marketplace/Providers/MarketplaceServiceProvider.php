<?php

namespace App\Modules\Marketplace\Providers;

use App\Modules\Marketplace\Http\Middleware\CheckTenantAddonAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Marketplace de addons - loja interna onde devs publicam modulos pagos/gratuitos
 * e tenants instalam com 1 clique.
 *
 * Funcionalidades:
 *  - Catalogo de addons com busca, categorias, screenshots
 *  - Instalacao 1-clique (download + install via AddonManager)
 *  - Reviews e ratings por tenant
 *  - Painel do dev: publicar addon, ver downloads, receber pagamentos
 *  - Superadmin: aprovar/rejeitar addons antes de publicar
 */
class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Modules\Marketplace\Services\AddonMarketplaceService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Marketplace/Resources/views'), 'marketplace');
        $this->loadMigrationsFrom(app_path('Modules/Marketplace/database/migrations'));

        app('router')->aliasMiddleware('tenant.addon', CheckTenantAddonAccess::class);

        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/marketplace')->name('admin.marketplace.')->group(function () {
            $ctrl = \App\Modules\Marketplace\Http\Controllers\Admin\MarketplaceAdminController::class;
            Route::get('/', [$ctrl, 'index'])->name('index');
            Route::post('/submissions/{id}/approve', [$ctrl, 'approve'])->name('submissions.approve');
            Route::post('/submissions/{id}/reject', [$ctrl, 'reject'])->name('submissions.reject');
        });

        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/addons')->name('tenant.addons.')->group(function () {
            $ctrl = \App\Modules\Marketplace\Http\Controllers\Tenant\TenantMarketplaceController::class;
            Route::get('/', [$ctrl, 'index'])->name('index');
            Route::post('/submit', [$ctrl, 'submit'])->name('submit');
            Route::post('/{id}/subscribe', [$ctrl, 'subscribe'])->name('subscribe');
            Route::post('/rent', [$ctrl, 'rent'])->name('rent');
            Route::post('/{id}/deactivate', [$ctrl, 'deactivate'])->name('deactivate');
            Route::post('/{id}/uninstall', [$ctrl, 'uninstall'])->name('uninstall');
        });

        // Rotas dinamicas de tenant para addons ativos (fallback generico)
        // Nao usa middleware 'auth' para permitir superadmin em modo assistido (guard admin)
        Route::middleware(['web', '2fa', 'tenant.addon'])
            ->prefix('painel/addons/{slug}')
            ->name('tenant.addons.generated.')
            ->group(function () {
                $ctrl = \App\Modules\Marketplace\Http\Controllers\Tenant\TenantAddonController::class;
                Route::get('/{page?}', [$ctrl, 'show'])
                    ->where('page', '.*')
                    ->name('index');
            });

    }
}
