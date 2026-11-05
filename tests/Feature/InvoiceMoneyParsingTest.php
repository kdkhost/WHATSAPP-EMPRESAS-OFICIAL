<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Permissions\Http\Middleware\CheckModulePermission;

it('salva e exibe valores monetarios brasileiros sem multiplicar por cem', function () {
    $this->withoutMiddleware([
        Ensure2FA::class,
        CheckModulePermission::class,
    ]);

    $admin = AdminUser::create([
        'name' => 'Admin Valores',
        'email' => 'admin-valores@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Valor',
        'whatsapp' => '21999999999',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.invoice-editor.store'), [
            'customer_id' => $customer->id,
            'description' => '<p>Servico mensal</p>',
            'amount' => 'R$ 97,90',
            'due_date' => '2026-05-27',
            'status' => 'pending',
            'gateway' => 'mercadopago',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $invoice = Invoice::latest('id')->first();

    expect((float) $invoice->amount)->toBe(97.90);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.invoice-editor.edit', $invoice->id))
        ->assertOk()
        ->assertSee('R$ 97,90');
});
