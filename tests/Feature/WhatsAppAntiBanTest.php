<?php

use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppRecipientPreference;
use App\Models\WhatsAppReputationScore;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhatsApp\Services\WhatsAppMetaClient;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use Illuminate\Support\Facades\Http;

function antibanTenant(): array
{
    $plan = Plan::create([
        'name' => 'Plano AntiBan',
        'slug' => 'plano-antiban-'.uniqid(),
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
        'name' => 'Tenant AntiBan',
        'email' => 'tenant-antiban-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant AntiBan',
        'slug' => 'tenant-antiban-'.uniqid(),
        'status' => 'active',
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_antiban_'.$tenant->id,
        'display_name' => 'AntiBan',
        'status' => 'connected',
        'api_base_url' => 'https://graph.facebook.com/v21.0',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);

    return [$tenant, $instance];
}

it('bloqueia envio para grupos', function () {
    [, $instance] = antibanTenant();

    $result = app(WhatsAppSafetyService::class)->inspect($instance, '120363000000000@g.us', 'Teste grupo');

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toBe('group_send_blocked');
});

it('respeita limite por minuto e cooldown do destinatario', function () {
    [, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 1);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 30);

    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5511999999999',
        'message' => 'Primeira mensagem',
        'direction' => 'out',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511999999999'),
        'message_hash' => hash('sha256', 'primeira mensagem'),
        'created_at' => now()->subSeconds(20),
        'updated_at' => now()->subSeconds(20),
    ]);

    $minute = app(WhatsAppSafetyService::class)->inspect($instance, '5511999999999', 'Nova mensagem');
    expect($minute['allowed'])->toBeFalse()
        ->and($minute['reason'])->toBe('minute_limit_reached');

    config()->set('whatsapp_anti_ban.limits.per_minute', 5);

    $cooldown = app(WhatsAppSafetyService::class)->inspect($instance, '5511999999999', 'Nova mensagem');
    expect($cooldown['allowed'])->toBeFalse()
        ->and($cooldown['reason'])->toBe('recipient_cooldown_active');
});

it('aplica warmup e bloqueia score critico', function () {
    [, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.warmup.enabled', true);
    config()->set('whatsapp_anti_ban.warmup.daily_limits', [3 => 2, 7 => 5, 15 => 10]);
    config()->set('whatsapp_anti_ban.risk_thresholds.block_above', 50);

    TenantInstance::whereKey($instance->id)->update(['created_at' => now()->subDay()]);
    $instance->refresh();

    for ($i = 0; $i < 2; $i++) {
        WhatsAppMessage::create([
            'tenant_instance_id' => $instance->id,
            'to' => '5511911111111',
            'message' => 'Mensagem '.$i,
            'direction' => 'out',
            'status' => 'sent',
            'recipient_hash' => hash('sha256', '5511911111111'),
            'message_hash' => hash('sha256', 'mensagem '.$i),
            'created_at' => now()->subMinutes(10 + $i),
            'updated_at' => now()->subMinutes(10 + $i),
        ]);
    }

    $result = app(WhatsAppSafetyService::class)->inspect($instance, '5511988887777', 'Oferta nova');

    expect($result['allowed'])->toBeFalse()
        ->and(in_array($result['reason'], ['day_limit_reached', 'risk_score_blocked'], true))->toBeTrue();
});

it('bloqueia destinatario com opt out salvo', function () {
    [$tenant, $instance] = antibanTenant();
    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente OptOut',
        'whatsapp' => '21999990000',
    ]);

    WhatsAppRecipientPreference::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'phone' => '5521999990000',
        'phone_hash' => hash('sha256', '5521999990000'),
        'opt_in' => false,
        'opt_out' => true,
        'opt_out_at' => now(),
        'opt_out_reason' => 'manual',
        'lgpd_consented' => true,
        'lgpd_consented_at' => now(),
    ]);

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);

    $result = app(WhatsAppSafetyService::class)->inspect($instance, '5521999990000', 'Mensagem');

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toBe('recipient_opted_out');
});

