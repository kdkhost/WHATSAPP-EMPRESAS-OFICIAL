<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Models\TenantInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function createTenantWithEvolutionInstance(): array
{
    $plan = Plan::create([
        'name' => 'Plano Evolution',
        'slug' => 'plano-evolution-'.uniqid(),
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
        'name' => 'Tenant Evolution',
        'email' => 'tenant-evolution-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Loja Premium',
        'slug' => 'loja-premium-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'loja_premium_'.$tenant->id,
        'display_name' => 'Atendimento Loja',
        'status' => 'creating',
        'is_default' => true,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);

    return [$user, $tenant, $instance];
}

it('mantem o botao do estado vazio de instancias no padrao compacto do painel', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();
    $instance->delete();

    $this->actingAs($user)
        ->get(route('tenant.instances'))
        ->assertOk()
        ->assertSee('empty-state__action', false)
        ->assertSee('btn btn-primary btn-sm empty-state__action', false)
        ->assertSee('.empty-state > i', false)
        ->assertDontSee('.empty-state i { font-size: 3rem', false);
});

it('exibe ajuda contextual e botao de configuracao recomendada na modal do tenant', function () {
    [$user] = createTenantWithEvolutionInstance();

    $this->actingAs($user)
        ->get(route('tenant.instances'))
        ->assertOk()
        ->assertSee('Aplicar configuração recomendada')
        ->assertSee('QR Code atualizado')
        ->assertSee('data-bs-toggle="tooltip"', false)
        ->assertSee('Atualiza o QR Code de conexão no painel.')
        ->assertSee('Status da conexão')
        ->assertSee('Rejeitar chamadas')
        ->assertSee('Evita que o sistema responda mensagens em grupos.')
        ->assertSee('Mensagem ao rejeitar chamada');
});

it('sincroniza avatar nome slogan numero e status da instancia evolution', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_contains($url, '/instance/connectionState/')) {
            return Http::response(['instance' => ['state' => 'open']], 200);
        }

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['data' => ['Connected' => true, 'LoggedIn' => true, 'Name' => 'Loja Premium']], 200);
        }

        if (str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => [[
                    'name' => $instance->instance_name,
                    'jid' => '5511999999999@s.whatsapp.net',
                    'client_name' => 'Loja Premium',
                ]],
            ], 200);
        }

        if (str_contains($url, '/chat/fetchProfile/')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/user/info')) {
            return Http::response([
                'data' => [
                    'users' => [[
                        'jid' => '5511999999999@s.whatsapp.net',
                        'number' => '5511999999999',
                        'pushName' => 'Loja Premium',
                        'fullName' => 'Loja Premium Atendimento',
                        'businessName' => 'Loja Premium',
                        'isBusiness' => true,
                        'status' => 'Atendimento premium no WhatsApp',
                        'pictureId' => 'pic-123',
                        'profilePicUrl' => 'https://cdn.test/avatar-json.jpg',
                        'devices' => [
                            '5511999999999@s.whatsapp.net',
                            '5511999999999:5@s.whatsapp.net',
                        ],
                        'lid' => '123456789@lid',
                        'contactFound' => true,
                    ]],
                ],
                'message' => 'success',
            ], 200);
        }

        if (str_contains($url, '/chat/fetchProfilePictureUrl/')) {
            return Http::response(['profilePictureUrl' => 'https://cdn.test/avatar.jpg'], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->post(route('tenant.instances.sync', $instance->id))
        ->assertOk()
        ->assertJsonPath('instance.profile_name', 'Loja Premium')
        ->assertJsonPath('instance.profile_business_name', 'Loja Premium')
        ->assertJsonPath('instance.profile_full_name', 'Loja Premium Atendimento')
        ->assertJsonPath('instance.profile_is_business', true)
        ->assertJsonPath('instance.profile_avatar_url', 'https://cdn.test/avatar-json.jpg')
        ->assertJsonPath('instance.profile_status', 'Atendimento premium no WhatsApp')
        ->assertJsonPath('instance.profile_lid', '123456789@lid')
        ->assertJsonPath('instance.profile_devices.1', '5511999999999:5@s.whatsapp.net')
        ->assertJsonPath('instance.profile_contact_found', true);

    $instance->refresh();

    expect($instance->status)->toBe('connected')
        ->and($instance->profile_number)->toBe('5511999999999')
        ->and($instance->profile_jid)->toBe('5511999999999@s.whatsapp.net')
        ->and($instance->profile_avatar_url)->toBe('https://cdn.test/avatar-json.jpg')
        ->and($instance->profile_business_name)->toBe('Loja Premium')
        ->and($instance->profile_full_name)->toBe('Loja Premium Atendimento')
        ->and($instance->profile_is_business)->toBeTrue()
        ->and($instance->profile_lid)->toBe('123456789@lid')
        ->and($instance->profile_devices)->toHaveCount(2)
        ->and($instance->profile_contact_found)->toBeTrue()
        ->and($instance->tenant_id)->toBe($tenant->id);
});

