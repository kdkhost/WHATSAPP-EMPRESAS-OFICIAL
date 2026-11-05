<?php

namespace App\Modules\Marketplace\Http\Controllers\Tenant;

use App\Addons\gestao_financeira\Models\Account;
use App\Addons\gestao_financeira\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Services\AddonMarketplaceService;
use App\Modules\Tenancy\Models\Tenant;
use App\Services\AddonManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class TenantAddonController extends Controller
{
    public function __construct(
        protected AddonMarketplaceService $marketplace,
        protected AddonManagerService $addons,
    ) {}

    protected function tenant(): Tenant
    {
        $tenant = Tenant::query()->where('user_id', Auth::id())->first();

        abort_if(! $tenant, 403, 'Voce nao possui tenant ativo.');

        return $tenant;
    }

    public function show(string $slug, string $page = 'index'): View|RedirectResponse
    {
        $tenant = $this->tenant();

        if (! $this->marketplace->tenantHasAddon($tenant, $slug)) {
            abort(403, 'Voce nao possui acesso a este addon.');
        }

        // Módulos do sistema têm controllers/views próprios — redirecionar para suas rotas de tenant
        $systemModuleRoutes = [
            'whmcs'   => 'tenant.whmcs.index',
            'cpanel'  => 'tenant.cpanel.index',
            'aapanel' => 'painel.aapanel.dashboard',
        ];

        if (isset($systemModuleRoutes[$slug]) && Route::has($systemModuleRoutes[$slug])) {
            return redirect()->route($systemModuleRoutes[$slug]);
        }

        $addon = $this->addons->findBySlug($slug);
        if (! $addon) {
            abort(404, 'Addon nao encontrado no sistema.');
        }

        $viewNamespace = str_replace('_', '-', $slug);
        $routePrefix = 'tenant.' . str_replace('-', '_', $slug);

        $addonDir = $addon['path'] ?? null;
        if ($addonDir && is_dir($addonDir . '/Resources/views')) {
            \Illuminate\Support\Facades\View::addLocation($addonDir . '/Resources/views');
            \Illuminate\Support\Facades\View::addNamespace($viewNamespace, $addonDir . '/Resources/views');
        }

        // Se for gestao-financeira, prepara dados financeiros diretamente
        if ($slug === 'gestao-financeira' && $page === 'index') {
            $data = $this->prepareFinanceDashboard($tenant);
            $data['routePrefix'] = $routePrefix;
            $data['slug'] = $slug;

            return view('gestao-financeira::dashboard', $data);
        }

        $commonData = [
            'tenant' => $tenant,
            'addon' => $addon,
            'routePrefix' => $routePrefix,
            'module' => [
                'slug' => $slug,
                'name' => $addon['name'],
                'description' => $addon['description'],
                'version' => $addon['version'],
                'icon' => $addon['icon'] ?? 'bi-puzzle',
            ],
        ];

        $defaultView = $viewNamespace . '::' . $page;
        if (view()->exists($defaultView)) {
            return view($defaultView, $commonData);
        }

        if ($page === 'index') {
            $dashboardView = $viewNamespace . '::dashboard';
            if (view()->exists($dashboardView)) {
                return view($dashboardView, $commonData);
            }
        }

        return view('marketplace::tenant.addon-page', $commonData + ['page' => $page]);
    }

    protected function prepareFinanceDashboard(Tenant $tenant): array
    {
        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        try {
            $receitasRecebidas = Transaction::where('type', 'receita')
                ->where('status', 'pago')
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $receitasPendentes = Transaction::where('type', 'receita')
                ->where('status', 'pendente')
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $despesasPagas = Transaction::where('type', 'despesa')
                ->where('status', 'pago')
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $despesasPendentes = Transaction::where('type', 'despesa')
                ->where('status', 'pendente')
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $saldoMes = $receitasRecebidas - $despesasPagas;
            $saldoContas = Account::sum('current_balance');
            $contas = Account::orderBy('name')->get();

            $recentTransactions = Transaction::with(['category', 'account'])
                ->orderBy('created_at', 'desc')
                ->limit(8)
                ->get();

            $upcomingTransactions = Transaction::with(['category', 'account'])
                ->where('status', 'pendente')
                ->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                ->orderBy('due_date', 'asc')
                ->get();

            $chartData = $this->getMonthlyFlowData();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('TenantAddonController: erro ao preparar dados financeiros: ' . $e->getMessage());

            $receitasRecebidas = 0;
            $receitasPendentes = 0;
            $despesasPagas = 0;
            $despesasPendentes = 0;
            $saldoMes = 0;
            $saldoContas = 0;
            $contas = collect();
            $recentTransactions = collect();
            $upcomingTransactions = collect();
            $chartData = ['labels' => [], 'receitas' => [], 'despesas' => []];
        }

        return compact(
            'receitasRecebidas', 'receitasPendentes', 'despesasPagas', 'despesasPendentes',
            'saldoMes', 'saldoContas', 'contas', 'recentTransactions', 'upcomingTransactions', 'chartData'
        );
    }

    protected function getMonthlyFlowData(): array
    {
        $months = [];
        $incomeData = [];
        $expenseData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('M/Y');
            $start = $date->copy()->startOfMonth()->toDateString();
            $end = $date->copy()->endOfMonth()->toDateString();

            $incomeData[] = Transaction::where('type', 'receita')
                ->where('status', 'pago')
                ->whereBetween('payment_date', [$start, $end])
                ->sum('amount');

            $expenseData[] = Transaction::where('type', 'despesa')
                ->where('status', 'pago')
                ->whereBetween('payment_date', [$start, $end])
                ->sum('amount');
        }

        return [
            'labels' => $months,
            'receitas' => $incomeData,
            'despesas' => $expenseData,
        ];
    }
}
