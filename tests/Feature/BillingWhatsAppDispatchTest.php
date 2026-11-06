<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WhatsAppMessage;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\EmailBilling\Models\EmailMessage;
use App\Modules\EmailBilling\Models\TenantEmailConfig;
use App\Modules\EmailBilling\Models\EmailTemplate;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function billingWhatsAppTenant(): array
{
    $plan = Plan::create([
        'name' => 'Plano Billing WhatsApp',
        'slug' => 'plano-billing-whatsapp-'.uniqid(),
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
        'name' => 'Tenant Billing WhatsApp',
        'email' => 'tenant-billing-wa-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Billing WhatsApp',
        'slug' => 'tenant-billing-wa-'.uniqid(),
        'status' => 'active',
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_billing_'.$tenant->id,
        'display_name' => 'Cobranca',
        'status' => 'connected',
        'is_default' => true,
        'api_base_url' => 'https://graph.facebook.com/v21.0',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);

    return [$tenant, $instance];
}

function billingWhatsAppAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin Billing WhatsApp',
        'email' => 'admin-billing-wa-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);
}

function billingWhatsAppTemplate(?int $tenantId = null): WhatsAppTemplate
{
    return WhatsAppTemplate::create([
        'tenant_id' => $tenantId,
        'name' => 'Cobranca manual',
        'slug' => 'cobranca-manual-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_html' => '<p>Ola {primeiro_nome}, sua fatura {numero_fatura} vence em {vencimento}. Valor: {valor}. {link_pagamento}</p>',
        'content_text' => 'Ola {primeiro_nome}, sua fatura {numero_fatura} vence em {vencimento}. Valor: {valor}. {link_pagamento}',
        'active' => true,
        'is_default' => true,
    ]);
}

it('envia cobranca manual por whatsapp usando template padrao e id de mensagem', function () {
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => '3EB0TESTBILLING']],
        ], 200),
    ]);

    [$tenant] = billingWhatsAppTenant();
    billingWhatsAppTemplate($tenant->id);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Cobranca',
        'email' => 'cliente-cobranca@example.com',
        'whatsapp' => '21999990000',
        'last_interaction_at' => now(),
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-WA-'.uniqid(),
        'description' => 'Mensalidade',
        'amount' => 99.90,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'webhook_token' => str_repeat('b', 48),
    ]);

    $this->actingAs(billingWhatsAppAdmin(), 'admin')
        ->postJson(route('admin.billing.invoices.send', $invoice->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'sent');

    expect(DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->where('status', 'sent')->exists())->toBeTrue()
        ->and(WhatsAppMessage::where('dispatch_id', DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->value('id'))
            ->where('meta_message_id', '3EB0TESTBILLING')
            ->where('status', 'sent')
            ->exists())->toBeTrue();
});

it('envia cobranca com servico parcelas link e qr code pelo whatsapp', function () {
    config()->set('whatsapp.send_separate_media', true);
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => '3EB0IMAGEQR']],
        ], 200),
    ]);

    [$tenant] = billingWhatsAppTenant();

    WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cobranca rica',
        'slug' => 'cobranca-rica-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_html' => '<p>{servico_principal} {resumo_parcelas} {link_pagamento} {qrcode_instrucao}</p>',
        'content_text' => "Servico: {servico_principal}\nParcelas: {resumo_parcelas}\nLink: {link_pagamento}\n{qrcode_instrucao}",
        'active' => true,
        'is_default' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Parcelado',
        'email' => 'cliente-parcelado@example.com',
        'whatsapp' => '21999990003',
        'last_interaction_at' => now(),
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-WA-QR-'.uniqid(),
        'description' => 'Plano Premium - Parcela 2/6',
        'amount' => 150.00,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'payment_url' => 'https://pagar.exemplo.com/fatura/qr-123',
        'webhook_token' => str_repeat('e', 48),
    ]);

    $packageId = DB::table('tenant_packages')->insertGetId([
        'tenant_id' => $tenant->id,
        'name' => 'Plano Premium',
        'total_amount' => 900,
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
        'total_amount' => 900,
        'final_amount' => 900,
        'start_date' => now()->toDateString(),
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
            'amount' => 150,
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
            'amount' => 150,
            'due_date' => $invoice->due_date,
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs(billingWhatsAppAdmin(), 'admin')
        ->postJson(route('admin.billing.invoices.send', $invoice->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'sent');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'graph.facebook.com')
        && ($request['type'] ?? null) === 'image'
        && str_contains((string) data_get($request->data(), 'image.link'), 'api.qrserver.com')
        && str_contains((string) data_get($request->data(), 'image.caption'), 'Plano Premium')
        && str_contains((string) data_get($request->data(), 'image.caption'), 'Parcela 2/6')
        && str_contains((string) data_get($request->data(), 'image.caption'), 'https://pagar.exemplo.com/fatura/qr-123'));

    $dispatchId = DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->value('id');

    expect(WhatsAppMessage::where('dispatch_id', $dispatchId)->where('meta_message_id', '3EB0IMAGEQR')->exists())->toBeTrue();
});

