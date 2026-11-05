<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Serviço Oficial Mercado Pago SDK v2 & Checkout Transparente.
 */

namespace App\Modules\MercadoPago\Services;

use App\Models\Invoice;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MercadoPagoPaymentService
{
    protected string $accessToken;
    protected string $publicKey;
    protected string $webhookSecret;
    protected bool $sandbox;

    public function __construct(
        ?string $accessToken = null,
        ?string $publicKey = null,
        ?string $webhookSecret = null
    ) {
        $this->accessToken = $accessToken
            ?: (string) env('MERCADOPAGO_ACCESS_TOKEN', env('MP_ACCESS_TOKEN', config('gateways.mercadopago.access_token', '')));

        $this->publicKey = $publicKey
            ?: (string) env('MERCADOPAGO_PUBLIC_KEY', env('MP_PUBLIC_KEY', config('gateways.mercadopago.public_key', '')));

        $this->webhookSecret = $webhookSecret
            ?: (string) env('MERCADOPAGO_WEBHOOK_SECRET', config('gateways.mercadopago.webhook_secret', ''));

        $this->sandbox = (bool) env('MERCADOPAGO_SANDBOX', false);
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Gera cobrança Pix com QR Code visual (Base64) e código Copia e Cola dinâmico.
     */
    public function createPixCharge(array $invoice): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Access Token do Mercado Pago não configurado.');
        }

        $token = $invoice['token'] ?? $invoice['webhook_token'] ?? Str::random(48);
        $notificationUrl = url("/api/v1/webhooks/mercadopago?token={$token}");
        $amount = round((float) ($invoice['amount'] ?? 0), 2);
        $invoiceId = $invoice['id'] ?? null;

        $expDays = max(1, (int) config('gateways.mercadopago.expiration_days', 3));
        $expirationDate = now('America/Sao_Paulo')->addDays($expDays)->format('Y-m-d\TH:i:s.000P');

        if (!empty($invoice['due_date'])) {
            try {
                $due = Carbon::parse($invoice['due_date'], 'America/Sao_Paulo')->endOfDay();
                if ($due->isFuture() && $due->diffInMinutes(now('America/Sao_Paulo')) >= 30) {
                    $expirationDate = $due->format('Y-m-d\TH:i:s.000P');
                }
            } catch (\Throwable) {}
        }

        $payload = [
            'transaction_amount' => $amount,
            'description' => $invoice['description'] ?? ("Fatura #" . ($invoice['number'] ?? $invoiceId)),
            'payment_method_id' => 'pix',
            'external_reference' => 'INV-' . $invoiceId,
            'notification_url' => $notificationUrl,
            'date_of_expiration' => $expirationDate,
            'payer' => [
                'email' => $invoice['customer_email'] ?? $invoice['client_email'] ?? 'cliente@kdkhost.com.br',
                'first_name' => $invoice['customer_name'] ?? $invoice['client_name'] ?? 'Cliente',
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D/', '', $invoice['customer_document'] ?? $invoice['client_document'] ?? '00000000000'),
                ],
            ],
        ];

        $idempotencyKey = 'PIX-INV-' . $invoiceId . '-' . number_format($amount, 2, '.', '');

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->timeout(15)
            ->post('https://api.mercadopago.com/v1/payments', array_filter($payload))
            ->throw()
            ->json();

        $chargeId = (string) ($response['id'] ?? '');
        $pointOfInteraction = $response['point_of_interaction']['transaction_data'] ?? [];
        $pixCode = (string) ($pointOfInteraction['qr_code'] ?? '');
        $qrCodeBase64 = (string) ($pointOfInteraction['qr_code_base64'] ?? '');

        if ($qrCodeBase64 && !str_starts_with($qrCodeBase64, 'data:')) {
            $qrCodeBase64 = 'data:image/png;base64,' . $qrCodeBase64;
        }

        // Atualiza a fatura no banco com as informações do Pix
        if ($invoiceId) {
            $updateData = [
                'pix_code' => $pixCode,
                'qr_code_base64' => $qrCodeBase64,
                'gateway' => 'mercadopago',
                'gateway_charge_id' => $chargeId,
                'token' => $token,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('invoices', 'webhook_token')) {
                $updateData['webhook_token'] = $token;
            }

            DB::table('invoices')->where('id', $invoiceId)->update(array_filter($updateData, fn($v) => $v !== null));
        }

        return [
            'ok' => true,
            'charge_id' => $chargeId,
            'pix_code' => $pixCode,
            'qr_code_base64' => $qrCodeBase64,
            'ticket_url' => $pointOfInteraction['ticket_url'] ?? '',
            'public_url' => url('/fatura/' . $token),
            'raw' => $response,
        ];
    }

    /**
     * Processa pagamento via Cartão de Crédito transparente utilizando token do MercadoPago.js v2.
     */
    public function createCardCharge(array $invoice, array $cardData): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Access Token do Mercado Pago não configurado.');
        }

        $token = $invoice['token'] ?? $invoice['webhook_token'] ?? Str::random(48);
        $notificationUrl = url("/api/v1/webhooks/mercadopago?token={$token}");
        $amount = round((float) ($invoice['amount'] ?? 0), 2);
        $invoiceId = $invoice['id'] ?? null;

        $payload = [
            'transaction_amount' => $amount,
            'token' => $cardData['token'],
            'description' => $invoice['description'] ?? ("Fatura #" . ($invoice['number'] ?? $invoiceId)),
            'installments' => (int) ($cardData['installments'] ?? 1),
            'payment_method_id' => $cardData['payment_method_id'] ?? null,
            'issuer_id' => $cardData['issuer_id'] ?? null,
            'external_reference' => 'INV-' . $invoiceId,
            'notification_url' => $notificationUrl,
            'statement_descriptor' => 'KDKHOST',
            'payer' => [
                'email' => $cardData['payer']['email'] ?? ($invoice['customer_email'] ?? $invoice['client_email'] ?? 'cliente@kdkhost.com.br'),
                'identification' => [
                    'type' => $cardData['payer']['identification']['type'] ?? 'CPF',
                    'number' => preg_replace('/\D/', '', $cardData['payer']['identification']['number'] ?? ($invoice['customer_document'] ?? '00000000000')),
                ],
            ],
        ];

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.mercadopago.com/v1/payments', array_filter($payload))
            ->throw()
            ->json();

        $chargeId = (string) ($response['id'] ?? '');
        $status = $response['status'] ?? 'pending';

        if ($status === 'approved' && $invoiceId) {
            $this->markInvoiceAsPaid($invoiceId, $chargeId, $response);
        }

        return [
            'ok' => $status === 'approved',
            'status' => $status,
            'status_detail' => $response['status_detail'] ?? '',
            'charge_id' => $chargeId,
            'raw' => $response,
        ];
    }

    /**
     * Consulta detalhes completos da cobrança na API oficial do Mercado Pago.
     */
    public function fetchPayment(string $paymentId): ?array
    {
        if (!$this->isConfigured() || !is_numeric($paymentId)) {
            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

            return $response->ok() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning("[MercadoPagoPaymentService] Erro ao consultar pagamento #{$paymentId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Executa estorno total ou parcial de um pagamento.
     */
    public function refund(string $paymentId, ?float $amount = null): array
    {
        if (!$this->isConfigured() || !is_numeric($paymentId)) {
            return ['ok' => false, 'error' => 'Identificador de pagamento ou credenciais inválidas.'];
        }

        $payload = $amount ? ['amount' => round((float) $amount, 2)] : [];

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(15)
                ->post("https://api.mercadopago.com/v1/payments/{$paymentId}/refunds", $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Atualiza fatura local se existir
                $inv = DB::table('invoices')->where('gateway_charge_id', $paymentId)->first();
                if ($inv) {
                    $isPartial = $amount && ((float) $amount < (float) ($inv->amount ?? 0));
                    DB::table('invoices')->where('id', $inv->id)->update([
                        'status' => $isPartial ? 'partially_refunded' : 'refunded',
                        'updated_at' => now(),
                    ]);
                }

                return [
                    'ok' => true,
                    'refund_id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? 'approved',
                    'amount' => $amount ?: ($data['amount'] ?? 0),
                    'raw' => $data,
                ];
            }

            return [
                'ok' => false,
                'error' => $response->json()['message'] ?? 'Falha ao estornar pagamento no Mercado Pago.',
            ];
        } catch (\Throwable $e) {
            Log::error("[MercadoPagoPaymentService] Exceção no estorno do pagamento #{$paymentId}: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dá baixa automática na fatura, calculando e deduzindo as tarifas reais do gateway
     * e cancelando disparos subsequentes na régua de cobrança.
     */
    public function markInvoiceAsPaid(string|int $invoiceId, string $paymentId, ?array $paymentDetails = null): bool
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
        if (!$invoice) {
            return false;
        }

        if (!$paymentDetails) {
            $paymentDetails = $this->fetchPayment($paymentId) ?: [];
        }

        // Calcula taxas e valor líquido
        $feeMercadoPago = 0.0;
        $amount = (float) ($invoice->amount ?? 0);
        $netAmount = $amount;

        foreach ($paymentDetails['fee_details'] ?? [] as $fee) {
            $feeMercadoPago += (float) ($fee['amount'] ?? 0);
        }

        if (isset($paymentDetails['transaction_details']['net_received_amount'])) {
            $netAmount = (float) $paymentDetails['transaction_details']['net_received_amount'];
        } else {
            $netAmount = max(0, $amount - $feeMercadoPago);
        }

        $updateData = [
            'status' => 'paid',
            'paid_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('invoices', 'fee_mercadopago')) {
            $updateData['fee_mercadopago'] = round($feeMercadoPago, 2);
        }
        if (Schema::hasColumn('invoices', 'net_amount')) {
            $updateData['net_amount'] = round($netAmount, 2);
        }

        DB::table('invoices')->where('id', $invoiceId)->update($updateData);

        // Cancela disparos pendentes desta fatura na régua de cobrança para não cobrar cliente que já pagou
        if (Schema::hasTable('billing_rules')) {
            DB::table('billing_rules')
                ->where('invoice_id', $invoiceId)
                ->where('status', 'scheduled')
                ->update([
                    'status' => 'canceled',
                    'canceled_reason' => 'Fatura quitada no Mercado Pago',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('billing_dispatches')) {
            DB::table('billing_dispatches')
                ->where('invoice_id', $invoiceId)
                ->whereIn('status', ['pending', 'scheduled'])
                ->update([
                    'status' => 'canceled',
                    'updated_at' => now(),
                ]);
        }

        Log::info("[MercadoPagoPaymentService] Fatura #{$invoiceId} baixada com sucesso. Líquido: R$ {$netAmount} | Taxa MP: R$ {$feeMercadoPago}");

        return true;
    }
}

