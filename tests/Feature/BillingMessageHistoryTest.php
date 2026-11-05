<?php

use App\Models\AdminUser;
use App\Models\BillingDispatch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Modules\Billing\Services\BillingMessageHistoryService;
use App\Modules\EmailBilling\Models\EmailMessage;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Services\CronManager;

function billingMessageHistoryAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin Histórico Cobrança',
        'email' => 'admin-historico-cobranca-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);
}

function billingMessageHistoryFixture(): array
{
    $plan = Plan::create([
        'name' => 'Plano Histórico Mensagens',
        'slug' => 'plano-historico-mensagens-'.uniqid(),
        'price' => 99,
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
        'name' => 'Tenant Histórico Mensagens',
        'email' => 'tenant-historico-msg-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Histórico Mensagens',
        'slug' => 'tenant-historico-msg-'.uniqid(),
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Histórico',
        'email' => 'cliente-historico@example.com',
        'phone' => '11988887777',
        'whatsapp' => '5511988887777',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-HIST-'.uniqid(),
        'description' => 'Serviço Premium - Parcela 2/6',
        'amount' => 149.90,
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => 'pending',
        'payment_url' => 'https://pagar.exemplo.com/historico',
        'webhook_token' => str_repeat('h', 48),
    ]);

    $dispatch = BillingDispatch::create([
        'invoice_id' => $invoice->id,
        'channel' => 'both',
        'schedule_offset' => 'manual',
        'scheduled_at' => now(),
        'status' => 'sent',
        'attempts' => 1,
        'sent_at' => now(),
        'payload' => ['channel' => 'both'],
    ]);

    return [$tenant, $customer, $invoice, $dispatch];
}

it('exibe histórico centralizado de mensagens de cobrança por WhatsApp e e-mail', function () {
    [, $customer, $invoice, $dispatch] = billingMessageHistoryFixture();

    WhatsAppMessage::create([
        'dispatch_id' => $dispatch->id,
        'to' => $customer->whatsapp,
        'message' => "Olá {$customer->name}, sua fatura {$invoice->number} vence em breve.\nPix copia e cola disponível.",
        'direction' => 'out',
        'status' => 'sent',
        'evolution_id' => 'WA-HIST-1',
        'media_type' => 'text',
        'sent_at' => now(),
    ]);

    EmailMessage::create([
        'tenant_id' => $invoice->tenant_id,
        'dispatch_id' => $dispatch->id,
        'to' => $customer->email,
        'subject' => 'Sua fatura está disponível',
        'body_html' => '<p>Olá Cliente Histórico, acesse o link de pagamento.</p>',
        'status' => 'sent',
        'message_id' => 'MAIL-HIST-1',
        'sent_at' => now(),
    ]);

    $this->actingAs(billingMessageHistoryAdmin(), 'admin')
        ->get(route('admin.billing.message-history.index'))
        ->assertOk()
        ->assertSee('Histórico de mensagens de cobrança')
        ->assertSee('Retenção')
        ->assertSee('14 dias')
        ->assertSee('Cliente Histórico')
        ->assertSee($invoice->number)
        ->assertSee('WhatsApp')
        ->assertSee('E-mail')
        ->assertSee('WA-HIST-1')
        ->assertSee('MAIL-HIST-1');
});

