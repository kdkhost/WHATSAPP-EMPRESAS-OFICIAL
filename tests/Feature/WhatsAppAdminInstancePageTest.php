<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Models\WhatsAppInstance;
use App\Services\EvolutionGoSyncService;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Support\Facades\Http;

function adminWhatsappUser(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin WhatsApp',
        'email' => 'admin-whatsapp-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);
}

function adminWhatsappTenantInstance(): TenantInstance
{
    $plan = Plan::create([
        'name' => 'Plano WhatsApp Admin',
        'slug' => 'plano-whatsapp-admin-'.uniqid(),
        'price' => 0,
        'extra_instance_price' => 39.90,
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
        'name' => 'Tenant WhatsApp Admin',
        'email' => 'tenant-whatsapp-admin-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Cliente WhatsApp',
        'slug' => 'cliente-whatsapp-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    return TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'cliente_whatsapp_'.$tenant->id,
        'display_name' => 'Atendimento',
        'status' => 'creating',
        'is_default' => true,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
        'webhook_enabled' => true,
        'webhook_by_events' => true,
        'webhook_base64' => false,
        'webhook_events' => ['CONNECTION_UPDATE'],
    ]);
}

it('renderiza a pagina admin de instancia whatsapp com os controles principais', function () {
    $admin = adminWhatsappUser();
    adminWhatsappTenantInstance();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.instance'))
        ->assertOk()
        ->assertSee('frm-whatsapp-config', false)
        ->assertSee('btn-connect', false)
        ->assertSee('btn-disconnect', false)
        ->assertSee('wa-webhook-url-display', false)
        ->assertSee('wa-btn-copy-webhook', false)
        ->assertSee('wa-webhook-status-url', false)
        ->assertSee('wa-basic-config-grid', false)
        ->assertSee('wa-form-actions', false)
        ->assertSee('js-sync-tenant-instance', false)
        ->assertSee('modalInstanceConfig', false);
});

it('valida imagem do qr antes de exibir e cai para canvas quando falhar', function () {
    $admin = adminWhatsappUser();
    adminWhatsappTenantInstance();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.instance'))
        ->assertOk()
        ->assertSee('function renderQrImage', false)
        ->assertSee('img.onerror = function', false)
        ->assertSee('renderQrCanvas(box, fallbackValue)', false)
        ->assertDontSee("box.innerHTML = '<img", false);
});

it('exibe configuracao recomendada evolution go e controla qr dinamico na pagina admin', function () {
    $admin = adminWhatsappUser();
    adminWhatsappTenantInstance();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.instance'))
        ->assertOk()
        ->assertSee('wa-btn-apply-recommended', false)
        ->assertSee('QRCODE_UPDATED', false)
        ->assertSee('CONNECTION_UPDATE', false)
        ->assertSee('MESSAGES_UPSERT', false)
        ->assertSee('MESSAGES_DELETE', false)
        ->assertSee('rejectCallsMessage', false)
        ->assertSee('let waQrRefreshInterval', false)
        ->assertSee('let waStatusPollInterval', false)
        ->assertSee('function updateWebhookConfigUi', false)
        ->assertSee('function startQrAutoRefresh', false)
        ->assertSee('connectWhatsAppInstance(false)', false)
        ->assertSee('60000', false)
        ->assertSee('10000', false);
});

