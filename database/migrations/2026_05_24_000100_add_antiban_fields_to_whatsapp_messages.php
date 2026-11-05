<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'tenant_instance_id')) {
                $table->foreignId('tenant_instance_id')->nullable()->after('dispatch_id')
                    ->constrained('tenant_instances')->nullOnDelete();
            }

            if (! Schema::hasColumn('whatsapp_messages', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('status');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('scheduled_for');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'blocked_reason')) {
                $table->string('blocked_reason', 120)->nullable()->after('blocked_at');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'risk_score')) {
                $table->unsignedTinyInteger('risk_score')->default(0)->after('blocked_reason');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'message_hash')) {
                $table->string('message_hash', 64)->nullable()->after('risk_score');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'recipient_hash')) {
                $table->string('recipient_hash', 64)->nullable()->after('message_hash');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'meta')) {
                $table->json('meta')->nullable()->after('response');
            }
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index('tenant_instance_id', 'wam_tenant_instance_idx');
            $table->index('status', 'wam_status_idx');
            $table->index('created_at', 'wam_created_at_idx');
            $table->index('sent_at', 'wam_sent_at_idx');
            $table->index('message_hash', 'wam_message_hash_idx');
            $table->index('recipient_hash', 'wam_recipient_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            foreach ([
                'wam_tenant_instance_idx',
                'wam_status_idx',
                'wam_created_at_idx',
                'wam_sent_at_idx',
                'wam_message_hash_idx',
                'wam_recipient_hash_idx',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                }
            }

            if (Schema::hasColumn('whatsapp_messages', 'tenant_instance_id')) {
                try {
                    $table->dropConstrainedForeignId('tenant_instance_id');
                } catch (\Throwable) {
                    $table->dropColumn('tenant_instance_id');
                }
            }

            foreach ([
                'scheduled_for',
                'sent_at',
                'blocked_at',
                'blocked_reason',
                'risk_score',
                'message_hash',
                'recipient_hash',
                'meta',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
