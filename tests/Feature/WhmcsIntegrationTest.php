<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;
use App\Modules\WhmcsIntegration\Services\WhmcsPaymentSyncer;
use App\Modules\WhmcsIntegration\Services\WhmcsServiceAutomation;
use App\Modules\Templates\Services\TemplateRenderer;
use Illuminate\Support\Facades\Http;

function whmcsAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin WHMCS',
        'email' => 'admin-whmcs-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);
}

it('cria conexao whmcs somente apos validar credenciais', function () {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response([
            'result' => 'success',
            'activity' => [],
        ]),
    ]);

    $response = $this->actingAs(whmcsAdmin(), 'admin')
        ->postJson('/admin/whmcs', [
            'name' => 'WHMCS Produção',
            'api_url' => 'https://whmcs.test/includes/api.php',
            'identifier' => 'identifier-ok',
            'secret' => 'secret-ok',
            'sync_customers' => 1,
            'sync_invoices' => 1,
            'auto_send_invoices' => 0,
            'sync_statuses' => ['Unpaid', 'Paid'],
            'webhook_require_signature' => 1,
        ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonMissing(['secret' => 'secret-ok']);

    expect(WhmcsConnection::count())->toBe(1)
        ->and(WhmcsConnection::first()->webhook_require_signature)->toBeTrue();
});

it('forca assinatura hmac em novas conexoes whmcs mesmo quando o formulario envia desligado', function () {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response([
            'result' => 'success',
            'activity' => [],
        ]),
    ]);

    $this->actingAs(whmcsAdmin(), 'admin')
        ->postJson('/admin/whmcs', [
            'name' => 'WHMCS Seguro',
            'api_url' => 'https://whmcs.test/includes/api.php',
            'identifier' => 'identifier-ok',
            'secret' => 'secret-ok',
            'sync_customers' => 1,
            'sync_invoices' => 1,
            'auto_send_invoices' => 0,
            'sync_statuses' => ['Unpaid'],
            'webhook_require_signature' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(WhmcsConnection::first()->webhook_require_signature)->toBeTrue();
});

it('sincroniza clientes e faturas whmcs de forma idempotente', function () {
    Http::fake(function ($request) {
        $action = $request['action'] ?? null;

        if ($action === 'GetClientsProducts') {
            if (($request['status'] ?? null) !== 'Active') {
                return Http::response([
                    'result' => 'success',
                    'totalresults' => 0,
                    'products' => ['product' => []],
                ]);
            }

            return Http::response([
                'result' => 'success',
                'totalresults' => 1,
                'products' => [
                    'product' => [[
                        'id' => 501,
                        'clientid' => 12,
                        'pid' => 7,
                        'name' => 'Hospedagem Pro',
                        'groupname' => 'Hospedagem',
                        'domain' => 'cliente.com.br',
                        'status' => 'Active',
                        'billingcycle' => 'Monthly',
                        'recurringamount' => '89.90',
                        'regdate' => '2026-01-10',
                        'nextduedate' => '2026-05-26',
                    ]],
                ],
            ]);
        }

        return match ($action) {
            'GetClients' => Http::response([
                'result' => 'success',
                'totalresults' => 1,
                'clients' => [
                    'client' => [[
                        'id' => 12,
                        'firstname' => 'Maria',
                        'lastname' => 'Silva',
                        'email' => 'maria@example.com',
                        'phonenumber' => '(21) 99999-0000',
                        'status' => 'Active',
                    ]],
                ],
            ]),
            'GetInvoices' => Http::response([
                'result' => 'success',
                'totalresults' => 1,
                'invoices' => [
                    'invoice' => [[
                        'id' => 88,
                        'userid' => 12,
                        'status' => 'Unpaid',
                        'total' => '1,234.56',
                        'duedate' => '2026-05-26',
                        'date' => '2026-05-20',
                    ]],
                ],
            ]),
            default => Http::response(['result' => 'success']),
        };
    });

    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Sync',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
        'sync_customers' => true,
        'sync_invoices' => true,
        'auto_send_invoices' => false,
        'sync_statuses' => ['Unpaid'],
        'sync_paid_invoices' => false,
        'sync_cancelled_invoices' => false,
    ]);

    for ($i = 0; $i < 2; $i++) {
        $this->actingAs(whmcsAdmin(), 'admin')
            ->postJson("/admin/whmcs/{$connection->id}/sync")
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    expect(Customer::count())->toBe(1)
        ->and(Invoice::count())->toBe(1)
        ->and(WhmcsService::count())->toBe(1)
        ->and((float) Invoice::first()->amount)->toBe(1234.56)
        ->and(Invoice::first()->number)->toBe('WHMCS-88')
        ->and(WhmcsService::first()->product_name)->toBe('Hospedagem Pro');
});

