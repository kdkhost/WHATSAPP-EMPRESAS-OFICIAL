<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantBillingConfig;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;
use App\Modules\WhmcsIntegration\Services\WhmcsServiceAutomation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Artisan::call('route:clear');
});

function createWeekendTestTenant(): array
{
    $plan = Plan::create([
        'name' => 'Plano Teste Finais de Semana',
        'slug' => 'plano-fds-'.uniqid(),
        'price' => 99.90,
        'extra_instance_price' => 29.90,
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
        'name' => 'Tenant User Fds',
        'email' => 'tenant-fds-'.uniqid().'@example.com',
        'password' => bcrypt('password123'),
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Teste Fds',
        'status' => 'active',
        'slug' => 'tenant-fds-'.uniqid(),
    ]);

    TenantBillingConfig::create([
        'tenant_id' => $tenant->id,
        'cron_time' => '09:00',
        'days_before_due' => [1, 3],
        'days_after_due' => [1, 3],
        'invoice_creation_days' => 5,
        'fee_passthrough' => false,
        'installment_enabled' => true,
        'max_installments' => 12,
        'charge_type' => 'both',
        'skip_weekends_holidays' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Teste Fds',
        'email' => 'cliente-fds-'.uniqid().'@example.com',
        'whatsapp' => '5511999990001',
        'active' => true,
    ]);

    return compact('user', 'tenant', 'customer');
}

it('ajusta automaticamente fatura criada para final de semana para o proximo dia util quando skip_weekends_holidays esta ativo', function () {
    $data = createWeekendTestTenant();
    $tenant = $data['tenant'];
    $customer = $data['customer'];

    // Encontra o próximo sábado a partir de hoje
    $saturday = Carbon::now('America/Sao_Paulo')->next(Carbon::SATURDAY);
    $expectedMonday = $saturday->copy()->next(Carbon::MONDAY);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'TEST-FDS-01',
        'description' => 'Cobrança Teste Fim de Semana',
        'amount' => 150.00,
        'due_date' => $saturday->toDateString(),
        'status' => 'pending',
    ]);

    // O model Invoice deve mover o vencimento do sábado para a segunda-feira
    expect($invoice->due_date->toDateString())->toBe($expectedMonday->toDateString());
});

