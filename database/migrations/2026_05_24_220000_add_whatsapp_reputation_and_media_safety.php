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
                if (! Schema::hasColumn('whatsapp_messages', 'media_type')) {
                    $table->string('media_type', 30)->nullable()->after('direction')->index();
                }

                if (! Schema::hasColumn('whatsapp_messages', 'trust_score')) {
                    $table->unsignedTinyInteger('trust_score')->nullable()->after('risk_score');
                }

                if (! Schema::hasColumn('whatsapp_messages', 'quality_score')) {
                    $table->unsignedTinyInteger('quality_score')->nullable()->after('trust_score');
                }
            });
        }

        if (Schema::hasTable('whatsapp_safety_events')) {
            Schema::table('whatsapp_safety_events', function (Blueprint $table) {
                if (! Schema::hasColumn('whatsapp_safety_events', 'media_type')) {
                    $table->string('media_type', 30)->nullable()->after('event_type')->index();
                }

                if (! Schema::hasColumn('whatsapp_safety_events', 'trust_score')) {
                    $table->unsignedTinyInteger('trust_score')->nullable()->after('risk_score');
                }

                if (! Schema::hasColumn('whatsapp_safety_events', 'quality_score')) {
                    $table->unsignedTinyInteger('quality_score')->nullable()->after('trust_score');
                }
            });
        }

        if (! Schema::hasTable('whatsapp_reputation_scores')) {
            Schema::create('whatsapp_reputation_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->foreignId('tenant_instance_id')->nullable()->index();
                $table->string('scope', 30)->index();
                $table->string('recipient_hash', 64)->nullable()->index();
                $table->unsignedTinyInteger('risk_score')->default(0)->index();
                $table->unsignedTinyInteger('trust_score')->default(100);
                $table->unsignedTinyInteger('quality_score')->default(100);
                $table->unsignedInteger('messages_sent')->default(0);
                $table->unsignedInteger('messages_blocked')->default(0);
                $table->unsignedInteger('messages_deferred')->default(0);
                $table->timestamp('last_event_at')->nullable()->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'tenant_instance_id', 'scope', 'recipient_hash'], 'wa_reputation_scope_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_reputation_scores');

        if (Schema::hasTable('whatsapp_safety_events')) {
            Schema::table('whatsapp_safety_events', function (Blueprint $table) {
                foreach (['media_type', 'trust_score', 'quality_score'] as $column) {
                    if (Schema::hasColumn('whatsapp_safety_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                foreach (['media_type', 'trust_score', 'quality_score'] as $column) {
                    if (Schema::hasColumn('whatsapp_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
