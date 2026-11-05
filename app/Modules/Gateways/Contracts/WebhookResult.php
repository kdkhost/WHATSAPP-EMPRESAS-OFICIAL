<?php

namespace App\Modules\Gateways\Contracts;

class WebhookResult
{
    public function __construct(
        public readonly bool   $processed,
        public readonly string $status,        // paid, refunded, canceled, ignored
        public readonly ?int   $invoiceId = null,
        public readonly array  $raw = [],
    ) {}
}
