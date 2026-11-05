<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Models\BillingDispatch;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

function billingCalendarAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Admin Calendario',
        'email' => 'admin-calendario@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
    ]);
}

function billingCalendarCustomer(): Customer
{
    return Customer::create([
        'name' => 'Cliente Calendario',
        'email' => 'cliente-calendario@example.com',
        'whatsapp' => '21999999999',
        'active' => true,
    ]);
}

it('exibe feriados no calendario de cobrancas', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    DB::table('holidays')->insert([
        'name' => 'Dia do Trabalho',
        'date' => '2026-05-01',
        'type' => 'national',
        'uf' => null,
        'color' => '#dc3545',
        'block_billing' => true,
        'block_suspension' => true,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(billingCalendarAdmin(), 'admin')
        ->getJson(route('admin.billing.calendar.events', [
            'start' => '2026-05-01',
            'end' => '2026-05-31',
        ]))
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'Feriado: Dia do Trabalho',
            'start' => '2026-05-01',
        ])
        ->assertJsonFragment([
            'kind' => 'holiday',
            'blocked' => true,
        ]);
});

it('bloqueia mover vencimento para feriado e permite dia util', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    DB::table('holidays')->insert([
        'name' => 'Dia do Trabalho',
        'date' => '2026-05-01',
        'type' => 'national',
        'uf' => null,
        'color' => '#dc3545',
        'block_billing' => true,
        'block_suspension' => true,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoice = Invoice::create([
        'customer_id' => billingCalendarCustomer()->id,
        'number' => 'INV-CAL-001',
        'description' => 'Servico mensal',
        'amount' => 99.90,
        'due_date' => '2026-05-04',
        'status' => 'pending',
        'gateway' => 'mercadopago',
    ]);

    $admin = billingCalendarAdmin();

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.billing.calendar.events.move'), [
            'id' => 'inv-'.$invoice->id,
            'start' => '2026-05-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.billing.calendar.events.move'), [
            'id' => 'inv-'.$invoice->id,
            'start' => '2026-05-05',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($invoice->fresh()->due_date->toDateString())->toBe('2026-05-05');
});

it('bloqueia cobranca manual em feriado no calendario', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    DB::table('holidays')->insert([
        'name' => 'Dia do Trabalho',
        'date' => '2026-05-01',
        'type' => 'national',
        'uf' => null,
        'color' => '#dc3545',
        'block_billing' => true,
        'block_suspension' => true,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(billingCalendarAdmin(), 'admin')
        ->postJson(route('admin.billing.calendar.invoices.store'), [
            'customer_id' => billingCalendarCustomer()->id,
            'description' => 'Cobranca manual',
            'amount' => 'R$ 120,00',
            'due_date' => '2026-05-01',
            'gateway' => 'mercadopago',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    expect(Invoice::where('description', 'Cobranca manual')->exists())->toBeFalse();
});

it('cancela cobranca e envios agendados pelo calendario', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $invoice = Invoice::create([
        'customer_id' => billingCalendarCustomer()->id,
        'number' => 'INV-CAL-002',
        'description' => 'Servico mensal',
        'amount' => 99.90,
        'due_date' => '2026-05-05',
        'status' => 'pending',
        'gateway' => 'mercadopago',
    ]);

    $dispatch = BillingDispatch::create([
        'invoice_id' => $invoice->id,
        'schedule_offset' => 'manual',
        'scheduled_at' => '2026-05-05 09:00:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs(billingCalendarAdmin(), 'admin')
        ->postJson(route('admin.billing.calendar.events.cancel'), [
            'id' => 'inv-'.$invoice->id,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($invoice->fresh()->status)->toBe('canceled')
        ->and($dispatch->fresh()->status)->toBe('canceled');
});
