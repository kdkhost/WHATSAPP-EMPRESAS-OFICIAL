<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Tenancy\Models\CustomerPackageInstallment;
use App\Models\Invoice;

class SyncInstallmentsCommand extends Command
{
    protected $signature = 'installments:sync';
    protected $description = 'Sincroniza o status das parcelas com suas respectivas faturas para evitar contagens divergentes (parcelas pendentes fantasmas).';

    public function handle()
    {
        $this->info('Iniciando sincronização de parcelas e faturas...');

        // Buscar todas as parcelas
        $installments = CustomerPackageInstallment::all();
        $updated = 0;
        $deleted = 0;

        foreach ($installments as $installment) {
            if ($installment->invoice_id) {
                $invoice = Invoice::find($installment->invoice_id);
                if ($invoice) {
                    if ($installment->status !== $invoice->status || $installment->paid_at != $invoice->paid_at) {
                        $installment->update([
                            'status' => $invoice->status,
                            'paid_at' => $invoice->paid_at,
                        ]);
                        $updated++;
                    }
                } else {
                    // Se a fatura não existe mais, apagamos a parcela orfã
                    $installment->delete();
                    $deleted++;
                }
            }
        }

        $this->info("Sincronização concluída. Atualizadas: {$updated}. Órfãs removidas: {$deleted}.");
        return self::SUCCESS;
    }
}
