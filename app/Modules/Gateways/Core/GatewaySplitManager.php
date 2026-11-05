<?php

namespace App\Modules\Gateways\Core;

class GatewaySplitManager
{
    public function calculate(float $amount, array $splitRules = []): array
    {
        $percent = (float) ($splitRules['percent'] ?? 0);
        $fixed = (float) ($splitRules['fixed'] ?? 0);
        $commission = (float) ($splitRules['commission'] ?? 0);
        $splitValue = ($amount * ($percent / 100)) + $fixed + $commission;

        return [
            'split_amount' => round($splitValue, 2),
            'tenant_amount' => round(max(0, $amount - $splitValue), 2),
        ];
    }
}
