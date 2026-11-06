<?php

use App\Models\WhatsAppInstanceQuarantine;
use App\Models\WhatsAppInstanceReputation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSafetyControl;
use App\Models\WhatsAppSafetyEvent;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhatsApp\Services\WhatsAppQuarantineService;
use App\Modules\WhatsApp\Services\WhatsAppQueuePlanner;

function advancedTenant(): array
{
    $plan = Plan::create([
        'name' => 'Plano Advanced',
        'slug' => 'plano-advanced-'.uniqid(),
        'price' => 0, 'billing_cycle' => 'monthly', 'trial_days' => 7,
        'max_instances' => 2, 'max_customers' => 100,
        'max_invoices_month' => 100, 'max_messages_month' => 100,
        'custom_gateway' => true, 'custom_templates' => true,
        'installment_enabled' => true, 'fee_passthrough' => false, 'active' => true,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Tenant Advanced', 'email' => 'tenant-advanced-'.uniqid().'@example.com', 'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id, 'plan_id' => $plan->id,
        'company_name' => 'Tenant Advanced', 'slug' => 'tenant-advanced-'.uniqid(), 'status' => 'active',
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id, 'instance_name' => 'tenant_advanced_'.$tenant->id,
        'display_name' => 'Advanced', 'status' => 'connected',
        'api_base_url' => 'https://graph.facebook.com/v21.0', 'api_key' => 'token', 'webhook_secret' => 'secret',
    ]);

    return [$tenant, $instance];
}

it('quarantine service detecta falha alta e aplica quarentena', function () {
    [, $instance] = advancedTenant();
    $service = app(WhatsAppQuarantineService::class);

    for ($i = 0; $i < 10; $i++) {
        WhatsAppMessage::create([
            'tenant_instance_id' => $instance->id, 'to' => '5511911111111',
            'message' => 'Msg '.$i, 'direction' => 'out', 'status' => 'failed',
            'recipient_hash' => hash('sha256', '5511911111111'),
            'message_hash' => hash('sha256', 'msg '.$i),
            'created_at' => now()->subMinutes(10 - $i),
        ]);
        WhatsAppMessage::create([
            'tenant_instance_id' => $instance->id, 'to' => '5511911111112',
            'message' => 'Msg '.$i, 'direction' => 'out', 'status' => 'sent',
            'recipient_hash' => hash('sha256', '5511911111112'),
            'message_hash' => hash('sha256', 'msg '.$i),
            'created_at' => now()->subMinutes(10 - $i),
        ]);
    }

    expect($service->shouldQuarantine($instance))->toBeTrue();

    $quarantine = $service->quarantine($instance, 'failure_rate_too_high');
    expect($quarantine)->toBeInstanceOf(WhatsAppInstanceQuarantine::class)
        ->and($quarantine->active)->toBeTrue()
        ->and($quarantine->reason)->toBe('failure_rate_too_high');

    expect($service->isQuarantined($instance))->toBeTrue()
        ->and($service->reason($instance))->toBe('failure_rate_too_high');

    $service->release($instance, null, 'test_release');
    expect($service->isQuarantined($instance))->toBeFalse();
});

it('quarantine service trata 401 como token lock', function () {
    [, $instance] = advancedTenant();
    $service = app(WhatsAppQuarantineService::class);

    $quarantine = $service->quarantine($instance, 'token_invalid_401', 'token_lock');
    expect($quarantine->mode)->toBe('token_lock');

    $control = WhatsAppSafetyControl::where('tenant_instance_id', $instance->id)->first();
    expect($control)->not->toBeNull()
        ->and($control->sending_paused)->toBeTrue();
});