it('respeita o metodo do whmcs, recalcula atraso e gera cobranca local com pix', function () {
    config()->set('gateways.efipro.client_id', 'efi-client');
    config()->set('gateways.efipro.client_secret', 'efi-secret');
    config()->set('gateways.efipro.pix_key', 'financeiro@example.com');
    config()->set('gateways.efipro.sandbox', true);

    Http::fake(function ($request) {
        $url = $request->url();
        $action = $request['action'] ?? null;

        if (str_contains($url, '/oauth/token')) {
            return Http::response(['access_token' => 'token-efi', 'expires_in' => 3600]);
        }

        if (str_contains($url, '/v2/cob')) {
            return Http::response([
                'txid' => 'TXID-WHMCS-PIX',
                'loc' => ['id' => 77],
            ]);
        }

        if (str_contains($url, '/v2/loc/77/qrcode')) {
            return Http::response([
                'qrcode' => '000201010212...PIX',
                'qrcodeTexto' => '000201010212...PIX',
                'imagemQrcode' => 'https://cdn.example.com/qrcode-whmcs.png',
            ]);
        }

        if ($action === 'GetClientsProducts') {
            return Http::response([
                'result' => 'success',
                'totalresults' => 0,
                'products' => ['product' => []],
            ]);
        }

        return match ($action) {
            'GetClients' => Http::response([
                'result' => 'success',
                'totalresults' => 1,
                'clients' => [
                    'client' => [[
                        'id' => 12,
                        'firstname' => 'Maria',
                        'lastname' => 'Silva',
                        'email' => 'maria-pix@example.com',
                        'phonenumber' => '(21) 99999-0000',
                        'status' => 'Active',
                    ]],
                ],
            ]),
            'GetInvoices' => Http::response([
                'result' => 'success',
                'totalresults' => 1,
                'invoices' => [
                    'invoice' => [[
                        'id' => 99,
                        'userid' => 12,
                        'status' => 'Unpaid',
                        'total' => '100.00',
                        'duedate' => now('America/Sao_Paulo')->subDays(3)->toDateString(),
                        'paymentmethod' => 'Pix',
                    ]],
                ],
            ]),
            default => Http::response(['result' => 'success']),
        };
    });

    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Pix',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
        'sync_customers' => true,
        'sync_invoices' => true,
        'sync_services' => false,
        'default_gateway' => 'efipro',
        'settings' => [
            'generate_local_gateway_charge' => true,
            'respect_payment_method' => true,
            'late_fee_grace_days' => 0,
            'late_fee_fixed_amount' => 2.50,
            'late_fee_fixed_percent' => 0,
            'late_fee_daily_amount' => 1.00,
            'late_fee_daily_percent' => 0,
        ],
        'sync_statuses' => ['Unpaid'],
        'sync_paid_invoices' => false,
        'sync_cancelled_invoices' => false,
    ]);

    (new WhmcsClient($connection))->syncAll();

    $invoice = Invoice::query()->firstOrFail();

    expect((float) $invoice->amount)->toBe(105.50)
        ->and(data_get($invoice->meta, 'payment_method'))->toBe('pix')
        ->and(data_get($invoice->meta, 'metodo_pagamento'))->toBe('Pix')
        ->and(data_get($invoice->meta, 'gateway_payment.charge_id'))->toBe('TXID-WHMCS-PIX')
        ->and(data_get($invoice->meta, 'gateway_payment.pix_copia_cola'))->toBe('000201010212...PIX')
        ->and(data_get($invoice->meta, 'dias_atraso'))->toBe(3)
        ->and($invoice->gateway)->toBe('efipro');
});

