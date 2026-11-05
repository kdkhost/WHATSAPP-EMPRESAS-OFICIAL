<?php

use App\Models\User;
use App\Models\AdminUser;
use App\Models\WhatsAppInstance;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhatsApp\Services\EvolutionClient;
use Illuminate\Support\Facades\Http;

function createGovernanceTenantInstance(?string $instanceName = null): TenantInstance
{
    $plan = Plan::create([
        'name' => 'Plano Governanca Evolution',
        'slug' => 'plano-governanca-evolution-' . uniqid(),
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 3,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Governanca Evolution',
        'email' => 'governanca-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Governanca Evolution',
        'slug' => 'governanca-evolution-' . uniqid(),
        'status' => 'active',
    ]);

    return TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => $instanceName ?: 'governanca_' . $tenant->id,
        'display_name' => 'Instancia Governanca',
        'status' => 'connected',
        'is_default' => true,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'token-local-instancia',
        'webhook_secret' => 'secret-token',
    ]);
}

it('bloqueia endpoints experimentais desativados com retorno padronizado', function () {
    config()->set('whatsapp.features.message_presence', false);
    config()->set('whatsapp.features.message_delete', false);
    config()->set('whatsapp.features.poll_results', false);
    config()->set('whatsapp.features.newsletter', false);
    config()->set('whatsapp.features.community', false);
    config()->set('whatsapp.features.call_reject', false);

    Http::fake();

    $client = new EvolutionClient('https://evo.test', 'token-instancia', 'governanca_bloqueio', 5);

    expect($client->chatPresence('5511999999999'))->toMatchArray([
        'ok' => false,
        'success' => false,
        'error_code' => 'FEATURE_DISABLED',
        'feature' => 'message_presence',
    ]);

    expect($client->deleteMessage('5511999999999@s.whatsapp.net', 'msg-1'))->toMatchArray([
        'error_code' => 'FEATURE_DISABLED',
        'feature' => 'message_delete',
    ]);

    expect($client->getPollResults('poll-1'))->toMatchArray([
        'error_code' => 'FEATURE_DISABLED',
        'feature' => 'poll_results',
    ]);

    expect($client->rejectCall(['id' => 'call-1']))->toMatchArray([
        'error_code' => 'FEATURE_DISABLED',
        'feature' => 'call_reject',
    ]);

    expect($client->createNewsletter('Canal de testes'))->toMatchArray([
        'error_code' => 'FEATURE_DISABLED',
        'feature' => 'newsletter',
    ]);

    expect($client->createCommunity('Comunidade de testes'))->toMatchArray([
        'error_code' => 'FEATURE_DISABLED',
        'feature' => 'community',
    ]);

    Http::assertNothingSent();
});

it('usa global apikey em operacoes administrativas e apikey da instancia nas operacoes da sessao', function () {
    $instance = createGovernanceTenantInstance('governanca_headers');

    config()->set('whatsapp.base_url', 'https://evo.test');
    config()->set('whatsapp.global_api_key', 'token-global-admin');
    config()->set('whatsapp.instance_api_key', 'token-config-instancia');
    config()->set('whatsapp.api_key', 'token-legado');

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['instances' => []], 200),
        'https://evo.test/instance/status' => Http::response(['instance' => ['state' => 'open']], 200),
    ]);

    $client = new EvolutionClient('https://evo.test', 'token-construtor-legado', $instance->instance_name, 5);

    $client->listInstances();
    $client->getStatus();

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/instance/all')
            && $request->hasHeader('apikey', 'token-global-admin')
            && ! $request->hasHeader('Authorization');
    });

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/instance/status')
            && $request->hasHeader('apikey', 'token-construtor-legado')
            && ! $request->hasHeader('Authorization');
    });
});

