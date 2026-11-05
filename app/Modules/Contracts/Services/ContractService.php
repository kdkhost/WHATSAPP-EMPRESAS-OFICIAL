<?php

namespace App\Modules\Contracts\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractTemplate;
use App\Modules\EmailBilling\Services\SmtpMailer;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\WhatsApp\Services\EvolutionClient;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function __construct(
        protected ContractTemplateCatalog $catalog,
        protected ?EvolutionClient $evolution = null,
        protected ?SmtpMailer $mailer = null,
        protected ?InstanceManager $instances = null,
        protected ?WhatsAppSafetyService $safety = null,
    ) {}

    public function availableVariables(): array
    {
        return $this->catalog->availableVariables();
    }

    public function defaultLayout(): array
    {
        return $this->catalog->defaultLayout();
    }

    public function normalizeLayout(array|string|null $layout): array
    {
        $base = $this->defaultLayout();

        if (is_string($layout) && $layout !== '') {
            $layout = json_decode($layout, true);
        }

        if (! is_array($layout)) {
            return $base;
        }

        foreach (['page', 'content', 'signature', 'signer', 'metadata'] as $section) {
            $layout[$section] = array_merge($base[$section], is_array($layout[$section] ?? null) ? $layout[$section] : []);
        }

        return $layout;
    }

    public function inferBackgroundType(?string $backgroundPath): string
    {
        if (! $backgroundPath) {
            return 'none';
        }

        $path = strtolower(parse_url($backgroundPath, PHP_URL_PATH) ?: $backgroundPath);

        return str_ends_with($path, '.pdf') ? 'pdf' : 'image';
    }

    public function prepareTemplatePayload(array $data): array
    {
        $backgroundPath = trim((string) ($data['background_path'] ?? '')) ?: null;
        $backgroundType = $data['background_type'] ?? $this->inferBackgroundType($backgroundPath);

        return [
            'preset_key' => $data['preset_key'] ?? null,
            'name' => trim((string) ($data['name'] ?? '')),
            'slug' => $data['slug'] ?? null,
            'category' => trim((string) ($data['category'] ?? '')) ?: 'Serviços',
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'content_html' => (string) ($data['content_html'] ?? ''),
            'variables' => array_keys($this->availableVariables()),
            'background_type' => in_array($backgroundType, ['image', 'pdf'], true) ? $backgroundType : 'none',
            'background_path' => $backgroundPath,
            'layout_json' => $this->normalizeLayout($data['layout_json'] ?? null),
            'require_witness' => (bool) ($data['require_witness'] ?? false),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    public function buildContractPayload(
        array $data,
        Customer $customer,
        ?Invoice $invoice = null,
        ?Tenant $tenant = null,
        ?ContractTemplate $template = null
    ): array {
        $contentHtml = (string) ($data['content_html'] ?? $template?->content_html ?? '');
        $title = trim((string) ($data['title'] ?? $template?->name ?? 'Contrato digital'));
        $bgPath = !empty($data['background_path']) ? $data['background_path'] : $template?->background_path;
        $bgType = !empty($data['background_type']) ? $data['background_type'] : ($bgPath ? (str_ends_with(strtolower($bgPath), '.pdf') ? 'pdf' : 'image') : ($template?->background_type ?: 'none'));

        $payload = [
            'tenant_id' => $tenant?->id,
            'template_id' => $template?->id,
            'customer_id' => $customer->id,
            'title' => $title,
            'content_html' => $this->renderTemplateVariables($contentHtml, $this->buildContext($customer, $invoice, $tenant, null, $title)),
            'background_type' => $bgType,
            'background_path' => $bgPath,
            'layout_json' => $this->normalizeLayout($data['layout_json'] ?? $template?->layout_json),
            'expires_at' => $data['expires_at'] ?? null,
        ];

        if ($invoice) {
            $payload['invoice_id'] = $invoice->id;
        }

        return $payload;
    }

    public function buildContext(
        Customer $customer,
        ?Invoice $invoice = null,
        ?Tenant $tenant = null,
        ?Contract $contract = null,
        ?string $title = null
    ): array {
        $name = trim((string) $customer->name);
        $firstName = trim(explode(' ', $name)[0] ?? '');
        $date = now('America/Sao_Paulo');

        return [
            '{nome}' => $name,
            '{primeiro_nome}' => $firstName,
            '{email}' => (string) ($customer->email ?? ''),
            '{documento}' => $this->formatDocument($customer->document),
            '{telefone}' => Format::phone($customer->phone),
            '{whatsapp}' => Format::phone($customer->whatsapp),
            '{cep}' => Format::cep($customer->cep),
            '{logradouro}' => (string) ($customer->logradouro ?? ''),
            '{numero}' => (string) ($customer->numero ?? ''),
            '{complemento}' => (string) ($customer->complemento ?? ''),
            '{bairro}' => (string) ($customer->bairro ?? ''),
            '{cidade}' => (string) ($customer->cidade ?? ''),
            '{uf}' => (string) ($customer->uf ?? ''),
            '{empresa}' => (string) ($tenant?->company_name ?: config('app.name')),
            '{titulo_contrato}' => (string) ($title ?: $contract?->title ?: ''),
            '{numero_contrato}' => (string) ($contract?->number ?: ''),
            '{data_atual}' => $date->format('d/m/Y'),
            '{data_extenso}' => $this->dateExtenso($date),
            '{valor}' => Format::money($invoice?->amount ?? 0),
            '{vencimento}' => Format::date($invoice?->due_date),
            '{numero_fatura}' => (string) ($invoice?->number ?? ''),
            '{link_assinatura}' => (string) ($contract?->public_url ?? ''),
        ];
    }

    public function renderTemplateVariables(string $html, array $context): string
    {
        return strtr($html, $context);
    }

    public function sendForSigning(Contract $contract, string $channel = 'both'): array
    {
        $report = ['email' => false, 'whatsapp' => false];
        $contract->loadMissing('customer', 'tenant');
        $publicUrl = $contract->public_url;

        if (in_array($channel, ['email', 'both'], true) && $contract->customer?->email) {
            $report['email'] = $this->sendByEmail($contract, $publicUrl);
        }

        if (in_array($channel, ['whatsapp', 'both'], true) && $contract->customer?->whatsapp) {
            $report['whatsapp'] = $this->sendByWhatsApp($contract, $publicUrl);
        }

        $contract->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $contract->recordEvent('sent', null, null, ['channel' => $channel]);

        return $report;
    }

    public function sign(Contract $contract, array $data): Contract
    {
        $contract->update([
            'status' => 'signed',
            'signed_at' => now(),
            'signer_name' => $data['signer_name'] ?? null,
            'signer_email' => $data['signer_email'] ?? null,
            'signer_document' => $data['signer_document'] ?? null,
            'signer_ip' => $data['ip'] ?? null,
            'signer_user_agent' => $data['user_agent'] ?? null,
            'signature_image' => $data['signature_image'] ?? null,
            'return_channel' => $data['return_channel'] ?? 'email',
        ]);

        $contract->recordEvent('signed', $data['ip'] ?? null, $data['user_agent'] ?? null);
        $this->generateAndSendCopy($contract->fresh(['customer', 'tenant', 'invoice']));

        return $contract->fresh();
    }

    public function generateAndSendCopy(Contract $contract): array
    {
        $contract->loadMissing('customer', 'tenant', 'invoice');

        $signedHtml = $this->renderSignedHtml($contract);
        $htmlPath = "contracts/signed/{$contract->access_token}.html";
        Storage::disk('local')->put($htmlPath, $signedHtml);

        $pdfPath = null;
        if ($this->canGenerateRealPdf()) {
            try {
                $pdfBinary = $this->generatePdfBinary($this->renderPdfHtml($contract));
                if ($pdfBinary !== false) {
                    $pdfPath = "contracts/signed/{$contract->access_token}.pdf";
                    Storage::disk('local')->put($pdfPath, $pdfBinary);
                }
            } catch (\Throwable $e) {
                Log::warning('[Contract] Falha ao gerar PDF real: ' . $e->getMessage());
            }
        }

        $contract->update([
            'signed_html_path' => $htmlPath,
            'signed_pdf_path' => $pdfPath,
            'signed_file_mime' => $pdfPath ? 'application/pdf' : 'text/html',
        ]);

        $report = ['email' => false, 'whatsapp' => false];
        $publicUrl = route('public.contracts.download', $contract->access_token);
        $channel = $contract->return_channel ?? 'email';

        if (in_array($channel, ['email', 'both'], true) && $contract->customer?->email) {
            $body = $this->buildSignedEmailBody($contract, $publicUrl);
            try {
                if ($contract->tenant && $this->mailer) {
                    $attachments = $pdfPath ? [[
                        'path' => Storage::disk('local')->path($pdfPath),
                        'name' => "contrato-{$contract->number}.pdf",
                        'mime' => 'application/pdf',
                    ]] : null;

                    $result = $this->mailer->enqueue(
                        $contract->tenant,
                        $contract->customer->email,
                        "Contrato {$contract->number} assinado com sucesso",
                        $body,
                        null,
                        null,
                        $attachments
                    );
                    $report['email'] = $result['ok'] ?? false;
                    $contract->recordEvent('copy_sent', null, null, ['channel' => 'email', 'attachment' => $pdfPath ? 'pdf' : 'link']);
                }
            } catch (\Throwable $e) {
                Log::error('[Contract] Falha ao enviar copia por email: ' . $e->getMessage());
            }
        }

        if (in_array($channel, ['whatsapp', 'both'], true) && $contract->customer?->whatsapp) {
            try {
                $tenantInstance = $this->resolveTenantInstance($contract->tenant_id ? (int) $contract->tenant_id : null);
                $message = $this->buildSignedWhatsappMessage($contract, $publicUrl);
                if (! $message) {
                    Log::warning("[Contract] Envio de cópia assinada cancelado: nenhum template válido ativo para 'contrato-assinado'.");
                    return $report;
                }
                $recipientPhone = Format::whatsappNumber((string) $contract->customer->whatsapp);
                $context = [
                    'tenant_id' => $contract->tenant_id,
                    'customer_id' => $contract->customer_id,
                    'campaign' => 'contract_copy',
                    'origin' => 'contracts_copy',
                ];

                $id = null;
                $instanceManager = $this->instances ??= app(InstanceManager::class);

                // 1. Prioridade: Enviar pelo TenantInstance via InstanceManager
                if ($tenantInstance) {
                    // Tenta enviar o documento PDF em anexo caso exista
                    if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                        try {
                            $fullPdfPath = Storage::disk('local')->path($pdfPath);
                            $id = $instanceManager->sendDocument(
                                $tenantInstance,
                                $recipientPhone,
                                $fullPdfPath,
                                "contrato-{$contract->number}.pdf",
                                "Olá! Segue em anexo a cópia assinada do contrato {$contract->number} ({$contract->title}).",
                                $context
                            );
                        } catch (\Throwable $docErr) {
                            Log::warning('[Contract] Falha no envio do PDF via tenantInstance, enviando texto com link: ' . $docErr->getMessage());
                        }
                    }

                    // Se não tiver PDF ou o envio do arquivo falhou, envia a mensagem de texto com o link de download direto
                    if (! $id) {
                        $id = $instanceManager->sendText(
                            $tenantInstance,
                            $recipientPhone,
                            $message,
                            $context
                        );
                    }
                }

                // 2. Fallback: Caso não haja TenantInstance conectado, tenta Evolution global se configurado
                if (! $id && $this->evolution && $this->evolution->isConfigured()) {
                    $sendContext = $pdfPath ? array_merge($context, ['media_type' => 'document']) : $context;
                    $inspection = ($this->safety ??= app(WhatsAppSafetyService::class))->inspect(null, $recipientPhone, $message, $sendContext);

                    if ($inspection['allowed']) {
                        if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                            try {
                                $id = $this->evolution->sendDocument(
                                    $recipientPhone,
                                    Storage::disk('local')->path($pdfPath),
                                    "contrato-{$contract->number}.pdf",
                                    "Olá! Segue a cópia assinada do contrato {$contract->number}.",
                                    array_merge($sendContext, $inspection)
                                );
                            } catch (\Throwable $docErr2) {
                                Log::warning('[Contract] Falha no envio do PDF via evolution global, enviando texto: ' . $docErr2->getMessage());
                            }
                        }

                        if (! $id) {
                            $id = $this->evolution->sendText($recipientPhone, $message, array_merge($sendContext, $inspection));
                        }
                    } else {
                        $this->safety->recordOutcome($inspection, [
                            'tenant_id' => $contract->tenant_id,
                            'to' => $recipientPhone,
                            'meta' => ['origin' => 'contracts_copy_fallback'],
                        ]);
                    }
                }

                $report['whatsapp'] = (bool) $id;
                if ($id) {
                    $contract->recordEvent('copy_sent', null, null, [
                        'channel' => 'whatsapp',
                        'attachment' => $pdfPath ? 'pdf' : 'link',
                        'phone' => $recipientPhone,
                        'message_id' => $id,
                    ]);
                    Log::info("[Contract] Cópia assinada despachada com sucesso no WhatsApp para o cliente {$recipientPhone} (ID: {$id})");
                } else {
                    Log::warning("[Contract] Não foi possível despachar mensagem no WhatsApp para {$recipientPhone}. Nenhuma instância ativa ou envio rejeitado.");
                }
            } catch (\Throwable $e) {
                Log::error('[Contract] Falha ao enviar copia por WhatsApp para o cliente: ' . $e->getMessage());
            }
        }

        // 3. Envio OBRIGATÓRIO da cópia assinada para o fornecedor do contrato (Tenant / Empresa emissora) no WhatsApp
        try {
            $contract->loadMissing('tenant.user');
            $providerPhone = null;
            $providerName = 'Fornecedor';

            if ($contract->tenant) {
                $providerName = $contract->tenant->company_name ?: ($contract->tenant->user?->name ?: 'Empresa');
                $providerPhone = $contract->tenant->user?->phone
                    ?: data_get($contract->tenant->settings, 'phone')
                    ?: data_get($contract->tenant->settings, 'whatsapp')
                    ?: data_get($contract->tenant->settings, 'company_phone')
                    ?: data_get($contract->tenant->settings, 'support_phone');
            }

            // Fallback caso seja contrato criado sem tenant (admin global)
            if (! $providerPhone) {
                $settingsManager = app(\App\Modules\Settings\Services\SettingsManager::class);
                $providerPhone = $settingsManager->get('app.whatsapp')
                    ?: $settingsManager->get('app.phone')
                    ?: \App\Models\User::where('role', 'admin')->whereNotNull('phone')->value('phone');
                $providerName = $settingsManager->get('app.name', config('app.name'));
            }

            if ($providerPhone) {
                $cleanProviderPhone = Format::whatsappNumber((string) $providerPhone);
                if ($cleanProviderPhone) {
                    $signerDisplayName = $contract->signer_name ?: $contract->customer?->name ?: 'Cliente';
                    $signerDisplayDoc  = $contract->signer_document ?: $contract->customer?->document ?: 'Não informado';
                    $signerDisplayWpp  = $contract->customer?->whatsapp ?: $contract->customer?->phone ?: 'Não informado';
                    $signedDateTime    = now('America/Sao_Paulo')->format('d/m/Y H:i:s');

                    $providerMsg = "🔔 *Contrato Assinado com Sucesso!*\n\n"
                        . "Olá, *{$providerName}*!\n"
                        . "O contrato *{$contract->number}* ({$contract->title}) acabou de ser assinado pelo cliente.\n\n"
                        . "👤 *Signatário:* {$signerDisplayName}\n"
                        . "📄 *Documento:* {$signerDisplayDoc}\n"
                        . "📱 *WhatsApp:* {$signerDisplayWpp}\n"
                        . "📅 *Data da Assinatura:* {$signedDateTime}\n"
                        . "🌐 *IP de Registro:* " . ($contract->signer_ip ?: '—') . "\n\n"
                        . "Segue em anexo a via assinada com carimbo e validade jurídica.\n"
                        . "🔗 Link do documento: {$publicUrl}";

                    $tenantInstance = $this->resolveTenantInstance($contract->tenant_id ? (int) $contract->tenant_id : null);
                    $instanceManager = $this->instances ??= app(InstanceManager::class);
                    $provContext = [
                        'tenant_id' => $contract->tenant_id,
                        'customer_id' => $contract->customer_id,
                        'campaign' => 'contract_provider_copy',
                        'origin' => 'contracts_provider_copy',
                    ];

                    $provMsgId = null;

                    // A) Envio via TenantInstance conectada
                    if ($tenantInstance) {
                        if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                            try {
                                $fullPdfPath = Storage::disk('local')->path($pdfPath);
                                $provMsgId = $instanceManager->sendDocument(
                                    $tenantInstance,
                                    $cleanProviderPhone,
                                    $fullPdfPath,
                                    "contrato-{$contract->number}-assinado.pdf",
                                    "Olá {$providerName}! Segue em anexo a via assinada do contrato {$contract->number} ({$signerDisplayName}).",
                                    $provContext
                                );
                            } catch (\Throwable $pErr) {
                                Log::warning('[Contract] Falha no envio de PDF para fornecedor via tenantInstance: ' . $pErr->getMessage());
                            }
                        }

                        if (! $provMsgId) {
                            $provMsgId = $instanceManager->sendText(
                                $tenantInstance,
                                $cleanProviderPhone,
                                $providerMsg,
                                $provContext
                            );
                        }
                    }

                    // B) Fallback via Evolution global
                    if (! $provMsgId && $this->evolution && $this->evolution->isConfigured()) {
                        if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                            try {
                                $provMsgId = $this->evolution->sendDocument(
                                    $cleanProviderPhone,
                                    Storage::disk('local')->path($pdfPath),
                                    "contrato-{$contract->number}-assinado.pdf",
                                    "Olá {$providerName}! Segue em anexo a via assinada do contrato {$contract->number}.",
                                    $provContext
                                );
                            } catch (\Throwable $pErr2) {
                                Log::warning('[Contract] Falha no envio de PDF para fornecedor via evolution global: ' . $pErr2->getMessage());
                            }
                        }

                        if (! $provMsgId) {
                            $provMsgId = $this->evolution->sendText($cleanProviderPhone, $providerMsg, $provContext);
                        }
                    }

                    if ($provMsgId) {
                        $contract->recordEvent('provider_notified', null, null, [
                            'channel' => 'whatsapp',
                            'phone' => $cleanProviderPhone,
                            'message_id' => $provMsgId,
                        ]);
                        Log::info("[Contract] Cópia assinada enviada com sucesso no WhatsApp para o fornecedor {$cleanProviderPhone} (ID: {$provMsgId})");
                    } else {
                        Log::warning("[Contract] Não foi possível despachar cópia para o fornecedor no WhatsApp ({$cleanProviderPhone}).");
                    }
                }
            }
        } catch (\Throwable $provEx) {
            Log::error('[Contract] Erro ao enviar cópia assinada para fornecedor no WhatsApp: ' . $provEx->getMessage());
        }

        return $report;
    }

    public function renderSignedHtml(Contract $contract): string
    {
        return view('contracts::public.signed', compact('contract'))->render();
    }

    public function renderPdfHtml(Contract $contract): string
    {
        $backgroundUrl = $contract->background_type === 'image'
            ? $this->assetUrl($contract->background_path)
            : null;

        return view('contracts::public.pdf', compact('contract', 'backgroundUrl'))->render();
    }

    public function renderDocumentHtml(Contract $contract, bool $signed = false): string
    {
        $layout = $this->normalizeLayout($contract->layout_json);
        $backgroundUrl = $this->assetUrl($contract->background_path);

        return view('contracts::public.partials.document-sheet', [
            'contract' => $contract,
            'layout' => $layout,
            'signed' => $signed,
            'backgroundUrl' => $backgroundUrl,
            'backgroundType' => $contract->background_type ?: 'none',
        ])->render();
    }

    protected function sendByEmail(Contract $contract, string $publicUrl): bool
    {
        if (! $this->mailer || ! $contract->tenant) {
            return false;
        }

        $body = '<p style="margin:0 0 16px;">Olá <strong>' . e($contract->customer->name) . '</strong>,</p>'
            . '<p style="margin:0 0 16px;">Você recebeu o contrato <strong>' . e($contract->title) . '</strong> para leitura e assinatura eletrônica.</p>'
            . '<p style="margin:0 0 16px;">Clique no botão abaixo para revisar o documento com calma e assinar nos campos indicados.</p>'
            . '<p style="margin:28px 0;">'
            . '<a href="' . e($publicUrl) . '" style="display:inline-block;background:#166534;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:10px;font-weight:700;">Revisar e assinar contrato</a>'
            . '</p>'
            . '<p style="margin:0;color:#667085;font-size:13px;">Se preferir, copie este link em seu navegador: ' . e($publicUrl) . '</p>';

        $result = $this->mailer->enqueue(
            $contract->tenant,
            $contract->customer->email,
            "Contrato {$contract->number} para assinatura",
            $body
        );

        return $result['ok'] ?? false;
    }

    protected function sendByWhatsApp(Contract $contract, string $publicUrl): bool
    {
        try {
            $tenantId = $contract->tenant_id ? (int) $contract->tenant_id : null;
            $resolver = app(\App\Modules\Tenancy\Services\TenantTemplateResolver::class);
            $template = $resolver->whatsapp($tenantId, null, 'contrato', 'contrato-assinatura');

            if (! $template || ! $template->active) {
                Log::warning("[Contract] Envio de contrato #{$contract->number} via WhatsApp cancelado: nenhum template ativo configurado para 'contrato-assinatura'.");
                return false;
            }

            $message = app(\App\Modules\Templates\Services\TemplateRenderer::class)->render($template, [
                'nome'            => $contract->customer?->name,
                'customer_name'   => $contract->customer?->name,
                'primeiro_nome'   => trim(explode(' ', (string) ($contract->customer?->name ?? ''))[0] ?? ''),
                'contrato_numero' => $contract->number,
                'contract_number' => $contract->number,
                'contrato_titulo' => $contract->title,
                'contract_title'  => $contract->title,
                'link_assinatura' => $publicUrl,
                'link_contrato'   => $publicUrl,
                'empresa'         => $contract->tenant?->company_name ?: config('app.name'),
            ]);

            $tenantInstance = $this->resolveTenantInstance($contract->tenant_id ? (int) $contract->tenant_id : null);
            $recipientPhone = Format::whatsappNumber((string) $contract->customer->whatsapp);
            $context = [
                'tenant_id' => $contract->tenant_id,
                'customer_id' => $contract->customer_id,
                'campaign' => 'contract_signature',
                'origin' => 'contracts_signature',
            ];

            if ($tenantInstance) {
                return (bool) (($this->instances ??= app(InstanceManager::class))->sendText(
                    $tenantInstance,
                    $recipientPhone,
                    $message,
                    $context
                ));
            }

            if (! $this->evolution || ! $this->evolution->isConfigured()) {
                return false;
            }

            $inspection = ($this->safety ??= app(WhatsAppSafetyService::class))->inspect(null, $recipientPhone, $message, $context);
            if (! $inspection['allowed']) {
                $this->safety->recordOutcome($inspection, [
                    'tenant_id' => $contract->tenant_id,
                    'to' => $recipientPhone,
                    'meta' => ['origin' => 'contracts_signature_fallback'],
                ]);

                return false;
            }

            return (bool) $this->evolution->sendText(
                $recipientPhone,
                $message,
                array_merge($context, $inspection)
            );
        } catch (\Throwable) {
            return false;
        }
    }

    protected function resolveTenantInstance(?int $tenantId): ?TenantInstance
    {
        if ($tenantId) {
            $connected = TenantInstance::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'connected')
                ->orderByDesc('is_default')
                ->first();

            if ($connected) {
                return $connected;
            }

            $anyTenant = TenantInstance::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($anyTenant) {
                return $anyTenant;
            }
        }

        return TenantInstance::query()
            ->where('status', 'connected')
            ->orderByDesc('is_default')
            ->first()
            ?: TenantInstance::query()->orderByDesc('is_default')->first();
    }

    protected function buildSignedEmailBody(Contract $contract, string $publicUrl): string
    {
        $signedAt = $contract->signed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?: now('America/Sao_Paulo')->format('d/m/Y H:i');

        return '<p style="margin:0 0 16px;">Olá <strong>' . e($contract->signer_name ?: $contract->customer?->name) . '</strong>,</p>'
            . '<p style="margin:0 0 16px;">Confirmamos a assinatura do contrato <strong>' . e($contract->number) . '</strong> em <strong>' . e($signedAt) . '</strong>.</p>'
            . '<p style="margin:0 0 16px;">A cópia assinada foi disponibilizada para o seu registro e pode ser acessada pelo botão abaixo.</p>'
            . '<p style="margin:28px 0;">'
            . '<a href="' . e($publicUrl) . '" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:700;">Baixar contrato assinado</a>'
            . '</p>'
            . '<p style="margin:0;color:#667085;font-size:13px;">Guarde este email para futuras consultas.</p>';
    }

    protected function buildSignedWhatsappMessage(Contract $contract, string $publicUrl): ?string
    {
        $tenantId = $contract->tenant_id ? (int) $contract->tenant_id : null;
        $resolver = app(\App\Modules\Tenancy\Services\TenantTemplateResolver::class);
        $template = $resolver->whatsapp($tenantId, null, 'contrato_assinado', 'contrato-assinado');

        if (! $template || ! $template->active) {
            Log::warning("[Contract] Notificação de contrato assinado #{$contract->number} via WhatsApp cancelada: nenhum template ativo configurado para 'contrato-assinado'.");
            return null;
        }

        return app(\App\Modules\Templates\Services\TemplateRenderer::class)->render($template, [
            'nome'            => $contract->signer_name ?: $contract->customer?->name,
            'customer_name'   => $contract->customer?->name,
            'primeiro_nome'   => trim(explode(' ', (string) ($contract->signer_name ?: $contract->customer?->name))[0] ?? ''),
            'contrato_numero' => $contract->number,
            'contract_number' => $contract->number,
            'contrato_titulo' => $contract->title,
            'contract_title'  => $contract->title,
            'data_assinatura' => ($contract->signed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?: now('America/Sao_Paulo')->format('d/m/Y H:i')),
            'link_contrato'   => $publicUrl,
            'empresa'         => $contract->tenant?->company_name ?: config('app.name'),
        ]);
    }

    protected function generatePdfBinary(string $html): string|false
    {
        if (! $this->canGenerateRealPdf()) {
            return false;
        }

        $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    protected function canGenerateRealPdf(): bool
    {
        return class_exists(\Dompdf\Dompdf::class) && extension_loaded('gd');
    }

    protected function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return url($path);
        }

        return Storage::disk('public')->url($path);
    }

    protected function formatDocument(?string $document): string
    {
        $digits = preg_replace('/\D/', '', (string) $document);

        return match (strlen($digits)) {
            11 => Format::cpf($digits),
            14 => Format::cnpj($digits),
            default => (string) $document,
        };
    }

    protected function dateExtenso(Carbon $date): string
    {
        $meses = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'março',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro',
        ];

        return $date->day . ' de ' . ($meses[$date->month] ?? '') . ' de ' . $date->year;
    }
}
