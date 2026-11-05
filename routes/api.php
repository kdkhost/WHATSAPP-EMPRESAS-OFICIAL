<?php

use App\Http\Controllers\Api\ViaCepController;
use App\Http\Controllers\Api\EvolutionGoWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * API publica consumida pelo frontend React (TS + Tailwind 4).
 * Rotas autenticadas usam Sanctum (cookie ou Bearer).
 *
 * Modulos individuais registram /api/* em seus proprios ServiceProviders.
 */

Route::prefix('v1')->group(function () {
    // ViaCEP proxy (evita CORS no front)
    Route::get('/cep/{cep}', [ViaCepController::class, 'lookup'])
        ->where('cep', '[0-9]+')
        ->name('api.cep.lookup');

    // Health do PWA
    Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);

    // Webhook Oficial da Meta (WhatsApp Cloud API Graph API v21.0)
    Route::get('/webhook', [\App\Modules\WhatsApp\Http\Controllers\MetaWebhookController::class, 'verify'])
        ->name('api.v1.meta.webhook.verify');
    Route::post('/webhook', [\App\Modules\WhatsApp\Http\Controllers\MetaWebhookController::class, 'receive'])
        ->name('api.v1.meta.webhook.receive');

    // Webhook Oficial do Mercado Pago para Assinaturas e Faturas (/api/v1/webhooks/mercadopago)
    Route::match(['get', 'post'], '/webhooks/mercadopago', [\App\Modules\MercadoPago\Http\Controllers\MercadoPagoWebhookController::class, 'handle'])
        ->name('api.v1.mercadopago.webhook');
});

// Alias raiz para o Webhook oficial da Meta (/api/v1/webhook)
Route::get('/webhook', [\App\Modules\WhatsApp\Http\Controllers\MetaWebhookController::class, 'verify']);
Route::post('/webhook', [\App\Modules\WhatsApp\Http\Controllers\MetaWebhookController::class, 'receive']);

// Alias para Webhook do Mercado Pago
Route::match(['get', 'post'], '/webhooks/mercadopago', [\App\Modules\MercadoPago\Http\Controllers\MercadoPagoWebhookController::class, 'handle']);

Route::post('/webhooks/evolution-go', EvolutionGoWebhookController::class)
    ->name('api.webhooks.evolution-go');

// Webhook do Addon Gestao Financeira (Open Finance / Pluggy)
Route::match(['get', 'post', 'head'], '/gestao-financeira/open-finance-webhook', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceWebhookController::class, 'handle'])
    ->name('api.gestao-financeira.open-finance-webhook');

Route::fallback(function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::notice('Rota API nao encontrada', [
        'path' => $request->path(),
        'method' => $request->method(),
        'ip' => $request->ip(),
    ]);

    return response()->json([
        'ok' => false,
        'error' => 'api_route_not_found',
        'message' => 'Endpoint nao encontrado.',
    ], 404);
})->name('api.fallback.404');
