<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliates')) {
            // Garante que o status suporte 'rejected' de forma segura
            try {
                $driver = DB::getDriverName();
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE affiliates MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
                }
            } catch (\Throwable $e) {
                // Silently fallback se o driver for SQLite em testes
            }

            Schema::table('affiliates', function (Blueprint $table) {
                if (! Schema::hasColumn('affiliates', 'promotion_channel')) {
                    $table->string('promotion_channel', 100)->nullable()->after('referral_code');
                }
                if (! Schema::hasColumn('affiliates', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('status');
                }
                if (! Schema::hasColumn('affiliates', 'admin_notes')) {
                    $table->text('admin_notes')->nullable()->after('rejection_reason');
                }
                if (! Schema::hasColumn('affiliates', 'mp_collector_id')) {
                    $table->string('mp_collector_id', 50)->nullable()->after('payment_info');
                }
                if (! Schema::hasColumn('affiliates', 'mp_public_key')) {
                    $table->string('mp_public_key', 120)->nullable()->after('mp_collector_id');
                }
                if (! Schema::hasColumn('affiliates', 'mp_access_token')) {
                    $table->text('mp_access_token')->nullable()->after('mp_public_key');
                }
                if (! Schema::hasColumn('affiliates', 'split_enabled')) {
                    $table->boolean('split_enabled')->default(false)->after('mp_access_token');
                }
                if (! Schema::hasColumn('affiliates', 'split_pct')) {
                    $table->decimal('split_pct', 5, 2)->nullable()->after('split_enabled');
                }
                if (! Schema::hasColumn('affiliates', 'split_mode')) {
                    $table->string('split_mode', 20)->default('percentage')->after('split_pct');
                }
                if (! Schema::hasColumn('affiliates', 'applied_at')) {
                    $table->timestamp('applied_at')->nullable()->after('updated_at');
                }
                if (! Schema::hasColumn('affiliates', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('applied_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $cols = [
                    'promotion_channel',
                    'rejection_reason',
                    'admin_notes',
                    'mp_collector_id',
                    'mp_public_key',
                    'mp_access_token',
                    'split_enabled',
                    'split_pct',
                    'split_mode',
                    'applied_at',
                    'approved_at',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('affiliates', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
