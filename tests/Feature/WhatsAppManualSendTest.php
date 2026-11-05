<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Templates\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createAdminForManualSend(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin WhatsApp Manual',
        'email' => 'admin-manual-'.uniqid().'@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);
}

it('exibe a tela de mensagens com o botao e modal de envio manual', function () {
    $admin = createAdminForManualSend();

    WhatsAppTemplate::create([
        'name' => 'Lembrete de Vencimento Teste',
        'slug' => 'lembrete-teste-'.uniqid(),
        'content_text' => 'Olá {nome}, sua fatura de {valor} vence em {vencimento}. Pague aqui: {link_pagamento}',
        'content_html' => '<p>Olá {nome}, sua fatura de {valor} vence em {vencimento}.</p>',
        'active' => true,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->get('/admin/whatsapp/messages');

    $response->assertOk();
    $response->assertSee('Nova mensagem manual');
    $response->assertSee('modal-manual-send');
    $response->assertSee('Lembrete de Vencimento Teste');
});

it('permite buscar clientes para envio manual via endpoint search', function () {
    $admin = createAdminForManualSend();

    $customer = Customer::create([
        'name' => 'Marcelo Silva Teste',
        'email' => 'marcelo.'.uniqid().'@exemplo.com',
        'whatsapp' => '5521981325441',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => '1001',
        'description' => 'Mensalidade Hospedagem',
        'amount' => 150.00,
        'due_date' => now()->addDays(3)->format('Y-m-d'),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->getJson('/admin/whatsapp/customers/search?q=Marcelo');

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $customers = $response->json('customers');
    expect($customers)->not->toBeEmpty();
    expect($customers[0]['id'])->toBe($customer->id);
    expect($customers[0]['name'])->toBe('Marcelo Silva Teste');
    expect($customers[0]['has_invoice'])->toBeTrue();
    expect($customers[0]['variables']['{nome}'])->toBe('Marcelo Silva Teste');
    expect($customers[0]['variables']['{primeiro_nome}'])->toBe('Marcelo');
});

it('renderiza template com dados do cliente selecionado via endpoint render-template', function () {
    $admin = createAdminForManualSend();

    $customer = Customer::create([
        'name' => 'Carlos Alberto',
        'email' => 'carlos.'.uniqid().'@exemplo.com',
        'whatsapp' => '5511999998888',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => '5002',
        'description' => 'Plano Premium',
        'amount' => 250.00,
        'due_date' => '2026-10-15',
        'status' => 'pending',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'Aviso de Cobranca Teste',
        'slug' => 'aviso-cobranca-'.uniqid(),
        'content_text' => 'Olá {primeiro_nome}! Sua fatura #{numero_fatura} no valor de {valor} vence em {vencimento}.',
        'content_html' => '<p>Olá {primeiro_nome}!</p>',
        'active' => true,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->postJson('/admin/whatsapp/render-template', [
            'template_id' => $template->id,
            'customer_id' => $customer->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $rendered = $response->json('rendered_text');
    expect($rendered)->toContain('Olá Carlos!');
    expect($rendered)->toContain('Sua fatura #5002');
    expect($rendered)->toContain('250,00');
    expect($rendered)->toContain('15/10/2026');
});

it('valida campos obrigatorios no envio manual', function () {
    $admin = createAdminForManualSend();

    // Sem número de telefone
    $this->actingAs($admin, 'admin')
        ->postJson('/admin/whatsapp/send-manual', [
            'to' => '',
            'message' => 'Teste de envio manual',
        ])
        ->assertStatus(422);

    // Sem mensagem
    $this->actingAs($admin, 'admin')
        ->postJson('/admin/whatsapp/send-manual', [
            'to' => '5521981325441',
            'message' => '',
        ])
        ->assertStatus(422);
});
