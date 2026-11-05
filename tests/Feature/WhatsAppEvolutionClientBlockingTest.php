<?php

use App\Models\WhatsAppSafetyControl;
use App\Models\WhatsAppSafetyEvent;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhatsApp\Services\EvolutionClient;
use Illuminate\Support\Facades\Http;

it('evolution client detecta 401 e aciona quarentena', function () {
    $plan = Plan::create(['name' => 'Evo Test', 'slug' => 'evo-test-'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'trial_days' => 7, 'max_instances' => 1, 'max_customers' => 50, 'max_invoices_month' => 50, 'max_messages_month' => 50, 'custom_gateway' => true, 'custom_templates' => true, 'installment_enabled' => true, 'fee_passthrough' => false, 'active' => true]);
    $user = \App\Models\User::create(['name' => 'Evo', 'email' => 'evo-'.uniqid().'@test.com', 'password' => 'secret']);
    $tenant = Tenant::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'company_name' => 'Evo', 'slug' => 'evo-'.uniqid(), 'status' => 'active']);
    $instance = TenantInstance::create(['tenant_id' => $tenant->id, 'instance_name' => 'evo_'.$tenant->id, 'display_name' => 'Evo', 'status' => 'connected', 'api_base_url' => 'https://evo.test', 'api_key' => 'token', 'webhook_secret' => 'secret']);

    WhatsAppSafetyControl::create(['tenant_instance_id' => $instance->id, 'tenant_id' => $tenant->id, 'sending_paused' => false]);

    Http::fake([
        'https://evo.test/send/text' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);

    $client = new EvolutionClient('https://evo.test', 'token', $instance->instance_name, 5);

    expect(fn () => $client->sendText('5511999999999', 'Teste', [
        'tenant_id' => $tenant->id,
        'tenant_instance_id' => $instance->id,
        'skip_safety' => true,
        'allowed' => true,
    ]))->toThrow(RuntimeException::class, '401');

    $control = WhatsAppSafetyControl::where('tenant_instance_id', $instance->id)->first();
    expect($control->last_unauthorized_at)->not->toBeNull();
});

it('evolution client detecta 429 e registra rate limit', function () {
    $plan = Plan::create(['name' => 'Evo Test2', 'slug' => 'evo-test2-'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'trial_days' => 7, 'max_instances' => 1, 'max_customers' => 50, 'max_invoices_month' => 50, 'max_messages_month' => 50, 'custom_gateway' => true, 'custom_templates' => true, 'installment_enabled' => true, 'fee_passthrough' => false, 'active' => true]);
    $user = \App\Models\User::create(['name' => 'Evo2', 'email' => 'evo2-'.uniqid().'@test.com', 'password' => 'secret']);
    $tenant = Tenant::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'company_name' => 'Evo2', 'slug' => 'evo2-'.uniqid(), 'status' => 'active']);
    $instance = TenantInstance::create(['tenant_id' => $tenant->id, 'instance_name' => 'evo2_'.$tenant->id, 'display_name' => 'Evo2', 'status' => 'connected', 'api_base_url' => 'https://evo.test', 'api_key' => 'token', 'webhook_secret' => 'secret']);

    WhatsAppSafetyControl::create(['tenant_instance_id' => $instance->id, 'tenant_id' => $tenant->id, 'sending_paused' => false]);

    Http::fake([
        'https://evo.test/send/text' => Http::response(['message' => 'Rate limit'], 429),
    ]);

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);

    $client = new EvolutionClient('https://evo.test', 'token', $instance->instance_name, 5);

    expect(fn () => $client->sendText('5511999999998', 'Teste 429', [
        'tenant_id' => $tenant->id,
        'tenant_instance_id' => $instance->id,
        'skip_safety' => true,
        'allowed' => true,
    ]))->toThrow(RuntimeException::class);

    $control = WhatsAppSafetyControl::where('tenant_instance_id', $instance->id)->first();
    expect($control->last_rate_limit_at)->not->toBeNull();
});
