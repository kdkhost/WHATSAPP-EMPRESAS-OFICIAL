<?php

namespace App\Modules\Installer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BLOQUEIA completamente rotas de instalacao apos o sistema estar instalado.
 *
 * Protecao contra engenharia reversa:
 *  - Retorna 404 (NAO 302/403) — nao revela que a rota existe
 *  - Registra tentativa como evento de seguranca
 *  - Atacante/scanner nao sabe se o sistema usa instalador ou nao
 *  - Nenhum header vazado que indique framework
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('installed.lock'))) {
            // Registra tentativa no WAF (possivel scan/ataque)
            $this->logAttempt($request);

            // 404 generico — oculta existencia da rota
            abort(404);
        }
        return $next($request);
    }

    protected function logAttempt(Request $request): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('security_events')->insert([
                'rule_code'    => 'install_access_blocked',
                'severity'     => 'high',
                'action_taken' => 'blocked',
                'ip'           => $request->ip(),
                'method'       => $request->method(),
                'uri'          => substr($request->getRequestUri(), 0, 500),
                'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
                'matched'      => json_encode(['reason' => 'Acesso a /install apos sistema instalado']),
                'created_at'   => now(),
            ]);
        } catch (\Throwable) {}
    }
}
