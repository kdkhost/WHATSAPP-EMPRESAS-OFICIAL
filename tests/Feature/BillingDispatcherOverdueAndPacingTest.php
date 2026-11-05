<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function createTestTenantAndInstance(): array
{
    $plan = Plan::create([
        'name' => 'Plano Test Pacing',
        'slug' => 'plano-test-pacing-'.uniqid(),
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Tenant User',
        'email' => 'tenant-user-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Teste',
        'slug' => 'empresa-teste-'.uniqid(),
        'status' => 'active',
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'instance_test_'.$tenant->id,
        'display_name' => 'Instancia Teste',
        'status' => 'connected',
        'is_default' => true,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'token-test',
        'webhook_secret' => 'wh-secret',
    ]);

    return [$tenant, $instance];
}

it('agenda cobranca imediata de atraso para faturas que ja entram vencidas', function () {
    [$tenant] = createTestTenantAndInstance();

    // Template para atraso (+1d e +7d)
    WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Atraso 7 dias',
        'slug' => 'atraso-7d-'.uniqid(),
        'category' => 'atraso',
        'schedule_offset' => '+7d',
        'content_text' => 'Fatura em atraso: {numero_fatura}',
        'content_html' => '<p>Fatura em atraso: {numero_fatura}</p>',
        'active' => true,
        'is_default' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Atrasado',
        'email' => 'atrasado@example.com',
        'whatsapp' => '21988887777',
        'active' => true,
    ]);

    // Fatura vencida há 10 dias
    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-OVERDUE-'.uniqid(),
        'description' => 'Servico em atraso',
        'amount' => 120.00,
        'due_date' => now()->subDays(10)->toDateString(),
        'status' => 'overdue',
    ]);

    /** @var BillingDispatcher $dispatcher */
    $dispatcher = app(BillingDispatcher::class);
    $scheduledCount = $dispatcher->scheduleNewInvoices();

    expect($scheduledCount)->toBeGreaterThanOrEqual(1);

    $dispatches = DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->get();
    expect($dispatches->count())->toBeGreaterThanOrEqual(1);

    $immediateDispatch = $dispatches->firstWhere('schedule_offset', '+7d');
    expect($immediateDispatch)->not->toBeNull()
        ->and($immediateDispatch->status)->toBe('scheduled')
        ->and(Carbon::parse($immediateDispatch->scheduled_at)->isSameDay(now('America/Sao_Paulo')))->toBeTrue();
});

it('permite envio no dispatchDue quando o ultimo envio ocorreu no passado (corrige bug do Carbon 3)', function () {
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    Http::fake([
        'https://evo.test/send/text' => Http::response([
            'data' => ['Info' => ['ID' => '3EB0TESTPASTDIFF']],
            'message' => 'success',
        ], 200),
    ]);

    [$tenant] = createTestTenantAndInstance();

    $tpl = WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cobranca Teste',
        'slug' => 'cobranca-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_text' => 'Fatura {numero_fatura}',
        'content_html' => '<p>Fatura {numero_fatura}</p>',
        'active' => true,
        'is_default' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Pontual',
        'email' => 'pontual@example.com',
        'whatsapp' => '21977776666',
        'active' => true,
    ]);

    // Fatura antiga já enviada há 3 dias atrás
    $oldInvoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-OLD-'.uniqid(),
        'description' => 'Servico Antigo',
        'amount' => 50.00,
        'due_date' => now()->subDays(5)->toDateString(),
        'status' => 'pending',
    ]);

    DB::table('billing_dispatches')->insert([
        'invoice_id' => $oldInvoice->id,
        'template_id' => $tpl->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '0d',
        'scheduled_at' => now()->subDays(3),
        'status' => 'sent',
        'sent_at' => now()->subDays(3),
        'attempts' => 1,
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3), // Último envio foi há 3 dias atrás
    ]);

    // Nova fatura agendada para ser enviada agora
    $newInvoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-NEW-'.uniqid(),
        'description' => 'Servico Novo',
        'amount' => 80.00,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $newDispatchId = DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $newInvoice->id,
        'template_id' => $tpl->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '0d',
        'scheduled_at' => now()->subMinutes(10), // Já devida
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    /** @var BillingDispatcher $dispatcher */
    $dispatcher = app(BillingDispatcher::class);
    $report = $dispatcher->dispatchDue();

    // Deve ter enviado a nova fatura com sucesso! (Não pode dar 0 por conta do Carbon 3)
    expect($report['sent'])->toBe(1);

    $status = DB::table('billing_dispatches')->where('id', $newDispatchId)->value('status');
    expect($status)->toBe('sent');
});

it('descarta dispatches agendados de faturas que ja foram pagas ou canceladas', function () {
    [$tenant] = createTestTenantAndInstance();

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Pago',
        'email' => 'pago@example.com',
        'whatsapp' => '21966665555',
        'active' => true,
    ]);

    $paidInvoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-PAID-'.uniqid(),
        'description' => 'Fatura Liquidada',
        'amount' => 150.00,
        'due_date' => now()->subDays(2)->toDateString(),
        'status' => 'paid',
    ]);

    $dispatchId = DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $paidInvoice->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '+1d',
        'scheduled_at' => now()->subDay(),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    /** @var BillingDispatcher $dispatcher */
    $dispatcher = app(BillingDispatcher::class);
    $report = $dispatcher->dispatchDue();

    $status = DB::table('billing_dispatches')->where('id', $dispatchId)->value('status');
    expect($status)->toBe('canceled');
});
