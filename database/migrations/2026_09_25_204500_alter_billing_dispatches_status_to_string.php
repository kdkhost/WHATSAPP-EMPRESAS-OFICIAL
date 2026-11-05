<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_dispatches')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE billing_dispatches MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'scheduled'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_dispatches')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE billing_dispatches MODIFY COLUMN status ENUM('scheduled', 'sending', 'sent', 'failed', 'canceled') NOT NULL DEFAULT 'scheduled'");
            }
        }
    }
};
