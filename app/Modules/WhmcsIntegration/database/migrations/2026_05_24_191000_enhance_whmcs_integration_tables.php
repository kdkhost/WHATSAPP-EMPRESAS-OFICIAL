<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whmcs_connections')) {
            Schema::table('whmcs_connections', function (Blueprint $table) {
                if (! Schema::hasColumn('whmcs_connections', 'webhook_secret')) {
                    $table->string('webhook_secret', 64)->nullable()->after('webhook_token');
                }
                if (! Schema::hasColumn('whmcs_connections', 'webhook_require_signature')) {
                    $table->boolean('webhook_require_signature')->default(false)->after('webhook_secret');
                }
                if (! Schema::hasColumn('whmcs_connections', 'default_gateway')) {
                    $table->string('default_gateway', 40)->nullable()->after('auto_send_invoices');
                }
                if (! Schema::hasColumn('whmcs_connections', 'sync_statuses')) {
                    $table->json('sync_statuses')->nullable()->after('default_gateway');
                }
                if (! Schema::hasColumn('whmcs_connections', 'sync_paid_invoices')) {
                    $table->boolean('sync_paid_invoices')->default(true)->after('sync_statuses');
                }
                if (! Schema::hasColumn('whmcs_connections', 'sync_cancelled_invoices')) {
                    $table->boolean('sync_cancelled_invoices')->default(true)->after('sync_paid_invoices');
                }
                if (! Schema::hasColumn('whmcs_connections', 'last_test_at')) {
                    $table->timestamp('last_test_at')->nullable()->after('last_sync_at');
                }
                if (! Schema::hasColumn('whmcs_connections', 'last_test_status')) {
                    $table->string('last_test_status', 20)->nullable()->after('last_test_at');
                }
                if (! Schema::hasColumn('whmcs_connections', 'last_error')) {
                    $table->text('last_error')->nullable()->after('last_sync_error');
                }
                if (! Schema::hasColumn('whmcs_connections', 'last_sync_stats')) {
                    $table->json('last_sync_stats')->nullable()->after('last_error');
                }
                if (! Schema::hasColumn('whmcs_connections', 'last_webhook_at')) {
                    $table->timestamp('last_webhook_at')->nullable()->after('last_sync_stats');
                }
            });

            DB::table('whmcs_connections')
                ->whereNull('webhook_secret')
                ->orderBy('id')
                ->get(['id'])
                ->each(function ($row): void {
                    DB::table('whmcs_connections')
                        ->where('id', $row->id)
                        ->update(['webhook_secret' => Str::random(64)]);
                });
        }

        if (! Schema::hasTable('whmcs_webhook_events')) {
            Schema::create('whmcs_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('connection_id')->nullable()->constrained('whmcs_connections')->nullOnDelete();
                $table->string('event', 80)->nullable()->index();
                $table->string('invoice_id', 60)->nullable()->index();
                $table->string('status', 30)->nullable()->index();
                $table->string('replay_key', 64)->nullable()->unique();
                $table->boolean('signature_valid')->nullable();
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whmcs_webhook_events');

        if (Schema::hasTable('whmcs_connections')) {
            Schema::table('whmcs_connections', function (Blueprint $table) {
                foreach ([
                    'last_webhook_at',
                    'last_sync_stats',
                    'last_error',
                    'last_test_status',
                    'last_test_at',
                    'sync_cancelled_invoices',
                    'sync_paid_invoices',
                    'sync_statuses',
                    'default_gateway',
                    'webhook_require_signature',
                    'webhook_secret',
                ] as $column) {
                    if (Schema::hasColumn('whmcs_connections', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
