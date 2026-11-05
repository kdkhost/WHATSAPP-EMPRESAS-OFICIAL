<?php

namespace App\Modules\PublicApi\Http\Middleware;

use App\Models\SecurityAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ThrottlePublicApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = max(5, (int) config('whatsapp_anti_ban.security.api_rate_limit_per_minute', 30));
        $window = 60;
        $tokenHash = sha1((string) ($request->bearerToken() ?: 'no-token'));
        $userId = $request->user()?->id ?: 'guest';
        $tenantId = optional($request->attributes->get('tenant'))->id ?: 'no-tenant';
        $route = $request->route()?->getName() ?: $request->path();
        $key = "public-api:{$tenantId}:{$userId}:{$tokenHash}:{$route}";

        $hits = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $hits, $window);

        if ($hits > $limit) {
            $this->audit($request, $tenantId, $route, $hits, $limit);

            return response()->json([
                'error' => 'public_api_rate_limit_exceeded',
                'message' => 'Limite de requisicoes da API excedido. Aguarde alguns instantes.',
                'retry_after' => $window,
            ], 429);
        }

        $response = $next($request);
        $response->headers->set('X-PublicApi-Limit', (string) $limit);
        $response->headers->set('X-PublicApi-Remaining', (string) max(0, $limit - $hits));

        return $response;
    }

    protected function audit(Request $request, string|int $tenantId, string $route, int $hits, int $limit): void
    {
        try {
            SecurityAuditLog::create([
                'tenant_id' => is_numeric($tenantId) ? (int) $tenantId : null,
                'guard' => 'api',
                'action' => 'public_api_rate_limit_exceeded',
                'subject_type' => 'route',
                'subject_id' => $route,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => ['hits' => $hits, 'limit' => $limit],
                'status' => 'blocked',
                'error' => 'Rate limit por token/tenant excedido.',
            ]);
        } catch (\Throwable) {
        }
    }
}
