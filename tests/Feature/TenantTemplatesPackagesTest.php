<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Chatbot\Services\ChatbotEngine;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\ExtraInstancePrice;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Models\TenantPackage;
use App\Modules\Tenancy\Services\TenantBillingService;
use App\Modules\Tenancy\Services\TenantPackageService;
use App\Modules\Tenancy\Services\TenantTemplateResolver;

function createTenantForPackages(): array
{
    $plan = Plan::create([
        'name' => 'Plano Comercial',
        'slug' => 'plano-comercial-'.uniqid(),
        'price' => 97.90,
        'extra_instance_price' => 39.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
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
        'name' => 'Tenant Comercial',
        'email' => 'tenant-comercial-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Comercial',
        'slug' => 'tenant-comercial-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_comercial_'.$tenant->id,
        'display_name' => 'Principal',
        'status' => 'connected',
        'is_default' => true,
    ]);

    return [$user, $tenant, $plan];
}

it('permite definir template whatsapp padrao sem permitir excluir template do sistema', function () {
    [$user, $tenant] = createTenantForPackages();

    $system = WhatsAppTemplate::create([
        'name' => 'Sistema lembrete',
        'slug' => 'sistema-lembrete-'.uniqid(),
        'category' => 'lembrete',
        'schedule_offset' => '-3d',
        'content_html' => 'Sistema {nome}',
        'content_text' => 'Sistema {nome}',
        'active' => true,
        'scope' => 'system',
        'is_system' => true,
        'locked' => true,
    ]);

    $custom = WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant lembrete',
        'slug' => 'tenant-lembrete-'.uniqid(),
        'category' => 'lembrete',
        'schedule_offset' => '-3d',
        'content_html' => 'Tenant {nome}',
        'content_text' => 'Tenant {nome}',
        'active' => true,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('tenant.templates.destroy', $system->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('tenant.templates.default', $system->id))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(app(TenantTemplateResolver::class)->whatsapp($tenant->id, '-3d', 'lembrete')?->id)
        ->toBe($system->id);

    $this->actingAs($user)
        ->postJson(route('tenant.templates.default', $custom->id))
        ->assertOk();

    expect(app(TenantTemplateResolver::class)->whatsapp($tenant->id, '-3d', 'lembrete')?->id)
        ->toBe($custom->id);
});

it('usa tabela do superadmin para cotar instancia extra', function () {
    [, $tenant, $plan] = createTenantForPackages();

    ExtraInstancePrice::create([
        'plan_id' => $plan->id,
        'name' => 'Extra premium',
        'min_quantity' => 1,
        'price' => 59.90,
        'billing_cycle' => 'monthly',
        'active' => true,
    ]);

    $quote = app(TenantBillingService::class)->quoteExtraInstance($tenant);

    expect($quote['recurring_amount'])->toBe(59.90)
        ->and($quote['price_table_name'])->toBe('Extra premium');
});

it('cria pacote parcelado, gera faturas e responde consulta via whatsapp', function () {
    [, $tenant] = createTenantForPackages();

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Pacote',
        'email' => 'cliente-pacote@example.com',
        'document' => '12345678901',
        'phone' => '21999998888',
        'whatsapp' => '5521999998888',
        'active' => true,
    ]);

    $package = TenantPackage::create([
        'tenant_id' => $tenant->id,
        'name' => 'Marketing 360',
        'description' => 'Servico mensal completo',
        'total_amount' => 300,
        'allow_cash' => true,
        'allow_installments' => true,
        'max_installments' => 3,
        'gateway_fee_mode' => 'customer',
        'gateway_fee_percent' => 2,
        'gateway_fixed_fee' => 3,
        'installment_interest_percent' => 1,
        'active' => true,
    ]);

    $subscription = app(TenantPackageService::class)->assignToCustomer(
        $tenant,
        $customer,
        $package,
        'installment',
        3,
        now()->addDay()
    );

    expect($subscription->installments)->toHaveCount(3)
        ->and(Invoice::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->count())->toBe(3)
        ->and((float) $subscription->final_amount)->toBe(315.00);

    $reply = app(ChatbotEngine::class)->processMessage($customer->whatsapp, 'parcelas', $tenant->id);

    expect($reply)->toContain('Pendentes: 3')
        ->and($reply)->toContain('Em aberto: R$ 315,00');

    $invoice = Invoice::where('customer_id', $customer->id)->orderBy('due_date')->first();
    $invoice->update(['status' => 'paid', 'paid_at' => now()]);
    app(TenantPackageService::class)->confirmInvoicePayment($invoice->id, 'manual', 'tx-1');

    $subscription->refresh();

    $installment2 = $subscription->installments()->where('installment_number', 2)->first();

    expect($subscription->installments()->where('status', 'paid')->count())->toBe(1)
        ->and($subscription->status)->toBe('active')
        ->and($subscription->next_due_date?->toDateString())->toBe($installment2->due_date?->toDateString());
});
