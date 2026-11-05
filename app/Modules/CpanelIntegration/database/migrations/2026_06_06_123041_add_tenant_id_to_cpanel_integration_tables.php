<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cpanel_servers', function (Blueprint $table) {
            if (! Schema::hasColumn('cpanel_servers', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        Schema::table('cpanel_actions_log', function (Blueprint $table) {
            if (! Schema::hasColumn('cpanel_actions_log', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        // Backfill: preenche tenant_id nos logs existentes a partir do servidor vinculado
        $servers = DB::table('cpanel_servers')->whereNotNull('tenant_id')->get(['id', 'tenant_id']);
        foreach ($servers as $server) {
            DB::table('cpanel_actions_log')
                ->where('server_id', $server->id)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $server->tenant_id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cpanel_actions_log', function (Blueprint $table) {
            if (Schema::hasColumn('cpanel_actions_log', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('cpanel_servers', function (Blueprint $table) {
            if (Schema::hasColumn('cpanel_servers', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
