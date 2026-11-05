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
 * Valida IP de origem dos webhooks de gateways de pagamento.
 *
 * Apenas IPs conhecidos dos gateways sao aceitos.
 * Se o IP nao estiver na whitelist, o webhook eh rejeitado com 403.
 *
 * Uso: Route::post('/webhooks/mercadopago/{token}', ...)->middleware('webhook.ip:mercadopago');
 */
class ValidateWebhookIp
{
    /**
     * IPs/CIDRs oficiais dos gateways.
     * Fonte: documentacao oficial de cada gateway.
     * Atualize conforme necessario.
     */
    protected array $allowedIps = [
        'mercadopago' => [
            '216.33.196.0/24',
            '209.225.49.0/24',
            '18.184.0.0/15',
            '18.228.0.0/16',
            '54.94.0.0/16',
            // Permite qualquer em dev (descomente em producao)
        ],
        'efipro' => [
            '34.193.0.0/16',
            '52.67.0.0/16',
            '18.229.0.0/16',
        ],
        'sumup' => [
            '52.29.0.0/16',
            '52.57.0.0/16',
            '3.120.0.0/14',
        ],
    ];

    public function handle(Request $request, Closure $next, string $gateway = ''): Response
    {
        // Em ambiente de desenvolvimento, nao bloquear
        if (config('app.debug') === true && config('app.env') !== 'production') {
            return $next($request);
        }

        $ip = $request->ip();
        $allowed = $this->allowedIps[$gateway] ?? [];

        // Se nenhuma whitelist definida, passa (gateway novo/desconhecido)
        if (empty($allowed)) {
            return $next($request);
        }

        foreach ($allowed as $range) {
            if ($this->ipInRange($ip, $range)) {
                return $next($request);
            }
        }

        // IP nao autorizado
        $this->logUnauthorized($request, $gateway);

        return response()->json([
            'error' => 'ip_not_allowed',
            'message' => 'IP de origem nao autorizado para este webhook.',
        ], 403);
    }

    protected function ipInRange(string $ip, string $range): bool
    {
        if (! str_contains($range, '/')) {
            return $ip === $range;
        }
        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;
        $ipLong = ip2long($ip);
        $sub = ip2long($subnet);
        if ($ipLong === false || $sub === false) return false;
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($sub & $mask);
    }

    protected function logUnauthorized(Request $request, string $gateway): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('security_events')->insert([
                'rule_code'    => 'webhook_ip_blocked',
                'severity'     => 'high',
                'action_taken' => 'blocked',
                'ip'           => $request->ip(),
                'method'       => $request->method(),
                'uri'          => substr($request->getRequestUri(), 0, 500),
                'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
                'matched'      => json_encode(['gateway' => $gateway, 'ip' => $request->ip()]),
                'created_at'   => now(),
            ]);
        } catch (\Throwable) {}
    }
}
