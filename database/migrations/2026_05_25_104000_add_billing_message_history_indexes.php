<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->index(['dispatch_id', 'created_at'], 'wa_msg_dispatch_created_idx');
                $table->index(['status', 'created_at'], 'wa_msg_status_created_idx');
            });
        }

        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table) {
                $table->index(['dispatch_id', 'created_at'], 'email_msg_dispatch_created_idx');
                $table->index(['status', 'created_at'], 'email_msg_status_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_messages')) {
            Schema::table('email_messages', function (Blueprint $table) {
                $table->dropIndex('email_msg_status_created_idx');
                $table->dropIndex('email_msg_dispatch_created_idx');
            });
        }

        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropIndex('wa_msg_status_created_idx');
                $table->dropIndex('wa_msg_dispatch_created_idx');
            });
        }
    }
};
