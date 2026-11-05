<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clientes (CRM)
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable()->index();
            $t->string('document', 20)->nullable()->index(); // CPF/CNPJ
            $t->string('phone', 20)->nullable();
            $t->string('whatsapp', 20)->nullable()->index();
            $t->string('cep', 9)->nullable();
            $t->string('logradouro')->nullable();
            $t->string('numero', 20)->nullable();
            $t->string('complemento')->nullable();
            $t->string('bairro')->nullable();
            $t->string('cidade')->nullable();
            $t->string('uf', 2)->nullable();
            $t->json('meta')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Faturas / Cobrancas
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $t->string('number', 30)->unique();
            $t->longText('description');
            $t->decimal('amount', 12, 2);
            $t->date('due_date')->index();
            $t->enum('status', ['pending', 'sent', 'viewed', 'paid', 'overdue', 'canceled', 'refunded'])
                ->default('pending')->index();
            $t->string('gateway', 30)->nullable(); // mercadopago, efipro, sumup
            $t->string('gateway_charge_id')->nullable();
            $t->string('payment_url')->nullable();
            $t->string('webhook_token', 64)->unique()->nullable(); // webhook dinamico unico
            $t->timestamp('paid_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        // Agendamento de envios de cobranca via WhatsApp
        Schema::create('billing_dispatches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $t->string('schedule_offset', 30); // -3d, -1d, 0d, +1d, +3d, +7d, +15d, manual
            $t->timestamp('scheduled_at')->index();
            $t->enum('status', ['scheduled', 'sending', 'sent', 'failed', 'canceled'])
                ->default('scheduled')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->text('last_error')->nullable();
            $t->json('payload')->nullable(); // mensagem renderizada
            $t->timestamps();
        });

        // Logs de envio Evolution
        Schema::create('whatsapp_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispatch_id')->nullable()->constrained('billing_dispatches')->nullOnDelete();
            $t->string('to', 30)->index();
            $t->longText('message');
            $t->string('evolution_id')->nullable()->index();
            $t->enum('direction', ['out', 'in'])->default('out');
            $t->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $t->json('response')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        // Webhooks (gateways) - registro completo
        Schema::create('payment_webhooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->string('gateway', 30);
            $t->string('event', 60)->nullable();
            $t->json('payload');
            $t->string('ip', 45)->nullable();
            $t->boolean('processed')->default(false);
            $t->text('error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('billing_dispatches');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customers');
    }
};
