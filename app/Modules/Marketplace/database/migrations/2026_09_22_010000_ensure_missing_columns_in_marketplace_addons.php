<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_addons')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                if (! Schema::hasColumn('marketplace_addons', 'source')) {
                    $table->string('source', 40)->default('system')->index()->nullable();
                }
                if (! Schema::hasColumn('marketplace_addons', 'color')) {
                    $table->string('color', 40)->nullable();
                }
                if (! Schema::hasColumn('marketplace_addons', 'commercial')) {
                    $table->boolean('commercial')->default(true)->index();
                }
                if (! Schema::hasColumn('marketplace_addons', 'submission_id')) {
                    $table->unsignedBigInteger('submission_id')->nullable()->index();
                }
                if (! Schema::hasColumn('marketplace_addons', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->index();
                }
                if (! Schema::hasColumn('marketplace_addons', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->index();
                }
                if (! Schema::hasColumn('marketplace_addons', 'package_path')) {
                    $table->string('package_path', 255)->nullable();
                }
            });

            // Atualiza registros legados para source = 'system'
            DB::table('marketplace_addons')
                ->whereNull('source')
                ->orWhere('source', '')
                ->update(['source' => 'system']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_addons')) {
            Schema::table('marketplace_addons', function (Blueprint $table) {
                $columns = ['source', 'color', 'commercial', 'submission_id', 'approved_by', 'approved_at', 'package_path'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('marketplace_addons', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