it('nao cria instancia local quando a evolution recusa a criacao', function () {
    [$user, $tenant] = createTenantWithEvolutionInstance();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/create' => Http::response(['message' => 'token invalido'], 500),
    ]);

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Loja Extra'])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Falha ao criar instancia no servidor Evolution: token invalido');

    expect($tenant->instances()->count())->toBe(1);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && filled($request['name'])
        && $request['name'] === $request['instanceName']
        && filled($request['token']));
});

it('exige confirmacao de compra extra e gera cobranca prorata agrupada', function () {
    [$user, $tenant] = createTenantWithEvolutionInstance();
    $tenant->plan->update([
        'max_instances' => 1,
        'extra_instance_price' => 39.90,
    ]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/create' => Http::response([
            'instance' => ['id' => 'f2f0c590-31c3-4b66-a4e0-71548f4760f7', 'instanceName' => 'loja_extra'],
            'qrcode' => ['base64' => 'data:image/png;base64,abc'],
            'hash' => 'token-extra',
        ], 201),
    ]);

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Loja Extra'])
        ->assertStatus(422)
        ->assertJsonPath('requires_purchase', true)
        ->assertJsonStructure(['quote' => ['recurring_amount', 'prorata_amount', 'due_date']]);

    expect($tenant->instances()->count())->toBe(1);

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Loja Extra', 'purchase_extra' => true])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('requires_purchase', true)
        ->assertJsonStructure(['invoice' => ['id', 'amount', 'meta']]);

    $extra = $tenant->instances()->where('is_extra_billable', true)->first();
    $invoice = TenantInvoice::where('tenant_id', $tenant->id)->where('status', 'pending')->first();

    expect($extra)->not->toBeNull()
        ->and((float) $extra->recurring_amount)->toBe(39.90)
        ->and($extra->billing_started_at)->not->toBeNull()
        ->and(data_get($extra->meta, 'evolution_remote.id'))->toBe('f2f0c590-31c3-4b66-a4e0-71548f4760f7')
        ->and($invoice)->not->toBeNull()
        ->and((float) $invoice->amount)->toBeGreaterThan(0)
        ->and(data_get($invoice->meta, 'line_items.0.type'))->toBe('extra_instance_prorata');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && $request['name'] === $extra->instance_name
        && $request['instanceName'] === $extra->instance_name
        && filled($request['token']));
});

it('envia token exigido pela evolution e usa como chave quando resposta nao traz hash', function () {
    [$user, $tenant] = createTenantWithEvolutionInstance();
    $tenant->plan->update(['max_instances' => 3]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/create' => Http::response([
            'instance' => ['id' => '80e0dc9b-c63a-4773-9a7f-95aa321b1f54', 'instanceName' => 'atendimento_token'],
            'qrcode' => ['base64' => 'data:image/png;base64,abc'],
        ], 201),
    ]);

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Atendimento Token'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $created = $tenant->instances()->where('display_name', 'Atendimento Token')->firstOrFail();
    $sentCreate = collect(Http::recorded())
        ->first(fn ($record) => str_ends_with($record[0]->url(), '/instance/create'));

    expect($sentCreate)->not->toBeNull()
        ->and($sentCreate[0]['token'])->not->toBeEmpty()
        ->and($created->api_key)->toBe($sentCreate[0]['token'])
        ->and(data_get($created->meta, 'evolution_remote.token_provided'))->toBeTrue();
});

it('retorna html e contadores para atualizar a lista de instancias via ajax', function () {
    [$user, $tenant] = createTenantWithEvolutionInstance();
    $tenant->plan->update(['max_instances' => 3]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/create' => Http::response([
            'instance' => ['id' => '7a1ec1e9-81f3-49c6-94e2-6f246fdc83f5', 'instanceName' => 'suporte_ajax'],
            'data' => ['Qrcode' => 'data:image/png;base64,abc123'],
            'hash' => 'token-suporte',
        ], 201),
    ]);

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Suporte Ajax'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('instance.display_name', 'Suporte Ajax')
        ->assertJsonPath('counts.total', 2)
        ->assertJsonPath('qr', 'data:image/png;base64,abc123')
        ->assertJsonPath('config.webhook_enabled', true)
        ->assertJsonStructure(['html', 'config' => ['id', 'webhook_url']]);
});

