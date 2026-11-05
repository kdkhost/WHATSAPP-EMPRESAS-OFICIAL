<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint que alimenta os graficos do painel (Chart.js).
 * "Graphify" = camada de visualizacao grafica do modulo de seguranca.
 */
class SecurityChartsController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $days = max(1, min(30, (int) $request->query('days', 7)));
        $since = now()->subDays($days);

        // Eventos por dia (linha)
        $byDay = DB::table('security_events')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total, SUM(action_taken="blocked") as blocked')
            ->where('created_at', '>=', $since)
            ->groupBy('d')->orderBy('d')->get();

        // Eventos por hora (24 ultimas horas)
        $byHour = DB::table('security_events')
            ->selectRaw('HOUR(created_at) as h, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('h')->orderBy('h')->get();

        // Distribuicao por regra (donut)
        $byRule = DB::table('security_events')
            ->select('rule_code', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('rule_code')->orderByDesc('total')->get();

        // Distribuicao por severidade (donut)
        $bySeverity = DB::table('security_events')
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('severity')->get();

        return response()->json([
            'period'     => ['days' => $days, 'since' => $since->toIso8601String()],
            'by_day'     => $byDay,
            'by_hour'    => $byHour,
            'by_rule'    => $byRule,
            'by_severity'=> $bySeverity,
        ]);
    }
}
