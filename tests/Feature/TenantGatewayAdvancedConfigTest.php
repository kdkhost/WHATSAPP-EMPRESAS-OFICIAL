<?php

use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantGateway;

function createTenantForGatewayConfig(): array
{
    $plan = Plan::create([
        'name' => 'Plano Gateway',
        'slug' => 'plano-gateway-'.uniqid(),
        'price' => 149.90,
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
        'name' => 'Tenant Gateway',
        'email' => 'tenant-gateway-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Gateway',
        'slug' => 'tenant-gateway-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    return [$user, $tenant];
}

it('salva configuracao avancada do gateway do tenant com webhook dinamico', function () {
    [$user, $tenant] = createTenantForGatewayConfig();

    $this->actingAs($user)
        ->postJson(route('tenant.gateways.save'), [
            'gateway' => 'mercadopago',
            'credentials' => [
                'access_token' => 'mp-token',
                'public_key' => 'pk-test',
            ],
            'is_default' => true,
            'environment' => 'sandbox',
            'priority' => 15,
            'payment_methods' => [
                'pix' => true,
                'credit' => true,
                'boleto' => false,
            ],
            'fees' => [
                'fixed' => 2.50,
                'percent' => 3.99,
            ],
            'split_rules' => [
                'percent' => 10,
                'fixed' => 1.25,
            ],
            'installment_rules' => [
                'max_installments' => 12,
                'interest_free_installments' => 3,
                'interest_percent' => 1.99,
            ],
            'settings' => [
                'limit_per_charge' => 5000,
                'antifraud_enabled' => true,
                'ip_allowlist' => '1.2.3.4,5.6.7.8',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('webhook.signature_header', 'X-Webhook-Signature');

    $gateway = TenantGateway::where('tenant_id', $tenant->id)->where('gateway', 'mercadopago')->firstOrFail();

    expect($gateway->is_default)->toBeTrue()
        ->and($gateway->environment)->toBe('sandbox')
        ->and($gateway->priority)->toBe(15)
        ->and($gateway->payment_methods['pix'])->toBeTrue()
        ->and($gateway->fees['percent'])->toBe(3.99)
        ->and($gateway->split_rules['percent'])->toBe(10)
        ->and($gateway->installment_rules['max_installments'])->toBe(12)
        ->and($gateway->settings['antifraud_enabled'])->toBeTrue()
        ->and(filled($gateway->webhook_token))->toBeTrue()
        ->and(filled($gateway->webhook_secret))->toBeTrue();
});

it('permite upload e exclusao de certificado PEM da Efi pelo tenant', function () {
    [$user, $tenant] = createTenantForGatewayConfig();

    // Cria o TenantGateway prévio
    TenantGateway::create([
        'tenant_id' => $tenant->id,
        'gateway' => 'efipro',
        'credentials' => [
            'client_id' => 'Client_Id_test',
            'client_secret' => 'Client_Secret_test',
            'pix_key' => 'chave@pix.com',
        ],
        'active' => true,
    ]);

    $fakePemContent = "-----BEGIN CERTIFICATE-----\nMIIDXTCCAkWgAwIBAgIU...\n-----END CERTIFICATE-----\n-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA...\n-----END RSA PRIVATE KEY-----";
    $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('meu_certificado.pem', $fakePemContent);

    $response = $this->actingAs($user)
        ->postJson(route('tenant.gateways.upload_certificate'), [
            'certificate' => $uploadedFile,
            'gateway' => 'efipro',
        ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('original_name', 'meu_certificado.pem');

    $tg = TenantGateway::where('tenant_id', $tenant->id)->where('gateway', 'efipro')->firstOrFail();
    expect($tg->credentials['certificate_path'])->not->toBeEmpty();
    expect(file_exists($tg->credentials['certificate_path']))->toBeTrue();

    // Testa exclusão do certificado
    $deleteResp = $this->actingAs($user)
        ->deleteJson(route('tenant.gateways.delete_certificate', 'efipro'));

    $deleteResp->assertOk()->assertJsonPath('ok', true);

    $tg->refresh();
    expect(isset($tg->credentials['certificate_path']))->toBeFalse();
});

it('testa conexao do Mercado Pago com mock de API', function () {
    [$user, $tenant] = createTenantForGatewayConfig();

    \Illuminate\Support\Facades\Http::fake([
        'https://api.mercadopago.com/users/me' => \Illuminate\Support\Facades\Http::response([
            'id' => 12345678,
            'nickname' => 'LOJA_TESTE',
            'first_name' => 'Loja Teste',
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('tenant.gateways.test'), [
            'gateway' => 'mercadopago',
            'credentials' => [
                'access_token' => 'APP_USR-test-token-valid',
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonFragment(['ok' => true]);
});

it('gera pix com sucesso no MercadoPagoGateway e calcula date_of_expiration', function () {
    [$user, $tenant] = createTenantForGatewayConfig();

    $tg = TenantGateway::create([
        'tenant_id' => $tenant->id,
        'gateway' => 'mercadopago',
        'credentials' => [
            'access_token' => 'APP_USR-test-token-valid',
        ],
        'settings' => [
            'expiration_days' => 2,
        ],
        'active' => true,
    ]);

    $gateway = app(\App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway::class);
    $gateway->setTenantContext($tg);

    $expirationFuture = $gateway->resolvePixExpiration([
        'due_date' => now()->addDays(5)->format('Y-m-d'),
    ]);
    expect($expirationFuture)->not->toBeNull();

    $expirationFallback = $gateway->resolvePixExpiration([
        'due_date' => now()->subDay()->format('Y-m-d'),
    ]);
    expect($expirationFallback)->not->toBeNull();

    \Illuminate\Support\Facades\Http::fake([
        'https://api.mercadopago.com/v1/payments/search*' => \Illuminate\Support\Facades\Http::response([
            'results' => [],
        ], 200),
        'https://api.mercadopago.com/v1/payments' => \Illuminate\Support\Facades\Http::response([
            'id' => 987654321,
            'status' => 'pending',
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code' => '00020126580014br.gov.bcb.pix...',
                    'qr_code_base64' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/987654321/ticket',
                ],
            ],
        ], 201),
    ]);

    $invoice = [
        'id' => 9999,
        'tenant_id' => $tenant->id,
        'amount' => 50.00,
        'description' => 'Fatura Teste',
        'due_date' => now()->addDays(3)->format('Y-m-d'),
        'customer' => [
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'document' => '12345678901',
        ],
    ];

    $result = $gateway->createCharge($invoice, 'pix');
    expect($result->chargeId)->toBe('987654321');
    expect($result->raw['point_of_interaction']['transaction_data']['qr_code'])->toBe('00020126580014br.gov.bcb.pix...');
    expect($result->raw['point_of_interaction']['transaction_data']['qr_code_base64'])->toContain('data:image/png;base64,');
});