it('busca detalhes do cliente whmcs quando a listagem nao traz telefone', function () {
    Http::fake(function ($request) {
        return match ($request['action'] ?? null) {
            'GetClients' => Http::response([
                'result' => 'success',
                'totalresults' => 1,
                'clients' => [
                    'client' => [[
                        'id' => 12,
                        'firstname' => 'Maria',
                        'lastname' => 'Silva',
                        'email' => 'maria@example.com',
                        'status' => 'Active',
                    ]],
                ],
            ]),
            'GetClientsDetails' => Http::response([
                'result' => 'success',
                'userid' => 12,
                'id' => 12,
                'firstname' => 'Maria',
                'lastname' => 'Silva',
                'email' => 'maria@example.com',
                'phonenumber' => '21999990000',
                'phonecc' => '55',
                'postcode' => '21.710-255-extra',
                'status' => 'Active',
            ]),
            default => Http::response(['result' => 'success']),
        };
    });

    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Telefones',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
        'sync_customers' => true,
        'sync_invoices' => false,
        'auto_send_invoices' => false,
    ]);

    $stats = (new WhmcsClient($connection))->syncCustomers();

    expect($stats['imported'])->toBe(1)
        ->and(Customer::first()->whatsapp)->toBe('5521999990000')
        ->and(Customer::first()->cep)->toBe('21710-255');
});

it('bloqueia webhook whmcs sem assinatura e processa evento assinado sem replay', function () {
    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Webhook',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
        'webhook_token' => 'token-whmcs-teste',
        'webhook_secret' => 'segredo-webhook',
        'webhook_require_signature' => true,
        'auto_send_invoices' => false,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente WHMCS',
        'email' => 'cliente-whmcs@example.com',
        'whatsapp' => '5521999990000',
        'active' => true,
        'meta' => ['whmcs_id' => '12'],
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'WHMCS-88',
        'description' => 'Fatura WHMCS',
        'amount' => 100.00,
        'due_date' => '2026-05-26',
        'status' => 'pending',
        'meta' => [
            'whmcs_invoice_id' => '88',
            'whmcs_connection_id' => $connection->id,
            'whmcs_status' => 'Unpaid',
        ],
    ]);

    $payload = ['event' => 'InvoicePaid', 'invoiceid' => '88', 'status' => 'Paid'];
    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $signature = hash_hmac('sha256', $raw, 'segredo-webhook');

    $this->getJson('/webhooks/whmcs/token-whmcs-teste')
        ->assertOk()
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('method', 'POST')
        ->assertJsonPath('signature_required', true);

    $this->postJson('/webhooks/whmcs/token-whmcs-teste', $payload)
        ->assertStatus(401)
        ->assertJsonPath('error', 'invalid_signature');

    $this->call('POST', '/webhooks/whmcs/token-whmcs-teste', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WHMCS_SIGNATURE' => 'sha256='.$signature,
    ], $raw)->assertOk()->assertJsonPath('status', 'paid');

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->paid_at)->not->toBeNull();

    $this->call('POST', '/webhooks/whmcs/token-whmcs-teste', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WHMCS_SIGNATURE' => 'sha256='.$signature,
    ], $raw)->assertOk()->assertJsonPath('status', 'duplicate_ignored');
});

