<?php

namespace App\Listeners;

use App\Events\EvolutionWebhookReceived;
use Illuminate\Support\Facades\Log;

class LogEvolutionWebhookReceived
{
    public function handle(EvolutionWebhookReceived $event): void
    {
        Log::info('Evolution webhook recebido', [
            'log_id' => $event->log->id,
            'tenant_id' => $event->log->tenant_id,
            'instance' => $event->log->instance_name,
            'event' => $event->log->event_name,
            'status' => $event->log->status,
        ]);
    }
}
