<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica autenticacao para rotas administrativas.
 *
 * - Rotas /painel* aceitam o guard 'web' (tenant logado) OU guard 'admin' (modo assistido).
 * - Todas as outras rotas exigem exclusivamente o guard 'admin'.
 */
class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        // Rotas do painel do tenant aceitam guard 'web' (tenant) ou guard 'admin' (modo assistido)
        if ($request->is('painel', 'painel/*')) {
            if (Auth::guard('web')->check() || Auth::guard('admin')->check()) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => 'nao_autenticado'], 401);
            }

            return redirect()->route('login');
        }

        // Demais rotas (admin/*) exigem guard 'admin'
        if (! Auth::guard('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'nao_autenticado'], 401);
            }
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
