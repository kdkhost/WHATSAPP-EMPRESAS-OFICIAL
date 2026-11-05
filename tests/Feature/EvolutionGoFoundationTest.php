<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\WhatsAppCustomerLimit;
use App\Models\WhatsAppWebhookLog;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Services\AddonManagerService;
use App\Services\WhatsAppDailyLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

function evolutionFoundationTenant(): array
{
    $plan = Plan::create([
        'name' => 'Plano Webhook',
        'slug' => 'plano-webhook-'.uniqid(),
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
        'name' => 'Tenant Webhook',
        'email' => 'tenant-webhook-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Webhook',
        'slug' => 'tenant-webhook-'.uniqid(),
        'status' => 'active',
        'settings' => ['whatsapp_daily_limits' => ['debt_query' => 1]],
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_webhook_'.$tenant->id,
        'display_name' => 'Atendimento',
        'status' => 'creating',
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);

    return [$user, $tenant, $instance];
}

it('recebe webhook evolution go com token da instancia e atualiza o espelho local', function () {
    [, $tenant, $instance] = evolutionFoundationTenant();

    $payload = [
        'event' => 'connection.update',
        'instance' => $instance->instance_name,
        'data' => ['state' => 'open', 'jid' => '5511999999999@s.whatsapp.net'],
    ];

    $this->postJson('/api/webhooks/evolution-go?token=errado', $payload)
        ->assertForbidden()
        ->assertJsonPath('error', 'webhook_token_invalid');

    $content = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $content, 'secret-token');

    $this->withHeaders([
        'X-Webhook-Timestamp' => $timestamp,
        'X-Webhook-Signature' => $signature,
    ])->postJson('/api/webhooks/evolution-go?token=secret-token', $payload)
        ->assertAccepted()
        ->assertJsonPath('ok', true);

    $instance->refresh();

    expect($instance->status)->toBe('connected')
        ->and(DB::table('whatsapp_instances')->where('tenant_id', $tenant->id)->where('instance_name', $instance->instance_name)->exists())->toBeTrue()
        ->and(WhatsAppWebhookLog::where('tenant_instance_id', $instance->id)->where('status', 'processed')->exists())->toBeTrue();
});

it('controla limite diario de autoatendimento por tenant cliente telefone e acao', function () {
    [, $tenant] = evolutionFoundationTenant();
    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Limite',
        'whatsapp' => '21999998888',
    ]);

    $service = app(WhatsAppDailyLimitService::class);

    expect($service->hit($tenant, $customer, '21999998888', 'debt_query')['allowed'])->toBeTrue()
        ->and($service->hit($tenant, $customer, '21999998888', 'debt_query')['allowed'])->toBeFalse()
        ->and(WhatsAppCustomerLimit::where('tenant_id', $tenant->id)->where('action_type', 'debt_query')->value('total_requests'))->toBe(2);
});