it('usa fallback xml quando o whmcs quebra json por caracteres legados', function () {
    $xmlProductName = 'Hospedagem Avan'.chr(0xE7).'ada';
    $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>'
        .'<whmcsapi><result>success</result><totalresults>1</totalresults><products><product>'
        .'<id>501</id><clientid>12</clientid><pid>7</pid><name>'.$xmlProductName.'</name>'
        .'<groupname>Hospedagem</groupname><domain>cliente.com.br</domain><dedicatedip></dedicatedip><status>Active</status>'
        .'<billingcycle>Monthly</billingcycle><recurringamount>89.90</recurringamount>'
        .'<password>'.chr(2).chr(0xE7).'segredo</password><subscriptionid></subscriptionid><notes></notes>'
        .'<regdate>2026-01-10</regdate><nextduedate>2026-05-26</nextduedate>'
        .'</product></products></whmcsapi>';

    Http::fake(function ($request) use ($xml) {
        if (($request['action'] ?? null) === 'GetClientsProducts' && ($request['responsetype'] ?? null) === 'json') {
            return Http::response([
                'result' => 'error',
                'message' => 'Error generating JSON encoded response: Malformed UTF-8 characters, possibly incorrectly encoded',
            ]);
        }

        if (($request['action'] ?? null) === 'GetClientsProducts' && ($request['responsetype'] ?? null) === 'xml') {
            return Http::response($xml, 200, ['Content-Type' => 'text/xml; charset=ISO-8859-1']);
        }

        return Http::response(['result' => 'success']);
    });

    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Encoding',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
    ]);

    Customer::create([
        'name' => 'Cliente Encoding',
        'email' => 'encoding@example.com',
        'active' => true,
        'meta' => ['whmcs_id' => '12'],
    ]);

    $stats = (new WhmcsClient($connection))->syncServices(['Active']);

    expect($stats['imported'])->toBe(1)
        ->and(WhmcsService::first()->product_name)->toBe('Hospedagem Avançada');

    Http::assertSent(fn ($request) => ($request['action'] ?? null) === 'GetClientsProducts'
        && ($request['responsetype'] ?? null) === 'xml');
});

it('envia baixa de pagamento local para whmcs quando a fatura veio do whmcs', function () {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response(['result' => 'success']),
    ]);

    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Pagamento',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
        'default_gateway' => 'mercadopago',
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Pagamento',
        'email' => 'pagamento@example.com',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'WHMCS-99',
        'description' => 'Fatura WHMCS paga localmente',
        'amount' => 250.00,
        'due_date' => '2026-05-26',
        'status' => 'paid',
        'paid_at' => now(),
        'meta' => [
            'whmcs_invoice_id' => '99',
            'whmcs_connection_id' => $connection->id,
            'whmcs_status' => 'Unpaid',
        ],
    ]);

    expect(app(WhmcsPaymentSyncer::class)->pushPaidInvoice($invoice->id, 'mercadopago', 'pay-123'))->toBeTrue();

    Http::assertSent(fn ($request) => ($request['action'] ?? null) === 'AddInvoicePayment'
        && (string) ($request['invoiceid'] ?? '') === '99'
        && (string) ($request['gateway'] ?? '') === 'mercadopago');

    expect($invoice->fresh()->meta['whmcs_payment_pushed_at'] ?? null)->not->toBeNull();
});

