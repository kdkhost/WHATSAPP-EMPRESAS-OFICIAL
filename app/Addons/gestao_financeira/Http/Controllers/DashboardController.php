<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\Account;
use App\Addons\gestao_financeira\Models\Transaction;

class DashboardController extends BaseController
{
    public function index()
    {
        $tenantId   = $this->currentTenantId();
        $today      = now();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd   = $today->copy()->endOfMonth();

        try {
            $base = fn () => Transaction::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

            $receitasRecebidas = $base()->where('type', 'receita')->where('status', 'pago')
                ->whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount');

            $receitasPendentes = $base()->where('type', 'receita')->where('status', 'pendente')
                ->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount');

            $despesasPagas = $base()->where('type', 'despesa')->where('status', 'pago')
                ->whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount');

            $despesasPendentes = $base()->where('type', 'despesa')->where('status', 'pendente')
                ->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount');

            $saldoMes = $receitasRecebidas - $despesasPagas;

            $saldoContas = Account::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->sum('current_balance');

            $contas = Account::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderBy('name')->get();

            $recentTransactions = $base()->with(['category', 'account'])
                ->orderByDesc('created_at')->limit(8)->get();

            $upcomingTransactions = $base()->with(['category', 'account'])
                ->where('status', 'pendente')
                ->whereBetween('due_date', [
                    $today->toDateString(),
                    $today->copy()->addDays(7)->toDateString(),
                ])
                ->orderBy('due_date')->get();

            $chartData = $this->getMonthlyFlowData($tenantId);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[gestao-financeira] DashboardController: ' . $e->getMessage());
            $receitasRecebidas = $receitasPendentes = $despesasPagas = $despesasPendentes = $saldoMes = $saldoContas = 0;
            $contas = $recentTransactions = $upcomingTransactions = collect();
            $chartData = ['labels' => [], 'receitas' => [], 'despesas' => []];
        }

        $routePrefix = $this->routePrefix();

        $categoriesForJs = \App\Addons\gestao_financeira\Models\Category::when(
            $tenantId,
            fn ($q) => $q->where('tenant_id', $tenantId)
        )->get(['id', 'name', 'type', 'color'])->toArray();

        return view('gestao-financeira::dashboard', compact(
            'receitasRecebidas', 'receitasPendentes',
            'despesasPagas', 'despesasPendentes',
            'saldoMes', 'saldoContas',
            'contas', 'recentTransactions', 'upcomingTransactions',
            'chartData', 'routePrefix',
            'categoriesForJs'
        ));
    }

    protected function getMonthlyFlowData(?int $tenantId): array
    {
        $months = $incomeData = $expenseData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth()->toDateString();
            $end   = $date->copy()->endOfMonth()->toDateString();

            $months[] = $date->translatedFormat('M/Y');

            $incomeData[] = (float) Transaction::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('type', 'receita')->where('status', 'pago')
                ->whereBetween('payment_date', [$start, $end])->sum('amount');

            $expenseData[] = (float) Transaction::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('type', 'despesa')->where('status', 'pago')
                ->whereBetween('payment_date', [$start, $end])->sum('amount');
        }

        return ['labels' => $months, 'receitas' => $incomeData, 'despesas' => $expenseData];
    }
}
