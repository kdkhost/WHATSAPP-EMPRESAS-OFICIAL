<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrmController extends Controller
{
    /** Retorna tenant_id quando o admin acessa em modo assistido; null = superadmin vê tudo. */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            return \App\Modules\Tenancy\Models\Tenant::where('user_id', $user?->id)->value('id');
        }
        return null;
    }

    public function index()
    {
        $tenantId = $this->activeTenantId();

        $base     = $tenantId ? DB::table('customers')->where('tenant_id', $tenantId) : DB::table('customers');
        $invBase  = $tenantId ? DB::table('invoices')->where('tenant_id', $tenantId) : DB::table('invoices');

        $totals = [
            'customers'        => (clone $base)->count(),
            'customers_active' => (clone $base)->where('active', true)->count(),
            'invoices'         => (clone $invBase)->count(),
            'paid'             => (clone $invBase)->where('status', 'paid')->count(),
            'pending'          => (clone $invBase)->where('status', 'pending')->count(),
            'overdue'          => (clone $invBase)->where('status', 'overdue')->count(),
            'revenue_30d'      => (float) (clone $invBase)->where('status', 'paid')->where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'new_customers_30d'=> (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        $recentCustomers = (clone $base)->orderByDesc('id')->limit(5)->get();

        $invQ = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->select('invoices.*', 'customers.name as customer_name')
            ->orderByDesc('invoices.id')
            ->limit(5);
        if ($tenantId) $invQ->where('invoices.tenant_id', $tenantId);
        $recentInvoices = $invQ->get();

        return view('admin.crm.index', compact('totals', 'recentCustomers', 'recentInvoices'));
    }

    public function contacts()
    {
        $tenantId  = $this->activeTenantId();
        $query     = Customer::orderByDesc('id');
        if ($tenantId) $query->where('tenant_id', $tenantId);
        if ($q = trim((string) request('q'))) {
            $query->where(function($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('document', 'like', "%{$q}%")
                  ->orWhere('whatsapp', 'like', "%{$q}%")
                  ->orWhere('cidade', 'like', "%{$q}%");
            });
        }
        $customers = $query->paginate(50)->withQueryString();

        $base = $tenantId ? Customer::where('tenant_id', $tenantId) : Customer::query();
        $stats = [
            'total'          => (clone $base)->count(),
            'active'         => (clone $base)->where('active', true)->count(),
            'with_whatsapp'  => (clone $base)->whereNotNull('whatsapp')->where('whatsapp', '!=', '')->count(),
            'new_30d'        => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return view('admin.crm.contacts', compact('customers', 'stats'));
    }

    public function funnel() { return view('admin.crm.funnel'); }
}
