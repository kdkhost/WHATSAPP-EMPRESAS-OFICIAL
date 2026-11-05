<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Chatbot\Services\ChatbotEngine;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Services\WhatsAppAutoService;
use Illuminate\Support\Facades\DB;

function whatsappAutoBotTenant(): array
{
    $plan = Plan::create([
        'name' => 'Plano AutoBot',
        'slug' => 'plano-autobot-'.uniqid(),
        'price' => 97.90,
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
        'name' => 'Tenant AutoBot',
        'email' => 'tenant-autobot-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant AutoBot',
        'slug' => 'tenant-autobot-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_autobot_'.$tenant->id,
        'display_name' => 'Principal',
        'status' => 'connected',
        'is_default' => true,
    ]);

    return [$tenant, $instance];
}

function whatsappAutoBotCustomer(Tenant $tenant): Customer
{
    return Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente AutoBot',
        'email' => 'cliente-autobot@example.com',
        'document' => '12345678901',
        'phone' => '11988887777',
        'whatsapp' => '5511988887777',
        'active' => true,
    ]);
}

function whatsappAutoBotInvoice(Tenant $tenant, Customer $customer): Invoice
{
    return Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-AUTO-'.uniqid(),
        'description' => 'Hospedagem Premium - Parcela 2/6',
        'amount' => 180.00,
        'due_date' => now('America/Sao_Paulo')->addDays(3)->toDateString(),
        'status' => 'pending',
        'payment_url' => 'https://pagar.exemplo.com/autobot-123',
        'webhook_token' => str_repeat('a', 48),
        'meta' => [
            'servico_principal' => 'Hospedagem Premium',
            'resumo_parcelas' => 'Parcela 2/6 - 1 paga(s), 5 pendente(s)',
            'parcela_atual' => 2,
            'parcelas_total' => 6,
            'parcelas_pagas' => 1,
            'parcelas_pendentes' => 5,
            'pix_copia_cola' => '00020101021226880014br.gov.bcb.pix2566pix.exemplo.com/autobot5204000053039865406180.005802BR5920TENANT AUTOBOT6009SAO PAULO62070503***6304ABCD',
        ],
    ]);
}

it('responde débitos, segunda via, vencimento e pix com dados da fatura', function () {
    [$tenant] = whatsappAutoBotTenant();
    $customer = whatsappAutoBotCustomer($tenant);
    $invoice = whatsappAutoBotInvoice($tenant, $customer);

    $engine = app(ChatbotEngine::class);

    $debits = $engine->processMessage($customer->whatsapp, 'consultar débitos', $tenant->id);
    expect($debits)->toContain('Débitos em aberto')
        ->and($debits)->toContain($invoice->number)
        ->and($debits)->toContain('Hospedagem Premium')
        ->and($debits)->toContain('R$ 180,00')
        ->and($debits)->toContain('Parcela 2/6');

    $secondCopy = $engine->processMessage($customer->whatsapp, 'segunda via', $tenant->id);
    expect($secondCopy)->toContain('2ª via da fatura')
        ->and($secondCopy)->toContain('https://pagar.exemplo.com/autobot-123')
        ->and($secondCopy)->toContain('Hospedagem Premium');

    $pix = $engine->processMessage($customer->whatsapp, 'código pix', $tenant->id);
    expect($pix)->toContain('Código Pix copia e cola')
        ->and($pix)->toContain('00020101021226880014br.gov.bcb.pix')
        ->and($pix)->toContain('https://pagar.exemplo.com/autobot-123');

    $dueDate = $engine->processMessage($customer->whatsapp, 'vencimento', $tenant->id);
    expect($dueDate)->toContain('Próximo vencimento')
        ->and($dueDate)->toContain($invoice->number);
});

it('responde resumo de parcelas e integra opções numéricas do menu', function () {
    [$tenant, $instance] = whatsappAutoBotTenant();
    $customer = whatsappAutoBotCustomer($tenant);
    $invoice = whatsappAutoBotInvoice($tenant, $customer);

    $packageId = DB::table('tenant_packages')->insertGetId([
        'tenant_id' => $tenant->id,
        'name' => 'Hospedagem Premium',
        'total_amount' => 720,
        'allow_cash' => true,
        'allow_installments' => true,
        'max_installments' => 6,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $subscriptionId = DB::table('customer_package_subscriptions')->insertGetId([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'tenant_package_id' => $packageId,
        'status' => 'active',
        'payment_mode' => 'installment',
        'installments_count' => 6,
        'total_amount' => 720,
        'final_amount' => 720,
        'start_date' => now()->toDateString(),
        'next_due_date' => $invoice->due_date,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('customer_package_installments')->insert([
        [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => null,
            'installment_number' => 1,
            'amount' => 120,
            'due_date' => now()->subMonth()->toDateString(),
            'status' => 'paid',
            'paid_at' => now()->subMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'installment_number' => 2,
            'amount' => 120,
            'due_date' => $invoice->due_date,
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $installments = app(ChatbotEngine::class)->processMessage($customer->whatsapp, 'parcelas', $tenant->id);
    expect($installments)->toContain('Parcelas do seu cadastro')
        ->and($installments)->toContain('Pagas: 1/6')
        ->and($installments)->toContain('Pendentes: 1')
        ->and($installments)->toContain('Em aberto: R$ 120,00');

    $pixByMenu = app(WhatsAppAutoService::class)->reply($tenant, $instance, $customer->whatsapp, '3');
    expect($pixByMenu)->toContain('Código Pix copia e cola')
        ->and($pixByMenu)->toContain('00020101021226880014br.gov.bcb.pix');
});
