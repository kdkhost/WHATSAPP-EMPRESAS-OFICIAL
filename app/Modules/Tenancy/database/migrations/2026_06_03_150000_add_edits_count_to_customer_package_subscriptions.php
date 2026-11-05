<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_package_subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('edits_count')->default(0)->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('customer_package_subscriptions', function (Blueprint $table) {
            $table->dropColumn('edits_count');
        });
    }
};
