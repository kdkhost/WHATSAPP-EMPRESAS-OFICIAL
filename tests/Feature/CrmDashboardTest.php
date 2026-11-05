<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;

it('renderiza o dashboard do crm com a estrutura reorganizada', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Admin CRM',
        'email' => 'admin-crm@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente CRM',
        'email' => 'cliente-crm@example.com',
        'whatsapp' => '21999999999',
        'document' => '12345678901',
        'active' => true,
    ]);

    Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-CRM-001',
        'description' => 'Servico de CRM',
        'amount' => 150.00,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.crm.index'))
        ->assertOk()
        ->assertSee('Relacionamento e cobranca')
        ->assertSee('Atalhos operacionais')
        ->assertSee('Ultimos clientes')
        ->assertSee('Resumo comercial')
        ->assertSee('Cliente CRM')
        ->assertSee('INV-CRM-001');
});
