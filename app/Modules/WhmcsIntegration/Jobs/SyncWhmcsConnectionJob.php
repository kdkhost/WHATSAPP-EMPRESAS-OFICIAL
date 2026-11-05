<?php

namespace App\Modules\WhmcsIntegration\Jobs;

use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;
use App\Modules\WhmcsIntegration\Services\WhmcsServiceAutomation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWhmcsConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;
    public $tries = 1;

    public function __construct(
        public int $connectionId
    ) {}

    public function handle(BillingDispatcher $dispatcher, WhmcsServiceAutomation $automation): void
    {
        $connection = WhmcsConnection::find($this->connectionId);
        
        if (! $connection || ! $connection->active) {
            return;
        }

        try {
            $client = new WhmcsClient($connection);
            $stats = $client->syncAll();

            // Cancela cobranças de faturas que agora constam como pagas no WHMCS
            $canceledDispatches = $dispatcher->cancelPaidWhmcsInvoiceDispatches();
            
            $automationStats = $automation->run($connection);
            
            $finalStats = $stats + [
                'canceled_paid_dispatches' => $canceledDispatches,
                'billing_dispatches_scheduled' => $connection->auto_send_invoices ? $dispatcher->scheduleNewInvoices() : 0,
                'service_automation' => $automationStats,
            ];

            \Illuminate\Support\Facades\Cache::put("whmcs_sync_stats_{$connection->id}", $finalStats, now()->addMinutes(10));
            
            Log::info('[WHMCS] Sincronizacao assincrona concluida', [
                'connection_id' => $connection->id,
                'stats' => $finalStats
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::put("whmcs_sync_error_{$connection->id}", $e->getMessage(), now()->addMinutes(10));
            Log::error('[WHMCS] Erro na sincronizacao assincrona', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
