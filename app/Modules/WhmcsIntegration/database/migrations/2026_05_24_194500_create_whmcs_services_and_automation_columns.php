<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whmcs_connections')) {
            Schema::table('whmcs_connections', function (Blueprint $table) {
                if (! Schema::hasColumn('whmcs_connections', 'sync_services')) {
                    $table->boolean('sync_services')->default(true)->after('sync_invoices');
                }
                if (! Schema::hasColumn('whmcs_connections', 'auto_suspend_overdue_services')) {
                    $table->boolean('auto_suspend_overdue_services')->default(false)->after('auto_send_invoices');
                }
                if (! Schema::hasColumn('whmcs_connections', 'auto_unsuspend_paid_services')) {
                    $table->boolean('auto_unsuspend_paid_services')->default(true)->after('auto_suspend_overdue_services');
                }
                if (! Schema::hasColumn('whmcs_connections', 'suspend_after_days')) {
                    $table->unsignedSmallInteger('suspend_after_days')->default(0)->after('auto_unsuspend_paid_services');
                }
                if (! Schema::hasColumn('whmcs_connections', 'sync_service_statuses')) {
                    $table->json('sync_service_statuses')->nullable()->after('sync_statuses');
                }
            });

            DB::table('whmcs_connections')
                ->whereNull('sync_service_statuses')
                ->update(['sync_service_statuses' => json_encode(['Active', 'Suspended', 'Pending'])]);
        }

        if (! Schema::hasTable('whmcs_services')) {
            Schema::create('whmcs_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('connection_id')->constrained('whmcs_connections')->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('whmcs_client_id', 60)->index();
                $table->string('whmcs_service_id', 60);
                $table->string('product_id', 60)->nullable();
                $table->string('product_name')->nullable();
                $table->string('group_name')->nullable();
                $table->string('domain')->nullable();
                $table->string('username')->nullable();
                $table->string('dedicated_ip', 80)->nullable();
                $table->string('server_id', 60)->nullable();
                $table->string('status', 40)->index();
                $table->string('billing_cycle', 80)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->date('registration_date')->nullable();
                $table->date('next_due_date')->nullable()->index();
                $table->date('next_invoice_date')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('reactivated_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();

                $table->unique(['connection_id', 'whmcs_service_id'], 'whmcs_services_connection_service_unique');
                $table->index(['tenant_id', 'status']);
                $table->index(['customer_id', 'status']);
                $table->index(['next_due_date', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whmcs_services');

        if (Schema::hasTable('whmcs_connections')) {
            Schema::table('whmcs_connections', function (Blueprint $table) {
                foreach ([
                    'sync_service_statuses',
                    'suspend_after_days',
                    'auto_unsuspend_paid_services',
                    'auto_suspend_overdue_services',
                    'sync_services',
                ] as $column) {
                    if (Schema::hasColumn('whmcs_connections', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
