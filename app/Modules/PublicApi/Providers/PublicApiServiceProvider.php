<?php

namespace App\Modules\PublicApi\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * API REST publica documentada para integracoes externas.
 * Compativel com Zapier, N8N, Make, Power Automate.
 *
 * Autenticacao: Bearer token (API key do tenant gerada no painel).
 * Base: /api/v1/
 *
 * Endpoints:
 *  GET    /api/v1/customers          Listar clientes
 *  POST   /api/v1/customers          Criar cliente
 *  GET    /api/v1/invoices           Listar faturas
 *  POST   /api/v1/invoices           Criar fatura
 *  POST   /api/v1/invoices/{id}/send Enviar cobranca (WhatsApp/email)
 *  GET    /api/v1/dispatches         Listar envios
 *  GET    /api/v1/health             Status da API
 */
class PublicApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/PublicApi/Resources/views'), 'public-api');

        Route::middleware(['api', 'auth:sanctum', 'public.api.access', 'public.api.throttle'])->prefix('api/v1')->name('api.v1.')->group(function () {
            $ctrl = \App\Modules\PublicApi\Http\Controllers\ApiController::class;

            Route::get('/customers',          [$ctrl, 'customers'])->name('customers.index');
            Route::post('/customers',         [$ctrl, 'createCustomer'])->name('customers.store');
            Route::get('/invoices',           [$ctrl, 'invoices'])->name('invoices.index');
            Route::post('/invoices',          [$ctrl, 'createInvoice'])->name('invoices.store');
            Route::post('/invoices/{id}/send',[$ctrl, 'sendInvoice'])->name('invoices.send');
            Route::get('/dispatches',         [$ctrl, 'dispatches'])->name('dispatches.index');
            Route::get('/templates',          [$ctrl, 'templates'])->name('templates.index');
            Route::get('/stats',              [$ctrl, 'stats'])->name('stats');
        });

        // Documentacao Swagger UI
        Route::middleware('web')->get('/api/docs', function () {
            return view('public-api::docs');
        })->name('api.docs');

        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/api')->name('tenant.api.')->group(function () {
            $ctrl = \App\Modules\PublicApi\Http\Controllers\ApiTokenController::class;
            Route::get('/', [$ctrl, 'index'])->name('index');
            Route::post('/tokens', [$ctrl, 'store'])->name('tokens.store');
            Route::delete('/tokens/{id}', [$ctrl, 'destroy'])->name('tokens.destroy');
        });

        // OpenAPI 3.0 spec (JSON consumido pela Swagger UI)
        Route::get('/api/openapi.json', [\App\Modules\PublicApi\Http\Controllers\OpenApiController::class, 'spec'])->name('api.openapi');
    }
}
