<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\Account;
use App\Addons\gestao_financeira\Models\Category;
use App\Addons\gestao_financeira\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TransactionController extends BaseController
{
    protected function accountRule(): mixed
    {
        $tenantId = $this->currentTenantId();
        return $tenantId !== null
            ? Rule::exists('addon_gestao_financeira_accounts', 'id')->where('tenant_id', $tenantId)
            : 'exists:addon_gestao_financeira_accounts,id';
    }

    protected function categoryRule(): mixed
    {
        $tenantId = $this->currentTenantId();
        return $tenantId !== null
            ? Rule::exists('addon_gestao_financeira_categories', 'id')->where('tenant_id', $tenantId)
            : 'exists:addon_gestao_financeira_categories,id';
    }

    protected function transactionValidationRules(): array
    {
        return [
            'type'         => 'required|in:receita,despesa',
            'description'  => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0.01',
            'due_date'     => 'required|date',
            'payment_date' => 'nullable|required_if:status,pago|date',
            'status'       => 'required|in:pago,pendente',
            'account_id'   => ['required', $this->accountRule()],
            'category_id'  => ['required', $this->categoryRule()],
            'notes'        => 'nullable|string',
            'attachment'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ];
    }

    protected function isAjax(): bool
    {
        return request()->expectsJson()
            || request()->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function index(Request $request)
    {
        $tenantId = $this->currentTenantId();

        $query = Transaction::with(['category', 'account'])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($request->filled('search'))      $query->where('description', 'like', '%' . $request->search . '%');
        if ($request->filled('type'))        $query->where('type', $request->type);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('account_id'))  $query->where('account_id', $request->account_id);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date',   now()->endOfMonth()->toDateString());

        $query->where(function ($b) use ($startDate, $endDate) {
            $b->whereBetween('due_date',     [$startDate, $endDate])
              ->orWhereBetween('payment_date', [$startDate, $endDate]);
        });

        $transactions = $query->orderByDesc('due_date')->paginate(20)->withQueryString();
        $accounts     = Account::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        $categories   = Category::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        $routePrefix  = $this->routePrefix();

        return view('gestao-financeira::transactions.index',
            compact('transactions', 'accounts', 'categories', 'startDate', 'endDate', 'routePrefix'));
    }

    public function create()
    {
        $tenantId   = $this->currentTenantId();
        $accounts   = Account::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        $categories = Category::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        $routePrefix = $this->routePrefix();

        return view('gestao-financeira::transactions.create',
            compact('accounts', 'categories', 'routePrefix'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate($this->transactionValidationRules());
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        $data = $request->only(['type', 'description', 'amount', 'due_date', 'payment_date', 'status', 'account_id', 'category_id', 'notes']);

        if ($data['status'] === 'pago' && empty($data['payment_date'])) {
            $data['payment_date'] = now()->toDateString();
        }

        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')
                ->store('addons/financeiro/attachments', 'public');
        }

        Transaction::create($data);

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Lançamento cadastrado com sucesso!']);
        }

        return $this->redirectTo('transactions.index')
            ->with('success', 'Lançamento cadastrado com sucesso!');
    }

    public function edit(Transaction $transaction)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $transaction->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        if ($this->isAjax()) {
            return response()->json([
                'ok' => true,
                'transaction' => [
                    'id'           => $transaction->id,
                    'type'         => $transaction->type,
                    'description'  => $transaction->description,
                    'amount'       => $transaction->amount,
                    'due_date'     => $transaction->due_date?->format('Y-m-d'),
                    'payment_date' => $transaction->payment_date?->format('Y-m-d'),
                    'status'       => $transaction->status,
                    'account_id'   => $transaction->account_id,
                    'category_id'  => $transaction->category_id,
                    'notes'        => $transaction->notes,
                ],
            ]);
        }

        $accounts    = Account::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        $categories  = Category::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();
        $routePrefix = $this->routePrefix();

        return view('gestao-financeira::transactions.edit',
            compact('transaction', 'accounts', 'categories', 'routePrefix'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $transaction->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        try {
            $request->validate($this->transactionValidationRules());
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        $data = $request->only(['type', 'description', 'amount', 'due_date', 'payment_date', 'status', 'account_id', 'category_id', 'notes']);

        if ($data['status'] === 'pago' && empty($data['payment_date'])) {
            $data['payment_date'] = now()->toDateString();
        } elseif ($data['status'] === 'pendente') {
            $data['payment_date'] = null;
        }

        if ($request->hasFile('attachment')) {
            if ($transaction->attachment_path) {
                Storage::disk('public')->delete($transaction->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')
                ->store('addons/financeiro/attachments', 'public');
        }

        $transaction->update($data);

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Lançamento atualizado com sucesso!']);
        }

        return $this->redirectTo('transactions.index')
            ->with('success', 'Lançamento atualizado com sucesso!');
    }

    public function destroy(Transaction $transaction)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $transaction->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        if ($transaction->attachment_path) {
            Storage::disk('public')->delete($transaction->attachment_path);
        }

        $transaction->delete();

        if ($this->isAjax()) {
            return response()->json(['ok' => true]);
        }

        return $this->redirectTo('transactions.index')
            ->with('success', 'Lançamento excluído com sucesso!');
    }

    public function pay(Transaction $transaction)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $transaction->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $transaction->update([
            'status'       => 'pago',
            'payment_date' => now()->toDateString(),
        ]);

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Lançamento marcado como pago!']);
        }

        return redirect()->back()->with('success', 'Lançamento marcado como pago!');
    }
}
