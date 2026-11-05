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
        if (Schema::hasTable('addon_gestao_financeira_accounts')) {
            return;
        }

        Schema::create('addon_gestao_financeira_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('banco'); // banco, digital, dinheiro, cartao, outro
            $table->decimal('initial_balance', 15, 2)->default(0.00);
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_gestao_financeira_accounts');
    }
};
