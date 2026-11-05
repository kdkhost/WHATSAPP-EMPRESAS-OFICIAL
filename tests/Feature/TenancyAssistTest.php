<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;

it('permite login assistido do admin no tenant e retorno ao painel admin', function () {
    $plan = Plan::create([
        'name' => 'Plano Teste',
        'slug' => 'plano-teste',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $tenantUser = User::create([
        'name' => 'Tenant User',
        'email' => 'tenant-assist@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $tenantUser->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Tenant',
        'slug' => 'empresa-tenant',
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $admin = AdminUser::create([
        'name' => 'Super Admin',
        'email' => 'superadmin-assist@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');

    $this->post(route('admin.tenancy.tenants.assist.start', $tenant->id))
        ->assertRedirect(route('tenant.dashboard'));

    $this->assertAuthenticatedAs($tenantUser, 'web');
    $this->get(route('tenant.dashboard'))
        ->assertOk()
        ->assertSee('app-sidebar', false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee('tenant-premium-hero', false)
        ->assertDontSee(route('logout'), false);

    $this->get(route('tenant.onboarding.index'))
        ->assertOk()
        ->assertSee('tenant-admin-layout', false)
        ->assertSee('Modo assistido')
        ->assertSee('Painel do cliente')
        ->assertDontSee('Superadmin')
        ->assertDontSee('/admin/import/customers', false)
        ->assertSessionHas('admin_assist_mode', true);

    $this->get(route('tenant.contracts.index'))
        ->assertOk()
        ->assertSee('Contratos Digitais');

    $this->post(route('logout'))
        ->assertRedirect(route('tenant.dashboard'));

    $this->assertAuthenticatedAs($tenantUser, 'web');
    $this->assertAuthenticatedAs($admin, 'admin');

    $this->post(route('tenant.assist.stop'))
        ->assertRedirect(route('admin.tenancy.tenants.show', $tenant->id));

    $this->assertGuest('web');
    $this->assertAuthenticatedAs($admin, 'admin');
});

it('persiste o tema dark light do painel do cliente entre paginas', function () {
    $plan = Plan::create([
        'name' => 'Plano Tema',
        'slug' => 'plano-tema',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $tenantUser = User::create([
        'name' => 'Tenant Tema',
        'email' => 'tenant-theme@example.com',
        'password' => 'secret',
    ]);

    Tenant::create([
        'user_id' => $tenantUser->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Tema',
        'slug' => 'empresa-tema',
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($tenantUser)
        ->post(route('tenant.preferences.theme'), ['theme' => 'dark'])
        ->assertOk()
        ->assertCookie('admin_theme', 'dark');

    expect($tenantUser->refresh()->theme_dark)->toBeTrue();

    $this->actingAs($tenantUser)
        ->withCookie('admin_theme', 'dark')
        ->get(route('tenant.dashboard'))
        ->assertOk()
        ->assertSee('data-bs-theme="dark"', false);
});

it('permite atualizar a foto de perfil do usuario tenant sem alterar avatar de instancia', function () {
    $plan = Plan::create([
        'name' => 'Plano Perfil',
        'slug' => 'plano-perfil',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $tenantUser = User::create([
        'name' => 'Tenant Perfil',
        'email' => 'tenant-profile@example.com',
        'password' => 'secret',
        'phone' => '(21) 99999-0000',
        'cep' => '20040-002',
        'logradouro' => 'Rua da Assembleia',
        'numero' => '10',
        'bairro' => 'Centro',
        'cidade' => 'Rio de Janeiro',
        'uf' => 'RJ',
    ]);

    Tenant::create([
        'user_id' => $tenantUser->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Perfil',
        'slug' => 'empresa-perfil',
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($tenantUser)
        ->get(route('tenant.profile'))
        ->assertOk()
        ->assertSee('Esta foto e do seu usuario no painel')
        ->assertSee('A foto da instancia WhatsApp continua sendo sincronizada somente pelo WhatsApp conectado')
        ->assertSee('Endereco completo')
        ->assertSee('Autenticacao em dois fatores')
        ->assertSee('Alterar senha');

    $this->actingAs($tenantUser)
        ->postJson(route('tenant.profile.update'), [
            'name' => 'Tenant Perfil Atualizado',
            'email' => 'tenant-profile@example.com',
            'phone' => '(21) 98888-7777',
            'document' => '123.456.789-01',
            'cep' => '20040-002',
            'logradouro' => 'Rua da Assembleia',
            'numero' => '123',
            'complemento' => 'Sala 1001',
            'bairro' => 'Centro',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
            'avatar_url' => '/storage/uploads/avatars/avatar.webp',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $tenantUser->refresh();

    expect($tenantUser->avatar_url)->toBe('/storage/uploads/avatars/avatar.webp')
        ->and($tenantUser->numero)->toBe('123')
        ->and($tenantUser->cidade)->toBe('Rio de Janeiro');

    $this->actingAs($tenantUser)
        ->postJson(route('tenant.profile.password'), [
            'current_password' => 'secret',
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(Hash::check('nova-senha-segura', $tenantUser->refresh()->password))->toBeTrue();
});

it('permite desativar 2fa do usuario tenant pelo proprio perfil', function () {
    $plan = Plan::create([
        'name' => 'Plano 2FA Tenant',
        'slug' => 'plano-2fa-tenant',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $tenantUser = User::create([
        'name' => 'Tenant 2FA',
        'email' => 'tenant-2fa@example.com',
        'password' => 'secret',
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345'])),
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    Tenant::create([
        'user_id' => $tenantUser->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa 2FA',
        'slug' => 'empresa-2fa',
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($tenantUser)
        ->get(route('tenant.profile'))
        ->assertRedirect(route('tenant.2fa.verify'));

    $this->actingAs($tenantUser)
        ->withSession(['tenant_2fa_confirmed' => true])
        ->get(route('tenant.profile'))
        ->assertOk()
        ->assertSee('Desativar 2FA');

    $this->actingAs($tenantUser)
        ->withSession(['tenant_2fa_confirmed' => true])
        ->postJson(route('tenant.profile.2fa.disable'), ['password' => 'secret'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $tenantUser->refresh();

    expect($tenantUser->two_factor_secret)->toBeNull()
        ->and($tenantUser->two_factor_enabled)->toBeFalse();
});
