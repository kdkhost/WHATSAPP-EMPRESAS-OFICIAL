<?php

namespace App\Modules\Gateways\Core;

use App\Modules\Tenancy\Models\TenantGateway;
use Illuminate\Support\Str;

class GatewayWebhookManager
{
    public function buildDynamicWebhook(string $gateway, ?TenantGateway $tenantGateway = null): array
    {
        $token = (string) ($tenantGateway?->webhook_token ?: Str::random(40));
        $secret = (string) ($tenantGateway?->webhook_secret ?: Str::random(64));

        return [
            'url' => route('webhook.'.$gateway, ['token' => $token]),
            'token' => $token,
            'secret' => $secret,
            'signature_header' => 'X-Webhook-Signature',
            'timestamp_header' => 'X-Webhook-Timestamp',
        ];
    }
}
