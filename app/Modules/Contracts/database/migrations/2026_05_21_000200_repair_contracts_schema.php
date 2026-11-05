<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'number')) {
                $table->string('number', 30)->nullable()->unique();
            }
            if (! Schema::hasColumn('contracts', 'signer_name')) {
                $table->string('signer_name')->nullable();
            }
            if (! Schema::hasColumn('contracts', 'signer_email')) {
                $table->string('signer_email')->nullable();
            }
            if (! Schema::hasColumn('contracts', 'signer_document')) {
                $table->string('signer_document', 20)->nullable();
            }
            if (! Schema::hasColumn('contracts', 'signer_ip')) {
                $table->string('signer_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('contracts', 'signer_user_agent')) {
                $table->text('signer_user_agent')->nullable();
            }
            if (! Schema::hasColumn('contracts', 'signature_image')) {
                $table->longText('signature_image')->nullable();
            }
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `contracts` MODIFY `status` enum('draft','sent','viewed','signed','rejected','expired','canceled') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            foreach ([
                'signature_image',
                'signer_user_agent',
                'signer_ip',
                'signer_document',
                'signer_email',
                'signer_name',
            ] as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('contracts', 'number')) {
                $table->dropUnique(['number']);
                $table->dropColumn('number');
            }
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `contracts` MODIFY `status` enum('draft','sent','viewed','signed','expired','canceled') NOT NULL DEFAULT 'draft'");
        }
    }
};
