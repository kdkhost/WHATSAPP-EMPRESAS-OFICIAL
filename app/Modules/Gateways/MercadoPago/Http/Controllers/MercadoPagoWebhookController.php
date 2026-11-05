<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Webhook Oficial do Mercado Pago para Assinaturas e Faturas SaaS.
 */

namespace App\Modules\Gateways\MercadoPago\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MercadoPagoWebhookController extends Controller
{
    /**
     * Endpoint oficial: /api/v1/webhooks/mercadopago
     */
    public function handle(Request $request, MercadoPagoGateway $gateway): JsonResponse
    {
        $payload = $request->all();
        $token = (string) ($request->query('token') ?: $request->input('token') ?: '');

        Log::info('[MercadoPagoWebhook] Evento recebido:', [
            'type' => $payload['type'] ?? $payload['action'] ?? 'unknown',
            'data_id' => $payload['data']['id'] ?? $request->query('id'),
            'token' => $token ? 'presente' : 'ausente',
        ]);

        // Validação da assinatura x-signature (HMAC-SHA256) se configurado secret
        $webhookSecret = env('MERCADOPAGO_WEBHOOK_SECRET', config('gateways.mercadopago.webhook_secret'));
        $xSignature = $request->header('x-signature');
        if (!empty($webhookSecret) && !empty($xSignature)) {
            $parts = [];
            foreach (explode(',', $xSignature) as $segment) {
                $sub = explode('=', trim($segment), 2);
                if (count($sub) === 2) {
                    $parts[trim($sub[0])] = trim($sub[1]);
                }
            }
            $ts = $parts['ts'] ?? '';
            $v1 = $parts['v1'] ?? '';

            if ($ts && $v1) {
                $dataId = (string) ($payload['data']['id'] ?? $request->query('id') ?? '');
                $manifest = "id:{$dataId};request-id:{$request->header('x-request-id')};ts:{$ts};";
                $expectedHash = hash_hmac('sha256', $manifest, $webhookSecret);

                if (!hash_equals($expectedHash, $v1)) {
                    Log::warning('[MercadoPagoWebhook] Assinatura x-signature não conferiu, processando em modo seguro.');
                }
            }
        }

        $paymentId = (string) ($payload['data']['id'] ?? $request->query('id') ?? '');
        $type = $payload['type'] ?? $payload['action'] ?? '';

        if (($type === 'payment' || str_contains($type, 'payment')) && !empty($paymentId)) {
            // Consulta oficial na API do Mercado Pago
            $paymentDetails = $gateway->fetchPaymentDetails($paymentId);

            if ($paymentDetails) {
                $status = $paymentDetails['status'] ?? 'unknown';
                $externalRef = $paymentDetails['external_reference'] ?? '';

                Log::info("[MercadoPagoWebhook] Status do pagamento #{$paymentId}: {$status} (Ref: {$externalRef})");

                // 1. Pagamento de Fatura de Cliente
                if ($token) {
                    $gateway->handleWebhook($payload, $token);
                } elseif (str_starts_with($externalRef, 'INV-')) {
                    $invId = substr($externalRef, 4);
                    $inv = DB::table('invoices')->where('id', $invId)->first();
                    if ($inv && $inv->webhook_token) {
                        $gateway->handleWebhook($payload, $inv->webhook_token);
                    }
                }

                // 2. Pagamento de Assinatura SaaS de Tenant
                if (str_starts_with($externalRef, 'SUB-TENANT-') || str_starts_with($externalRef, 'PLAN-')) {
                    $tenantId = (int) preg_replace('/\D/', '', $externalRef);
                    $this->processTenantSubscription($tenantId, $status, $paymentDetails);
                }
            }
        }

        return response()->json(['ok' => true, 'message' => 'Webhook processado com sucesso']);
    }

    /**
     * Ativa, renova ou suspende a conta do Tenant automaticamente baseado no status do pagamento.
     */
    protected function processTenantSubscription(int $tenantId, string $status, array $paymentDetails): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }

        if ($status === 'approved') {
            Log::info("[MercadoPagoWebhook] Assinatura SaaS aprovada para o Tenant #{$tenantId}. Ativando conta.");

            $update = [
                'status' => 'active',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('tenants', 'subscription_status')) {
                $update['subscription_status'] = 'active';
            }
            if (Schema::hasColumn('tenants', 'subscription_expires_at')) {
                $update['subscription_expires_at'] = now()->addDays(30);
            }

            DB::table('tenants')->where('id', $tenantId)->update($update);

            // Grava notificação no painel do tenant
            if (Schema::hasTable('admin_notifications')) {
                DB::table('admin_notifications')->insert([
                    'group' => 'subscription',
                    'type' => 'plan_renewed',
                    'title' => 'Plano SaaS Ativado',
                    'message' => 'Seu plano SaaS foi ativado com sucesso após aprovação do pagamento no Mercado Pago.',
                    'url' => '/painel',
                    'icon' => 'bi-award-fill',
                    'color' => 'success',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } elseif (in_array($status, ['rejected', 'cancelled', 'refunded'], true)) {
            Log::warning("[MercadoPagoWebhook] Pagamento da assinatura do Tenant #{$tenantId} está {$status}.");

            if (Schema::hasColumn('tenants', 'subscription_status')) {
                DB::table('tenants')->where('id', $tenantId)->update([
                    'subscription_status' => 'suspended',
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

