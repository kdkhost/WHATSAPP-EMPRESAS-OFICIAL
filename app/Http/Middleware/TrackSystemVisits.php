<?php

namespace App\Http\Middleware;

use App\Models\SystemVisit;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackSystemVisits
{
    /** Rotas e caminhos que não devem ser contabilizados como visita de página */
    protected array $ignoredPaths = [
        'up',
        'cron',
        'notifications/feed',
        'admin/dashboard/kpis',
        'admin/analytics/live-visitors',
        'webhooks',
        'api/site',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apenas processa após a resposta ser gerada, garantindo latência zero para o usuário
        $this->trackVisitQuietly($request);

        return $response;
    }

    protected function trackVisitQuietly(Request $request): void
    {
        try {
            $path = trim($request->path(), '/');

            // Ignora assets e extensões de arquivos
            if (preg_match('/\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|eot|map|json|webp)$/i', $path)) {
                return;
            }

            // Ignora rotas de polling e monitoramento
            foreach ($this->ignoredPaths as $ignored) {
                if ($path === $ignored || str_starts_with($path, $ignored . '/')) {
                    return;
                }
            }

            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            $ip = $request->ip();
            $userAgent = (string) $request->userAgent();

            // Identificação do Usuário Logado
            $userId = null;
            $adminId = null;
            $guard = null;
            $tenantId = null;
            $isAuthenticated = false;

            if (Auth::guard('admin')->check()) {
                $admin = Auth::guard('admin')->user();
                $adminId = $admin?->id;
                $guard = 'admin';
                $isAuthenticated = true;
            } elseif (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
                $userId = $user?->id;
                $guard = 'web';
                $isAuthenticated = true;
                $tenantId = Tenant::where('user_id', $userId)->value('id');
            }

            $now = now();
            $today = today();

            // Se for requisição AJAX sem ser GET de página, apenas atualiza last_activity_at da sessão
            if ($request->ajax() && ! $request->isMethod('GET')) {
                if ($sessionId) {
                    $updateData = [
                        'last_activity_at' => $now,
                        'is_authenticated' => $isAuthenticated,
                    ];
                    if ($adminId) {
                        $updateData['admin_id'] = $adminId;
                        $updateData['guard'] = 'admin';
                    } elseif ($userId) {
                        $updateData['user_id'] = $userId;
                        $updateData['guard'] = 'web';
                    }
                    SystemVisit::where('session_id', $sessionId)
                        ->whereDate('created_at', $today)
                        ->latest('id')
                        ->limit(1)
                        ->update($updateData);
                }
                return;
            }

            // Detecta detalhes do aparelho
            $deviceType = $this->detectDevice($userAgent);
            $browser    = $this->detectBrowser($userAgent);
            $platform   = $this->detectPlatform($userAgent);

            // Verifica se este visitante/sessão já tem registro desta página nos últimos 10 minutos
            $recentVisit = null;
            if ($sessionId) {
                $recentVisit = SystemVisit::where('session_id', $sessionId)
                    ->where('path', '/' . $path)
                    ->where('last_activity_at', '>=', $now->copy()->subMinutes(10))
                    ->first();
            }

            if ($recentVisit) {
                // Apenas atualiza a última atividade
                $recentVisit->update([
                    'last_activity_at' => $now,
                    'is_authenticated' => $isAuthenticated,
                    'user_id'          => $userId ?: $recentVisit->user_id,
                    'admin_id'         => $adminId ?: $recentVisit->admin_id,
                    'guard'            => $guard ?: $recentVisit->guard,
                    'tenant_id'        => $tenantId ?: $recentVisit->tenant_id,
                ]);
                return;
            }

            // Verifica se é o primeiro acesso desta sessão/IP no dia de hoje
            $hasVisitedToday = false;
            if ($sessionId) {
                $hasVisitedToday = SystemVisit::where('session_id', $sessionId)
                    ->whereDate('created_at', $today)
                    ->exists();
            } elseif ($ip) {
                $hasVisitedToday = SystemVisit::where('ip_address', $ip)
                    ->whereDate('created_at', $today)
                    ->exists();
            }

            $referer = (string) $request->header('referer');
            $refererHost = $referer ? parse_url($referer, PHP_URL_HOST) : null;

            SystemVisit::create([
                'user_id'          => $userId,
                'admin_id'         => $adminId,
                'guard'            => $guard,
                'tenant_id'        => $tenantId,
                'session_id'       => $sessionId,
                'ip_address'       => $ip,
                'user_agent'       => mb_substr($userAgent, 0, 500),
                'device_type'      => $deviceType,
                'browser'          => $browser,
                'platform'         => $platform,
                'path'             => '/' . $path,
                'url'              => mb_substr($request->fullUrl(), 0, 500),
                'method'           => $request->method(),
                'referer'          => $referer ? mb_substr($referer, 0, 500) : null,
                'referer_host'     => $refererHost ? mb_substr($refererHost, 0, 191) : null,
                'is_authenticated' => $isAuthenticated,
                'is_unique_today'  => ! $hasVisitedToday,
                'last_activity_at' => $now,
            ]);

        } catch (\Throwable $e) {
            // Rastreamento silencioso: nunca interromper requisições do usuário
        }
    }

    protected function detectDevice(string $ua): string
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/(iphone|ipod|mobile|android|blackberry|iemobile|opera mini|webos)/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    protected function detectBrowser(string $ua): string
    {
        if (preg_match('/Edg/i', $ua)) return 'Edge';
        if (preg_match('/Chrome/i', $ua)) return 'Chrome';
        if (preg_match('/Safari/i', $ua) && ! preg_match('/Chrome/i', $ua)) return 'Safari';
        if (preg_match('/Firefox/i', $ua)) return 'Firefox';
        if (preg_match('/OPR|Opera/i', $ua)) return 'Opera';
        return 'Outro';
    }

    protected function detectPlatform(string $ua): string
    {
        if (preg_match('/Windows/i', $ua)) return 'Windows';
        if (preg_match('/Android/i', $ua)) return 'Android';
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'iOS';
        if (preg_match('/Macintosh|Mac OS X/i', $ua)) return 'macOS';
        if (preg_match('/Linux/i', $ua)) return 'Linux';
        return 'Outro';
    }
}