it('anexa boleto no email e envia documento no whatsapp quando a cobranca usa boleto', function () {
    config()->set('whatsapp.send_separate_media', true);
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => '3EB0DOCBOLETO']],
        ], 200),
        'https://files.test/boleto-123.pdf' => Http::response('%PDF-1.4 boleto fake%', 200, ['Content-Type' => 'application/pdf']),
    ]);

    [$tenant] = billingWhatsAppTenant();

    WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cobranca boleto',
        'slug' => 'cobranca-boleto-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_html' => '<p>{numero_fatura} {boleto_url}</p>',
        'content_text' => "Fatura {numero_fatura}\nBoleto: {boleto_url}",
        'active' => true,
        'is_default' => true,
    ]);

    $emailTemplate = EmailTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Email boleto',
        'slug' => 'email-boleto-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'subject' => 'Fatura {numero_fatura}',
        'body_html' => '<p>Boleto: {boleto_url}</p>',
        'body_text' => 'Boleto: {boleto_url}',
        'active' => true,
        'is_default' => true,
    ]);

    TenantEmailConfig::create([
        'tenant_id' => $tenant->id,
        'enabled' => true,
        'driver' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'financeiro@example.com',
        'password' => 'secret',
        'from_email' => 'financeiro@example.com',
        'from_name' => 'Financeiro',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Boleto',
        'email' => 'cliente-boleto@example.com',
        'whatsapp' => '21999990009',
        'last_interaction_at' => now(),
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-BOLETO-'.uniqid(),
        'description' => 'Servico com boleto',
        'amount' => 199.90,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'webhook_token' => str_repeat('f', 48),
        'meta' => [
            'payment_method' => 'boleto',
            'metodo_pagamento' => 'Boleto',
            'boleto_url' => 'https://files.test/segunda-via',
            'boleto_pdf_url' => 'https://files.test/boleto-123.pdf',
        ],
    ]);

    $dispatchId = DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $invoice->id,
        'template_id' => WhatsAppTemplate::where('tenant_id', $tenant->id)->value('id'),
        'email_template_id' => $emailTemplate->id,
        'channel' => 'both',
        'schedule_offset' => '0d',
        'scheduled_at' => now(),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ok = app(BillingDispatcher::class)->dispatchSpecific($dispatchId);

    expect($ok)->toBeTrue()
        ->and(EmailMessage::query()->where('dispatch_id', $dispatchId)->where('status', 'queued')->exists())->toBeTrue();

    $headers = EmailMessage::query()->where('dispatch_id', $dispatchId)->value('headers');
    $attachments = is_array($headers) ? ($headers['attachments'] ?? []) : [];

    expect($attachments)->toHaveCount(2);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'graph.facebook.com')
        && ($request['type'] ?? null) === 'document'
        && data_get($request->data(), 'document.filename') === 'boleto-'.$invoice->number.'.pdf');
});

it('reagenda cobranca manual quando o anti ban limita o envio temporariamente', function () {
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 1);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    [$tenant, $instance] = billingWhatsAppTenant();
    billingWhatsAppTemplate($tenant->id);

    WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5521999990001',
        'message' => 'Envio anterior',
        'direction' => 'out',
        'status' => 'sent',
        'recipient_hash' => hash('sha256', '5521999990001'),
        'message_hash' => hash('sha256', 'envio anterior'),
        'created_at' => now()->subSeconds(10),
        'updated_at' => now()->subSeconds(10),
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Reagendado',
        'email' => 'cliente-reagendado@example.com',
        'whatsapp' => '21999990002',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-WA-DEFER-'.uniqid(),
        'description' => 'Mensalidade',
        'amount' => 99.90,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'webhook_token' => str_repeat('c', 48),
    ]);

    $this->actingAs(billingWhatsAppAdmin(), 'admin')
        ->postJson(route('admin.billing.invoices.send', $invoice->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'scheduled');

    $dispatch = DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->first();

    expect($dispatch->status)->toBe('scheduled')
        ->and($dispatch->attempts)->toBe(0)
        ->and($dispatch->last_error)->toBeNull()
        ->and($dispatch->scheduled_at)->not->toBeNull();
});

