<?php

namespace App\Modules\Gateways\EfiPro\Services;

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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EfiProGateway implements PaymentGateway
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
        return 'efipro';
    }

    public function setTenantContext(TenantGateway $gateway): void
    {
        $this->tenantGatewayContext = $gateway;
    }

    public function createCharge(array $invoice): ChargeResult
    {
        if ($this->hasReusableCharge($invoice)) {
            return new ChargeResult(
                chargeId: (string) $invoice['gateway_charge_id'],
                paymentUrl: (string) ($invoice['payment_url'] ?? ''),
                webhookToken: (string) ($invoice['webhook_token'] ?? ''),
                raw: ['reused' => true]
            );
        }

        $paymentMethod = strtolower((string) data_get($invoice, 'meta.payment_method', 'pix'));
        if (! in_array($paymentMethod, ['pix', 'credit_card', 'card'], true)) {
            $paymentMethod = 'pix';
        }

        return $paymentMethod === 'pix'
            ? $this->createPixCharge($invoice)
            : $this->createCardCharge($invoice);
    }

    public function handleWebhook(array $payload, string $token): WebhookResult
    {
        $txid = (string) (data_get($payload, 'pix.0.txid') ?: data_get($payload, 'txid') ?: data_get($payload, 'data.txid'));

        // Verificar se é uma tenant_invoice
        $tenantInvoice = TenantInvoice::where('webhook_token', $token)->first();
        if ($tenantInvoice) {
            return $this->handleTenantInvoiceWebhook($tenantInvoice, $payload, $txid);
        }

        // Verificar se é uma invoice normal
        $invoice = DB::table('invoices')
            ->where('webhook_token', $token)
            ->when($txid !== '', function ($query) use ($txid) {
                $query->where(function ($nested) use ($txid) {
                    $nested->whereNull('gateway_charge_id')
                        ->orWhere('gateway_charge_id', $txid);
                });
            })
            ->first();

        if (! $invoice) {
            return new WebhookResult(false, 'ignored');
        }

        DB::table('payment_webhooks')->insert([
            'invoice_id' => $invoice->id,
            'gateway' => 'efipro',
            'event' => $txid !== '' ? 'pix' : (string) data_get($payload, 'tipo'),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'processed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($txid !== '') {
            if ($invoice && $invoice->tenant_id) {
                $tenantGateway = TenantGateway::where('tenant_id', $invoice->tenant_id)->where('gateway', 'efipro')->first();
                if ($tenantGateway) $this->setTenantContext($tenantGateway);
            }

            if ($this->fetchStatus($txid) === 'CONCLUIDA' || data_get($payload, 'pix.0.status') === 'CONCLUIDA') {
                DB::table('invoices')->where('id', $invoice->id)->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

                app(WhmcsPaymentSyncer::class)->pushPaidInvoice($invoice, 'efipro', $txid);
                app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->settlePaid((int) $invoice->id, 'efipro', $txid);

                return new WebhookResult(true, 'paid', invoiceId: $invoice->id);
            }
        }

        return new WebhookResult(true, 'processed', invoiceId: $invoice->id);
    }

    protected function handleTenantInvoiceWebhook(TenantInvoice $invoice, array $payload, string $txid): WebhookResult
    {
        if ($txid !== '') {
            if ($invoice->tenant_id) {
                $tenantGateway = TenantGateway::where('tenant_id', $invoice->tenant_id)->where('gateway', 'efipro')->first();
                if ($tenantGateway) $this->setTenantContext($tenantGateway);
            }

            if ($this->fetchStatus($txid) === 'CONCLUIDA' || data_get($payload, 'pix.0.status') === 'CONCLUIDA') {
                app(TenantInvoicePaymentService::class)->markInvoicePaid($invoice, $txid);

                return new WebhookResult(true, 'paid', invoiceId: $invoice->id);
            }
        }

        return new WebhookResult(true, 'processed', invoiceId: $invoice->id);
    }

    public function fetchStatus(string $chargeId): ?string
    {
        $response = $this->http()->get("/v2/cob/{$chargeId}");

        return $response->ok() ? ($response->json()['status'] ?? null) : null;
    }

    public function refund(string $chargeId, ?float $amount = null): bool
    {
        return false;
    }

    public function validateCredentials(): bool
    {
        return filled($this->cfg('client_id')) && filled($this->cfg('client_secret'));
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

    protected function createPixCharge(array $invoice): ChargeResult
    {
        $webhookToken = (string) ($invoice['webhook_token'] ?? Str::random(48));

        $response = $this->http()
            ->post('/v2/cob', [
                'calendario' => [
                    'expiracao' => (int) $this->cfg('pix_expiration', 3600),
                ],
                'devedor' => [],
                'valor' => [
                    'original' => number_format((float) $invoice['amount'], 2, '.', ''),
                ],
                'chave' => (string) $this->cfg('pix_key', ''),
                'solicitacaoPagador' => Str::limit((string) ($invoice['description'] ?? 'Cobranca'), 140, ''),
                'infoAdicionais' => [
                    ['nome' => 'fatura', 'valor' => (string) ($invoice['number'] ?? 'INV-'.$invoice['id'])],
                    ['nome' => 'token', 'valor' => $webhookToken],
                ],
            ])
            ->throw()
            ->json();

        $txid = (string) ($response['txid'] ?? '');
        $locId = data_get($response, 'loc.id');
        $qrcodeResponse = $locId
            ? $this->http()->get("/v2/loc/{$locId}/qrcode")->throw()->json()
            : [];

        $pixLink = (string) (
            data_get($qrcodeResponse, 'imagemQrcode')
            ?: data_get($qrcodeResponse, 'qrcode')
            ?: data_get($qrcodeResponse, 'qrcodeTexto')
            ?: ''
        );

        DB::table('invoices')->where('id', $invoice['id'])->update([
            'gateway' => 'efipro',
            'gateway_charge_id' => $txid ?: null,
            'payment_url' => $pixLink ?: null,
            'webhook_token' => $webhookToken,
            'updated_at' => now(),
        ]);

        return new ChargeResult(
            chargeId: $txid,
            paymentUrl: $pixLink,
            webhookToken: $webhookToken,
            raw: array_merge($response, ['qrcode' => $qrcodeResponse]),
        );
    }

    protected function createCardCharge(array $invoice): ChargeResult
    {
        $paymentToken = (string) data_get($invoice, 'meta.payment_token', '');
        if ($paymentToken === '') {
            throw new \RuntimeException('Para cobranca por cartao na Efi, informe o payment_token no checkout antes de gerar o link.');
        }

        $webhookToken = (string) ($invoice['webhook_token'] ?? Str::random(48));

        $payload = [
            'items' => [[
                'name' => Str::limit((string) ($invoice['description'] ?? 'Cobranca'), 255, ''),
                'value' => (int) round(((float) $invoice['amount']) * 100),
                'amount' => 1,
            ]],
            'metadata' => [
                'custom_id' => (string) ($invoice['number'] ?? 'INV-'.$invoice['id']),
                'notification_url' => route('webhook.efipro', ['token' => $webhookToken]),
            ],
            'payment' => [
                'credit_card' => [
                    'payment_token' => $paymentToken,
                    'installments' => max(1, (int) $this->cfg('installments_max', 1)),
                    'billing_address' => data_get($invoice, 'meta.billing_address', []),
                    'customer' => data_get($invoice, 'meta.customer', []),
                ],
            ],
        ];

        $response = $this->chargesHttp()
            ->post('/v1/charge/one-step', $payload)
            ->throw()
            ->json();

        $chargeId = (string) (data_get($response, 'data.charge_id') ?: data_get($response, 'charge_id') ?: '');
        $paymentUrl = (string) (data_get($response, 'data.link') ?: data_get($response, 'payment_url') ?: '');

        DB::table('invoices')->where('id', $invoice['id'])->update([
            'gateway' => 'efipro',
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

    protected function http(): PendingRequest
    {
        $token = $this->accessToken();
        $certificate = (string) $this->cfg('certificate_path', '');

        $request = Http::acceptJson()
            ->withToken($token)
            ->baseUrl($this->pixBaseUrl());

        if ($certificate !== '') {
            $request = $request->withOptions(['cert' => $certificate]);
        }

        return $request;
    }

    protected function chargesHttp(): PendingRequest
    {
        return Http::acceptJson()
            ->withBasicAuth((string) $this->cfg('client_id', ''), (string) $this->cfg('client_secret', ''))
            ->baseUrl($this->chargesBaseUrl());
    }

    protected function accessToken(): string
    {
        $cacheKey = 'gateways.efipro.oauth.'.md5((string) $this->cfg('client_id', '').'|'.(string) $this->cfg('sandbox', false));
        $cached = cache()->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $certificate = (string) $this->cfg('certificate_path', '');

        $request = Http::acceptJson()
            ->asForm()
            ->withBasicAuth((string) $this->cfg('client_id', ''), (string) $this->cfg('client_secret', ''))
            ->baseUrl($this->pixBaseUrl());

        if ($certificate !== '') {
            $request = $request->withOptions(['cert' => $certificate]);
        }

        $response = $request->post('/oauth/token', ['grant_type' => 'client_credentials'])->throw()->json();
        $token = (string) ($response['access_token'] ?? '');
        $ttl = max(60, ((int) ($response['expires_in'] ?? 300)) - 60);
        cache()->put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    protected function pixBaseUrl(): string
    {
        return (bool) $this->cfg('sandbox', true)
            ? 'https://pix-h.api.efipay.com.br'
            : 'https://pix.api.efipay.com.br';
    }

    protected function chargesBaseUrl(): string
    {
        return (bool) $this->cfg('sandbox', true)
            ? 'https://cobrancas-h.api.efipay.com.br'
            : 'https://cobrancas.api.efipay.com.br';
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        if ($this->tenantGatewayContext) {
            $val = $this->tenantGatewayContext->credentials[$key] ?? $this->tenantGatewayContext->settings[$key] ?? null;
            if ($val !== null) return $val;
        }

        return $this->settings->get("gateways.efipro.{$key}", config("gateways.efipro.{$key}", $default));
    }

    protected function hasReusableCharge(array $invoice): bool
    {
        return in_array((string) ($invoice['status'] ?? ''), ['pending', 'sent', 'overdue'], true)
            && filled($invoice['gateway_charge_id'] ?? null)
            && filled($invoice['payment_url'] ?? null)
            && ($invoice['gateway'] ?? 'efipro') === 'efipro';
    }
}
