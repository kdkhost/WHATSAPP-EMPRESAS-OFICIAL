<?php

namespace App\Modules\Contracts\Http\Middleware;

use App\Models\SecurityAuditLog;
use App\Modules\Contracts\Services\ContractAccessService;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege todas as rotas /painel/contratos/* verificando acesso ao módulo.
 *
 * - Superadmin (guard admin): acesso sempre permitido
 * - Tenant autenticado (guard web) com acesso ao plano/addon: permitido
 * - Tenant sem acesso: redireciona para /painel/contratos/upgrade (ou JSON 403)
 */
class TenantContractMiddleware
{
    public function __construct(protected ContractAccessService $accessService) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Superadmin sempre tem acesso
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            if ($admin?->role === 'superadmin') {
                return $next($request);
            }
        }

        // Resolve tenant do guard web
        $tenant = $this->resolveTenant();

        if ($tenant && $this->accessService->tenantHasAccess($tenant)) {
            return $next($request);
        }

        // Acesso negado — registrar auditoria
        $this->auditDenied($request, $tenant);

        if ($request->expectsJson()) {
            return response()->json([
                'error'   => 'modulo_indisponivel',
                'message' => 'Seu plano não inclui o módulo de Contratos.',
            ], 403);
        }

        return redirect()->route('tenant.contracts.upgrade');
    }

    protected function resolveTenant(): ?Tenant
    {
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if (! $user) {
                return null;
            }
            $tenant = Tenant::where('user_id', $user->id)->first();
            if (! $tenant && ! empty($user->tenant_id)) {
                $tenant = Tenant::find($user->tenant_id);
            }
            return $tenant;
        }

        return null;
    }

    protected function auditDenied(Request $request, ?Tenant $tenant): void
    {
        try {
            $userId = null;
            if (Auth::guard('web')->check()) {
                $userId = Auth::guard('web')->id();
            }

            SecurityAuditLog::create([
                'tenant_id'  => $tenant?->id,
                'user_id'    => $userId,
                'guard'      => 'web',
                'action'     => 'contract_module_access_denied',
                'subject_type' => 'url',
                'subject_id'   => null,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload'    => ['url' => $request->fullUrl()],
                'status'     => 'denied',
            ]);
        } catch (\Throwable $e) {
            // Falha silenciosa — não interrompe o fluxo
            Log::warning('[ContractAccess] Falha ao registrar auditoria: ' . $e->getMessage());
        }
    }
}