it('consulta status da instancia principal usando fallback de config e payload connected loggedin', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['data' => ['Connected' => true, 'LoggedIn' => true, 'Name' => 'Atendimento']], 200),
        'https://evo.test/instance/info/principal' => Http::response(['instance' => [
            'name' => 'principal',
            'ownerJid' => '5521981325441@s.whatsapp.net',
            'profileName' => 'Atendimento',
            'clientName' => 'Cliente Principal',
            'createdAt' => '2026-05-29T10:00:00Z',
        ]], 200),
        'https://evo.test/instance/all' => Http::response(['data' => [[
            'name' => 'principal',
            'owner' => '5521981325441@s.whatsapp.net',
            'profileName' => 'Atendimento',
            'createdAt' => '2026-05-29T10:00:00Z',
        ]]], 200),
        'https://evo.test/user/avatar' => Http::response(['url' => 'https://cdn.test/avatar.jpg'], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('state', 'open')
        ->assertJsonPath('number', '5521981325441')
        ->assertJsonPath('name', 'Atendimento')
        ->assertJsonPath('client_name', 'Cliente Principal')
        ->assertJsonPath('jid', '5521981325441@s.whatsapp.net')
        ->assertJsonPath('avatar', 'https://cdn.test/avatar.jpg');

    $row = WhatsAppInstance::query()
        ->whereNull('tenant_id')
        ->whereNull('tenant_instance_id')
        ->where('instance_name', 'principal')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->connected)->toBeTrue()
        ->and($row->logged_in)->toBeTrue()
        ->and($row->phone)->toBe('5521981325441')
        ->and($row->profile_name)->toBe('Atendimento')
        ->and($row->client_name)->toBe('Cliente Principal')
        ->and($row->jid)->toBe('5521981325441@s.whatsapp.net')
        ->and($row->server_status)->toBe('open')
        ->and(data_get($row->server_payload, 'display._source'))->toContain('evolution_status');
});

it('atualiza status principal com dados detalhados do endpoint instance info v3', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal-info',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['state' => 'close'], 200),
        'https://evo.test/instance/info/principal-info' => Http::response(['instance' => [
            'name' => 'principal-info',
            'ownerJid' => '5521981325441@s.whatsapp.net',
            'profileName' => 'Atendimento Info',
            'clientName' => 'Cliente Info',
            'createdAt' => '2026-05-28T09:30:00Z',
            'state' => 'close',
        ]], 200),
        'https://evo.test/instance/all' => Http::response(['data' => []], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('state', 'close')
        ->assertJsonPath('number', '5521981325441')
        ->assertJsonPath('name', 'Atendimento Info')
        ->assertJsonPath('client_name', 'Cliente Info')
        ->assertJsonPath('jid', '5521981325441@s.whatsapp.net')
        ->assertJsonPath('raw_state', 'close');

    $row = WhatsAppInstance::where('instance_name', 'principal-info')->first();

    expect($row)->not->toBeNull()
        ->and($row->phone)->toBe('5521981325441')
        ->and($row->profile_name)->toBe('Atendimento Info')
        ->and($row->client_name)->toBe('Cliente Info')
        ->and($row->server_status)->toBe('close');
});

it('continua enriquecendo o status com instance info quando status retorna erro', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal-info',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['message' => 'status indisponivel'], 500),
        'https://evo.test/instance/info/principal-info' => Http::response(['instance' => [
            'name' => 'principal-info',
            'ownerJid' => '5521981325441@s.whatsapp.net',
            'profileName' => 'Atendimento Info',
            'clientName' => 'Cliente Info',
            'createdAt' => '2026-05-28T09:30:00Z',
        ]], 200),
        'https://evo.test/instance/all' => Http::response(['data' => []], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('number', '5521981325441')
        ->assertJsonPath('name', 'Atendimento Info')
        ->assertJsonPath('client_name', 'Cliente Info')
        ->assertJsonPath('message', 'status indisponivel');
});

it('renderiza status persistido da instancia principal ao recarregar a pagina', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.instance' => 'principal-cache',
    ]);

    WhatsAppInstance::create([
        'instance_name' => 'principal-cache',
        'phone' => '5521981325441',
        'profile_name' => 'Atendimento Cache',
        'client_name' => 'Cliente Cache',
        'connected' => true,
        'logged_in' => true,
        'server_status' => 'open',
        'last_sync_at' => now(),
        'server_payload' => [
            'display' => [
                'created_at' => now()->subDay()->toIso8601String(),
                '_source' => 'test_cache',
            ],
        ],
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.instance'))
        ->assertOk()
        ->assertSee('type="button" class="btn btn-outline-info" id="btn-refresh-status"', false)
        ->assertSee('info-jid', false)
        ->assertSee('info-source', false)
        ->assertSee('Conectado')
        ->assertSee('Atendimento Cache')
        ->assertSee('Cliente Cache')
        ->assertSee('principal-cache')
        ->assertSee('98132-5441');
});

