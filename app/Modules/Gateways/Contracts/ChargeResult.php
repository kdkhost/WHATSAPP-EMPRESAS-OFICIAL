<?php

namespace App\Modules\Gateways\Contracts;

class ChargeResult
{
    public function __construct(
        public readonly string $chargeId,
        public readonly string $paymentUrl,
        public readonly string $webhookToken,
        public readonly array  $raw = [],
    ) {}
}
