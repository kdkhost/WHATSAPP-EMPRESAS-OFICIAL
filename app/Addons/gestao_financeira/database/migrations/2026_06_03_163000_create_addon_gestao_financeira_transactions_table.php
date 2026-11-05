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
        if (Schema::hasTable('addon_gestao_financeira_transactions')) {
            return;
        }

        Schema::create('addon_gestao_financeira_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // receita, despesa
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('due_date'); // Data de vencimento
            $table->date('payment_date')->nullable(); // Data em que o pagamento de fato ocorreu
            $table->string('status')->default('pendente'); // pago, pendente, atrasado, cancelado
            
            // Relacionamentos
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable(); // Comprovante / PDF / etc.
            $table->timestamps();

            // Chaves estrangeiras opcionais baseadas nos relacionamentos
            $table->foreign('account_id')
                ->references('id')
                ->on('addon_gestao_financeira_accounts')
                ->onDelete('set null');

            $table->foreign('category_id')
                ->references('id')
                ->on('addon_gestao_financeira_categories')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_gestao_financeira_transactions');
    }
};
