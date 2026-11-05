<?php

namespace App\Modules\PublicApi\Http\Middleware;

use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Token de API obrigatorio.'], 401);
        }

        if ($user->currentAccessToken() && ! $user->tokenCan('public-api')) {
            return response()->json(['message' => 'Token sem permissao para a API publica.'], 403);
        }

        $tenant = Tenant::with('plan')->where('user_id', $user->id)->first();

        if (! $tenant) {
            return response()->json(['message' => 'Usuario sem tenant vinculado.'], 403);
        }

        if (! $tenant->isActive()) {
            return response()->json(['message' => 'Tenant sem assinatura ativa para usar a API.'], 402);
        }

        if (! $tenant->publicApiEnabled()) {
            return response()->json([
                'message' => 'API REST nao habilitada para este tenant.',
                'upgrade_required' => true,
            ], 402);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
