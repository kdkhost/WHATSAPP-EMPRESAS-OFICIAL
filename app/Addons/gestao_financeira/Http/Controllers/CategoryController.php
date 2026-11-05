<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    protected function isAjax(): bool
    {
        return request()->expectsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function index()
    {
        $tenantId   = $this->currentTenantId();
        $categories = Category::withCount('transactions')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('type')->orderBy('name')->get();
        $routePrefix = $this->routePrefix();

        return view('gestao-financeira::categories.index', compact('categories', 'routePrefix'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name'  => 'required|string|max:100',
                'type'  => 'required|in:receita,despesa',
                'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
            }
            throw $e;
        }

        $data     = $request->only(['name', 'type', 'color']);
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        Category::create($data);

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Categoria cadastrada com sucesso!']);
        }

        return $this->redirectTo('categories.index')->with('success', 'Categoria cadastrada com sucesso!');
    }

    public function update(Request $request, Category $category)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $category->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        try {
            $request->validate([
                'name'  => 'required|string|max:100',
                'type'  => 'required|in:receita,despesa',
                'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->isAjax()) {
                return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
            }
            throw $e;
        }

        $category->update($request->only(['name', 'type', 'color']));

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => 'Categoria atualizada com sucesso!']);
        }

        return $this->redirectTo('categories.index')->with('success', 'Categoria atualizada com sucesso!');
    }

    public function destroy(Category $category)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $category->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $count = $category->transactions()->count();

        $category->delete();

        if ($this->isAjax()) {
            return response()->json(['ok' => true, 'message' => $count > 0
                ? "Categoria excluída. {$count} lançamentos foram desvinculados."
                : 'Categoria excluída com sucesso!'
            ]);
        }

        return $this->redirectTo('categories.index')
            ->with('success', $count > 0 ? "Categoria excluída. {$count} lançamentos foram desvinculados." : 'Categoria excluída com sucesso!');
    }
}