it('diagnostica evolution sem expor chaves e separa endpoints por tipo de apikey', function () {
    $instance = createGovernanceTenantInstance('governanca_diagnose');

    config()->set('whatsapp.base_url', 'https://evo.test');
    config()->set('whatsapp.global_api_key', 'token-global-diagnose');
    config()->set('whatsapp.instance_api_key', 'token-config-instancia');

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['instance' => ['state' => 'open']], 200),
        'https://evo.test/instance/qr' => Http::response(['qrcode' => ['Code' => '2@codigo']], 200),
        'https://evo.test/instance/all' => Http::response(['data' => [['name' => $instance->instance_name, 'state' => 'open']]], 200),
        'https://evo.test/instance/info/'.$instance->instance_name => Http::response(['instance' => ['name' => $instance->instance_name, 'state' => 'open']], 200),
    ]);

    $client = new EvolutionClient('https://evo.test', 'token-instancia-diagnose', $instance->instance_name, 5);
    $diagnosis = $client->diagnoseConnection();

    expect($diagnosis)
        ->toMatchArray([
            'instance' => $instance->instance_name,
            'instance_configured' => true,
            'admin_configured' => true,
            'has_instance_key' => true,
            'has_global_key' => true,
        ])
        ->and(collect($diagnosis['tests'])->pluck('ok')->all())->toBe([true, true, true, true]);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/instance/status')
            && $request->hasHeader('apikey', 'token-instancia-diagnose')
            && ! str_contains($request->url(), 'apikey=')
            && ! $request->hasHeader('Authorization');
    });

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/instance/all')
            && $request->hasHeader('apikey', 'token-global-diagnose')
            && ! str_contains($request->url(), 'apikey=')
            && ! $request->hasHeader('Authorization');
    });
});

it('expõe diagnostico protegido no painel admin sem retornar segredo', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Diagnose',
        'email' => 'admin-diagnose-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $settings = app(SettingsManager::class);
    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'token-global-secreto');
    $settings->set('whatsapp.instance_api_key', 'token-instancia-secreto');
    $settings->set('whatsapp.instance', 'diagnose_main');
    app()->forgetInstance(EvolutionClient::class);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['instance' => ['state' => 'open']], 200),
        'https://evo.test/instance/qr' => Http::response(['qrcode' => ['Code' => '2@codigo']], 200),
        'https://evo.test/instance/all' => Http::response(['data' => [['name' => 'diagnose_main', 'state' => 'open']]], 200),
        'https://evo.test/instance/info/diagnose_main' => Http::response(['instance' => ['state' => 'open']], 200),
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.diagnose'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.has_instance_key', true)
        ->assertJsonPath('data.has_global_key', true);

    expect($response->getContent())
        ->not->toContain('token-global-secreto')
        ->not->toContain('token-instancia-secreto');
});

it('normaliza status ajax da pagina admin whatsapp instance no formato Evolution v3 data data', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Evolution Instance',
        'email' => 'admin-evolution-instance-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $settings = app(SettingsManager::class);
    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'token-global-admin');
    $settings->set('whatsapp.instance_api_key', 'token-instancia');
    $settings->set('whatsapp.apikey', 'token-instancia');
    $settings->set('whatsapp.instance', 'loja_main');
    $settings->set('whatsapp.timeout', 10, 'int');
    app()->forgetInstance(EvolutionClient::class);

    $qr = str_repeat('A', 320);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [
                'data' => [[
                    'InstanceName' => 'loja_main',
                    'connected' => false,
                    'ownerJid' => '5511999999999@s.whatsapp.net',
                    'profileName' => 'Loja Main',
                    'clientName' => 'Cliente Principal',
                    'qrcode' => ['Code' => $qr],
                ]],
            ],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('state', 'connecting')
        ->assertJsonPath('status_label', 'Aguardando QR Code')
        ->assertJsonPath('number', '5511999999999')
        ->assertJsonPath('name', 'Loja Main')
        ->assertJsonPath('client_name', 'Cliente Principal')
        ->assertJsonPath('qrcode', $qr);

    expect(WhatsAppInstance::query()
        ->where('instance_name', 'loja_main')
        ->where('server_status', 'connecting')
        ->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/instance/all')
            && $request->hasHeader('apikey', 'token-global-admin')
            && ! $request->hasHeader('Authorization');
    });
});
