<?php

namespace App\Modules\Gateways\EfiPro\Providers;

use App\Modules\Gateways\Http\Controllers\GatewayAdminController;
use App\Modules\Gateways\EfiPro\Services\EfiProGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Efi Pro (antigo Gerencianet) - PIX + Boleto + Cartao.
 */
class EfiProServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EfiProGateway::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Gateways/EfiPro/Resources/views'), 'gateways-efipro');

        // Admin routes — APENAS SUPERADMIN (credenciais de gateway)
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/gateways')->name('admin.gateways.')->group(function () {
            Route::get('/efipro',         [GatewayAdminController::class, 'efipro'])->name('efipro');
            Route::post('/efipro',        [GatewayAdminController::class, 'saveEfipro'])->name('efipro.save');
            Route::delete('/efipro',      [GatewayAdminController::class, 'deleteEfipro'])->name('efipro.delete');
            Route::post('/efipro/test',   [GatewayAdminController::class, 'testEfipro'])->name('efipro.test');
            Route::post('/efipro/certificate', [GatewayAdminController::class, 'uploadCertificateEfipro'])->name('efipro.certificate');
            Route::delete('/efipro/certificate', [GatewayAdminController::class, 'deleteCertificateEfipro'])->name('efipro.certificate.delete');
            Route::get('/efipro/recent',  [GatewayAdminController::class, 'recentEfipro'])->name('efipro.recent');
        });

        Route::post('/webhooks/efipro/{token}', function (string $token, \Illuminate\Http\Request $request) {
            $result = app(EfiProGateway::class)->handleWebhook($request->all(), $token);
            return response()->json(['ok' => $result->processed, 'status' => $result->status]);
        })->name('webhook.efipro');
    }
}
