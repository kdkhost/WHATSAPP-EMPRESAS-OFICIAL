<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — WhatsApp Cloud API Oficial da Meta (Graph API v21.0).
 */

namespace App\Modules\WhatsApp\Services;

use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Modules\Settings\Services\SettingsManager;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCloudApiClient
{
    protected string $graphVersion;
    protected string $phoneNumberId;
    protected string $wabaId;
    protected string $accessToken;
    protected string $verifyToken;
    protected string $appSecret;

    public function __construct(?SettingsManager $settings = null)
    {
        $s = $settings ?? app(SettingsManager::class);

        $this->graphVersion = (string) ($s->get('whatsapp.meta_graph_version')
            ?: config('whatsapp.meta_graph_version', env('META_GRAPH_VERSION', 'v21.0')));

        $this->phoneNumberId = (string) ($s->get('whatsapp.meta_phone_number_id')
            ?: $s->get('whatsapp.phone_number_id')
            ?: config('whatsapp.meta_phone_number_id', env('META_PHONE_NUMBER_ID', '')));

        $this->wabaId = (string) ($s->get('whatsapp.meta_waba_id')
            ?: $s->get('whatsapp.waba_id')
            ?: config('whatsapp.meta_waba_id', env('META_WABA_ID', '')));

        $this->accessToken = (string) ($s->get('whatsapp.meta_access_token')
            ?: $s->get('whatsapp.access_token')
            ?: config('whatsapp.meta_access_token', env('META_ACCESS_TOKEN', '')));

        $this->verifyToken = (string) ($s->get('whatsapp.meta_webhook_verify_token')
            ?: config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_token')));

        $this->appSecret = (string) ($s->get('whatsapp.meta_app_secret')
            ?: config('whatsapp.meta_app_secret', env('META_APP_SECRET', '')));
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    public function getGraphVersion(): string
    {
        return $this->graphVersion;
    }

    public function getPhoneNumberId(): string
    {
        return $this->phoneNumberId;
    }

    public function getWabaId(): string
    {
        return $this->wabaId;
    }

    public function getVerifyToken(): string
    {
        return $this->verifyToken;
    }

    /**
     * Verifica se o cliente está dentro da Janela Ativa de 24 horas da Meta.
     */
    public function isWithin24HourWindow(string $phoneNumber): array
    {
        $cleanPhone = Format::whatsappNumber($phoneNumber);
        $customer = Customer::where('whatsapp', $cleanPhone)
            ->orWhere('whatsapp', 'like', "%{$cleanPhone}%")
            ->orWhere('phone', $cleanPhone)
            ->orWhere('phone', 'like', "%{$cleanPhone}%")
            ->first();

        if (!$customer || empty($customer->last_interaction_at)) {
            return ['active' => false, 'remaining_hours' => 0, 'customer' => $customer];
        }

        $lastInteraction = Carbon::parse($customer->last_interaction_at);
        $diffHours = now()->diffInHours($lastInteraction);
        $active = $diffHours < 24;
        $remaining = $active ? max(0, 24 - $diffHours) : 0;

        return [
            'active' => $active,
            'remaining_hours' => $remaining,
            'last_interaction' => $lastInteraction,
            'customer' => $customer
        ];
    }

    /**
     * Atualiza o timestamp de última interação quando o cliente envia mensagem.
     */
    public function recordInteraction(string $phoneNumber): void
    {
        $cleanPhone = Format::whatsappNumber($phoneNumber);
        Customer::where('whatsapp', $cleanPhone)
            ->orWhere('whatsapp', 'like', "%{$cleanPhone}%")
            ->orWhere('phone', $cleanPhone)
            ->orWhere('phone', 'like', "%{$cleanPhone}%")
            ->update(['last_interaction_at' => now()]);
    }

    /**
     * Disparo Oficial via Meta Graph API: Message Template Homologado (Obrigatório fora da janela de 24h).
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        array $bodyParameters = [],
        string $languageCode = 'pt_BR',
        ?string $buttonUrlSuffix = null,
        ?string $headerText = null
    ): array {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Credenciais da Meta Cloud API não configuradas.'];
        }

        $recipient = Format::whatsappNumber($to);
        $components = [];

        if (!empty($headerText)) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    ['type' => 'text', 'text' => $headerText]
                ]
            ];
        }

        if (!empty($bodyParameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn($param) => [
                    'type' => 'text',
                    'text' => (string) $param
                ], array_values($bodyParameters))
            ];
        }

        if ($buttonUrlSuffix !== null) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $buttonUrlSuffix]
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ],
                'components' => !empty($components) ? $components : null
            ]
        ];

        return $this->postGraphApi('/messages', array_filter($payload));
    }

    /**
     * Disparo Oficial de Mensagem de Texto Livre (Sessão dentro da janela de 24h).
     */
    public function sendSessionTextMessage(string $to, string $text, array $context = []): array
    {
        $windowCheck = $this->isWithin24HourWindow($to);

        // Se estiver fora da janela de 24h, deve usar Template aprovado
        if (!$windowCheck['active'] && empty($context['force_session'])) {
            Log::info("[MetaCloudApi] Fora da janela de 24h para {$to}. Despachando via Template de Cobrança.");
            return $this->sendTemplateMessage(
                to: $to,
                templateName: $context['template_name'] ?? 'cobranca_padrao',
                bodyParameters: $context['template_params'] ?? [
                    $windowCheck['customer']?->name ?? 'Cliente',
                    $context['invoice_title'] ?? 'Fatura',
                    $context['invoice_amount'] ?? 'R$ 0,00',
                    $context['invoice_due'] ?? date('d/m/Y'),
                    $context['pix_code'] ?? 'Consulte a fatura'
                ],
                buttonUrlSuffix: $context['invoice_uuid'] ?? null
            );
        }

        $recipient = Format::whatsappNumber($to);
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'text',
            'text' => [
                'preview_url' => $context['linkPreview'] ?? true,
                'body' => $text
            ]
        ];

        return $this->postGraphApi('/messages', $payload);
    }

    /**
     * Envio de Mídia Oficial (Imagem, Documento PDF, etc.).
     */
    public function sendMediaMessage(string $to, string $type, string $url, ?string $caption = null, ?string $filename = null): array
    {
        $recipient = Format::whatsappNumber($to);
        $mediaObj = ['link' => $url];
        if (!empty($caption)) {
            $mediaObj['caption'] = $caption;
        }
        if (!empty($filename) && $type === 'document') {
            $mediaObj['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => $type,
            $type => $mediaObj
        ];

        return $this->postGraphApi('/messages', $payload);
    }

    /**
     * Disparo Oficial de Cobrança com Duplo Canal:
     * Injeta dinamicamente o código Pix Copia e Cola no corpo do template e o link da fatura pública (/fatura/{token}).
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
        $formattedAmount = is_numeric($amount) ? 'R$ ' . number_format((float) $amount, 2, ',', '.') : (string) $amount;
        $publicInvoiceUrl = url('/fatura/' . $invoiceToken);
        $tpl = $templateName ?: config('whatsapp.billing_template', 'aviso_fatura_pix');

        $bodyParams = [
            $clientName,
            $formattedAmount,
            $dueDate,
            $pixCode,
            $publicInvoiceUrl
        ];

        return $this->sendTemplateMessage(
            to: $to,
            templateName: $tpl,
            bodyParameters: $bodyParams,
            languageCode: 'pt_BR',
            buttonUrlSuffix: $invoiceToken,
            headerText: 'Fatura Disponível'
        );
    }

    /**
     * Teste de Conexão com a Graph API Oficial da Meta.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Phone Number ID ou Token Permanente ausentes.'];
        }

        try {
            $url = "https://graph.facebook.com/{$this->graphVersion}/{$this->phoneNumberId}";
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'ok' => true,
                    'verified_name' => $data['verified_name'] ?? 'Conta Verificada Meta',
                    'display_phone_number' => $data['display_phone_number'] ?? $this->phoneNumberId,
                    'quality_rating' => $data['quality_rating'] ?? 'GREEN',
                    'data' => $data
                ];
            }

            return [
                'ok' => false,
                'message' => $response->json()['error']['message'] ?? 'Falha na autenticação com a Meta Graph API.'
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Chamada HTTP genérica na Meta Graph API.
     */
    protected function postGraphApi(string $endpoint, array $payload): array
    {
        try {
            $url = "https://graph.facebook.com/{$this->graphVersion}/{$this->phoneNumberId}" . $endpoint;
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? null;
                return [
                    'ok' => true,
                    'message_id' => $messageId,
                    'data' => $data
                ];
            }

            $error = $response->json()['error'] ?? ['message' => 'Erro desconhecido na Meta API'];
            Log::error("[MetaCloudApi] Falha no disparo Graph API:", $error);

            return [
                'ok' => false,
                'error' => $error['message'] ?? 'Erro retornado pela Meta.',
                'code' => $error['code'] ?? null,
                'error_data' => $error
            ];
        } catch (\Throwable $e) {
            Log::error("[MetaCloudApi] Exceção de conexão:", ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

