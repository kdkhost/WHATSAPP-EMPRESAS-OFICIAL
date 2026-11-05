<?php

use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Modules\Tenancy\Models\TenantInvoice;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createTenantForPlanInvoice(): array
{
    $plan = Plan::create([
        'name' => 'Plano Plan Inv Test',
        'slug' => 'plano-pi-'.uniqid(),
        'price' => 149.90,
        'extra_instance_price' => 29.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 2,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Tenant User Plan Inv',
        'email' => 'tenant-pi-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Plan Inv Ltda',
        'slug' => 'tenant-pi-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    TenantGateway::create([
        'tenant_id' => $tenant->id,
        'gateway' => 'mercadopago',
        'active' => true,
        'credentials' => ['access_token' => 'TEST_TOKEN'],
    ]);

    $tenantInvoice = TenantInvoice::create([
        'tenant_id' => $tenant->id,
        'number' => 'TI-'.str_pad(mt_rand(1, 99999), 6, '0', STR_PAD_LEFT),
        'amount' => 149.90,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
        'meta' => [
            'description' => 'Mensalidade do Plano Pro',
            'payment_method' => 'pix',
            'items' => [
                ['description' => 'Assinatura Plano Pro', 'amount' => 149.90],
            ],
        ],
    ]);

    return [$user, $tenant, $tenantInvoice];
}

test('dashboard do painel carrega com sucesso sem erro 500', function () {
    [$user, $tenant, $tenantInvoice] = createTenantForPlanInvoice();

    $response = $this->actingAs($user)->get(route('tenant.dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Saúde da Conta');
});

test('tenant consegue visualizar detalhes da fatura do seu plano', function () {
    [$user, $tenant, $tenantInvoice] = createTenantForPlanInvoice();

    $response = $this->actingAs($user)->getJson(route('tenant.invoices.plan.show', $tenantInvoice->id));

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'id' => $tenantInvoice->id,
        'number' => $tenantInvoice->number,
        'formatted_amount' => 'R$ 149,90',
        'status' => 'pending',
    ]);
});

test('tenant consegue solicitar cancelamento da fatura pendente do plano', function () {
    [$user, $tenant, $tenantInvoice] = createTenantForPlanInvoice();

    $response = $this->actingAs($user)->postJson(route('tenant.invoices.plan.cancel', $tenantInvoice->id), [
        'reason' => 'Upgrade de plano realizado antecipadamente',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'message' => "Fatura {$tenantInvoice->number} cancelada com sucesso.",
    ]);

    $freshInvoice = $tenantInvoice->fresh();
    expect($freshInvoice->status)->toBe('canceled');
    expect($freshInvoice->meta['cancellation_reason'])->toBe('Upgrade de plano realizado antecipadamente');
});

test('tenant consegue obter dados de pagamento da fatura do plano', function () {
    [$user, $tenant, $tenantInvoice] = createTenantForPlanInvoice();

    $response = $this->actingAs($user)->postJson(route('tenant.invoices.plan.pay', $tenantInvoice->id));

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'number' => $tenantInvoice->number,
    ]);
});
