<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting avancado por rota com sliding window + lockout progressivo.
 *
 * Limites:
 *  - login:    5 tentativas / 5min, block 15min (anti brute-force)
 *  - register: 3 / 10min
 *  - api:      60 / min
 *  - webhooks: 30 / min
 *  - admin:    120 / min
 *  - default:  180 / min
 */
class RateLimitByRoute
{
    protected array $limits = [
        'login'       => ['max' => 15,  'window' => 300,  'block' => 120],
        'admin_login' => ['max' => 15,  'window' => 300,  'block' => 120],
        'register'    => ['max' => 5,   'window' => 600,  'block' => 300],
        'password'    => ['max' => 5,   'window' => 600,  'block' => 300],
        'api'         => ['max' => 60,  'window' => 60,   'block' => 60],
        'webhooks'    => ['max' => 30,  'window' => 60,   'block' => 0],
        'admin'       => ['max' => 300, 'window' => 60,   'block' => 30],
        'default'     => ['max' => 180, 'window' => 60,   'block' => 0],
    ];

    public function handle(Request $request, Closure $next, ?string $context = null): Response
    {
        $context = $context ?? $this->detectContext($request);

        // Nao aplicar rate limit em GET de paginas admin/login/register/password (apenas POST/PUT/DELETE)
        if (in_array($context, ['admin', 'admin_login', 'login', 'register', 'password'], true) && $request->isMethod('GET')) {
            return $next($request);
        }

        $limit = $this->limits[$context] ?? $this->limits['default'];
        $email = $request->input('email');
        if (in_array($context, ['login', 'admin_login'], true) && !empty($email) && is_string($email)) {
            $identifier = substr(md5(strtolower(trim($email))), 0, 10) . '_' . $request->ip();
        } else {
            $identifier = $request->user() ? 'u' . $request->user()->id : $request->ip();
        }

        $key = "rl3:{$context}:{$identifier}";
        $blockKey = "rl3_block:{$context}:{$identifier}";

        if (Cache::has($blockKey)) {
            $retryAfter = (int) Cache::get($blockKey) - time();
            return $this->blocked($retryAfter, $context);
        }

        if (!Cache::has($key)) {
            Cache::put($key, 1, $limit['window']);
            $hits = 1;
        } else {
            Cache::increment($key);
            $hits = (int) Cache::get($key);
        }

        if ($hits > $limit['max']) {
            if ($limit['block'] > 0) {
                Cache::put($blockKey, time() + $limit['block'], $limit['block']);
            }
            $this->logEvent($request, $context, $hits, $limit['max']);
            return $this->blocked($limit['block'], $context);
        }

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit['max']);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $limit['max'] - $hits));
        return $response;
    }

    protected function detectContext(Request $request): string
    {
        $path = $request->path();
        if (str_starts_with($path, 'admin/login') || $path === 'admin/login') return 'admin_login';
        if (str_starts_with($path, 'admin/')) return 'admin';
        if (str_contains($path, 'login')) return 'login';
        if (str_contains($path, 'cadastro') || str_contains($path, 'register')) return 'register';
        if (str_contains($path, 'esqueci-senha') || str_contains($path, 'password')) return 'password';
        if (str_starts_with($path, 'api/')) return 'api';
        if (str_starts_with($path, 'webhooks/')) return 'webhooks';
        if (str_starts_with($path, 'painel')) return 'painel';
        return 'default';
    }

    protected function blocked(int $retryAfter, string $context): Response
    {
        $minutes = max(1, (int) ceil(max(1, $retryAfter) / 60));
        $msg = match ($context) {
            'login', 'admin_login' => 'Muitas tentativas de login. Aguarde ' . $minutes . ' minuto(s) antes de tentar novamente.',
            'register'             => 'Muitas tentativas de cadastro. Aguarde ' . $minutes . ' minuto(s) antes de tentar novamente.',
            'password'             => 'Muitas tentativas de redefinicao de senha. Aguarde ' . $minutes . ' minuto(s) antes de tentar novamente.',
            default                => 'Limite de requisicoes excedido. Aguarde alguns instantes e tente novamente.',
        };
        if (request()->expectsJson()) {
            return response()->json(['error' => 'rate_limit_exceeded', 'message' => $msg, 'retry_after' => max(0, $retryAfter)], 429);
        }
        if (in_array($context, ['login', 'admin_login', 'register', 'password'], true)) {
            return redirect()
                ->back()
                ->withInput(request()->except(['password', 'password_confirmation']))
                ->withErrors(['email' => $msg])
                ->setStatusCode(429);
        }
        abort(429, $msg);
    }

    protected function logEvent(Request $request, string $context, int $hits, int $max): void
    {
        try {
            DB::table('security_events')->insert([
                'rule_code' => 'ratelimit', 'severity' => $context === 'login' ? 'high' : 'medium',
                'action_taken' => 'blocked', 'ip' => $request->ip(), 'method' => $request->method(),
                'uri' => substr($request->getRequestUri(), 0, 500),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'matched' => json_encode(['context' => $context, 'hits' => $hits, 'max' => $max]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {}
    }
}
