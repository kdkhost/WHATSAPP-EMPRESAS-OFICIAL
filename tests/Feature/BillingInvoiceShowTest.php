<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;

it('renderiza detalhes da fatura com descricao rica sem marcadores do editor', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Admin Billing',
        'email' => 'billing-admin@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Monique Motta',
        'whatsapp' => '21989588235',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-000001',
        'description' => '<p data-start="28" data-end="384">Prestacao de servico digital de <strong data-start="60" data-end="77">Marketing 360</strong>.</p>',
        'amount' => 150000,
        'due_date' => '2026-05-27',
        'status' => 'pending',
        'gateway' => 'mercadopago',
    ]);

    $invoice->dispatches()->create([
        'schedule_offset' => 'manual',
        'scheduled_at' => now(),
        'status' => 'failed',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.billing.invoices.show', $invoice->id))
        ->assertOk()
        ->assertSee('<strong>Marketing 360</strong>', false)
        ->assertSee('Pendente')
        ->assertSee('Falhou')
        ->assertDontSee('data-start')
        ->assertDontSee('data-end')
        ->assertDontSee('&lt;p');
});
