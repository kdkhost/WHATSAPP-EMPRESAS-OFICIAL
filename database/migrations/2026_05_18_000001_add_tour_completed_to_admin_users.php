<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $t) {
            if (!Schema::hasColumn('admin_users', 'tour_completed_at')) {
                $t->timestamp('tour_completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $t) {
            if (Schema::hasColumn('admin_users', 'tour_completed_at')) {
                $t->dropColumn('tour_completed_at');
            }
        });
    }
};
