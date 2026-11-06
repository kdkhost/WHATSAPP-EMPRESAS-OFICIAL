<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Contracts\Services\ContractTemplateCatalog;
use App\Modules\EmailBilling\Services\EmailTemplateDefaults;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantBillingConfig;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    public const EMAIL = 'cliente.demo@exemplo.com';
    public const PASSWORD = 'Demo@123456';

    public function run(): void
    {
        $now = now('America/Sao_Paulo');

        $plan = $this->ensureDemoPlan($now);
        $user = $this->ensureDemoUser($now);
        $tenant = $this->ensureDemoTenant($user, $plan, $now);

        $this->ensureTenantSettings($tenant);
        $this->ensureWhatsAppTemplates($tenant, $now);
        $this->ensureCustomersAndInvoices($tenant, $now);
        $this->ensureTenantInvoices($tenant, $now);

        app(EmailTemplateDefaults::class)->ensureForTenant($tenant);
        app(ContractTemplateCatalog::class)->ensureDefaultsForTenant($tenant);

        $this->command?->info('Cliente demo criado/atualizado com sucesso.');
        $this->command?->line('Login: ' . self::EMAIL);
        $this->command?->line('Senha: ' . self::PASSWORD);
        $this->command?->line('Painel: /painel');
    }

    protected function ensureDemoPlan(Carbon $now): Plan
    {
        DB::table('plans')->updateOrInsert(
            ['slug' => 'demo'],
            [
                'name' => 'Demo / Sandbox',
                'description' => 'Plano de demonstracao para testes, apresentacoes e padronizacao comercial.',
                'price' => 0,
                'extra_instance_price' => 0,
                'billing_cycle' => 'monthly',
                'trial_days' => 30,
                'max_instances' => 3,
                'max_customers' => 100,
                'max_invoices_month' => 500,
                'max_messages_month' => 1000,
                'custom_gateway' => true,
                'custom_templates' => true,
                'installment_enabled' => true,
                'fee_passthrough' => true,
                'features' => json_encode([
                    'Painel completo para demonstracao',
                    'Dados comerciais editaveis',
                    'SMTP, WhatsApp, contratos e cobrancas',
                    'Gateway proprio em modo sandbox',
                    'Ideal para revisar o produto antes da venda',
                ], JSON_UNESCAPED_UNICODE),
                'active' => true,
                'is_demo' => true,
                'highlight' => false,
                'badge' => 'Demo',
                'sort' => 99,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return Plan::where('slug', 'demo')->firstOrFail();
    }

    protected function ensureDemoUser(Carbon $now): User
    {
        DB::table('users')->updateOrInsert(
            ['email' => self::EMAIL],
            [
                'name' => 'Cliente Demo',
                'password' => Hash::make(self::PASSWORD),
                'document' => '11222333000181',
                'phone' => '21999990000',
                'cep' => '20040002',
                'logradouro' => 'Avenida Rio Branco',
                'numero' => '156',
                'complemento' => 'Sala 1201',
                'bairro' => 'Centro',
                'cidade' => 'Rio de Janeiro',
                'uf' => 'RJ',
                'lgpd_accepted' => true,
                'lgpd_accepted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return User::where('email', self::EMAIL)->firstOrFail();
    }

    protected function ensureDemoTenant(User $user, Plan $plan, Carbon $now): Tenant
    {
        DB::table('tenants')->updateOrInsert(
            ['slug' => 'cliente-demo'],
            [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'company_name' => 'CW Solutions Demo',
                'status' => 'active',
                'trial_ends_at' => $now->copy()->addDays(30)->toDateString(),
                'current_period_start' => $now->copy()->startOfMonth()->toDateString(),
                'current_period_end' => $now->copy()->addMonth()->endOfMonth()->toDateString(),
                'gateway' => 'mercadopago',
                'settings' => json_encode([
                    'brand_name' => 'CW Solutions Demo',
                    'support_email' => 'suporte@exemplo.com',
                    'support_whatsapp' => '(21) 99999-0000',
                    'commercial_whatsapp' => '(21) 98888-0000',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return Tenant::where('slug', 'cliente-demo')->firstOrFail();
    }

    protected function ensureTenantSettings(Tenant $tenant): void
    {
        TenantInstance::updateOrCreate(
            ['tenant_id' => $tenant->id, 'instance_name' => 'cliente_demo_principal_' . $tenant->id],
            [
                'display_name' => 'Atendimento Principal',
                'phone' => '21999990000',
                'status' => 'active',
                'meta' => ['demo' => true, 'provider' => 'Meta Cloud API Oficial'],
                'is_default' => true,
            ]
        );

        TenantGateway::updateOrCreate(
            ['tenant_id' => $tenant->id, 'gateway' => 'mercadopago'],
            [
                'credentials' => [
                    'mode' => 'sandbox',
                    'public_key' => 'TEST-public-key-demo',
                    'access_token' => 'TEST-access-token-demo',
                ],
                'active' => true,
                'is_default' => true,
            ]
        );

        TenantBillingConfig::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'cron_time' => '09:00',
                'days_before_due' => [3, 1],
                'days_after_due' => [1, 3, 7, 15],
                'invoice_creation_days' => 7,
                'fee_passthrough' => true,
                'fee_percentage' => 3.49,
                'installment_enabled' => true,
                'max_installments' => 12,
                'charge_type' => 'both',
            ]
        );
    }

    protected function ensureWhatsAppTemplates(Tenant $tenant, Carbon $now): void
    {
        $templates = [
            [
                'slug' => 'demo-lembrete-3-dias-' . $tenant->id,
                'name' => 'Demo - lembrete 3 dias antes',
                'category' => 'lembrete',
                'schedule_offset' => '-3d',
                'content_html' => '<p>Ola {primeiro_nome}, tudo bem?</p><p>Sua fatura {numero_fatura}, no valor de <strong>{valor}</strong>, vence em {vencimento}.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse: {link_pagamento}</p><p>{qrcode_instrucao}</p>',
                'content_text' => "Ola {primeiro_nome}, tudo bem?\n\nSua fatura {numero_fatura}, no valor de {valor}, vence em {vencimento}.\n\nServico: {servico_principal}\nParcelas: {resumo_parcelas}\n\nAcesse: {link_pagamento}\n\n{qrcode_instrucao}",
            ],
            [
                'slug' => 'demo-atraso-1-dia-' . $tenant->id,
                'name' => 'Demo - aviso de atraso',
                'category' => 'atraso',
                'schedule_offset' => '+1d',
                'content_html' => '<p>Ola {primeiro_nome}, identificamos uma pendencia na fatura {numero_fatura}.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Regularize pelo link: {link_pagamento}</p><p>{qrcode_instrucao}</p>',
                'content_text' => "Ola {primeiro_nome}, identificamos uma pendencia na fatura {numero_fatura}.\n\nServico: {servico_principal}\nValor: {valor}\nParcelas: {resumo_parcelas}\n\nRegularize pelo link: {link_pagamento}\n\n{qrcode_instrucao}",
            ],
            [
                'slug' => 'demo-pagamento-confirmado-' . $tenant->id,
                'name' => 'Demo - pagamento confirmado',
                'category' => 'agradecimento',
                'schedule_offset' => null,
                'content_html' => '<p>Recebemos seu pagamento, {primeiro_nome}.</p><p>Obrigado por manter tudo em dia com {empresa}.</p>',
                'content_text' => "Recebemos seu pagamento, {primeiro_nome}.\n\nObrigado por manter tudo em dia com {empresa}.",
            ],
        ];

        foreach ($templates as $template) {
            DB::table('whatsapp_templates')->updateOrInsert(
                ['slug' => $template['slug']],
                $template + [
                    'tenant_id' => $tenant->id,
                    'variables' => json_encode([
                        '{nome}', '{primeiro_nome}', '{valor}', '{vencimento}',
                        '{link_pagamento}', '{numero_fatura}', '{empresa}',
                        '{servico_principal}', '{resumo_parcelas}', '{qrcode_instrucao}',
                    ]),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    protected function ensureCustomersAndInvoices(Tenant $tenant, Carbon $now): void
    {
        $customers = [
            [
                'document' => '12345678909',
                'name' => 'Joao da Silva',
                'email' => 'joao.demo@exemplo.com',
                'phone' => '21988887777',
                'whatsapp' => '21988887777',
                'city' => 'Rio de Janeiro',
                'status' => 'pending',
                'amount' => 1234.56,
                'due' => $now->copy()->addDays(3),
            ],
            [
                'document' => '98765432100',
                'name' => 'Maria Oliveira',
                'email' => 'maria.demo@exemplo.com',
                'phone' => '21977776666',
                'whatsapp' => '21977776666',
                'city' => 'Niteroi',
                'status' => 'overdue',
                'amount' => 489.90,
                'due' => $now->copy()->subDays(5),
            ],
            [
                'document' => '44555666000190',
                'name' => 'Padaria Bela Massa Ltda',
                'email' => 'financeiro@belamassa.exemplo',
                'phone' => '2133334444',
                'whatsapp' => '21966665555',
                'city' => 'Sao Goncalo',
                'status' => 'paid',
                'amount' => 899.00,
                'due' => $now->copy()->subDays(10),
            ],
        ];

        foreach ($customers as $index => $item) {
            $customer = Customer::updateOrCreate(
                ['tenant_id' => $tenant->id, 'document' => $item['document']],
                [
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'phone' => $item['phone'],
                    'whatsapp' => $item['whatsapp'],
                    'cep' => '20040002',
                    'logradouro' => 'Avenida Rio Branco',
                    'numero' => (string) (100 + $index),
                    'bairro' => 'Centro',
                    'cidade' => $item['city'],
                    'uf' => 'RJ',
                    'meta' => ['origem' => 'demo', 'segmento' => $index === 2 ? 'empresa' : 'pessoa_fisica'],
                    'active' => true,
                ]
            );

            Invoice::updateOrCreate(
                ['number' => 'DEMO-' . $tenant->id . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'description' => 'Mensalidade demo - Plano Pro',
                    'amount' => $item['amount'],
                    'due_date' => $item['due']->toDateString(),
                    'status' => $item['status'],
                    'gateway' => 'mercadopago',
                    'gateway_charge_id' => 'demo-charge-' . $tenant->id . '-' . ($index + 1),
                    'payment_url' => 'https://pagar.exemplo.com/demo-' . $tenant->id . '-' . ($index + 1),
                    'webhook_token' => Str::lower(Str::random(64)),
                    'paid_at' => $item['status'] === 'paid' ? $now->copy()->subDays(8) : null,
                    'meta' => ['demo' => true],
                ]
            );
        }
    }

    protected function ensureTenantInvoices(Tenant $tenant, Carbon $now): void
    {
        DB::table('tenant_invoices')->updateOrInsert(
            ['number' => 'TENANT-DEMO-' . $tenant->id . '-001'],
            [
                'tenant_id' => $tenant->id,
                'amount' => 0,
                'due_date' => $now->copy()->addDays(30)->toDateString(),
                'status' => 'paid',
                'gateway_charge_id' => 'demo-subscription-' . $tenant->id,
                'payment_url' => null,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
