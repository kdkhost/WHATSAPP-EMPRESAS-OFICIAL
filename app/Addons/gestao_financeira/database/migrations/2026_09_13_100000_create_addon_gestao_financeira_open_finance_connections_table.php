<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_gestao_financeira_open_finance_connections', function (Blueprint $table) {
            $table->id();
            
            // tenant_id from tenants
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'ofc_tenant_fk')->references('id')->on('tenants')->onDelete('cascade');
            
            // account_id from addon_gestao_financeira_accounts
            $table->unsignedBigInteger('account_id')->nullable();
            $table->foreign('account_id', 'ofc_account_fk')->references('id')->on('addon_gestao_financeira_accounts')->onDelete('cascade');

            $table->string('provider')->default('pluggy');
            $table->string('item_id')->unique()->comment('ID do item de conexao no Pluggy');
            
            // Info do banco
            $table->string('connector_id')->nullable();
            $table->string('connector_name')->nullable();
            $table->string('connector_image_url')->nullable();
            
            // Status e Erros
            $table->string('status')->default('UPDATED')->comment('UPDATED, UPDATING, WAITING_USER_INPUT, LOGIN_ERROR, OUTDATED');
            $table->text('error_message')->nullable();
            
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
        
        // Adicionar provider_transaction_id na tabela de transactions para evitar duplicidade
        Schema::table('addon_gestao_financeira_transactions', function (Blueprint $table) {
            $table->string('provider_transaction_id')->nullable()->unique('agf_trans_provider_id_unique')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('addon_gestao_financeira_transactions', function (Blueprint $table) {
            $table->dropColumn('provider_transaction_id');
        });
        
        Schema::dropIfExists('addon_gestao_financeira_open_finance_connections');
    }
};
