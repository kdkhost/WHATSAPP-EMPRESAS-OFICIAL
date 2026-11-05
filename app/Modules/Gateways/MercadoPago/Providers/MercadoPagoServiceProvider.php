<?php

namespace App\Modules\Gateways\MercadoPago\Providers;

use App\Modules\Gateways\Http\Controllers\GatewayAdminController;
use App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MercadoPagoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MercadoPagoGateway::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Gateways/MercadoPago/Resources/views'), 'gateways-mercadopago');

        // Admin routes — APENAS SUPERADMIN (credenciais de gateway)
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/gateways')->name('admin.gateways.')->group(function () {
            Route::get('/mercadopago',         [GatewayAdminController::class, 'mercadopago'])->name('mercadopago');
            Route::post('/mercadopago',        [GatewayAdminController::class, 'saveMercadopago'])->name('mercadopago.save');
            Route::delete('/mercadopago',      [GatewayAdminController::class, 'deleteMercadopago'])->name('mercadopago.delete');
            Route::post('/mercadopago/test',   [GatewayAdminController::class, 'testMercadopago'])->name('mercadopago.test');
            Route::get('/mercadopago/recent',  [GatewayAdminController::class, 'recentMercadopago'])->name('mercadopago.recent');
        });

        // Webhook dinamico - cada fatura tem um token unico (gerado em createCharge)
        Route::post('/webhooks/mercadopago/{token}', function (string $token, \Illuminate\Http\Request $request) {
            $result = app(MercadoPagoGateway::class)->handleWebhook($request->all(), $token);
            return response()->json(['ok' => $result->processed, 'status' => $result->status]);
        })->name('webhook.mercadopago');
    }
}