it('registra novas instancias com username do cliente tenant e slot sequencial', function () {
    [$user, $tenant] = createTenantWithEvolutionInstance();
    $user->forceFill([
        'name' => 'Marcelo Brad',
        'email' => 'financeiro@example.com',
    ])->save();
    $tenant->plan->update(['max_instances' => 3]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/create' => Http::response([
            'instance' => ['id' => 'c1c0dc9b-c63a-4773-9a7f-95aa321b1f54', 'instanceName' => 'marcelo_brad_'.$tenant->id.'_2'],
            'hash' => 'token-marcelo-slot',
        ], 201),
        'https://evo.test/instance/connect' => Http::response(['message' => 'webhook configurado'], 200),
        'https://evo.test/settings/set/*' => Http::response(['message' => 'success'], 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Suporte Comercial'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('instance.display_name', 'Suporte Comercial')
        ->assertJsonPath('instance.instance_name', 'marcelo_brad_'.$tenant->id.'_2');

    $created = $tenant->instances()->where('display_name', 'Suporte Comercial')->firstOrFail();

    expect($created->instance_name)->toBe('marcelo_brad_'.$tenant->id.'_2');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && $request['name'] === 'marcelo_brad_'.$tenant->id.'_2'
        && $request['instanceName'] === 'marcelo_brad_'.$tenant->id.'_2'
        && ! array_key_exists('webhookUrl', $request->data())
        && ! array_key_exists('subscribe', $request->data()));
});

