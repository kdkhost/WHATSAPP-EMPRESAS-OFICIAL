<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * BaseController do addon Gestão Financeira.
 *
 * ISOLAMENTO:
 * - Tenant (guard web): usa tenant_id da tabela tenants — ve apenas seus dados
 * - SuperAdmin (guard admin): retorna null — ve TODOS os dados (visao global)
 *   O admin e o dono do sistema, nao precisa de isolamento.
 */
abstract class BaseController extends Controller
{
    protected function routePrefix(): string
    {
        return request()->is('painel*')
            ? 'tenant.gestao-financeira.'
            : 'admin.gestao-financeira.';
    }

    protected function route(string $name): string
    {
        return $this->routePrefix() . $name;
    }

    protected function redirectTo(string $name, array $params = [])
    {
        return redirect()->route($this->routePrefix() . $name, $params);
    }

    /**
     * Retorna o tenant_id do usuario autenticado.
     *
     * - Guard web  → tenant real via tabela tenants (isolamento de dados)
     * - Guard admin → null (admin ve todos os dados — visao global do sistema)
     */
    protected function currentTenantId(): ?int
    {
        // Tenant normal via guard web
        if (Auth::guard('web')->check()) {
            $userId = Auth::guard('web')->id();
            return Cache::remember("finance_tenant_id_web_{$userId}", 300, function () use ($userId) {
                return Tenant::where('user_id', $userId)->value('id');
            });
        }

        // SuperAdmin via guard admin — sem filtro (visao global)
        return null;
    }

    /**
     * Verifica se o contexto atual e de admin (sem filtro de tenant).
     */
    protected function isAdminContext(): bool
    {
        return Auth::guard('admin')->check() && ! request()->is('painel*');
    }

    /**
     * Verifica se o contexto atual e do SuperAdmin no painel administrativo (/admin/).
     * Retorna FALSE quando estiver no painel do tenant (/painel/), mesmo em modo assistido.
     */
    protected function isSuperAdmin(): bool
    {
        return $this->isAdminContext();
    }

    /**
     * Garante que sempre ha um tenant_id — aborta se nao resolver.
     * Usado apenas em contexto de tenant (guard web).
     */
    protected function requireTenantId(): int
    {
        $id = $this->currentTenantId();

        if ($id === null && ! $this->isAdminContext()) {
            abort(403, 'Contexto de usuário não identificado.');
        }

        // Admin sem tenant_id — retorna 0 como sentinela
        if ($id === null) {
            return 0;
        }

        return (int) $id;
    }
}

