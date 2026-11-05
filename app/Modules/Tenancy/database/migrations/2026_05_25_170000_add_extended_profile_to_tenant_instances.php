<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_instances')) {
            return;
        }

        Schema::table('tenant_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_instances', 'profile_push_name')) {
                $table->string('profile_push_name')->nullable()->after('profile_name');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_full_name')) {
                $table->string('profile_full_name')->nullable()->after('profile_push_name');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_first_name')) {
                $table->string('profile_first_name')->nullable()->after('profile_full_name');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_business_name')) {
                $table->string('profile_business_name')->nullable()->after('profile_first_name');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_is_business')) {
                $table->boolean('profile_is_business')->default(false)->after('profile_business_name');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_picture_id')) {
                $table->string('profile_picture_id')->nullable()->after('profile_avatar_url');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_devices')) {
                $table->json('profile_devices')->nullable()->after('profile_jid');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_lid')) {
                $table->string('profile_lid')->nullable()->after('profile_devices');
            }

            if (! Schema::hasColumn('tenant_instances', 'profile_contact_found')) {
                $table->boolean('profile_contact_found')->nullable()->after('profile_lid');
            }
        });
    }

    public function down(): void
    {
        // Migration operacional: os metadados de perfil devem ser preservados.
    }
};
