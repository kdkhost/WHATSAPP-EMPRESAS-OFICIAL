<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use App\Services\SessionManagerService;
use App\Services\SystemVisitAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /** Tenant_id ativo. Null = superadmin (vê tudo). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    public function index(SessionManagerService $sessions)
    {
        $concurrentAllowed = $sessions->isConcurrentSessionsAllowed();
        return view('analytics::dashboard', compact('concurrentAllowed'));
    }

    public function data(Request $request, SystemVisitAnalyticsService $visits, SessionManagerService $sessions): JsonResponse
    {
        $days  = max(1, min(365, (int) $request->query('days', 30)));
        $since = now()->subDays($days);

        return response()->json([
            'period'                     => ['days' => $days, 'since' => $since->toIso8601String()],
            'revenue'                    => $this->revenueByDay($since),
            'invoices'                   => $this->invoicesByStatus($since),
            'dispatches'                 => $this->dispatchesByDay($since),
            'top_clients'                => $this->topClients($since),
            'kpis'                       => $this->kpis($since),
            'online_stats'               => $visits->getOnlineStats(5),
            'visits_kpis'                => $visits->getVisitsKpis($days),
            'visits_traffic'             => $visits->getTrafficByDay($days),
            'visits_devices'             => $visits->getTrafficByDevice($days),
            'visits_browsers'            => $visits->getTrafficByBrowser($days),
            'top_paths'                  => $visits->getTopPaths($days, 8),
            'live_feed'                  => $visits->getLiveFeed(15),
            'concurrent_sessions_allowed'=> $sessions->isConcurrentSessionsAllowed(),
            'current_session_id'         => $request->hasSession() ? $request->session()->getId() : null,
        ]);
    }

    /** Endpoint de alta velocidade para atualização ao vivo dos visitantes */
    public function liveVisitors(Request $request, SystemVisitAnalyticsService $visits, SessionManagerService $sessions): JsonResponse
    {
        return response()->json([
            'online'                     => $visits->getOnlineStats(5),
            'visits_today'               => $visits->getVisitsKpis(1),
            'live_feed'                  => $visits->getLiveFeed(10),
            'concurrent_sessions_allowed'=> $sessions->isConcurrentSessionsAllowed(),
            'current_session_id'         => $request->hasSession() ? $request->session()->getId() : null,
            'timestamp'                  => now()->format('H:i:s'),
        ]);
    }

    /** Desconecta uma sessão específica */
    public function terminateSession(Request $request, SessionManagerService $sessions): JsonResponse
    {
        $sessionId = trim((string) $request->input('session_id'));

        if (empty($sessionId)) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador da sessão não fornecido.',
            ], 422);
        }

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        if ($sessionId === $currentSessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Você não pode desconectar a sua sessão atual por este botão. Para sair, use o botão de Logout.',
            ], 400);
        }

        $ok = $sessions->terminateSession($sessionId, 'Desconectado manualmente pelo administrador em tempo real.');

        if ($ok) {
            return response()->json([
                'success' => true,
                'message' => 'Sessão desconectada com sucesso. O dispositivo foi deslogado.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Não foi possível encerrar a sessão informada.',
        ], 500);
    }

    /** Desconecta todas as outras sessões ativas com exceção da atual */
    public function terminateOtherSessions(Request $request, SessionManagerService $sessions): JsonResponse
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : '';
        $terminatedCount = $sessions->terminateAllOtherSessionsExcept($currentSessionId);

        return response()->json([
            'success'          => true,
            'terminated_count' => $terminatedCount,
            'message'          => $terminatedCount > 0
                ? "{$terminatedCount} sessões ativas foram desconectadas imediatamente."
                : "Nenhuma outra sessão externa ativa foi encontrada no momento.",
        ]);
    }

    /** Alterna a política de sessões simultâneas */
    public function toggleConcurrentSessions(Request $request, SessionManagerService $sessions): JsonResponse
    {
        $allowed = $request->boolean('allowed');
        $sessions->setConcurrentSessionsAllowed($allowed);

        // Se o modo de sessão única foi ativado agora, desconecta outras sessões existentes do admin logado
        if (! $allowed) {
            $adminId = Auth::guard('admin')->id();
            if ($adminId && $request->hasSession()) {
                $sessions->terminateOtherSessions($adminId, 'admin', $request->session()->getId());
            }
        }

        return response()->json([
            'success' => true,
            'allowed' => $allowed,
            'message' => $allowed
                ? 'Múltiplas conexões simultâneas permitidas.'
                : 'Modo de Sessão Única ativado! Outras máquinas e sessões foram bloqueadas.',
        ]);
    }

    protected function kpis($since): array
    {
        $tenantId = $this->activeTenantId();

        $base = DB::table('invoices')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        $totalRevenue  = (clone $base)->where('status', 'paid')->where('paid_at', '>=', $since)->sum('amount');
        $totalInvoices = (clone $base)->where('created_at', '>=', $since)->count();
        $paidInvoices  = (clone $base)->where('status', 'paid')->where('paid_at', '>=', $since)->count();
        $overdue       = (clone $base)->where('status', 'overdue')->count();

        $conversionRate = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 2) : 0;

        $msgBase = DB::table('whatsapp_messages')
            ->when($tenantId !== null, fn ($q) => $q->whereExists(fn ($sub) => $sub
                ->from('billing_dispatches')
                ->join('invoices', 'invoices.id', '=', 'billing_dispatches.invoice_id')
                ->whereColumn('billing_dispatches.id', 'whatsapp_messages.dispatch_id')
                ->where('invoices.tenant_id', $tenantId)));

        $messagesSent = (clone $msgBase)->where('status', 'sent')->where('created_at', '>=', $since)->count();

        return [
            'total_revenue'   => (float) $totalRevenue,
            'total_invoices'  => $totalInvoices,
            'paid_invoices'   => $paidInvoices,
            'overdue'         => $overdue,
            'conversion_rate' => $conversionRate,
            'messages_sent'   => $messagesSent,
        ];
    }

    protected function revenueByDay($since): array
    {
        $tenantId = $this->activeTenantId();
        return DB::table('invoices')
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as total')
            ->where('status', 'paid')
            ->where('paid_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('d')->orderBy('d')->get()->toArray();
    }

    protected function invoicesByStatus($since): array
    {
        $tenantId = $this->activeTenantId();
        return DB::table('invoices')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('status')->get()->toArray();
    }

    protected function dispatchesByDay($since): array
    {
        $tenantId = $this->activeTenantId();
        $q = DB::table('billing_dispatches')
            ->selectRaw('DATE(billing_dispatches.created_at) as d, billing_dispatches.status, COUNT(*) as total')
            ->where('billing_dispatches.created_at', '>=', $since);

        if ($tenantId !== null) {
            $q->join('invoices', 'invoices.id', '=', 'billing_dispatches.invoice_id')
              ->where('invoices.tenant_id', $tenantId);
        }

        return $q->groupBy('d', 'billing_dispatches.status')->orderBy('d')->get()->toArray();
    }

    protected function topClients($since): array
    {
        $tenantId = $this->activeTenantId();
        return DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->select('customers.name', DB::raw('SUM(invoices.amount) as total'), DB::raw('COUNT(invoices.id) as invoices'))
            ->where('invoices.status', 'paid')
            ->where('invoices.paid_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()->toArray();
    }
}
