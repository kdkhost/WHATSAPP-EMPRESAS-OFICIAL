<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $t) {
            $t->text('two_factor_secret')->nullable()->after('theme_dark');
            $t->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $t->boolean('two_factor_enabled')->default(false)->after('two_factor_recovery_codes');
            $t->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $t) {
            $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled', 'two_factor_confirmed_at']);
        });
    }
};