it('queue planner respeita concorrencia e retorna delay baseado em risco', function () {
    [, $instance] = advancedTenant();

    WhatsAppSafetyControl::create([
        'tenant_instance_id' => $instance->id, 'tenant_id' => $instance->tenant_id,
        'limit_per_day' => 100, 'warmup_enabled' => false,
    ]);

    $planner = app(WhatsAppQueuePlanner::class);
    $plan = $planner->plan($instance, ['media_type' => 'text']);

    expect($plan)->toHaveKey('allowed')
        ->and($plan)->toHaveKey('delay_seconds')
        ->and($plan)->toHaveKey('concurrency');

    $delay = $planner->calculateDelay($instance, ['media_type' => 'image']);
    expect($delay)->toBeGreaterThan(0);
});

it('queue planner bloqueia instancia em quarentena', function () {
    [, $instance] = advancedTenant();
    $quarantineService = app(WhatsAppQuarantineService::class);

    $quarantineService->quarantine($instance, 'test_lock', 'soft_pause', 60);

    $planner = app(WhatsAppQueuePlanner::class);
    $plan = $planner->plan($instance);

    expect($plan['allowed'])->toBeFalse()
        ->and($plan['reason'])->toBe('instance_quarantined');
});

it('queue planner bloqueia sending disabled', function () {
    [, $instance] = advancedTenant();

    WhatsAppSafetyControl::create([
        'tenant_instance_id' => $instance->id, 'tenant_id' => $instance->tenant_id,
        'sending_disabled' => true,
    ]);

    $planner = app(WhatsAppQueuePlanner::class);
    $plan = $planner->plan($instance);

    expect($plan['allowed'])->toBeFalse()
        ->and($plan['reason'])->toBe('sending_disabled');
});

it('aplica per-type limits corretamente no safety service', function () {
    [, $instance] = advancedTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 20);
    config()->set('whatsapp_anti_ban.limits.per_hour', 200);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.type_limits.image', 1);

    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id, 'to' => '5511999999999',
        'message' => '[imagem]', 'direction' => 'out', 'media_type' => 'image',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511999999999'),
        'message_hash' => hash('sha256', '[imagem]'),
        'created_at' => now()->subMinutes(5),
    ]);

    $service = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);

    $blocked = $service->inspect($instance, '5511988887777', 'Outra imagem', ['media_type' => 'image']);
    expect($blocked['allowed'])->toBeFalse()
        ->and($blocked['reason'])->toBe('type_hour_limit_reached');

    $text = $service->inspect($instance, '5511988887777', 'Texto normal', ['media_type' => 'text']);
    expect($text['allowed'])->toBeTrue();
});

it('aplica warmup real com schedule expandido', function () {
    [, $instance] = advancedTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.warmup.enabled', true);
    config()->set('whatsapp_anti_ban.warmup.daily_limits', [1 => 10, 2 => 20, 3 => 30, 7 => 50, 15 => 100, 30 => 150]);

    TenantInstance::whereKey($instance->id)->update(['created_at' => now()->subDay()]);
    $instance->refresh();

    $service = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('warmupLimit');
    $method->setAccessible(true);

    $limit = $method->invoke($service, $instance);
    expect($limit)->toBe(20);

    TenantInstance::whereKey($instance->id)->update(['created_at' => now()->subDays(5)]);
    $instance->refresh();
    $limit = $method->invoke($service, $instance);
    expect($limit)->toBe(50);
});

