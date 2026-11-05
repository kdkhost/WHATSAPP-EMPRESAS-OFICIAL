<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Controlador Oficial do Webhook da Meta Graph API.
 */

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\WhatsApp\Services\MetaCloudApiClient;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Handshake Oficial GET /api/v1/webhook
     */
    public function verify(Request $request, MetaCloudApiClient $metaClient)
    {
        $mode = $request->query('hub_mode') ?: $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?: $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?: $request->query('hub.challenge');

        $expectedToken = $metaClient->getVerifyToken();

        Log::info('[MetaWebhook] Handshake recebido:', [
            'mode' => $mode,
            'token' => $token,
            'challenge' => $challenge
        ]);

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('[MetaWebhook] Handshake aprovado com sucesso!');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[MetaWebhook] Handshake recusado: token inválido.');
        return response()->json(['error' => 'Token de verificação inválido'], 403);
    }

    /**
     * Recepção de Eventos POST /api/v1/webhook com Validação HMAC-SHA256
     */
    public function receive(Request $request, MetaCloudApiClient $metaClient)
    {
        $rawPayload = $request->getContent();
        $signatureHeader = $request->header('x-hub-signature-256');

        // Validação criptográfica de integridade HMAC-SHA256
        $appSecret = env('META_APP_SECRET', config('whatsapp.meta_app_secret'));
        if (!empty($appSecret) && env('APP_ENV') === 'production') {
            if (empty($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
                Log::warning('[MetaWebhook] Cabeçalho x-hub-signature-256 ausente ou mal formatado.');
                return response()->json(['error' => 'Assinatura ausente'], 401);
            }

            $signatureHash = substr($signatureHeader, 7);
            $expectedHash = hash_hmac('sha256', $rawPayload, $appSecret);

            if (!hash_equals($expectedHash, $signatureHash)) {
                Log::warning('[MetaWebhook] Assinatura HMAC-SHA256 inválida.');
                return response()->json(['error' => 'Assinatura inválida'], 401);
            }
        }

        $payload = json_decode($rawPayload, true) ?: [];

        // Resposta imediata 200 OK para a Meta
        $response = response('EVENT_RECEIVED', 200);

        // Processa eventos em segundo plano
        $this->processMetaEvent($payload, $metaClient);

        return $response;
    }

    protected function processMetaEvent(array $payload, MetaCloudApiClient $metaClient): void
    {
        try {
            $entries = $payload['entry'] ?? [];
            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];

                    // 1. Mensagens Recebidas do Cliente Final (Atualiza janela de 24 horas!)
                    if (!empty($value['messages']) && is_array($value['messages'])) {
                        foreach ($value['messages'] as $msg) {
                            $fromPhone = $msg['from'] ?? null;
                            if ($fromPhone) {
                                $cleanPhone = Format::whatsappNumber($fromPhone);
                                Log::info("[MetaWebhook] Mensagem recebida de {$cleanPhone}. Atualizando janela de 24h.");
                                $metaClient->recordInteraction($cleanPhone);
                            }
                        }
                    }

                    // 2. Atualizações de Status de Entrega (sent, delivered, read, failed)
                    if (!empty($value['statuses']) && is_array($value['statuses'])) {
                        foreach ($value['statuses'] as $statusObj) {
                            $metaMessageId = $statusObj['id'] ?? null;
                            $status = strtolower((string) ($statusObj['status'] ?? ''));
                            $errors = $statusObj['errors'] ?? [];

                            if ($metaMessageId) {
                                $updateData = ['status' => $status, 'updated_at' => now()];

                                if (!empty($errors)) {
                                    $updateData['last_error'] = json_encode($errors, JSON_UNESCAPED_UNICODE);
                                }

                                DB::table('whatsapp_messages')
                                    ->where('meta_message_id', $metaMessageId)
                                    ->orWhere('external_id', $metaMessageId)
                                    ->update($updateData);

                                if (DB::getSchemaBuilder()->hasTable('billing_dispatches')) {
                                    DB::table('billing_dispatches')
                                        ->where('external_message_id', $metaMessageId)
                                        ->update($updateData);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("[MetaWebhook] Erro ao processar evento:", ['error' => $e->getMessage()]);
        }
    }
}

