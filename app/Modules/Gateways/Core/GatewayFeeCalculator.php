<?php

namespace App\Modules\Gateways\Core;

class GatewayFeeCalculator
{
    public function calculate(float $amount, array $fees = [], array $installments = []): array
    {
        $fixed = (float) ($fees['fixed'] ?? 0);
        $percent = (float) ($fees['percent'] ?? 0);
        $installmentPercent = (float) ($installments['interest_percent'] ?? 0);
        $count = max(1, (int) ($installments['max_installments'] ?? 1));
        $baseFee = $fixed + ($amount * ($percent / 100));
        $installmentFee = $count > 1 ? ($amount * ($installmentPercent / 100)) : 0;

        return [
            'amount' => round($amount, 2),
            'fixed_fee' => round($fixed, 2),
            'percent_fee' => round($amount * ($percent / 100), 2),
            'installment_fee' => round($installmentFee, 2),
            'total_fee' => round($baseFee + $installmentFee, 2),
            'net_amount' => round($amount - ($baseFee + $installmentFee), 2),
        ];
    }
}