it('move faturas pendentes existentes para o proximo dia util ao salvar configuracao de cobranca', function () {
    $data = createWeekendTestTenant();
    $tenant = $data['tenant'];
    $customer = $data['customer'];
    $user = $data['user'];

    $sunday = Carbon::now('America/Sao_Paulo')->next(Carbon::SUNDAY);
    $expectedMonday = $sunday->copy()->next(Carbon::MONDAY);

    // Salva silenciosamente sem hook para simular fatura prévia que estava cadastrada para domingo
    $invoice = new Invoice([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'number' => 'TEST-PREV-SUNDAY',
        'description' => 'Cobrança Prévia Domingo',
        'amount' => 200.00,
        'due_date' => $sunday->toDateString(),
        'status' => 'pending',
    ]);
    $invoice->saveQuietly();

    // Cria um dispatch agendado para a fatura
    DB::table('billing_dispatches')->insert([
        'invoice_id' => $invoice->id,
        'channel' => 'whatsapp',
        'schedule_offset' => '0d',
        'scheduled_at' => $sunday->copy()->setTime(9, 0),
        'status' => 'scheduled',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Faz requisição para salvar configurações do tenant com skip_weekends_holidays = true
    $response = $this->actingAs($user, 'web')->postJson(route('tenant.billing.save'), [
        'cron_time' => '09:00',
        'invoice_creation_days' => 5,
        'charge_type' => 'both',
        'fee_passthrough' => false,
        'fee_percentage' => 0.0,
        'installment_enabled' => true,
        'max_installments' => 12,
        'skip_weekends_holidays' => true,
    ]);

    $response->assertOk();
    expect($response->json('moved_invoices'))->toBeGreaterThanOrEqual(1);

    // Recarrega a fatura e o dispatch do banco
    $invoice->refresh();
    expect($invoice->due_date->toDateString())->toBe($expectedMonday->toDateString());

    $dispatch = DB::table('billing_dispatches')->where('invoice_id', $invoice->id)->first();
    expect(Carbon::parse($dispatch->scheduled_at)->toDateString())->toBe($expectedMonday->toDateString());
});

it('impede que o WHMCS suspenda servicos de clientes em finais de semana e feriados bancarios', function () {
    $data = createWeekendTestTenant();
    $tenant = $data['tenant'];
    $customer = $data['customer'];

    $conn = WhmcsConnection::create([
        'tenant_id' => $tenant->id,
        'name' => 'WHMCS Anti-Suspensão',
        'api_url' => 'https://whmcs.example.com/includes/api.php',
        'identifier' => 'whmcs-user',
        'secret' => 'whmcs-secret',
        'active' => true,
        'auto_suspend_overdue_services' => true,
        'suspend_after_days' => 0,
        'prevent_weekend_holiday_suspension' => true,
    ]);

    $service = WhmcsService::create([
        'connection_id' => $conn->id,
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'whmcs_client_id' => '10',
        'whmcs_service_id' => '999',
        'product_name' => 'Hospedagem Protegida',
        'status' => 'Active',
        'next_due_date' => now()->subDays(5)->toDateString(),
    ]);

    // Mock do serviço de feriados para simular que hoje é um sábado
    $mockHolidayService = Mockery::mock(HolidayService::class);
    $mockHolidayService->shouldReceive('isNonBusinessDay')->andReturn(true);
    app()->instance(HolidayService::class, $mockHolidayService);

    // Tenta rodar a automação de suspensão em dia não útil
    $automation = new WhmcsServiceAutomation();
    $stats = $automation->suspendOverdueServices($conn);

    // NENHUM serviço deve ser suspenso em dia não útil
    expect($stats['suspended'])->toBe(0);

    $service->refresh();
    expect($service->status)->toBe('Active');
});

it('reverte suspensao no WHMCS se detectada em dia nao util durante a sincronizacao de servicos', function () {
    $data = createWeekendTestTenant();
    $tenant = $data['tenant'];
    $customer = $data['customer'];

    $conn = WhmcsConnection::create([
        'tenant_id' => $tenant->id,
        'name' => 'WHMCS Auto Revert',
        'api_url' => 'https://whmcs.example.com/includes/api.php',
        'identifier' => 'whmcs-user',
        'secret' => 'whmcs-secret',
        'active' => true,
        'prevent_weekend_holiday_suspension' => true,
    ]);

    // Mock da API do WHMCS respondendo ao comando ModuleUnsuspend
    Http::fake([
        'https://whmcs.example.com/includes/api.php' => Http::response([
            'result' => 'success',
            'message' => 'Service unsuspended successfully',
        ], 200),
    ]);

    // Simula que hoje é fim de semana
    $mockHolidayService = Mockery::mock(HolidayService::class);
    $mockHolidayService->shouldReceive('isNonBusinessDay')->andReturn(true);
    app()->instance(HolidayService::class, $mockHolidayService);

    $client = new WhmcsClient($conn);

    // Simula produto vindo do WHMCS marcado como Suspended no final de semana
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('upsertService');
    $method->setAccessible(true);

    // Vincula o cliente ao ID do WHMCS
    $customer->update(['meta' => ['whmcs_id' => (string) $customer->id]]);

    $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
    $serviceData = [
        'id' => '888',
        'clientid' => (string) $customer->id,
        'status' => 'Suspended',
        'name' => 'Servidor VPS',
        'nextduedate' => now()->toDateString(),
    ];

    $localService = $method->invokeArgs($client, [$serviceData, &$stats]);

    // Como é dia não útil, o sistema deve executar unsuspendService e manter o serviço Active
    expect($localService)->not->toBeNull()
        ->and($localService->status)->toBe('Active');
});
