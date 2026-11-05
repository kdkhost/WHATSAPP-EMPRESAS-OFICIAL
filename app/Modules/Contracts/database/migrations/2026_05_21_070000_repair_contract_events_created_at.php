<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_events') || ! Schema::hasColumn('contract_events', 'created_at')) {
            return;
        }

        DB::table('contract_events')
            ->whereNull('created_at')
            ->update(['created_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
