<?php

namespace App\Modules\Contracts\Services;

use App\Modules\Contracts\Models\Contract;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Gerencia limites mensais de contratos por tenant.
 */
class ContractLimitService
{
    /**
     * Retorna a quota atual do tenant.
     *
     * @return array{limit: int|null, used: int, remaining: int|null}
     */
    public function getQuota(Tenant $tenant): array
    {
        $limit = $tenant->plan?->max_contracts_month ?? null;

        // NULL ou 0 = ilimitado
        if (! $limit) {
            $limit = null;
        }

        $used = Contract::where('tenant_id', $tenant->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        return [
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => $limit !== null ? max(0, $limit - $used) : null,
        ];
    }

    /**
     * Verifica se o tenant atingiu o limite mensal.
     */
    public function hasReachedLimit(Tenant $tenant): bool
    {
        $quota = $this->getQuota($tenant);

        if ($quota['limit'] === null) {
            return false;
        }

        return $quota['used'] >= $quota['limit'];
    }
}
