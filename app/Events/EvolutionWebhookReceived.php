<?php

namespace App\Events;

use App\Models\WhatsAppWebhookLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvolutionWebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public WhatsAppWebhookLog $log) {}
}
