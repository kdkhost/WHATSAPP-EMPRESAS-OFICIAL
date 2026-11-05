<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Models\TenantPackage;
use App\Modules\Tenancy\Services\InstanceManager;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createTenantForManualSend(): array
{
    $plan = Plan::create([
        'name' => 'Plano Tenant Manual',
        'slug' => 'plano-manual-'.uniqid(),
        'price' => 129.90,
        'extra_instance_price' => 39.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 2,
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
        'name' => 'Membro Tenant WhatsApp',
        'email' => 'membro-wa-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Membro WhatsApp',
        'slug' => 'membro-wa-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->startOfMonth()->toDateString(),
        'current_period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'instancia_membro_'.$tenant->id,
        'display_name' => 'WhatsApp Membro',
        'status' => 'connected',
        'is_default' => true,
    ]);

    return [$user, $tenant, $instance];
}

it('exibe a tela de historico do tenant com o botao e modal de envio manual', function () {
    [$user, $tenant] = createTenantForManualSend();

    WhatsAppTemplate::create([
        'name' => 'Template Global Sistema',
        'slug' => 'global-teste-'.uniqid(),
        'content_text' => 'Olá {nome}, sua fatura de {valor} vence em {vencimento}.',
        'content_html' => '<p>Olá {nome}!</p>',
        'active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('tenant.history.whatsapp'));

    $response->assertOk();
    $response->assertSee('Nova mensagem manual');
    $response->assertSee('modal-manual-send');
    $response->assertSee('Template Global Sistema');
});

it('permite que o tenant busque somente seus clientes via endpoint search', function () {
    [$user, $tenant] = createTenantForManualSend();

    // Cliente do tenant
    $myCustomer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Do Meu Tenant',
        'email' => 'cliente.tenant.'.uniqid().'@exemplo.com',
        'whatsapp' => '5521999998888',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $myCustomer->id,
        'number' => '9901',
        'description' => 'Serviço Mensal',
        'amount' => 199.90,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
    ]);

    // Cliente de outro tenant
    [, $otherTenant] = createTenantForManualSend();
    $otherCustomer = Customer::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Cliente De Outro Tenant',
        'email' => 'outro.cliente.'.uniqid().'@exemplo.com',
        'whatsapp' => '5521988887777',
        'active' => true,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('tenant.whatsapp.customers.search', ['q' => 'Cliente']));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $customers = $response->json('customers');

    $ids = collect($customers)->pluck('id')->all();
    expect($ids)->toContain($myCustomer->id);
    expect($ids)->not->toContain($otherCustomer->id);

    $matched = collect($customers)->firstWhere('id', $myCustomer->id);
    expect($matched['name'])->toBe('Cliente Do Meu Tenant');
    expect($matched['has_invoice'])->toBeTrue();
    expect($matched['variables']['{nome}'])->toBe('Cliente Do Meu Tenant');
});

it('renderiza template para o cliente do tenant via endpoint render-template', function () {
    [$user, $tenant] = createTenantForManualSend();

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Mariana Oliveira',
        'email' => 'mariana.'.uniqid().'@exemplo.com',
        'whatsapp' => '5531988889999',
        'active' => true,
    ]);

    Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => '4421',
        'description' => 'Assinatura Software',
        'amount' => 350.00,
        'due_date' => '2026-11-20',
        'status' => 'pending',
    ]);

    $template = WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Aviso Tenant',
        'slug' => 'aviso-tenant-'.uniqid(),
        'content_text' => 'Olá {primeiro_nome}, sua fatura #{numero_fatura} no valor de {valor} vence em {vencimento}.',
        'content_html' => '<p>Olá {primeiro_nome}</p>',
        'active' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('tenant.whatsapp.render-template'), [
            'template_id' => $template->id,
            'customer_id' => $customer->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $rendered = $response->json('rendered_text');
    expect($rendered)->toContain('Olá Mariana');
    expect($rendered)->toContain('fatura #4421');
    expect($rendered)->toContain('350,00');
    expect($rendered)->toContain('20/11/2026');
});

