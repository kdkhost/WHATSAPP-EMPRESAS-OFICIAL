<?php

namespace App\Modules\WhiteLabel\Providers;

use App\Modules\WhiteLabel\Http\Controllers\WhiteLabelController;
use App\Modules\WhiteLabel\Http\Controllers\TenantWhiteLabelController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * White-label por tenant: cada tenant pode ter dominio proprio, logo, cores,
 * favicon, nome personalizado - como se fosse um sistema 100% dele.
 */
class WhiteLabelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/WhiteLabel/Resources/views'), 'whitelabel');

        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/white-label')->name('admin.whitelabel.')->group(function () {
            Route::get('/',  [WhiteLabelController::class, 'index'])->name('index');
            Route::post('/', [WhiteLabelController::class, 'save'])->name('save');
        });

        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/white-label')->name('tenant.whitelabel.')->group(function () {
            Route::get('/', [TenantWhiteLabelController::class, 'index'])->name('index');
            Route::post('/', [TenantWhiteLabelController::class, 'save'])->name('save');
            Route::get('/status', [TenantWhiteLabelController::class, 'status'])->name('status');
            Route::post('/validar-dns', [TenantWhiteLabelController::class, 'check'])->name('check');
        });
    }
}
