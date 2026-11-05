<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Subscriptions WebPush (PWA)
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('endpoint', 500)->unique();
            $t->string('public_key');
            $t->string('auth_token');
            $t->string('content_encoding', 20)->default('aesgcm');
            $t->string('user_agent')->nullable();
            $t->timestamps();
        });

        // Devices reconhecidos (libera manutencao)
        Schema::create('trusted_devices', function (Blueprint $t) {
            $t->id();
            $t->string('label')->nullable();
            $t->string('ip', 45)->nullable()->index();
            $t->string('mac', 30)->nullable()->index();
            $t->string('fingerprint', 80)->nullable()->index();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Promocoes / banners do PWA
        Schema::create('pwa_promotions', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('icon')->nullable();
            $t->string('image')->nullable();
            $t->string('url')->nullable();
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Instalacoes do PWA - estatistica
        Schema::create('pwa_installs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('platform', 30)->nullable(); // ios, android, desktop
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_installs');
        Schema::dropIfExists('pwa_promotions');
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('push_subscriptions');
    }
};
