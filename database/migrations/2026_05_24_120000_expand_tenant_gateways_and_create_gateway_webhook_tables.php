<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_gateways', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_gateways', 'settings')) {
                $table->json('settings')->nullable()->after('credentials');
            }
            if (! Schema::hasColumn('tenant_gateways', 'payment_methods')) {
                $table->json('payment_methods')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('tenant_gateways', 'fees')) {
                $table->json('fees')->nullable()->after('payment_methods');
            }
            if (! Schema::hasColumn('tenant_gateways', 'split_rules')) {
                $table->json('split_rules')->nullable()->after('fees');
            }
            if (! Schema::hasColumn('tenant_gateways', 'installment_rules')) {
                $table->json('installment_rules')->nullable()->after('split_rules');
            }
            if (! Schema::hasColumn('tenant_gateways', 'webhook_token')) {
                $table->string('webhook_token', 120)->nullable()->after('installment_rules');
            }
            if (! Schema::hasColumn('tenant_gateways', 'webhook_secret')) {
                $table->string('webhook_secret', 160)->nullable()->after('webhook_token');
            }
            if (! Schema::hasColumn('tenant_gateways', 'environment')) {
                $table->string('environment', 20)->nullable()->after('webhook_secret');
            }
            if (! Schema::hasColumn('tenant_gateways', 'priority')) {
                $table->integer('priority')->default(0)->after('environment');
            }
            if (! Schema::hasColumn('tenant_gateways', 'last_test_status')) {
                $table->string('last_test_status', 40)->nullable()->after('priority');
            }
            if (! Schema::hasColumn('tenant_gateways', 'last_test_at')) {
                $table->timestamp('last_test_at')->nullable()->after('last_test_status');
            }
            if (! Schema::hasColumn('tenant_gateways', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_test_at');
            }
        });

        if (! Schema::hasTable('gateway_webhooks')) {
            Schema::create('gateway_webhooks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('tenant_gateway_id')->nullable()->constrained('tenant_gateways')->nullOnDelete();
                $table->string('gateway', 30)->index();
                $table->string('webhook_token', 120)->nullable()->index();
                $table->string('webhook_secret', 160)->nullable();
                $table->string('signature_header', 80)->nullable();
                $table->string('timestamp_header', 80)->nullable();
                $table->boolean('debug_mode')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gateway_webhook_logs')) {
            Schema::create('gateway_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gateway_webhook_id')->nullable()->constrained('gateway_webhooks')->nullOnDelete();
                $table->string('gateway', 30)->index();
                $table->string('event', 120)->nullable()->index();
                $table->string('signature', 255)->nullable();
                $table->string('replay_hash', 120)->nullable()->index();
                $table->json('headers')->nullable();
                $table->longText('payload')->nullable();
                $table->boolean('processed')->default(false)->index();
                $table->boolean('valid_signature')->default(false);
                $table->boolean('replayed')->default(false);
                $table->text('error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gateway_webhook_events')) {
            Schema::create('gateway_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gateway_webhook_log_id')->constrained('gateway_webhook_logs')->cascadeOnDelete();
                $table->string('event', 120)->index();
                $table->string('status', 40)->default('queued')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->json('context')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gateway_webhook_failures')) {
            Schema::create('gateway_webhook_failures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gateway_webhook_event_id')->constrained('gateway_webhook_events')->cascadeOnDelete();
                $table->unsignedSmallInteger('attempt')->default(1);
                $table->text('error');
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_webhook_failures');
        Schema::dropIfExists('gateway_webhook_events');
        Schema::dropIfExists('gateway_webhook_logs');
        Schema::dropIfExists('gateway_webhooks');

        Schema::table('tenant_gateways', function (Blueprint $table) {
            foreach ([
                'settings',
                'payment_methods',
                'fees',
                'split_rules',
                'installment_rules',
                'webhook_token',
                'webhook_secret',
                'environment',
                'priority',
                'last_test_status',
                'last_test_at',
                'last_error',
            ] as $column) {
                if (Schema::hasColumn('tenant_gateways', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
