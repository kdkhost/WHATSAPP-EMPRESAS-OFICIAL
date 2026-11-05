<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createTenantForStandaloneInvoice(): array
{
    $plan = Plan::create([
        'name' => 'Plano Standalone Test',
        'slug' => 'plano-standalone-'.uniqid(),
        'price' => 99.90,
        'extra_instance_price' => 29.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 2,
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
        'name' => 'Tenant User Standalone',
        'email' => 'tenant-standalone-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Cobrança Avulsa Ltda',
        'slug' => 'tenant-sa-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Teste Avulso',
        'email' => 'cliente-avulso-'.uniqid().'@example.com',
        'whatsapp' => '5511999998888',
        'phone' => '11999998888',
        'document' => '12345678909',
        'active' => true,
    ]);

    $gateway = TenantGateway::create([
        'tenant_id' => $tenant->id,
        'gateway' => 'mercadopago',
        'credentials' => ['access_token' => 'TEST-123456789'],
        'settings' => [],
        'payment_methods' => ['pix'],
        'fees' => [],
        'split_rules' => [],
        'installment_rules' => [],
        'environment' => 'sandbox',
        'priority' => 1,
        'active' => true,
        'is_default' => true,
    ]);

    return compact('user', 'tenant', 'customer', 'gateway');
}

test('painel de faturas do tenant exibe clientes e gateways para cobranca avulsa', function () {
    $ctx = createTenantForStandaloneInvoice();

    $response = $this->actingAs($ctx['user'])->get(route('tenant.invoices'));

    $response->assertStatus(200);
    $response->assertSee('Nova Fatura Avulsa');
    $response->assertSee('modal-create-standalone-invoice');
    $response->assertSee($ctx['customer']->name);
});

test('tenant consegue criar fatura avulsa a vista com sucesso', function () {
    $ctx = createTenantForStandaloneInvoice();

    $payload = [
        'customer_id'    => $ctx['customer']->id,
        'description'    => 'Desenvolvimento de Landing Page',
        'amount'         => '1.500,00',
        'installments'   => 1,
        'interest_rate'  => 0,
        'first_due_date' => now()->addDays(3)->toDateString(),
        'gateway'        => 'mercadopago',
        'send_now'       => 0,
    ];

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.standalone.store'), $payload);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'count' => 1,
    ]);

    $invoice = Invoice::where('tenant_id', $ctx['tenant']->id)
        ->where('customer_id', $ctx['customer']->id)
        ->latest('id')
        ->first();

    expect($invoice)->not->toBeNull();
    expect((float) $invoice->amount)->toBe(1500.00);
    expect($invoice->status)->toBe('pending');
    expect(Str::startsWith($invoice->number, 'AV-'))->toBeTrue();
    expect($invoice->meta['is_standalone'])->toBeTrue();
    expect($invoice->meta['installment'])->toBe(1);
    expect($invoice->meta['total_installments'])->toBe(1);
});

test('tenant consegue criar carne parcelado com calculo de juros', function () {
    $ctx = createTenantForStandaloneInvoice();

    $payload = [
        'customer_id'    => $ctx['customer']->id,
        'description'    => 'Servico de Assessoria Trimestral',
        'amount'         => '300,00',
        'installments'   => 3,
        'interest_rate'  => 5.0, // 5% ao mês em juros simples
        'first_due_date' => now()->addDays(5)->toDateString(),
        'gateway'        => 'mercadopago',
        'send_now'       => 0,
    ];

    // Calculo: Base 300, Juros = 300 * (5/100) * 3 = 45. Total = 345. Cada parcela = 115.00
    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.standalone.store'), $payload);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'count' => 3,
    ]);

    $invoices = Invoice::where('tenant_id', $ctx['tenant']->id)
        ->where('customer_id', $ctx['customer']->id)
        ->orderBy('id')
        ->get();

    expect($invoices)->toHaveCount(3);

    $batchId = $invoices->first()->meta['batch_id'] ?? null;
    expect($batchId)->not->toBeNull();

    foreach ($invoices as $idx => $inv) {
        $num = $idx + 1;
        expect((float) $inv->amount)->toBe(115.00);
        expect($inv->meta['is_standalone'])->toBeTrue();
        expect($inv->meta['installment'])->toBe($num);
        expect($inv->meta['total_installments'])->toBe(3);
        expect($inv->meta['batch_id'])->toBe($batchId);
        expect($inv->description)->toContain("(Parcela {$num}/3)");
    }
});

test('tenant nao pode criar fatura avulsa para cliente de outro tenant', function () {
    $ctx1 = createTenantForStandaloneInvoice();
    $ctx2 = createTenantForStandaloneInvoice();

    $payload = [
        'customer_id'    => $ctx2['customer']->id, // Cliente pertence a ctx2
        'description'    => 'Tentativa cross-tenant',
        'amount'         => '100,00',
        'installments'   => 1,
        'first_due_date' => now()->addDay()->toDateString(),
    ];

    // O tenant 1 tenta faturar o cliente do tenant 2
    $response = $this->actingAs($ctx1['user'])->postJson(route('tenant.invoices.standalone.store'), $payload);

    $response->assertStatus(404);
});
