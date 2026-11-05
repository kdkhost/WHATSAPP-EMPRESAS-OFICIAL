<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController
{
    public function index()
    {
        $routePrefix = $this->routePrefix();
        return view('gestao-financeira::reports.index', compact('routePrefix'));
    }

    public function cashFlow(Request $request)
    {
        $tenantId = $this->currentTenantId();
        $ano      = (int) $request->input('year', now()->year);

        $rawTransactions = Transaction::select(
            DB::raw('MONTH(due_date) as mes'),
            'type',
            'status',
            DB::raw('SUM(amount) as total')
        )
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereYear('due_date', $ano)
            ->groupBy('mes', 'type', 'status')
            ->get();

        $monthsData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthsData[$month] = [
                'receitas_pagas'     => 0,
                'receitas_pendentes' => 0,
                'despesas_pagas'     => 0,
                'despesas_pendentes' => 0,
            ];
        }

        foreach ($rawTransactions as $row) {
            $month = (int) $row->mes;
            if ($row->type === 'receita') {
                $monthsData[$month][$row->status === 'pago' ? 'receitas_pagas' : 'receitas_pendentes'] = (float) $row->total;
            } else {
                $monthsData[$month][$row->status === 'pago' ? 'despesas_pagas' : 'despesas_pendentes'] = (float) $row->total;
            }
        }

        $routePrefix = $this->routePrefix();
        return view('gestao-financeira::reports.fluxo-caixa', compact('monthsData', 'ano', 'routePrefix'));
    }

    public function dre(Request $request)
    {
        $tenantId  = $this->currentTenantId();
        $ano       = (int) $request->input('year', now()->year);
        $startDate = "{$ano}-01-01";
        $endDate   = "{$ano}-12-31";

        $receitasPorCategoria = DB::table('addon_gestao_financeira_transactions as t')
            ->join('addon_gestao_financeira_categories as c', 't.category_id', '=', 'c.id')
            ->select('c.name', DB::raw('SUM(t.amount) as total'))
            ->where('t.type', 'receita')
            ->where('t.status', 'pago')
            ->whereBetween('t.payment_date', [$startDate, $endDate])
            ->when($tenantId, fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->groupBy('c.name')
            ->orderByDesc('total')
            ->get();

        $despesasPorCategoria = DB::table('addon_gestao_financeira_transactions as t')
            ->join('addon_gestao_financeira_categories as c', 't.category_id', '=', 'c.id')
            ->select('c.name', DB::raw('SUM(t.amount) as total'))
            ->where('t.type', 'despesa')
            ->where('t.status', 'pago')
            ->whereBetween('t.payment_date', [$startDate, $endDate])
            ->when($tenantId, fn ($q) => $q->where('t.tenant_id', $tenantId))
            ->groupBy('c.name')
            ->orderByDesc('total')
            ->get();

        $totalReceitas = $receitasPorCategoria->sum('total');
        $totalDespesas = $despesasPorCategoria->sum('total');
        $lucroLiquido  = $totalReceitas - $totalDespesas;
        $margemLiquida = $totalReceitas > 0 ? ($lucroLiquido / $totalReceitas) * 100 : 0;
        $routePrefix   = $this->routePrefix();

        return view('gestao-financeira::reports.dre', compact(
            'receitasPorCategoria', 'despesasPorCategoria',
            'totalReceitas', 'totalDespesas',
            'lucroLiquido', 'margemLiquida',
            'ano', 'routePrefix'
        ));
    }
}
