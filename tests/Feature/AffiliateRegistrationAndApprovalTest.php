<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateReferral;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvoice;
use App\Modules\Tenancy\Services\TenantInvoicePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateRegistrationAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function createClientUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Usuário Teste ' . Str::random(5),
            'email'    => 'user-' . Str::random(8) . '@teste.com',
            'phone'    => '11999998888',
            'password' => bcrypt('password'),
        ], $attrs));
    }

    protected function createTestPlan(): Plan
    {
        return Plan::create([
            'name'                => 'Plano Teste ' . Str::random(4),
            'slug'                => 'plano-teste-' . Str::random(6),
            'price'               => 99.90,
            'billing_cycle'       => 'monthly',
            'max_instances'       => 5,
            'max_customers'       => 500,
            'max_invoices_month'  => 500,
            'max_messages_month'  => 5000,
            'active'              => true,
        ]);
    }

    protected function createTestTenant(User $user, array $attrs = []): Tenant
    {
        $plan = $this->createTestPlan();

        return Tenant::create(array_merge([
            'user_id'      => $user->id,
            'plan_id'      => $plan->id,
            'company_name' => 'Empresa Teste ' . Str::random(5),
            'slug'         => 'empresa-' . Str::random(6),
            'status'       => 'active',
        ], $attrs));
    }

    protected function createAdmin(): AdminUser
    {
        return AdminUser::create([
            'name'     => 'Admin ' . Str::random(5),
            'email'    => 'admin-' . Str::random(8) . '@teste.com',
            'password' => bcrypt('password'),
            'role'     => 'superadmin',
            'active'   => true,
        ]);
    }

    public function test_tenant_sem_registro_deve_ver_formulario_de_inscricao()
    {
        $user = $this->createClientUser();
        $tenant = $this->createTestTenant($user, [
            'company_name' => 'Empresa Teste Inscrição',
        ]);

        $this->actingAs($user);

        $response = $this->get('/painel/afiliado');

        $response->assertStatus(200);
        $response->assertSee('Formulário de Inscrição');
        $response->assertSee('Canal Principal de Divulgação');
        $response->assertSee('Chave PIX p/ Recebimento');

        // Garante que o afiliado NÃO foi criado automaticamente como ativo
        $this->assertDatabaseMissing('affiliates', [
            'user_id' => $user->id,
        ]);
    }

    public function test_tenant_consegue_submeter_inscricao_e_fica_com_status_pending()
    {
        $user = $this->createClientUser(['phone' => '11988887777']);
        $tenant = $this->createTestTenant($user, [
            'company_name' => 'Tech Corp',
        ]);

        $this->actingAs($user);

        $payload = [
            'promotion_channel' => 'Redes Sociais (Instagram, TikTok, Facebook)',
            'pix_type'          => 'cpf',
            'pix_key'           => '12345678901',
            'mp_collector_id'   => '123456789',
            'terms_accepted'    => 1,
        ];

        $response = $this->postJson('/painel/afiliado/apply', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);

        $this->assertDatabaseHas('affiliates', [
            'user_id'           => $user->id,
            'tenant_id'         => $tenant->id,
            'status'            => 'pending',
            'promotion_channel' => 'Redes Sociais (Instagram, TikTok, Facebook)',
            'mp_collector_id'   => '123456789',
        ]);

        // Acessa a página novamente e deve ver o status de pendência em análise
        $pageResponse = $this->get('/painel/afiliado');
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('Sua Inscrição está em Análise pela Administração');
    }

    public function test_admin_consegue_aprovar_afiliado_com_split_mercado_pago()
    {
        $admin = $this->createAdmin();
        $user = $this->createClientUser(['phone' => '11999998888']);
        $tenant = $this->createTestTenant($user, [
            'company_name' => 'Parceiro Alfa',
        ]);

        $affiliate = Affiliate::create([
            'user_id'           => $user->id,
            'tenant_id'         => $tenant->id,
            'slug'              => 'parceiro-alfa-' . Str::random(5),
            'referral_code'     => 'ALFA2026',
            'status'            => 'pending',
            'promotion_channel' => 'WhatsApp',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->postJson("/admin/affiliates/{$affiliate->id}/approve", [
            'split_enabled'   => true,
            'split_pct'       => 25.00,
            'mp_collector_id' => '987654321',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $affiliate->refresh();
        $this->assertEquals('active', $affiliate->status);
        $this->assertTrue($affiliate->split_enabled);
        $this->assertEquals(25.00, (float) $affiliate->split_pct);
        $this->assertEquals('987654321', $affiliate->mp_collector_id);
        $this->assertNotNull($affiliate->approved_at);
    }

    public function test_admin_consegue_rejeitar_afiliado_com_motivo()
    {
        $admin = $this->createAdmin();
        $user = $this->createClientUser();
        $tenant = $this->createTestTenant($user, [
            'company_name' => 'Parceiro Beta',
        ]);

        $affiliate = Affiliate::create([
            'user_id'           => $user->id,
            'tenant_id'         => $tenant->id,
            'slug'              => 'parceiro-beta-' . Str::random(5),
            'referral_code'     => 'BETA2026',
            'status'            => 'pending',
            'promotion_channel' => 'Tráfego Pago',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->postJson("/admin/affiliates/{$affiliate->id}/reject", [
            'reason' => 'Canal de divulgação informado viola as diretrizes de spam.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $affiliate->refresh();
        $this->assertEquals('rejected', $affiliate->status);
        $this->assertEquals('Canal de divulgação informado viola as diretrizes de spam.', $affiliate->rejection_reason);

        // Cliente entra no painel e vê a mensagem de rejeição
        $this->actingAs($user);
        $clientResponse = $this->get('/painel/afiliado');
        $clientResponse->assertStatus(200);
        $clientResponse->assertSee('Inscrição Não Aprovada ou com Pendência');
        $clientResponse->assertSee('Canal de divulgação informado viola as diretrizes de spam.');
    }

    public function test_admin_consegue_marcar_pendencia_com_instrucoes()
    {
        $admin = $this->createAdmin();
        $user = $this->createClientUser();
        $affiliate = Affiliate::create([
            'user_id'       => $user->id,
            'slug'          => 'parceiro-gama-' . Str::random(5),
            'referral_code' => 'GAMA2026',
            'status'        => 'pending',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->postJson("/admin/affiliates/{$affiliate->id}/pend", [
            'instructions' => 'Por favor cadastre sua chave PIX no formato CPF e informe seu Instagram.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $affiliate->refresh();
        $this->assertEquals('pending', $affiliate->status);
        $this->assertEquals('Por favor cadastre sua chave PIX no formato CPF e informe seu Instagram.', $affiliate->rejection_reason);
    }

    public function test_split_mercado_pago_automatico_liquida_comissao_diretamente_ao_pagar_fatura()
    {
        // 1. Cria o afiliado promotor com split MP ativo
        $promoterUser = $this->createClientUser(['phone' => '11911112222']);
        $promoterTenant = $this->createTestTenant($promoterUser, [
            'company_name' => 'Promotor Oficial',
        ]);

        $affiliate = Affiliate::create([
            'user_id'         => $promoterUser->id,
            'tenant_id'       => $promoterTenant->id,
            'slug'            => 'promotor-top-' . Str::random(5),
            'referral_code'   => 'TOP2026',
            'status'          => 'active',
            'commission_pct'  => 20.00,
            'split_enabled'   => true,
            'split_pct'       => 20.00,
            'mp_collector_id' => '123456789',
        ]);

        // 2. Cria o cliente indicado
        $buyerUser = $this->createClientUser();
        $buyerTenant = $this->createTestTenant($buyerUser, [
            'company_name' => 'Comprador Indicado',
        ]);

        // Registra indicação
        AffiliateReferral::create([
            'affiliate_id'      => $affiliate->id,
            'user_id'           => $buyerUser->id,
            'tenant_id'         => $buyerTenant->id,
            'cookie_expires_at' => now()->addDays(60),
        ]);

        // Cria a fatura do comprador
        $invoice = TenantInvoice::create([
            'tenant_id'     => $buyerTenant->id,
            'number'        => 'INV-SPLIT-001',
            'amount'        => 150.00,
            'status'        => 'pending',
            'due_date'      => now()->addDays(3),
            'meta'          => [
                'split_mercadopago' => [
                    'affiliate_id' => $affiliate->id,
                    'collector_id' => '123456789',
                    'split_amount' => 30.00,
                ],
            ],
        ]);

        // 3. Liquida a fatura
        app(TenantInvoicePaymentService::class)->markInvoicePaid($invoice, 'MP-SPLIT-CHARGE-123');

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);

        // 4. Verifica se a comissão foi gerada e liquidada automaticamente via split MP
        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_id'      => $affiliate->id,
            'tenant_invoice_id' => $invoice->id,
            'commission_amount' => 30.00,
            'status'            => 'paid',
        ]);

        $affiliate->refresh();
        $this->assertEquals(30.00, (float) $affiliate->total_earned);
        $this->assertEquals(30.00, (float) $affiliate->total_paid);
    }

    public function test_admin_consegue_cadastrar_novo_afiliado_com_campos_mascarados()
    {
        $admin = $this->createAdmin();
        $user = $this->createClientUser(['phone' => '11988887777']);
        $tenant = $this->createTestTenant($user, [
            'company_name' => 'Empresa Foco7',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->postJson('/admin/affiliates/store', [
            'tenant_id'        => $tenant->id,
            'slug'             => 'foco7',
            'status'           => 'active',
            'commission_mode'  => 'percentage',
            'commission_pct'   => '20',
            'commission_fixed' => '0,00',
            'commission_type'  => 'recurring',
            'discount_mode'    => 'fixed',
            'discount_pct'     => '10',
            'discount_fixed'   => '0,00',
            'discount_type'    => 'first_payment',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Afiliado cadastrado com sucesso!');

        $this->assertDatabaseHas('affiliates', [
            'user_id'   => $user->id,
            'tenant_id' => $tenant->id,
            'slug'      => 'foco7',
            'status'    => 'active',
        ]);
    }
}
