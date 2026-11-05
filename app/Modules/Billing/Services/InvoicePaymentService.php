<?php

namespace App\Modules\Billing\Services;

use App\Models\Invoice;
use App\Modules\Gateways\Contracts\PaymentGateway;
use App\Modules\Gateways\EfiPro\Services\EfiProGateway;
use App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway;
use App\Modules\Gateways\SumUp\Services\SumUpGateway;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class InvoicePaymentService
{
    public function __construct(protected SettingsManager $settings)
    {
    }

    /**
     * Calcula o valor total do PIX (base + multa + juros por atraso).
     * Usa valores já registrados no meta da fatura ou calcula automaticamente
     * via configuração do gateway quando a fatura está vencida e sem acréscimos.
     */
    public function calcTotalPix(Invoice $invoice, string $gatewaySlug): array
    {
        $meta        = $invoice->meta ?? [];
        $multa       = (float) data_get($meta, 'multa_atraso', 0);
        $juros       = (float) data_get($meta, 'juros_atraso', 0);
        $diasAtraso  = (int)   data_get($meta, 'dias_atraso', 0);

        // Se a fatura tem whmcs_balance gravado (saldo pendente após créditos aplicados):
        $whmcsBalance    = (float) data_get($meta, 'whmcs_balance', 0);
        $valorPendente   = (float) data_get($meta, 'valor_pendente', 0);
        $valorAtualizado = (float) data_get($meta, 'valor_atualizado', 0);

        if ($whmcsBalance > 0) {
            $baseAmount = $whmcsBalance;
        } elseif ($valorPendente > 0) {
            $baseAmount = $valorPendente;
        } elseif ($valorAtualizado > 0 && abs((float)$invoice->amount - $valorAtualizado) < 0.01 && ($multa > 0 || $juros > 0)) {
            // Se o amount na tabela invoices já é o valor final com multa, extrai o valor base real
            $baseAmount = max(0.0, round((float)$invoice->amount - $multa - $juros, 2));
        } else {
            $baseAmount = (float) $invoice->amount;
        }

        // Se não há multa/juros no meta e a fatura está vencida → calcular pela config do gateway
        if ($multa == 0 && $juros == 0 && $invoice->due_date?->isPast() && $invoice->status !== 'paid') {
            $feePct       = (float) $this->gatewayConfig($gatewaySlug, 'late_fee_pct', 0);
            $interestPct  = (float) $this->gatewayConfig($gatewaySlug, 'late_interest_pct_day', 0);
            $carencia     = (int)   $this->gatewayConfig($gatewaySlug, 'late_fee_after_days', 1);
            $diasAtraso   = (int) $invoice->due_date->diffInDays(now());

            if ($diasAtraso >= $carencia) {
                if ($feePct > 0) {
                    $multa = round($baseAmount * ($feePct / 100), 2);
                }
                if ($interestPct > 0) {
                    $juros = round($baseAmount * ($interestPct / 100) * $diasAtraso, 2);
                }

                // Persiste no meta para ficar registrado e exibição consistente
                if ($multa > 0 || $juros > 0) {
                    $newMeta = array_merge($meta, [
                        'multa_atraso' => $multa,
                        'juros_atraso' => $juros,
                        'dias_atraso'  => $diasAtraso,
                    ]);
                    $invoice->update(['meta' => $newMeta]);
                    // Atualiza referência local
                    $meta = $newMeta;

                    Log::info("[InvoicePaymentService] Multa/juros calculados automaticamente para fatura #{$invoice->id}", [
                        'dias_atraso'  => $diasAtraso,
                        'multa'        => $multa,
                        'juros'        => $juros,
                        'gateway'      => $gatewaySlug,
                    ]);
                }
            }
        }

        $totalPix = round($baseAmount + $multa + $juros, 2);

        return compact('baseAmount', 'multa', 'juros', 'diasAtraso', 'totalPix');
    }

    public function hydrateGatewayPayment(Invoice $invoice, bool $force = false): array
    {
        $gatewaySlug = (string) ($invoice->gateway ?: '');
        if ($gatewaySlug === '' && $invoice->tenant_id) {
            $defaultTg = \App\Modules\Tenancy\Models\TenantGateway::where('tenant_id', $invoice->tenant_id)
                ->where('active', true)
                ->where('is_default', true)
                ->value('gateway');
            if (! $defaultTg) {
                $defaultTg = \App\Modules\Tenancy\Models\TenantGateway::where('tenant_id', $invoice->tenant_id)
                    ->where('active', true)
                    ->orderBy('priority')
                    ->value('gateway');
            }
            $gatewaySlug = (string) ($defaultTg ?: config('gateways.default', 'mercadopago'));
        }
        if ($gatewaySlug === '') {
            $gatewaySlug = (string) config('gateways.default', 'mercadopago');
        }

        $calc        = $this->calcTotalPix($invoice, $gatewaySlug);
        $totalPix    = $calc['totalPix'];

        // Se o valor do charge gravado diverge do totalPix atual, forçar recriação
        $meta           = $invoice->meta ?? [];
        $previousAmount = (float) data_get($meta, 'gateway_payment.amount', 0);
        if ($previousAmount > 0 && abs($previousAmount - $totalPix) > 0.01) {
            $force = true;
        }

        if (! $force && $invoice->hasReusableCharge()) {
            return [
                'ok'          => true,
                'reused'      => true,
                'payment_url' => $invoice->payment_url,
                'charge_id'   => $invoice->gateway_charge_id,
            ];
        }

        $gateway     = $this->resolveGateway($gatewaySlug);

        if ($invoice->tenant_id && method_exists($gateway, 'setTenantContext')) {
            $tenantGateway = \App\Modules\Tenancy\Models\TenantGateway::where('tenant_id', $invoice->tenant_id)
                ->where('gateway', $gatewaySlug)
                ->where('active', true)
                ->first();

            if ($tenantGateway && !empty($tenantGateway->credentials)) {
                $gateway->setTenantContext($tenantGateway);
            }
        }

        // Passa o amount correto (com acréscimos) e força PIX como método
        $invoiceArray             = $invoice->fresh()->toArray(); // fresh após possível update do meta
        $invoiceArray['amount']   = $totalPix;
        $invoiceArray['meta']     = array_merge($invoiceArray['meta'] ?? [], ['payment_method' => 'pix']);

        $result = $gateway->createCharge($invoiceArray);

        $meta = $invoice->fresh()->meta ?: [];
        $meta['gateway_payment'] = array_filter([
            'provider'      => $gatewaySlug,
            'amount'        => $totalPix,
            'base_amount'   => $calc['baseAmount'],
            'multa'         => $calc['multa'],
            'juros'         => $calc['juros'],
            'dias_atraso'   => $calc['diasAtraso'],
            'charge_id'     => $result->chargeId,
            'payment_url'   => $result->paymentUrl,
            'ticket_url'    => $this->firstFilled(
                data_get($result->raw, 'point_of_interaction.transaction_data.ticket_url'),
                data_get($result->raw, 'ticket_url'),
                data_get($meta, 'gateway_payment.ticket_url')
            ),
            'invoice_url'   => $this->firstFilled(
                data_get($result->raw, 'invoice_url'),
                data_get($result->raw, 'data.link'),
            ),
            'boleto_url'    => $this->firstFilled(
                data_get($result->raw, 'ticket_url'),
                data_get($result->raw, 'boleto_url'),
                data_get($result->raw, 'banking_billet.link'),
                data_get($result->raw, 'data.banking_billet.link'),
            ),
            'boleto_pdf_url' => $this->firstFilled(
                data_get($result->raw, 'ticket_pdf_url'),
                data_get($result->raw, 'boleto_pdf_url'),
                data_get($result->raw, 'banking_billet.pdf.charge'),
                data_get($result->raw, 'data.banking_billet.pdf.charge'),
            ),
            'linha_digitavel' => $this->firstFilled(
                data_get($result->raw, 'barcode.content'),
                data_get($result->raw, 'linha_digitavel'),
                data_get($result->raw, 'banking_billet.barcode'),
                data_get($result->raw, 'data.banking_billet.barcode'),
            ),
            'qrcode'        => data_get($result->raw, 'qrcode'),
            'pix_copia_cola' => $this->firstFilled(
                data_get($result->raw, 'qrcode.qrcode'),
                data_get($result->raw, 'qrcode.qrcodeTexto'),
                data_get($result->raw, 'point_of_interaction.transaction_data.qr_code'),
            ),
            'qrcode_url'    => $this->firstFilled(
                data_get($result->raw, 'qrcode.imagemQrcode'),
                data_get($result->raw, 'point_of_interaction.transaction_data.qr_code_base64'),
            ),
            'generated_at'  => now()->toISOString(),
        ], fn ($value) => $value !== null && $value !== '');

        $meta['gateway_raw'] = array_filter([
            'qrcode'               => data_get($result->raw, 'qrcode'),
            'point_of_interaction' => data_get($result->raw, 'point_of_interaction'),
            'banking_billet'       => data_get($result->raw, 'banking_billet'),
            'data'                 => Arr::only((array) data_get($result->raw, 'data', []), ['link', 'banking_billet']),
        ]);

        $webhookToken = $invoice->webhook_token ?: ($result->webhookToken ?: \Illuminate\Support\Str::random(48));
        $secureCheckoutUrl = url('/fatura/' . $webhookToken);

        $updateData = [
            'payment_url'       => $secureCheckoutUrl,
            'gateway'           => $gatewaySlug,
            'gateway_charge_id' => $result->chargeId,
            'webhook_token'     => $webhookToken,
            'meta'              => $meta,
        ];

        $invoice->update($updateData);

        return [
            'ok'          => true,
            'reused'      => false,
            'payment_url' => $secureCheckoutUrl,
            'charge_id'   => $result->chargeId,
            'total_pix'   => $totalPix,
        ];
    }

    public function resolveGateway(string $slug): PaymentGateway
    {
        return match ($slug) {
            'mercadopago' => app(MercadoPagoGateway::class),
            'efipro'      => app(EfiProGateway::class),
            'sumup'       => app(SumUpGateway::class),
            default       => app(MercadoPagoGateway::class),
        };
    }

    /**
     * Lê uma configuração do gateway via SettingsManager (com fallback para config/).
     */
    protected function gatewayConfig(string $slug, string $key, mixed $default = null): mixed
    {
        return $this->settings->get("gateways.{$slug}.{$key}", config("gateways.{$slug}.{$key}", $default));
    }

    protected function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}
