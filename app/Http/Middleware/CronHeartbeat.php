<?php

namespace App\Http\Middleware;

use App\Services\CronManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Heartbeat do cron interno - sem dependencia de trafego ou cron do cPanel.
 *
 * Como funciona:
 *   1. A requisicao do usuario eh processada normalmente.
 *   2. ANTES de retornar, registramos um shutdown_function.
 *   3. Apos o response ser entregue (fastcgi_finish_request), verificamos jobs vencidos.
 *
 * Resultado: zero impacto perceptivel na latencia do site.
 */
class CronHeartbeat
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Nao roda em rotas de console/api/cron-externo (evita loop)
        // Nao roda em /install* (wizard de instalacao precisa funcionar antes do banco existir)
        if (app()->environment('testing') || $request->is('cron*', 'up', 'install*')) {
            return $response;
        }

        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                app(CronManager::class)->heartbeat();
            } catch (\Throwable $e) {
                logger()->warning('[CronHeartbeat] '.$e->getMessage());
            }
        });

        return $response;
    }
}
