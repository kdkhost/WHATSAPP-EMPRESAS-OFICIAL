<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Gateways\EfiPro\Services\EfiProGateway;
use App\Modules\Gateways\SumUp\Services\SumUpGateway;
use App\Modules\Settings\Services\SettingsManager;

it('salva configuracao aninhada do gateway e le o grupo corretamente', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Gateway',
        'email' => 'admin-gateway-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $payload = [
        'access_token' => 'APP_USR_TESTE',
        'public_key' => 'APP_PUBLIC_TESTE',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'webhook_secret' => 'webhook-secret',
        'sandbox' => 1,
        'enabled' => 1,
        'pix_enabled' => 1,
        'credit_card_enabled' => 1,
        'boleto_enabled' => 0,
        'expiration_days' => 5,
        'installments_max' => 6,
        'soft_descriptor' => 'CW SOLUTIONS',
        'default_description' => 'Cobranca SaaS',
    ];

    $this->actingAs($admin, 'admin')
        ->postJson('/admin/gateways/mercadopago', $payload)
        ->assertOk()
        ->assertJson(['ok' => true]);

    $settings = app(SettingsManager::class);
    $group = $settings->group('gateways.mercadopago');

    expect($group['access_token'] ?? null)->toBe('APP_USR_TESTE')
        ->and($group['enabled'] ?? null)->toBeTrue()
        ->and($group['pix_enabled'] ?? null)->toBeTrue()
        ->and($group['boleto_enabled'] ?? null)->toBeFalse()
        ->and($group['installments_max'] ?? null)->toBe(6);
});

it('bloqueia cobranca duplicada no calendario administrativo', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Billing',
        'email' => 'admin-billing-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Duplicado',
        'email' => 'cliente-duplicado-'.uniqid().'@example.com',
        'whatsapp' => '21999999999',
        'active' => true,
    ]);

    Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-TST-'.uniqid(),
        'description' => 'Mensalidade Premium',
        'amount' => 199.90,
        'due_date' => '2026-05-26',
        'status' => 'pending',
        'webhook_token' => str_repeat('a', 48),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson('/admin/billing/calendar/invoices', [
            'customer_id' => $customer->id,
            'description' => 'Mensalidade Premium',
            'amount' => '199,90',
            'due_date' => '2026-05-26',
            'gateway' => 'mercadopago',
        ])
        ->assertStatus(422)
        ->assertJsonPath('invoice_id', Invoice::query()->latest('id')->value('id'));
});

it('nao processa webhook efi com token errado mesmo quando txid existe', function () {
    $customer = Customer::create([
        'name' => 'Cliente Efi',
        'email' => 'cliente-efi-'.uniqid().'@example.com',
        'whatsapp' => '21999999998',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'EFI-TST-'.uniqid(),
        'description' => 'Cobranca Efi',
        'amount' => 99.90,
        'due_date' => '2026-05-26',
        'status' => 'pending',
        'gateway' => 'efipro',
        'gateway_charge_id' => 'txid-seguro',
        'webhook_token' => 'token-correto-efi',
    ]);

    $result = app(EfiProGateway::class)->handleWebhook([
        'pix' => [['txid' => 'txid-seguro', 'status' => 'CONCLUIDA']],
    ], 'token-errado-efi');

    expect($result->processed)->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('pending');
});

it('nao processa webhook sumup com token errado mesmo quando checkout existe', function () {
    $customer = Customer::create([
        'name' => 'Cliente SumUp',
        'email' => 'cliente-sumup-'.uniqid().'@example.com',
        'whatsapp' => '21999999997',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'SUMUP-TST-'.uniqid(),
        'description' => 'Cobranca SumUp',
        'amount' => 149.90,
        'due_date' => '2026-05-26',
        'status' => 'pending',
        'gateway' => 'sumup',
        'gateway_charge_id' => 'checkout-seguro',
        'webhook_token' => 'token-correto-sumup',
    ]);

    $result = app(SumUpGateway::class)->handleWebhook([
        'checkout_id' => 'checkout-seguro',
        'status' => 'PAID',
    ], 'token-errado-sumup');

    expect($result->processed)->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('pending');
});