it('instala addon por manifesto module json registra permissao menu e limpa cache via servico', function () {
    $admin = AdminUser::create([
        'name' => 'Super Addon',
        'email' => 'super-addon-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $dir = app_path('Addons/teste-premium');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Teste Premium',
        'slug' => 'teste-premium',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-puzzle',
        'route' => '/admin/addons/teste-premium',
        'active' => true,
        'menu' => ['title' => 'Teste Premium', 'icon' => 'bi-puzzle', 'order' => 55],
        'permissions' => ['teste-premium.view', 'teste-premium.create'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $result = app(AddonManagerService::class)->install('teste-premium');

        expect($result['ok'])->toBeTrue()
            ->and(DB::table('registered_modules')->where('slug', 'teste-premium')->where('enabled', true)->exists())->toBeTrue()
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium')->where('active', true)->exists())->toBeTrue()
            ->and(DB::table('permissions')->where('slug', 'teste-premium.view')->exists())->toBeTrue();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sidebar.reload'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['addons' => 1]);
    } finally {
        File::deleteDirectory($dir);
    }
});

it('instala addon por manifesto com rota de menu e registra link correto', function () {
    $admin = AdminUser::create([
        'name' => 'Super Addon',
        'email' => 'super-addon-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $dir = app_path('Addons/teste-premium-menu-route');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Teste Premium',
        'slug' => 'teste-premium-menu-route',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-puzzle',
        'active' => true,
        'menu' => ['title' => 'Teste Premium', 'icon' => 'bi-puzzle', 'order' => 55, 'route' => '/admin/addons/teste-premium-menu-route'],
        'permissions' => ['teste-premium-menu-route.view', 'teste-premium-menu-route.create'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $result = app(AddonManagerService::class)->install('teste-premium-menu-route');

        expect($result['ok'])->toBeTrue()
            ->and(DB::table('registered_modules')->where('slug', 'teste-premium-menu-route')->where('enabled', true)->exists())->toBeTrue()
            ->and(DB::table('registered_modules')->where('slug', 'teste-premium-menu-route')->value('route'))->toBe('/admin/addons/teste-premium-menu-route')
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium-menu-route')->where('active', true)->exists())->toBeTrue()
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium-menu-route')->value('route'))->toBe('/admin/addons/teste-premium-menu-route')
            ->and(DB::table('permissions')->where('slug', 'teste-premium-menu-route.view')->exists())->toBeTrue();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sidebar.reload'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['addons' => 1]);
    } finally {
        File::deleteDirectory($dir);
    }
});

it('mantem rota operacional do addon livre e envia detalhes para rota tecnica separada', function () {
    $admin = AdminUser::create([
        'name' => 'Super Addon',
        'email' => 'super-addon-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    \Illuminate\Support\Facades\Route::get('/admin/addons/teste-premium-operacional', fn () => 'Painel operacional do addon')
        ->middleware(['web', 'admin'])
        ->name('admin.addons.teste-premium-operacional.index');

    $dir = app_path('Addons/teste-premium-operacional');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Teste Premium Operacional',
        'slug' => 'teste-premium-operacional',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-puzzle',
        'active' => true,
        'menu' => ['title' => 'Teste Operacional', 'icon' => 'bi-puzzle', 'order' => 55, 'route' => '/admin/addons/teste-premium-operacional'],
        'permissions' => ['teste-premium-operacional.view'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $install = app(AddonManagerService::class)->install('teste-premium-operacional');
        expect($install['ok'])->toBeTrue();

        $this->actingAs($admin, 'admin')
            ->get('/admin/addons/teste-premium-operacional')
            ->assertOk()
            ->assertSeeText('Painel operacional do addon')
            ->assertDontSee('Manifesto');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.addons.show', 'teste-premium-operacional'))
            ->assertOk()
            ->assertSeeText('Addon instalado')
            ->assertSeeText('Abrir painel do addon')
            ->assertDontSee('"permissions"', false);
    } finally {
        File::deleteDirectory($dir);
    }
});

it('instala addon usando nome de rota em menu.route e registra menu dinamico', function () {
    $admin = AdminUser::create([
        'name' => 'Super Addon',
        'email' => 'super-addon-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    \Illuminate\Support\Facades\Route::get('/admin/financeiro', fn () => 'Financeiro')->name('admin.financeiro.index');

    $dir = app_path('Addons/teste-premium-menu-name');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Teste Premium',
        'slug' => 'teste-premium-menu-name',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-puzzle',
        'active' => true,
        'menu' => ['title' => 'Financeiro', 'icon' => 'bi-cash-stack', 'order' => 55, 'route' => 'admin.financeiro.index'],
        'permissions' => ['teste-premium-menu-name.view'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $result = app(AddonManagerService::class)->install('teste-premium-menu-name');

        expect($result['ok'])->toBeTrue()
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium-menu-name')->where('active', true)->exists())->toBeTrue()
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium-menu-name')->value('route'))->toBe('admin.financeiro.index');

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sidebar.reload'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['addons' => 1]);
    } finally {
        File::deleteDirectory($dir);
    }
});

it('registra todos os menus declarados pelo addon dentro do grupo do modulo', function () {
    $admin = AdminUser::create([
        'name' => 'Super Addon',
        'email' => 'super-addon-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    \Illuminate\Support\Facades\Route::get('/admin/financeiro/receitas', fn () => 'Receitas')->name('admin.financeiro.receitas');
    \Illuminate\Support\Facades\Route::get('/admin/financeiro/despesas', fn () => 'Despesas')->name('admin.financeiro.despesas');

    $dir = app_path('Addons/teste-premium-multi-menu');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Financeiro Multi Menu',
        'slug' => 'teste-premium-multi-menu',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-wallet2',
        'active' => true,
        'menu' => [
            'title' => 'Financeiro Multi',
            'icon' => 'bi-wallet2',
            'order' => 55,
            'items' => [
                ['title' => 'Receitas', 'icon' => 'bi-arrow-up-circle', 'route' => 'admin.financeiro.receitas', 'order' => 56],
                ['title' => 'Despesas', 'icon' => 'bi-arrow-down-circle', 'route' => 'admin.financeiro.despesas', 'order' => 57],
            ],
        ],
        'permissions' => ['teste-premium-multi-menu.view'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $result = app(AddonManagerService::class)->install('teste-premium-multi-menu');

        expect($result['ok'])->toBeTrue()
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium-multi-menu')->where('active', true)->count())->toBe(2)
            ->and(DB::table('addon_menus')->where('module_slug', 'teste-premium-multi-menu')->pluck('route')->all())->toContain('admin.financeiro.receitas', 'admin.financeiro.despesas');

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sidebar.reload'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['addons' => 1]);

        expect($response->json('html'))
            ->toContain('Financeiro Multi')
            ->toContain('Receitas')
            ->toContain('Despesas');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('desativa addon e desmarca menu dinamico como inativo', function () {
    $admin = AdminUser::create([
        'name' => 'Super Addon',
        'email' => 'super-addon-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $dir = app_path('Addons/teste-premium-menu-deactivate');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Teste Premium',
        'slug' => 'teste-premium-menu-deactivate',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-puzzle',
        'active' => true,
        'menu' => ['title' => 'Financeiro', 'icon' => 'bi-cash-stack', 'order' => 55, 'route' => '/admin/financeiro'],
        'permissions' => ['teste-premium-menu-deactivate.view'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $install = app(AddonManagerService::class)->install('teste-premium-menu-deactivate');
        expect($install['ok'])->toBeTrue();

        $deactivate = app(AddonManagerService::class)->deactivate('teste-premium-menu-deactivate');
        expect($deactivate['ok'])->toBeTrue();

        expect(DB::table('addon_menus')->where('module_slug', 'teste-premium-menu-deactivate')->value('active'))->toBe(0)
            ->and(DB::table('registered_modules')->where('slug', 'teste-premium-menu-deactivate')->value('enabled'))->toBe(0);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sidebar.reload'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['addons' => 0]);
    } finally {
        File::deleteDirectory($dir);
    }
});

it('desinstala addon e remove menu dinamico e registro', function () {
    $dir = app_path('Addons/teste-premium-menu-uninstall');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/module.json', json_encode([
        'name' => 'Teste Premium',
        'slug' => 'teste-premium-menu-uninstall',
        'version' => '1.0.0',
        'description' => 'Modulo de teste',
        'icon' => 'bi-puzzle',
        'active' => true,
        'menu' => ['title' => 'Financeiro', 'icon' => 'bi-cash-stack', 'order' => 55, 'route' => '/admin/financeiro'],
        'permissions' => ['teste-premium-menu-uninstall.view'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        $install = app(AddonManagerService::class)->install('teste-premium-menu-uninstall');
        expect($install['ok'])->toBeTrue();

        $uninstall = app(AddonManagerService::class)->uninstall('teste-premium-menu-uninstall');
        expect($uninstall['ok'])->toBeTrue();

        expect(DB::table('addon_menus')->where('module_slug', 'teste-premium-menu-uninstall')->exists())->toBeFalse()
            ->and(DB::table('registered_modules')->where('slug', 'teste-premium-menu-uninstall')->exists())->toBeFalse()
            ->and(DB::table('permissions')->where('slug', 'teste-premium-menu-uninstall.view')->exists())->toBeFalse();
    } finally {
        File::deleteDirectory($dir);
    }
});
