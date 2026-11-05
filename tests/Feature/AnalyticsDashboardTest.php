<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Models\BillingDispatch;
use App\Models\Customer;
use App\Models\Invoice;

it('renderiza o dashboard de analytics com a estrutura premium', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Admin Analytics',
        'email' => 'admin-analytics@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertSee('Painel analitico')
        ->assertSee('Filtro e atualizacao')
        ->assertSee('Receita por dia')
        ->assertSee('Top clientes');
});

it('retorna os dados de analytics para os graficos', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Admin Analytics Dados',
        'email' => 'admin-analytics-dados@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Cliente Analytics',
        'email' => 'cliente-analytics@example.com',
        'whatsapp' => '21999999999',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-ANL-001',
        'description' => 'Servico mensal',
        'amount' => 199.90,
        'due_date' => now()->toDateString(),
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    BillingDispatch::create([
        'invoice_id' => $invoice->id,
        'schedule_offset' => 'manual',
        'scheduled_at' => now(),
        'status' => 'sent',
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.analytics.data', ['days' => 30]))
        ->assertOk()
        ->assertJsonStructure([
            'period' => ['days', 'since'],
            'revenue',
            'invoices',
            'dispatches',
            'top_clients',
            'kpis' => [
                'total_revenue',
                'total_invoices',
                'paid_invoices',
                'overdue',
                'conversion_rate',
                'messages_sent',
            ],
        ]);
});
