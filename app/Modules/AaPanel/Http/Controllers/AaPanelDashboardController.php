<?php

namespace App\Modules\AaPanel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AaPanel\Models\AaPanelAccount;
use App\Modules\AaPanel\Models\AaPanelOperationLog;
use App\Modules\AaPanel\Models\AaPanelPlan;
use App\Modules\AaPanel\Models\AaPanelServer;

class AaPanelDashboardController extends Controller
{
    protected function currentTenantId(): ?int
    {
        if (request()->is('painel') || request()->is('painel/*')) {
            $user = auth('web')->user();
            if (! $user) return null;
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            return $tenant?->id;
        }
        if (auth('admin')->check()) { return null; }
        $user = auth('web')->user();
        if (! $user) { return null; }
        $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
        return $tenant?->id;
    }

    protected function scopeByTenant($query)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) { $query->where('tenant_id', $tenantId); }
        return $query;
    }

    public function index()
    {
        $servers = AaPanelServer::query();
        $this->scopeByTenant($servers);
        
        $plans = AaPanelPlan::query();
        $this->scopeByTenant($plans);
        
        $accounts = AaPanelAccount::query();
        $this->scopeByTenant($accounts);

        $activeAccounts = AaPanelAccount::where('status', 'active');
        $this->scopeByTenant($activeAccounts);

        $logsQuery = AaPanelOperationLog::latest()->limit(10);
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            // Filtra diretamente por tenant_id na tabela de logs (campo adicionado na migration)
            $logsQuery->where('tenant_id', $tenantId);
        }

        return view('aapanel::dashboard', [
            'servers' => $servers->count(),
            'plans' => $plans->count(),
            'accounts' => $accounts->count(),
            'activeAccounts' => $activeAccounts->count(),
            'logs' => $logsQuery->get(),
        ]);
    }
}
