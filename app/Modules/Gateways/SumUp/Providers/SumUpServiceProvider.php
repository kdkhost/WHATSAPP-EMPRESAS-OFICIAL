<?php

namespace App\Modules\Gateways\SumUp\Providers;

use App\Modules\Gateways\Http\Controllers\GatewayAdminController;
use App\Modules\Gateways\SumUp\Services\SumUpGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * SumUp - Cartao + Link de pagamento.
 */
class SumUpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SumUpGateway::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Gateways/SumUp/Resources/views'), 'gateways-sumup');

        // Admin routes — APENAS SUPERADMIN (credenciais de gateway)
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/gateways')->name('admin.gateways.')->group(function () {
            Route::get('/sumup',         [GatewayAdminController::class, 'sumup'])->name('sumup');
            Route::post('/sumup',        [GatewayAdminController::class, 'saveSumup'])->name('sumup.save');
            Route::delete('/sumup',      [GatewayAdminController::class, 'deleteSumup'])->name('sumup.delete');
            Route::post('/sumup/test',   [GatewayAdminController::class, 'testSumup'])->name('sumup.test');
            Route::get('/sumup/recent',  [GatewayAdminController::class, 'recentSumup'])->name('sumup.recent');
        });

        Route::post('/webhooks/sumup/{token}', function (string $token, \Illuminate\Http\Request $request) {
            $result = app(SumUpGateway::class)->handleWebhook($request->all(), $token);
            return response()->json(['ok' => $result->processed, 'status' => $result->status]);
        })->name('webhook.sumup');
    }
}
