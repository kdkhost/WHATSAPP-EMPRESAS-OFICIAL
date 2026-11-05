<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'tenant_id')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'tenant_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'tenant_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'tenant_id')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
