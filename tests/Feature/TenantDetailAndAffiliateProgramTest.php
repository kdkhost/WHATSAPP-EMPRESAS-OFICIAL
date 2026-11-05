<?php

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Security\Http\Middleware\Ensure2FA;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

function createTestAdminUser(): \App\Models\AdminUser
{
    return \App\Models\AdminUser::create([
        'name'      => 'Admin Teste',
        'email'     => 'admin-test-' . Str::random(8) . '@example.com',
        'password'  => bcrypt('password'),
        'role'      => 'superadmin',
        'active' => true,
    ]);
}

function createTestTenantWithPlan(): Tenant
{
    $user = User::create([
        'name' => 'Cliente Teste',
        'email' => 'cliente-test-' . Str::random(8) . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $plan = Plan::create([
        'name' => 'Plano Pro Teste',
        'slug' => 'plano-pro-teste-' . Str::random(6),
        'price' => 99.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 5,
        'max_customers' => 500,
        'max_invoices_month' => 500,
        'max_messages_month' => 5000,
        'custom_gateway' => false,
        'custom_templates' => false,
        'installment_enabled' => false,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    return Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Teste Afiliado',
        'slug' => 'empresa-teste-' . Str::random(6),
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
}

it('renderiza a pagina de detalhes do tenant com tabs e modulo de afiliados', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = createTestAdminUser();
    $tenant = createTestTenantWithPlan();

    $this->actingAs($admin, 'admin')
        ->get("/admin/tenancy/tenants/{$tenant->id}")
        ->assertOk()
        ->assertSee('Detalhes do Tenant')
        ->assertSee($tenant->company_name)
        ->assertSee('Programa de Afiliados')
        ->assertSee('chartTenantRevenue')
        ->assertSee('Instâncias WhatsApp');
});

it('salva regras de afiliado para um tenant especifico', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = createTestAdminUser();
    $tenant = createTestTenantWithPlan();

    $response = $this->actingAs($admin, 'admin')
        ->postJson("/admin/tenancy/tenants/{$tenant->id}/affiliate", [
            'status'           => 'active',
            'commission_mode'  => 'percentage',
            'commission_pct'   => 25.00,
            'commission_fixed' => 0,
            'commission_type'  => 'recurring',
            'discount_mode'    => 'percentage',
            'discount_pct'     => 15.00,
            'discount_fixed'   => 0,
            'discount_type'    => 'first_payment',
            'min_withdrawal'   => 100.00,
            'slug'             => 'promotor-tenant-4',
            'referral_code'    => 'PROMO4',
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    $affiliate = Affiliate::where('tenant_id', $tenant->id)->first();
    expect($affiliate)->not->toBeNull();
    expect((float) $affiliate->commission_pct)->toBe(25.00);
    expect((float) $affiliate->discount_pct)->toBe(15.00);
    expect($affiliate->referral_code)->toBe('PROMO4');
});

it('renderiza o painel central de afiliados com graficos e kpis interativos', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = createTestAdminUser();

    $this->actingAs($admin, 'admin')
        ->get('/admin/affiliates')
        ->assertOk()
        ->assertSee('Programa de Afiliados')
        ->assertSee('chartClicksConversions')
        ->assertSee('chartDailyCommissions')
        ->assertSee('Configurações Globais do Programa');
});

it('atualiza configuracoes globais do programa de afiliados', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = createTestAdminUser();

    $response = $this->actingAs($admin, 'admin')
        ->postJson('/admin/affiliates/settings', [
            'enabled'                  => true,
            'commission_mode'          => 'percentage',
            'default_commission_pct'   => 30.00,
            'default_commission_fixed' => 0,
            'default_commission_type'  => 'recurring',
            'buyer_discount_enabled'   => true,
            'buyer_discount_mode'      => 'percentage',
            'buyer_discount_pct'       => 12.50,
            'buyer_discount_fixed'     => 0,
            'buyer_discount_type'      => 'first_payment',
            'cookie_days'              => 90,
            'min_withdrawal'           => 60.00,
            'auto_approve_days'        => 5,
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
        ]);
});


it('redireciona link de afiliado /a/{slug} para a pagina de promocao com parametros ref e cupom', function () {
    $tenant = createTestTenantWithPlan();
    $affiliate = Affiliate::create([
        'user_id'          => $tenant->user_id,
        'tenant_id'        => $tenant->id,
        'slug'             => 'parceiro-vip-' . Str::random(4),
        'referral_code'    => 'CUPOMVIP',
        'status'           => 'active',
        'discount_mode'    => 'percentage',
        'discount_pct'     => 15.00,
        'discount_type'    => 'first_payment',
    ]);

    $response = $this->get('/a/' . $affiliate->slug);

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('/promocao');
    expect($targetUrl)->toContain('ref=' . $affiliate->slug);
    expect($targetUrl)->toContain('cupom=CUPOMVIP');
});

it('exibe cupom e desconto sugerido do afiliado na pagina de promocao', function () {
    $tenant = createTestTenantWithPlan();
    $affiliate = Affiliate::create([
        'user_id'          => $tenant->user_id,
        'tenant_id'        => $tenant->id,
        'slug'             => 'divulgador-' . Str::random(4),
        'referral_code'    => 'DESCONTO20',
        'status'           => 'active',
        'discount_mode'    => 'percentage',
        'discount_pct'     => 20.00,
        'discount_type'    => 'first_payment',
    ]);

    $response = $this->get('/promocao?ref=' . $affiliate->slug . '&cupom=DESCONTO20');

    $response->assertOk();
    $response->assertSee('DESCONTO20');
    $response->assertSee('20,0%');
});

it('carrega cupom no cadastro e aplica desconto de afiliado na primeira mensalidade do tenant', function () {
    $referrerTenant = createTestTenantWithPlan();
    $affiliate = Affiliate::create([
        'user_id'          => $referrerTenant->user_id,
        'tenant_id'        => $referrerTenant->id,
        'slug'             => 'afiliado-top-' . Str::random(4),
        'referral_code'    => 'CUPOMTOP10',
        'status'           => 'active',
        'discount_mode'    => 'percentage',
        'discount_pct'     => 10.00,
        'discount_type'    => 'first_payment',
    ]);

    // 1. Tela de cadastro exibe cupom ativo
    $showResp = $this->get('/cadastro?ref=' . $affiliate->slug . '&cupom=CUPOMTOP10');
    $showResp->assertOk();
    $showResp->assertSee('CUPOMTOP10');
    $showResp->assertSee('10,0%');

    // 2. Realiza o cadastro passando cupom
    $email = 'novo-cliente-' . Str::random(6) . '@example.com';
    $regResp = $this->post('/cadastro', [
        'name'                  => 'Novo Cliente Com Cupom',
        'email'                 => $email,
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'lgpd_accepted'         => '1',
        'ref'                   => $affiliate->slug,
        'cupom'                 => 'CUPOMTOP10',
    ]);

    $regResp->assertRedirect('/painel');

    $newTenant = Tenant::whereHas('user', fn ($q) => $q->where('email', $email))->first();
    expect($newTenant)->not->toBeNull();

    // 3. Verifica se a conversão do afiliado foi registrada
    $referral = \App\Modules\Affiliates\Models\AffiliateReferral::where('tenant_id', $newTenant->id)->first();
    expect($referral)->not->toBeNull();
    expect($referral->affiliate_id)->toBe($affiliate->id);

    // 4. Calcula o desconto e valida que deduz 10% na 1a mensalidade
    $discount = app(\App\Modules\Affiliates\Services\AffiliateService::class)->calculateReferralDiscount($newTenant, 100.00);
    expect($discount)->not->toBeNull();
    expect($discount['discount_amount'])->toBe(10.00);
    expect($discount['description'])->toContain('CUPOMTOP10');
});
