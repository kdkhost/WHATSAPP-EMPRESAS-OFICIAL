<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_instance_reputations')) {
            Schema::create('whatsapp_instance_reputations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('tenant_instance_id')->nullable();
                $table->unsignedSmallInteger('trust_score')->default(100);
                $table->unsignedSmallInteger('risk_score')->default(0);
                $table->unsignedSmallInteger('quality_score')->default(100);
                $table->unsignedInteger('daily_sent')->default(0);
                $table->unsignedInteger('daily_failed')->default(0);
                $table->unsignedInteger('daily_delivered')->default(0);
                $table->unsignedInteger('daily_replied')->default(0);
                $table->timestamp('last_rate_limit_at')->nullable();
                $table->timestamp('last_forbidden_at')->nullable();
                $table->timestamp('last_unauthorized_at')->nullable();
                $table->timestamp('quarantine_until')->nullable();
                $table->string('quarantine_reason', 100)->nullable();
                $table->boolean('sending_paused')->default(false);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('tenant_instance_id');
                $table->index('trust_score');
                $table->index('risk_score');
            });
        }

        if (! Schema::hasTable('whatsapp_instance_quarantines')) {
            Schema::create('whatsapp_instance_quarantines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('tenant_instance_id')->nullable();
                $table->string('mode', 50); // soft_pause, rate_limit_pause, forbidden_lock, token_lock, quality_lock, manual_lock
                $table->string('reason', 200);
                $table->timestamp('quarantine_until')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('released_by')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->string('release_reason', 200)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('tenant_instance_id');
                $table->index('active');
                $table->index('mode');
            });
        }

        if (! Schema::hasTable('whatsapp_delivery_metrics')) {
            Schema::create('whatsapp_delivery_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_instance_id')->nullable();
                $table->date('date');
                $table->unsignedInteger('sent')->default(0);
                $table->unsignedInteger('delivered')->default(0);
                $table->unsignedInteger('read')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->unsignedInteger('replied')->default(0);
                $table->unsignedInteger('text_count')->default(0);
                $table->unsignedInteger('image_count')->default(0);
                $table->unsignedInteger('audio_count')->default(0);
                $table->unsignedInteger('video_count')->default(0);
                $table->unsignedInteger('document_count')->default(0);
                $table->unsignedInteger('buttons_count')->default(0);
                $table->unsignedInteger('list_count')->default(0);
                $table->unsignedInteger('poll_count')->default(0);
                $table->decimal('delivery_rate', 5, 2)->default(0);
                $table->decimal('reply_rate', 5, 2)->default(0);
                $table->decimal('failure_rate', 5, 2)->default(0);
                $table->timestamps();

                $table->unique(['tenant_instance_id', 'date']);
                $table->index('date');
            });
        }

        if (! Schema::hasTable('whatsapp_recipient_scores')) {
            Schema::create('whatsapp_recipient_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('phone_hash', 64);
                $table->string('phone', 20)->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->boolean('opt_in')->default(false);
                $table->boolean('opt_out')->default(false);
                $table->boolean('blacklisted')->default(false);
                $table->timestamp('last_interaction_at')->nullable();
                $table->timestamp('last_reply_at')->nullable();
                $table->unsignedInteger('total_sent')->default(0);
                $table->unsignedInteger('total_failed')->default(0);
                $table->unsignedInteger('total_replied')->default(0);
                $table->unsignedSmallInteger('quality_score')->default(50);
                $table->string('block_reason', 100)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'phone_hash']);
                $table->index('quality_score');
                $table->index('opt_out');
                $table->index('blacklisted');
            });
        }
    }
};