it('renomeia somente o nome exibido no sistema mantendo identificador evolution', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'is_default' => false,
        'instance_name' => 'marcelo_brad_'.$tenant->id.'_2',
        'display_name' => 'Suporte',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake();

    $this->actingAs($user)
        ->putJson(route('tenant.instances.rename', $instance->id), ['name' => 'Vendas WhatsApp'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('instance.display_name', 'Vendas WhatsApp')
        ->assertJsonPath('instance.instance_name', 'marcelo_brad_'.$tenant->id.'_2');

    $instance->refresh();

    expect($instance->display_name)->toBe('Vendas WhatsApp')
        ->and($instance->instance_name)->toBe('marcelo_brad_'.$tenant->id.'_2');

    Http::assertNothingSent();
});

it('provisiona token webhook e configuracoes padrao automaticamente ao criar instancia', function () {
    [$user, $tenant] = createTenantWithEvolutionInstance();
    $tenant->plan->update(['max_instances' => 3]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/create')) {
            return Http::response([
                'instance' => ['id' => 'auto-001', 'instanceName' => 'auto_sync'],
                'hash' => 'token-auto-sync',
            ], 201);
        }

        if (str_contains($url, '/settings/set/')) {
            return Http::response(['message' => 'success'], 200);
        }

        if (str_ends_with($url, '/instance/connect')) {
            return Http::response(['data' => ['Qrcode' => 'data:image/png;base64,qr-connect']], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->postJson(route('tenant.instances.create'), ['name' => 'Auto Sync'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $created = $tenant->instances()->where('display_name', 'Auto Sync')->firstOrFail();

    expect($created->api_key)->toBe('token-auto-sync')
        ->and($created->webhook_enabled)->toBeTrue()
        ->and($created->webhook_by_events)->toBeTrue()
        ->and($created->webhook_events)->toBe(['QRCODE_UPDATED', 'CONNECTION_UPDATE', 'MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'MESSAGES_DELETE', 'SEND_MESSAGE', 'CALL'])
        ->and(data_get($created->meta, 'evolution_settings.rejectCall'))->toBeTrue()
        ->and(data_get($created->meta, 'evolution_config_apply.webhook.ok'))->toBeTrue()
        ->and(data_get($created->meta, 'evolution_config_apply.webhook.endpoint'))->toBe('instance_connect')
        ->and(data_get($created->meta, 'evolution_config_apply.settings.ok'))->toBeTrue()
        ->and($created->evolution_qr_code)->toBe('data:image/png;base64,qr-connect');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && str_contains((string) $request['webhookUrl'], '/webhooks/evolution/messages')
        && in_array('MESSAGE', $request['subscribe'], true)
        && in_array('CONNECTION', $request['subscribe'], true));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && ! array_key_exists('webhookUrl', $request->data())
        && ! array_key_exists('subscribe', $request->data()));
});

it('gera qr code usando fallback da evolution go por token da instancia', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/connect/'.$instance->instance_name)) {
            return Http::response('404 page not found', 404);
        }

        if (str_ends_with($url, '/instance/connect')) {
            return Http::response(['data' => ['eventString' => 'MESSAGE'], 'message' => 'success'], 200);
        }

        if (str_ends_with($url, '/instance/qr')) {
            return Http::response(['data' => ['Qrcode' => 'data:image/png;base64,qr-ok']], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $this->actingAs($user)
        ->getJson(route('tenant.instances.qr', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('qr', 'data:image/png;base64,qr-ok');

    expect($instance->refresh()->evolution_qr_code)->toBe('data:image/png;base64,qr-ok');
});

it('gera qr code do tenant com connect minimo quando webhook da evolution falha', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();
    $connectAttempts = 0;

    $instance->forceFill([
        'webhook_enabled' => true,
        'webhook_by_events' => true,
        'webhook_events' => ['QRCODE_UPDATED', 'CONNECTION_UPDATE'],
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use (&$connectAttempts) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/connect')) {
            $connectAttempts++;

            if (filled($request['webhookUrl'] ?? null)) {
                return Http::response(['message' => 'invalid webhook subscribe'], 422);
            }

            return Http::response(['data' => ['Qrcode' => 'data:image/png;base64,qr-minimo']], 200);
        }

        return Http::response(['message' => 'not used'], 500);
    });

    $this->actingAs($user)
        ->getJson(route('tenant.instances.qr', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('qr', 'data:image/png;base64,qr-minimo');

    expect($connectAttempts)->toBe(2)
        ->and($instance->refresh()->evolution_qr_code)->toBe('data:image/png;base64,qr-minimo');
});

it('bloqueia qr code quando numero pessoal ja esta em uso por outra instancia ativa', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();

    $duplicate = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'loja_premium_'.$tenant->id.'_2',
        'display_name' => 'Suporte duplicado',
        'status' => 'connected',
        'profile_number' => '5511987654321',
        'phone' => '5511987654321',
        'profile_is_business' => false,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token-2',
        'webhook_secret' => 'secret-token-2',
    ]);

    $instance->forceFill([
        'status' => 'disconnected',
        'profile_number' => '5511987654321',
        'phone' => '5511987654321',
        'profile_is_business' => false,
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/status' => Http::response(['instance' => ['state' => 'close']], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/qr' => Http::response(['data' => ['Qrcode' => 'data:image/png;base64,qr-bloqueado']], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('tenant.instances.qr', $instance->id))
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Numero 5511987654321 ja esta em uso pela instancia #'.$duplicate->id.' ('.$duplicate->instance_name.') do tenant #'.$tenant->id.'.');

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/instance/connect'));
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/instance/qr'));

    expect($instance->refresh()->evolution_qr_code)->toBeNull();
});

it('recupera instancia extra ausente na evolution antes de gerar qr code', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'instance_name' => 'suporte_1_2',
        'status' => 'disconnected',
        'last_connection_state' => 'not_authorized',
        'evolution_qr_code' => null,
        'last_error' => 'not authorized',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.global_api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['message' => 'not authorized'], 401);
        }

        if (str_ends_with($url, '/instance/all')) {
            return Http::response(['data' => []], 200);
        }

        if (str_ends_with($url, '/instance/create')) {
            return Http::response([
                'instance' => ['id' => '1b7b89e7-5d4f-4c88-b8dc-dad54b7a6c11', 'name' => $instance->instance_name],
                'hash' => 'token-recuperado-extra',
            ], 201);
        }

        if (str_contains($url, '/settings/set/') || str_contains($url, '/webhook/set/')) {
            return Http::response(['message' => 'success'], 200);
        }

        if (str_ends_with($url, '/instance/connect')) {
            return Http::response(['message' => 'success'], 200);
        }

        if (str_ends_with($url, '/instance/qr')) {
            return Http::response(['data' => ['Qrcode' => 'data:image/png;base64,qr-recuperado']], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->getJson(route('tenant.instances.qr', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('qr', 'data:image/png;base64,qr-recuperado');

    $instance->refresh();

    expect($instance->api_key)->toBe('token-recuperado-extra')
        ->and($instance->evolution_qr_code)->toBe('data:image/png;base64,qr-recuperado')
        ->and($instance->last_error)->toBeNull()
        ->and(data_get($instance->meta, 'evolution_recovered_at'))->not->toBeNull();
});

it('ignora timeout do endpoint legado de avatar sem marcar erro na instancia', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_contains($url, '/instance/connectionState/')) {
            return Http::response(['instance' => ['state' => 'open']], 200);
        }

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['data' => ['Connected' => true, 'LoggedIn' => true, 'Name' => 'Loja Premium']], 200);
        }

        if (str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => [[
                    'name' => $instance->instance_name,
                    'jid' => '5511999999999@s.whatsapp.net',
                    'client_name' => 'Loja Premium',
                ]],
            ], 200);
        }

        if (str_contains($url, '/chat/fetchProfile/')) {
            return Http::response([
                'name' => 'Loja Premium',
                'businessProfile' => ['description' => 'Atendimento premium no WhatsApp'],
            ], 200);
        }

        if (str_contains($url, '/chat/fetchProfilePictureUrl/')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/user/avatar')) {
            throw new RuntimeException('cURL error 28: Operation timed out after 15002 milliseconds with 0 bytes received for https://api.kdkhost.com.br/user/avatar');
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->post(route('tenant.instances.sync', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($instance->refresh()->last_error)->toBeNull();
});

it('recupera instancia extra ausente antes de sincronizar perfil', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'instance_name' => 'suporte_1_2',
        'status' => 'disconnected',
        'last_connection_state' => 'not_authorized',
        'api_key' => 'token-antigo',
        'last_error' => 'not authorized',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.global_api_key' => 'global-token',
    ]);

    $created = false;

    Http::fake(function ($request) use ($instance, &$created) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => $created ? [[
                    'id' => '5528b021-bc54-414d-aefb-b87d98d16e6c',
                    'name' => $instance->instance_name,
                    'ownerJid' => '5521981325441@s.whatsapp.net',
                    'profileName' => 'KDKHost',
                    'clientName' => 'KDKHost',
                    'connected' => true,
                ]] : [],
            ], 200);
        }

        if (str_ends_with($url, '/instance/create')) {
            $created = true;

            return Http::response([
                'instance' => ['id' => '5528b021-bc54-414d-aefb-b87d98d16e6c', 'name' => $instance->instance_name],
                'hash' => 'token-sync-recuperado',
            ], 201);
        }

        if (str_contains($url, '/settings/set/') || str_contains($url, '/webhook/set/')) {
            return Http::response(['message' => 'success'], 200);
        }

        if (str_ends_with($url, '/instance/status')) {
            return $created
                ? Http::response(['instance' => ['state' => 'open', 'jid' => '5521981325441@s.whatsapp.net']], 200)
                : Http::response(['message' => 'not authorized'], 401);
        }

        if (str_contains($url, '/user/info')) {
            return Http::response(['data' => ['users' => [[
                'jid' => '5521981325441@s.whatsapp.net',
                'number' => '5521981325441',
                'pushName' => 'KDKHost',
            ]]]], 200);
        }

        if (str_contains($url, '/user/avatar')) {
            return Http::response(['url' => 'https://cdn.test/avatar.jpg'], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->post(route('tenant.instances.sync', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('connected', true);

    $instance->refresh();

    expect($instance->api_key)->toBe('token-sync-recuperado')
        ->and($instance->status)->toBe('connected')
        ->and($instance->last_error)->toBeNull();
});

it('nao marca conectado nem sincroniza quando evolution nao retorna identidade whatsapp', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'status' => 'creating',
        'last_connection_state' => 'connecting',
        'phone' => null,
        'profile_number' => null,
        'profile_jid' => null,
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.global_api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/all')) {
            return Http::response(['data' => [[
                'id' => '27f624c9-f191-42fa-9915-a7b970744757',
                'name' => $instance->instance_name,
                'connected' => true,
                'state' => 'open',
            ]]], 200);
        }

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['instance' => ['state' => 'open']], 200);
        }

        if (str_contains($url, '/settings/set/') || str_ends_with($url, '/instance/connect')) {
            return Http::response(['message' => 'success'], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->post(route('tenant.instances.sync', $instance->id))
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('connected', false)
        ->assertJsonPath('error', 'Nenhum WhatsApp conectado nesta instancia. Gere o QR Code e conecte o aparelho antes de sincronizar.');

    $instance->refresh();

    expect($instance->status)->toBe('disconnected')
        ->and($instance->profile_number)->toBeNull()
        ->and($instance->profile_jid)->toBeNull()
        ->and($instance->last_error)->toContain('nao retornou numero/JID');
});

it('permite sincronizar numero repetido quando a conta whatsapp e business', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();

    TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'loja_premium_'.$tenant->id.'_2',
        'display_name' => 'Business principal',
        'status' => 'connected',
        'profile_number' => '5511999999999',
        'phone' => '5511999999999',
        'profile_is_business' => true,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token-2',
        'webhook_secret' => 'secret-token-2',
    ]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['data' => ['Connected' => true, 'LoggedIn' => true, 'Name' => 'Loja Premium']], 200);
        }

        if (str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => [[
                    'name' => $instance->instance_name,
                    'jid' => '5511999999999@s.whatsapp.net',
                    'connected' => true,
                    'client_name' => 'Loja Premium',
                ]],
            ], 200);
        }

        if (str_contains($url, '/user/info')) {
            return Http::response([
                'data' => [
                    'users' => [[
                        'jid' => '5511999999999@s.whatsapp.net',
                        'number' => '5511999999999',
                        'pushName' => 'Loja Premium',
                        'fullName' => 'Loja Premium Atendimento',
                        'businessName' => 'Loja Premium',
                        'isBusiness' => true,
                        'status' => 'Atendimento premium no WhatsApp',
                    ]],
                ],
            ], 200);
        }

        if (str_contains($url, '/chat/fetchProfile/') || str_contains($url, '/chat/fetchProfilePictureUrl/')) {
            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->post(route('tenant.instances.sync', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('connected', true)
        ->assertJsonPath('instance.profile_is_business', true)
        ->assertJsonPath('instance.profile_number', '5511999999999');

    expect($instance->refresh()->status)->toBe('connected')
        ->and($instance->last_error)->toBeNull()
        ->and($instance->profile_number)->toBe('5511999999999')
        ->and($instance->profile_is_business)->toBeTrue();
});

it('ignora endpoints opcionais ausentes da evolution sem marcar erro na instancia', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();

    $instance->forceFill([
        'last_error' => 'erro antigo',
        'webhook_enabled' => false,
        'webhook_secret' => null,
        'webhook_events' => [],
        'meta' => [],
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake(function ($request) use ($instance) {
        $url = $request->url();

        if (str_contains($url, '/instance/connectionState/')) {
            return Http::response('404 page not found', 404);
        }

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['data' => ['Connected' => true, 'LoggedIn' => true, 'Name' => 'Marcelo Brad']], 200);
        }

        if (str_ends_with($url, '/instance/all')) {
            return Http::response([
                'data' => [[
                    'id' => 'bc26413b-6480-4af4-ba89-e54b016de898',
                    'name' => $instance->instance_name,
                    'jid' => '5521981325441:7@s.whatsapp.net',
                    'connected' => true,
                    'client_name' => 'KDKHost',
                ]],
                'message' => 'success',
            ], 200);
        }

        if (str_contains($url, '/chat/fetchProfile/')) {
            return Http::response('404 page not found', 404);
        }

        if (str_contains($url, '/chat/fetchProfilePictureUrl/')) {
            return Http::response('404 page not found', 404);
        }

        if (str_contains($url, '/user/avatar')) {
            throw new RuntimeException('cURL error 28: Operation timed out after 15002 milliseconds with 0 bytes received for https://api.kdkhost.com.br/user/avatar');
        }

        if (str_contains($url, '/settings/set/')) {
            return Http::response(['message' => 'success'], 200);
        }

        if (str_contains($url, '/webhook/set/')) {
            return Http::response(['message' => 'success'], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->post(route('tenant.instances.sync', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('connected', true)
        ->assertJsonPath('instance.profile_name', 'Marcelo Brad');

    expect($instance->refresh()->last_error)->toBeNull()
        ->and($instance->status)->toBe('connected')
        ->and($instance->webhook_enabled)->toBeTrue()
        ->and($instance->webhook_secret)->not->toBeNull()
        ->and($instance->webhook_events)->not->toBeEmpty()
        ->and(data_get($instance->meta, 'evolution_config_apply.webhook.ok'))->toBeTrue();
});

it('deleta instancia na evolution pelo painel do tenant', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['data' => [['name' => $instance->instance_name, 'connected' => false]]], 200),
        'https://evo.test/instance/delete/*' => Http::response([], 200),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tenant.instances.destroy', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse()
        ->and($tenant->instances()->count())->toBe(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/instance/delete/'.$instance->instance_name));
});

it('remove instancia orfa local quando a evolution exige uuid e nao existe id remoto', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'status' => 'creating',
        'phone' => null,
        'profile_number' => null,
        'profile_jid' => null,
        'meta' => [],
        'last_error' => 'erro desconhecido | not authorized',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['data' => []], 200),
        'https://evo.test/instance/delete/*' => Http::response(['message' => 'invalid UUID format: invalid UUID length: 18'], 400),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tenant.instances.destroy', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse()
        ->and($tenant->instances()->count())->toBe(0);
});

it('remove instancia local com perfil antigo quando evolution confirma ausencia remota', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'status' => 'connected',
        'last_connection_state' => 'open',
        'phone' => '5521981325441',
        'profile_number' => '5521981325441',
        'profile_jid' => '5521981325441@s.whatsapp.net',
        'meta' => [
            'evolution_remote' => [
                'id' => '9d52d65b-5e80-4a9d-b4f8-80b4db807e91',
                'name' => $instance->instance_name,
            ],
        ],
        'last_error' => 'not authorized',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.global_api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['data' => []], 200),
        'https://evo.test/instance/delete/*' => Http::response(['message' => 'record not found'], 404),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tenant.instances.destroy', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse()
        ->and($tenant->instances()->count())->toBe(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/instance/delete/'));
});

it('remove instancia tenant sem identidade quando evolution rejeita nome como uuid', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'instance_name' => 'suporte_1_2',
        'status' => 'disconnected',
        'last_connection_state' => 'not_authorized',
        'phone' => '-',
        'profile_number' => '-',
        'profile_jid' => '-',
        'profile_raw' => ['id' => 'suporte_1_2'],
        'meta' => [],
        'last_error' => 'not authorized',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.global_api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['data' => []], 200),
        'https://evo.test/instance/delete/*' => Http::response([
            'message' => 'record not found | invalid UUID format: invalid UUID length: 11',
        ], 400),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tenant.instances.destroy', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse()
        ->and($tenant->instances()->count())->toBe(0);
});