it('envia mensagem manual pelo tenant utilizando a instancia conectada do tenant', function () {
    [$user, $tenant, $instance] = createTenantForManualSend();

    // Mock do InstanceManager para não fazer requisições HTTP reais para Evolution
    $mock = Mockery::mock(InstanceManager::class);
    $mock->shouldReceive('sendText')
        ->twice()
        ->andReturn('EVOLUTION_MANUAL_123');
    app()->instance(InstanceManager::class, $mock);

    $template = WhatsAppTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Template Manual Tenant',
        'slug' => 'manual-tenant-'.uniqid(),
        'content_text' => 'Olá {nome}, mensagem manual.',
        'content_html' => '<p>Olá {nome}</p>',
        'active' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('tenant.whatsapp.send-manual'), [
            'to' => '5521999998888',
            'message' => 'Olá! Mensagem manual de teste para cliente.',
            'template_id' => $template->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('evolution_id', 'EVOLUTION_MANUAL_123');

    // Segundo envio manual consecutivo para o mesmo destinatário: NÃO deve ser bloqueado por campaign_cooldown_active
    $secondResponse = $this->actingAs($user)
        ->postJson(route('tenant.whatsapp.send-manual'), [
            'to' => '5521999998888',
            'message' => 'Segunda mensagem manual para o mesmo número.',
            'template_id' => $template->id,
        ]);

    $secondResponse->assertOk();
    $secondResponse->assertJsonPath('ok', true);
});

it('permite criar pacotes com intervalo Vitalicio (0) e Permuta (-1)', function () {
    [$user, $tenant] = createTenantForManualSend();

    // Vitalício
    $resVitalicio = $this->actingAs($user)
        ->postJson(route('tenant.packages.store'), [
            'name' => 'Pacote Vitalício Gold',
            'description' => 'Acesso vitalício sem cobranças recorrentes',
            'total_amount' => '1.997,00',
            'allow_cash' => 1,
            'allow_installments' => 1,
            'max_installments' => 12,
            'gateway_fee_mode' => 'tenant',
            'gateway_fee_percent' => '0,00',
            'gateway_fixed_fee' => '0,00',
            'installment_interest_percent' => '0,00',
            'active' => 1,
            'billing_interval_days' => 0,
        ]);

    $resVitalicio->assertOk();
    $resVitalicio->assertJsonPath('ok', true);
    $pkgVitalicioId = $resVitalicio->json('package.id');

    $pkg = TenantPackage::find($pkgVitalicioId);
    expect($pkg->settings['billing_interval_days'])->toBe(0);

    // Permuta
    $resPermuta = $this->actingAs($user)
        ->postJson(route('tenant.packages.store'), [
            'name' => 'Pacote Permuta Parceria',
            'description' => 'Permuta institucional com parceiro comercial',
            'total_amount' => '500,00',
            'allow_cash' => 1,
            'allow_installments' => 0,
            'max_installments' => 1,
            'gateway_fee_mode' => 'tenant',
            'gateway_fee_percent' => '0,00',
            'gateway_fixed_fee' => '0,00',
            'installment_interest_percent' => '0,00',
            'active' => 1,
            'billing_interval_days' => -1,
        ]);

    $resPermuta->assertOk();
    $resPermuta->assertJsonPath('ok', true);
    $pkgPermutaId = $resPermuta->json('package.id');

    $pkgPermuta = TenantPackage::find($pkgPermutaId);
    expect($pkgPermuta->settings['billing_interval_days'])->toBe(-1);
});

it('exibe a tela dedicada de envio manual e link no sidebar acima de envios agendados', function () {
    [$user, $tenant] = createTenantForManualSend();

    $response = $this->actingAs($user)
        ->get(route('tenant.whatsapp.manual'));

    $response->assertOk();
    $response->assertSee('Envio Manual de Mensagem');
    $response->assertSee('wa-phone-container');
    $response->assertSee('wa-bubble');
    $response->assertSee('Disparar Mensagem');
    $response->assertSee(route('tenant.whatsapp.manual'));
    $response->assertSee('Envio Manual');
    $response->assertSee('Envios Agendados');
});

it('envia notificacao de cancelamento de assinatura de pacote pelo gestor', function () {
    [$user, $tenant, $instance] = createTenantForManualSend();

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Cancelado Teste',
        'whatsapp' => '5511999998888',
        'active' => true,
    ]);

    $package = TenantPackage::create([
        'tenant_id' => $tenant->id,
        'name' => 'Pacote Mensal Cancel',
        'total_amount' => 150.00,
        'active' => true,
        'settings' => [
            'installments_count' => 1,
            'billing_interval_days' => 30,
        ],
    ]);

    $subscription = \App\Modules\Tenancy\Models\CustomerPackageSubscription::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'tenant_package_id' => $package->id,
        'status' => 'active',
        'total_amount' => 150.00,
        'final_amount' => 150.00,
        'installments_count' => 1,
        'start_date' => now(),
    ]);

    $mockMgr = Mockery::mock(InstanceManager::class);
    $mockMgr->shouldReceive('sendText')
        ->once()
        ->with(
            Mockery::on(fn ($inst) => $inst->id === $instance->id),
            Mockery::on(fn ($to) => str_contains($to, '5511999998888')),
            Mockery::on(fn ($msg) => str_contains($msg, 'cancelada') && str_contains($msg, 'Pacote Mensal Cancel')),
            Mockery::any()
        )
        ->andReturn('EVOLUTION_CANCEL_123');

    app()->instance(InstanceManager::class, $mockMgr);

    $service = app(\App\Modules\Tenancy\Services\TenantPackageService::class);
    $service->notifyCancellation($subscription);

    $this->assertDatabaseHas('whatsapp_messages', [
        'to' => '5511999998888',
        'tenant_instance_id' => $instance->id,
    ]);
});

