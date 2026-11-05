<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_addons', 'is_installed')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $table->boolean('is_installed')->default(false)->index()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_addons', 'is_installed')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $table->dropColumn('is_installed');
            });
        }
    }
};
