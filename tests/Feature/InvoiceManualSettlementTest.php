<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\BillingDispatch;
use App\Modules\Billing\Services\InvoiceSettlementService;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createTenantForManualSettlement(): array
{
    $plan = Plan::create([
        'name' => 'Plano Settle Test',
        'slug' => 'plano-settle-'.uniqid(),
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
        'name' => 'Tenant User Settle',
        'email' => 'tenant-settle-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Settle Ltda',
        'slug' => 'tenant-settle-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Teste Settle',
        'email' => 'cliente-settle-'.uniqid().'@example.com',
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
        'payment_methods' => ['pix', 'boleto'],
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

test('tenant consegue dar baixa manual em fatura individual', function () {
    $ctx = createTenantForManualSettlement();

    $invoice = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'FAT-TEST-'.uniqid(),
        'description' => 'Fatura de Teste Individual',
        'amount' => 150.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
    ]);

    // Criar dispatch agendado para garantir que é cancelado
    BillingDispatch::create([
        'invoice_id' => $invoice->id,
        'schedule_offset' => 'manual',
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.settle', ['id' => $invoice->id]), [
        'gateway' => 'pix',
        'paid_at' => now()->toDateString(),
        'notes' => 'Recebido via Pix presencial',
        'notify_customer' => false,
        'settle_mode' => 'single',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->gateway)->toBe('pix');
    expect($invoice->paid_at)->not->toBeNull();

    // Dispatch deve ter sido cancelado
    $dispatch = BillingDispatch::where('invoice_id', $invoice->id)->first();
    expect($dispatch->status)->toBe('canceled');
});

test('retorna contexto de parcelamento com faturas normais e antecipadas', function () {
    $ctx = createTenantForManualSettlement();
    $batchId = Str::uuid()->toString();

    // Criar 3 parcelas de um carnê
    $inv1 = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'PARC-1-'.uniqid(),
        'description' => 'Parcela 1/3',
        'amount' => 100.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
        'meta' => [
            'batch_id' => $batchId,
            'installment' => 1,
            'total_installments' => 3,
        ],
    ]);

    $inv2 = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'PARC-2-'.uniqid(),
        'description' => 'Parcela 2/3',
        'amount' => 100.00,
        'due_date' => now()->toDateString(), // Antecipada para hoje
        'status' => 'pending',
        'meta' => [
            'batch_id' => $batchId,
            'installment' => 2,
            'total_installments' => 3,
            'is_antecipated' => true,
        ],
    ]);

    $inv3 = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'PARC-3-'.uniqid(),
        'description' => 'Parcela 3/3',
        'amount' => 100.00,
        'due_date' => now()->addDays(35)->toDateString(),
        'status' => 'pending',
        'meta' => [
            'batch_id' => $batchId,
            'installment' => 3,
            'total_installments' => 3,
        ],
    ]);

    $response = $this->actingAs($ctx['user'])->getJson(route('tenant.invoices.installment-context', ['id' => $inv1->id]));

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'context' => [
            'is_installment' => true,
            'total_installments' => 3,
            'open_count' => 2,
            'antecipated_count' => 1,
        ],
    ]);
});

test('tenant consegue dar baixa em fatura juntamente com parcelas antecipadas', function () {
    $ctx = createTenantForManualSettlement();
    $batchId = Str::uuid()->toString();

    $inv1 = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'PARC-1-'.uniqid(),
        'description' => 'Parcela 1/3',
        'amount' => 120.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
        'meta' => [
            'batch_id' => $batchId,
            'installment' => 1,
            'total_installments' => 3,
        ],
    ]);

    $inv2 = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'PARC-2-'.uniqid(),
        'description' => 'Parcela 2/3',
        'amount' => 120.00,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
        'meta' => [
            'batch_id' => $batchId,
            'installment' => 2,
            'total_installments' => 3,
            'is_antecipated' => true,
        ],
    ]);

    $inv3 = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'PARC-3-'.uniqid(),
        'description' => 'Parcela 3/3',
        'amount' => 120.00,
        'due_date' => now()->addDays(40)->toDateString(),
        'status' => 'pending',
        'meta' => [
            'batch_id' => $batchId,
            'installment' => 3,
            'total_installments' => 3,
        ],
    ]);

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.settle', ['id' => $inv1->id]), [
        'gateway' => 'dinheiro',
        'settle_mode' => 'antecipated',
        'notify_customer' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'count' => 2, // inv1 e inv2 antecipada
    ]);

    $inv1->refresh();
    $inv2->refresh();
    $inv3->refresh();

    expect($inv1->status)->toBe('paid');
    expect($inv2->status)->toBe('paid');
    expect($inv3->status)->toBe('pending'); // Parcela 3 ainda pendente
});

test('tenant consegue dar baixa em lote em faturas selecionadas', function () {
    $ctx = createTenantForManualSettlement();

    $invA = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'BULK-1-'.uniqid(),
        'description' => 'Fatura Bulk A',
        'amount' => 50.00,
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'pending',
    ]);

    $invB = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'BULK-2-'.uniqid(),
        'description' => 'Fatura Bulk B',
        'amount' => 75.00,
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.bulk'), [
        'action' => 'settle',
        'ids' => [$invA->id, $invB->id],
        'gateway' => 'cartao',
        'paid_at' => now()->toDateString(),
        'notes' => 'Baixa em lote via balcão',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'count' => 2,
    ]);

    $invA->refresh();
    $invB->refresh();

    expect($invA->status)->toBe('paid');
    expect($invB->status)->toBe('paid');
    expect($invA->gateway)->toBe('cartao');
    expect($invB->gateway)->toBe('cartao');
});