it('remove instancia tenant com perfil obsoleto quando evolution confirma que nao existe uuid remoto', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'instance_name' => 'suporte_1_2',
        'status' => 'disconnected',
        'last_connection_state' => 'not_authorized',
        'phone' => '5521981325441',
        'profile_number' => '5521981325441',
        'profile_jid' => '5521981325441@s.whatsapp.net',
        'profile_raw' => ['id' => 'suporte_1_2'],
        'meta' => [],
        'last_error' => 'not authorized',
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.global_api_key' => 'global-token',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_ends_with($url, '/instance/status')) {
            return Http::response(['message' => 'not authorized'], 401);
        }

        if (str_ends_with($url, '/instance/all')) {
            return Http::response(['data' => []], 200);
        }

        if (str_contains($url, '/instance/delete/')) {
            return Http::response([
                'message' => 'record not found | invalid UUID format: invalid UUID length: 11',
            ], 400);
        }

        return Http::response([], 200);
    });

    $this->actingAs($user)
        ->deleteJson(route('tenant.instances.destroy', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse()
        ->and($tenant->instances()->count())->toBe(0);
});

it('usa uuid remoto da evolution ao deletar instancia quando disponivel', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();
    $instance->forceFill([
        'meta' => [
            'evolution_remote' => [
                'id' => '9d52d65b-5e80-4a9d-b4f8-80b4db807e91',
                'name' => $instance->instance_name,
            ],
        ],
    ])->save();

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['data' => [[
            'id' => '9d52d65b-5e80-4a9d-b4f8-80b4db807e91',
            'name' => $instance->instance_name,
            'connected' => false,
        ]]], 200),
        'https://evo.test/instance/delete/9d52d65b-5e80-4a9d-b4f8-80b4db807e91' => Http::response([], 200),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tenant.instances.destroy', $instance->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/delete/9d52d65b-5e80-4a9d-b4f8-80b4db807e91'));
});

