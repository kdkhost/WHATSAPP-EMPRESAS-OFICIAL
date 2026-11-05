<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobranca por email - paralela e independente da cobranca via WhatsApp.
 *
 * O cliente (tenant) configura seu proprio servidor SMTP e cria templates de email.
 * As cobrancas seguem o mesmo cron de schedule_offset (-3d, -1d, 0d, +1d, etc).
 * Cliente pode usar email + WhatsApp juntos, intercalados, ou apenas um.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Configuracao SMTP por tenant
        if (! Schema::hasTable('tenant_email_configs')) {
            Schema::create('tenant_email_configs', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
                $t->boolean('enabled')->default(false);
                $t->enum('driver', ['smtp', 'sendgrid', 'mailgun', 'ses'])->default('smtp');
                $t->string('host')->nullable();
                $t->unsignedInteger('port')->nullable();
                $t->enum('encryption', ['tls', 'ssl', 'none'])->default('tls');
                $t->string('username')->nullable();
                $t->text('password')->nullable();           // encriptado
                $t->string('from_email');
                $t->string('from_name', 120);
                $t->string('reply_to')->nullable();
                $t->string('api_key')->nullable();           // encriptado (sendgrid/mailgun/ses)
                $t->json('settings')->nullable();            // configs extras
                $t->timestamp('last_test_at')->nullable();
                $t->string('last_test_status', 20)->nullable();
                $t->text('last_test_error')->nullable();
                $t->timestamps();
            });
        }

        // Templates de email do tenant (similar ao whatsapp_templates)
        if (! Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $t->string('name', 120);
                $t->string('slug', 120);
                $t->string('category', 30)->default('cobranca');
                $t->string('schedule_offset', 30)->nullable();
                $t->string('subject');                       // assunto do email
                $t->longText('body_html');                   // Summernote
                $t->longText('body_text')->nullable();       // texto puro
                $t->json('variables')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->unique(['tenant_id', 'slug']);
                $t->index(['tenant_id', 'schedule_offset', 'active']);
            });
        }

        // Logs de envio
        if (! Schema::hasTable('email_messages')) {
            Schema::create('email_messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $t->foreignId('dispatch_id')->nullable()->constrained('billing_dispatches')->nullOnDelete();
                $t->string('to', 255)->index();
                $t->string('subject');
                $t->longText('body_html');
                $t->enum('status', ['queued', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'failed'])
                    ->default('queued')->index();
                $t->string('message_id', 255)->nullable();
                $t->text('error')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('opened_at')->nullable();
                $t->json('headers')->nullable();
                $t->timestamps();
            });
        }

        // Adiciona suporte a multi-canal em billing_dispatches
        if (Schema::hasTable('billing_dispatches')) {
            Schema::table('billing_dispatches', function (Blueprint $t) {
                if (! Schema::hasColumn('billing_dispatches', 'channel')) {
                    $t->enum('channel', ['whatsapp', 'email', 'both'])->default('whatsapp')->after('template_id')->index();
                }

                if (! Schema::hasColumn('billing_dispatches', 'email_template_id')) {
                    $t->foreignId('email_template_id')->nullable()->after('template_id')->constrained('email_templates')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('billing_dispatches', function (Blueprint $t) {
            $t->dropConstrainedForeignId('email_template_id');
            $t->dropColumn('channel');
        });
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('tenant_email_configs');
    }
};
