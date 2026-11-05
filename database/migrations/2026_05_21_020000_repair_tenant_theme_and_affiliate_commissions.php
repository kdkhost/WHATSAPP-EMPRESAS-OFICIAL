<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'theme_dark')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('theme_dark')->default(false);
            });
        }

        if (! Schema::hasTable('affiliate_commissions')) {
            return;
        }

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_commissions', 'base_amount')) {
                $table->decimal('base_amount', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('affiliate_commissions', 'commission_pct')) {
                $table->decimal('commission_pct', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('affiliate_commissions', 'commission_amount')) {
                $table->decimal('commission_amount', 12, 2)->default(0);
            }
        });

        if (
            Schema::hasColumn('affiliate_commissions', 'amount')
            && Schema::hasColumn('affiliate_commissions', 'commission_amount')
        ) {
            DB::table('affiliate_commissions')
                ->where(function ($query) {
                    $query->whereNull('commission_amount')
                        ->orWhere('commission_amount', 0);
                })
                ->update(['commission_amount' => DB::raw('amount')]);
        }
    }

    public function down(): void
    {
        // Migration corretiva: rollback destrutivo poderia remover colunas validas de bancos ja atualizados.
    }
};
