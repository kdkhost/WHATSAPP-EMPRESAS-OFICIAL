<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Billing\Services\InvoicePaymentService;
use App\Modules\Billing\Services\InvoiceSettlementService;
use App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway;
use App\Modules\Gateways\SumUp\Services\SumUpGateway;
use App\Modules\Gateways\EfiPro\Services\EfiProGateway;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantBillingConfig;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublicInvoiceController extends Controller
{
    /**
     * Exibe a página pública de pagamento tokenizada da fatura.
     */
    public function show(string $token)
    {
        $invoice = Invoice::with('customer')
            ->where('webhook_token', $token)
            ->first();

        if (! $invoice && auth()->check()) {
            // Fallback por ID ou número apenas para operadores autenticados
            $invoice = Invoice::with('customer')
                ->where('id', $token)
                ->orWhere('number', $token)
                ->first();
        }

        if (! $invoice) {
            return view('billing::public.not_found');
        }

        $tenant = $invoice->tenant_id ? Tenant::find($invoice->tenant_id) : null;
        $billingConfig = $tenant
            ? TenantBillingConfig::where('tenant_id', $tenant->id)->first()
            : null;

        $tenantGateway = $tenant
            ? TenantGateway::where('tenant_id', $tenant->id)->where('active', true)->orderByDesc('is_default')->first()
            : null;

        // Metadados
        $meta = $invoice->meta ?: [];

        // Auto-sincronização com WHMCS se a fatura for originária do WHMCS e ainda não paga
        $whmcsId = data_get($meta, 'whmcs_invoice_id');
        $connId  = data_get($meta, 'whmcs_connection_id');
        if ($whmcsId && $connId && $invoice->status !== 'paid') {
            $lastSync   = data_get($meta, 'whmcs_synced_at');
            $hasBalance = data_get($meta, 'whmcs_balance') !== null;
            $isStale    = ! $lastSync || \Carbon\Carbon::parse($lastSync)->diffInMinutes(now()) >= 3;

            if (! $hasBalance || $isStale) {
                try {
                    $whmcsConn = \App\Modules\WhmcsIntegration\Models\WhmcsConnection::find($connId);
                    if ($whmcsConn) {
                        $whmcsClient = new \App\Modules\WhmcsIntegration\Services\WhmcsClient($whmcsConn);
                        $whmcsClient->syncSingleInvoice($whmcsId);
                        $invoice->refresh();
                        $meta = $invoice->meta ?: [];
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[PublicInvoiceController] Falha auto-sync WHMCS #{$invoice->id}: " . $e->getMessage());
                }
            }
        }

        // Informações da Empresa (Tenant)
        $companyName     = $tenant?->name ?? config('app.name', 'KDK Host');
        $companyPhone    = data_get($tenant?->settings, 'support_phone')    ?: ($tenant?->phone    ?? '');
        $companyWhatsapp = data_get($tenant?->settings, 'support_whatsapp') ?: ($tenant?->whatsapp ?? '');
        $companyEmail    = data_get($tenant?->settings, 'support_email')    ?: ($tenant?->email    ?? '');
        $companyLogo     = $tenant?->logo_url ?? null;

        // Dados do Cliente
        $customer     = $invoice->customer;
        $customerName = $customer?->name ?? 'Cliente';
        $customerDoc  = $customer ? Format::document($customer->document) : '';

        // Cálculo de Valores — usa o service para garantir fallback automático via config do gateway
        $gatewaySlug    = (string) ($invoice->gateway ?: config('gateways.default', 'mercadopago'));
        $paymentService = app(InvoicePaymentService::class);
        $calc           = $paymentService->calcTotalPix($invoice, $gatewaySlug);
        $baseAmount     = $calc['baseAmount'];
        $multa          = $calc['multa'];
        $juros          = $calc['juros'];
        $totalPix       = $calc['totalPix'];

        // Taxa do Cartão de Crédito
        $feePassthrough = (bool) ($billingConfig?->fee_passthrough ?? false);
        $feePercent     = (float) ($billingConfig?->fee_percentage ?? 0);
        if ($feePercent <= 0 && $tenantGateway && ! empty($tenantGateway->fees['credit_card'])) {
            $feePercent = (float) $tenantGateway->fees['credit_card'];
        }

        $cardFeeAmount = 0.0;
        if ($feePercent > 0) {
            $cardFeeAmount = round($totalPix * ($feePercent / 100), 2);
        }
        $totalCard = round($totalPix + $cardFeeAmount, 2);

        // Pix payload existente — só utiliza se o valor gravado coincidir com o totalPix atual
        $freshInvoice = $invoice->fresh();
        $meta       = ($freshInvoice ? $freshInvoice->meta : $invoice->meta) ?: []; // atualiza após possível gravação de multa
        $prevAmount = (float) data_get($meta, 'gateway_payment.amount', 0);
        $pixStale   = $prevAmount > 0 && abs($prevAmount - $totalPix) > 0.01;

        $pixPayload = null;
        $pixQrImage = null;

        if (! $pixStale) {
            $pixPayload = data_get($meta, 'pix_copia_cola')
                ?: data_get($meta, 'pix_payload')
                ?: data_get($meta, 'gateway_payment.pix_copia_cola');

            $pixQrImage = data_get($meta, 'qrcode_url')
                ?: data_get($meta, 'pix_qrcode')
                ?: data_get($meta, 'gateway_payment.qrcode_url');
        }

        $isPaid    = $invoice->status === 'paid';
        $isOverdue = $invoice->due_date && $invoice->due_date->isPast() && ! $isPaid;

        // Crédito WHMCS: extrai do meta para exibição informativa na view
        $whmcsCreditApplied = (float) data_get($meta, 'whmcs_credit_applied', 0);
        $whmcsSubtotal      = (float) data_get($meta, 'whmcs_subtotal', 0);

        // Chave pública do Mercado Pago para checkout transparente no frontend
        $settings = app(\App\Modules\Settings\Services\SettingsManager::class);
        $mpGateway = $tenant ? TenantGateway::where('tenant_id', $tenant->id)->where('gateway', 'mercadopago')->where('active', true)->first() : null;
        $mpPublicKey = (string) ($mpGateway?->credentials['public_key']
            ?? $settings->get('gateways.mercadopago.public_key')
            ?? config('gateways.mercadopago.public_key', ''));

        $viewName = view()->exists('public.invoice') ? 'public.invoice' : 'billing::public.invoice';
        return view($viewName, compact(
            'invoice',
            'tenant',
            'companyName',
            'companyPhone',
            'companyWhatsapp',
            'companyEmail',
            'companyLogo',
            'customer',
            'customerName',
            'customerDoc',
            'baseAmount',
            'multa',
            'juros',
            'totalPix',
            'feePercent',
            'cardFeeAmount',
            'totalCard',
            'pixPayload',
            'pixQrImage',
            'isPaid',
            'isOverdue',
            'token',
            'whmcsCreditApplied',
            'whmcsSubtotal',
            'mpPublicKey'
        ));
    }

    /**
     * Gera ou recupera o Pix para a fatura via gateway ativo do tenant.
     */
    public function generatePix(string $token, InvoicePaymentService $paymentService): JsonResponse
    {
        $invoice = Invoice::where('webhook_token', $token)->first();
        if (! $invoice) {
            return response()->json(['ok' => false, 'error' => 'Fatura não encontrada.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => true, 'already_paid' => true, 'message' => 'Fatura já está paga.']);
        }

        // Calcula o total correto (com multa/juros) para exibir no retorno
        $gatewaySlug = (string) ($invoice->gateway ?: config('gateways.default', 'mercadopago'));
        $calc        = $paymentService->calcTotalPix($invoice, $gatewaySlug);
        $totalPix    = $calc['totalPix'];

        $meta    = $invoice->fresh()->meta ?: [];
        $pixCode = data_get($meta, 'pix_copia_cola')
            ?: data_get($meta, 'pix_payload')
            ?: data_get($meta, 'gateway_payment.pix_copia_cola');

        $pixQr = data_get($meta, 'qrcode_url')
            ?: data_get($meta, 'pix_qrcode')
            ?: data_get($meta, 'gateway_payment.qrcode_url');

        // Verifica se o PIX existente foi gerado pelo valor correto (com multa/juros)
        $pixAmount = (float) data_get($meta, 'gateway_payment.amount', 0);
        $pixStale  = $pixCode && abs($pixAmount - $totalPix) > 0.009; // reprocessa se valor diverge

        // Se já tem PIX válido com valor correto, retorna direto
        if ($pixCode && !$pixStale) {
            return response()->json([
                'ok'            => true,
                'pix_copia_cola' => $pixCode,
                'qrcode_url'    => $pixQr,
                'amount'        => $totalPix,
                'multa'         => $calc['multa'],
                'juros'         => $calc['juros'],
            ]);
        }

        // Gera novo PIX (ou regera com valor correto)
        try {
            $paymentService->hydrateGatewayPayment($invoice, true);
            $fresh     = $invoice->fresh();
            $freshMeta = $fresh->meta ?: [];

            $pixCode = data_get($freshMeta, 'gateway_payment.pix_copia_cola')
                ?: data_get($freshMeta, 'pix_copia_cola');
            $pixQr   = data_get($freshMeta, 'gateway_payment.qrcode_url')
                ?: data_get($freshMeta, 'qrcode_url');

            if (!$pixCode) {
                Log::warning("[PublicInvoice] Gateway não retornou pix_copia_cola para fatura #{$invoice->id}", [
                    'gateway'   => $fresh->gateway,
                    'meta_keys' => array_keys(data_get($freshMeta, 'gateway_payment', [])),
                ]);
                return response()->json([
                    'ok'    => false,
                    'error' => 'Gateway não retornou o código Pix. Verifique as configurações do gateway de pagamento.',
                ], 422);
            }

            return response()->json([
                'ok'             => true,
                'pix_copia_cola' => $pixCode,
                'qrcode_url'     => $pixQr,
                'amount'         => $totalPix,
                'multa'          => $calc['multa'],
                'juros'          => $calc['juros'],
            ]);
        } catch (\Throwable $e) {
            Log::error("[PublicInvoice] Erro ao gerar Pix para fatura #{$invoice->id}: " . $e->getMessage());
            return response()->json([
                'ok'    => false,
                'error' => 'Não foi possível gerar o Pix no momento. Detalhe: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Processa a opção de Cartão de Crédito aplicando a taxa configurada.
     */
    public function processCard(string $token, Request $request): JsonResponse
    {
        $invoice = Invoice::with('tenant')->where('webhook_token', $token)->first();
        if (! $invoice) {
            return response()->json(['ok' => false, 'error' => 'Fatura não encontrada.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => true, 'already_paid' => true, 'message' => 'Fatura já está paga.']);
        }

        $tenant = $invoice->tenant;
        $billingConfig = $tenant
            ? TenantBillingConfig::where('tenant_id', $tenant->id)->first()
            : null;

        $tenantGateway = $tenant
            ? TenantGateway::where('tenant_id', $tenant->id)->where('gateway', 'mercadopago')->where('active', true)->first()
            : null;

        // Calcula valor final do cartão com taxas aplicadas
        // Usa InvoicePaymentService para obter o valor correto considerando whmcs_balance e multas/juros
        $paymentService = app(InvoicePaymentService::class);
        $gatewaySlugForCalc = (string) ($invoice->gateway ?: config('gateways.default', 'mercadopago'));
        $calcCard = $paymentService->calcTotalPix($invoice, $gatewaySlugForCalc);
        $baseAmount = $calcCard['totalPix']; // Já inclui multa/juros e respeita whmcs_balance
        $feePercent = (float) ($billingConfig?->fee_percentage ?? 0);
        if ($feePercent <= 0 && $tenantGateway && ! empty($tenantGateway->fees['credit_card'])) {
            $feePercent = (float) $tenantGateway->fees['credit_card'];
        }

        $cardFeeAmount = $feePercent > 0 ? round($baseAmount * ($feePercent / 100), 2) : 0.0;
        $finalAmount = round($baseAmount + $cardFeeAmount, 2);

        // Se o cliente digitou os dados do cartão no formulário transparente, processa diretamente na API
        $cardNumber = preg_replace('/\D/', '', (string) $request->input('card_number'));
        $cardToken  = (string) $request->input('token');

        if (! empty($cardNumber) || ! empty($cardToken)) {
            return $this->processTransparentCardPayment(
                $invoice,
                $finalAmount,
                $cardFeeAmount,
                $tenantGateway,
                $request
            );
        }

        // Caso seja apenas uma requisição para obter os valores/status
        return response()->json([
            'ok'          => true,
            'amount'      => $finalAmount,
            'fee'         => $cardFeeAmount,
            'fee_percent' => $feePercent,
        ]);
    }

    /**
     * Processa a cobrança de cartão de crédito de forma transparente (sem sair do site).
     */
    protected function processTransparentCardPayment(
        Invoice $invoice,
        float $finalAmount,
        float $cardFeeAmount,
        ?TenantGateway $tenantGateway,
        Request $request
    ): JsonResponse {
        $settings = app(\App\Modules\Settings\Services\SettingsManager::class);
        $accessToken = $tenantGateway?->credentials['access_token']
            ?: $settings->get('gateways.mercadopago.access_token')
            ?: config('gateways.mercadopago.access_token');

        $publicKey = $tenantGateway?->credentials['public_key']
            ?: $settings->get('gateways.mercadopago.public_key')
            ?: config('gateways.mercadopago.public_key');

        if (! $accessToken) {
            return response()->json([
                'ok' => false,
                'error' => 'Gateway de cartão de crédito não configurado no sistema.',
            ], 422);
        }

        $cardToken      = (string) $request->input('token');
        $cardNumber     = preg_replace('/\D/', '', (string) $request->input('card_number'));
        $cardholderName = trim((string) $request->input('cardholder_name', ''));
        $expMonth       = (int) $request->input('expiration_month', 0);
        $expYear        = (int) $request->input('expiration_year', 0);
        if ($expYear < 100) {
            $expYear += 2000;
        }
        $securityCode = trim((string) $request->input('security_code', ''));
        $docType      = strtoupper(trim((string) $request->input('doc_type', 'CPF')));
        $docNumber    = preg_replace('/\D/', '', (string) $request->input('doc_number', ''));
        $installments = max(1, (int) $request->input('installments', 1));

        // Validações locais antes de enviar ao gateway
        if (empty($cardToken)) {
            if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
                return response()->json(['ok' => false, 'error' => 'Número do cartão inválido.'], 422);
            }
            if (empty($cardholderName)) {
                return response()->json(['ok' => false, 'error' => 'Informe o nome do titular impresso no cartão.'], 422);
            }
            if ($expMonth < 1 || $expMonth > 12 || $expYear < (int) date('Y')) {
                return response()->json(['ok' => false, 'error' => 'Data de validade do cartão inválida.'], 422);
            }
            if (strlen($securityCode) < 3 || strlen($securityCode) > 4) {
                return response()->json(['ok' => false, 'error' => 'Código de segurança (CVV) inválido.'], 422);
            }
            if (empty($docNumber)) {
                return response()->json(['ok' => false, 'error' => 'Informe o CPF/CNPJ do titular do cartão.'], 422);
            }

            // Gera card_token seguro via API do Mercado Pago
            try {
                $tokenUrl = "https://api.mercadopago.com/v1/card_tokens?public_key={$publicKey}";
                $tokenResp = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post($tokenUrl, [
                        'card_number'      => $cardNumber,
                        'cardholder'       => [
                            'name'           => $cardholderName,
                            'identification' => [
                                'type'   => $docType,
                                'number' => $docNumber,
                            ],
                        ],
                        'security_code'    => $securityCode,
                        'expiration_month' => $expMonth,
                        'expiration_year'  => $expYear,
                    ]);

                if (! $tokenResp->successful()) {
                    $errData = $tokenResp->json();
                    $msg = data_get($errData, 'cause.0.description')
                        ?: data_get($errData, 'message')
                        ?: 'Dados do cartão recusados na validação.';
                    return response()->json(['ok' => false, 'error' => $this->translateMpError($msg)], 422);
                }

                $cardToken = (string) data_get($tokenResp->json(), 'id');
            } catch (\Throwable $e) {
                Log::error("[PublicInvoice] Erro ao gerar card_token: " . $e->getMessage());
                return response()->json(['ok' => false, 'error' => 'Erro ao validar cartão: ' . $e->getMessage()], 500);
            }
        }

        // Identifica a bandeira do cartão
        $bin = substr($cardNumber, 0, 6);
        $paymentMethodId = $this->detectCardBrand($bin, $cardNumber);

        // Dados do pagador
        $customer = $invoice->customer;
        $payerEmail = filter_var($customer?->email, FILTER_VALIDATE_EMAIL) ?: 'cliente' . rand(1000, 9999) . '@email.com';
        $payerDoc = $docNumber ?: preg_replace('/\D/', '', (string) $customer?->document);

        $paymentPayload = [
            'transaction_amount' => $finalAmount,
            'token'              => $cardToken,
            'description'        => $invoice->description ?: ('Cobrança Fatura #' . ($invoice->number ?? $invoice->id)),
            'installments'       => $installments,
            'payment_method_id'  => $paymentMethodId,
            'payer'              => [
                'email'          => $payerEmail,
                'first_name'     => $cardholderName ?: ($customer?->name ?: 'Cliente'),
                'identification' => [
                    'type'   => strlen($payerDoc) > 11 ? 'CNPJ' : 'CPF',
                    'number' => $payerDoc,
                ],
            ],
            'external_reference' => 'INV-' . $invoice->id,
            'notification_url'   => route('webhook.mercadopago', ['token' => $invoice->webhook_token]),
        ];

        try {
            $paymentResp = Http::withToken($accessToken)
                ->acceptJson()
                ->withHeaders(['X-Idempotency-Key' => 'CARD-' . $invoice->id . '-' . time()])
                ->post('https://api.mercadopago.com/v1/payments', array_filter($paymentPayload, fn($v) => $v !== null));

            $pData = $paymentResp->json();
            $status = (string) data_get($pData, 'status');
            $statusDetail = (string) data_get($pData, 'status_detail');
            $chargeId = (string) data_get($pData, 'id');

            $errorMsg = null;
            if ($status === 'approved') {
                $errorMsg = 'Aprovado';
            } elseif ($status === 'in_process' || $status === 'pending') {
                $errorMsg = 'Em análise pela operadora';
            } else {
                $errorMsg = $this->translateMpStatusDetail($statusDetail)
                    ?: data_get($pData, 'message')
                    ?: 'Pagamento não autorizado pela operadora do cartão.';
            }

            // Registra tentativa no meta
            $meta = $invoice->meta ?: [];
            $attempts = $meta['payment_attempts'] ?? [];
            $attempts[] = [
                'date' => now()->toDateTimeString(),
                'gateway' => 'mercadopago',
                'method' => 'credit_card',
                'brand' => $paymentMethodId,
                'last4' => substr($cardNumber, -4),
                'amount' => $finalAmount,
                'status' => $status,
                'status_detail' => $statusDetail,
                'error_message' => $errorMsg,
            ];
            if (count($attempts) > 15) {
                $attempts = array_slice($attempts, -15);
            }
            $meta['payment_attempts'] = $attempts;
            $invoice->update(['meta' => $meta]);

            if ($status === 'approved') {
                // Liquida a fatura e calcula deduções de tarifas no MercadoPagoPaymentService
                app(\App\Modules\MercadoPago\Services\MercadoPagoPaymentService::class)->markInvoiceAsPaid(
                    $invoice->id,
                    $chargeId,
                    $pData
                );

                // Liquida a fatura imediatamente de forma global
                app(InvoiceSettlementService::class)->settlePaid(
                    invoice: $invoice,
                    gateway: 'mercadopago',
                    transactionId: $chargeId,
                    paidAmount: $finalAmount
                );

                return response()->json([
                    'ok'      => true,
                    'status'  => 'approved',
                    'message' => 'Pagamento aprovado com sucesso!',
                ]);
            }

            if ($status === 'in_process' || $status === 'pending') {
                return response()->json([
                    'ok'      => true,
                    'status'  => 'pending',
                    'message' => 'Pagamento em análise pela operadora do cartão. Aguarde a confirmação.',
                ]);
            }

            return response()->json([
                'ok'     => false,
                'status' => $status ?: 'rejected',
                'error'  => $errorMsg,
            ], 422);

        } catch (\Throwable $e) {
            Log::error("[PublicInvoice] Erro ao processar pagamento com cartão para fatura #{$invoice->id}: " . $e->getMessage());
            return response()->json([
                'ok'    => false,
                'error' => 'Falha ao processar pagamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function detectCardBrand(string $bin, string $cardNumber): string
    {
        if (preg_match('/^4/', $cardNumber)) return 'visa';
        if (preg_match('/^(5[1-5]|2[2-7])/', $cardNumber)) return 'master';
        if (preg_match('/^(4011|438935|451416|4576|504175|506699|5067|5090|627780|636297|636368)/', $cardNumber)) return 'elo';
        if (preg_match('/^3[47]/', $cardNumber)) return 'amex';
        if (preg_match('/^(606282|3841)/', $cardNumber)) return 'hipercard';
        return 'visa';
    }

    protected function translateMpStatusDetail(string $detail): string
    {
        return match ($detail) {
            'cc_rejected_bad_filled_card_number'   => 'Número do cartão incorreto ou inválido.',
            'cc_rejected_bad_filled_date'          => 'Data de validade incorreta.',
            'cc_rejected_bad_filled_other'         => 'Dados do cartão incorretos.',
            'cc_rejected_bad_filled_security_code' => 'Código de segurança (CVV) inválido.',
            'cc_rejected_blacklist'                => 'Cartão não autorizado para este pagamento.',
            'cc_rejected_call_for_authorize'       => 'Autorização necessária: contate seu banco emissor.',
            'cc_rejected_card_disabled'            => 'Cartão desativado. Ligue para o banco emissor.',
            'cc_rejected_card_error'               => 'Não foi possível processar o cartão. Tente outro cartão.',
            'cc_rejected_duplicated_payment'       => 'Pagamento duplicado detectado recentemente.',
            'cc_rejected_high_risk'                => 'Pagamento recusado por segurança pela operadora.',
            'cc_rejected_insufficient_amount'      => 'Limite insuficiente no cartão de crédito.',
            'cc_rejected_invalid_installments'     => 'Número de parcelas não permitido para este cartão.',
            'cc_rejected_max_attempts'             => 'Limite de tentativas excedido. Tente outro cartão.',
            'cc_rejected_other_reason'             => 'Pagamento recusado pela operadora do cartão.',
            default                                => '',
        };
    }

    protected function translateMpError(string $err): string
    {
        if (str_contains($err, 'card_number')) return 'Número do cartão inválido.';
        if (str_contains($err, 'security_code')) return 'Código de segurança (CVV) inválido.';
        if (str_contains($err, 'expiration')) return 'Data de validade do cartão inválida.';
        if (str_contains($err, 'cardholder')) return 'Nome do titular inválido.';
        return $err;
    }

    /**
     * Consulta o status da fatura em tempo real para atualização dinâmica da tela via polling.
     */
    public function status(string $token): JsonResponse
    {
        $invoice = Invoice::where('webhook_token', $token)->first();
        if (! $invoice) {
            return response()->json(['ok' => false, 'error' => 'Não encontrada'], 404);
        }

        return response()->json([
            'ok' => true,
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at?->format('d/m/Y H:i'),
            'paid_amount' => (float) $invoice->amount,
        ]);
    }
}