test('admin consegue dar baixa manual em fatura individual', function () {
    $this->withoutMiddleware(\App\Http\Middleware\Ensure2FA::class);

    $admin = \App\Models\AdminUser::create([
        'name' => 'Admin Settle',
        'email' => 'admin-settle-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Admin Settle',
        'whatsapp' => '5511999997777',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'ADM-FAT-'.uniqid(),
        'description' => 'Serviço Admin Individual',
        'amount' => 200.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')->postJson(route('admin.billing.invoices.settle', ['id' => $invoice->id]), [
        'gateway' => 'pix',
        'paid_at' => now()->toDateString(),
        'notes' => 'Recebido pelo admin via Pix',
        'notify_customer' => false,
        'settle_mode' => 'single',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->gateway)->toBe('pix');
});

test('admin consegue dar baixa em lote em faturas selecionadas', function () {
    $this->withoutMiddleware(\App\Http\Middleware\Ensure2FA::class);

    $admin = \App\Models\AdminUser::create([
        'name' => 'Admin Bulk Settle',
        'email' => 'admin-bulk-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Admin Bulk',
        'whatsapp' => '5511999996666',
        'active' => true,
    ]);

    $invA = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'ADM-BULK-1-'.uniqid(),
        'description' => 'Admin Bulk A',
        'amount' => 80.00,
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'pending',
    ]);

    $invB = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'ADM-BULK-2-'.uniqid(),
        'description' => 'Admin Bulk B',
        'amount' => 95.00,
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')->postJson(route('admin.billing.invoices.bulk-settle'), [
        'ids' => [$invA->id, $invB->id],
        'gateway' => 'transferencia',
        'paid_at' => now()->toDateString(),
        'notes' => 'Transferência conferida',
        'notify_customer' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'count' => 2,
    ]);

    $invA->refresh();
    $invB->refresh();

    expect($invA->status)->toBe('paid');
    expect($invB->status)->toBe('paid');
    expect($invA->gateway)->toBe('transferencia');
    expect($invB->gateway)->toBe('transferencia');
});

test('tenant da baixa manual com notify_customer=true e dispara mensagem de confirmacao WhatsApp', function () {
    $ctx = createTenantForManualSettlement();

    $invoice = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'FAT-NOTIFY-'.uniqid(),
        'description' => 'Fatura com Notificação WhatsApp',
        'amount' => 180.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response(['key' => ['id' => 'EVO-MSG-123']], 200),
    ]);

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.settle', ['id' => $invoice->id]), [
        'gateway' => 'pix',
        'paid_at' => now()->toDateString(),
        'notes' => 'Pago no caixa',
        'notify_customer' => true,
        'settle_mode' => 'single',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');

    $msg = \App\Models\WhatsAppMessage::where('to', '5511999998888')->latest('id')->first();
    expect($msg)->not->toBeNull();
    expect(strtolower($msg->message))->toContain('pagamento');
});

test('tenant da baixa manual em fatura de parcela de pacote e atualiza ciclo e dispara confirmacao', function () {
    $ctx = createTenantForManualSettlement();

    $package = \App\Modules\Tenancy\Models\TenantPackage::create([
        'tenant_id' => $ctx['tenant']->id,
        'name' => 'Plano Internet Fibra',
        'total_amount' => 99.00,
        'active' => true,
    ]);

    $sub = \App\Modules\Tenancy\Models\CustomerPackageSubscription::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'tenant_package_id' => $package->id,
        'status' => 'active',
        'total_amount' => 99.00,
        'final_amount' => 99.00,
        'payment_mode' => 'installments',
        'installments_count' => 1,
        'start_date' => now()->toDateString(),
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $ctx['tenant']->id,
        'customer_id' => $ctx['customer']->id,
        'number' => 'FAT-PKG-'.uniqid(),
        'description' => 'Parcela 1/1 - Internet Fibra',
        'amount' => 99.00,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $installment = \App\Modules\Tenancy\Models\CustomerPackageInstallment::create([
        'subscription_id' => $sub->id,
        'customer_id' => $ctx['customer']->id,
        'tenant_id' => $ctx['tenant']->id,
        'invoice_id' => $invoice->id,
        'installment_number' => 1,
        'amount' => 99.00,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response(['key' => ['id' => 'EVO-PKG-456']], 200),
    ]);

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.settle', ['id' => $invoice->id]), [
        'gateway' => 'dinheiro',
        'paid_at' => now()->toDateString(),
        'notify_customer' => true,
        'settle_mode' => 'single',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');

    $installment->refresh();
    expect($installment->status)->toBe('paid');

    $sub->refresh();
    expect($sub->status)->toBe('completed');

    $msg = \App\Models\WhatsAppMessage::where('to', '5511999998888')->latest('id')->first();
    expect($msg)->not->toBeNull();
    expect($msg->message)->toContain('Internet Fibra');
});


