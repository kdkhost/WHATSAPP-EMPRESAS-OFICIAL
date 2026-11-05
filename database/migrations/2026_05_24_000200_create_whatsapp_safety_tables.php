<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_safety_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_instance_id')->nullable()->constrained('tenant_instances')->nullOnDelete();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('event_type', 50);
            $table->string('reason', 120)->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level', 20)->default('baixo');
            $table->string('recipient', 30)->nullable();
            $table->string('recipient_hash', 64)->nullable();
            $table->string('message_hash', 64)->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_instance_id', 'event_type'], 'wse_instance_event_idx');
            $table->index(['recipient_hash', 'created_at'], 'wse_recipient_idx');
            $table->index(['risk_score', 'created_at'], 'wse_risk_idx');
        });

        Schema::create('whatsapp_recipient_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone', 30);
            $table->string('phone_hash', 64);
            $table->boolean('opt_in')->default(true);
            $table->timestamp('opt_in_at')->nullable();
            $table->boolean('opt_out')->default(false);
            $table->timestamp('opt_out_at')->nullable();
            $table->string('opt_out_reason', 120)->nullable();
            $table->boolean('blacklisted')->default(false);
            $table->boolean('whitelisted')->default(false);
            $table->boolean('lgpd_consented')->default(false);
            $table->timestamp('lgpd_consented_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_hash'], 'wrp_tenant_phone_unique');
            $table->index(['opt_out', 'blacklisted'], 'wrp_optout_blacklist_idx');
        });

        Schema::create('whatsapp_safety_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_instance_id')->nullable()->constrained('tenant_instances')->nullOnDelete();
            $table->boolean('sending_paused')->default(false);
            $table->timestamp('paused_until')->nullable();
            $table->boolean('sending_disabled')->default(false);
            $table->boolean('campaigns_blocked')->default(false);
            $table->boolean('warmup_enabled')->default(false);
            $table->unsignedSmallInteger('limit_per_minute')->nullable();
            $table->unsignedSmallInteger('limit_per_hour')->nullable();
            $table->unsignedSmallInteger('limit_per_day')->nullable();
            $table->unsignedSmallInteger('human_delay_min_seconds')->nullable();
            $table->unsignedSmallInteger('human_delay_max_seconds')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_instance_id'], 'wsc_instance_unique');
            $table->index(['tenant_id', 'sending_paused'], 'wsc_tenant_pause_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_safety_controls');
        Schema::dropIfExists('whatsapp_recipient_preferences');
        Schema::dropIfExists('whatsapp_safety_events');
    }
};
