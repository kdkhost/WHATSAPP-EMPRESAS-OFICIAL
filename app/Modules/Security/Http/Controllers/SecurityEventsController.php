<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SecurityEventsController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('security_events')->orderByDesc('created_at');

        if ($rule = $request->query('rule'))         $q->where('rule_code', $rule);
        if ($severity = $request->query('severity')) $q->where('severity', $severity);
        if ($action = $request->query('action'))     $q->where('action_taken', $action);
        if ($ip = $request->query('ip'))             $q->where('ip', $ip);
        if ($from = $request->query('from'))         $q->where('created_at', '>=', $from . ' 00:00:00');
        if ($to = $request->query('to'))             $q->where('created_at', '<=', $to . ' 23:59:59');

        $events = $q->paginate(50)->withQueryString();

        // KPIs (24h)
        $since24h = Carbon::now()->subDay();
        $stats = [
            'total_24h'    => DB::table('security_events')->where('created_at', '>=', $since24h)->count(),
            'blocked_24h'  => DB::table('security_events')->where('created_at', '>=', $since24h)->where('action_taken', 'blocked')->count(),
            'critical_24h' => DB::table('security_events')->where('created_at', '>=', $since24h)->where('severity', 'critical')->count(),
            'unique_ips_24h' => DB::table('security_events')->where('created_at', '>=', $since24h)->distinct('ip')->count('ip'),
        ];

        // Listas pra filtros
        $rules = DB::table('security_events')
            ->selectRaw('rule_code, COUNT(*) as total')
            ->groupBy('rule_code')
            ->orderByDesc('total')
            ->limit(20)
            ->pluck('total', 'rule_code');

        return view('security::events', compact('events', 'stats', 'rules'));
    }
}
