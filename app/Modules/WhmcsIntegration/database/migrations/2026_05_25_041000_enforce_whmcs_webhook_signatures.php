<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whmcs_connections') || ! Schema::hasColumn('whmcs_connections', 'webhook_require_signature')) {
            return;
        }

        DB::table('whmcs_connections')
            ->where(function ($query) {
                $query->whereNull('webhook_require_signature')
                    ->orWhere('webhook_require_signature', false);
            })
            ->update([
                'webhook_require_signature' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Forward-only security hardening.
    }
};
