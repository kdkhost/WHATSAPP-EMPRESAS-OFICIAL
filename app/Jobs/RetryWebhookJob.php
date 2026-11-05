<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookLog;
use App\Services\EvolutionGoWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $logId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(EvolutionGoWebhookService $webhooks): void
    {
        $log = WhatsAppWebhookLog::find($this->logId);
        if (! $log || $log->status !== 'failed') {
            return;
        }

        $webhooks->processLog($this->logId);
    }
}
