<?php

use App\Modules\Tenancy\Models\Plan;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Marketplace\Models\MarketplaceAddon;
use App\Modules\Tenancy\Models\TenantAddon;
use App\Modules\Marketplace\Services\AddonMarketplaceService;

it('returns only one root menu item per installed addon for the system sidebar', function () {
    $plan = Plan::create(['name' => 'Plano Teste', 'slug' => 'plano-'.uniqid(), 'price' => 100]);
    $user = User::create(['name' => 'User Teste', 'email' => 'user-'.uniqid().'@test.com', 'password' => bcrypt('password')]);
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Teste',
        'slug' => 'empresa-'.uniqid(),
        'status' => 'active',
    ]);

    $addon = MarketplaceAddon::create([
        'name' => 'Gestão Financeira Completa',
        'slug' => 'gestao-financeira',
        'version' => '1.0.0',
        'author' => 'Marcelo Brad',
        'description' => 'Módulo financeiro',
        'is_active' => true,
        'manifest' => [
            'name' => 'Gestão Financeira Completa',
            'slug' => 'gestao-financeira',
            'menu' => [
                'title' => 'Gestão Financeira',
                'icon' => 'bi-cash-coin',
                'order' => 55,
                'items' => [
                    ['title' => 'Dashboard', 'route' => 'tenant.gestao-financeira.index'],
                    ['title' => 'Lançamentos', 'route' => 'tenant.gestao-financeira.transactions.index'],
                    ['title' => 'Categorias', 'route' => 'tenant.gestao-financeira.categories.index'],
                    ['title' => 'Contas', 'route' => 'tenant.gestao-financeira.accounts.index'],
                    ['title' => 'Relatórios', 'route' => 'tenant.gestao-financeira.reports.index'],
                ],
            ],
            'tenant_menu' => [
                ['title' => 'Dashboard Financeiro', 'route' => 'tenant.gestao-financeira.index'],
                ['title' => 'Lançamentos', 'route' => 'tenant.gestao-financeira.transactions.index'],
                ['title' => 'Categorias', 'route' => 'tenant.gestao-financeira.categories.index'],
                ['title' => 'Contas Bancárias', 'route' => 'tenant.gestao-financeira.accounts.index'],
                ['title' => 'Relatórios Financeiros', 'route' => 'tenant.gestao-financeira.reports.index'],
            ],
        ],
    ]);

    TenantAddon::create([
        'tenant_id' => $tenant->id,
        'marketplace_addon_id' => $addon->id,
        'status' => 'active',
    ]);

    $service = app(AddonMarketplaceService::class);
    $menus = $service->tenantMenus($tenant);

    // Deve retornar apenas 1 item para a sidebar do sistema
    expect($menus)->toHaveCount(1)
        ->and($menus[0]['title'])->toBe('Gestão Financeira')
        ->and($menus[0]['slug'])->toBe('gestao-financeira')
        ->and($menus[0]['icon'])->toBe('bi-cash-coin');
});

it('ensures invoice action dropdown uses popper fixed strategy and visible overflow', function () {
    $content = file_get_contents(resource_path('../app/Modules/Tenancy/Resources/views/tenant/invoices.blade.php'));

    expect($content)->toContain('data-bs-popper-config=\'{"strategy":"fixed"}\'')
        ->and($content)->toContain('overflow-y: visible !important;');
});

it('ensures open finance only appears for tenant when configured in admin', function () {
    $accountsView = file_get_contents(base_path('app/Addons/gestao_financeira/Resources/views/accounts/index.blade.php'));

    // Verifica que o bloco de Open Finance no topo só renderiza para o tenant se $isOpenFinanceConfigured for true
    expect($accountsView)->toContain('@if($isOpenFinanceConfigured)')
        ->and($accountsView)->toContain('@elseif($canManageOpenFinance)')
        ->and($accountsView)->toContain('Conectar Open Finance')
        ->and($accountsView)->toContain('Configurar Open Finance');
});

it('responds with 200 ok on open finance webhook endpoint for both get and post requests', function () {
    // 1. Requisição pelo navegador (HTML) -> 200 OK com página de status visual
    $responseHtml = $this->get('/api/gestao-financeira/open-finance-webhook', [
        'Accept' => 'text/html,application/xhtml+xml',
    ]);
    $responseHtml->assertStatus(200)
        ->assertSee('Endpoint do Open Finance')
        ->assertSee('Webhook Ativo e Operacional');

    // 2. Requisição técnica de verificação de API (JSON) -> 200 OK
    $responseGet = $this->getJson('/api/gestao-financeira/open-finance-webhook');
    $responseGet->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'status' => 'online',
        ]);

    // 3. Post de evento da Pluggy -> 200 OK
    $responsePost = $this->postJson('/api/gestao-financeira/open-finance-webhook', ['event' => 'test']);
    $responsePost->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'status' => 'online',
        ]);
});