it('retorna cache persistido quando a evolution falha na consulta de status', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.instance' => 'principal-cache',
    ]);

    WhatsAppInstance::create([
        'instance_name' => 'principal-cache',
        'phone' => '5521981325441',
        'profile_name' => 'Atendimento Cache',
        'client_name' => 'Cliente Cache',
        'connected' => true,
        'logged_in' => true,
        'server_status' => 'open',
        'last_sync_at' => now()->subMinute(),
        'server_payload' => [
            'display' => [
                'created_at' => now()->subDay()->toIso8601String(),
                '_source' => 'test_cache',
            ],
        ],
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['message' => 'Evolution indisponivel'], 500),
        'https://evo.test/instance/info/principal-cache' => Http::response(['message' => 'not found'], 404),
        'https://evo.test/instance/all' => Http::response(['data' => []], 404),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('state', 'open')
        ->assertJsonPath('number', '5521981325441')
        ->assertJsonPath('name', 'Atendimento Cache')
        ->assertJsonPath('client_name', 'Cliente Cache')
        ->assertJsonPath('_source', 'local_cache')
        ->assertJsonPath('message', 'Evolution indisponivel');
});

it('remove qr persistido quando a instancia principal fica conectada', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'principal',
        'phone' => '5521981900390',
        'profile_name' => 'GMOP',
        'client_name' => 'KDKHost',
        'connected' => false,
        'logged_in' => false,
        'server_status' => 'connecting',
        'last_sync_at' => now()->subMinute(),
        'last_qr_at' => now()->subMinutes(10),
        'server_payload' => [
            'display' => [
                'state' => 'connecting',
                'status' => 'connecting',
                'status_label' => 'Aguardando QR Code',
                'qrcode' => 'data:image/png;base64,qr-antigo',
                '_source' => 'instance_qr',
            ],
        ],
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'principal',
                'connected' => true,
                'jid' => '5521981900390@s.whatsapp.net',
                'profileName' => 'GMOP',
                'clientName' => 'KDKHost',
                'qrcode' => ['base64' => 'data:image/png;base64,qr-antigo'],
            ]],
        ], 200),
        'https://evo.test/user/avatar' => Http::response(['url' => 'https://cdn.test/avatar.jpg'], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('state', 'open')
        ->assertJsonPath('connected', true)
        ->assertJsonPath('qrcode', null);

    $row = WhatsAppInstance::where('instance_name', 'principal')->first();

    expect($row)->not->toBeNull()
        ->and($row->connected)->toBeTrue()
        ->and($row->server_status)->toBe('open')
        ->and(data_get($row->server_payload, 'display.qrcode'))->toBeNull();
});

it('remove qr persistido quando evolution confirma instancia desconectada sem qr novo', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'principal',
        'phone' => '5521981900390',
        'connected' => false,
        'logged_in' => false,
        'server_status' => 'connecting',
        'last_sync_at' => now()->subMinute(),
        'last_qr_at' => now()->subMinutes(10),
        'server_payload' => [
            'display' => [
                'state' => 'connecting',
                'status' => 'connecting',
                'status_label' => 'Aguardando QR Code',
                'qrcode' => 'data:image/png;base64,qr-antigo',
                '_source' => 'instance_qr',
            ],
        ],
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'principal',
                'connected' => false,
                'jid' => '5521981900390@s.whatsapp.net',
                'profileName' => 'GMOP',
                'clientName' => 'KDKHost',
                'state' => 'close',
            ]],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('state', 'close')
        ->assertJsonPath('qrcode', null);

    $row = WhatsAppInstance::where('instance_name', 'principal')->first();

    expect(data_get($row->server_payload, 'display.qrcode'))->toBeNull();
});

