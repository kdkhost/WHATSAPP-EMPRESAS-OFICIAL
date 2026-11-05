<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'tour_completed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('tour_completed_at')->nullable()->after('onboarding_completed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tour_completed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tour_completed_at');
            });
        }
    }
};
