<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliates')) {
            try {
                // Modifica payment_info para LONGTEXT NULL removendo a constraint check json_valid se houver
                DB::statement("ALTER TABLE affiliates MODIFY COLUMN payment_info LONGTEXT NULL");
            } catch (\Throwable) {
                // Silencioso se o banco for sqlite em testes
            }
        }

        if (Schema::hasTable('affiliate_withdrawals')) {
            try {
                DB::statement("ALTER TABLE affiliate_withdrawals MODIFY COLUMN payment_info LONGTEXT NULL");
            } catch (\Throwable) {
                // Silencioso se o banco for sqlite em testes
            }
        }
    }

    public function down(): void
    {
        // Reversão não necessária
    }
};
