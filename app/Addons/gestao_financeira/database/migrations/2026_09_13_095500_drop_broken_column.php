<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpar o que ficou quebrado da primeira tentativa
        Schema::dropIfExists('addon_gestao_financeira_open_finance_connections');
        
        if (Schema::hasColumn('addon_gestao_financeira_transactions', 'provider_transaction_id')) {
            Schema::table('addon_gestao_financeira_transactions', function (Blueprint $table) {
                // Remove the column so the main migration can recreate it cleanly with the index
                $table->dropColumn('provider_transaction_id');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
