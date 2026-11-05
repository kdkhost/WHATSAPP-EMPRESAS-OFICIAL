<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('contracts', 'content_html')) {
                    $table->longText('content_html')->nullable()->after('title');
                }
                if (! Schema::hasColumn('contracts', 'background_type')) {
                    $table->string('background_type', 20)->default('none')->after('content_html');
                }
                if (! Schema::hasColumn('contracts', 'background_path')) {
                    $table->string('background_path')->nullable()->after('background_type');
                }
                if (! Schema::hasColumn('contracts', 'layout_json')) {
                    $table->json('layout_json')->nullable()->after('background_path');
                }
                if (! Schema::hasColumn('contracts', 'signed_html_path')) {
                    $table->string('signed_html_path')->nullable()->after('signature_image');
                }
                if (! Schema::hasColumn('contracts', 'signed_file_mime')) {
                    $table->string('signed_file_mime', 120)->nullable()->after('signed_html_path');
                }
                if (! Schema::hasColumn('contracts', 'signed_pdf_path')) {
                    $table->string('signed_pdf_path')->nullable()->after('signed_file_mime');
                }
                if (! Schema::hasColumn('contracts', 'return_channel')) {
                    $table->enum('return_channel', ['email', 'whatsapp', 'both'])->nullable()->after('signed_pdf_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                foreach (['return_channel', 'signed_file_mime', 'signed_html_path', 'layout_json', 'background_path', 'background_type', 'content_html'] as $column) {
                    if (Schema::hasColumn('contracts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
