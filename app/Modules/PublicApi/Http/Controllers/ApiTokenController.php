<?php

namespace App\Modules\PublicApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        $tokens = $request->user()->tokens()->latest()->get();

        return view('public-api::tokens', compact('tenant', 'tokens'));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if (! $tenant->publicApiEnabled() || ! $tenant->isActive()) {
            return response()->json([
                'ok' => false,
                'message' => 'API REST disponivel somente para tenants ativos com acesso contratado.',
            ], 402);
        }

        $data = $request->validate([
            'name' => 'required|string|max:80',
        ]);

        $token = $request->user()->createToken($data['name'], ['public-api']);

        return response()->json([
            'ok' => true,
            'message' => 'Token criado com sucesso. Copie agora, ele nao sera exibido novamente.',
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $deleted = $request->user()->tokens()->whereKey($id)->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => (bool) $deleted]);
        }

        return back()->with('success', 'Token revogado com sucesso.');
    }

    protected function tenant(Request $request): Tenant
    {
        return Tenant::with('plan')->where('user_id', $request->user()->id)->firstOrFail();
    }
}