it('aplica cooldown por tipo de midia e registra reputacao', function () {
    [, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.media_minutes', 30);

    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5511999999999',
        'message' => '[imagem]',
        'direction' => 'out',
        'media_type' => 'image',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511999999999'),
        'message_hash' => hash('sha256', '[imagem]'),
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    $service = app(WhatsAppSafetyService::class);
    $blocked = $service->inspect($instance, '5511999999999', 'Imagem nova', ['media_type' => 'image']);

    expect($blocked['allowed'])->toBeFalse()
        ->and($blocked['reason'])->toBe('media_cooldown_active')
        ->and($blocked['trust_score'])->toBeLessThan(100)
        ->and($blocked['quality_score'])->toBeGreaterThan(0);

    $service->recordOutcome($blocked, [
        'tenant_id' => $instance->tenant_id,
        'tenant_instance_id' => $instance->id,
        'to' => '5511999999999',
        'meta' => ['origin' => 'test', 'media_type' => 'image'],
    ]);

    expect(WhatsAppReputationScore::where('tenant_instance_id', $instance->id)->where('scope', 'instance')->exists())->toBeTrue();
});

it('nao bloqueia destinatarios diferentes pelo cooldown de campanha', function () {
    [, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 90);

    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5511999990001',
        'message' => 'Cobranca A',
        'direction' => 'out',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511999990001'),
        'message_hash' => hash('sha256', 'cobranca a'),
        'meta' => ['campaign' => 'billing_0d'],
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    $service = app(WhatsAppSafetyService::class);

    $sameRecipient = $service->inspect($instance, '5511999990001', 'Cobranca B', ['campaign' => 'billing_0d']);
    $otherRecipient = $service->inspect($instance, '5511999990002', 'Cobranca B', ['campaign' => 'billing_0d']);

    expect($sameRecipient['allowed'])->toBeFalse()
        ->and($sameRecipient['reason'])->toBe('campaign_cooldown_active')
        ->and($otherRecipient['allowed'])->toBeTrue();
});

it('aplica camada anti ban tambem em envios de video pelo client whatsapp meta', function () {
    [$tenant, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.media_minutes', 0);

    config()->set('whatsapp.meta_access_token', 'test-token');
    config()->set('whatsapp.meta_phone_number_id', '123456789');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'video-1']]], 200),
    ]);

    $client = new WhatsAppMetaClient('', '', $instance->instance_name, 5);
    $id = $client->sendVideo('5511999997777', 'https://cdn.example.com/video.mp4', 'Video de teste', [
        'tenant_id' => $tenant->id,
        'tenant_instance_id' => $instance->id,
        'origin' => 'test_video',
    ]);

    expect($id)->toBe('video-1')
        ->and(WhatsAppMessage::where('tenant_instance_id', $instance->id)->where('media_type', 'video')->where('status', 'sent')->exists())->toBeTrue();

    Http::assertSent(fn ($request) =>
        str_contains((string) $request->url(), 'graph.facebook.com')
        && data_get($request->data(), 'type') === 'video'
        && data_get($request->data(), 'video.link') === 'https://cdn.example.com/video.mp4'
    );
});

it('bloqueia envio direto do whatsapp meta client quando tenant tem opt out', function () {
    [$tenant] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);

    WhatsAppRecipientPreference::create([
        'tenant_id' => $tenant->id,
        'phone' => '5511999991111',
        'phone_hash' => hash('sha256', '5511999991111'),
        'opt_in' => false,
        'opt_out' => true,
        'opt_out_at' => now(),
        'opt_out_reason' => 'manual',
    ]);

    Http::fake();

    $client = new WhatsAppMetaClient('', '', 'global', 5);

    expect(fn () => $client->sendText('5511999991111', 'Mensagem bloqueada', [
        'tenant_id' => $tenant->id,
        'origin' => 'direct_client_test',
    ]))->toThrow(RuntimeException::class, 'recipient_opted_out');

    Http::assertNothingSent();
});

