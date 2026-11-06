<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — WhatsApp Cloud API Oficial da Meta (Graph API v21.0).
 */

namespace App\Modules\WhatsApp\Services;

use App\Models\WhatsAppMessage;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Support\Format;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsAppMetaClient
 * Cliente Oficial da Meta WhatsApp Cloud API (Graph API v21.0).
 * Substitui integralmente qualquer cliente legado/não-oficial.
 */
class WhatsAppMetaClient
{
    protected MetaCloudApiClient $metaClient;

    public function __construct(
        protected string $baseUrl = '',
        protected string $apiKey = '',
        protected string $instance = '',
        protected int $timeout = 30,
        ?MetaCloudApiClient $metaClient = null,
    ) {
        $this->metaClient = $metaClient ?? app(MetaCloudApiClient::class);
    }

    public function isConfigured(): bool
    {
        return $this->metaClient->isConfigured();
    }

    public function isInstanceConfigured(): bool
    {
        return $this->metaClient->isConfigured();
    }

    public function isAdminConfigured(): bool
    {
        return $this->metaClient->isConfigured();
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setQuarantineService($service): void
    {
        // Quarentena desnecessária na API oficial da Meta (sem risco de ban por automação não oficial)
    }

    /**
     * Valida segurança e opt-out antes do disparo
     */
    protected function checkSafety(string $to, string $message, array &$context, string $mediaType = 'text'): void
    {
        $safetyService = app(WhatsAppSafetyService::class);
        $tenantInstance = null;

        if (!empty($context['tenant_instance_id'])) {
            $tenantInstance = TenantInstance::find($context['tenant_instance_id']);
        } elseif (!empty($this->instance)) {
            $tenantInstance = TenantInstance::where('instance_name', $this->instance)->first();
            if ($tenantInstance && empty($context['tenant_instance_id'])) {
                $context['tenant_instance_id'] = $tenantInstance->id;
            }
        }

        $context['media_type'] = $mediaType;
        $inspection = $safetyService->inspect($tenantInstance, $to, $message, $context);

        if (empty($inspection['allowed'])) {
            $reason = $inspection['reason'] ?? 'blocked_by_safety';
            throw new \RuntimeException($reason);
        }

        $delayMs = (int) ($inspection['delay'] ?? 0);
        if ($delayMs > 0 && !app()->runningUnitTests()) {
            usleep($delayMs * 1000);
        }
    }

    /**
     * Envio de Mensagem de Texto Oficial com Regra de Janela de 24 Horas da Meta.
     */
    public function sendText(string $to, string $message, array $context = []): ?string
    {
        $this->checkSafety($to, $message, $context, 'text');

        $result = $this->metaClient->sendSessionTextMessage($to, $message, $context);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, $message, 'text', $result['message_id'], $context);
            return $result['message_id'];
        }

