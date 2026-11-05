<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_safety_controls', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_safety_controls', 'quarantine_until')) {
                $table->timestamp('quarantine_until')->nullable()->after('paused_until');
            }
            if (! Schema::hasColumn('whatsapp_safety_controls', 'quarantine_reason')) {
                $table->string('quarantine_reason', 100)->nullable()->after('quarantine_until');
            }
            if (! Schema::hasColumn('whatsapp_safety_controls', 'last_rate_limit_at')) {
                $table->timestamp('last_rate_limit_at')->nullable()->after('quarantine_reason');
            }
            if (! Schema::hasColumn('whatsapp_safety_controls', 'last_forbidden_at')) {
                $table->timestamp('last_forbidden_at')->nullable()->after('last_rate_limit_at');
            }
            if (! Schema::hasColumn('whatsapp_safety_controls', 'last_unauthorized_at')) {
                $table->timestamp('last_unauthorized_at')->nullable()->after('last_forbidden_at');
            }
            if (! Schema::hasColumn('whatsapp_safety_controls', 'risk_override')) {
                $table->unsignedSmallInteger('risk_override')->nullable()->after('meta');
            }
            if (! Schema::hasColumn('whatsapp_safety_controls', 'delay_override')) {
                $table->unsignedSmallInteger('delay_override')->nullable()->after('risk_override');
            }
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'media_type')) {
                $table->string('media_type', 30)->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('whatsapp_messages', 'normalized_message_hash')) {
                $table->string('normalized_message_hash', 64)->nullable()->after('message_hash')->index();
            }
            if (! Schema::hasColumn('whatsapp_messages', 'template_hash')) {
                $table->string('template_hash', 64)->nullable()->after('normalized_message_hash')->index();
            }
            if (! Schema::hasColumn('whatsapp_messages', 'campaign_hash')) {
                $table->string('campaign_hash', 64)->nullable()->after('template_hash')->index();
            }
            if (! Schema::hasColumn('whatsapp_messages', 'similarity_hash')) {
                $table->string('similarity_hash', 64)->nullable()->after('campaign_hash')->index();
            }
        });
    }
};
