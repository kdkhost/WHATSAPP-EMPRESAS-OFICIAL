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
 * CORS Policy restritiva para a API.
 *
 * - Apenas origins autorizados podem consumir /api/*
 * - Em producao: apenas o dominio do app
 * - Credenciais (cookies) permitidos apenas para same-origin
 * - Methods e headers estritamente limitados
 */
class CorsPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        // Apenas rotas API
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin', '');
        $allowedOrigins = $this->getAllowedOrigins();

        // Preflight (OPTIONS)
        if ($request->isMethod('OPTIONS')) {
            return $this->preflightResponse($origin, $allowedOrigins);
        }

        $response = $next($request);

        // Adicionar headers CORS apenas se origin permitido
        if ($this->isAllowed($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
            $response->headers->set('Access-Control-Max-Age', '7200');
            $response->headers->set('Access-Control-Expose-Headers', 'X-RateLimit-Limit, X-RateLimit-Remaining');
        }

        return $response;
    }

    protected function getAllowedOrigins(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $origins = [$appUrl];

        // Em dev, permitir localhost
        if (config('app.debug')) {
            $origins[] = 'http://localhost:5173'; // Vite dev server
            $origins[] = 'http://localhost:3000';
            $origins[] = 'http://localhost:8000';
        }

        return $origins;
    }

    protected function isAllowed(string $origin, array $allowed): bool
    {
        if (empty($origin)) return false;
        return in_array($origin, $allowed, true);
    }

    protected function preflightResponse(string $origin, array $allowedOrigins): Response
    {
        $headers = [
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept',
            'Access-Control-Max-Age'       => '7200',
        ];

        if ($this->isAllowed($origin, $allowedOrigins)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return response('', 204, $headers);
    }
}
