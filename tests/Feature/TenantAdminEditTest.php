<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;

it('permite ao superadmin editar tenant e liberar API REST comercial', function () {
    $admin = AdminUser::create([
        'name' => 'Superadmin Tenants',
        'email' => 'superadmin-tenants@example.com',
        'password' => bcrypt('password'),
        'role' => 'superadmin',
        'active' => true,
    ]);

    $plan = Plan::create([
        'name' => 'Starter Teste',
        'slug' => 'starter-teste',
        'price' => 49.90,
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
        'name' => 'Tenant Antigo',
        'email' => 'tenant-antigo@example.com',
        'password' => bcrypt('password'),
    ]);
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Antiga',
        'slug' => 'empresa-antiga',
        'status' => 'trial',
    ]);

    $this->actingAs($admin, 'admin')
        ->putJson(route('admin.tenancy.tenants.update', $tenant->id), [
            'company_name' => 'Empresa Nova',
            'slug' => 'empresa-nova',
            'plan_id' => $plan->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => '2026-05-01',
            'current_period_end' => '2026-05-31',
            'gateway' => 'mercadopago',
            'user_name' => 'Tenant Novo',
            'user_email' => 'tenant-novo@example.com',
            'api_enabled' => true,
            'api_monthly_fee' => '79,90',
            'api_notes' => 'API contratada mensalmente.',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $tenant->refresh();
    $user->refresh();

    expect($tenant->company_name)->toBe('Empresa Nova')
        ->and($tenant->status)->toBe('active')
        ->and($tenant->publicApiEnabled())->toBeTrue()
        ->and($tenant->publicApiMonthlyFee())->toBe(79.9)
        ->and($user->email)->toBe('tenant-novo@example.com');
});
