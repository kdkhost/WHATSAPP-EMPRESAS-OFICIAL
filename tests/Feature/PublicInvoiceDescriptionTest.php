<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

it('renderiza descricao rica com tags html sanitizadas sem escapar como texto na fatura publica', function () {
    $plan = Plan::create([
        'name' => 'Plano Public Test',
        'slug' => 'plano-public-test-'.uniqid(),
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Tenant Public User',
        'email' => 'public-user-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Cobranca WhatsApp',
        'slug' => 'tenant-public-'.uniqid(),
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Monique Motta',
        'email' => 'monique@example.com',
        'whatsapp' => '21999998888',
        'document' => '055.259.277-30',
        'active' => true,
    ]);

    $htmlDescription = '<p data-start="28" data-end="384">Prestação de serviço digital de <strong data-start="60" data-end="77">Marketing 360</strong>, </p><ul><li>incluindo planejamento estratégico, </li><li>gestão de presença online, </li></ul>';

    $token = Str::random(48);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-000001',
        'description' => $htmlDescription,
        'amount' => 150.00,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
        'webhook_token' => $token,
    ]);

    $response = $this->get(route('public.invoice.show', $token));

    $response->assertOk();

    // Não deve conter tags escapadas como texto puro &lt;p data-start=
    $response->assertDontSee('&lt;p data-start=', false);
    $response->assertDontSee('&lt;strong', false);
    $response->assertDontSee('&lt;ul&gt;', false);

    // Deve conter os elementos HTML limpos renderizados sem os atributos data-start e data-end
    $response->assertSee('<strong>Marketing 360</strong>', false);
    $response->assertSee('<li>incluindo planejamento estratégico, </li>', false);
    $response->assertDontSee('data-start="28"', false);
    $response->assertDontSee('data-end="384"', false);
});