it('mantem qr recente fixado quando status remoto oscila sem qr novo', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'principal',
        'phone' => '5521981900390',
        'connected' => false,
        'logged_in' => false,
        'server_status' => 'connecting',
        'last_sync_at' => now()->subSeconds(20),
        'last_qr_at' => now()->subSeconds(20),
        'server_payload' => [
            'display' => [
                'state' => 'connecting',
                'status' => 'connecting',
                'status_label' => 'Aguardando QR Code',
                'qrcode' => '2@codigo-recente-da-evolution',
                '_source' => 'instance_qr',
            ],
        ],
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'principal',
                'connected' => false,
                'jid' => '5521981900390@s.whatsapp.net',
                'state' => 'close',
            ]],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('state', 'connecting')
        ->assertJsonPath('status_label', 'Aguardando QR Code')
        ->assertJsonPath('qrcode', '2@codigo-recente-da-evolution');

    $row = WhatsAppInstance::where('instance_name', 'principal')->first();

    expect(data_get($row->server_payload, 'display.qrcode'))->toBe('2@codigo-recente-da-evolution');
});

it('nao marca admin como conectado quando evolution retorna open sem numero ou jid', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'principal',
                'connected' => true,
                'state' => 'open',
                'qrcode' => ['base64' => 'data:image/png;base64,qr-sem-identidade'],
            ]],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('connected', false)
        ->assertJsonPath('state', 'connecting')
        ->assertJsonPath('status_label', 'Aguardando QR Code')
        ->assertJsonPath('qrcode', 'data:image/png;base64,qr-sem-identidade');
});

it('substitui imagem de qr da listagem pelo code textual do endpoint qr', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'principal',
                'state' => 'qrcode',
                'qrcode' => ['base64' => 'data:image/png;base64,imagem-da-listagem'],
            ]],
        ], 200),
        'https://evo.test/instance/qr' => Http::response([
            'data' => [
                'Qrcode' => 'data:image/png;base64,imagem-densa',
                'Code' => '2@codigo-textual-da-evolution',
            ],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('connected', false)
        ->assertJsonPath('qrcode', '2@codigo-textual-da-evolution');
});

it('bloqueia conexao quando o mesmo numero ja esta conectado em outra instancia', function () {
    $admin = adminWhatsappUser();
    $connectCalled = false;

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'principal',
        'phone' => '5521981900390',
        'connected' => false,
        'logged_in' => false,
        'server_status' => 'close',
    ]);

    Http::fake(function ($request) use (&$connectCalled) {
        $url = (string) $request->url();
        $method = $request->method();

        if ($method === 'GET' && str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => [[
                    'name' => 'principal',
                    'connected' => false,
                    'jid' => '5521981900390@s.whatsapp.net',
                    'profileName' => 'GMOP',
                ], [
                    'name' => 'outra-instancia',
                    'connected' => true,
                    'jid' => '5521981900390@s.whatsapp.net',
                    'profileName' => 'GMOP',
                ]],
            ], 200);
        }

        if ($method === 'POST' && str_ends_with($url, '/instance/connect')) {
            $connectCalled = true;

            return Http::response(['message' => 'nao deveria chamar'], 500);
        }

        return Http::response(['message' => 'not found'], 404);
    });

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', 'Este número já está conectado na instância outra-instancia.');

    expect($connectCalled)->toBeFalse();
});

