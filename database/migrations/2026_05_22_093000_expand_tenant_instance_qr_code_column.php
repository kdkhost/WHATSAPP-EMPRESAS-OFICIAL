<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_instances') || ! Schema::hasColumn('tenant_instances', 'evolution_qr_code')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tenant_instances MODIFY evolution_qr_code LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_instances') || ! Schema::hasColumn('tenant_instances', 'evolution_qr_code')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tenant_instances MODIFY evolution_qr_code VARCHAR(255) NULL');
        }
    }
};
