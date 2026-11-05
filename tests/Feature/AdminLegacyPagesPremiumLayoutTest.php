<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;

function premiumLayoutAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin Premium Legacy',
        'email' => 'admin-premium-legacy@example.com',
        'password' => bcrypt('password'),
        'role' => 'superadmin',
        'active' => true,
    ]);
}

function premiumLayoutTenant(): Tenant
{
    $plan = Plan::create([
        'name' => 'Plano Premium',
        'slug' => 'plano-premium',
        'price' => 99.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => false,
        'custom_templates' => true,
        'installment_enabled' => false,
        'fee_passthrough' => false,
        'features' => ['Dashboard'],
        'active' => true,
        'sort' => 1,
    ]);

    $user = User::create([
        'name' => 'Tenant Premium',
        'email' => 'tenant-premium-layout@example.com',
        'password' => bcrypt('password'),
    ]);

    return Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Premium',
        'slug' => 'tenant-premium',
        'status' => 'trial',
    ]);
}

it('renderiza paginas administrativas antigas com o cabecalho premium global', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = premiumLayoutAdmin();
    $tenant = premiumLayoutTenant();

    $paths = [
        '/admin/security' => 'Segurança do sistema',
        '/admin/security/events' => 'Eventos de segurança',
        '/admin/security/blocklist' => 'Blocklist',
        '/admin/security/allowlist' => 'Allowlist',
        '/admin/tenancy' => 'Planos e Tenants',
        '/admin/tenancy/tenants/'.$tenant->id.'/edit' => 'Editar tenant',
        '/admin/affiliates' => 'Programa de Afiliados',
        '/admin/holidays' => 'Feriados',
        '/admin/whmcs' => 'Integração WHMCS',
        '/admin/cpanel' => 'WHM/cPanel',
        '/admin/logs' => 'Logs do Sistema',
        '/admin/cache' => 'Cache do Sistema',
    ];

    foreach ($paths as $path => $title) {
        $this->actingAs($admin, 'admin')
            ->get($path)
            ->assertOk()
            ->assertSee('admin-premium-hero')
            ->assertSee($title);
    }
});

it('renderiza cabecalhos premium sem coluna vazia nem card de acao artificial', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = premiumLayoutAdmin();

    $this->actingAs($admin, 'admin')
        ->get('/admin/affiliates')
        ->assertOk()
        ->assertSee('admin-premium-hero--simple')
        ->assertDontSee('<div class="dashboard-hero-notes"', false);

    $this->actingAs($admin, 'admin')
        ->get('/admin/whmcs')
        ->assertOk()
        ->assertSee('admin-premium-hero--with-actions')
        ->assertSee('admin-premium-hero__actions')
        ->assertDontSee('admin-hero-note d-flex', false);
});
