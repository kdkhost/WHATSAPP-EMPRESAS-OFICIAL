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
        if (! Schema::hasTable('tenant_instances')) {
            return;
        }

        $this->expandStatusEnum();

        Schema::table('tenant_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_instances', 'api_base_url')) {
                $table->string('api_base_url')->nullable()->after('is_default');
            }

            if (! Schema::hasColumn('tenant_instances', 'api_key')) {
                $table->text('api_key')->nullable()->after('api_base_url');
            }

            if (! Schema::hasColumn('tenant_instances', 'webhook_url')) {
                $table->string('webhook_url')->nullable()->after('api_key');
            }

            if (! Schema::hasColumn('tenant_instances', 'webhook_enabled')) {
                $table->boolean('webhook_enabled')->default(false)->after('webhook_url');
            }

            if (! Schema::hasColumn('tenant_instances', 'webhook_by_events')) {
                $table->boolean('webhook_by_events')->default(true)->after('webhook_enabled');
            }

            if (! Schema::hasColumn('tenant_instances', 'webhook_base64')) {
                $table->boolean('webhook_base64')->default(false)->after('webhook_by_events');
            }

            if (! Schema::hasColumn('tenant_instances', 'webhook_events')) {
                $table->json('webhook_events')->nullable()->after('webhook_base64');
            }

            if (! Schema::hasColumn('tenant_instances', 'webhook_secret')) {
                $table->string('webhook_secret', 96)->nullable()->after('webhook_events');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_name')) {
                $table->string('profile_name')->nullable()->after('webhook_secret');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_status')) {
                $table->string('profile_status', 500)->nullable()->after('profile_name');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_avatar_url')) {
                $table->text('profile_avatar_url')->nullable()->after('profile_status');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_number')) {
                $table->string('profile_number', 30)->nullable()->after('profile_avatar_url');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_jid')) {
                $table->string('profile_jid')->nullable()->after('profile_number');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_raw')) {
                $table->json('profile_raw')->nullable()->after('profile_jid');
            }

            if (! Schema::hasColumn('tenant_instances', 'last_connection_state')) {
                $table->string('last_connection_state', 40)->nullable()->after('profile_raw');
            }

            if (! Schema::hasColumn('tenant_instances', 'last_profile_sync_at')) {
                $table->timestamp('last_profile_sync_at')->nullable()->after('last_connection_state');
            }

            if (! Schema::hasColumn('tenant_instances', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_profile_sync_at');
            }
        });

        DB::table('tenant_instances')
            ->whereNull('webhook_secret')
            ->orderBy('id')
            ->chunkById(100, function ($instances) {
                foreach ($instances as $instance) {
                    DB::table('tenant_instances')
                        ->where('id', $instance->id)
                        ->update([
                            'webhook_secret' => Str::random(48),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function expandStatusEnum(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tenant_instances MODIFY status ENUM('creating','active','connected','disconnected','suspended','banned') NOT NULL DEFAULT 'creating'");
    }

    public function down(): void
    {
        // Migration operacional: mantemos os dados de perfil/configuracao para evitar perda acidental.
    }
};
