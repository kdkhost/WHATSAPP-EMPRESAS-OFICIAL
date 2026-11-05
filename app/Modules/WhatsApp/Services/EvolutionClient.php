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
use App\Support\Format;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EvolutionClient (Refatorado para Meta Cloud API Oficial).
 * Preserva 100% da compatibilidade com os módulos existentes do projeto,
 * despachando as mensagens através da Graph API Oficial da Meta.
 */
class EvolutionClient
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
        // Quarentena descontinuada na API oficial da Meta
    }

    /**
     * Envio de Texto com Regra de 24 Horas da Meta.
     */
    public function sendText(string $to, string $message, array $context = []): ?string
    {
        $result = $this->metaClient->sendSessionTextMessage($to, $message, $context);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, $message, 'text', $result['message_id'], $context);
            return $result['message_id'];
        }

        Log::warning("[EvolutionClient -> MetaAPI] Falha no envio de texto:", $result);
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
        $result = $this->metaClient->sendMediaMessage($to, 'image', $image, $caption);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, $caption ?: '[imagem]', 'image', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Documento Oficial (PDF de faturas e carnês).
     */
    public function sendDocument(string $to, string $file, string $filename = 'fatura.pdf', ?string $caption = null, array $context = []): ?string
    {
        $result = $this->metaClient->sendMediaMessage($to, 'document', $file, $caption, $filename);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, "[documento: {$filename}]", 'document', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
    }

    /**
     * Envio de Áudio Oficial.
     */
    public function sendAudio(string $to, string $audio, bool $ptt = true, array $context = []): ?string
    {
        $result = $this->metaClient->sendMediaMessage($to, 'audio', $audio);

        if (!empty($result['ok']) && !empty($result['message_id'])) {
            $this->logMessage($to, '[audio]', 'audio', $result['message_id'], $context);
            return $result['message_id'];
        }

        return null;
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
                'number' => Format::whatsappNumber($to),
                'direction' => 'outbound',
                'type' => $type,
                'message' => $preview,
                'status' => 'sent',
                'meta_message_id' => $messageId,
                'tenant_id' => $context['tenant_id'] ?? null,
                'invoice_id' => $context['invoice_id'] ?? null,
                'customer_id' => $context['customer_id'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("[EvolutionClient] Erro ao registrar log da mensagem:", ['error' => $e->getMessage()]);
        }
    }
}
