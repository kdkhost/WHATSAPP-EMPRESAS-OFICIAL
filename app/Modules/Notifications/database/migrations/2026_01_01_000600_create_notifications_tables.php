<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila de notificacoes (multi-canal)
        Schema::create('notification_queue', function (Blueprint $t) {
            $t->id();
            $t->enum('channel', ['push', 'email', 'whatsapp', 'sms'])->index();
            $t->json('payload');
            $t->enum('status', ['pending', 'sending', 'sent', 'failed'])->default('pending')->index();
            $t->text('error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        // Templates de notificacao (configuravel pelo admin)
        Schema::create('notification_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('channel', ['push', 'email', 'whatsapp', 'sms']);
            $t->string('subject')->nullable(); // assunto do email
            $t->longText('body');              // conteudo (suporta variaveis)
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_queue');
    }
};
