<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('aapanel_servers', function (Blueprint $table) {
            if (! Schema::hasColumn('aapanel_servers', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        Schema::table('aapanel_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('aapanel_plans', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        Schema::table('aapanel_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('aapanel_accounts', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aapanel_servers', function (Blueprint $table) {
            $table->dropColumn(['tenant_id']);
        });

        Schema::table('aapanel_plans', function (Blueprint $table) {
            $table->dropColumn(['tenant_id']);
        });

        Schema::table('aapanel_accounts', function (Blueprint $table) {
            $table->dropColumn(['tenant_id']);
        });
    }
};
