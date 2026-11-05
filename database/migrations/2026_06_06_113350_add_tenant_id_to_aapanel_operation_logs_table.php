<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aapanel_operation_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('aapanel_operation_logs', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        // Backfill: preenche tenant_id nos logs existentes a partir do servidor vinculado
        $servers = DB::table('aapanel_servers')->whereNotNull('tenant_id')->get(['id', 'tenant_id']);
        foreach ($servers as $server) {
            DB::table('aapanel_operation_logs')
                ->where('server_id', $server->id)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $server->tenant_id]);
        }
    }

    public function down(): void
    {
        Schema::table('aapanel_operation_logs', function (Blueprint $table) {
            if (Schema::hasColumn('aapanel_operation_logs', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
