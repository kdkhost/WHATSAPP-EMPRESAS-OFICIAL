<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans') && ! Schema::hasColumn('plans', 'extra_instance_price')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->decimal('extra_instance_price', 10, 2)->default(0)->after('price');
            });
        }

        if (Schema::hasTable('tenant_instances')) {
            Schema::table('tenant_instances', function (Blueprint $table) {
                if (! Schema::hasColumn('tenant_instances', 'is_extra_billable')) {
                    $table->boolean('is_extra_billable')->default(false)->after('is_default');
                }

                if (! Schema::hasColumn('tenant_instances', 'recurring_amount')) {
                    $table->decimal('recurring_amount', 10, 2)->default(0)->after('is_extra_billable');
                }

                if (! Schema::hasColumn('tenant_instances', 'billing_started_at')) {
                    $table->date('billing_started_at')->nullable()->after('recurring_amount');
                }

                if (! Schema::hasColumn('tenant_instances', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('billing_started_at');
                }
            });
        }

        if (Schema::hasTable('tenant_invoices')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('tenant_invoices', 'description')) {
                    $table->text('description')->nullable()->after('amount');
                }

                if (! Schema::hasColumn('tenant_invoices', 'meta')) {
                    $table->json('meta')->nullable()->after('payment_url');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_invoices')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_invoices', 'meta')) {
                    $table->dropColumn('meta');
                }

                if (Schema::hasColumn('tenant_invoices', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }

        if (Schema::hasTable('tenant_instances')) {
            Schema::table('tenant_instances', function (Blueprint $table) {
                foreach (['cancelled_at', 'billing_started_at', 'recurring_amount', 'is_extra_billable'] as $column) {
                    if (Schema::hasColumn('tenant_instances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'extra_instance_price')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('extra_instance_price');
            });
        }
    }
};
