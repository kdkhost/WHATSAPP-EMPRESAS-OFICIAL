<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admin_users', 'users'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'avatar_url')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('avatar_url', 500)->nullable()->after('phone');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['admin_users', 'users'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'avatar_url')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('avatar_url');
                });
            }
        }
    }
};
