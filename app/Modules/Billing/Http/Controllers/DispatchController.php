<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingDispatch;
use App\Modules\Billing\Services\BillingMessageHistoryService;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    /** Retorna tenant_id ativo. Null = superadmin (vê tudo). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    public function index(Request $request)
    {
        $tenantId = $this->activeTenantId();

        $q = BillingDispatch::with('invoice.customer')->orderByDesc('scheduled_at');

        // Isolamento por tenant via invoice
        if ($tenantId !== null) {
            $q->whereHas('invoice', fn ($iq) => $iq->where('tenant_id', $tenantId));
        }

        if ($s = $request->query('status')) $q->where('status', $s);
        if ($search = $request->query('q')) {
            $q->whereHas('invoice', fn ($w) => $w
                ->where('number', 'like', "%$search%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$search%")));
        }
        $dispatches = $q->paginate(50)->withQueryString();

        // KPIs escopados ao tenant
        $statsQuery = DB::table('billing_dispatches');
        if ($tenantId !== null) {
            $statsQuery->whereExists(fn ($sub) => $sub
                ->from('invoices')
                ->whereColumn('invoices.id', 'billing_dispatches.invoice_id')
                ->where('invoices.tenant_id', $tenantId));
        }

        $stats = (clone $statsQuery)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status='scheduled' THEN 1 ELSE 0 END) AS scheduled,
            SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed
        ")->first();

        return view('billing::dispatches.index', compact('dispatches', 'stats'));
    }

    public function messageHistory(Request $request, BillingMessageHistoryService $history)
    {
        $tenantId      = $this->activeTenantId();
        $retentionDays = $history->retentionDays();
        $cutoff        = now('America/Sao_Paulo')->subDays($retentionDays)->startOfDay();
        $channel       = $request->query('channel');

        $query = $history->baseQuery($channel)
            ->where('created_at', '>=', $cutoff);

        // Isolamento por tenant
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_whatsapp', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhere('provider_id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($status  = $request->query('status'))  $query->where('status', $status);
        if ($from    = $request->query('from'))    $query->whereDate('created_at', '>=', $from);
        if ($to      = $request->query('to'))      $query->whereDate('created_at', '<=', $to);

        // KPIs globais do período (independentes do canal filtrado)
        $statsBase = $history->baseQuery()
            ->where('created_at', '>=', $cutoff);
        if ($tenantId !== null) {
            $statsBase->where('tenant_id', $tenantId);
        }
        $statsRows = $statsBase
            ->selectRaw('channel, status, COUNT(*) as total')
            ->groupBy('channel', 'status')
            ->get();

        $messages = $query
            ->orderByDesc('created_at')
            ->orderByDesc('source_id')
            ->paginate(25)
            ->withQueryString();

        $totalCount     = (int) $statsRows->sum('total');
        $sentCount      = (int) $statsRows->whereIn('status', ['sent', 'delivered', 'read', 'opened', 'clicked'])->sum('total');
        $deliveredCount = (int) $statsRows->whereIn('status', ['delivered', 'read', 'opened', 'clicked'])->sum('total');
        $failedCount    = (int) $statsRows->whereIn('status', ['failed', 'bounced'])->sum('total');

        $stats = [
            'total'         => $totalCount,
            'whatsapp'      => (int) $statsRows->where('channel', 'whatsapp')->sum('total'),
            'email'         => (int) $statsRows->where('channel', 'email')->sum('total'),
            'sent'          => $sentCount,
            'delivered'     => $deliveredCount,
            'failed'        => $failedCount,
            'success_rate'  => $totalCount > 0 ? round(($sentCount / $totalCount) * 100, 1) : 100,
            'delivery_rate' => $totalCount > 0 ? round(($deliveredCount / $totalCount) * 100, 1) : 0,
        ];

        return view('billing::message-history.index', compact('messages', 'stats', 'retentionDays', 'cutoff'));
    }
}
