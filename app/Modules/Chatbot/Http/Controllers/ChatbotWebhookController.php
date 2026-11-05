<?php

namespace App\Modules\Chatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbot\Services\ChatbotEngine;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\WhatsApp\Services\EvolutionClient;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Modules\Settings\Services\SettingsManager;
use App\Services\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe mensagens da Evolution API e responde automaticamente via chatbot.
 * Configurar em Evolution: Webhook URL = /webhooks/evolution/messages
 */
class ChatbotWebhookController extends Controller
{
    public function handle(Request $request, ChatbotEngine $engine, EvolutionClient $evolution, InstanceManager $instances, WhatsAppSafetyService $safety, WebhookSignatureVerifier $webhookVerifier, ?string $event = null): JsonResponse
    {
        $data = $request->all();
        $tenantInstance = $this->resolveTenantInstance($request, $data);

        // Eventos de estado da Evolution (ex.: connection.update no restart)
        // nao carregam mensagem de cliente e algumas instalacoes nao enviam HMAC.
        // Para esses casos aceitamos apenas token valido e ignoramos o evento.
        $eventName = strtoupper(str_replace(['.', '-'], '_', (string) ($data['event'] ?? $event ?? '')));
        $isMessageEvent = in_array($eventName, ['MESSAGES_UPSERT', 'MESSAGE_UPSERT', 'MESSAGE'], true);

        $secret = (string) ($tenantInstance?->webhook_secret ?: $this->globalWebhookSecret());
        // A rota legado /webhooks/evolution/messages continua sendo usada pelas
        // instancias tenant da Evolution Go. Na pratica, varias instalacoes da
        // v3 nao enviam HMAC nem timestamp nessa URL, apenas o token.
        // Mantemos token obrigatorio aqui e concentramos validacao forte
        // (assinatura/replay) no endpoint dedicado /api/webhooks/evolution-go.
        $webhookCheck = $webhookVerifier->validate($request, $secret, $secret, [
            'token_required' => true,
            'signature_required' => false,
            'timestamp_required' => false,
        ]);
        if (! $webhookCheck['ok']) {
            Log::warning('Webhook chatbot rejeitado', [
                'reason' => $webhookCheck['message'],
                'tenant_instance_id' => $tenantInstance?->id,
                'tenant_id' => $tenantInstance?->tenant_id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => $webhookCheck['message']], 403);
        }

        // Evolution envia diferentes eventos (messages.upsert, connection.update, etc.)
        if (! $isMessageEvent) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        foreach ($this->extractMessages($data) as $msg) {
            // Ignorar mensagens do proprio bot (direction=out)
            if (($msg['key']['fromMe'] ?? false) === true) continue;

            $from = $msg['key']['remoteJid'] ?? '';
            $from = preg_replace('/@.*$/', '', $from); // remove @s.whatsapp.net
            $text = $msg['message']['conversation']
                ?? $msg['message']['extendedTextMessage']['text']
                ?? null;

            if (!$from || !$text) continue;

            if ($tenantInstance && $safety->handleInboundOptOut($tenantInstance, $from, $text, [
                'tenant_id' => $tenantInstance->tenant_id,
                'origin' => 'chatbot_webhook_inbound',
            ])) {
                continue;
            }

            // Processa e responde
            $reply = $engine->processMessage($from, $text, $tenantInstance?->tenant_id);
            if (! $reply) {
                continue;
            }

            if ($tenantInstance) {
                $instances->sendText($tenantInstance, $from, $reply, [
                    'tenant_id' => $tenantInstance->tenant_id,
                    'tenant_instance_id' => $tenantInstance->id,
                    'origin' => 'chatbot_webhook',
                    'campaign' => 'chatbot_autoreply',
                ]);
            } elseif ($evolution->isConfigured()) {
                $inspection = $safety->inspect(null, $from, $reply, [
                    'origin' => 'chatbot_webhook_fallback',
                    'campaign' => 'chatbot_autoreply',
                ]);

                if (! $inspection['allowed']) {
                    $safety->recordOutcome($inspection, [
                        'to' => $from,
                        'meta' => ['origin' => 'chatbot_webhook_fallback'],
                    ]);
                    continue;
                }

                $evolution->sendText($from, $reply, $inspection + [
                    'origin' => 'chatbot_webhook_fallback',
                    'campaign' => 'chatbot_autoreply',
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function resolveTenantInstance(Request $request, array $payload): ?TenantInstance
    {
        $id = $request->query('tenant_instance');
        if ($id) {
            return TenantInstance::find((int) $id);
        }

        $name = $request->query('instance')
            ?: data_get($payload, 'instance')
            ?: data_get($payload, 'instanceName')
            ?: data_get($payload, 'data.instance')
            ?: data_get($payload, 'data.instanceName');

        return $name ? TenantInstance::where('instance_name', $name)->first() : null;
    }

    private function globalWebhookSecret(): string
    {
        try {
            return (string) app(SettingsManager::class)->get('whatsapp.webhook_token', env('EVOLUTION_WEBHOOK_TOKEN', ''));
        } catch (\Throwable) {
            return (string) env('EVOLUTION_WEBHOOK_TOKEN', '');
        }
    }

    private function extractMessages(array $payload): array
    {
        $messages = data_get($payload, 'data.messages')
            ?: data_get($payload, 'data.message')
            ?: data_get($payload, 'data')
            ?: [];

        if (is_array($messages) && isset($messages['key'])) {
            return [$messages];
        }

        if (is_array($messages) && array_is_list($messages)) {
            return array_filter($messages, 'is_array');
        }

        return [];
    }
}
