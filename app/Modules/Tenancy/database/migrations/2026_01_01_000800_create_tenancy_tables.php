<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenancy SaaS: planos, assinaturas, numeros Meta (WhatsApp Cloud API), isolamento de dados.
 *
 * Hierarquia:
 *   superadmin -> gerencia planos, periodos de teste, precos
 *   tenant (cliente) -> compra plano, cria instancias, configura gateways/templates/cron
 *   end_customer -> cliente do tenant (recebe cobrancas)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Planos/Pacotes (configurados pelo superadmin)
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');                        // "Starter", "Pro", "Enterprise"
            $t->string('slug', 60)->unique();
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2);               // mensalidade
            $t->enum('billing_cycle', ['monthly', 'quarterly', 'semiannual', 'annual'])->default('monthly');
            $t->unsignedInteger('trial_days')->default(7); // periodo de teste configuravel
            $t->unsignedInteger('max_instances')->default(1); // numeros Meta incluidos
            $t->unsignedInteger('max_customers')->default(100); // clientes por tenant
            $t->unsignedInteger('max_invoices_month')->default(500); // faturas/mes
            $t->unsignedInteger('max_messages_month')->default(1000); // msgs whatsapp/mes
            $t->boolean('custom_gateway')->default(false); // pode configurar proprio gateway
            $t->boolean('custom_templates')->default(true); // pode criar templates proprios
            $t->boolean('installment_enabled')->default(false); // parcelamento
            $t->boolean('fee_passthrough')->default(false); // repasse de taxas ao cliente
            $t->json('features')->nullable();           // features extras do plano
            $t->boolean('active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        // Tenants (clientes que compram o sistema)
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('plans');
            $t->string('company_name');
            $t->string('slug', 60)->unique();           // usado no subdominio ou path
            $t->enum('status', ['trial', 'active', 'suspended', 'canceled'])->default('trial')->index();
            $t->date('trial_ends_at')->nullable();
            $t->date('current_period_start')->nullable();
            $t->date('current_period_end')->nullable();
            $t->string('gateway', 30)->nullable();      // gateway do superadmin para cobrar o tenant
            $t->string('gateway_subscription_id')->nullable();
            $t->json('settings')->nullable();            // configs proprias do tenant
            $t->timestamps();
        });

        // Numeros da WhatsApp Cloud API Oficial (Meta) por tenant
        Schema::create('tenant_instances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('instance_name', 80)->unique();  // nome_id (lowercase, sem espacos)
            $t->string('display_name')->nullable();      // nome amigavel
            $t->string('phone', 20)->nullable();
            $t->enum('status', ['creating', 'active', 'connected', 'disconnected', 'suspended', 'banned'])->default('creating');
            $t->json('meta')->nullable();
            $t->boolean('is_default')->default(false);   // instancia padrao do plano
            $t->timestamps();
        });

        // Gateway de pagamento do tenant (cada cliente configura o seu)
        Schema::create('tenant_gateways', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('gateway', 30);                  // mercadopago, efipro, sumup
            $t->json('credentials')->nullable();        // encriptado no model
            $t->boolean('active')->default(true);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'gateway']);
        });

        // Configuracao de cobranca do tenant
        Schema::create('tenant_billing_config', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $t->string('cron_time', 5)->default('09:00'); // horario do envio (HH:mm)
            $t->json('days_before_due')->nullable();       // ex: [3, 1] (3 dias antes, 1 dia antes)
            $t->json('days_after_due')->nullable();        // ex: [1, 3, 7, 15] (apos vencimento)
            $t->unsignedInteger('invoice_creation_days')->default(7); // criar fatura X dias antes
            $t->boolean('fee_passthrough')->default(false); // repassar taxas ao cliente final
            $t->decimal('fee_percentage', 5, 2)->default(0); // % de taxa repassada
            $t->boolean('installment_enabled')->default(false);
            $t->unsignedTinyInteger('max_installments')->default(12);
            $t->enum('charge_type', ['single', 'installment', 'both'])->default('both');
            $t->timestamps();
        });

        // Faturas do tenant (cobranca da mensalidade pelo superadmin)
        Schema::create('tenant_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('number', 30)->unique();
            $t->decimal('amount', 10, 2);
            $t->date('due_date')->index();
            $t->enum('status', ['pending', 'paid', 'overdue', 'canceled'])->default('pending');
            $t->string('gateway_charge_id')->nullable();
            $t->string('payment_url')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        // Adicionar tenant_id nas tabelas existentes (isolamento)
        Schema::table('customers', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $t->index('tenant_id');
        });

        Schema::table('invoices', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $t->index('tenant_id');
        });

        Schema::table('whatsapp_templates', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $t->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', fn (Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('tenant_invoices');
        Schema::dropIfExists('tenant_billing_config');
        Schema::dropIfExists('tenant_gateways');
        Schema::dropIfExists('tenant_instances');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
