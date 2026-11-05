<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_recipient_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'last_interaction_at')) {
                $table->timestamp('last_interaction_at')->nullable()->after('meta');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'last_reply_at')) {
                $table->timestamp('last_reply_at')->nullable()->after('last_interaction_at');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'total_sent')) {
                $table->unsignedInteger('total_sent')->default(0)->after('last_reply_at');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'total_failed')) {
                $table->unsignedInteger('total_failed')->default(0)->after('total_sent');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'total_replied')) {
                $table->unsignedInteger('total_replied')->default(0)->after('total_failed');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'quality_score')) {
                $table->unsignedSmallInteger('quality_score')->default(50)->after('total_replied');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'block_reason')) {
                $table->string('block_reason', 100)->nullable()->after('quality_score');
            }
            if (! Schema::hasColumn('whatsapp_recipient_preferences', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('block_reason');
            }
        });
    }
};
