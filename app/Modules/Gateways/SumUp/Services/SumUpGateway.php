<?php

namespace App\Modules\Gateways\SumUp\Services;

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
use App\Modules\WhmcsIntegration\Services\WhmcsPaymentSyncer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SumUpGateway implements PaymentGateway
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
        return 'sumup';
    }

    public function setTenantContext(TenantGateway $gateway): void
    {
        $this->tenantGatewayContext = $gateway;
    }

    public function createCharge(array $invoice, string $paymentMethod = 'all'): ChargeResult
    {
        if ($paymentMethod === 'pix' && $this->hasReusableCharge($invoice)) {
            return new ChargeResult(
                chargeId: (string) $invoice['gateway_charge_id'],
                paymentUrl: (string) ($invoice['payment_url'] ?? ''),
                webhookToken: (string) ($invoice['webhook_token'] ?? ''),
                raw: ['reused' => true]
            );
        }

        $apiKey = (string) $this->cfg('api_key', '');
        $merchantCode = (string) $this->cfg('merchant_code', '');

        if ($apiKey === '' || $merchantCode === '') {
            throw new \RuntimeException('API Key e Merchant Code da SumUp sao obrigatorios.');
        }

        $webhookToken = (string) ($invoice['webhook_token'] ?? Str::random(48));
        $checkoutReference = 'INV-'.$invoice['id'].'-'.$webhookToken;

        $payload = [
            'amount' => (float) $invoice['amount'],
            'checkout_reference' => $checkoutReference,
            'currency' => (string) $this->cfg('currency', 'BRL'),
            'description' => (string) ($invoice['description'] ?? $this->cfg('default_description', 'Pagamento')),
            'merchant_code' => $merchantCode,
            'redirect_url' => (string) $this->cfg('redirect_uri', url('/pagamento/sucesso')),
            'return_url' => route('webhook.sumup', ['token' => $webhookToken]),
            'hosted_checkout' => ['enabled' => (bool) $this->cfg('hosted_checkout_enabled', true)],
            'pay_to_email' => null,
        ];

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.sumup.com/v0.1/checkouts', array_filter($payload, fn ($value) => $value !== null))
            ->throw()
            ->json();

        $chargeId = (string) ($response['id'] ?? '');
        $paymentUrl = (string) ($response['hosted_checkout_url'] ?? '');

        DB::table('invoices')->where('id', $invoice['id'])->update([
            'gateway' => 'sumup',
            'gateway_charge_id' => $chargeId ?: null,
            'payment_url' => $paymentUrl ?: null,
            'webhook_token' => $webhookToken,
            'updated_at' => now(),
        ]);

        return new ChargeResult(
            chargeId: $chargeId,
            paymentUrl: $paymentUrl,
            webhookToken: $webhookToken,
            raw: $response,
        );
    }

    public function handleWebhook(array $payload, string $token): WebhookResult
    {
        $payloadChargeId = (string) (data_get($payload, 'checkout_id') ?: data_get($payload, 'id') ?: '');

        // Verificar se é uma tenant_invoice
        $tenantInvoice = TenantInvoice::where('webhook_token', $token)->first();
        if ($tenantInvoice) {
            return $this->handleTenantInvoiceWebhook($tenantInvoice, $payload, $payloadChargeId);
        }

        // Verificar se é uma invoice normal
        $invoice = DB::table('invoices')
            ->where('webhook_token', $token)
            ->when($payloadChargeId !== '', function ($query) use ($payloadChargeId) {
                $query->where(function ($nested) use ($payloadChargeId) {
                    $nested->whereNull('gateway_charge_id')
                        ->orWhere('gateway_charge_id', $payloadChargeId);
                });
            })
            ->first();

        if (! $invoice) {
            return new WebhookResult(false, 'ignored');
        }

        DB::table('payment_webhooks')->insert([
            'invoice_id' => $invoice->id,
            'gateway' => 'sumup',
            'event' => (string) (data_get($payload, 'event_type') ?: data_get($payload, 'status')),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($invoice && $invoice->tenant_id) {
            $tenantGateway = TenantGateway::where('tenant_id', $invoice->tenant_id)->where('gateway', 'sumup')->first();
            if ($tenantGateway) $this->setTenantContext($tenantGateway);
        }

        $chargeId = (string) ($invoice->gateway_charge_id ?: data_get($payload, 'checkout_id') ?: data_get($payload, 'id'));
        $status = $chargeId !== '' ? $this->fetchStatus($chargeId) : null;

        if (in_array($status, ['PAID', 'SUCCESSFUL'], true)) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

            app(WhmcsPaymentSyncer::class)->pushPaidInvoice($invoice, 'sumup', $chargeId);
            app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->settlePaid((int) $invoice->id, 'sumup', $chargeId);

            return new WebhookResult(true, 'paid', invoiceId: $invoice->id);
        }

        if (in_array($status, ['FAILED', 'CANCELED'], true)) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => 'canceled',
                'updated_at' => now(),
            ]);
        }

        return new WebhookResult(true, strtolower((string) ($status ?: 'processed')), invoiceId: $invoice->id);
    }

    protected function handleTenantInvoiceWebhook(TenantInvoice $invoice, array $payload, string $payloadChargeId): WebhookResult
    {
        if ($invoice->tenant_id) {
            $tenantGateway = TenantGateway::where('tenant_id', $invoice->tenant_id)->where('gateway', 'sumup')->first();
            if ($tenantGateway) $this->setTenantContext($tenantGateway);
        }

        $chargeId = (string) ($invoice->gateway_charge_id ?: data_get($payload, 'checkout_id') ?: data_get($payload, 'id'));
        $status = $chargeId !== '' ? $this->fetchStatus($chargeId) : null;

        if (in_array($status, ['PAID', 'SUCCESSFUL'], true)) {
            app(TenantInvoicePaymentService::class)->markInvoicePaid($invoice, $chargeId);

            return new WebhookResult(true, 'paid', invoiceId: $invoice->id);
        }

        return new WebhookResult(true, strtolower((string) ($status ?: 'processed')), invoiceId: $invoice->id);
    }

    public function fetchStatus(string $chargeId): ?string
    {
        $apiKey = (string) $this->cfg('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->get("https://api.sumup.com/v0.1/checkouts/{$chargeId}");

        return $response->ok() ? ($response->json()['status'] ?? null) : null;
    }

    public function refund(string $chargeId, ?float $amount = null): bool
    {
        $apiKey = (string) $this->cfg('api_key', '');
        if ($apiKey === '') {
            return false;
        }

        $payload = array_filter([
            'checkout_id' => $chargeId,
            'amount' => $amount,
        ], fn ($value) => $value !== null);

        return Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.sumup.com/v0.1/refunds', $payload)
            ->successful();
    }

    public function validateCredentials(): bool
    {
        return filled($this->cfg('api_key')) && filled($this->cfg('merchant_code'));
    }

    public function calculateFees(float $amount, array $context = []): array
    {
        return $this->fees->calculate($amount, $context['fees'] ?? [], $context['installment_rules'] ?? []);
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

        return $this->settings->get("gateways.sumup.{$key}", config("gateways.sumup.{$key}", $default));
    }

    protected function hasReusableCharge(array $invoice): bool
    {
        return in_array((string) ($invoice['status'] ?? ''), ['pending', 'sent', 'overdue'], true)
            && filled($invoice['gateway_charge_id'] ?? null)
            && filled($invoice['payment_url'] ?? null)
            && ($invoice['gateway'] ?? 'sumup') === 'sumup';
    }
}
