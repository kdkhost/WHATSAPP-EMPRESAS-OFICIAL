<?php

namespace App\Modules\Contracts\Services;

use App\Modules\Tenancy\Models\Tenant;

/**
 * Centraliza toda a lógica de verificação de acesso ao ContractsModule.
 *
 * Um tenant tem acesso quando:
 *  - O plano contém "contracts" no array features, OU
 *  - O tenant possui um TenantAddon ativo com addon.slug = 'contracts'
 *    (verificado via AddonMarketplaceService para evitar acesso direto a campos ausentes)
 */
class ContractAccessService
{
    public function tenantHasAccess(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        // Acesso via features do plano
        $features = collect($tenant->plan?->features ?? [])
            ->map(fn ($f) => mb_strtolower(trim((string) $f)));

        if ($features->contains('contracts')) {
            return true;
        }

        // Acesso via addon do marketplace
        // TenantAddon não tem slug diretamente — usa a relação addon (MarketplaceAddon)
        // que tem o campo slug e o status 'active' é em TenantAddon.status
        return $tenant->addons()
            ->where('status', 'active')
            ->whereHas('addon', fn ($q) => $q->where('slug', 'contracts')->where('is_active', true))
            ->exists();
    }
}
