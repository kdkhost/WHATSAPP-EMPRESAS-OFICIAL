<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        $since = now()->subDays(7);

        $stats = [
            'events_total'     => DB::table('security_events')->where('created_at', '>=', $since)->count(),
            'events_blocked'   => DB::table('security_events')->where('action_taken', 'blocked')->where('created_at', '>=', $since)->count(),
            'events_critical'  => DB::table('security_events')->where('severity', 'critical')->where('created_at', '>=', $since)->count(),
            'unique_ips'       => DB::table('security_events')->where('created_at', '>=', $since)->distinct('ip')->count('ip'),
            'blocklist_size'   => DB::table('security_blocklist')->count(),
            'allowlist_size'   => DB::table('security_allowlist')->count(),
            'failed_logins_24h'=> DB::table('security_login_attempts')->where('success', false)->where('created_at', '>=', now()->subDay())->count(),
        ];

        $topRules = DB::table('security_events')
            ->select('rule_code', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('rule_code')->orderByDesc('total')->limit(10)->get();

        $topIps = DB::table('security_events')
            ->select('ip', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('ip')->orderByDesc('total')->limit(10)->get();

        return view('security::dashboard', compact('stats', 'topRules', 'topIps'));
    }

    public function loginAttempts(Request $request)
    {
        $q = DB::table('security_login_attempts')->orderByDesc('id');
        if ($s = $request->query('success')) $q->where('success', $s === '1');
        if ($ip = $request->query('ip'))     $q->where('ip', $ip);
        $attempts = $q->paginate(50)->withQueryString();
        return view('security::login-attempts', compact('attempts'));
    }

    public function realtime(): JsonResponse
    {
        $recent = DB::table('security_events')
            ->orderByDesc('id')->limit(20)
            ->get(['id', 'rule_code', 'severity', 'action_taken', 'ip', 'method', 'uri', 'created_at']);
        return response()->json(['events' => $recent, 'timestamp' => now()->toIso8601String()]);
    }

    public function export(Request $request)
    {
        $days = max(1, min(90, (int) $request->query('days', 7)));
        $since = now()->subDays($days);

        $events = DB::table('security_events')->where('created_at', '>=', $since)->orderBy('id')->get();

        $filename = 'security-events-'.now()->format('Y-m-d').'.csv';
        return response()->stream(function () use ($events) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'data', 'regra', 'severidade', 'acao', 'ip', 'metodo', 'uri', 'user_agent']);
            foreach ($events as $e) {
                fputcsv($out, [$e->id, $e->created_at, $e->rule_code, $e->severity, $e->action_taken, $e->ip, $e->method, $e->uri, $e->user_agent]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
