<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'onboarding_completed_at')) {
                $t->timestamp('onboarding_completed_at')->nullable()->after('lgpd_accepted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'onboarding_completed_at')) {
                $t->dropColumn('onboarding_completed_at');
            }
        });
    }
};
