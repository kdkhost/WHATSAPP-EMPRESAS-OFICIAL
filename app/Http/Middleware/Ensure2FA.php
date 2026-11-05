<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que verifica se o usuario com 2FA ativado ja confirmou o codigo.
 *
 * Admin e tenant usam guards/sessoes separados para o acesso supervisionado
 * nunca vazar para o fluxo do superadmin.
 */
class Ensure2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('painel*')) {
            return $this->handleTenant($request, $next);
        }

        return $this->handleAdmin($request, $next);
    }

    protected function handleAdmin(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();
        if (! $user) {
            return $next($request);
        }

        if (! $this->twoFactorEnabled($user)) {
            return $next($request);
        }

        if ($request->is('admin/2fa*', 'admin/logout')) {
            return $next($request);
        }

        if (! session('2fa_confirmed')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => '2fa_required', 'redirect' => '/admin/2fa/verify'], 403);
            }
            return redirect('/admin/2fa/verify');
        }

        return $next($request);
    }

    protected function handleTenant(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user || $request->session()->get('admin_assist_mode')) {
            return $next($request);
        }

        if (! $this->twoFactorEnabled($user)) {
            return $next($request);
        }

        if ($request->is('painel/2fa*', 'logout')) {
            return $next($request);
        }

        if (! session('tenant_2fa_confirmed')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => '2fa_required', 'redirect' => '/painel/2fa/verify'], 403);
            }

            return redirect('/painel/2fa/verify');
        }

        return $next($request);
    }

    protected function twoFactorEnabled(object $user): bool
    {
        return (bool) ($user->two_factor_enabled ?? false) && ! empty($user->two_factor_secret);
    }
}