        Log::warning('[WhatsAppMetaClient] Falha no disparo oficial de texto:', $result);
        return null;
    }

    /**
     * Envio de Link com Preview Oficial.
     */
    public function sendLink(string $to, string $text, array $context = []): ?string
    {
        $context['linkPreview'] = true;
        return $this->sendText($to, $text, $context);
    }

    /**
     * Envio de Imagem Oficial.
     */
    public function sendImage(string $to, string $image, ?string $caption = null, array $context = []): ?string
    {
        $preview = $caption ?: '[imagem]';
        $this->checkSafety($to, $preview, $context, 'image');

        $result = $this->metaClient->sendMediaMessage($to, 'image', $image, $caption);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, $preview, 'image', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Documento Oficial (PDF de faturas, recibos e contratos).
     */
    public function sendDocument(string $to, string $file, string $filename = 'fatura.pdf', ?string $caption = null, array $context = []): ?string
    {
        $preview = $caption ?: "[documento: {$filename}]";
        $this->checkSafety($to, $preview, $context, 'document');

        $result = $this->metaClient->sendMediaMessage($to, 'document', $file, $caption, $filename);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, $preview, 'document', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Áudio Oficial.
     */
    public function sendAudio(string $to, string $audio, bool $ptt = true, array $context = []): ?string
    {
        $this->checkSafety($to, '[audio]', $context, 'audio');

        $result = $this->metaClient->sendMediaMessage($to, 'audio', $audio);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, '[audio]', 'audio', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Vídeo Oficial.
     */
    public function sendVideo(string $to, string $video, ?string $caption = null, array $context = []): ?string
    {
        $preview = $caption ?: '[video]';
        $this->checkSafety($to, $preview, $context, 'video');

        $result = $this->metaClient->sendMediaMessage($to, 'video', $video, $caption);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, $preview, 'video', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Contato.
     */
    public function sendContact(string $to, string $contactName, string $contactPhone, array $context = []): ?string
    {
        $text = "Contato: {$contactName} - {$contactPhone}";
        return $this->sendText($to, $text, $context);
    }

    /**
     * Envio de Localização.
     */
    public function sendLocation(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null, array $context = []): ?string
    {
        $mapsUrl = "https://maps.google.com/?q={$latitude},{$longitude}";
        $text = ($name ? "{$name}\n" : '') . ($address ? "{$address}\n" : '') . $mapsUrl;
        return $this->sendText($to, $text, $context);
    }

    /**
     * Envio de Botões (Formatado para mensagem oficial de texto com links/opções).
     */
    public function sendButtons(string $to, string $title, string $description, array $buttons, ?string $footer = null, array $context = []): ?string
    {
        $text = "*{$title}*\n\n{$description}";
        if (!empty($buttons)) {
            $text .= "\n";
            foreach ($buttons as $btn) {
                $label = is_array($btn) ? ($btn['displayText'] ?? $btn['label'] ?? json_encode($btn)) : (string) $btn;
                $text .= "\n• {$label}";
            }
        }
        if ($footer) {
            $text .= "\n\n_{$footer}_";
        }
        return $this->sendText($to, $text, $context);
    }

    /**
     * Envio de Lista interativa.
     */
    public function sendList(string $to, string $title, string $description, string $buttonText, array $sections, ?string $footer = null, array $context = []): ?string
    {
        $text = "*{$title}*\n\n{$description}";
        foreach ($sections as $section) {
            $sectionTitle = $section['title'] ?? 'Opções';
            $text .= "\n\n*{$sectionTitle}*";
            foreach ($section['rows'] ?? [] as $row) {
                $rowTitle = $row['title'] ?? '';
                $rowDesc = $row['description'] ?? '';
                $text .= "\n• {$rowTitle}" . ($rowDesc ? " - {$rowDesc}" : '');
            }
        }
        if ($footer) {
            $text .= "\n\n_{$footer}_";
        }
        return $this->sendText($to, $text, $context);
    }

    /**
     * Envio de Enquete (Poll).
     */
    public function sendPoll(string $to, string $name, array $options, int $selectableCount = 1, array $context = []): ?string
    {
        $text = "*Enquete: {$name}*\n";
        foreach ($options as $i => $opt) {
            $num = $i + 1;
            $text .= "\n{$num}. {$opt}";
        }
        return $this->sendText($to, $text, $context);
    }

    /**
     * Envio de Template Homologado na Meta.
     */
    public function sendTemplate(string $to, string $templateName, array $params = [], string $lang = 'pt_BR', ?string $buttonSuffix = null): ?string
    {
        $result = $this->metaClient->sendTemplateMessage($to, $templateName, $params, $lang, $buttonSuffix);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, "[template: {$templateName}]", 'template', $result['message_id'], []);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Mensagem de Cobrança Oficial via Template da Meta.
     */
    public function sendInvoiceBillingTemplateMessage(
        string $to,
        string $clientName,
        float|string $amount,
        string $dueDate,
        string $pixCode,
        string $invoiceToken,
        ?string $templateName = null
    ): array {
        return $this->metaClient->sendInvoiceBillingTemplateMessage(
            $to,
            $clientName,
            $amount,
            $dueDate,
            $pixCode,
            $invoiceToken,
            $templateName
        );
    }

    /**
     * Consulta status da conta / número oficial na Meta Graph API.
     */
    public function status(): array
    {
        $test = $this->metaClient->testConnection();

        if (!empty($test['ok'])) {
            return [
                'state' => 'open',
                'connected' => true,
                'status' => 'connected',
                'status_label' => 'Conectado (Meta Oficial)',
                'number' => $test['display_phone_number'] ?? '',
                'verified_name' => $test['verified_name'] ?? 'Conta Meta Oficial',
                'quality' => $test['quality_rating'] ?? 'GREEN'
            ];
        }

        return [
            'state' => 'close',
            'connected' => false,
            'status' => 'disconnected',
            'status_label' => 'Desconectado / Token Pendente',
            'error' => $test['message'] ?? 'Credenciais Meta não configuradas.'
        ];
    }

    public function testConnection(): array
    {
        return $this->metaClient->testConnection();
    }

    /**
     * Retorna status normalizado para exibição no painel administrativo e nas rotas AJAX.
     */
    public function getNormalizedInstanceStatus(): array
    {
        $test = $this->metaClient->testConnection();

        if (!empty($test['ok'])) {
            return [
                'ok' => true,
                'state' => 'open',
                'status' => 'connected',
                'connected' => true,
                'status_label' => 'Conectado (Meta Oficial)',
                'number' => $test['display_phone_number'] ?? '',
                'name' => $test['verified_name'] ?? 'Conta Meta Oficial',
                'client_name' => 'Meta Cloud API v21.0',
                'instance' => 'meta_official',
                'quality' => $test['quality_rating'] ?? 'GREEN',
                'qrcode' => null,
                'source' => 'meta_graph_api',
                '_source' => 'meta_graph_api',
                'created_at' => now()->toDateTimeString(),
                'last_check' => now()->toDateTimeString(),
            ];
        }

        return [
            'ok' => false,
            'state' => 'close',
            'status' => 'disconnected',
            'connected' => false,
            'status_label' => 'Desconectado / Token Pendente',
            'number' => '',
            'name' => 'Não configurado',
            'client_name' => 'Meta Cloud API v21.0',
            'instance' => 'meta_official',
            'quality' => 'UNKNOWN',
            'qrcode' => null,
            'source' => 'meta_graph_api',
            '_source' => 'meta_graph_api',
            '_api_error' => $test['message'] ?? 'Credenciais da Meta Cloud API não configuradas.',
            'created_at' => now()->toDateTimeString(),
            'last_check' => now()->toDateTimeString(),
        ];
    }

    /**
     * Diagnóstico da infraestrutura de comunicação com a Meta Graph API.
     */
    public function diagnoseConnection(): array
    {
        return [
            'meta_api_configured' => $this->isConfigured(),
            'graph_version' => config('whatsapp.meta_graph_version', 'v21.0'),
            'phone_number_id' => config('whatsapp.meta_phone_number_id') ? 'configurado' : 'pendente',
            'waba_id' => config('whatsapp.meta_waba_id') ? 'configurado' : 'pendente',
            'token' => config('whatsapp.meta_access_token') ? 'presente' : 'ausente',
            'connection' => $this->metaClient->testConnection(),
        ];
    }

    protected function logMessage(string $to, string $preview, string $type, string $messageId, array $context): void
    {
        try {
            WhatsAppMessage::create([
                'to' => Format::whatsappNumber($to),
                'direction' => 'out',
                'media_type' => $type,
                'message' => $preview,
                'status' => 'sent',
                'meta_message_id' => $messageId,
                'tenant_instance_id' => $context['tenant_instance_id'] ?? null,
                'dispatch_id' => $context['dispatch_id'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppMetaClient] Erro ao registrar log da mensagem:', ['error' => $e->getMessage()]);
        }
    }
}
