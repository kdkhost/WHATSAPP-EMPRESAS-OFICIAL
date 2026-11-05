<?php

use App\Models\BillingDispatch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createTenantInvoiceSetup(): array
{
    $plan = Plan::create([
        'name' => 'Plano Actions Test',
        'slug' => 'plano-actions-'.uniqid(),
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
        'name' => 'Tenant User Actions',
        'email' => 'tenant-actions-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Ações Fatura Ltda',
        'slug' => 'tenant-act-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Ações Teste',
        'email' => 'cliente-actions-'.uniqid().'@example.com',
        'whatsapp' => '5511999997777',
        'phone' => '11999997777',
        'document' => '12345678909',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-ACT-' . strtoupper(uniqid()),
        'amount' => 75.00,
        'status' => 'pending',
        'due_date' => now()->addDays(5)->toDateString(),
        'description' => 'Cobrança teste de ações',
        'meta' => [
            'is_standalone' => true,
            'pix_copia_cola' => '00020101021226580014br.gov.bcb.pix',
        ],
    ]);

    $template = WhatsAppTemplate::firstWhere('slug', 'cobranca-gerada')
        ?? WhatsAppTemplate::firstOrCreate(
            ['slug' => 'cobranca-gerada-' . uniqid()],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Template Teste Ações',
                'category' => 'cobranca',
                'schedule_offset' => 'manual',
                'content_text' => 'Olá {cliente_nome}, fatura {fatura_numero} no valor de {fatura_valor}.',
                'content_html' => '<p>Olá {cliente_nome}, fatura {fatura_numero} no valor de {fatura_valor}.</p>',
                'active' => true,
                'is_system' => false,
                'is_default' => false,
                'locked' => false,
            ]
        );

    return compact('tenant', 'user', 'customer', 'invoice', 'template');
}

test('tenant consegue cancelar fatura pendente e cancela dispatches agendados em cascata', function () {
    $setup = createTenantInvoiceSetup();

    // Cria dispatches agendados para a fatura
    $dispatch = BillingDispatch::create([
        'invoice_id' => $setup['invoice']->id,
        'template_id' => $setup['template']->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '0',
        'scheduled_at' => now()->addDays(1),
        'status' => 'scheduled',
    ]);

    $response = $this->actingAs($setup['user'])
        ->postJson(route('tenant.invoices.cancel', $setup['invoice']->id));

    $response->assertOk()
        ->assertJson(['ok' => true]);

    $setup['invoice']->refresh();
    expect($setup['invoice']->status)->toBe('canceled');

    $dispatch->refresh();
    expect($dispatch->status)->toBe('cancelled');
});

test('tenant consegue reenviar cobranca manual com sucesso via rota ajax', function () {
    $setup = createTenantInvoiceSetup();

    $response = $this->actingAs($setup['user'])
        ->postJson(route('tenant.invoices.resend', $setup['invoice']->id));

    $response->assertOk()
        ->assertJson(['ok' => true]);

    // Verifica se um novo dispatch manual foi criado
    $manualDispatch = BillingDispatch::where('invoice_id', $setup['invoice']->id)
        ->where('schedule_offset', 'manual')
        ->latest('id')
        ->first();

    expect($manualDispatch)->not->toBeNull();
});

test('tenant nao pode reenviar cobranca de fatura cancelada', function () {
    $setup = createTenantInvoiceSetup();
    $setup['invoice']->update(['status' => 'canceled']);

    $response = $this->actingAs($setup['user'])
        ->postJson(route('tenant.invoices.resend', $setup['invoice']->id));

    $response->assertStatus(422)
        ->assertJson(['ok' => false]);
});
