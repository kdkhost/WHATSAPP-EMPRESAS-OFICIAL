<?php

namespace App\Jobs;

use App\Services\EvolutionGoSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEvolutionInstancesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public ?int $tenantId = null)
    {
        $this->onQueue('sync');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(EvolutionGoSyncService $sync): void
    {
        $result = $sync->syncAll($this->tenantId);

        Log::info('Sincronizacao Evolution concluida', $result + ['tenant_id' => $this->tenantId]);
    }
}
