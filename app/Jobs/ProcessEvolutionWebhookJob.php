<?php

namespace App\Jobs;

use App\Services\EvolutionGoWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEvolutionWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $logId)
    {
        $this->onQueue('webhooks');
    }

    public function backoff(): array
    {
        return [10, 30, 90, 180];
    }

    public function handle(EvolutionGoWebhookService $webhooks): void
    {
        $webhooks->processLog($this->logId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao processar webhook Evolution', [
            'log_id' => $this->logId,
            'error' => $exception->getMessage(),
        ]);
    }
}
