<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admin_users', 'users'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'notification_sound_enabled')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->boolean('notification_sound_enabled')->default(true);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['admin_users', 'users'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'notification_sound_enabled')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('notification_sound_enabled');
                });
            }
        }
    }
};
