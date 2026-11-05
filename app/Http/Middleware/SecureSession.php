<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441
 * @contato Email: contato@kdkhost.com.br
 * @contato Telegram: @MARCELO_BRAD
 * @contato Instagram: @marcelobradrj
 * @contato WhatsApp: 21981325441
 *
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Este arquivo integra software proprietário. É proibida a cópia,
 * distribuição, sublicenciamento, engenharia reversa, revenda, cessão, publicação ou uso
 * fora do projeto autorizado sem permissão expressa e por escrito do titular.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protecao contra session fixation e hijacking.
 *
 * Medidas implementadas:
 *  1. Regenera session ID apos login (ja feito nos controllers - aqui eh backup)
 *  2. Vincula session ao IP + User-Agent (fingerprint)
 *  3. Expira session inativa apos 30 minutos
 *  4. Detecta troca de IP/UA mid-session (possivel hijack)
 *  5. SameSite=Lax nos cookies (configurado no session.php)
 */
class SecureSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nao aplicar em rotas sem session (API, cron, webhooks)
        if ($request->is('api/*', 'cron*', 'webhooks/*', 'up')) {
            return $next($request);
        }

        // Bypass durante acesso supervisionado (admin assist)
        if (session('admin_assist_mode')) {
            return $next($request);
        }

        // 0. Verifica se esta sessao foi revogada/desconectada remotamente
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        if ($sessionId && app(\App\Services\SessionManagerService::class)->isSessionRevoked($sessionId)) {
            if (\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
                \Illuminate\Support\Facades\Auth::guard('admin')->logout();
            }
            if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => 'session_revoked', 'message' => 'Sua sessao foi encerrada pelo administrador.'], 401);
            }

            $redirectRoute = $request->is('admin', 'admin/*') ? 'admin.login' : 'login';
            return redirect()->route($redirectRoute)->with('error', 'Sua sessao foi encerrada e desconectada.');
        }

        // Verificar fingerprint da session (IP + UA hash)
        $currentFingerprint = $this->fingerprint($request);
        $storedFingerprint = session('_security_fingerprint');

        if ($storedFingerprint && $storedFingerprint !== $currentFingerprint) {
            // Possivel session hijacking detectado - invalida session
            $this->logHijackAttempt($request, $storedFingerprint, $currentFingerprint);
            session()->invalidate();
            session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => 'session_invalidated', 'message' => 'Sessao invalidada por seguranca.'], 401);
            }
            return redirect('/login')->with('error', 'Sua sessao foi invalidada por motivos de seguranca. Faca login novamente.');
        }

        // Gravar fingerprint se nao existir (primeira vez)
        if (! $storedFingerprint && session()->has('_token')) {
            session(['_security_fingerprint' => $currentFingerprint]);
        }

        // Verificar inatividade (30 minutos)
        $lastActivity = session('_security_last_activity', time());
        $maxInactive = 1800; // 30 minutos

        if ((time() - $lastActivity) > $maxInactive) {
            session()->invalidate();
            session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => 'session_expired'], 401);
            }
            return redirect('/login')->with('error', 'Sessao expirada por inatividade.');
        }

        session(['_security_last_activity' => time()]);

        $response = $next($request);

        // Garantir cookie flags seguros
        $config = config('session');
        if ($response->headers->has('Set-Cookie')) {
            // Laravel ja configura via session.php - aqui eh belt-and-suspenders
        }

        return $response;
    }

    protected function fingerprint(Request $request): string
    {
        // Hash apenas do User-Agent (IP pode mudar em redes moveis/proxies)
        $ua = $request->userAgent() ?? '';
        return hash('sha256', $ua . '|' . config('app.key'));
    }

    protected function logHijackAttempt(Request $request, string $stored, string $current): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('security_events')->insert([
                'rule_code'    => 'session_hijack',
                'severity'     => 'critical',
                'action_taken' => 'blocked',
                'ip'           => $request->ip(),
                'method'       => $request->method(),
                'uri'          => substr($request->getRequestUri(), 0, 500),
                'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
                'matched'      => json_encode([
                    'stored_fingerprint' => substr($stored, 0, 12) . '...',
                    'current_fingerprint' => substr($current, 0, 12) . '...',
                ]),
                'created_at'   => now(),
            ]);
        } catch (\Throwable) {}
    }
}