it('deleta instancia na evolution pelo superadmin', function () {
    [, $tenant, $instance] = createTenantWithEvolutionInstance();

    $admin = AdminUser::create([
        'name' => 'Super Admin Evolution',
        'email' => 'superadmin-evolution-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    config([
        'whatsapp.base_url' => 'https://evo.test',
        'whatsapp.api_key' => 'global-token',
    ]);

    Http::fake([
        'https://evo.test/instance/all' => Http::response(['data' => [['name' => $instance->instance_name, 'connected' => false]]], 200),
        'https://evo.test/instance/delete/*' => Http::response([], 200),
    ]);

    $this->actingAs($admin, 'admin')
        ->deleteJson(route('admin.tenancy.tenants.instances.destroy', [$tenant->id, $instance->id]))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(TenantInstance::whereKey($instance->id)->exists())->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/instance/delete/'.$instance->instance_name));
});

it('salva credenciais configuracoes e webhook evolution por instancia', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();

    Http::fake([
        'https://evo.test/settings/set/*' => Http::response(['settings' => ['ok' => true]], 201),
        'https://evo.test/instance/*/advanced-settings' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/connect' => Http::response(['webhook' => ['ok' => true]], 200),
        'https://evo.test/webhook/set/*' => Http::response(['webhook' => ['ok' => true]], 201),
    ]);

    $payload = [
        'api_base_url' => 'https://evo.test',
        'api_key' => 'novo-token-instancia',
        'webhook_enabled' => true,
        'webhook_by_events' => true,
        'webhook_base64' => false,
        'webhook_events' => ['CONNECTION_UPDATE', 'MESSAGES_UPSERT'],
        'event_producer_rabbitmq' => 'enabled',
        'event_producer_websocket' => 'disabled',
        'event_producer_nats' => 'default',
        'reject_call' => true,
        'msg_call' => 'Atendimento somente por mensagem.',
        'groups_ignore' => true,
        'always_online' => true,
        'read_messages' => true,
        'ignore_status' => true,
        'read_status' => false,
        'sync_full_history' => false,
    ];

    $this->actingAs($user)
        ->postJson(route('tenant.instances.config', $instance->id), $payload)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('applied', true);

    $instance->refresh();
    $storedToken = DB::table('tenant_instances')->where('id', $instance->id)->value('api_key');

    expect($instance->api_key)->toBe('novo-token-instancia')
        ->and($storedToken)->not->toBe('novo-token-instancia')
        ->and($instance->webhook_enabled)->toBeTrue()
        ->and($instance->webhook_events)->toBe(['CONNECTION_UPDATE', 'MESSAGES_UPSERT'])
        ->and(data_get($instance->meta, 'evolution_event_producers.rabbitmq'))->toBe('enabled')
        ->and(data_get($instance->meta, 'evolution_event_producers.websocket'))->toBe('disabled')
        ->and(data_get($instance->meta, 'evolution_event_producers.nats'))->toBe('default')
        ->and(data_get($instance->meta, 'evolution_settings.readMessages'))->toBeTrue()
        ->and(data_get($instance->meta, 'evolution_settings.ignoreStatus'))->toBeTrue()
        ->and(data_get($instance->meta, 'evolution_settings.readStatus'))->toBeFalse();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/'.$instance->instance_name.'/advanced-settings')
        && $request['rejectCalls'] === true
        && $request['rejectCallsMessage'] === 'Atendimento somente por mensagem.'
        && $request['readMessages'] === true
        && $request['readStatus'] === false);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && str_contains((string) $request['webhookUrl'], 'tenant_instance='.$instance->id)
        && $request['subscribe'] === ['CONNECTION', 'MESSAGE']
        && $request['rabbitmqEnable'] === 'true'
        && $request['websocketEnable'] === 'false'
        && $request['natsEnable'] === 'false');
});

