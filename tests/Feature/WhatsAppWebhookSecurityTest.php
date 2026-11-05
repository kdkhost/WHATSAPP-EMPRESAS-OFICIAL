<?php

use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;

function webhookSecurityTenant(): TenantInstance
{
    $plan = Plan::create([
        'name' => 'Plano Webhook Seguro',
        'slug' => 'plano-webhook-seguro-'.uniqid(),
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 2,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Tenant Webhook Seguro',
        'email' => 'tenant-webhook-seguro-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Webhook Seguro',
        'slug' => 'tenant-webhook-seguro-'.uniqid(),
        'status' => 'active',
    ]);

    return TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_webhook_seguro_'.$tenant->id,
        'display_name' => 'Webhook Seguro',
        'status' => 'creating',
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);
}

it('rejeita webhook com assinatura invalida', function () {
    $instance = webhookSecurityTenant();
    config()->set('whatsapp_anti_ban.security.webhook_secret_required', true);

    $payload = [
        'event' => 'connection.update',
        'instance' => $instance->instance_name,
        'data' => ['state' => 'open'],
    ];

    $this->withHeaders([
        'X-Webhook-Token' => 'secret-token',
        'X-Webhook-Signature' => 'assinatura-invalida',
        'X-Webhook-Timestamp' => (string) now()->timestamp,
    ])->postJson('/api/webhooks/evolution-go', $payload)
        ->assertForbidden()
        ->assertJsonPath('error', 'webhook_signature_invalid');
});

it('rejeita webhook sem assinatura obrigatoria', function () {
    $instance = webhookSecurityTenant();
    config()->set('whatsapp_anti_ban.security.webhook_secret_required', true);
    config()->set('whatsapp_anti_ban.security.webhook_signature_required', true);

    $payload = [
        'event' => 'connection.update',
        'instance' => $instance->instance_name,
        'data' => ['state' => 'open'],
    ];

    $this->withHeaders([
        'X-Webhook-Token' => 'secret-token',
        'X-Webhook-Timestamp' => (string) now()->timestamp,
    ])->postJson('/api/webhooks/evolution-go', $payload)
        ->assertForbidden()
        ->assertJsonPath('error', 'webhook_signature_missing');
});

it('rejeita webhook sem timestamp quando replay protection esta ativo', function () {
    $instance = webhookSecurityTenant();
    config()->set('whatsapp_anti_ban.security.webhook_secret_required', true);
    config()->set('whatsapp_anti_ban.security.webhook_timestamp_required', true);

    $payload = [
        'event' => 'connection.update',
        'instance' => $instance->instance_name,
        'data' => ['state' => 'open'],
    ];
    $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), 'secret-token');

    $this->withHeaders([
        'X-Webhook-Token' => 'secret-token',
        'X-Webhook-Signature' => $signature,
    ])->postJson('/api/webhooks/evolution-go', $payload)
        ->assertForbidden()
        ->assertJsonPath('error', 'webhook_timestamp_missing');
});

it('rejeita webhook fora da janela de replay', function () {
    $instance = webhookSecurityTenant();
    config()->set('whatsapp_anti_ban.security.webhook_secret_required', true);
    config()->set('whatsapp_anti_ban.security.webhook_max_age_seconds', 60);

    $payload = [
        'event' => 'connection.update',
        'instance' => $instance->instance_name,
        'data' => ['state' => 'open'],
    ];

    $content = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $content, 'secret-token');

    $this->withHeaders([
        'X-Webhook-Token' => 'secret-token',
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Timestamp' => (string) now()->subMinutes(10)->timestamp,
    ])->postJson('/api/webhooks/evolution-go', $payload)
        ->assertForbidden()
        ->assertJsonPath('error', 'webhook_replay_window_expired');
});
