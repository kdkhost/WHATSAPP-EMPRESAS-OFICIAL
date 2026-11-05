<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_visits')) {
            Schema::table('system_visits', function (Blueprint $table) {
                if (! Schema::hasColumn('system_visits', 'admin_id')) {
                    $table->foreignId('admin_id')->nullable()->after('user_id')->index()->constrained('admin_users')->nullOnDelete();
                }
                if (! Schema::hasColumn('system_visits', 'guard')) {
                    $table->string('guard', 20)->nullable()->default('web')->after('admin_id')->index();
                }
            });

            // Correção retroativa dos registros onde o admin estava gravado em user_id
            try {
                DB::table('system_visits')
                    ->where(function ($q) {
                        $q->where('path', 'like', '/admin%')
                          ->orWhere('path', 'like', 'admin%');
                    })
                    ->whereNotNull('user_id')
                    ->update([
                        'admin_id' => DB::raw('user_id'),
                        'guard'    => 'admin',
                        'user_id'  => null,
                    ]);

                DB::table('system_visits')
                    ->whereNull('guard')
                    ->whereNotNull('user_id')
                    ->update([
                        'guard' => 'web',
                    ]);
            } catch (\Throwable) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_visits')) {
            Schema::table('system_visits', function (Blueprint $table) {
                if (Schema::hasColumn('system_visits', 'admin_id')) {
                    $table->dropForeign(['admin_id']);
                    $table->dropColumn('admin_id');
                }
                if (Schema::hasColumn('system_visits', 'guard')) {
                    $table->dropColumn('guard');
                }
            });
        }
    }
};

