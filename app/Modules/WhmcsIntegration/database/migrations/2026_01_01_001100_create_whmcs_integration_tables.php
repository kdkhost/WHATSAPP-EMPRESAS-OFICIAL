<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integracao bidirecional com WHMCS:
 *  - Sincroniza clientes (Customers <-> WHMCS Clients)
 *  - Sincroniza faturas (WHMCS Invoices -> sistema -> WhatsApp)
 *  - Recebe webhook de pagamento -> dispara cobranca/agradecimento
 *  - Permite acionar comandos WHMCS (suspender, ativar, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whmcs_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('api_url');                      // https://meusite.com/whmcs/includes/api.php
            $t->string('identifier');                   // API identifier
            $t->text('secret');                         // API secret (encriptado)
            $t->string('webhook_token', 64)->unique();
            $t->boolean('sync_customers')->default(true);
            $t->boolean('sync_invoices')->default(true);
            $t->boolean('auto_send_invoices')->default(true);
            $t->json('settings')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamp('last_sync_at')->nullable();
            $t->string('last_sync_status', 20)->nullable();
            $t->text('last_sync_error')->nullable();
            $t->timestamps();
        });

        Schema::create('whmcs_sync_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('connection_id')->constrained('whmcs_connections')->cascadeOnDelete();
            $t->enum('direction', ['pull', 'push']);
            $t->string('entity', 30);                  // customer, invoice, payment
            $t->unsignedBigInteger('whmcs_id')->nullable();
            $t->unsignedBigInteger('local_id')->nullable();
            $t->enum('status', ['ok', 'error', 'skipped'])->default('ok');
            $t->text('message')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whmcs_sync_log');
        Schema::dropIfExists('whmcs_connections');
    }
};
