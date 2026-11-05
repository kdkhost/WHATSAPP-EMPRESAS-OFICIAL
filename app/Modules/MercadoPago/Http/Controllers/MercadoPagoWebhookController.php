<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Controlador Oficial do Webhook Mercado Pago.
 */

namespace App\Modules\MercadoPago\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MercadoPago\Services\MercadoPagoPaymentService;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MercadoPagoWebhookController extends Controller
{
    /**
     * Recepção e Processamento Oficial de Webhooks do Mercado Pago (/api/v1/webhooks/mercadopago).
     */
    public function handle(Request $request, MercadoPagoPaymentService $paymentService): JsonResponse
    {
        $payload = $request->all();
        $token = (string) ($request->query('token') ?: $request->input('token') ?: '');

        $paymentId = (string) ($payload['data']['id'] ?? $request->query('id') ?? $payload['id'] ?? '');
        $type = $payload['type'] ?? $payload['action'] ?? $request->query('topic') ?? '';

        Log::info('[MercadoPagoWebhook] Notificação recebida:', [
            'type' => $type,
            'payment_id' => $paymentId,
            'token' => $token ? 'presente' : 'ausente'
        ]);

        if (empty($paymentId) || !is_numeric($paymentId)) {
            return response()->json(['ok' => true, 'message' => 'Notificação sem ID de pagamento ignorada.']);
        }

        // Consulta oficial e direta do status do pagamento na API do Mercado Pago
        $paymentDetails = $paymentService->fetchPayment($paymentId);
        if (!$paymentDetails) {
            Log::warning("[MercadoPagoWebhook] Não foi possível obter os detalhes do pagamento #{$paymentId} na API do MP.");
            return response()->json(['ok' => true, 'message' => 'Pagamento não localizado na API.']);
        }

        $status = $paymentDetails['status'] ?? 'unknown';
        $externalRef = $paymentDetails['external_reference'] ?? '';

        Log::info("[MercadoPagoWebhook] Pagamento #{$paymentId} verificado: Status = {$status} (Ref: {$externalRef})");

        // 1. Processamento de Faturas de Clientes Finais
        if ($status === 'approved') {
            if ($token) {
                $invoice = DB::table('invoices')->where('token', $token)->orWhere('webhook_token', $token)->first();
                if ($invoice) {
                    $paymentService->markInvoiceAsPaid($invoice->id, $paymentId, $paymentDetails);
                }
            } elseif (str_starts_with($externalRef, 'INV-')) {
                $invoiceId = (int) substr($externalRef, 4);
                $paymentService->markInvoiceAsPaid($invoiceId, $paymentId, $paymentDetails);
            }
        }

        // 2. Processamento de Assinaturas SaaS de Tenants
        if (str_starts_with($externalRef, 'SUB-TENANT-') || str_starts_with($externalRef, 'PLAN-TENANT-')) {
            $tenantId = (int) preg_replace('/\D/', '', $externalRef);
            $this->processTenantSubscription($tenantId, $status, $paymentDetails);
        }

        return response()->json([
            'ok' => true,
            'status' => $status,
            'payment_id' => $paymentId
        ]);
    }

    /**
     * Ativa, renova ou suspende o Tenant conforme aprovação da assinatura no Mercado Pago.
     */
    protected function processTenantSubscription(int $tenantId, string $status, array $paymentDetails): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }

        if ($status === 'approved') {
            Log::info("[MercadoPagoWebhook] Assinatura do Tenant #{$tenantId} APROVADA. Renovando conta.");

            $update = ['status' => 'active', 'updated_at' => now()];
            if (Schema::hasColumn('tenants', 'subscription_status')) {
                $update['subscription_status'] = 'active';
            }
            if (Schema::hasColumn('tenants', 'subscription_expires_at')) {
                $update['subscription_expires_at'] = now()->addDays(30);
            }

            DB::table('tenants')->where('id', $tenantId)->update($update);
        } elseif (in_array($status, ['cancelled', 'rejected', 'refunded'], true)) {
            Log::warning("[MercadoPagoWebhook] Assinatura do Tenant #{$tenantId} está {$status}.");

            if (Schema::hasColumn('tenants', 'subscription_status')) {
                DB::table('tenants')->where('id', $tenantId)->update([
                    'subscription_status' => 'suspended',
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

