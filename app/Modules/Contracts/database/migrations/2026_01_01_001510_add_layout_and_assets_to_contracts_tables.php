<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('contract_templates', 'preset_key')) {
                    $table->string('preset_key', 120)->nullable();
                }
                if (! Schema::hasColumn('contract_templates', 'category')) {
                    $table->string('category', 80)->nullable();
                }
                if (! Schema::hasColumn('contract_templates', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('contract_templates', 'background_type')) {
                    $table->string('background_type', 20)->default('none');
                }
                if (! Schema::hasColumn('contract_templates', 'background_path')) {
                    $table->string('background_path')->nullable();
                }
                if (! Schema::hasColumn('contract_templates', 'layout_json')) {
                    $table->json('layout_json')->nullable();
                }
            });
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('contracts', 'background_type')) {
                    $table->string('background_type', 20)->default('none');
                }
                if (! Schema::hasColumn('contracts', 'background_path')) {
                    $table->string('background_path')->nullable();
                }
                if (! Schema::hasColumn('contracts', 'layout_json')) {
                    $table->json('layout_json')->nullable();
                }
                if (! Schema::hasColumn('contracts', 'signed_html_path')) {
                    $table->string('signed_html_path')->nullable();
                }
                if (! Schema::hasColumn('contracts', 'signed_file_mime')) {
                    $table->string('signed_file_mime', 120)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                foreach (['background_type', 'background_path', 'layout_json', 'signed_html_path', 'signed_file_mime'] as $column) {
                    if (Schema::hasColumn('contracts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('contract_templates')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                foreach (['preset_key', 'category', 'description', 'background_type', 'background_path', 'layout_json'] as $column) {
                    if (Schema::hasColumn('contract_templates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
