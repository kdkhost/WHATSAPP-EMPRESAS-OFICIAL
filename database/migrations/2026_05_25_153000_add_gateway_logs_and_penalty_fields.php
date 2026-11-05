<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gateway_activity_logs')) {
            Schema::create('gateway_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('tenant_gateway_id')->nullable();
                $table->string('gateway', 50);
                $table->string('event_type', 60);
                $table->string('status', 30);
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('charge_id', 100)->nullable();
                $table->string('error_message', 255)->nullable();
                $table->json('request_summary')->nullable();
                $table->json('response_summary')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('tenant_gateway_id');
                $table->index('gateway');
                $table->index('event_type');
                $table->index('created_at');
            });
        }

        Schema::table('tenant_gateways', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_gateways', 'penalty')) {
                $table->json('penalty')->nullable()->after('fees');
            }
            if (! Schema::hasColumn('tenant_gateways', 'discount')) {
                $table->json('discount')->nullable()->after('penalty');
            }
            if (! Schema::hasColumn('tenant_gateways', 'webhook_fail_count')) {
                $table->unsignedSmallInteger('webhook_fail_count')->default(0)->after('last_error');
            }
            if (! Schema::hasColumn('tenant_gateways', 'webhook_last_fail_at')) {
                $table->timestamp('webhook_last_fail_at')->nullable()->after('webhook_fail_count');
            }
        });
    }
};
