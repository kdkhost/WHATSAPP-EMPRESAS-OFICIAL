<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts') && ! Schema::hasColumn('contracts', 'invoice_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('customer_id');
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'invoice_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            });
        }
    }
};
