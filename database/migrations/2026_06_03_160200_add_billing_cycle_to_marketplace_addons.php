<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_addons', 'billing_cycle')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $table->string('billing_cycle', 30)->default('monthly')->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_addons', 'billing_cycle')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $table->dropColumn('billing_cycle');
            });
        }
    }
};
