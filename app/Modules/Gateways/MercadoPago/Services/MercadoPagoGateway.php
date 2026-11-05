<?php

namespace App\Modules\Gateways\MercadoPago\Services;

use App\Modules\Gateways\Contracts\ChargeResult;
use App\Modules\Gateways\Contracts\PaymentGateway;
use App\Modules\Gateways\Contracts\WebhookResult;
use App\Modules\Gateways\Core\GatewayFeatureResolver;
use App\Modules\Gateways\Core\GatewayFeeCalculator;
use App\Modules\Gateways\Core\GatewayWebhookManager;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Modules\Tenancy\Models\TenantInvoice;
use App\Modules\Tenancy\Services\TenantInvoicePaymentService;
use App\Modules\Tenancy\Services\TenantPackageService;
use App\Modules\WhmcsIntegration\Services\WhmcsPaymentSyncer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MercadoPagoGateway implements PaymentGateway
{
    protected ?TenantGateway $tenantGatewayContext = null;

    public function __construct(
        protected SettingsManager $settings,
        protected GatewayFeatureResolver $features,
        protected GatewayFeeCalculator $fees,
        protected GatewayWebhookManager $webhooks,
    )
    {
    }

    public function id(): string
    {
        return 'mercadopago';
    }

    public function setTenantContext(TenantGateway $gateway): void
    {
        $this->tenantGatewayContext = $gateway;
    }

    public function notificationUrl(array $invoice, ?string $webhookToken = null): string
    {
        $token = $webhookToken ?: (string) ($invoice['webhook_token'] ?? Str::random(48));
        return route('webhook.mercadopago', ['token' => $token]);
    }

    public function createCharge(array $invoice, string $paymentMethod = 'all'): ChargeResult
    {
        if ($paymentMethod === 'all' && filled(data_get($invoice, 'payment_method'))) {
            $paymentMethod = strtolower((string) data_get($invoice, 'payment_method'));
        } elseif ($paymentMethod === 'all' && filled(data_get($invoice, 'meta.payment_method'))) {
            $paymentMethod = strtolower((string) data_get($invoice, 'meta.payment_method'));
        }

        if (! in_array($paymentMethod, ['pix', 'credit_card', 'ticket', 'all'], true)) {
            $paymentMethod = 'all';
        }

        // 1. Reutiliza cobrança PIX se já tiver dados salvos localmente
        if ($paymentMethod === 'pix' && $this->hasReusableCharge($invoice)) {
            return new ChargeResult(
                chargeId: (string) $invoice['gateway_charge_id'],
                paymentUrl: (string) ($invoice['payment_url'] ?? ''),
                webhookToken: (string) ($invoice['webhook_token'] ?? ''),
                raw: ['reused' => true]
            );
        }

        // 2. Reutiliza preferência de checkout (cartão/all) se já existir na fatura aberta
        if ($paymentMethod !== 'pix' && $this->hasReusablePreference($invoice)) {
            $meta = is_array($invoice['meta'] ?? null) ? $invoice['meta'] : (json_decode($invoice['meta'] ?? '[]', true) ?: []);
            return new ChargeResult(
                chargeId: (string) ($meta['card_preference_id'] ?? $invoice['gateway_charge_id'] ?? ''),
                paymentUrl: (string) ($meta['card_checkout_url'] ?? $invoice['payment_url'] ?? ''),
                webhookToken: (string) ($invoice['webhook_token'] ?? ''),
                raw: ['reused' => true, 'preference_id' => $meta['card_preference_id'] ?? null]
            );
        }

        return $paymentMethod === 'pix'
            ? $this->createPixCharge($invoice)
            : $this->createCheckoutPreference($invoice, $paymentMethod);
    }

    protected function createPixCharge(array $invoice): ChargeResult
    {
        $token = $this->cfg('access_token');
        if (! $token) {
            throw new \RuntimeException('Access Token do Mercado Pago nao configurado.');
        }

        $webhookToken = (string) ($invoice['webhook_token'] ?? Str::random(48));

        $email = trim((string) data_get($invoice, 'customer.email', ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'cliente' . rand(1000, 9999) . '@email.com';
        }
        $name = trim((string) data_get($invoice, 'customer.name', 'Cliente'));
        $document = preg_replace('/[^0-9]/', '', (string) data_get($invoice, 'customer.document', ''));

        $payer = [
            'email' => $email,
            'first_name' => $name,
        ];

        if (strlen($document) === 11 || strlen($document) === 14) {
            $payer['identification'] = [
                'type' => strlen($document) === 11 ? 'CPF' : 'CNPJ',
                'number' => $document,
            ];
        }

        // 1. Verifica se já existe um pagamento aberto/pendente no Mercado Pago para esta fatura/serviço
        $existing = $this->findOpenPaymentForInvoice($invoice);
        if ($existing) {
            return $this->formatExistingPixChargeResult($existing, $invoice, $webhookToken);
        }

        $payload = [
            'transaction_amount' => round((float) $invoice['amount'], 2),
            'description' => $invoice['description'] ?? $this->cfg('default_description', 'Cobranca'),
            'payment_method_id' => 'pix',
            'payer' => $payer,
            'external_reference' => 'INV-' . $invoice['id'],
            'notification_url' => $this->notificationUrl($invoice, $webhookToken),
            'date_of_expiration' => $this->resolvePixExpiration($invoice),
        ];

        $idempotencyKey = 'PIX-INV-' . $invoice['id'] . '-' . number_format((float) $invoice['amount'], 2, '.', '');

        $split = $this->resolveAffiliateSplitForInvoice($invoice);
        if ($split) {
            $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                'split_affiliate_id' => $split['affiliate_id'],
                'split_collector_id' => $split['collector_id'],
                'split_amount'       => $split['split_amount'],
            ]);
            if (!empty($split['collector_id'])) {
                $payload['application_fee'] = (float) $split['split_amount'];
            }
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
                ->post('https://api.mercadopago.com/v1/payments', array_filter($payload, fn ($value) => $value !== null))
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            // Se falhar devido a application_fee (permissão restrita da conta MP), faz fallback sem o campo no payload
            if (isset($payload['application_fee'])) {
                unset($payload['application_fee']);
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->withHeaders(['X-Idempotency-Key' => $idempotencyKey . '-retry'])
                    ->post('https://api.mercadopago.com/v1/payments', array_filter($payload, fn ($value) => $value !== null))
                    ->throw()
                    ->json();
            } else {
                throw $e;
            }
        }

        $ticketUrl = (string) ($response['point_of_interaction']['transaction_data']['ticket_url'] ?? '');
        $secureCheckoutUrl = url('/fatura/' . $webhookToken);
        
        if (!empty($response['point_of_interaction']['transaction_data']['qr_code_base64'])) {
            $base64 = $response['point_of_interaction']['transaction_data']['qr_code_base64'];
            if (!str_starts_with($base64, 'data:')) {
                $response['point_of_interaction']['transaction_data']['qr_code_base64'] = 'data:image/png;base64,' . $base64;
            }
        }

        $currentInv = DB::table('invoices')->where('id', $invoice['id'])->first();
        $meta = json_decode($currentInv?->meta ?? '[]', true) ?: [];
        if ($ticketUrl) {
            $meta['gateway_payment'] = array_merge($meta['gateway_payment'] ?? [], [
                'ticket_url' => $ticketUrl,
                'mp_ticket_url' => $ticketUrl,
            ]);
        }
        if ($split) {
            $meta['split_mercadopago'] = $split;
        }

        $invoiceUpdate = [
            'gateway' => 'mercadopago',
            'gateway_charge_id' => $response['id'] ?? null,
            'payment_url' => $secureCheckoutUrl,
            'webhook_token' => $webhookToken,
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ];

        DB::table('invoices')->where('id', $invoice['id'])->update($invoiceUpdate);

        return new ChargeResult(
            chargeId: (string) ($response['id'] ?? ''),
            paymentUrl: $secureCheckoutUrl,
            webhookToken: $webhookToken,
            raw: $response,
        );
    }

    protected function createCheckoutPreference(array $invoice, string $paymentMethod): ChargeResult
    {
        $token = $this->cfg('access_token');
        if (! $token) {
            throw new \RuntimeException('Access Token do Mercado Pago nao configurado.');
        }

        $webhookToken = (string) ($invoice['webhook_token'] ?? Str::random(48));

        $invoiceUrl = url('/fatura/' . $webhookToken);
        $payload = [
            'items' => [[
                'title' => $invoice['description'] ?? $this->cfg('default_description', 'Cobranca'),
                'quantity' => 1,
                'unit_price' => (float) $invoice['amount'],
                'currency_id' => 'BRL',
            ]],
            'external_reference' => 'INV-'.$invoice['id'],
            'notification_url' => route('webhook.mercadopago', ['token' => $webhookToken]),
            'back_urls' => [
                'success' => $invoiceUrl,
                'pending' => $invoiceUrl,
                'failure' => $invoiceUrl,
            ],
            'auto_return' => 'approved',
            'expires' => true,
            'expiration_date_to' => now('America/Sao_Paulo')
                ->addDays((int) $this->cfg('expiration_days', 3))
                ->toIso8601String(),
            'statement_descriptor' => $this->cfg('soft_descriptor') ?: null,
            'payment_methods' => $this->paymentMethodsPayload($paymentMethod),
        ];

        $split = $this->resolveAffiliateSplitForInvoice($invoice);
        if ($split) {
            $payload['marketplace_fee'] = (float) $split['split_amount'];
            $payload['metadata'] = [
                'split_affiliate_id' => $split['affiliate_id'],
                'split_collector_id' => $split['collector_id'],
                'split_amount'       => $split['split_amount'],
            ];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://api.mercadopago.com/checkout/preferences', array_filter($payload, fn ($value) => $value !== null))
            ->throw()
            ->json();

        $paymentUrl = (string) ($response['init_point'] ?? $response['sandbox_init_point'] ?? '');

        // Salva a preferência no meta da fatura sem sobrescrever a payment_url interna do painel
        $currentInvoice = DB::table('invoices')->where('id', $invoice['id'])->first();
        $meta = json_decode($currentInvoice->meta ?? '[]', true) ?: [];
        $meta['card_checkout_url'] = $paymentUrl;
        $meta['card_preference_id'] = $response['id'] ?? null;
        if ($split) {
            $meta['split_mercadopago'] = $split;
        }

        $updateData = [
            'gateway' => 'mercadopago',
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ];
        if (empty($currentInvoice->payment_url)) {
            $updateData['payment_url'] = $invoiceUrl;
        }

        DB::table('invoices')->where('id', $invoice['id'])->update($updateData);

        return new ChargeResult(
            chargeId: (string) ($response['id'] ?? ''),
            paymentUrl: $paymentUrl,
            webhookToken: $webhookToken,
            raw: $response,
        );
    }

    public function handleWebhook(array $payload, string $token): WebhookResult
    {
        // Verificar se é uma tenant_invoice
        $tenantInvoice = TenantInvoice::where('webhook_token', $token)->first();
        if ($tenantInvoice) {
            return $this->handleTenantInvoiceWebhook($tenantInvoice, $payload);
        }

        // Verificar se é uma invoice normal
        $invoice = DB::table('invoices')->where('webhook_token', $token)->first();
        if (! $invoice) {
            return new WebhookResult(false, 'ignored');
        }

        DB::table('payment_webhooks')->insert([
            'invoice_id' => $invoice->id,
            'gateway' => 'mercadopago',
            'event' => $payload['type'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (($payload['type'] ?? null) === 'payment' && ! empty($payload['data']['id'])) {
            if ($invoice && $invoice->tenant_id) {
                $tenantGateway = TenantGateway::where('tenant_id', $invoice->tenant_id)->where('gateway', 'mercadopago')->first();
                if ($tenantGateway) $this->setTenantContext($tenantGateway);
            }

            $status = $this->fetchStatus((string) $payload['data']['id']);
            if ($status === 'approved') {
                $this->markInvoicePaid($invoice, (string) $payload['data']['id']);

                return new WebhookResult(true, 'paid', invoiceId: $invoice->id);
            }
        }

        return new WebhookResult(true, (string) ($payload['action'] ?? 'ignored'));
    }

    protected function handleTenantInvoiceWebhook(TenantInvoice $invoice, array $payload): WebhookResult
    {
        if (($payload['type'] ?? null) === 'payment' && ! empty($payload['data']['id'])) {
            if ($invoice->tenant_id) {
                $tenantGateway = TenantGateway::where('tenant_id', $invoice->tenant_id)->where('gateway', 'mercadopago')->first();
                if ($tenantGateway) $this->setTenantContext($tenantGateway);
            }

            $status = $this->fetchStatus((string) $payload['data']['id']);
            if ($status === 'approved') {
                app(TenantInvoicePaymentService::class)->markInvoicePaid($invoice, (string) $payload['data']['id']);

                return new WebhookResult(true, 'paid', invoiceId: $invoice->id);
            }
        }

        return new WebhookResult(true, (string) ($payload['action'] ?? 'ignored'));
    }

    /**
     * Executa cobrança via Cartão de Crédito no Checkout Transparente.
     * Suporta token gerado pelo MercadoPago.js v2 / Bricks, parcelamento e dedução de tarifas.
     */
    public function createCardCharge(array $invoice, array $cardData): ChargeResult
    {
        $token = $this->cfg('access_token');
        if (! $token) {
            throw new \RuntimeException('Access Token do Mercado Pago não configurado.');
        }

        $webhookToken = (string) ($invoice['webhook_token'] ?? Str::random(48));
        $notificationUrl = $this->notificationUrl($invoice, $webhookToken);

        $payload = [
            'transaction_amount' => (float) $invoice['amount'],
            'token' => $cardData['token'],
            'description' => $invoice['description'] ?? $this->cfg('default_description', 'Cobrança Fatura'),
            'installments' => (int) ($cardData['installments'] ?? 1),
            'payment_method_id' => $cardData['payment_method_id'] ?? null,
            'issuer_id' => $cardData['issuer_id'] ?? null,
            'payer' => [
                'email' => $cardData['payer']['email'] ?? ($invoice['customer_email'] ?? 'cliente@kdkhost.com.br'),
                'identification' => [
                    'type' => $cardData['payer']['identification']['type'] ?? 'CPF',
                    'number' => preg_replace('/\D/', '', $cardData['payer']['identification']['number'] ?? ($invoice['customer_cpf'] ?? '')),
                ],
            ],
            'external_reference' => 'INV-' . $invoice['id'],
            'notification_url' => $notificationUrl,
            'statement_descriptor' => $this->cfg('soft_descriptor') ?: 'KDKHOST',
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://api.mercadopago.com/v1/payments', array_filter($payload, fn ($v) => $v !== null))
            ->throw()
            ->json();

        $chargeId = (string) ($response['id'] ?? '');
        $status = $response['status'] ?? 'pending';

        $currentInv = DB::table('invoices')->where('id', $invoice['id'])->first();
        $meta = json_decode($currentInv?->meta ?? '[]', true) ?: [];
        $meta['gateway_payment'] = array_merge($meta['gateway_payment'] ?? [], [
            'card_payment_id' => $chargeId,
            'card_status' => $status,
            'installments' => $cardData['installments'] ?? 1,
            'payment_method_id' => $cardData['payment_method_id'] ?? null,
        ]);

        $updateData = [
            'gateway' => 'mercadopago',
            'gateway_charge_id' => $chargeId,
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ];

        if ($status === 'approved') {
            $updateData['status'] = 'paid';
            $updateData['paid_at'] = now();
        }

        DB::table('invoices')->where('id', $invoice['id'])->update($updateData);

        if ($status === 'approved' && $currentInv) {
            $this->markInvoicePaid($currentInv, $chargeId);
        }

        return new ChargeResult(
            chargeId: $chargeId,
            paymentUrl: url('/fatura/' . $webhookToken),
            webhookToken: $webhookToken,
            raw: $response
        );
    }

    public function fetchStatus(string $chargeId): ?string
    {
        $details = $this->fetchPaymentDetails($chargeId);
        return $details['status'] ?? null;
    }

    /**
     * Consulta os detalhes completos do pagamento na API do Mercado Pago
     * incluindo fee_details, tarifas deduzidas e valor líquido.
     */
    public function fetchPaymentDetails(string $chargeId): ?array
    {
        $token = $this->cfg('access_token');
        if (! $token || ! is_numeric($chargeId)) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get("https://api.mercadopago.com/v1/payments/{$chargeId}");

            return $response->ok() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Realiza estorno total ou parcial no Mercado Pago e atualiza a fatura.
     */
    public function refund(string $chargeId, ?float $amount = null): bool
    {
        $token = $this->cfg('access_token');
        if (! $token || ! is_numeric($chargeId)) {
            return false;
        }

        $payload = $amount ? ['amount' => round((float) $amount, 2)] : [];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post("https://api.mercadopago.com/v1/payments/{$chargeId}/refunds", $payload);

            if ($response->successful()) {
                $refundData = $response->json();

                $inv = DB::table('invoices')->where('gateway_charge_id', $chargeId)->first();
                if ($inv) {
                    $meta = json_decode($inv->meta ?? '[]', true) ?: [];
                    $meta['refunds'] = $meta['refunds'] ?? [];
                    $meta['refunds'][] = [
                        'refund_id' => $refundData['id'] ?? null,
                        'amount' => $amount ?: (float) ($inv->amount ?? 0),
                        'refunded_at' => now()->toIso8601String(),
                        'raw' => $refundData,
                    ];

                    $isPartial = $amount && ((float) $amount < (float) $inv->amount);
                    DB::table('invoices')->where('id', $inv->id)->update([
                        'status' => $isPartial ? 'partially_refunded' : 'refunded',
                        'meta' => json_encode($meta),
                        'updated_at' => now(),
                    ]);
                }

                return true;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[MercadoPagoGateway] Erro ao processar reembolso da cobrança #{$chargeId}: " . $e->getMessage());
        }

        return false;
    }

    public function validateCredentials(): bool
    {
        return filled($this->cfg('access_token'));
    }

    public function calculateFees(float $amount, array $context = []): array
    {
        return $this->fees->calculate($amount, $context['fees'] ?? []);
    }

    public function availablePaymentMethods(): array
    {
        return $this->features->availablePaymentMethods($this->id());
    }

    public function buildDynamicWebhook(array $context = []): array
    {
        return $this->webhooks->buildDynamicWebhook(
            $this->id(),
            $context['tenant_gateway'] instanceof TenantGateway ? $context['tenant_gateway'] : null,
        );
    }

    public function supports(string $feature): bool
    {
        return $this->features->supports($this->id(), $feature);
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        if ($this->tenantGatewayContext) {
            $val = $this->tenantGatewayContext->credentials[$key] ?? $this->tenantGatewayContext->settings[$key] ?? null;
            if ($val !== null) return $val;
        }

        return $this->settings->get("gateways.mercadopago.{$key}", config("gateways.mercadopago.{$key}", $default));
    }

    protected function hasReusableCharge(array $invoice): bool
    {
        // Só reutiliza se for status pendente e já tiver QR PIX gerado
        $hasPix = !empty(data_get($invoice, 'meta.gateway_payment.pix_copia_cola'))
            || !empty(data_get($invoice, 'meta.pix_copia_cola'));

        return in_array((string) ($invoice['status'] ?? ''), ['pending', 'sent', 'overdue'], true)
            && filled($invoice['gateway_charge_id'] ?? null)
            && ($invoice['gateway'] ?? 'mercadopago') === 'mercadopago'
            && $hasPix;
    }

    protected function hasReusablePreference(array $invoice): bool
    {
        $isOpen = in_array((string) ($invoice['status'] ?? ''), ['pending', 'sent', 'overdue'], true);
        $meta = is_array($invoice['meta'] ?? null) ? $invoice['meta'] : (json_decode($invoice['meta'] ?? '[]', true) ?: []);

        return $isOpen && !empty($meta['card_preference_id']) && !empty($meta['card_checkout_url']);
    }

    /**
     * Localiza se já existe um pagamento aberto ou em análise no Mercado Pago
     * para a mesma fatura/serviço, evitando criar múltiplas cobranças pendentes.
     */
    protected function findOpenPaymentForInvoice(array $invoice): ?array
    {
        $token = $this->cfg('access_token');
        if (! $token || empty($invoice['id'])) {
            return null;
        }

        // 1. Se já possui um gateway_charge_id associado, consulta o status direto
        $chargeId = $invoice['gateway_charge_id'] ?? null;
        if ($chargeId && is_numeric($chargeId)) {
            try {
                $resp = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(6)
                    ->get("https://api.mercadopago.com/v1/payments/{$chargeId}");

                if ($resp->ok()) {
                    $data = $resp->json();
                    if (in_array($data['status'] ?? '', ['pending', 'in_process'], true)) {
                        return $data;
                    }
                }
            } catch (\Throwable) {}
        }

        // 2. Busca no Mercado Pago por external_reference com status pendente
        try {
            $resp = Http::withToken($token)
                ->acceptJson()
                ->timeout(6)
                ->get('https://api.mercadopago.com/v1/payments/search', [
                    'external_reference' => 'INV-' . $invoice['id'],
                    'status' => 'pending',
                    'limit' => 1,
                ]);

            if ($resp->ok()) {
                $results = $resp->json()['results'] ?? [];
                if (! empty($results[0]) && in_array($results[0]['status'] ?? '', ['pending', 'in_process'], true)) {
                    return $results[0];
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    /**
     * Formata um pagamento pendente já existente retornado pelo Mercado Pago,
     * sincroniza a fatura local e reaproveita os dados em vez de gerar nova cobrança.
     */
    protected function formatExistingPixChargeResult(array $response, array $invoice, string $webhookToken): ChargeResult
    {
        $ticketUrl = (string) ($response['point_of_interaction']['transaction_data']['ticket_url'] ?? '');
        $secureCheckoutUrl = url('/fatura/' . $webhookToken);
        
        if (!empty($response['point_of_interaction']['transaction_data']['qr_code_base64'])) {
            $base64 = $response['point_of_interaction']['transaction_data']['qr_code_base64'];
            if (!str_starts_with($base64, 'data:')) {
                $response['point_of_interaction']['transaction_data']['qr_code_base64'] = 'data:image/png;base64,' . $base64;
            }
        }

        $currentInv = DB::table('invoices')->where('id', $invoice['id'])->first();
        $meta = json_decode($currentInv?->meta ?? '[]', true) ?: [];
        if ($ticketUrl) {
            $meta['gateway_payment'] = array_merge($meta['gateway_payment'] ?? [], [
                'ticket_url' => $ticketUrl,
                'mp_ticket_url' => $ticketUrl,
            ]);
        }

        DB::table('invoices')->where('id', $invoice['id'])->update([
            'gateway' => 'mercadopago',
            'gateway_charge_id' => $response['id'] ?? null,
            'payment_url' => $secureCheckoutUrl,
            'webhook_token' => $webhookToken,
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ]);

        return new ChargeResult(
            chargeId: (string) ($response['id'] ?? ''),
            paymentUrl: $secureCheckoutUrl,
            webhookToken: $webhookToken,
            raw: array_merge($response, ['reused' => true])
        );
    }

    /**
     * Cancela todas as cobranças pendentes ou em processo no Mercado Pago
     * vinculadas a esta fatura (por exemplo, quando paga por outro gateway, link ou dinheiro).
     */
    public function cancelPendingChargesForInvoice(mixed $invoice): int
    {
        $invoiceId = is_numeric($invoice) ? (int) $invoice : (int) data_get($invoice, 'id');
        if (! $invoiceId) {
            return 0;
        }

        $inv = is_object($invoice) && isset($invoice->tenant_id)
            ? $invoice
            : DB::table('invoices')->where('id', $invoiceId)->first();

        if ($inv && !empty($inv->tenant_id)) {
            $tenantGateway = TenantGateway::where('tenant_id', $inv->tenant_id)
                ->where('gateway', 'mercadopago')
                ->first();
            if ($tenantGateway) {
                $this->setTenantContext($tenantGateway);
            }
        }

        $token = $this->cfg('access_token');
        if (! $token) {
            return 0;
        }

        $cancelledCount = 0;
        $chargeIdsToCancel = [];

        // 1. Se a fatura tiver um gateway_charge_id numérico salvo
        if ($inv && filled($inv->gateway_charge_id) && is_numeric($inv->gateway_charge_id)) {
            $chargeIdsToCancel[] = (string) $inv->gateway_charge_id;
        }

        // 2. Busca na API do Mercado Pago pagamentos pendentes e em processo desta fatura
        try {
            foreach (['pending', 'in_process'] as $statusToSearch) {
                $searchResp = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(6)
                    ->get('https://api.mercadopago.com/v1/payments/search', [
                        'external_reference' => 'INV-' . $invoiceId,
                        'status' => $statusToSearch,
                        'limit' => 50,
                    ]);

                if ($searchResp->ok()) {
                    $results = $searchResp->json()['results'] ?? [];
                    foreach ($results as $item) {
                        if (! empty($item['id'])) {
                            $chargeIdsToCancel[] = (string) $item['id'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[MercadoPagoGateway] Erro ao buscar cobranças para cancelamento na fatura #{$invoiceId}: " . $e->getMessage());
        }

        $chargeIdsToCancel = array_unique(array_filter($chargeIdsToCancel));

        // 3. Cancela cada pagamento no Mercado Pago
        foreach ($chargeIdsToCancel as $chargeId) {
            try {
                $res = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(6)
                    ->put("https://api.mercadopago.com/v1/payments/{$chargeId}", [
                        'status' => 'cancelled',
                    ]);

                if ($res->ok() || $res->status() === 400) {
                    $cancelledCount++;
                    \Illuminate\Support\Facades\Log::info("[MercadoPagoGateway] Cobrança pendente #{$chargeId} da fatura #{$invoiceId} cancelada com sucesso no Mercado Pago.");
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[MercadoPagoGateway] Falha ao cancelar cobrança #{$chargeId}: " . $e->getMessage());
            }
        }

        return $cancelledCount;
    }

    /**
     * Cancela um pagamento específico no Mercado Pago.
     */
    public function cancelPayment(string $chargeId): bool
    {
        $token = $this->cfg('access_token');
        if (! $token || ! is_numeric($chargeId)) {
            return false;
        }

        try {
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(6)
                ->put("https://api.mercadopago.com/v1/payments/{$chargeId}", [
                    'status' => 'cancelled',
                ]);

            return $res->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function paymentMethodsPayload(string $paymentMethod): ?array
    {
        $paymentMethod = strtolower(trim($paymentMethod));
        $installmentsMax = max(1, (int) $this->cfg('installments_max', 12));

        return match ($paymentMethod) {
            'pix' => [
                'installments' => 1,
                'excluded_payment_types' => [
                    ['id' => 'credit_card'],
                    ['id' => 'debit_card'],
                    ['id' => 'ticket'],
                ],
            ],
            'credit_card', 'card' => [
                'installments' => $installmentsMax,
                'excluded_payment_types' => [
                    ['id' => 'bank_transfer'],
                    ['id' => 'ticket'],
                ],
            ],
            default => null,
        };
    }

    protected function markInvoicePaid(object $invoice, string $transactionId): void
    {
        $paymentDetails = $this->fetchPaymentDetails($transactionId);

        $feeMercadoPago = 0.0;
        $netReceivedAmount = (float) ($invoice->amount ?? 0);

        if ($paymentDetails) {
            foreach ($paymentDetails['fee_details'] ?? [] as $fee) {
                $feeMercadoPago += (float) ($fee['amount'] ?? 0);
            }
            if (isset($paymentDetails['transaction_details']['net_received_amount'])) {
                $netReceivedAmount = (float) $paymentDetails['transaction_details']['net_received_amount'];
            } else {
                $netReceivedAmount = max(0, (float) ($invoice->amount ?? 0) - $feeMercadoPago);
            }
        }

        $meta = json_decode($invoice->meta ?? '[]', true) ?: [];
        $meta['reconciliation'] = [
            'gross_amount' => (float) ($invoice->amount ?? 0),
            'fee_mercadopago' => round($feeMercadoPago, 2),
            'net_tenant_amount' => round($netReceivedAmount, 2),
            'fee_details' => $paymentDetails['fee_details'] ?? [],
            'settled_at' => now()->toIso8601String(),
            'transaction_id' => $transactionId,
        ];

        $updateData = [
            'status' => 'paid',
            'paid_at' => now(),
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('invoices', 'fee_mercadopago')) {
            $updateData['fee_mercadopago'] = round($feeMercadoPago, 2);
        }
        if (Schema::hasColumn('invoices', 'net_amount')) {
            $updateData['net_amount'] = round($netReceivedAmount, 2);
        }

        DB::table('invoices')->where('id', $invoice->id)->update($updateData);

        // Cancela quaisquer outras cobranças pendentes no Mercado Pago que possam ter ficado abertas
        try {
            $this->cancelPendingChargesForInvoice($invoice);
        } catch (\Throwable) {}

        $tenant = $invoice->tenant_id
            ? DB::table('tenants')->select('id', 'user_id')->where('id', $invoice->tenant_id)->first()
            : null;

        $notification = [
            'group' => 'billing',
            'type' => 'invoice_paid',
            'title' => 'Pagamento aprovado',
            'message' => "Fatura {$invoice->number} paga (Líquido: R$ " . number_format($netReceivedAmount, 2, ',', '.') . " | Tarifa MP: R$ " . number_format($feeMercadoPago, 2, ',', '.') . ")",
            'url' => $tenant ? '/painel' : "/admin/billing/invoices/{$invoice->id}",
            'icon' => 'bi-check-circle-fill',
            'color' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (
            $tenant
            && Schema::hasColumn('admin_notifications', 'tenant_id')
            && Schema::hasColumn('admin_notifications', 'user_id')
        ) {
            $notification['tenant_id'] = $tenant->id;
            $notification['user_id'] = $tenant->user_id;
        }

        DB::table('admin_notifications')->insert($notification);

        if (Schema::hasTable('customer_package_installments')) {
            app(TenantPackageService::class)->confirmInvoicePayment(
                (int) $invoice->id,
                'mercadopago',
                $transactionId
            );
        }

        app(WhmcsPaymentSyncer::class)->pushPaidInvoice($invoice, 'mercadopago', $transactionId);
        app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->settlePaid((int) $invoice->id, 'mercadopago', $transactionId);
    }

    /**
     * Resolve split automático do programa de afiliados para a fatura.
     */
    protected function resolveAffiliateSplitForInvoice(array $invoice): ?array
    {
        $tenantId = $invoice['tenant_id'] ?? data_get($invoice, 'meta.tenant_id') ?? null;
        if (! $tenantId && !empty($invoice['id'])) {
            $tenantInvoice = TenantInvoice::find($invoice['id']);
            $tenantId = $tenantInvoice?->tenant_id;
        }

        if (! $tenantId) {
            return null;
        }

        if (! Schema::hasTable('affiliate_referrals') || ! Schema::hasTable('affiliates')) {
            return null;
        }

        $referral = \App\Modules\Affiliates\Models\AffiliateReferral::where('tenant_id', $tenantId)->first();
        if (! $referral) {
            return null;
        }

        $affiliate = $referral->affiliate;
        if (! $affiliate || $affiliate->status !== 'active') {
            return null;
        }

        if (! $affiliate->split_enabled && empty($affiliate->mp_collector_id)) {
            return null;
        }

        $invoiceAmount = (float) ($invoice['amount'] ?? 0);
        if ($invoiceAmount <= 0) {
            return null;
        }

        $splitPct = (float) ($affiliate->split_pct ?: $affiliate->commission_pct ?: 20.0);
        $splitAmount = 0.0;

        if ($affiliate->commission_mode === 'fixed' && $affiliate->commission_fixed > 0) {
            $splitAmount = min($invoiceAmount, (float) $affiliate->commission_fixed);
        } else {
            $splitAmount = round($invoiceAmount * ($splitPct / 100), 2);
        }

        if ($splitAmount <= 0) {
            return null;
        }

        return [
            'affiliate_id' => $affiliate->id,
            'collector_id' => $affiliate->mp_collector_id,
            'split_amount' => $splitAmount,
            'split_pct'    => $splitPct,
            'split_mode'   => $affiliate->commission_mode ?: 'percentage',
        ];
    }

    /**
     * Resolve a data de expiração para o pagamento PIX no formato ISO 8601 exigido pelo Mercado Pago.
     * Exemplo: 2026-10-25T23:59:59.000-03:00
     */
    public function resolvePixExpiration(array $invoice): ?string
    {
        $dueDate = $invoice['due_date'] ?? null;
        if (!empty($dueDate)) {
            try {
                $due = Carbon::parse($dueDate, 'America/Sao_Paulo')->endOfDay();
                if ($due->isFuture() && $due->diffInMinutes(now('America/Sao_Paulo')) >= 30) {
                    return $due->format('Y-m-d\TH:i:s.000P');
                }
            } catch (\Throwable) {
                // Fallback para expiration_days caso haja erro no parsing da data
            }
        }

        $expDays = max(1, (int) $this->cfg('expiration_days', 3));
        return now('America/Sao_Paulo')->addDays($expDays)->format('Y-m-d\TH:i:s.000P');
    }
}

