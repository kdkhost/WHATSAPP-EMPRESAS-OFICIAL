<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Criada antes das billing_dispatches (FK)
        Schema::create('whatsapp_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('category', 30)->default('cobranca'); // cobranca, lembrete, atraso, agradecimento, custom
            $t->string('schedule_offset', 30)->nullable(); // -3d, -1d, 0d, +1d...
            $t->longText('content_html'); // Summernote (HTML)
            $t->longText('content_text')->nullable(); // texto puro p/ WhatsApp
            $t->json('variables')->nullable(); // variaveis suportadas
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
