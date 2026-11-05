<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'custom_domain')) {
                $table->string('custom_domain', 190)->nullable()->unique();
            }
            if (! Schema::hasColumn('tenants', 'custom_domain_dns_target')) {
                $table->string('custom_domain_dns_target', 190)->nullable();
            }
            if (! Schema::hasColumn('tenants', 'custom_domain_status')) {
                $table->string('custom_domain_status', 30)->default('not_configured')->index();
            }
            if (! Schema::hasColumn('tenants', 'custom_domain_verified_at')) {
                $table->timestamp('custom_domain_verified_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'custom_domain_last_checked_at')) {
                $table->timestamp('custom_domain_last_checked_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'custom_domain_last_error')) {
                $table->string('custom_domain_last_error', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'custom_domain_last_error',
                'custom_domain_last_checked_at',
                'custom_domain_verified_at',
                'custom_domain_status',
                'custom_domain_dns_target',
                'custom_domain',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
