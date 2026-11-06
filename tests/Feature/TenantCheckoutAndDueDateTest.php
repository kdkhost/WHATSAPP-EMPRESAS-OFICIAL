<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Templates\Models\WhatsAppTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Artisan::call('route:clear');
});

function setupTenantForDueDateAndCheckoutTest(): array
{
    $plan = Plan::create([
        'name' => 'Plano Teste Vencimento',
        'slug' => 'plano-venc-'.uniqid(),
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
        'name' => 'Tenant User Checkout',
        'email' => 'tenant-checkout-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Teste Tenant',
        'status' => 'active',
        'slug' => 'tenant-chk-' . uniqid(),
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Carlos Silva',
        'email' => 'carlos@cliente.com',
        'whatsapp' => '11987654321',
        'document' => '123.456.789-00',
        'active' => true,
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'inst-' . uniqid(),
        'display_name' => 'WhatsApp Oficial',
        'status' => 'connected',
        'is_default' => true,
        'api_base_url' => 'https://graph.facebook.com/v21.0',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);

    TenantGateway::create([
        'tenant_id' => $tenant->id,
        'gateway' => 'mercadopago',
        'active' => true,
        'is_default' => true,
        'priority' => 1,
        'credentials' => [
            'access_token' => 'APP_USR-TEST-MOCK-TOKEN',
            'public_key'   => 'TEST-PUBLIC-KEY',
        ],
    ]);

    // Cria templates padrão para o teste
    WhatsAppTemplate::firstOrCreate(['slug' => 'lembrete-1d-antes'], [
        'name' => 'Lembrete 1 dia antes',
        'category' => 'lembrete',
        'schedule_offset' => '-1d',
        'content_html' => '<p>Olá {primeiro_nome}. Sua fatura {numero_fatura} vence amanhã em {vencimento}. Link: {link_pagamento}</p>',
        'content_text' => "Olá {primeiro_nome}.\n\nSua fatura {numero_fatura} vence amanhã em {vencimento}.\n\nLink: {link_pagamento}",
        'active' => true,
        'is_default' => true,
        'is_system' => true,
    ]);

    WhatsAppTemplate::firstOrCreate(['slug' => 'lembrete-3d-antes'], [
        'name' => 'Lembrete 3 dias antes',
        'category' => 'lembrete',
        'schedule_offset' => '-3d',
        'content_html' => '<p>Olá {primeiro_nome}. Sua fatura {numero_fatura} vence em {vencimento}. Link: {link_pagamento}</p>',
        'content_text' => "Olá {primeiro_nome}.\n\nSua fatura {numero_fatura} vence em {vencimento}.\n\nLink: {link_pagamento}",
        'active' => true,
        'is_default' => true,
        'is_system' => true,
    ]);

    WhatsAppTemplate::firstOrCreate(['slug' => 'cobranca-gerada'], [
        'name' => 'Nova Cobrança Gerada',
        'category' => 'cobranca',
        'schedule_offset' => 'manual',
        'content_html' => '<p>Olá {primeiro_nome}. Uma nova cobrança foi gerada com vencimento em {vencimento}. Link: {link_pagamento}</p>',
        'content_text' => "Olá {primeiro_nome}.\n\nUma nova cobrança foi gerada com vencimento em {vencimento}.\n\nLink: {link_pagamento}",
        'active' => true,
        'is_default' => true,
        'is_system' => true,
    ]);

    WhatsAppTemplate::firstOrCreate(['slug' => 'vencimento-hoje'], [
        'name' => 'Vencimento Hoje',
        'category' => 'cobranca',
        'schedule_offset' => '0d',
        'content_html' => '<p>Olá {primeiro_nome}. Hoje é o vencimento da sua fatura {numero_fatura}. Link: {link_pagamento}</p>',
        'content_text' => "Olá {primeiro_nome}.\n\nHoje é o vencimento da sua fatura {numero_fatura}.\n\nLink: {link_pagamento}",
        'active' => true,
        'is_default' => true,
        'is_system' => true,
    ]);

    return compact('user', 'tenant', 'customer', 'instance');
}

test('cobranca avulsa criada pelo tenant via Mercado Pago utiliza checkout seguro do painel e nao ticket externo', function () {
    $ctx = setupTenantForDueDateAndCheckoutTest();

    $mockTicketUrl = 'https://www.mercadopago.com.br/payments/185124584533/ticket?caller_id=3791534273&hash=023abc19-f503-42e0-a3c5-c6f6ba6b7b25';
    $mockPixCode = '00020126580014br.gov.bcb.pix0136123e4567-e89b-12d3-a456-4266141740005204000053039865802BR5913Empresa Teste6009Sao Paulo62070503***6304ABCD';

    Http::fake([
        'https://api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200),
        'https://api.mercadopago.com/v1/payments' => Http::response([
            'id' => 185124584533,
            'status' => 'pending',
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code' => $mockPixCode,
                    'qr_code_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                    'ticket_url' => $mockTicketUrl,
                ],
            ],
        ], 201),
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'MSG-123']]], 200),
    ]);

    $dueDateTomorrow = now('America/Sao_Paulo')->addDay()->toDateString();
    $expectedFormattedDate = now('America/Sao_Paulo')->addDay()->format('d/m/Y');

    $payload = [
        'customer_id'    => $ctx['customer']->id,
        'description'    => 'Desenvolvimento de Site Institucional',
        'amount'         => '350,00',
        'installments'   => 1,
        'interest_rate'  => 0,
        'first_due_date' => $dueDateTomorrow,
        'gateway'        => 'mercadopago',
        'send_now'       => 1,
    ];

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.standalone.store'), $payload);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);

    $invoice = Invoice::where('tenant_id', $ctx['tenant']->id)
        ->where('customer_id', $ctx['customer']->id)
        ->latest('id')
        ->first();

    expect($invoice)->not->toBeNull();
    // 1. O payment_url da fatura deve ser o checkout seguro do próprio painel
    expect($invoice->payment_url)->toBe(url('/fatura/' . $invoice->webhook_token));
    expect($invoice->payment_url)->not->toContain('mercadopago.com.br');
    expect($invoice->payment_url)->not->toContain('/ticket');

    // 2. O ticket_url do gateway deve ser mantido no meta para auditoria interna
    expect(data_get($invoice->meta, 'gateway_payment.ticket_url'))->toBe($mockTicketUrl);

    // 3. O envio disparado via WhatsApp deve ter o link do checkout transparente e a data cadastrada do vencimento (em midia com legenda ou texto)
    Http::assertSent(function ($request) use ($invoice, $mockTicketUrl, $expectedFormattedDate) {
        $text = (string) (data_get($request->data(), 'text.body') ?? data_get($request->data(), 'image.caption') ?? data_get($request->data(), 'document.caption') ?? data_get($request->data(), 'video.caption') ?? '');
        if ($text === '') {
            return false;
        }

        // Não deve conter o link externo do Mercado Pago
        $noMpTicket = ! str_contains($text, $mockTicketUrl) && ! str_contains($text, 'mercadopago.com.br');
        // Deve conter o link transparente do painel
        $hasSecureLink = str_contains($text, url('/fatura/' . $invoice->webhook_token));
        // Não deve dizer que vence hoje se vence amanhã ou no próximo dia útil
        $noVenceHoje = ! str_contains($text, 'Hoje é o vencimento');
        // Deve conter a data cadastrada do vencimento
        $hasDueDate = str_contains($text, $invoice->due_date->format('d/m/Y')) || str_contains($text, $expectedFormattedDate) || str_contains($text, 'amanhã');

        return $noMpTicket && $hasSecureLink && $noVenceHoje && $hasDueDate;
    });
});