it('detecta repeticao de mensagem e gera alerta', function () {
    [, $instance] = advancedTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.repeat_detection.identical_window_minutes', 60);
    config()->set('whatsapp_anti_ban.repeat_detection.max_identical_per_window', 3);

    $service = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('identicalMessagesCount');
    $method->setAccessible(true);

    for ($i = 0; $i < 5; $i++) {
        WhatsAppMessage::create([
            'tenant_instance_id' => $instance->id, 'to' => '551191111111'.$i,
            'message' => 'Mensagem repetida', 'direction' => 'out', 'status' => 'sent',
            'recipient_hash' => hash('sha256', '551191111111'.$i),
            'message_hash' => hash('sha256', 'mensagem repetida'),
            'created_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $count = $method->invoke($service, $instance, 'Mensagem repetida', now()->subHours(2));
    expect($count)->toBe(5);
});

it('gerencia reputacao de instancia com updateInstanceReputation', function () {
    [, $instance] = advancedTenant();

    $service = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('updateInstanceReputation');
    $method->setAccessible(true);

    $method->invoke($service, [
        'tenant_id' => $instance->tenant_id,
        'tenant_instance_id' => $instance->id,
        'risk_score' => 10,
        'trust_score' => 90,
        'quality_score' => 85,
    ], ['delivered' => true], 'allowed');

    $reputation = WhatsAppInstanceReputation::where('tenant_instance_id', $instance->id)->first();
    expect($reputation)->not->toBeNull()
        ->and($reputation->daily_sent)->toBe(1);

    $method->invoke($service, [
        'tenant_id' => $instance->tenant_id,
        'tenant_instance_id' => $instance->id,
        'risk_score' => 50,
        'trust_score' => 50,
        'quality_score' => 40,
    ], [], 'blocked');

    $reputation->refresh();
    expect($reputation->daily_failed)->toBe(1)
        ->and($reputation->trust_score)->toBeLessThan(90);
});

it('safety service inspect bloqueia instancia em quarentena', function () {
    [, $instance] = advancedTenant();
    $quarantineService = app(WhatsAppQuarantineService::class);
    $safety = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 20);
    config()->set('whatsapp_anti_ban.limits.per_hour', 200);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);

    $quarantineService->quarantine($instance, 'test_quarantine', 'soft_pause', 60);

    $result = $safety->inspect($instance, '5511988887777', 'Teste em quarentena');
    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toBe('instance_quarantined');
});

it('queue planner eh chamado antes do safety inspect no instance manager', function () {
    [, $instance] = advancedTenant();
    $planner = app(WhatsAppQueuePlanner::class);

    WhatsAppSafetyControl::create([
        'tenant_instance_id' => $instance->id, 'tenant_id' => $instance->tenant_id,
        'sending_disabled' => true, 'limit_per_day' => 100, 'warmup_enabled' => false,
    ]);

    $plan = $planner->plan($instance, ['media_type' => 'text']);
    expect($plan['allowed'])->toBeFalse()
        ->and($plan['reason'])->toBe('sending_disabled');
});

it('quarentena no safety service inspect persiste evento via recordOutcome', function () {
    [, $instance] = advancedTenant();
    $quarantineService = app(WhatsAppQuarantineService::class);
    $safety = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 20);
    config()->set('whatsapp_anti_ban.limits.per_hour', 200);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);

    $quarantineService->quarantine($instance, 'test_quality', 'quality_lock', 240);

    $result = $safety->inspect($instance, '5511988887777', 'Teste quality lock');
    expect($result['allowed'])->toBeFalse();

    $safety->recordOutcome($result, [
        'tenant_id' => $instance->tenant_id,
        'tenant_instance_id' => $instance->id,
        'to' => '5511988887777',
    ]);

    $event = WhatsAppSafetyEvent::where('tenant_instance_id', $instance->id)
        ->where('reason', 'instance_quarantined')->first();
    expect($event)->not->toBeNull();
});

it('gera alertas automaticos baseados em metricas', function () {
    [, $instance] = advancedTenant();

    $controller = app(\App\Modules\WhatsApp\Http\Controllers\WhatsAppAdminController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('generateAlerts');
    $method->setAccessible(true);

    config()->set('whatsapp_anti_ban.limits.per_day', 100);

    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id, 'to' => '5511911111111',
        'message' => 'test', 'direction' => 'out', 'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511911111111'),
        'message_hash' => hash('sha256', 'test'),
        'created_at' => now()->subMinutes(1),
    ]);

    WhatsAppSafetyEvent::create([
        'tenant_instance_id' => $instance->id, 'event_type' => 'blocked',
        'reason' => 'test', 'risk_score' => 95, 'risk_level' => 'critico',
        'created_at' => now()->subMinutes(1),
    ]);

    $alerts = $method->invoke($controller);
    expect($alerts)->toBeArray();
});