it('bloqueia cobranca manual para cliente sem whatsapp antes de criar envio', function () {
    [$tenant] = billingWhatsAppTenant();
    billingWhatsAppTemplate($tenant->id);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Sem WhatsApp',
        'email' => 'sem-whatsapp@example.com',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-WA-NO-PHONE-'.uniqid(),
        'description' => 'Mensalidade',
        'amount' => 99.90,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'webhook_token' => str_repeat('d', 48),
    ]);

    $this->actingAs(billingWhatsAppAdmin(), 'admin')
        ->postJson(route('admin.billing.invoices.send', $invoice->id))
        ->assertStatus(422)
        ->assertJsonPath('ok', false);

    expect(DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('envia cobranca em mensagem unica consolidada com pix copia e cola sem disparos extras por padrao', function () {
    config()->set('whatsapp.send_pix_qrcode_image', false);
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => '3EB0SINGLEMSG']],
        ], 200),
    ]);

    [$tenant] = billingWhatsAppTenant();

    WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cobranca simples',
        'slug' => 'cobranca-simples-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_html' => '<p>Ola {primeiro_nome}, fatura {numero_fatura} valor {valor}. Link: {link_pagamento}</p>',
        'content_text' => "Ola {primeiro_nome}, fatura {numero_fatura} valor {valor}.\nLink: {link_pagamento}",
        'active' => true,
        'is_default' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Mensagem Unica',
        'email' => 'cliente-single@example.com',
        'whatsapp' => '21999990005',
        'last_interaction_at' => now(),
        'active' => true,
    ]);

    $pixCode = '00020126330014br.gov.bcb.pix0111TESTEPIXSINGLEMSG';

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-WA-SINGLE-'.uniqid(),
        'description' => 'Servico Mensagem Unica',
        'amount' => 50.00,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'payment_url' => 'https://pagar.exemplo.com/fatura/single-123',
        'webhook_token' => str_repeat('g', 48),
        'meta' => [
            'payment_method' => 'pix',
            'pix_copia_cola' => $pixCode,
        ],
    ]);

    $this->actingAs(billingWhatsAppAdmin(), 'admin')
        ->postJson(route('admin.billing.invoices.send', $invoice->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'sent');

    // Verifica que enviou exatamente 1 requisicao oficial para a Meta sem disparos extras de midia
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'graph.facebook.com')
        && $request['to'] === '5521999990005');

    // Nenhuma requisicao de midia deve ter sido disparada
    Http::assertNotSent(fn ($request) => ($request['type'] ?? null) === 'image');

    // Nenhuma requisicao de midia deve ter sido disparada
    Http::assertNotSent(fn ($request) => ($request['type'] ?? null) === 'image');
});

it('envia cobranca com imagem do qrcode do pix quando send_pix_qrcode_image esta ativo por padrao', function () {
    config()->set('whatsapp.send_pix_qrcode_image', true);
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.limits.per_minute', 10);
    config()->set('whatsapp_anti_ban.recipient_cooldown_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.message_minutes', 0);
    config()->set('whatsapp_anti_ban.cooldowns.campaign_minutes', 0);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => '3EB0IMAGEPIXQR']],
        ], 200),
    ]);

    [$tenant] = billingWhatsAppTenant();

    WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cobranca com Pix e QR',
        'slug' => 'cobranca-pix-qr-'.uniqid(),
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_html' => '<p>Ola {primeiro_nome}, fatura {numero_fatura} valor {valor}. Pix: {pix_copia_cola}</p>',
        'content_text' => "Ola {primeiro_nome}, fatura {numero_fatura} valor {valor}.\nPix: {pix_copia_cola}",
        'active' => true,
        'is_default' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Pix QR',
        'email' => 'cliente-pix-qr@example.com',
        'whatsapp' => '21999990008',
        'last_interaction_at' => now(),
        'active' => true,
    ]);

    $pixCode = '00020126330014br.gov.bcb.pix0111TESTEQRCODEDISPATCH';

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-WA-PIXQR-'.uniqid(),
        'description' => 'Servico Pix QR',
        'amount' => 75.00,
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'pending',
        'payment_url' => 'https://pagar.exemplo.com/fatura/pixqr-123',
        'webhook_token' => str_repeat('h', 48),
        'meta' => [
            'payment_method' => 'pix',
            'pix_copia_cola' => $pixCode,
        ],
    ]);

    $this->actingAs(billingWhatsAppAdmin(), 'admin')
        ->postJson(route('admin.billing.invoices.send', $invoice->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status', 'sent');

    // Verifica que enviou a imagem do QR Code em mensagem unica com a legenda contendo o Copia e Cola e dados da fatura
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'graph.facebook.com')
        && ($request['type'] ?? null) === 'image'
        && str_contains((string) data_get($request->data(), 'image.link'), 'api.qrserver.com')
        && str_contains((string) data_get($request->data(), 'image.caption'), $invoice->number)
        && str_contains((string) data_get($request->data(), 'image.caption'), $pixCode));
});

