<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return;
        }

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_templates', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('whatsapp_templates', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('tenant_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return;
        }

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_templates', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};
