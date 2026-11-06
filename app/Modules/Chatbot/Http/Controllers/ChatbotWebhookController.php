<?php

namespace App\Modules\Chatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbot\Services\ChatbotEngine;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\WhatsApp\Services\WhatsAppMetaClient;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Modules\Settings\Services\SettingsManager;
use App\Services\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe mensagens do WhatsApp e responde automaticamente via chatbot.
 */
class ChatbotWebhookController extends Controller
{
    public function handle(Request $request, ChatbotEngine $engine, WhatsAppMetaClient $whatsApp, InstanceManager $instances, WhatsAppSafetyService $safety, WebhookSignatureVerifier $webhookVerifier, ?string $event = null): JsonResponse
    {
        $data = $request->all();
        $tenantInstance = $this->resolveTenantInstance($request, $data);

        // Eventos de estado do WhatsApp Oficial
        $eventName = strtoupper(str_replace(['.', '-'], '_', (string) ($data['event'] ?? $event ?? '')));
        $isMessageEvent = in_array($eventName, ['MESSAGES_UPSERT', 'MESSAGE_UPSERT', 'MESSAGE', 'MESSAGES'], true);

        $secret = (string) ($tenantInstance?->webhook_secret ?: $this->globalWebhookSecret());
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

        // Ignora eventos que não sejam de mensagens recebidas
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
            } elseif ($whatsApp->isConfigured()) {
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

                $whatsApp->sendText($from, $reply, $inspection + [
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
            return (string) app(SettingsManager::class)->get('whatsapp.webhook_token', config('whatsapp.meta_webhook_verify_token', ''));
        } catch (\Throwable) {
            return (string) config('whatsapp.meta_webhook_verify_token', '');
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
