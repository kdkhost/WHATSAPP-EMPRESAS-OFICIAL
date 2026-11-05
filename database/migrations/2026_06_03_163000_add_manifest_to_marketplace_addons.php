<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_addons', 'manifest')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $table->json('manifest')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_addons', 'manifest')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $table->dropColumn('manifest');
            });
        }
    }
};
