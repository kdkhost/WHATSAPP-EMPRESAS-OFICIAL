<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo Contratos - envio de contrato para assinatura digital,
 * com retorno automatico via email/WhatsApp escolhido pelo cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Templates de contrato (por tenant)
        if (! Schema::hasTable('contract_templates')) {
            Schema::create('contract_templates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $t->string('name', 120);
                $t->string('slug', 120);
                $t->longText('content_html');                // Summernote, com variaveis
                $t->json('variables')->nullable();
                $t->boolean('require_witness')->default(false);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        // Contratos enviados
        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $t->foreignId('template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
                $t->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $t->string('number', 30)->unique();
                $t->string('title');
                $t->longText('content_html');                // HTML final renderizado com variaveis substituidas
                $t->string('access_token', 64)->unique();    // token publico para acessar /contract/{token}
                $t->enum('status', ['draft', 'sent', 'viewed', 'signed', 'rejected', 'expired'])
                    ->default('draft')->index();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('viewed_at')->nullable();
                $t->timestamp('signed_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->string('signer_name')->nullable();
                $t->string('signer_email')->nullable();
                $t->string('signer_document', 20)->nullable();
                $t->string('signer_ip', 45)->nullable();
                $t->text('signer_user_agent')->nullable();
                $t->longText('signature_image')->nullable();  // base64 do canvas
                $t->string('signed_pdf_path')->nullable();    // caminho do PDF assinado
                $t->enum('return_channel', ['email', 'whatsapp', 'both'])->nullable(); // canal escolhido na assinatura
                $t->timestamps();

                $t->index(['tenant_id', 'status']);
            });
        }

        // Auditoria de eventos do contrato
        if (! Schema::hasTable('contract_events')) {
            Schema::create('contract_events', function (Blueprint $t) {
                $t->id();
                $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $t->string('event', 40);                     // sent, viewed, signed, rejected, copy_sent
                $t->string('ip', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_events');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
    }
};