it('mantem as chaves salvas no formulario principal e preserva valores em branco ao salvar', function () {
    $admin = adminWhatsappUser();
    $settings = app(SettingsManager::class);

    $settings->set('whatsapp.base_url', 'https://api.kdkhost.com.br');
    $settings->set('whatsapp.global_api_key', 'global-token-existente');
    $settings->set('whatsapp.instance_api_key', 'instance-token-existente');
    $settings->set('whatsapp.api_key', 'instance-token-existente');
    $settings->set('whatsapp.instance', 'testandoq');
    $settings->set('whatsapp.timeout', 15, 'int');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.instance'))
        ->assertOk()
        ->assertSee('value="global-token-existente"', false)
        ->assertSee('value="instance-token-existente"', false)
        ->assertSee('value="https://api.kdkhost.com.br"', false);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.config'), [
            'base_url' => 'https://api.kdkhost.com.br',
            'global_api_key' => '',
            'api_key' => '',
            'instance' => 'testandoq',
            'timeout' => 20,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($settings->get('whatsapp.global_api_key'))->toBe('global-token-existente')
        ->and($settings->get('whatsapp.global_apikey'))->toBe('global-token-existente')
        ->and($settings->get('whatsapp.instance_api_key'))->toBe('instance-token-existente')
        ->and($settings->get('whatsapp.apikey'))->toBe('instance-token-existente')
        ->and($settings->get('whatsapp.api_key'))->toBe('instance-token-existente')
        ->and((int) $settings->get('whatsapp.timeout'))->toBe(20);
});

it('salva e aplica configuracao recomendada da instancia principal na evolution', function () {
    $admin = adminWhatsappUser();
    $settings = app(SettingsManager::class);

    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'global-token-existente');
    $settings->set('whatsapp.instance_api_key', 'instance-token-existente');
    $settings->set('whatsapp.api_key', 'instance-token-existente');
    $settings->set('whatsapp.instance', 'principal');

    Http::fake([
        'https://evo.test/instance/principal/advanced-settings' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'webhook configurado'], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.config'), [
            'base_url' => 'https://evo.test',
            'global_api_key' => '',
            'api_key' => '',
            'instance' => 'principal',
            'timeout' => 15,
            'webhook_enabled' => true,
            'webhook_by_events' => true,
            'webhook_base64' => false,
            'webhook_events' => ['QRCODE_UPDATED', 'CONNECTION_UPDATE', 'MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'SEND_MESSAGE', 'CALL'],
            'event_producer_rabbitmq' => 'enabled',
            'event_producer_websocket' => 'disabled',
            'event_producer_nats' => 'default',
            'rejectCalls' => true,
            'rejectCallsMessage' => 'Atendimento somente por mensagem.',
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => false,
            'ignoreStatus' => true,
            'readStatus' => false,
            'syncFullHistory' => false,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($settings->get('whatsapp.webhook_enabled'))->toBeTrue()
        ->and($settings->get('whatsapp.webhook_events'))->toBe(['QRCODE_UPDATED', 'CONNECTION_UPDATE', 'MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'SEND_MESSAGE', 'CALL'])
        ->and($settings->get('whatsapp.event_producers'))->toBe([
            'rabbitmq' => 'enabled',
            'websocket' => 'disabled',
            'nats' => 'default',
        ])
        ->and(data_get($settings->get('whatsapp.advanced_settings'), 'rejectCalls'))->toBeTrue()
        ->and(data_get($settings->get('whatsapp.advanced_settings'), 'alwaysOnline'))->toBeFalse()
        ->and(data_get($settings->get('whatsapp.advanced_settings'), 'ignoreStatus'))->toBeTrue()
        ->and(data_get($settings->get('whatsapp.advanced_settings'), 'readStatus'))->toBeFalse();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/principal/advanced-settings')
        && $request['rejectCalls'] === true
        && $request['alwaysOnline'] === false
        && $request['readStatus'] === false);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && str_contains((string) $request['webhookUrl'], '/webhooks/evolution/messages')
        && in_array('QRCODE', $request['subscribe'], true)
        && in_array('CONNECTION', $request['subscribe'], true)
        && in_array('MESSAGE', $request['subscribe'], true)
        && in_array('SEND_MESSAGE', $request['subscribe'], true)
        && in_array('CALL', $request['subscribe'], true)
        && $request['rabbitmqEnable'] === 'true'
        && $request['websocketEnable'] === 'false'
        && $request['natsEnable'] === 'false');
});

it('envia subscribe ALL quando o admin desliga a separacao por eventos', function () {
    $admin = adminWhatsappUser();
    $settings = app(SettingsManager::class);

    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'global-token-existente');
    $settings->set('whatsapp.instance_api_key', 'instance-token-existente');
    $settings->set('whatsapp.api_key', 'instance-token-existente');
    $settings->set('whatsapp.instance', 'principal');

    Http::fake([
        'https://evo.test/instance/principal/advanced-settings' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'webhook configurado'], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.config'), [
            'base_url' => 'https://evo.test',
            'global_api_key' => '',
            'api_key' => '',
            'instance' => 'principal',
            'timeout' => 15,
            'webhook_enabled' => true,
            'webhook_by_events' => false,
            'webhook_base64' => false,
            'webhook_events' => ['QRCODE_UPDATED'],
            'rejectCalls' => true,
            'rejectCallsMessage' => 'Atendimento somente por mensagem.',
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => true,
            'syncFullHistory' => false,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && $request['subscribe'] === ['ALL']);
});