it('limpa automaticamente histórico de cobrança antigo sem apagar mensagens gerais', function () {
    [, $customer, $invoice, $dispatch] = billingMessageHistoryFixture();

    $recent = WhatsAppMessage::create([
        'dispatch_id' => $dispatch->id,
        'to' => $customer->whatsapp,
        'message' => 'Cobrança recente',
        'direction' => 'out',
        'status' => 'sent',
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $oldBilling = WhatsAppMessage::create([
        'dispatch_id' => $dispatch->id,
        'to' => $customer->whatsapp,
        'message' => 'Cobrança antiga',
        'direction' => 'out',
        'status' => 'sent',
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ]);

    $generalOld = WhatsAppMessage::create([
        'dispatch_id' => null,
        'to' => $customer->whatsapp,
        'message' => 'Mensagem geral antiga',
        'direction' => 'out',
        'status' => 'sent',
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ]);

    $oldEmail = EmailMessage::create([
        'tenant_id' => $invoice->tenant_id,
        'dispatch_id' => $dispatch->id,
        'to' => $customer->email,
        'subject' => 'Cobrança antiga',
        'body_html' => '<p>Mensagem antiga</p>',
        'status' => 'sent',
    ]);
    $oldEmail->forceFill([
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ])->save();

    $deleted = app(BillingMessageHistoryService::class)->cleanup(14);

    expect($deleted['total'])->toBe(2)
        ->and(WhatsAppMessage::whereKey($recent->id)->exists())->toBeTrue()
        ->and(WhatsAppMessage::whereKey($oldBilling->id)->exists())->toBeFalse()
        ->and(WhatsAppMessage::whereKey($generalOld->id)->exists())->toBeTrue()
        ->and(EmailMessage::whereKey($oldEmail->id)->exists())->toBeFalse()
        ->and(array_key_exists('billing.message_history.cleanup', CronManager::jobs()))->toBeTrue();
});

it('filtra histórico por canal específico na origem otimizando a consulta', function () {
    [, $customer, $invoice, $dispatch] = billingMessageHistoryFixture();

    WhatsAppMessage::create([
        'dispatch_id' => $dispatch->id,
        'to' => $customer->whatsapp,
        'message' => 'Cobrança teste WhatsApp exclusivo',
        'direction' => 'out',
        'status' => 'sent',
        'evolution_id' => 'WA-CH-ONLY-1',
        'sent_at' => now(),
    ]);

    EmailMessage::create([
        'tenant_id' => $invoice->tenant_id,
        'dispatch_id' => $dispatch->id,
        'to' => $customer->email,
        'subject' => 'Cobrança teste E-mail exclusivo',
        'body_html' => '<p>Cobrança teste E-mail exclusivo</p>',
        'status' => 'sent',
        'message_id' => 'MAIL-CH-ONLY-1',
        'sent_at' => now(),
    ]);

    $admin = billingMessageHistoryAdmin();

    // Filtra apenas WhatsApp
    $this->actingAs($admin, 'admin')
        ->get(route('admin.billing.message-history.index', ['channel' => 'whatsapp']))
        ->assertOk()
        ->assertSee('WA-CH-ONLY-1')
        ->assertDontSee('MAIL-CH-ONLY-1');

    // Filtra apenas E-mail
    $this->actingAs($admin, 'admin')
        ->get(route('admin.billing.message-history.index', ['channel' => 'email']))
        ->assertOk()
        ->assertSee('MAIL-CH-ONLY-1')
        ->assertDontSee('WA-CH-ONLY-1');
});

it('permite ao tenant visualizar no painel seu historico de cobrancas com regua e mensagens diretas', function () {
    [$tenant, $customer, $invoice, $dispatch] = billingMessageHistoryFixture();

    $user = \App\Models\User::create([
        'name' => 'Usuario Tenant Teste',
        'email' => 'tenant-user-'.uniqid().'@example.com',
        'password' => bcrypt('password123'),
    ]);
    $tenant->update(['user_id' => $user->id]);

    // Mensagem direta (sem dispatch_id) para o cliente do tenant
    WhatsAppMessage::create([
        'dispatch_id' => null,
        'to' => $customer->whatsapp,
        'message' => 'Comprovante de pagamento da sua fatura recebido com sucesso!',
        'direction' => 'out',
        'status' => 'sent',
        'evolution_id' => 'WA-DIRECT-MSG-1',
        'sent_at' => now(),
    ]);

    // Novo dispatch cancelado/agendado sem mensagem na tabela whatsapp_messages
    $canceledDispatch = BillingDispatch::create([
        'invoice_id' => $invoice->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '0d',
        'scheduled_at' => now(),
        'status' => 'canceled',
        'last_error' => 'Cancelado pelo sistema: fatura quitada.',
        'attempts' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('tenant.billing.history'))
        ->assertOk()
        ->assertSee('Histórico de Cobrança')
        ->assertSee($customer->name)
        ->assertSee('WhatsApp')
        ->assertSee('Comprovante de pagamento');
});

