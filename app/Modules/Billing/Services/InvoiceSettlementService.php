<?php

namespace App\Modules\Billing\Services;

use App\Models\Invoice;
use App\Modules\Tenancy\Models\CustomerPackageInstallment;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço centralizador de baixa e liquidação de faturas.
 *
 * Ao confirmar o pagamento de uma fatura (via gateway, pix, webhook, checkout ou manual):
 * 1. Atualiza status para 'paid' e define paid_at
 * 2. Dá baixa em parcelas de pacotes vinculadas (se houver)
 * 3. Se a fatura for originária do WHMCS (meta['whmcs_id']), dá baixa automática
 *    no WHMCS do tenant através da API (AddInvoicePayment)
 * 4. Notifica o tenant no painel administrativo
 * 5. Se solicitado/habilitado, envia mensagem de confirmação no WhatsApp
 */
class InvoiceSettlementService
{
    public function settlePaid(
        Invoice|int $invoice,
        string $gateway = 'manual',
        ?string $transactionId = null,
        ?float $paidAmount = null,
        bool $notifyCustomer = true,
        ?\DateTimeInterface $paidAt = null,
        ?string $notes = null
    ): bool {
        $model = is_int($invoice) ? Invoice::find($invoice) : $invoice;
        if (! $model) {
            return false;
        }

        // Se já estava paga, evita duplicidade
        if ($model->status === 'paid' && $model->paid_at) {
            return true;
        }

        $paidAmount = $paidAmount ?? (float) $model->amount;
        $now = now();
        $settledAt = $paidAt ? Carbon::parse($paidAt) : $now;

        DB::beginTransaction();
        try {
            // 1. Atualiza a fatura
            $meta = is_array($model->meta) ? $model->meta : [];
            if ($transactionId) {
                $meta['gateway_payment']['charge_id'] = $transactionId;
                $meta['gateway_payment']['settled_at'] = $settledAt->toIso8601String();
            }

            $meta['settled_manually'] = in_array(strtolower($gateway), ['manual', 'dinheiro', 'pix', 'cartao', 'transferencia', 'boleto', 'outro'], true);
            if (Auth::check()) {
                $meta['settled_by_user_id'] = Auth::id();
                $meta['settled_by_user_name'] = Auth::user()?->name ?: 'Usuário';
            }
            if ($notes) {
                $meta['settlement_note'] = $notes;
            }

            $model->update([
                'status' => 'paid',
                'paid_at' => $settledAt,
                'gateway' => $gateway ?: ($model->gateway ?: 'manual'),
                'gateway_charge_id' => $transactionId ?: $model->gateway_charge_id,
                'meta' => $meta,
            ]);

            // 2. Verifica se a fatura está vinculada a parcelas de pacotes
            $hasPackageInstallment = false;
            if (Schema::hasTable('customer_package_installments')) {
                $hasPackageInstallment = DB::table('customer_package_installments')
                    ->where('invoice_id', $model->id)
                    ->exists();
            }

            // 3. Cancela cobranças pendentes já agendadas para essa fatura
            if (Schema::hasTable('billing_dispatches')) {
                DB::table('billing_dispatches')
                    ->where('invoice_id', $model->id)
                    ->whereIn('status', ['scheduled', 'failed', 'pending'])
                    ->update([
                        'status' => 'canceled',
                        'last_error' => "Cancelado: pagamento confirmado via {$gateway}.",
                        'updated_at' => $now,
                    ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar fatura #{$model->id} para paga: " . $e->getMessage());
            return false;
        }

        // Se for pacote, processa avanço de ciclo e notificação de parcela via TenantPackageService
        $packageNotified = false;
        if ($hasPackageInstallment) {
            try {
                app(\App\Modules\Tenancy\Services\TenantPackageService::class)->confirmInvoicePayment(
                    $model->id,
                    $gateway,
                    $transactionId,
                    $notifyCustomer
                );
                $packageNotified = true;
            } catch (\Throwable $e) {
                Log::warning("Erro ao confirmar ciclo de pacote para fatura #{$model->id}: " . $e->getMessage());
            }
        }

        if ($notifyCustomer && ! $packageNotified) {
            // Se não for pacote (ou se o serviço de pacote falhar), envia confirmação/agradecimento de pagamento (WhatsApp e/ou Email)
            $this->sendPaymentConfirmation($model, $paidAmount, $gateway);
        }

        // 4. Baixa no WHMCS via API se a fatura for vinculada ao WHMCS
        $this->settleWhmcsInvoice($model, $paidAmount, $gateway);

        // 5. Notificação interna do Tenant
        $this->notifyTenantPayment($model, $paidAmount);

        // 6. Cancela cobranças que ainda estejam pendentes/abertas no Mercado Pago para essa fatura
        try {
            app(\App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway::class)
                ->cancelPendingChargesForInvoice($model);
        } catch (\Throwable $e) {
            Log::warning("[InvoiceSettlementService] Falha ao cancelar cobranças pendentes no Mercado Pago para fatura #{$model->id}: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Retorna o contexto de parcelamento de uma fatura, identificando:
     * - Se é parcela de um carnê ou pacote
     * - Número da parcela e total de parcelas
     * - Outras parcelas vinculadas que continuam abertas
     * - Quais dessas parcelas abertas foram antecipadas
     */
    public function getInstallmentContext(Invoice $invoice): array
    {
        $meta = is_array($invoice->meta) ? $invoice->meta : [];
        $batchId = $meta['batch_id'] ?? null;
        $installmentNum = $meta['installment'] ?? null;
        $totalInstallments = $meta['total_installments'] ?? null;

        $relatedQuery = Invoice::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('id', '!=', $invoice->id);

        if ($invoice->tenant_id !== null) {
            $relatedQuery->where('tenant_id', $invoice->tenant_id);
        }

        $relatedInvoices = collect();

        // 1. Tenta buscar pelo batch_id do carnê avulso
        if ($batchId) {
            $relatedInvoices = (clone $relatedQuery)
                ->whereJsonContains('meta->batch_id', $batchId)
                ->orderBy('due_date')
                ->get();
        }

        // 2. Se não encontrou por batch_id, tenta via CustomerPackageInstallment
        if ($relatedInvoices->isEmpty() && Schema::hasTable('customer_package_installments')) {
            $cpi = CustomerPackageInstallment::where('invoice_id', $invoice->id)->first();
            if ($cpi && $cpi->subscription_id) {
                $subInvoiceIds = CustomerPackageInstallment::where('subscription_id', $cpi->subscription_id)
                    ->where('invoice_id', '!=', $invoice->id)
                    ->pluck('invoice_id');

                if ($subInvoiceIds->isNotEmpty()) {
                    $relatedInvoices = (clone $relatedQuery)
                        ->whereIn('id', $subInvoiceIds)
                        ->orderBy('due_date')
                        ->get();

                    if (! $installmentNum) {
                        $installmentNum = $cpi->installment_number ?? 1;
                        $totalInstallments = CustomerPackageInstallment::where('subscription_id', $cpi->subscription_id)->count();
                    }
                }
            }
        }

        // 3. Fallback: regex em descrição " (Parcela X/Y)"
        if ($relatedInvoices->isEmpty() && preg_match('/\(Parcela\s+(\d+)\/(\d+)\)/i', (string) $invoice->description, $matches)) {
            $installmentNum = (int) $matches[1];
            $totalInstallments = (int) $matches[2];
            $baseDesc = trim(preg_replace('/\(Parcela\s+\d+\/\d+\)/i', '', (string) $invoice->description));

            $relatedInvoices = (clone $relatedQuery)
                ->where('description', 'like', "%{$baseDesc}%")
                ->where('amount', $invoice->amount)
                ->orderBy('due_date')
                ->get();
        }

        $now = now()->endOfDay();

        $openInstallments = $relatedInvoices->filter(fn ($inv) => in_array($inv->status, ['pending', 'sent', 'overdue'], true));

        $mappedOpen = $openInstallments->map(function ($inv) use ($now) {
            $invMeta = is_array($inv->meta) ? $inv->meta : [];
            $isAntecipated = ! empty($invMeta['is_antecipated'])
                || ($inv->due_date && $inv->due_date->lessThanOrEqualTo($now));

            return [
                'id'                 => $inv->id,
                'number'             => $inv->number,
                'description'        => $inv->description,
                'amount'             => (float) $inv->amount,
                'due_date'           => $inv->due_date?->toDateString(),
                'formatted_due_date' => $inv->due_date?->format('d/m/Y'),
                'status'             => $inv->status,
                'installment'        => $invMeta['installment'] ?? null,
                'is_antecipated'     => (bool) $isAntecipated,
                'original_due_date'  => $invMeta['original_due_date'] ?? null,
            ];
        })->values();

        $antecipated = $mappedOpen->filter(fn ($item) => $item['is_antecipated'])->values();

        return [
            'is_installment'           => ($totalInstallments && $totalInstallments > 1) || $relatedInvoices->isNotEmpty(),
            'installment_number'       => $installmentNum,
            'total_installments'       => $totalInstallments ?: ($relatedInvoices->count() + 1),
            'this_invoice'             => [
                'id'                 => $invoice->id,
                'number'             => $invoice->number,
                'amount'             => (float) $invoice->amount,
                'due_date'           => $invoice->due_date?->toDateString(),
                'formatted_due_date' => $invoice->due_date?->format('d/m/Y'),
                'status'             => $invoice->status,
                'customer_name'      => $invoice->customer?->name,
            ],
            'open_installments'        => $mappedOpen,
            'open_count'               => $mappedOpen->count(),
            'antecipated_installments' => $antecipated,
            'antecipated_count'        => $antecipated->count(),
            'total_open_amount'        => (float) $mappedOpen->sum('amount'),
            'total_antecipated_amount' => (float) $antecipated->sum('amount'),
        ];
    }

    /**
     * Dá baixa manual em múltiplas faturas simultaneamente.
     */
    public function settleMultiple(
        array $invoiceIds,
        string $gateway = 'manual',
        ?string $transactionId = null,
        bool $notifyCustomer = true,
        ?\DateTimeInterface $paidAt = null,
        ?string $notes = null,
        ?int $tenantId = null
    ): array {
        $query = Invoice::whereIn('id', $invoiceIds);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $invoices = $query->get();

        $settledIds = [];
        $totalAmount = 0.0;

        foreach ($invoices as $inv) {
            if ($inv->status === 'paid' && $inv->paid_at) {
                continue;
            }
            $ok = $this->settlePaid(
                $inv,
                $gateway,
                $transactionId,
                (float) $inv->amount,
                $notifyCustomer,
                $paidAt,
                $notes
            );
            if ($ok) {
                $settledIds[] = $inv->id;
                $totalAmount += (float) $inv->amount;
            }
        }

        return [
            'settled_ids'  => $settledIds,
            'count'        => count($settledIds),
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Dá baixa na fatura correspondente no WHMCS via API (AddInvoicePayment).
     */
    protected function settleWhmcsInvoice(Invoice $invoice, float $amount, string $gateway): void
    {
        // 1. Tenta sincronização via WhmcsPaymentSyncer padrão do sistema
        try {
            $synced = app(\App\Modules\WhmcsIntegration\Services\WhmcsPaymentSyncer::class)->pushPaidInvoice(
                $invoice,
                $gateway,
                $invoice->gateway_charge_id
            );
            if ($synced) {
                Log::info("WhmcsPaymentSyncer deu baixa com sucesso para fatura #{$invoice->id}.");
                return;
            }
        } catch (\Throwable $e) {
            Log::warning("Erro ao tentar WhmcsPaymentSyncer: " . $e->getMessage());
        }

        // 2. Fallback: Se não baixou pelo syncer, busca conexão e chama markPaid diretamente
        $meta = $invoice->meta ?: [];
        $whmcsInvoiceId = (string) (
            $meta['whmcs_id']
            ?? $meta['whmcs_invoice_id']
            ?? $meta['whmcs_data']['id']
            ?? ''
        );

        if ($whmcsInvoiceId === '' || ! $invoice->tenant_id) {
            return;
        }

        try {
            $connection = WhmcsConnection::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('active', true)
                ->first();

            if (! $connection) {
                Log::warning("Fatura #{$invoice->id} possui WHMCS ID #{$whmcsInvoiceId}, mas nenhuma conexão WHMCS ativa foi encontrada para o tenant #{$invoice->tenant_id}.");
                return;
            }

            $client = new WhmcsClient($connection);
            $success = $client->markPaid($whmcsInvoiceId, $amount, $gateway);

            if ($success) {
                Log::info("Baixa automática realizada com sucesso no WHMCS para fatura #{$whmcsInvoiceId} (Fatura local #{$invoice->id}, valor: R$ {$amount}).");
                
                $meta['whmcs_settled_at'] = now()->toIso8601String();
                $meta['whmcs_settled_gateway'] = $gateway;
                $invoice->updateQuietly(['meta' => $meta]);
            } else {
                Log::error("Falha ao dar baixa no WHMCS para fatura #{$whmcsInvoiceId} via API.");
            }
        } catch (\Throwable $e) {
            Log::error("Exceção ao dar baixa no WHMCS para fatura #{$invoice->id}: " . $e->getMessage());
        }
    }

    /**
     * Envia notificação interna para o Tenant.
     */
    protected function notifyTenantPayment(Invoice $invoice, float $amount): void
    {
        try {
            if (! $invoice->tenant_id) {
                return;
            }

            $tenant = DB::table('tenants')->select('id', 'user_id')->where('id', $invoice->tenant_id)->first();
            if (! $tenant || ! Schema::hasTable('admin_notifications')) {
                return;
            }

            $customerName = $invoice->customer?->name ?? 'Cliente';
            $formattedVal = number_format($amount, 2, ',', '.');

            app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                'channels'   => ['in_app', 'email'],
                'group'      => 'settlement',
                'type'       => 'invoice_paid',
                'title'      => 'Pagamento Confirmado (Quitação)',
                'message'    => "Fatura #{$invoice->number} de {$customerName} (R$ {$formattedVal}) foi paga com sucesso.",
                'url'        => route('tenant.invoices'),
                'icon'       => 'bi-check-circle-fill',
                'color'      => 'success',
                'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
            ]);
        } catch (\Throwable $e) {
            Log::warning("Não foi possível inserir notificação de pagamento: " . $e->getMessage());
        }
    }

    /**
     * Envia confirmação/agradecimento de quitação para o cliente (WhatsApp e/ou E-mail).
     * Se ambos canais estiverem disponíveis, envia por ambos.
     * Se apenas um canal estiver disponível, envia pelo disponível.
     */
    public function sendPaymentConfirmation(Invoice $invoice, float $amount, string $gateway): void
    {
        try {
            $customer = $invoice->customer;
            if (! $customer && $invoice->customer_id) {
                $customer = DB::table('customers')->where('id', $invoice->customer_id)->first();
            }

            $tenantId = $invoice->tenant_id;
            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            $phone = $customer?->whatsapp ?: $customer?->phone ?: data_get($invoice, 'customer.whatsapp') ?: data_get($invoice, 'customer.phone');
            $email = $customer?->email ?: data_get($invoice, 'customer.email');

            $hasWhatsApp = ! empty($phone);
            $hasSmtpGlobal = ! empty(config('mail.mailers.smtp.host')) || ! empty(config('mail.from.address')) || ! empty(env('MAIL_HOST'));
            $hasEmail = ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && (
                ($tenant ? ($tenant->emailConfig?->enabled ?? false) : false) || $hasSmtpGlobal
            );

            if (! $hasWhatsApp && ! $hasEmail) {
                Log::info("[InvoiceSettlement] Notificação de pagamento cancelada: cliente da fatura #{$invoice->id} não possui nem WhatsApp nem E-mail configurado.");
                return;
            }

            // 1. Envio por WhatsApp
            if ($hasWhatsApp) {
                $this->executePaymentConfirmationWhatsApp($invoice, $customer, $tenant, $phone, $amount, $gateway);
            }

            // 2. Envio por E-mail
            if ($hasEmail) {
                $this->executePaymentConfirmationEmail($invoice, $customer, $tenant, $email, $amount, $gateway);
            }
        } catch (\Throwable $e) {
            Log::warning("Erro ao enviar confirmação de pagamento para fatura #{$invoice->id}: " . $e->getMessage());
        }
    }

    /**
     * Mantém compatibilidade com chamadas legadas de sendPaymentConfirmationWhatsApp.
     */
    protected function sendPaymentConfirmationWhatsApp(Invoice $invoice, float $amount, string $gateway): void
    {
        $this->sendPaymentConfirmation($invoice, $amount, $gateway);
    }

    /**
     * Executa o disparo de confirmação de pagamento via WhatsApp.
     */
    protected function executePaymentConfirmationWhatsApp(
        Invoice $invoice,
        mixed $customer,
        ?Tenant $tenant,
        string $phone,
        float $amount,
        string $gateway
    ): void {
        try {
            $tenantId = $invoice->tenant_id;
            $companyName = $tenant?->company_name ?: config('app.name');
            $customerName = $customer?->name ?? 'Cliente';
            $primeiroNome = trim(explode(' ', $customerName)[0] ?? '');
            $valFormatted = number_format($amount, 2, ',', '.');
            $numFatura = $invoice->number ?: (string) $invoice->id;

            // Resolve template cadastrado da categoria 'agradecimento' / 'pagamento-confirmado'
            $template = null;
            if (class_exists(\App\Modules\Tenancy\Services\TenantTemplateResolver::class)) {
                $resolver = app(\App\Modules\Tenancy\Services\TenantTemplateResolver::class);
                $template = $resolver->whatsapp($tenantId ? (int) $tenantId : null, null, 'agradecimento', 'pagamento-confirmado')
                    ?? $resolver->whatsapp($tenantId ? (int) $tenantId : null, null, 'pacote_pagamento', 'pacote-pagamento-confirmado');
            }

            if ($template && $template->active) {
                $renderer = app(\App\Modules\Templates\Services\TemplateRenderer::class);
                $message = $renderer->render($template, [
                    'nome'             => $customerName,
                    'customer_name'    => $customerName,
                    'primeiro_nome'    => $primeiroNome,
                    'valor'            => $amount,
                    'numero_fatura'    => $numFatura,
                    'invoice_number'   => $numFatura,
                    'empresa'          => $companyName,
                    'forma_pagamento'  => ucfirst($gateway ?: 'PIX'),
                    'data_pagamento'   => now('America/Sao_Paulo')->format('d/m/Y H:i'),
                    'whatsapp_suporte' => $tenant?->whatsapp_support ?? '',
                ]);
            } else {
                $message = "*Pagamento Confirmado!* 🎉\n\n"
                    . "Olá, *{$customerName}*!\n\n"
                    . "Confirmamos o recebimento do pagamento da sua fatura *#{$numFatura}* no valor de *R$ {$valFormatted}*.\n\n"
                    . "*Forma de pagamento:* " . ucfirst($gateway ?: 'Pix') . "\n"
                    . "*Data:* " . now('America/Sao_Paulo')->format('d/m/Y H:i') . "\n\n"
                    . "Agradecemos pela preferência e confiança!\n"
                    . "*{$companyName}*";
            }

            $cleanTo = preg_replace('/\D+/', '', $phone);
            if (! str_starts_with($cleanTo, '55') && strlen($cleanTo) <= 11) {
                $cleanTo = '55' . $cleanTo;
            }

            $context = [
                'origin' => 'payment_confirmation_manual_settle',
                'tenant_id' => $tenantId,
                'customer_id' => $customer?->id ?? null,
                'invoice_id' => $invoice->id,
                'skip_safety' => true,
            ];

            // Resolve instância ativa conectada do tenant
            $tenantInstance = null;
            if ($tenantId) {
                $tenantInstance = \App\Modules\Tenancy\Models\TenantInstance::where('tenant_id', $tenantId)
                    ->where('status', 'connected')
                    ->orderByDesc('is_default')
                    ->first();
            }

            $msgId = null;
            if ($tenantInstance && class_exists(\App\Modules\Tenancy\Services\InstanceManager::class)) {
                try {
                    $msgId = app(\App\Modules\Tenancy\Services\InstanceManager::class)->sendText($tenantInstance, $cleanTo, $message, $context);
                } catch (\Throwable $e) {
                    Log::warning("[InvoiceSettlement] Falha ao enviar via instância do tenant #{$tenantInstance->id}: " . $e->getMessage());
                }
            }

            // Fallback para WhatsAppMetaClient global se a instância do tenant falhar ou não estiver conectada
            if (! $msgId && class_exists(\App\Modules\WhatsApp\Services\WhatsAppMetaClient::class)) {
                try {
                    $client = app(\App\Modules\WhatsApp\Services\WhatsAppMetaClient::class);
                    $msgId = $client->sendText($cleanTo, $message, $context);
                } catch (\Throwable $e) {
                    Log::warning("[InvoiceSettlement] Falha ao enviar via WhatsAppMetaClient global: " . $e->getMessage());
                }
            }

            if (Schema::hasTable('whatsapp_messages')) {
                \App\Models\WhatsAppMessage::create([
                    'to' => $cleanTo,
                    'message' => $message,
                    'status' => $msgId ? 'sent' : 'queued',
                    'meta_message_id' => $msgId,
                    'tenant_instance_id' => $tenantInstance?->id,
                    'meta' => array_merge($context, [
                        'meta_message_id' => $msgId,
                        'customer_name' => $customerName,
                        'invoice_number' => $numFatura,
                        'gateway' => $gateway,
                    ]),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Erro ao enviar confirmação de pagamento via WhatsApp para fatura #{$invoice->id}: " . $e->getMessage());
        }
    }

    /**
     * Executa o disparo de confirmação de pagamento via E-mail.
     */
    protected function executePaymentConfirmationEmail(
        Invoice $invoice,
        mixed $customer,
        Tenant $tenant,
        string $email,
        float $amount,
        string $gateway
    ): void {
        try {
            $tenantId = $tenant->id;
            $customerName = $customer?->name ?? 'Cliente';
            $primeiroNome = trim(explode(' ', $customerName)[0] ?? '');
            $companyName = $tenant->company_name ?: config('app.name');
            $valFormatted = 'R$ ' . number_format($amount, 2, ',', '.');
            $numFatura = $invoice->number ?: (string) $invoice->id;

            // Busca template de email para a categoria de quitação/agradecimento
            $template = null;
            if (class_exists(\App\Modules\Tenancy\Services\TenantTemplateResolver::class)) {
                $resolver = app(\App\Modules\Tenancy\Services\TenantTemplateResolver::class);
                $template = $resolver->email($tenantId, null, 'agradecimento')
                    ?? $resolver->email($tenantId, null, 'pacote_pagamento');
            }

            if (! $template) {
                $template = \App\Modules\EmailBilling\Models\EmailTemplate::where('tenant_id', $tenantId)
                    ->whereIn('slug', ['pagamento-confirmado', 'pacote-pagamento-confirmado'])
                    ->where('active', true)
                    ->first();
            }

            if (! $template && class_exists(\App\Modules\EmailBilling\Services\EmailTemplateDefaults::class)) {
                $defaults = (new \App\Modules\EmailBilling\Services\EmailTemplateDefaults())->defaults();
                if (isset($defaults['pagamento-confirmado'])) {
                    $template = new \App\Modules\EmailBilling\Models\EmailTemplate($defaults['pagamento-confirmado'] + [
                        'tenant_id' => $tenantId,
                        'slug' => 'pagamento-confirmado',
                    ]);
                }
            }

            $context = [
                'tenant'            => $tenant,
                'tenant_id'         => $tenant->id,
                'nome'              => $customerName,
                'customer_name'     => $customerName,
                'primeiro_nome'     => $primeiroNome,
                'email'             => $email,
                'numero_fatura'     => $numFatura,
                'invoice_number'    => $numFatura,
                'valor'             => $valFormatted,
                'amount'            => $valFormatted,
                'data_pagamento'    => now('America/Sao_Paulo')->format('d/m/Y H:i'),
                'metodo_pagamento'  => ucfirst($gateway ?: 'PIX'),
                'forma_pagamento'   => ucfirst($gateway ?: 'PIX'),
                'empresa'           => $companyName,
                'email_suporte'     => $tenant->email_support ?? $tenant->emailConfig?->from_address ?? '',
                'whatsapp_suporte'  => $tenant->whatsapp_support ?? '',
                'link_reativacao'   => url('/'),
                'link_pagamento'    => url('/'),
            ];

            if ($template && class_exists(\App\Modules\EmailBilling\Services\EmailTemplateRenderer::class)) {
                $rendered = app(\App\Modules\EmailBilling\Services\EmailTemplateRenderer::class)->render($template, $context);
                $subject = $rendered['subject'];
                $bodyHtml = $rendered['body_html'];
                $bodyText = $rendered['body_text'];
            } else {
                $subject = "Pagamento confirmado: fatura {$numFatura}";
                $bodyHtml = "<p>Olá <strong>{$customerName}</strong>,</p><p>Confirmamos o recebimento do pagamento da sua fatura <strong>#{$numFatura}</strong> no valor de <strong>{$valFormatted}</strong> via " . ucfirst($gateway ?: 'PIX') . " em " . now('America/Sao_Paulo')->format('d/m/Y H:i') . ".</p><p>Agradecemos pela preferência!</p><p><strong>{$companyName}</strong></p>";
                $bodyText = "Olá {$customerName},\n\nConfirmamos o pagamento da sua fatura #{$numFatura} no valor de {$valFormatted}.\nAgradecemos pela preferência!\n{$companyName}";
            }

            $emailSent = false;
            if ($tenant?->emailConfig?->enabled && class_exists(\App\Modules\EmailBilling\Services\SmtpMailer::class)) {
                $res = app(\App\Modules\EmailBilling\Services\SmtpMailer::class)->send(
                    $tenant,
                    $email,
                    $subject,
                    $bodyHtml,
                    $bodyText
                );
                if ($res['ok'] ?? false) {
                    $emailSent = true;
                    Log::info("[InvoiceSettlement] E-mail de confirmação de pagamento enviado com sucesso via SMTP do tenant para {$email} (Fatura #{$numFatura}).");
                } else {
                    Log::warning("[InvoiceSettlement] Falha ao enviar via SMTP do tenant para {$email}: " . ($res['error'] ?? 'Erro desconhecido') . ". Tentando fallback para SMTP do sistema...");
                }
            }

            // Fallback para o Mailer nativo do sistema
            if (! $emailSent) {
                try {
                    \Illuminate\Support\Facades\Mail::html($bodyHtml, function ($message) use ($email, $subject, $companyName) {
                        $message->to($email)->subject($subject);
                        $fromAddr = config('mail.from.address');
                        if (! empty($fromAddr)) {
                            $message->from($fromAddr, $companyName);
                        }
                    });
                    Log::info("[InvoiceSettlement] E-mail de confirmação de pagamento enviado com sucesso via Mailer global para {$email} (Fatura #{$numFatura}).");
                } catch (\Throwable $mailErr) {
                    Log::warning("[InvoiceSettlement] Falha ao enviar e-mail de quitação via Mailer global: " . $mailErr->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[InvoiceSettlement] Erro ao enviar confirmação de pagamento via e-mail para fatura #{$invoice->id}: " . $e->getMessage());
        }
    }
}
