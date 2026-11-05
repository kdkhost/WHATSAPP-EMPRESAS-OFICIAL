<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeTemplateTable('whatsapp_templates');
        $this->upgradeTemplateTable('email_templates');

        if (Schema::hasTable('whatsapp_templates')) {
            DB::table('whatsapp_templates')
                ->whereNull('tenant_id')
                ->update([
                    'scope' => 'system',
                    'is_system' => true,
                    'locked' => true,
                    'is_default' => true,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')
                ->whereIn('slug', $this->defaultEmailSlugs())
                ->update([
                    'scope' => 'system',
                    'is_system' => true,
                    'locked' => true,
                    'is_default' => true,
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasTable('tenant_template_preferences')) {
            Schema::create('tenant_template_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('channel', 20);
                $table->string('preference_key', 120);
                $table->unsignedBigInteger('template_id');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'channel', 'preference_key'], 'tenant_template_pref_unique');
                $table->index(['channel', 'template_id']);
            });
        }

        if (! Schema::hasTable('extra_instance_prices')) {
            Schema::create('extra_instance_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->string('name', 120);
                $table->unsignedInteger('min_quantity')->default(1);
                $table->unsignedInteger('max_quantity')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('billing_cycle', 30)->default('monthly');
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['plan_id', 'active']);
            });
        }

        if (! Schema::hasTable('tenant_packages')) {
            Schema::create('tenant_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 160);
                $table->longText('description')->nullable();
                $table->decimal('total_amount', 12, 2);
                $table->boolean('allow_cash')->default(true);
                $table->boolean('allow_installments')->default(false);
                $table->unsignedTinyInteger('max_installments')->default(1);
                $table->string('gateway_fee_mode', 20)->default('tenant');
                $table->decimal('gateway_fee_percent', 6, 3)->default(0);
                $table->decimal('gateway_fixed_fee', 10, 2)->default(0);
                $table->decimal('installment_interest_percent', 6, 3)->default(0);
                $table->boolean('active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'active']);
            });
        }

        if (! Schema::hasTable('customer_package_subscriptions')) {
            Schema::create('customer_package_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('tenant_package_id')->constrained('tenant_packages')->restrictOnDelete();
                $table->string('status', 30)->default('active');
                $table->string('payment_mode', 20)->default('cash');
                $table->unsignedTinyInteger('installments_count')->default(1);
                $table->decimal('total_amount', 12, 2);
                $table->decimal('fee_amount', 12, 2)->default(0);
                $table->decimal('interest_amount', 12, 2)->default(0);
                $table->decimal('final_amount', 12, 2);
                $table->date('start_date');
                $table->date('next_due_date')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id', 'status'], 'customer_pkg_sub_status_idx');
            });
        }

        if (! Schema::hasTable('customer_package_installments')) {
            Schema::create('customer_package_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('customer_package_subscriptions')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->unsignedTinyInteger('installment_number');
                $table->decimal('amount', 12, 2);
                $table->date('due_date')->index();
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('paid_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['subscription_id', 'installment_number'], 'customer_pkg_installment_unique');
                $table->index(['tenant_id', 'customer_id', 'status'], 'customer_pkg_inst_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_package_installments');
        Schema::dropIfExists('customer_package_subscriptions');
        Schema::dropIfExists('tenant_packages');
        Schema::dropIfExists('extra_instance_prices');
        Schema::dropIfExists('tenant_template_preferences');

        $this->dropTemplateColumns('email_templates');
        $this->dropTemplateColumns('whatsapp_templates');
    }

    private function upgradeTemplateTable(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'scope')) {
                $table->string('scope', 20)->default('tenant');
            }
            if (! Schema::hasColumn($tableName, 'is_system')) {
                $table->boolean('is_system')->default(false);
            }
            if (! Schema::hasColumn($tableName, 'is_default')) {
                $table->boolean('is_default')->default(false);
            }
            if (! Schema::hasColumn($tableName, 'locked')) {
                $table->boolean('locked')->default(false);
            }
            if (! Schema::hasColumn($tableName, 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    private function dropTemplateColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            foreach (['meta', 'locked', 'is_default', 'is_system', 'scope'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function defaultEmailSlugs(): array
    {
        return [
            'cobranca-3-dias-antes',
            'cobranca-1-dia-antes',
            'cobranca-no-vencimento',
            'cobranca-1-dia-atraso',
            'cobranca-3-dias-atraso',
            'cobranca-7-dias-atraso',
            'cobranca-15-dias-atraso',
            'aniversario-cliente',
            'boas-vindas',
            'cadastro-realizado-sucesso',
            'email-marketing-reativacao-cliente',
            'contrato-assinado-sucesso',
            'contrato-assinatura-pendente',
            'contrato-nao-assinado',
            'servico-suspenso',
            'servico-cancelado',
            'pagamento-confirmado',
            'pagamento-pendente',
            'pagamento-estornado',
        ];
    }
};
