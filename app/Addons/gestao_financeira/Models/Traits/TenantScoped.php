<?php

namespace App\Addons\gestao_financeira\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Modules\Tenancy\Models\Tenant;

/**
 * TenantScoped — isolamento por tenant_id.
 *
 * - Guard web  → filtra por tenant_id do tenant normal (isolamento total)
 * - Guard admin → sem filtro (admin ve todos os dados do sistema)
 * - Nenhum guard → bloqueia (sem dados)
 */
trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = self::resolveScopedTenantId();

            if ($tenantId !== null) {
                $builder->where('tenant_id', $tenantId);
            } elseif (Auth::guard('admin')->check()) {
                // Admin autenticado — sem filtro (visao global)
                // Nao aplica nenhum where — retorna todos os registros
            } else {
                // Sem contexto identificado — retorna zero registros (fail-closed)
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (! empty($model->tenant_id)) {
                return; // ja definido explicitamente
            }

            $tenantId = self::resolveScopedTenantId();
            if ($tenantId !== null) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * Resolve o tenant_id correto para o usuario autenticado no momento.
     * - Guard web  → tenant real
     * - Guard admin → null (sem filtro)
     * - Nenhum → null (fail-closed)
     */
    protected static function resolveScopedTenantId(): ?int
    {
        // Tenant normal
        if (Auth::guard('web')->check()) {
            $userId = Auth::guard('web')->id();
            return Cache::remember("finance_tenant_id_web_{$userId}", 300, function () use ($userId) {
                return Tenant::where('user_id', $userId)->value('id');
            });
        }

        // Admin — sem filtro (retorna null)
        return null;
    }
}
