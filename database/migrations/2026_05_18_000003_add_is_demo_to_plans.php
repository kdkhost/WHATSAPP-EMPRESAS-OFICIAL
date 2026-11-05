<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            if (!Schema::hasColumn('plans', 'is_demo')) {
                $t->boolean('is_demo')->default(false)->after('active');
            }
            if (!Schema::hasColumn('plans', 'highlight')) {
                $t->boolean('highlight')->default(false)->after('is_demo');
            }
            if (!Schema::hasColumn('plans', 'badge')) {
                $t->string('badge', 30)->nullable()->after('highlight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            if (Schema::hasColumn('plans', 'is_demo')) $t->dropColumn('is_demo');
            if (Schema::hasColumn('plans', 'highlight')) $t->dropColumn('highlight');
            if (Schema::hasColumn('plans', 'badge')) $t->dropColumn('badge');
        });
    }
};
