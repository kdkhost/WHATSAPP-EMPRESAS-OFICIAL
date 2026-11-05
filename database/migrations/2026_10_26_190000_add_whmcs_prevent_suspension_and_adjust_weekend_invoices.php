<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whmcs_connections')) {
            Schema::table('whmcs_connections', function (Blueprint $table) {
                if (! Schema::hasColumn('whmcs_connections', 'prevent_weekend_holiday_suspension')) {
                    $table->boolean('prevent_weekend_holiday_suspension')
                        ->default(true)
                        ->after('auto_unsuspend_paid_services')
                        ->comment('Bloqueia que contas de clientes sejam suspensas no WHMCS em fins de semana e feriados bancarios');
                }
            });
        }

        // Ajuste retroativo: move qualquer fatura pendente existente configurada para final de semana ou feriado
        try {
            Invoice::adjustPendingInvoicesToBusinessDays();
        } catch (\Throwable) {
            // Ignora caso tabelas auxiliares de feriados ainda não estejam carregadas
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whmcs_connections')) {
            Schema::table('whmcs_connections', function (Blueprint $table) {
                if (Schema::hasColumn('whmcs_connections', 'prevent_weekend_holiday_suspension')) {
                    $table->dropColumn('prevent_weekend_holiday_suspension');
                }
            });
        }
    }
};
