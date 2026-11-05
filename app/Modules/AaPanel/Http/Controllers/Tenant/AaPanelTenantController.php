<?php

namespace App\Modules\AaPanel\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;

class AaPanelTenantController extends Controller
{
    public function logs()
    {
        return view('aapanel::tenant.logs', $this->payload('Logs'));
    }

    public function settings()
    {
        return view('aapanel::tenant.settings', $this->payload('Configurações'));
    }

    private function payload(string $currentLabel): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id ?? $user->company_id ?? $user->owner_id ?? null;

        $stats = [
            'accounts' => 0,
            'servers' => 0,
            'plans' => 0,
            'active_accounts' => 0,
        ];

        if (class_exists(\App\Modules\AaPanel\Models\AaPanelAccount::class) && Schema::hasTable('aapanel_accounts')) {
            $query = \App\Modules\AaPanel\Models\AaPanelAccount::query();

            if ($tenantId && Schema::hasColumn('aapanel_accounts', 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            } elseif (Schema::hasColumn('aapanel_accounts', 'customer_id')) {
                $query->where('customer_id', $user->id);
            }

            $stats['accounts'] = (clone $query)->count();
            $stats['active_accounts'] = (clone $query)->where('status', 'active')->count();
        }

        if (class_exists(\App\Modules\AaPanel\Models\AaPanelServer::class) && Schema::hasTable('aapanel_servers')) {
            $stats['servers'] = \App\Modules\AaPanel\Models\AaPanelServer::query()->count();
        }

        if (class_exists(\App\Modules\AaPanel\Models\AaPanelPlan::class) && Schema::hasTable('aapanel_plans')) {
            $stats['plans'] = \App\Modules\AaPanel\Models\AaPanelPlan::query()->where('active', true)->count();
        }

        return [
            'user' => $user,
            'tenantId' => $tenantId,
            'stats' => $stats,
            'currentLabel' => $currentLabel,
        ];
    }
}
