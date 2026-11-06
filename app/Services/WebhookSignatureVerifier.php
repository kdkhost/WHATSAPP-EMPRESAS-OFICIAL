<?php

namespace App\Services;

use Illuminate\Http\Request;

class WebhookSignatureVerifier
{
    public function validate(Request $request, ?string $secret, ?string $expectedToken = null, array $options = []): array
    {
        $tokenRequired = (bool) ($options['token_required'] ?? config('whatsapp_anti_ban.security.webhook_secret_required', true));
        $signatureRequired = (bool) ($options['signature_required'] ?? config('whatsapp_anti_ban.security.webhook_signature_required', true));
        $timestampRequired = (bool) ($options['timestamp_required'] ?? config('whatsapp_anti_ban.security.webhook_timestamp_required', true));
        $maxAge = max(30, (int) ($options['max_age_seconds'] ?? config('whatsapp_anti_ban.security.webhook_max_age_seconds', 300)));

        $expectedToken = (string) ($expectedToken ?: $secret ?: '');
        $providedToken = (string) ($request->bearerToken()
            ?: $request->query('token')
            ?: $request->header('X-Webhook-Token')
            ?: $request->header('apikey'));

        if ($expectedToken !== '') {
            if (! hash_equals($expectedToken, $providedToken)) {
                return ['ok' => false, 'message' => 'webhook_token_invalid'];
            }
        } elseif ($tokenRequired) {
            return ['ok' => false, 'message' => 'webhook_token_not_configured'];
        }

        $timestamp = $request->header('X-Webhook-Timestamp') ?: $request->query('ts');
        if (! $timestamp && $timestampRequired) {
            return ['ok' => false, 'message' => 'webhook_timestamp_missing'];
        }

        if ($timestamp) {
            $age = abs(now()->timestamp - (int) $timestamp);
            if ($age > $maxAge) {
                return ['ok' => false, 'message' => 'webhook_replay_window_expired'];
            }
        }

        $signature = (string) ($request->header('X-Webhook-Signature') ?: $request->header('X-Hub-Signature-256') ?: '');
        if ($signature === '') {
            return $signatureRequired
                ? ['ok' => false, 'message' => 'webhook_signature_missing']
                : ['ok' => true];
        }

        $secret = (string) ($secret ?: $expectedToken);
        if ($secret === '') {
            return ['ok' => false, 'message' => 'webhook_signature_secret_missing'];
        }

        $signature = preg_replace('/^sha256=/i', '', trim($signature));
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $signature)
            ? ['ok' => true]
            : ['ok' => false, 'message' => 'webhook_signature_invalid'];
    }
}