it('suspende servico whmcs vencido e reativa apos pagamento', function () {
    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::response(['result' => 'success']),
    ]);

    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Servicos',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
        'auto_suspend_overdue_services' => true,
        'auto_unsuspend_paid_services' => true,
        'suspend_after_days' => 0,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Servico',
        'email' => 'servico@example.com',
        'whatsapp' => '5521999990000',
        'active' => true,
        'meta' => ['whmcs_id' => '12'],
    ]);

    $service = WhmcsService::create([
        'connection_id' => $connection->id,
        'customer_id' => $customer->id,
        'whmcs_client_id' => '12',
        'whmcs_service_id' => '501',
        'product_name' => 'Hospedagem Pro',
        'domain' => 'cliente.com.br',
        'status' => 'Active',
        'amount' => 89.90,
        'next_due_date' => now('America/Sao_Paulo')->subDay()->toDateString(),
    ]);

    $suspended = app(WhmcsServiceAutomation::class)->suspendOverdueServices($connection);

    expect($suspended['suspended'])->toBe(1)
        ->and($service->fresh()->status)->toBe('Suspended')
        ->and($service->fresh()->suspended_at)->not->toBeNull();

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'WHMCS-100',
        'description' => 'Fatura WHMCS com servico',
        'amount' => 89.90,
        'due_date' => now('America/Sao_Paulo')->subDay()->toDateString(),
        'status' => 'paid',
        'paid_at' => now(),
        'meta' => [
            'whmcs_invoice_id' => '100',
            'whmcs_connection_id' => $connection->id,
            'whmcs_user_id' => '12',
            'whmcs_service_ids' => ['501'],
        ],
    ]);

    $unsuspended = app(WhmcsServiceAutomation::class)->unsuspendPaidServicesForInvoice($invoice);

    expect($unsuspended['unsuspended'])->toBe(1)
        ->and($service->fresh()->status)->toBe('Active')
        ->and($service->fresh()->reactivated_at)->not->toBeNull();

    Http::assertSent(fn ($request) => ($request['action'] ?? null) === 'ModuleSuspend'
        && (string) ($request['serviceid'] ?? '') === '501');

    Http::assertSent(fn ($request) => ($request['action'] ?? null) === 'ModuleUnsuspend'
        && (string) ($request['serviceid'] ?? '') === '501');
});

it('renderiza variaveis whmcs nos templates de cobranca', function () {
    $message = app(TemplateRenderer::class)->render(
        'Cliente {nome}, sua fatura {numero_fatura} do servico {servico_principal} vence em {vencimento}. Ativos: {servicos_ativos}. WHMCS #{whmcs_cliente_id}/{whmcs_fatura_id}.',
        [
            'nome' => 'Maria Silva',
            'numero_fatura' => 'WHMCS-88',
            'servico_principal' => 'Hospedagem Pro (cliente.com.br)',
            'servicos_ativos' => 'Hospedagem Pro (cliente.com.br)',
            'vencimento' => '2026-05-26',
            'whmcs_cliente_id' => '12',
            'whmcs_fatura_id' => '88',
        ]
    );

    expect($message)->toContain('Maria Silva')
        ->and($message)->toContain('WHMCS-88')
        ->and($message)->toContain('Hospedagem Pro')
        ->and($message)->toContain('12/88');
});

it('cancela dispatches pendentes de faturas que ja foram pagas no WHMCS ou no sistema', function () {
    $customer = Customer::create([
        'name' => 'Cliente Pago',
        'email' => 'pago@example.com',
        'whatsapp' => '5521999991111',
        'active' => true,
    ]);

    $invoicePaid = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'WHMCS-200',
        'description' => 'Fatura Teste Paga',
        'amount' => 150.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'paid',
        'paid_at' => now(),
        'meta' => [
            'whmcs_invoice_id' => '200',
            'whmcs_status' => 'Paid',
        ],
    ]);

    $dispatchId = \Illuminate\Support\Facades\DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $invoicePaid->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '-3d',
        'scheduled_at' => now()->addDays(2),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dispatcher = app(\App\Modules\Billing\Services\BillingDispatcher::class);
    $canceled = $dispatcher->cancelPaidWhmcsInvoiceDispatches();

    expect($canceled)->toBeGreaterThanOrEqual(1);

    $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')->where('id', $dispatchId)->first();
    expect($dispatch->status)->toBe('canceled');
});

it('bloqueia envio pelo whatsapp quando fatura consta como paga antecipadamente no WHMCS', function () {
    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Teste Pago',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Antecipado',
        'email' => 'antecipado@example.com',
        'whatsapp' => '5521999992222',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'WHMCS-300',
        'description' => 'Fatura Teste Antecipada',
        'amount' => 200.00,
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => 'pending',
        'meta' => [
            'whmcs_invoice_id' => '300',
            'whmcs_connection_id' => $connection->id,
            'whmcs_status' => 'Paid',
        ],
    ]);

    $dispatchId = \Illuminate\Support\Facades\DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $invoice->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '-1d',
        'scheduled_at' => now()->subMinute(),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dispatcher = app(\App\Modules\Billing\Services\BillingDispatcher::class);
    $ok = $dispatcher->dispatchSpecific($dispatchId);

    // O envio deve ser bloqueado porque a fatura foi paga no WHMCS
    expect($ok)->toBeFalse();

    $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')->where('id', $dispatchId)->first();
    expect($dispatch->status)->toBe('canceled')
        ->and($dispatch->last_error)->toContain('WHMCS');
});

