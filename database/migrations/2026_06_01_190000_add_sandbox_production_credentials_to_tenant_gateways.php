<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_gateways', function (Blueprint $table) {
            // Adicionar colunas separadas para credenciais sandbox e produção
            $table->longText('sandbox_credentials')->nullable()->after('credentials');
            $table->longText('production_credentials')->nullable()->after('sandbox_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_gateways', function (Blueprint $table) {
            $table->dropColumn(['sandbox_credentials', 'production_credentials']);
        });
    }
};