it('mantem configuracoes salvas e aplica advanced settings v3 quando settings set nao existe', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();

    Http::fake([
        'https://evo.test/settings/set/*' => Http::response('404 page not found', 404),
        'https://evo.test/instance/'.$instance->instance_name.'/advanced-settings' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'webhook configurado'], 200),
    ]);

    $payload = [
        'api_base_url' => 'https://evo.test',
        'api_key' => '',
        'webhook_enabled' => true,
        'webhook_by_events' => true,
        'webhook_base64' => false,
        'webhook_events' => ['CONNECTION_UPDATE', 'MESSAGES_UPSERT'],
        'reject_call' => false,
        'msg_call' => 'Nao atendemos chamadas.',
        'groups_ignore' => true,
        'always_online' => true,
        'read_messages' => true,
        'read_status' => false,
        'sync_full_history' => true,
    ];

    $this->actingAs($user)
        ->postJson(route('tenant.instances.config', $instance->id), $payload)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('applied', true);

    $instance->refresh();

    expect(data_get($instance->meta, 'evolution_settings.rejectCall'))->toBeFalse()
        ->and(data_get($instance->meta, 'evolution_settings.alwaysOnline'))->toBeTrue()
        ->and(data_get($instance->meta, 'evolution_settings.syncFullHistory'))->toBeTrue()
        ->and(data_get($instance->meta, 'evolution_config_apply.settings.endpoint'))->toBe('advanced_settings')
        ->and($instance->last_error)->toBeNull();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/'.$instance->instance_name.'/advanced-settings')
        && $request['rejectCalls'] === false
        && $request['rejectCallsMessage'] === 'Nao atendemos chamadas.'
        && $request['alwaysOnline'] === true
        && $request['syncFullHistory'] === true);
});

