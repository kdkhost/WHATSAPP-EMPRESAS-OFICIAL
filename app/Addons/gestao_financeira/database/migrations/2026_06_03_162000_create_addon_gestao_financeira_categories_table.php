<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('addon_gestao_financeira_categories')) {
            return;
        }

        Schema::create('addon_gestao_financeira_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // receita, despesa
            $table->string('color', 7)->default('#3B82F6'); // Cor hexadecimal para os gráficos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_gestao_financeira_categories');
    }
};
