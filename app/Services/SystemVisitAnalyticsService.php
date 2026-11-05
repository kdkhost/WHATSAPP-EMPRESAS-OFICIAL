<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\SystemVisit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SystemVisitAnalyticsService
{
    /** Retorna as métricas de visitantes online agora (últimos 5 minutos) */
    public function getOnlineStats(int $minutes = 5): array
    {
        $since = now()->subMinutes($minutes);

        // Agrupamento por sessão para não duplicar contagem do mesmo usuário navegando em várias abas
        $activeSessions = SystemVisit::where('last_activity_at', '>=', $since)
            ->select('session_id', 'user_id', 'admin_id', 'guard', 'is_authenticated', 'ip_address', 'path', 'device_type', 'browser', 'last_activity_at')
            ->orderByDesc('last_activity_at')
            ->get();

        $uniqueSessions = [];
        $loggedUserIds  = [];
        $loggedAdminIds = [];
        $anonymousCount = 0;

        foreach ($activeSessions as $visit) {
            $key = $visit->session_id ?: $visit->ip_address;
            if (! isset($uniqueSessions[$key])) {
                $uniqueSessions[$key] = $visit;
                if ($visit->is_authenticated) {
                    if ($visit->guard === 'admin' || $visit->admin_id) {
                        if ($visit->admin_id) {
                            $loggedAdminIds[] = $visit->admin_id;
                        }
                    } elseif ($visit->user_id) {
                        $loggedUserIds[] = $visit->user_id;
                    }
                } else {
                    $anonymousCount++;
                }
            }
        }

        $loggedUserIds  = array_unique($loggedUserIds);
        $loggedAdminIds = array_unique($loggedAdminIds);

        $users = ! empty($loggedUserIds) ? User::whereIn('id', $loggedUserIds)->get()->keyBy('id') : collect();
        $admins = ! empty($loggedAdminIds) ? AdminUser::whereIn('id', $loggedAdminIds)->get()->keyBy('id') : collect();

        $onlineUsers = [];
        foreach ($uniqueSessions as $session) {
            if ($session->is_authenticated) {
                if (($session->guard === 'admin' || $session->admin_id) && isset($admins[$session->admin_id])) {
                    $adm = $admins[$session->admin_id];
                    $onlineUsers[] = [
                        'id'         => $adm->id,
                        'name'       => $adm->name,
                        'email'      => $adm->email,
                        'role'       => 'admin',
                        'role_label' => 'Administrador',
                        'avatar'     => $adm->avatar_url ?? null,
                        'path'       => $session->path,
                        'device'     => $session->device_type,
                        'session_id' => $session->session_id,
                        'last_seen'  => $session->last_activity_at?->diffForHumans() ?? 'agora',
                    ];
                } elseif (isset($users[$session->user_id])) {
                    $u = $users[$session->user_id];
                    $onlineUsers[] = [
                        'id'         => $u->id,
                        'name'       => $u->name,
                        'email'      => $u->email,
                        'role'       => 'user',
                        'role_label' => 'Tenant',
                        'avatar'     => $u->avatar_url ?? null,
                        'path'       => $session->path,
                        'device'     => $session->device_type,
                        'session_id' => $session->session_id,
                        'last_seen'  => $session->last_activity_at?->diffForHumans() ?? 'agora',
                    ];
                }
            }
        }

        return [
            'online_total'         => count($uniqueSessions),
            'online_authenticated' => count($loggedUserIds) + count($loggedAdminIds),
            'online_anonymous'     => $anonymousCount,
            'online_users_list'    => array_values($onlineUsers),
            'checked_at'           => now()->format('H:i:s'),
        ];
    }

    /** Retorna KPIs consolidados de visitas do sistema */
    public function getVisitsKpis(int $days = 30): array
    {
        $today = today();
        $since = now()->subDays($days);

        // Visitas hoje
        $visitsToday = SystemVisit::whereDate('created_at', $today)->count();
        $uniqueToday = SystemVisit::whereDate('created_at', $today)->where('is_unique_today', true)->count();
        $authToday   = SystemVisit::whereDate('created_at', $today)->where('is_authenticated', true)->count();
        $anonToday   = SystemVisit::whereDate('created_at', $today)->where('is_authenticated', false)->count();

        // Visitas no período
        $visitsPeriod = SystemVisit::where('created_at', '>=', $since)->count();
        $uniquePeriod = SystemVisit::where('created_at', '>=', $since)->where('is_unique_today', true)->count();
        $authPeriod   = SystemVisit::where('created_at', '>=', $since)->where('is_authenticated', true)->count();
        $anonPeriod   = SystemVisit::where('created_at', '>=', $since)->where('is_authenticated', false)->count();

        $authRate = $visitsPeriod > 0 ? round(($authPeriod / $visitsPeriod) * 100, 1) : 0;

        return [
            'visits_today'         => $visitsToday,
            'unique_today'         => $uniqueToday,
            'auth_today'           => $authToday,
            'anon_today'           => $anonToday,
            'visits_period'        => $visitsPeriod,
            'unique_period'        => $uniquePeriod,
            'auth_period'          => $authPeriod,
            'anon_period'          => $anonPeriod,
            'auth_rate_percentage' => $authRate,
        ];
    }

    /** Retorna gráfico diário de visitas e visitantes únicos */
    public function getTrafficByDay(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        return SystemVisit::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_visits, SUM(CASE WHEN is_unique_today = 1 THEN 1 ELSE 0 END) as unique_visits, SUM(CASE WHEN is_authenticated = 1 THEN 1 ELSE 0 END) as auth_visits, SUM(CASE WHEN is_authenticated = 0 THEN 1 ELSE 0 END) as anon_visits')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /** Retorna distribuição por tipo de dispositivo */
    public function getTrafficByDevice(int $days = 30): array
    {
        $since = now()->subDays($days);

        return SystemVisit::where('created_at', '>=', $since)
            ->select('device_type', DB::raw('COUNT(*) as total'))
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    /** Retorna distribuição por navegador */
    public function getTrafficByBrowser(int $days = 30): array
    {
        $since = now()->subDays($days);

        return SystemVisit::where('created_at', '>=', $since)
            ->select('browser', DB::raw('COUNT(*) as total'))
            ->whereNotNull('browser')
            ->where('browser', '!=', '')
            ->groupBy('browser')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->toArray();
    }

    /** Retorna páginas / rotas mais acessadas */
    public function getTopPaths(int $days = 30, int $limit = 10): array
    {
        $since = now()->subDays($days);

        return SystemVisit::where('created_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN is_authenticated = 1 THEN 1 ELSE 0 END) as auth_count'), DB::raw('SUM(CASE WHEN is_authenticated = 0 THEN 1 ELSE 0 END) as anon_count'))
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /** Retorna as últimas visitas em tempo real para o feed ao vivo */
    public function getLiveFeed(int $limit = 15): array
    {
        $visits = SystemVisit::with(['user:id,name,email', 'admin:id,name,email'])
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();

        $fiveMinAgo = now()->subMinutes(5);
        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

        return $visits->map(function ($v) use ($fiveMinAgo, $currentSessionId) {
            $isOnlineNow = $v->last_activity_at && $v->last_activity_at >= $fiveMinAgo;

            $role = 'guest';
            $roleLabel = 'Visitante';
            $userName = null;
            $userEmail = null;

            if ($v->is_authenticated) {
                if ($v->guard === 'admin' || $v->admin_id || ($v->admin && ! $v->user)) {
                    $role = 'admin';
                    $roleLabel = 'Administrador';
                    $userName = $v->admin?->name ?? 'Administrador Master';
                    $userEmail = $v->admin?->email;
                } elseif ($v->user) {
                    $role = 'user';
                    $roleLabel = 'Tenant';
                    $userName = $v->user?->name ?? 'Usuário';
                    $userEmail = $v->user?->email;
                }
            }

            return [
                'id'                 => $v->id,
                'path'               => $v->path,
                'is_authenticated'   => (bool) $v->is_authenticated,
                'user_name'          => $userName,
                'user_email'         => $userEmail,
                'role'               => $role,
                'role_label'         => $roleLabel,
                'session_id'         => $v->session_id,
                'is_current_session' => $v->session_id && $currentSessionId && ($v->session_id === $currentSessionId),
                'ip'                 => $v->ip_address,
                'device'             => $v->device_type,
                'browser'            => $v->browser,
                'platform'           => $v->platform,
                'is_online_now'      => $isOnlineNow,
                'time_formatted'     => $v->created_at?->format('H:i:s') ?? '',
                'time_ago'           => $v->last_activity_at?->diffForHumans() ?? 'agora',
            ];
        })->toArray();
    }
}
