<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converte tenant_instances para o modelo da WhatsApp Cloud API Oficial da Meta:
 * cada instancia passa a representar um numero da Meta (Phone Number ID, WABA ID e token proprios)
 * e as colunas herdadas da Evolution API (QR Code / evolution_id) sao removidas.
 *
 * Idempotente e compativel com SQLite (local) e MySQL/MariaDB (producao).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_instances')) {
            Schema::table('tenant_instances', function (Blueprint $t) {
                if (! Schema::hasColumn('tenant_instances', 'meta_phone_number_id')) {
                    $t->string('meta_phone_number_id', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('tenant_instances', 'meta_waba_id')) {
                    $t->string('meta_waba_id', 64)->nullable();
                }
                if (! Schema::hasColumn('tenant_instances', 'meta_access_token')) {
                    $t->text('meta_access_token')->nullable();
                }
            });

            if (Schema::hasColumn('tenant_instances', 'evolution_qr_code')) {
                Schema::table('tenant_instances', function (Blueprint $t) {
                    $t->dropColumn('evolution_qr_code');
                });
            }
        }

        if (Schema::hasTable('whatsapp_messages') && Schema::hasColumn('whatsapp_messages', 'evolution_id')) {
            if (Schema::hasIndex('whatsapp_messages', ['evolution_id'])) {
                Schema::table('whatsapp_messages', function (Blueprint $t) {
                    $t->dropIndex(['evolution_id']);
                });
            }

            Schema::table('whatsapp_messages', function (Blueprint $t) {
                $t->dropColumn('evolution_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_instances')) {
            Schema::table('tenant_instances', function (Blueprint $t) {
                if (Schema::hasColumn('tenant_instances', 'meta_phone_number_id')) {
                    $t->dropIndex(['meta_phone_number_id']);
                    $t->dropColumn('meta_phone_number_id');
                }
            });

            Schema::table('tenant_instances', function (Blueprint $t) {
                foreach (['meta_waba_id', 'meta_access_token'] as $col) {
                    if (Schema::hasColumn('tenant_instances', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};

