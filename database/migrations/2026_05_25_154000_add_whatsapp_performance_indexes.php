<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->safeIndex('whatsapp_messages', function (Blueprint $table) {
            $table->index(['tenant_instance_id', 'direction', 'status', 'created_at'], 'wa_msg_lookup');
        });
        if (Schema::hasColumn('whatsapp_messages', 'message_hash')) {
            $this->safeIndex('whatsapp_messages', fn($t) => $t->index('message_hash'));
        }
        if (Schema::hasColumn('whatsapp_messages', 'recipient_hash')) {
            $this->safeIndex('whatsapp_messages', fn($t) => $t->index('recipient_hash'));
        }
        if (Schema::hasColumn('whatsapp_messages', 'media_type')) {
            $this->safeIndex('whatsapp_messages', fn($t) => $t->index('media_type'));
        }
        if (Schema::hasColumn('whatsapp_messages', 'to')) {
            $this->safeIndex('whatsapp_messages', fn($t) => $t->index('to'));
        }
        if (Schema::hasColumn('whatsapp_messages', 'scheduled_for')) {
            $this->safeIndex('whatsapp_messages', fn($t) => $t->index('scheduled_for'));
        }

        if (Schema::hasTable('whatsapp_safety_events')) {
            if (Schema::hasColumn('whatsapp_safety_events', 'tenant_instance_id')) {
                $this->safeIndex('whatsapp_safety_events', fn($t) => $t->index('tenant_instance_id'));
            }
            $this->safeIndex('whatsapp_safety_events', fn($t) => $t->index(['event_type', 'created_at'], 'wa_events_type_created'));
        }

        if (Schema::hasTable('whatsapp_recipient_preferences') && Schema::hasColumn('whatsapp_recipient_preferences', 'phone_hash')) {
            $this->safeIndex('whatsapp_recipient_preferences', fn($t) => $t->index(['tenant_id', 'phone_hash'], 'wa_recipient_prefs_tenant_phone'));
        }

        if (Schema::hasTable('whatsapp_safety_controls') && Schema::hasColumn('whatsapp_safety_controls', 'tenant_instance_id')) {
            $this->safeIndex('whatsapp_safety_controls', fn($t) => $t->index('tenant_instance_id'));
        }

        if (Schema::hasTable('gateway_activity_logs')) {
            $this->safeIndex('gateway_activity_logs', fn($t) => $t->index(['tenant_gateway_id', 'created_at'], 'gateway_logs_lookup'));
        }

        if (Schema::hasTable('jobs')) {
            $this->safeIndex('jobs', fn($t) => $t->index(['queue', 'created_at']));
        }

        if (Schema::hasTable('failed_jobs')) {
            $this->safeIndex('failed_jobs', fn($t) => $t->index(['queue', 'failed_at']));
        }
    }

    protected function safeIndex(string $table, callable $callback): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($callback) {
                $callback($table);
            });
        } catch (\Throwable $e) {
            // Index likely already exists — safe to ignore
        }
    }

    public function down(): void
    {
    }
};
