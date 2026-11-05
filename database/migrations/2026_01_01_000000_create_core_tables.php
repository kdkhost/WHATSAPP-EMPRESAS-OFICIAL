<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sessions (driver=database)
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        // Cache
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });

        // Filas (driver=database)
        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });

        // Usuarios (publico)
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('document', 20)->nullable()->index();
            $t->string('phone', 20)->nullable();
            $t->string('password');
            $t->string('cep', 9)->nullable();
            $t->string('logradouro')->nullable();
            $t->string('numero', 20)->nullable();
            $t->string('complemento')->nullable();
            $t->string('bairro')->nullable();
            $t->string('cidade')->nullable();
            $t->string('uf', 2)->nullable();
            $t->boolean('lgpd_accepted')->default(false);
            $t->timestamp('lgpd_accepted_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // Admins (guard=admin) - separado do publico
        Schema::create('admin_users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->enum('role', ['superadmin', 'admin', 'operator'])->default('operator');
            $t->boolean('active')->default(true);
            $t->boolean('theme_dark')->default(false);
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // Notificacoes do sininho do navbar (admin)
        Schema::create('admin_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $t->string('group', 60)->index(); // ex.: billing, whatsapp, system
            $t->string('type', 60);            // ex.: invoice_paid, send_failed
            $t->string('title');
            $t->text('message')->nullable();
            $t->string('url')->nullable();
            $t->string('icon', 60)->nullable();
            $t->string('color', 30)->default('primary');
            $t->timestamp('read_at')->nullable()->index();
            $t->timestamps();
        });

        // Cron jobs - historico
        Schema::create('cron_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('description')->nullable();
            $t->unsignedInteger('interval_min')->default(5);
            $t->boolean('enabled')->default(true);
            $t->timestamp('last_run_at')->nullable();
            $t->string('last_status', 20)->nullable();
            $t->text('last_error')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestamps();
        });

        // Uploads (controle global pelo admin)
        Schema::create('uploads', function (Blueprint $t) {
            $t->id();
            $t->morphs('owner');
            $t->string('disk')->default('public');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->json('meta')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
        Schema::dropIfExists('cron_jobs');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('users');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
    }
};
