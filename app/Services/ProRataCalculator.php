<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Calculadora de pro-rata para upgrades, downgrades e cobrancas parciais.
 *
 * Casos de uso:
 *  1. Cliente assina plano no meio do mes -> cobra apenas dias restantes
 *  2. Upgrade meio do ciclo -> cobra diferenca proporcional
 *  3. Suspensao temporaria -> credita dias nao usados
 */
class ProRataCalculator
{
    /**
     * Calcula valor pro-rata para os dias restantes do periodo.
     *
     * @param float $fullPrice Valor cheio do plano
     * @param Carbon $startDate Data de inicio do servico
     * @param Carbon $periodEnd Data de fim do periodo cobrado
     * @return array [amount, days_used, days_total, percentage]
     */
    public function calculate(float $fullPrice, Carbon $startDate, Carbon $periodEnd): array
    {
        $periodStart = $periodEnd->copy()->subMonth();
        $totalDays = $periodStart->diffInDays($periodEnd) ?: 30;

        // Garante que start nao seja antes do periodo
        $effectiveStart = $startDate->isBefore($periodStart) ? $periodStart : $startDate;
        $usedDays = $effectiveStart->diffInDays($periodEnd);
        $usedDays = max(0, min($usedDays, $totalDays));

        $dailyRate = $fullPrice / $totalDays;
        $amount = round($dailyRate * $usedDays, 2);
        $percentage = $totalDays > 0 ? round(($usedDays / $totalDays) * 100, 2) : 0;

        return [
            'amount'      => $amount,
            'daily_rate'  => round($dailyRate, 4),
            'days_used'   => $usedDays,
            'days_total'  => $totalDays,
            'percentage'  => $percentage,
            'period_start'=> $periodStart->toDateString(),
            'period_end'  => $periodEnd->toDateString(),
        ];
    }

    /** Diferenca pro-rata em upgrade (cobra apenas a diferenca dos dias restantes). */
    public function upgrade(float $oldPrice, float $newPrice, Carbon $upgradeDate, Carbon $periodEnd): array
    {
        $remainingDays = max(0, $upgradeDate->diffInDays($periodEnd));
        $totalDays = 30; // mes padrao

        $diff = $newPrice - $oldPrice;
        $amount = round(($diff / $totalDays) * $remainingDays, 2);

        return [
            'amount'         => $amount,
            'price_diff'     => $diff,
            'remaining_days' => $remainingDays,
            'old_price'      => $oldPrice,
            'new_price'      => $newPrice,
        ];
    }

    /** Credito por cancelamento antecipado (dias nao usados). */
    public function refundCredit(float $fullPrice, Carbon $cancelDate, Carbon $periodEnd): array
    {
        $unusedDays = max(0, $cancelDate->diffInDays($periodEnd));
        $totalDays = 30;
        $credit = round(($fullPrice / $totalDays) * $unusedDays, 2);

        return [
            'credit'      => $credit,
            'unused_days' => $unusedDays,
            'cancel_date' => $cancelDate->toDateString(),
        ];
    }
}