it('bloqueia envio por whatsapp e email quando fatura esta cancelada no whmcs ou no sistema', function () {
    $customer = Customer::create([
        'name' => 'Cliente Cancelado',
        'email' => 'cancelado@example.com',
        'whatsapp' => '5521999993333',
        'active' => true,
    ]);

    $invoiceLocalCanceled = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-CANC-1',
        'description' => 'Fatura Cancelada Local',
        'amount' => 120.00,
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'canceled',
    ]);

    $dispatchId = \Illuminate\Support\Facades\DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $invoiceLocalCanceled->id,
        'channel' => 'both',
        'schedule_offset' => '-1d',
        'scheduled_at' => now()->subMinute(),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dispatcher = app(\App\Modules\Billing\Services\BillingDispatcher::class);
    $ok = $dispatcher->dispatchSpecific($dispatchId);

    expect($ok)->toBeFalse();
    $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')->where('id', $dispatchId)->first();
    expect($dispatch->status)->toBe('canceled')
        ->and($dispatch->last_error)->toContain('CANCELADA');
});

it('bloqueia envio por whatsapp e email quando fatura esta com status estornado (refunded)', function () {
    $connection = WhmcsConnection::create([
        'name' => 'WHMCS Teste Estorno',
        'api_url' => 'https://whmcs.test/includes/api.php',
        'identifier' => 'identifier-ok',
        'secret' => 'secret-ok',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Estornado',
        'email' => 'estornado@example.com',
        'whatsapp' => '5521999994444',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'WHMCS-400',
        'description' => 'Fatura Estornada',
        'amount' => 300.00,
        'due_date' => now()->addDays(1)->toDateString(),
        'status' => 'refunded',
        'meta' => [
            'whmcs_invoice_id' => '400',
            'whmcs_connection_id' => $connection->id,
            'whmcs_status' => 'Refunded',
        ],
    ]);

    $dispatchId = \Illuminate\Support\Facades\DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $invoice->id,
        'channel' => 'both',
        'schedule_offset' => '0d',
        'scheduled_at' => now()->subMinute(),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dispatcher = app(\App\Modules\Billing\Services\BillingDispatcher::class);
    $ok = $dispatcher->dispatchSpecific($dispatchId);

    expect($ok)->toBeFalse();
    $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')->where('id', $dispatchId)->first();
    expect($dispatch->status)->toBe('canceled')
        ->and($dispatch->last_error)->toContain('ESTORNADA');
});

it('cancela dispatches automaticamente quando o status da fatura e atualizado para cancelada ou estornada via model', function () {
    $customer = Customer::create([
        'name' => 'Cliente Mudança Status',
        'email' => 'mudanca@example.com',
        'whatsapp' => '5521999995555',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-TEST-500',
        'description' => 'Fatura Pendente que sera Cancelada',
        'amount' => 80.00,
        'due_date' => now()->addDays(4)->toDateString(),
        'status' => 'pending',
    ]);

    $dispatchId = \Illuminate\Support\Facades\DB::table('billing_dispatches')->insertGetId([
        'invoice_id' => $invoice->id,
        'channel' => 'both',
        'schedule_offset' => '-3d',
        'scheduled_at' => now()->addDay(),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Atualiza a fatura para cancelada
    $invoice->update(['status' => 'canceled']);

    $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')->where('id', $dispatchId)->first();
    expect($dispatch->status)->toBe('canceled')
        ->and($dispatch->last_error)->toContain('CANCELADA');
});
