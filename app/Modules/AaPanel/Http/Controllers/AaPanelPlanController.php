<?php

namespace App\Modules\AaPanel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AaPanel\Http\Requests\StoreAaPanelPlanRequest;
use App\Modules\AaPanel\Models\AaPanelPlan;
use App\Modules\AaPanel\Models\AaPanelServer;

class AaPanelPlanController extends Controller
{
    protected function currentTenantId(): ?int
    {
        if (request()->is('painel') || request()->is('painel/*')) {
            $user = auth('web')->user();
            if (! $user) {
                return null;
            }
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            return $tenant?->id;
        }

        if (auth('admin')->check()) {
            return null;
        }

        $user = auth('web')->user();
        if (! $user) {
            return null;
        }

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
        $plans = AaPanelPlan::with('server');
        $this->scopeByTenant($plans);

        $servers = AaPanelServer::query();
        $this->scopeByTenant($servers);

        return view('aapanel::plans.index', [
            'plans' => $plans->latest()->paginate(20),
            'servers' => $servers->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAaPanelPlanRequest $request)
    {
        $data = $request->validated();
        $data['ssl_enabled'] = (bool) ($data['ssl_enabled'] ?? false);
        $data['active'] = (bool) ($data['active'] ?? true);
        $data['tenant_id'] = $this->currentTenantId();

        $plan = AaPanelPlan::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Plano aaPanel criado com sucesso.',
            'data' => $plan,
        ]);
    }

    public function update(StoreAaPanelPlanRequest $request, AaPanelPlan $plan)
    {
        if ($this->currentTenantId() !== null && $plan->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }

        $data = $request->validated();
        $data['ssl_enabled'] = (bool) ($data['ssl_enabled'] ?? false);
        $data['active'] = (bool) ($data['active'] ?? true);

        $plan->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Plano atualizado com sucesso.',
        ]);
    }

    public function destroy(AaPanelPlan $plan)
    {
        if ($this->currentTenantId() !== null && $plan->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }

        if ($plan->accounts()->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'Nao e possivel remover plano com contas vinculadas.',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Plano removido com sucesso.',
        ]);
    }
}
