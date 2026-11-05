<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_interactions')) {
            Schema::create('chatbot_interactions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $t->string('from_number', 30)->index();
                $t->text('message');
                $t->string('intent', 40)->index();
                $t->string('response_preview', 255)->nullable();
                $t->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_interactions');
    }
};
