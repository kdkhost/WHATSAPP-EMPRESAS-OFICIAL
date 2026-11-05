<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard.index');
    }

    /** Endpoint AJAX para KPIs em tempo real */
    public function kpis(\App\Services\SystemVisitAnalyticsService $visitService): JsonResponse
    {
        try {
            $now = now();
            $today = today();

            $onlineStats = ['online_total' => 0, 'online_authenticated' => 0, 'online_anonymous' => 0];
            $visitKpis = ['visits_today' => 0, 'unique_today' => 0, 'visits_period' => 0];
            try {
                $onlineStats = $visitService->getOnlineStats(5);
                $visitKpis = $visitService->getVisitsKpis(30);
            } catch (\Throwable $e) {
                Log::warning("[DashboardController] Falha ao coletar estatísticas de visita: " . $e->getMessage());
            }

            // === FINANCEIRO ===
            $invoicesPending = $this->count('invoices', ['status' => 'pending']);
            $invoicesOverdue = $this->count('invoices', ['status' => 'overdue']);
            $invoicesPaid30d = (int) (DB::table('invoices')->where('status', 'paid')->where('paid_at', '>=', $now->copy()->subDays(30))->count() ?? 0);
            $revenuePaid30d = (float) (DB::table('invoices')->where('status', 'paid')->where('paid_at', '>=', $now->copy()->subDays(30))->sum('amount') ?? 0);
            $revenueToday = (float) (DB::table('invoices')->where('status', 'paid')->whereDate('paid_at', $today)->sum('amount') ?? 0);
            $invoicesTotal = $this->count('invoices');
            $customersTotal = $this->count('customers');
            $customersActive = $this->count('customers', ['active' => 1]);
            $ticketMedio = $invoicesPaid30d > 0 ? $revenuePaid30d / $invoicesPaid30d : 0;

            // === WHATSAPP ===
            $msgSentToday = (int) (DB::table('whatsapp_messages')->where('status', 'sent')->whereDate('created_at', $today)->count() ?? 0);
            $msgFailedToday = (int) (DB::table('whatsapp_messages')->where('status', 'failed')->whereDate('created_at', $today)->count() ?? 0);
            $msgDelivered24h = (int) (DB::table('whatsapp_messages')->where('status', 'delivered')->where('created_at', '>=', $now->copy()->subDay())->count() ?? 0);
            $msgTotal = $this->count('whatsapp_messages');
            $dispatchesToday = (int) (DB::table('billing_dispatches')->where('status', 'sent')->whereDate('sent_at', $today)->count() ?? 0);
            $dispatchesPending = (int) (DB::table('billing_dispatches')->where('status', 'scheduled')->where('scheduled_at', '<=', $now)->count() ?? 0);
            $taxaEntrega = ($msgSentToday + $msgFailedToday) > 0
                ? round(($msgSentToday / ($msgSentToday + $msgFailedToday)) * 100, 1) : 100;

            // === SEGURANCA ===
            $blocks24h = (int) (DB::table('security_events')->where('action_taken', 'blocked')->where('created_at', '>=', $now->copy()->subDay())->count() ?? 0);
            $loginAttempts24h = (int) (DB::table('security_login_attempts')->where('created_at', '>=', $now->copy()->subDay())->count() ?? 0);
            $loginFailed24h = (int) (DB::table('security_login_attempts')->where('success', false)->where('created_at', '>=', $now->copy()->subDay())->count() ?? 0);

            // === TENANCY ===
            $tenantsTotal = $this->count('tenants');
            $tenantsActive = $this->count('tenants', ['active' => 1]);
            $instancesConnected = $this->count('tenant_instances', ['status' => 'connected']);
            $instancesTotal = $this->count('tenant_instances');

            // === SISTEMA ===
            $sessionsActive = (int) (DB::table('sessions')->where('last_activity', '>=', $now->timestamp - 1800)->count() ?? 0);
            $jobsPending = $this->count('jobs');
            $jobsFailed = $this->count('failed_jobs');
            $uploadsSize = (float) (DB::table('uploads')->sum('size_bytes') ?? 0) / 1048576; // MB

            // === ULTIMAS ATIVIDADES ===
            $recentInvoices = DB::table('invoices')
                ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                ->leftJoin('tenants', 'invoices.tenant_id', '=', 'tenants.id')
                ->select(
                    'invoices.id',
                    'invoices.number',
                    'invoices.amount',
                    'invoices.status',
                    'invoices.due_date',
                    'invoices.paid_at',
                    'invoices.description',
                    'invoices.payment_url',
                    'invoices.created_at',
                    DB::raw("COALESCE(customers.name, 'Cliente Avulso') as customer"),
                    'customers.whatsapp as customer_whatsapp',
                    'customers.email as customer_email',
                    'tenants.company_name as tenant_name'
                )
                ->orderByDesc('invoices.created_at')
                ->limit(8)
                ->get();

            $recentMessages = DB::table('whatsapp_messages')
                ->select(
                    'id',
                    'to',
                    'message',
                    'status',
                    'created_at',
                    'sent_at',
                    'response'
                )
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            return response()->json([
                'financial' => [
                    'revenue_today' => $revenueToday,
                    'revenue_30d' => $revenuePaid30d,
                    'invoices_pending' => $invoicesPending,
                    'invoices_overdue' => $invoicesOverdue,
                    'invoices_paid_30d' => $invoicesPaid30d,
                    'invoices_total' => $invoicesTotal,
                    'customers_total' => $customersTotal,
                    'customers_active' => $customersActive,
                    'ticket_medio' => $ticketMedio,
                ],
                'whatsapp' => [
                    'sent_today' => $msgSentToday,
                    'failed_today' => $msgFailedToday,
                    'delivered_24h' => $msgDelivered24h,
                    'total' => $msgTotal,
                    'dispatches_today' => $dispatchesToday,
                    'dispatches_pending' => $dispatchesPending,
                    'taxa_entrega' => $taxaEntrega,
                ],
                'security' => [
                    'blocks_24h' => $blocks24h,
                    'login_attempts_24h' => $loginAttempts24h,
                    'login_failed_24h' => $loginFailed24h,
                ],
                'tenancy' => [
                    'tenants_total' => $tenantsTotal,
                    'tenants_active' => $tenantsActive,
                    'instances_connected' => $instancesConnected,
                    'instances_total' => $instancesTotal,
                ],
                'system' => [
                    'sessions_active' => $sessionsActive,
                    'jobs_pending' => $jobsPending,
                    'jobs_failed' => $jobsFailed,
                    'uploads_mb' => round($uploadsSize, 1),
                    'php' => phpversion(),
                    'laravel' => app()->version(),
                ],
                'visits' => [
                    'online_now'           => $onlineStats['online_total'] ?? 0,
                    'online_authenticated' => $onlineStats['online_authenticated'] ?? 0,
                    'online_anonymous'     => $onlineStats['online_anonymous'] ?? 0,
                    'visits_today'         => $visitKpis['visits_today'] ?? 0,
                    'unique_today'         => $visitKpis['unique_today'] ?? 0,
                    'visits_month'         => $visitKpis['visits_period'] ?? 0,
                ],
                'recent_invoices' => $recentInvoices,
                'recent_messages' => $recentMessages,
                'timestamp' => $now->format('H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error("[DashboardController] Erro crítico no endpoint de KPIs: " . $e->getMessage());
            return response()->json([
                'financial' => [
                    'revenue_today' => 0, 'revenue_30d' => 0, 'invoices_pending' => 0,
                    'invoices_overdue' => 0, 'invoices_paid_30d' => 0, 'invoices_total' => 0,
                    'customers_total' => 0, 'customers_active' => 0, 'ticket_medio' => 0,
                ],
                'whatsapp' => [
                    'sent_today' => 0, 'failed_today' => 0, 'delivered_24h' => 0,
                    'total' => 0, 'dispatches_today' => 0, 'dispatches_pending' => 0,
                    'taxa_entrega' => 100,
                ],
                'security' => ['blocks_24h' => 0, 'login_attempts_24h' => 0, 'login_failed_24h' => 0],
                'tenancy' => ['tenants_total' => 0, 'tenants_active' => 0, 'instances_connected' => 0, 'instances_total' => 0],
                'system' => ['sessions_active' => 0, 'jobs_pending' => 0, 'jobs_failed' => 0, 'uploads_mb' => 0, 'php' => phpversion(), 'laravel' => app()->version()],
                'visits' => ['online_now' => 0, 'online_authenticated' => 0, 'online_anonymous' => 0, 'visits_today' => 0, 'unique_today' => 0, 'visits_month' => 0],
                'recent_invoices' => [],
                'recent_messages' => [],
                'timestamp' => now()->format('H:i:s'),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function count(string $table, array $where = []): int
    {
        try {
            return DB::table($table)->where($where)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
