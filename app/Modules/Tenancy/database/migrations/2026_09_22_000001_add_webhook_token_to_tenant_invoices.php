<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_invoices') && ! Schema::hasColumn('tenant_invoices', 'webhook_token')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                $table->string('webhook_token', 64)->nullable()->unique()->after('payment_url')
                      ->comment('Token único para webhook de pagamento (URL pública)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_invoices') && Schema::hasColumn('tenant_invoices', 'webhook_token')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                $table->dropIndex(['webhook_token']);
                $table->dropColumn('webhook_token');
            });
        }
    }
};
