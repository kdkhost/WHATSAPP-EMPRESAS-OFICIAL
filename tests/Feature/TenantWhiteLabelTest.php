<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WhiteLabel\Services\TenantWhiteLabelService;
use Illuminate\Http\Request;

function createTenantForWhiteLabel(array $tenantOverrides = [], array $settings = []): array
{
    $plan = Plan::create([
        'name' => 'Plano White Label',
        'slug' => 'plano-white-label-'.uniqid(),
        'price' => 97.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
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
        'name' => 'Tenant White Label',
        'email' => 'tenant-whitelabel-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create(array_merge([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant White Label',
        'slug' => 'tenant-white-label-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
        'settings' => $settings,
    ], $tenantOverrides));

    return [$user, $tenant, $plan];
}

it('permite ao tenant configurar white label e dominio proprio com status visual pendente', function () {
    [$user, $tenant] = createTenantForWhiteLabel();

    $this->actingAs($user)
        ->get(route('tenant.whitelabel.index'))
        ->assertOk()
        ->assertSee('White Label')
        ->assertSee('Alvo DNS');

    $this->actingAs($user)
        ->postJson(route('tenant.whitelabel.save'), [
            'enabled' => true,
            'brand_name' => 'Agencia Premium',
            'slogan' => 'Cobrancas com a minha marca',
            'logo_url' => 'https://cdn.example.com/logo.png',
            'favicon_url' => 'https://cdn.example.com/favicon.ico',
            'primary_color' => '#1167ff',
            'secondary_color' => '#16c7d9',
            'sidebar_color' => '#121826',
            'custom_domain' => 'https://Portal.Cliente.com.br/login',
            'custom_css' => '.app-header{border-bottom-color:#1167ff;}',
            'footer_text' => 'Agencia Premium',
            'hide_powered_by' => true,
            'custom_login' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('status.status', 'pending');

    $tenant->refresh();

    expect($tenant->custom_domain)->toBe('portal.cliente.com.br')
        ->and($tenant->custom_domain_status)->toBe('pending')
        ->and($tenant->whiteLabelValue('brand_name'))->toBe('Agencia Premium')
        ->and($tenant->whiteLabelEnabled())->toBeTrue();

    $this->actingAs($user)
        ->get(route('tenant.dashboard'))
        ->assertOk()
        ->assertSee('Agencia Premium')
        ->assertSee('#1167ff', false);
});

it('valida DNS do dominio do tenant e libera acesso por dominio proprio', function () {
    [$user, $tenant] = createTenantForWhiteLabel([
        'custom_domain' => 'portal.cliente.test',
        'custom_domain_dns_target' => 'app.whatsapp.test',
        'custom_domain_status' => 'pending',
        'settings' => [
            'whitelabel' => [
                'enabled' => true,
                'brand_name' => 'Cliente Validado',
                'custom_login' => true,
            ],
        ],
    ]);

    $this->app->instance(TenantWhiteLabelService::class, new class(app(SettingsManager::class)) extends TenantWhiteLabelService {
        public function dnsTarget(?Request $request = null): string
        {
            return 'app.whatsapp.test';
        }

        protected function cnameRecords(string $domain): array
        {
            return ['app.whatsapp.test'];
        }

        protected function addressRecords(string $domain): array
        {
            return ['10.10.10.10'];
        }
    });

    $this->actingAs($user)
        ->postJson(route('tenant.whitelabel.check'))
        ->assertOk()
        ->assertJsonPath('status.status', 'active')
        ->assertJsonPath('status.verified', true);

    expect($tenant->fresh()->custom_domain_status)->toBe('active');

    $this->get('http://portal.cliente.test/')
        ->assertRedirect('/painel');

    $this->get('http://portal.cliente.test/login')
        ->assertOk()
        ->assertSee('Cliente Validado');
});

it('salva configuracoes globais do superadmin no grupo whitelabel correto', function () {
    $admin = AdminUser::create([
        'name' => 'Super White Label',
        'email' => 'super-whitelabel@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.whitelabel.save'), [
            'brand_name' => 'Marca Global',
            'dns_target' => 'app.whatsapp.test',
            'tenant_enabled' => '1',
            'ssl_enabled' => '1',
            'hide_powered_by' => '0',
            'custom_login' => '1',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $settings = app(SettingsManager::class);

    expect($settings->get('whitelabel.brand_name'))->toBe('Marca Global')
        ->and($settings->get('whitelabel.dns_target'))->toBe('app.whatsapp.test')
        ->and($settings->get('whitelabel.tenant_enabled'))->toBeTrue();
});
