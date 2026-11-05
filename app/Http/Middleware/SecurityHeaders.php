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
 * Injeta headers de seguranca em TODAS as respostas HTTP.
 *
 * Protege contra:
 *  - Clickjacking (X-Frame-Options, frame-ancestors)
 *  - MIME sniffing (X-Content-Type-Options)
 *  - XSS reflexivo (X-XSS-Protection + CSP)
 *  - Downgrade HTTPS (Strict-Transport-Security)
 *  - Referrer leaking (Referrer-Policy)
 *  - Permissions abuse (Permissions-Policy)
 *  - Injecao de conteudo (Content-Security-Policy)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('build/*', 'assets/*', 'uploads/*')) {
            return $response;
        }

        // Clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS Protection (legacy backup)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // HSTS: forca HTTPS por 1 ano
        if ($request->secure() || config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy: desabilita features desnecessarias
        $response->headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(self), usb=()');

        // Content-Security-Policy
        $googleScripts = 'https://www.googletagmanager.com https://www.google-analytics.com';
        $googleConnections = 'https://www.googletagmanager.com https://www.google-analytics.com https://analytics.google.com https://stats.g.doubleclick.net https://region1.google-analytics.com';
        $pluggySources = 'https://cdn.pluggy.ai https://connect.pluggy.ai https://api.pluggy.ai https://*.pluggy.ai';
        $dataTablesSources = 'https://cdn.datatables.net';
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com {$dataTablesSources} {$googleScripts} {$pluggySources}",
            "script-src-elem 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com {$dataTablesSources} {$googleScripts} {$pluggySources}",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com {$dataTablesSources}",
            "img-src 'self' data: blob: https:",
            "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "connect-src 'self' https://viacep.com.br https://api.mercadopago.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com {$dataTablesSources} {$googleConnections} {$pluggySources}",
            "frame-src 'self' https://www.googletagmanager.com https://connect.pluggy.ai https://*.pluggy.ai",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "worker-src 'self' blob: https://cdn.jsdelivr.net",
            "manifest-src 'self'",
            "upgrade-insecure-requests",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Cross-Origin policies (Permite iframes e scripts do Pluggy Connect)
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');

        // No-cache para admin
        if ($request->is('admin*', 'install*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
