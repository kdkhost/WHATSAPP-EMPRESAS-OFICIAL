<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo de Seguranca - WAF proprio com painel grafico (Chart.js).
 *
 * Tabelas:
 *  - security_rules        regras configuraveis (SQLi, XSS, LFI, RFI, badbots, ratelimit)
 *  - security_blocklist    IPs/CIDR/UA bloqueados manualmente ou pelo WAF
 *  - security_allowlist    IPs/CIDR sempre permitidos (admin, devs)
 *  - security_events       todo bloqueio/alerta gera evento (alimenta os graficos)
 *  - security_login_attempts  tentativas de login (admin e publico)
 *  - audit_logs            auditoria de acoes administrativas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique(); // sqli, xss, lfi, rfi, badbot, ratelimit, geo
            $t->string('name');
            $t->text('description')->nullable();
            $t->enum('action', ['log', 'block', 'challenge'])->default('block');
            $t->boolean('enabled')->default(true);
            $t->json('config')->nullable(); // padroes regex, threshold, etc.
            $t->timestamps();
        });

        Schema::create('security_blocklist', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['ip', 'cidr', 'user_agent', 'country'])->default('ip');
            $t->string('value', 200)->index();
            $t->string('reason')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
            $t->unique(['type', 'value']);
        });

        Schema::create('security_allowlist', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['ip', 'cidr', 'user_agent'])->default('ip');
            $t->string('value', 200)->index();
            $t->string('label')->nullable();
            $t->timestamps();
            $t->unique(['type', 'value']);
        });

        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->string('rule_code', 40)->index();
            $t->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $t->enum('action_taken', ['logged', 'blocked', 'challenged'])->default('logged')->index();
            $t->string('ip', 45)->index();
            $t->string('country', 2)->nullable()->index();
            $t->string('method', 10)->nullable();
            $t->string('uri', 500)->nullable();
            $t->text('user_agent')->nullable();
            $t->string('referer', 500)->nullable();
            $t->json('matched')->nullable(); // payload que disparou a regra
            $t->timestamp('created_at')->useCurrent()->index();

            $t->index(['rule_code', 'created_at']);
            $t->index(['ip', 'created_at']);
        });

        Schema::create('security_login_attempts', function (Blueprint $t) {
            $t->id();
            $t->enum('guard', ['admin', 'web'])->default('web')->index();
            $t->string('email')->nullable()->index();
            $t->string('ip', 45)->index();
            $t->text('user_agent')->nullable();
            $t->boolean('success')->default(false)->index();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $t->string('action', 80);            // ex.: settings.update, invoice.delete
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();     // before/after
            $t->string('ip', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();

            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('security_login_attempts');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('security_allowlist');
        Schema::dropIfExists('security_blocklist');
        Schema::dropIfExists('security_rules');
    }
};
