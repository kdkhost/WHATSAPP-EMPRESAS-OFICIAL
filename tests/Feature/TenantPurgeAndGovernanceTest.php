<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\TenantPurgeService;

function tenantGovernanceAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin Tenancy Governance',
        'email' => 'admin-tenancy-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);
}

function createTenantForGovernance(string $phone = '5511999998888', string $doc = '12345678901'): array
{
    $plan = Plan::create([
        'name' => 'Plano Governança',
        'slug' => 'plano-gov-'.uniqid(),
        'price' => 99.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 0,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => true,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Usuario Tenant',
        'email' => 'user-tenant-'.uniqid().'@example.com',
        'password' => 'secret',
        'phone' => $phone,
        'document' => $doc,
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Tenant ' . uniqid(),
        'slug' => 'empresa-tenant-' . uniqid(),
        'status' => 'active',
    ]);

    return [$user, $tenant, $plan];
}

it('exibe listagem de tenants com status, kpis e detecção de nacionalidade', function () {
    [$brUser, $brTenant] = createTenantForGovernance('5511999998888', '12345678901');
    [$nonBrUser, $nonBrTenant] = createTenantForGovernance('+12025550199', 'US-PASSPORT-99');

    $admin = tenantGovernanceAdmin();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.tenancy.tenants'))
        ->assertOk()
        ->assertSee('Gestão de Tenants')
        ->assertSee($brTenant->company_name)
        ->assertSee($nonBrTenant->company_name)
        ->assertSee('Brasil (+55)')
        ->assertSee('Não-Brasileiro');
});

it('permite alternar status de tenant rapidamente via toggle status', function () {
    [, $tenant] = createTenantForGovernance();

    expect($tenant->status)->toBe('active');

    $admin = tenantGovernanceAdmin();

    // Alterna para suspenso
    $res1 = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.tenancy.tenants.toggle-status', $tenant->id))
        ->assertOk()
        ->json();

    expect($res1['ok'])->toBeTrue()
        ->and($res1['status'])->toBe('suspended')
        ->and($tenant->fresh()->status)->toBe('suspended');

    // Alterna de volta para ativo
    $res2 = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.tenancy.tenants.toggle-status', $tenant->id))
        ->assertOk()
        ->json();

    expect($res2['ok'])->toBeTrue()
        ->and($res2['status'])->toBe('active')
        ->and($tenant->fresh()->status)->toBe('active');
});

it('deleta conta de tenant e expurga tudo vinculado a ele de forma completa', function () {
    [$user, $tenant] = createTenantForGovernance();

    // Cria faturas e clientes vinculados ao tenant
    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente do Tenant',
        'email' => 'cliente-tenant@example.com',
        'whatsapp' => '5511977776666',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-PURGE-'.uniqid(),
        'description' => 'Serviço',
        'amount' => 100,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $admin = tenantGovernanceAdmin();

    $res = $this->actingAs($admin, 'admin')
        ->deleteJson(route('admin.tenancy.tenants.destroy', $tenant->id))
        ->assertOk()
        ->json();

    expect($res['ok'])->toBeTrue()
        ->and(Tenant::whereKey($tenant->id)->exists())->toBeFalse()
        ->and(User::whereKey($user->id)->exists())->toBeFalse()
        ->and(Customer::whereKey($customer->id)->exists())->toBeFalse()
        ->and(Invoice::whereKey($invoice->id)->exists())->toBeFalse();
});

it('identifica e remove em lote tenants e usuarios nao brasileiros', function () {
    [$brUser, $brTenant] = createTenantForGovernance('5511999998888', '12345678901');
    [$nonBrUser, $nonBrTenant] = createTenantForGovernance('+442079460912', 'GB-REG-12345');

    $admin = tenantGovernanceAdmin();

    $res = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.tenancy.tenants.purge-non-brazilians'))
        ->assertOk()
        ->json();

    expect($res['ok'])->toBeTrue()
        ->and($res['total'])->toBeGreaterThanOrEqual(1)
        ->and(Tenant::whereKey($nonBrTenant->id)->exists())->toBeFalse()
        ->and(User::whereKey($nonBrUser->id)->exists())->toBeFalse()
        ->and(Tenant::whereKey($brTenant->id)->exists())->toBeTrue()
        ->and(User::whereKey($brUser->id)->exists())->toBeTrue();
});
