<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_gestao_financeira_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('addon_gestao_financeira_accounts', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        Schema::table('addon_gestao_financeira_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('addon_gestao_financeira_categories', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        Schema::table('addon_gestao_financeira_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('addon_gestao_financeira_transactions', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addon_gestao_financeira_accounts', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('addon_gestao_financeira_categories', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('addon_gestao_financeira_transactions', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
