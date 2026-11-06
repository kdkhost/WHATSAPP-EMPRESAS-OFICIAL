<?php

use App\Models\AdminUser;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Support\Facades\Artisan;

it('salva configuracao global anti ban pelo admin e aplica override em config', function () {
    Artisan::call('route:clear');

    $admin = AdminUser::create([
        'name' => 'Admin AntiBan',
        'email' => 'admin-antiban-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $payload = [
        'enabled' => 1,
        'timezone' => 'America/Sao_Paulo',
        'business_hours_enabled' => 1,
        'business_hours_start' => '09:00',
        'business_hours_end' => '18:00',
        'limit_per_minute' => 3,
        'limit_per_hour' => 40,
        'limit_per_day' => 300,
        'recipient_cooldown_minutes' => 25,
        'message_cooldown_minutes' => 55,
        'campaign_cooldown_minutes' => 95,
        'human_delay_enabled' => 1,
        'human_delay_min_seconds' => 7,
        'human_delay_max_seconds' => 22,
        'send_delay_min_ms' => 1300,
        'send_delay_max_ms' => 3900,
        'release_when_limited_seconds' => 420,
        'block_groups' => 1,
        'minimum_message_chars' => 4,
        'stop_keywords' => 'stop, parar, cancelar',
        'warmup_enabled' => 1,
        'warmup_limit_days_1_3' => 31,
        'warmup_limit_days_4_7' => 81,
        'warmup_limit_days_8_15' => 151,
        'risk_medium' => 25,
        'risk_high' => 55,
        'risk_critical' => 85,
        'risk_block_above' => 97,
        'webhook_secret_required' => 1,
        'webhook_max_age_seconds' => 240,
        'api_rate_limit_per_minute' => 44,
    ];

    $this->actingAs($admin, 'admin')
        ->postJson('/admin/whatsapp/safety-config', $payload)
        ->assertOk()
        ->assertJsonPath('ok', true);

    $settings = app(SettingsManager::class);

    expect($settings->get('whatsapp_anti_ban.limits.per_minute'))->toBe(3)
        ->and($settings->get('whatsapp_anti_ban.business_hours.start'))->toBe('09:00')
        ->and($settings->get('whatsapp_anti_ban.cooldowns.campaign_minutes'))->toBe(95)
        ->and($settings->get('whatsapp_anti_ban.stop_keywords'))->toBe(['stop', 'parar', 'cancelar'])
        ->and($settings->get('whatsapp_anti_ban.warmup.daily_limits'))->toBe([3 => 31, 7 => 81, 15 => 151]);

});

it('renderiza painel anti ban WhatsApp com layout premium', function () {
    Artisan::call('route:clear');

    $admin = AdminUser::create([
        'name' => 'Admin Safety UI',
        'email' => 'admin-safety-ui-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.safety'))
        ->assertOk()
        ->assertSee('whatsapp-safety-page', false)
        ->assertSee('safety-metrics-grid', false)
        ->assertSee('safety-config-form', false)
        ->assertSee('save-safety-config', false);
});