it('gera qr code no endpoint admin mesmo quando a evolution retorna qrcode aninhado', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['state' => 'close'], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/qr' => Http::response(['qrcode' => ['base64' => 'data:image/png;base64,qr-admin']], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertOk()
        ->assertJsonPath('qrcode', 'data:image/png;base64,qr-admin');
});

it('prioriza code textual da evolution ao gerar qr code do admin', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['state' => 'close'], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/qr' => Http::response([
            'data' => [
                'data' => [
                    'Qrcode' => 'data:image/png;base64,imagem-ruim',
                    'Code' => '2@codigo-textual-correto-da-evolution',
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertOk()
        ->assertJsonPath('qrcode', '2@codigo-textual-correto-da-evolution');
});

it('gera qr code no admin com connect minimo quando webhook da evolution falha', function () {
    $admin = adminWhatsappUser();
    $connectAttempts = 0;

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake(function ($request) use (&$connectAttempts) {
        $url = (string) $request->url();

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['state' => 'close'], 200);
        }

        if (str_ends_with($url, '/instance/connect')) {
            $connectAttempts++;

            if (filled($request['webhookUrl'] ?? null)) {
                return Http::response(['message' => 'invalid webhook subscribe'], 422);
            }

            return Http::response(['qrcode' => ['base64' => 'data:image/png;base64,qr-minimo']], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertOk()
        ->assertJsonPath('qrcode', 'data:image/png;base64,qr-minimo');

    expect($connectAttempts)->toBe(2);
});

it('cria instancia principal ausente na evolution e salva o token antes de gerar qr', function () {
    $admin = adminWhatsappUser();
    $settings = app(SettingsManager::class);

    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'global-token');
    $settings->set('whatsapp.instance', 'testandoq');
    $settings->set('whatsapp.instance_api_key', 'token-antigo');
    $settings->set('whatsapp.api_key', 'token-antigo');

    Http::fake(function ($request) {
        $url = (string) $request->url();

        if (str_ends_with($url, '/instance/all')) {
            return Http::response(['data' => []], 200);
        }

        if (str_ends_with($url, '/instance/create')) {
            return Http::response([
                'instance' => ['id' => 'uuid-admin', 'name' => 'testandoq'],
                'qrcode' => ['base64' => 'data:image/png;base64,qr-create-admin'],
                'token' => $request['token'],
            ], 201);
        }

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['state' => 'close'], 200);
        }

        if (str_ends_with($url, '/instance/connect')) {
            return Http::response(['message' => 'success'], 200);
        }

        if (str_ends_with($url, '/instance/qr')) {
            return Http::response(['qrcode' => ['base64' => 'data:image/png;base64,qr-after-create']], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertOk()
        ->assertJsonPath('qrcode', 'data:image/png;base64,qr-create-admin');

    expect($settings->get('whatsapp.instance_api_key'))->not->toBe('token-antigo');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && $request['name'] === 'testandoq'
        && filled($request['token']));
});

it('aguarda alguns ciclos apos connect antes de desistir do qr code da instancia principal', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.instance' => 'principal',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::sequence()
            ->push(['state' => 'close'], 200)
            ->push(['state' => 'close'], 200)
            ->push(['state' => 'close'], 200),
        'https://evo.test/instance/connect' => Http::response([
            'data' => ['jid' => '5521981900390:25@s.whatsapp.net'],
            'message' => 'success',
        ], 200),
        'https://evo.test/instance/qr' => Http::sequence()
            ->push(['error' => 'client disconnected'], 400)
            ->push(['qrcode' => ['base64' => 'data:image/png;base64,qr-posterior']], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertOk()
        ->assertJsonPath('qrcode', 'data:image/png;base64,qr-posterior')
        ->assertJsonPath('status_label', 'Aguardando QR Code');
});

it('nao deleta nem recria instancia remota quando connect ainda nao devolve qr', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'principal',
        'phone' => '5521981900390',
        'profile_name' => 'GMOP',
        'client_name' => 'KDKHost',
        'connected' => false,
        'logged_in' => false,
        'server_status' => 'close',
    ]);

    Http::fake(function ($request) {
        $url = (string) $request->url();
        $method = $request->method();

        if ($method === 'GET' && str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => [[
                    'id' => '3e365e9a-9418-425b-8fcf-5f02527a2bf3',
                    'name' => 'principal',
                    'connected' => false,
                    'jid' => '5521981900390:25@s.whatsapp.net',
                    'profileName' => 'GMOP',
                    'client_name' => 'KDKHost',
                    'createdAt' => '2026-05-17T14:39:03-03:00',
                ]],
            ], 200);
        }

        if ($method === 'POST' && str_ends_with($url, '/instance/connect')) {
            return Http::response(['data' => ['jid' => '5521981900390:25@s.whatsapp.net'], 'message' => 'success'], 200);
        }

        if ($method === 'GET' && str_ends_with($url, '/instance/qr')) {
            return Http::response(['error' => 'client disconnected'], 400);
        }

        return Http::response(['error' => 'unexpected '.$method.' '.$url], 500);
    });

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.connect'))
        ->assertOk()
        ->assertJsonPath('qrcode', null)
        ->assertJsonPath('status_label', 'Aguardando QR Code');

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/instance/delete/'));
    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/instance/create'));
});

