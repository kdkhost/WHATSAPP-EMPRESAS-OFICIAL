<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_gateways', function (Blueprint $table) {
            // Aumentar o tamanho da coluna credentials para suportar dados criptografados maiores
            $table->longText('credentials')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_gateways', function (Blueprint $table) {
            // Reverter para o tamanho original (assumindo string/text)
            $table->text('credentials')->change();
        });
    }
};