it('confirma pagamento de fatura, cancela dispatches e dispara WhatsApp de confirmacao', function () {
    [$user, $tenant, $instance] = createTenantForManualSend();

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Pagamento Gateway',
        'whatsapp' => '5511988887777',
        'active' => true,
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'INV-TEST-99',
        'amount' => 250.00,
        'description' => 'Serviços de consultoria',
        'status' => 'pending',
        'due_date' => now()->addDays(5),
    ]);

    // Cria dispatch de cobrança pendente para testar cancelamento automático
    \Illuminate\Support\Facades\DB::table('billing_dispatches')->insert([
        'invoice_id' => $invoice->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '0d',
        'scheduled_at' => now()->addDays(2),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mockMgr = Mockery::mock(InstanceManager::class);
    $mockMgr->shouldReceive('sendText')
        ->once()
        ->with(
            Mockery::on(fn ($inst) => $inst->id === $instance->id),
            Mockery::on(fn ($to) => str_contains($to, '5511988887777')),
            Mockery::on(fn ($msg) => str_contains($msg, 'confirmação do seu pagamento') || str_contains($msg, 'INV-TEST-99')),
            Mockery::any()
        )
        ->andReturn('EVOLUTION_PAID_123');

    app()->instance(InstanceManager::class, $mockMgr);

    $settlement = app(\App\Modules\Billing\Services\InvoiceSettlementService::class);
    $ok = $settlement->settlePaid($invoice, 'mercadopago', 'MP-TX-999', 250.00);

    expect($ok)->toBeTrue();

    // Verifica que a fatura foi marcada como paga
    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_at)->not->toBeNull();

    // Verifica que o dispatch pendente foi cancelado (skipped)
    $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')
        ->where('invoice_id', $invoice->id)
        ->first();
    expect(in_array($dispatch->status, ['canceled', 'skipped'], true))->toBeTrue();

    // Verifica registro do WhatsApp de confirmação
    $this->assertDatabaseHas('whatsapp_messages', [
        'to' => '5511988887777',
        'tenant_instance_id' => $instance->id,
    ]);
});

it('exibe dashboard do tenant com kpis, saude da conta e graficos dinamicos', function () {
    [$user, $tenant] = createTenantForManualSend();

    $response = $this->actingAs($user)
        ->get(route('tenant.dashboard'));

    $response->assertOk();
    $response->assertSee('Saúde da Conta');
    $response->assertSee('Disparos WhatsApp (Últimos 14 Dias)');
    $response->assertSee('Receita Recebida por Dia (R$)');
    $response->assertSee('Adimplência Carteira');
    $response->assertSee('chartMessagesCanvas');
    $response->assertSee('chartRevenueCanvas');
});

it('WhatsAppQueuePlanner planeja envio sem erro SQL de coluna inexistente failed_at', function () {
    [$user, $tenant] = createTenantForManualSend();
    $instance = $tenant->instances()->first();

    // Cria mensagens previas para exercitar activeJobsCount
    \App\Models\WhatsAppMessage::create([
        'tenant_instance_id' => $instance->id,
        'to' => '5511999998888',
        'message' => 'Mensagem em trânsito',
        'direction' => 'out',
        'status' => 'sent',
        'delivered_at' => null,
    ]);

    $planner = app(\App\Modules\WhatsApp\Services\WhatsAppQueuePlanner::class);
    $plan = $planner->plan($instance, ['media_type' => 'text']);

    expect($plan)->toBeArray();
    expect($plan)->toHaveKey('allowed');
});