it('registra opt out automatico quando cliente responde palavra de parada', function () {
    [$tenant, $instance] = antibanTenant();

    $preference = app(WhatsAppSafetyService::class)->handleInboundOptOut($instance, '551188887777', 'PARAR', [
        'tenant_id' => $tenant->id,
        'origin' => 'test_inbound',
    ]);

    expect($preference)->not->toBeNull()
        ->and($preference->opt_out)->toBeTrue()
        ->and($preference->opt_out_reason)->toBe('inbound_stop_keyword')
        ->and(WhatsAppRecipientPreference::where('tenant_id', $tenant->id)->where('phone_hash', hash('sha256', '551188887777'))->exists())->toBeTrue();
});

it('permite envios manuais para o mesmo destinatario sem travar em campaign_cooldown_active', function () {
    [$tenant, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 90);

    // Mensagem prévia enviada
    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5511999990001',
        'message' => 'Primeira mensagem manual',
        'direction' => 'out',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511999990001'),
        'message_hash' => hash('sha256', 'primeira mensagem manual'),
        'meta' => ['origin' => 'tenant_manual_send', 'is_manual' => true],
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    $service = app(WhatsAppSafetyService::class);

    // Próximo envio manual para o mesmo número minutos depois
    $manualSend = $service->inspect($instance, '5511999990001', 'Segunda mensagem manual com pix', [
        'origin' => 'tenant_manual_send',
        'is_manual' => true,
    ]);

    expect($manualSend['allowed'])->toBeTrue()
        ->and($manualSend['reason'])->toBe('safe_to_send');
});

it('zera os cooldowns de campanha e destinatario quando o tenant configura modo acelerado', function () {
    [$tenant, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 20);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 90);

    // Tenant configurou modo acelerado com termo aceito
    App\Models\WhatsAppSafetyControl::create([
        'tenant_id' => $tenant->id,
        'tenant_instance_id' => $instance->id,
        'limit_per_day' => 500,
        'limit_per_minute' => 6,
        'limit_per_hour' => 90,
        'meta' => [
            'risk_mode' => 'accelerated',
            'risk_accepted_at' => now()->toIso8601String(),
        ],
    ]);

    // Mensagem recente da mesma campanha
    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5511999990001',
        'message' => 'Mensagem Campanha A',
        'direction' => 'out',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5511999990001'),
        'message_hash' => hash('sha256', 'mensagem campanha a'),
        'meta' => ['campaign' => 'campanha_promocao'],
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    $service = app(WhatsAppSafetyService::class);

    // No modo acelerado, não deve dar campaign_cooldown_active
    $inspection = $service->inspect($instance, '5511999990001', 'Outra mensagem Campanha A', [
        'campaign' => 'campanha_promocao',
    ]);

    expect($inspection['allowed'])->toBeTrue()
        ->and($inspection['reason'])->toBe('safe_to_send');
});

it('envio manual deliberado pelo operador bypassa fora de horario comercial', function () {
    [$tenant, $instance] = antibanTenant();

    config()->set('whatsapp_anti_ban.business_hours.enabled', true);
    config()->set('whatsapp_anti_ban.business_hours.start', '08:00');
    config()->set('whatsapp_anti_ban.business_hours.end', '08:01'); // Janela fechada

    $service = app(WhatsAppSafetyService::class);

    // Envio manual deliberado
    $inspection = $service->inspect($instance, '5511999994444', 'Atendimento pontual', [
        'origin' => 'tenant_manual_send',
        'is_manual' => true,
    ]);

    expect($inspection['allowed'])->toBeTrue()
        ->and($inspection['reason'])->toBe('safe_to_send');
});

