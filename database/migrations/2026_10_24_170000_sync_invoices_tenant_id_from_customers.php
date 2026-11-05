<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasTable('customers') && Schema::hasColumn('invoices', 'tenant_id') && Schema::hasColumn('customers', 'tenant_id')) {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                DB::statement("
                    UPDATE invoices
                    SET tenant_id = (SELECT customers.tenant_id FROM customers WHERE customers.id = invoices.customer_id)
                    WHERE invoices.tenant_id IS NULL AND invoices.customer_id IS NOT NULL AND EXISTS (
                        SELECT 1 FROM customers WHERE customers.id = invoices.customer_id AND customers.tenant_id IS NOT NULL
                    )
                ");
            } else {
                DB::statement("
                    UPDATE invoices i
                    INNER JOIN customers c ON c.id = i.customer_id
                    SET i.tenant_id = c.tenant_id
                    WHERE i.tenant_id IS NULL AND c.tenant_id IS NOT NULL
                ");
            }
        }
    }

    public function down(): void
    {
    }
};