test('cobranca criada para data futura seleciona template adequado e informa data exata do vencimento', function () {
    $ctx = setupTenantForDueDateAndCheckoutTest();

    $futureDueDate = app(\App\Modules\Holidays\Services\HolidayService::class)->nextBusinessDay(now('America/Sao_Paulo')->addDays(3))->toDateString();
    $expectedFormattedDate = \Carbon\Carbon::parse($futureDueDate)->format('d/m/Y');

    Http::fake([
        'https://api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200),
        'https://api.mercadopago.com/v1/payments' => Http::response([
            'id' => 999111222,
            'status' => 'pending',
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code' => '00020126580014br.gov.bcb.pix...',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/999111222/ticket',
                ],
            ],
        ], 201),
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'MSG-456']]], 200),
    ]);

    $payload = [
        'customer_id'    => $ctx['customer']->id,
        'description'    => 'Consultoria de TI Mensal',
        'amount'         => '500,00',
        'installments'   => 1,
        'interest_rate'  => 0,
        'first_due_date' => $futureDueDate,
        'gateway'        => 'mercadopago',
        'send_now'       => 1,
    ];

    $response = $this->actingAs($ctx['user'])->postJson(route('tenant.invoices.standalone.store'), $payload);
    $response->assertStatus(200);

    $invoice = Invoice::where('tenant_id', $ctx['tenant']->id)->latest('id')->first();
    expect($invoice->due_date->toDateString())->toBe($futureDueDate);

    Http::assertSent(function ($request) use ($expectedFormattedDate) {
        $text = (string) (data_get($request->data(), 'text.body') ?? data_get($request->data(), 'image.caption') ?? data_get($request->data(), 'document.caption') ?? data_get($request->data(), 'video.caption') ?? '');
        if ($text === '') {
            return false;
        }

        // A mensagem NÃO pode dizer que o vencimento é hoje
        $noVenceHoje = ! str_contains($text, 'Hoje é o vencimento');
        // A mensagem DEVE conter a data cadastrada exata do vencimento
        $hasDueDate = str_contains($text, $expectedFormattedDate);

        return $noVenceHoje && $hasDueDate;
    });
});