it('preserva sessao conectada quando atualizar status recebe qr instavel da evolution', function () {
    $admin = adminWhatsappUser();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'instance-token',
        'whatsapp.global_api_key' => 'global-token',
        'whatsapp.instance' => 'principal',
    ]);

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'principal',
        'phone' => '5521981900390',
        'profile_name' => 'GMOP',
        'client_name' => 'KDKHost',
        'connected' => true,
        'logged_in' => true,
        'server_status' => 'open',
        'last_sync_at' => now(),
        'server_payload' => [
            'display' => [
                'state' => 'open',
                'status' => 'connected',
                'status_label' => 'Conectado',
                'connected' => true,
                'number' => '5521981900390',
                'name' => 'GMOP',
                'client_name' => 'KDKHost',
                'instance' => 'principal',
            ],
        ],
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'id' => '3e365e9a-9418-425b-8fcf-5f02527a2bf3',
                'name' => 'principal',
                'connected' => false,
                'jid' => '5521981900390:25@s.whatsapp.net',
                'qrcode' => ['Code' => '2@codigo-instavel-da-evolution'],
            ]],
        ], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.status'))
        ->assertOk()
        ->assertJsonPath('connected', true)
        ->assertJsonPath('state', 'open')
        ->assertJsonPath('status_label', 'Conectado')
        ->assertJsonPath('qrcode', null)
        ->assertJsonPath('_source', 'local_cache');
});

