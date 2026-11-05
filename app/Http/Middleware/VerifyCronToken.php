<?php

namespace App\Http\Middleware;

use App\Services\CronManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Administrador autenticado no painel pode acessar/disparar o cron diretamente
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        $expected = (string) config('modules.cron.external_token', '');
        $fallback = CronManager::resolvedExternalToken();
        $provided = (string) $request->query('token', $request->header('X-Cron-Token', ''));

        $validConfigured = ($expected !== '' && $expected !== 'troque-este-token-32-chars' && hash_equals($expected, $provided));
        $validFallback   = ($fallback !== '' && hash_equals($fallback, $provided));

        if (! $validConfigured && ! $validFallback) {
            if ($request->expectsJson() || $request->is('cron*')) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Token de cron invalido ou ausente. Forneca o parametro ?token=... ou o cabecalho X-Cron-Token.',
                ], 401);
            }
            abort(401, 'Token de cron invalido.');
        }

        return $next($request);
    }
}
