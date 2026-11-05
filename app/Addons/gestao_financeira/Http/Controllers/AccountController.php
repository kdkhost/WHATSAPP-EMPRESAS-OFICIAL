<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\Account;
use App\Addons\gestao_financeira\Models\OpenFinanceConnection;
use App\Addons\gestao_financeira\Services\OpenFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends BaseController
{
    protected function isAjax(): bool
    {
        return request()->expectsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function index()
    {
        $tenantId    = $this->currentTenantId();
        $accounts    = Account::withCount('transactions')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')->get();
        $routePrefix = $this->routePrefix();

        $openFinanceConnection = OpenFinanceConnection::where('tenant_id', $tenantId)->first();
        $isOpenFinanceConfigured = app(OpenFinanceService::class)->isConfigured();
        $canManageOpenFinance = $this->isSuperAdmin();

        return view('gestao-financeira::accounts.index', compact(
            'accounts',
            'routePrefix',
            'openFinanceConnection',
            'isOpenFinanceConfigured',
            'canManageOpenFinance'
        ));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name'            => 'required|string|max:100',
                'type'            => 'required|in:banco,digital,dinheiro,cartao,outro',
                'initial_balance' => 'nullable|numeric',
                'description'     => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
            }
            throw $e;
        }

        $data                    = $request->only(['name', 'type', 'initial_balance', 'description']);
        $data['initial_balance'] = (float) ($data['initial_balance'] ?? 0);
        $data['current_balance'] = $data['initial_balance'];

        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        Account::create($data);

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Conta cadastrada com sucesso!']);
        }

        return $this->redirectTo('accounts.index')->with('success', 'Conta cadastrada com sucesso!');
    }

    public function update(Request $request, Account $account)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $account->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        try {
            $request->validate([
                'name'        => 'required|string|max:100',
                'type'        => 'required|in:banco,digital,dinheiro,cartao,outro',
                'description' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
            }
            throw $e;
        }

        $account->update($request->only(['name', 'type', 'description']));

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Conta atualizada com sucesso!']);
        }

        return $this->redirectTo('accounts.index')->with('success', 'Conta atualizada com sucesso!');
    }

    public function destroy(Account $account)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $account->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $count = $account->transactions()->count();

        if ($count > 0) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => "Não é possível excluir: {$count} lançamento(s) vinculado(s)."], 422);
            }
            return $this->redirectTo('accounts.index')
                ->with('error', "Não é possível excluir esta conta porque existem {$count} transações vinculadas.");
        }

        $account->delete();

        if ($this->isAjax()) {
            return response()->json(['ok' => true]);
        }

        return $this->redirectTo('accounts.index')->with('success', 'Conta excluída com sucesso!');
    }
}