it('remove qr persistido quando sync remoto confirma instancia principal conectada', function () {
    $settings = app(SettingsManager::class);
    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'global-token');
    $settings->set('whatsapp.instance', 'testandoq');

    WhatsAppInstance::create([
        'tenant_id' => null,
        'tenant_instance_id' => null,
        'instance_name' => 'testandoq',
        'phone' => '5521981900390',
        'profile_name' => 'GMOP',
        'client_name' => 'KDKHost',
        'connected' => false,
        'logged_in' => false,
        'server_status' => 'close',
        'server_payload' => [
            'display' => [
                'state' => 'connecting',
                'status' => 'connecting',
                'status_label' => 'Aguardando QR Code',
                'connected' => false,
                'number' => '5521981900390',
                'name' => 'GMOP',
                'client_name' => 'KDKHost',
                'instance' => 'testandoq',
                'qrcode' => 'data:image/png;base64,qr-persistido',
                '_source' => 'instance_qr',
            ],
        ],
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'testandoq',
                'connected' => true,
                'owner' => '5521981900390@s.whatsapp.net',
                'profileName' => 'GMOP',
                'clientName' => 'KDKHost',
            ]],
        ], 200),
    ]);

    app(EvolutionGoSyncService::class)->syncAll();

    $row = WhatsAppInstance::whereNull('tenant_id')
        ->where('instance_name', 'testandoq')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->connected)->toBeTrue()
        ->and($row->logged_in)->toBeTrue()
        ->and($row->server_status)->toBe('open')
        ->and(data_get($row->server_payload, 'display.qrcode'))->toBeNull()
        ->and(data_get($row->server_payload, 'display.status_label'))->toBe('Conectado');
});

it('promove qr retornado em instance all para o display persistido da instancia principal', function () {
    $settings = app(SettingsManager::class);
    $settings->set('whatsapp.base_url', 'https://evo.test');
    $settings->set('whatsapp.global_api_key', 'global-token');
    $settings->set('whatsapp.instance', 'testandoq');

    Http::fake([
        'https://evo.test/instance/all' => Http::response([
            'data' => [[
                'name' => 'testandoq',
                'connected' => false,
                'qrcode' => ['base64' => 'data:image/png;base64,qr-remoto'],
                'owner' => '5521981900390@s.whatsapp.net',
                'profileName' => 'GMOP',
                'clientName' => 'KDKHost',
            ]],
        ], 200),
    ]);

    app(EvolutionGoSyncService::class)->syncAll();

    $row = WhatsAppInstance::whereNull('tenant_id')
        ->where('instance_name', 'testandoq')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->connected)->toBeFalse()
        ->and($row->server_status)->toBe('connecting')
        ->and(data_get($row->server_payload, 'display.qrcode'))->toBe('data:image/png;base64,qr-remoto')
        ->and(data_get($row->server_payload, 'display.status_label'))->toBe('Aguardando QR Code');
});

it('salva configuracao evolution da instancia tenant pelo painel admin', function () {
    $admin = adminWhatsappUser();
    $instance = adminWhatsappTenantInstance();

    Http::fake([
        'https://evo.test/settings/set/*' => Http::response(['settings' => ['ok' => true]], 201),
        'https://evo.test/webhook/set/*' => Http::response(['webhook' => ['ok' => true]], 201),
    ]);

    $payload = [
        'api_base_url' => 'https://evo.test',
        'api_key' => 'novo-token-instancia',
        'webhook_enabled' => true,
        'webhook_by_events' => true,
        'webhook_base64' => false,
        'webhook_events' => ['CONNECTION_UPDATE', 'MESSAGES_UPSERT'],
        'reject_call' => true,
        'msg_call' => 'Atendimento somente por mensagem.',
        'groups_ignore' => true,
        'always_online' => true,
        'read_messages' => true,
        'read_status' => true,
        'sync_full_history' => false,
    ];

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whatsapp.instances.config', $instance->id), $payload)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('applied', true)
        ->assertJsonPath('settings.readMessages', true);

    expect($instance->refresh()->api_key)->toBe('novo-token-instancia')
        ->and($instance->webhook_events)->toBe(['CONNECTION_UPDATE', 'MESSAGES_UPSERT']);
});
