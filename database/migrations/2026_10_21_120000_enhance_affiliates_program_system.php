<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Atualizar tabela 'affiliates'
        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                if (! Schema::hasColumn('affiliates', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained('tenants')->nullOnDelete();
                }
                if (! Schema::hasColumn('affiliates', 'commission_mode')) {
                    $table->string('commission_mode', 20)->default('percentage')->after('commission_pct'); // 'percentage' ou 'fixed'
                }
                if (! Schema::hasColumn('affiliates', 'commission_fixed')) {
                    $table->decimal('commission_fixed', 12, 2)->default(0)->after('commission_mode');
                }
                if (! Schema::hasColumn('affiliates', 'discount_pct')) {
                    $table->decimal('discount_pct', 5, 2)->default(0)->after('commission_type');
                }
                if (! Schema::hasColumn('affiliates', 'discount_fixed')) {
                    $table->decimal('discount_fixed', 12, 2)->default(0)->after('discount_pct');
                }
                if (! Schema::hasColumn('affiliates', 'discount_mode')) {
                    $table->string('discount_mode', 20)->default('percentage')->after('discount_fixed'); // 'percentage' ou 'fixed'
                }
                if (! Schema::hasColumn('affiliates', 'discount_type')) {
                    $table->string('discount_type', 20)->default('first_payment')->after('discount_mode'); // 'first_payment' ou 'recurring'
                }
                if (! Schema::hasColumn('affiliates', 'custom_rules')) {
                    $table->json('custom_rules')->nullable()->after('payment_info');
                }
            });
        }

        // 2. Atualizar tabela 'affiliate_clicks' para associar tenant_id se disponível
        if (Schema::hasTable('affiliate_clicks')) {
            Schema::table('affiliate_clicks', function (Blueprint $table) {
                if (! Schema::hasColumn('affiliate_clicks', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('affiliate_id')->constrained('tenants')->nullOnDelete();
                }
            });
        }

        // 3. Atualizar tabela 'affiliate_commissions' para suportar tipo de comissão e notas
        if (Schema::hasTable('affiliate_commissions')) {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                if (! Schema::hasColumn('affiliate_commissions', 'commission_type')) {
                    $table->string('commission_type', 30)->default('recurring')->after('commission_amount');
                }
                if (! Schema::hasColumn('affiliate_commissions', 'notes')) {
                    $table->string('notes', 255)->nullable()->after('commission_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $columns = ['tenant_id', 'commission_mode', 'commission_fixed', 'discount_pct', 'discount_fixed', 'discount_mode', 'discount_type', 'custom_rules'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('affiliates', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('affiliate_clicks')) {
            Schema::table('affiliate_clicks', function (Blueprint $table) {
                if (Schema::hasColumn('affiliate_clicks', 'tenant_id')) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                }
            });
        }

        if (Schema::hasTable('affiliate_commissions')) {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                if (Schema::hasColumn('affiliate_commissions', 'commission_type')) {
                    $table->dropColumn('commission_type');
                }
                if (Schema::hasColumn('affiliate_commissions', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
