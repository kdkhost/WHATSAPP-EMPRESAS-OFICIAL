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

namespace App\Modules\Security\Http\Middleware;

use App\Modules\Security\Services\WafService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware do WAF próprio com suporte a modos: off | detect | block
 *
 * Modos:
 *  off    — WAF desligado, passa tudo.
 *  detect — Detecta e registra, mas NÃO bloqueia.
 *  block  — Detecta, registra e bloqueia com 403.
 */
class WafMiddleware
{
    public function __construct(protected WafService $waf) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Verificar modo global do WAF
        $mode = $this->getWafMode();

        if ($mode === 'off') {
            return $next($request);
        }

        // 2. Rotas que nunca passam pelo WAF
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        // 3. Rotas admin: verificar se WAF admin está habilitado
        if ($request->is('admin/*', 'admin') && !$this->isWafAdminEnabled()) {
            return $next($request);
        }

        // 4. Inspecionar requisição
        $verdict = $this->waf->inspect($request);

        if ($verdict['block']) {
            // Modo detect: registra mas não bloqueia
            if ($mode === 'detect') {
                return $next($request);
            }

            // Resposta JSON para APIs ou chamadas AJAX do frontend
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Requisição bloqueada por segurança.',
                ], 403);
            }

            // Modo block: bloqueia com página personalizada
            return response()->view('errors.waf-blocked', [
                'reason' => 'Requisição bloqueada por segurança.',
                'rule'   => null, // Nunca expor a regra ao visitante
            ], 403);
        }

        return $next($request);
    }

    protected function getWafMode(): string
    {
        return Cache::remember('waf.mode', 60, function () {
            try {
                $enabled = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'security.waf_enabled')
                    ->value('value');
                if ($enabled !== null && !filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                    return 'off';
                }

                $val = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'security.waf_mode')
                    ->value('value');
                return in_array($val, ['off', 'detect', 'block'], true) ? $val : 'detect';
            } catch (\Throwable) {
                return env('SECURITY_WAF_MODE', 'detect');
            }
        });
    }

    protected function isWafAdminEnabled(): bool
    {
        return Cache::remember('waf.admin_enabled', 60, function () {
            try {
                $val = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'security.waf_admin_enabled')
                    ->value('value');
                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } catch (\Throwable) {
                return filter_var(env('SECURITY_WAF_ADMIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
            }
        });
    }

    protected function shouldBypass(Request $request): bool
    {
        return $request->is(
            'up',
            'build/*',
            'assets/*',
            'uploads/*',
            'installer.php',
            'install*',
            'api/docs',
            'contrato/*',
            'og-image*',
            'og-images/*',
            'webhooks/*',
            'api/webhooks/*',
            'cron*'
        );
    }
}