it('desativa webhook remoto da instancia quando tenant desmarca o recebimento de eventos', function () {
    [$user, , $instance] = createTenantWithEvolutionInstance();

    Http::fake([
        'https://evo.test/instance/'.$instance->instance_name.'/advanced-settings' => Http::response(['message' => 'success'], 200),
        'https://evo.test/instance/connect' => Http::response(['message' => 'webhook atualizado'], 200),
    ]);

    $payload = [
        'api_base_url' => 'https://evo.test',
        'api_key' => '',
        'webhook_enabled' => false,
        'webhook_by_events' => true,
        'webhook_base64' => false,
        'webhook_events' => ['CONNECTION_UPDATE'],
        'reject_call' => true,
        'msg_call' => 'Atendimento somente por mensagem.',
        'groups_ignore' => true,
        'always_online' => false,
        'read_messages' => false,
        'read_status' => true,
        'sync_full_history' => false,
    ];

    $this->actingAs($user)
        ->postJson(route('tenant.instances.config', $instance->id), $payload)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('applied', true);

    $instance->refresh();

    expect($instance->webhook_enabled)->toBeFalse();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && $request['webhookUrl'] === ''
        && $request['subscribe'] === []);
});

it('protege webhook tenant com token da instancia e registra interacao no tenant correto', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();

    Http::fake([
        'https://evo.test/send/text' => Http::response(['key' => ['id' => 'msg-1']], 201),
    ]);

    $payload = [
        'instance' => $instance->instance_name,
        'data' => [[
            'key' => ['fromMe' => false, 'remoteJid' => '5511988887777@s.whatsapp.net'],
            'message' => ['conversation' => 'oi'],
        ]],
    ];

    $this->postJson('/webhooks/evolution/messages/MESSAGES_UPSERT?tenant_instance='.$instance->id, $payload)
        ->assertForbidden();

    $this->postJson('/webhooks/evolution/messages/MESSAGES_UPSERT?tenant_instance='.$instance->id.'&token=secret-token', $payload)
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(DB::table('chatbot_interactions')->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(auth()->user())->toBeNull();
    expect($user->id)->not->toBeNull();
});

it('aceita webhook legado do chatbot apenas com token valido', function () {
    [$user, $tenant, $instance] = createTenantWithEvolutionInstance();

    Http::fake([
        'https://evo.test/send/text' => Http::response(['key' => ['id' => 'msg-1']], 201),
    ]);

    $payload = [
        'instance' => $instance->instance_name,
        'data' => [[
            'key' => ['fromMe' => false, 'remoteJid' => '5511988887777@s.whatsapp.net'],
            'message' => ['conversation' => 'oi'],
        ]],
    ];

    $this->postJson('/webhooks/evolution/messages/MESSAGES_UPSERT?tenant_instance='.$instance->id.'&token=secret-token', $payload)
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(DB::table('chatbot_interactions')->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect($user->id)->not->toBeNull();
});

it('aceita webhook legado de status da evolution com token sem exigir hmac', function () {
    [, , $instance] = createTenantWithEvolutionInstance();

    $payload = [
        'event' => 'connection.update',
        'instance' => $instance->instance_name,
        'data' => ['state' => 'open'],
    ];

    $this->postJson('/webhooks/evolution/messages?tenant_instance='.$instance->id.'&token=secret-token', $payload)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('ignored', true);

    expect(DB::table('chatbot_interactions')->where('tenant_id', $instance->tenant_id)->count())->toBe(0);
});
