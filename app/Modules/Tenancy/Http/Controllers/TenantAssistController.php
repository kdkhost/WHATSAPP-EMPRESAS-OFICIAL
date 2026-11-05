<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantAssistController extends Controller
{
    public function start(Request $request, int $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        if (! $tenant->user_id) {
            abort(422, 'Tenant sem usuario vinculado.');
        }

        $admin = Auth::guard('admin')->user();

        // Login manual do tenant SEM regenerar/destruir a sessao do admin
        $user = Auth::guard('web')->getProvider()->retrieveById($tenant->user_id);
        if (! $user) {
            abort(422, 'Usuario do tenant nao encontrado.');
        }

        // Seta o usuario no guard (memoria da requisicao atual)
        Auth::guard('web')->setUser($user);

        // Grava o identificador do usuario na sessao para persistir entre requisicoes
        // (loginUsingId regenera a sessao e destroi a do admin; gravamos manualmente)
        $request->session()->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());

        // Flags de modo assistido
        $request->session()->put([
            'admin_assist_mode' => true,
            'admin_assist_admin_id' => $admin->id,
            'admin_assist_tenant_id' => $tenant->id,
        ]);

        $request->session()->save();

        \Illuminate\Support\Facades\Log::warning('[ASSIST] Login supervisionado executado (sessao preservada)', [
            'admin_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'user_id' => $tenant->user_id,
            'session_id' => $request->session()->getId(),
            'web_check' => Auth::guard('web')->check(),
            'web_user' => Auth::guard('web')->id(),
        ]);

        return redirect('/painel');
    }

    public function stop(Request $request): RedirectResponse
    {
        if (! $request->session()->get('admin_assist_mode')) {
            abort(403, 'Modo assistido nao ativo.');
        }

        $adminId = (int) $request->session()->get('admin_assist_admin_id');
        $tenantId = (int) $request->session()->get('admin_assist_tenant_id');

        Auth::guard('web')->logout();

        $request->session()->forget([
            'admin_assist_mode',
            'admin_assist_admin_id',
            'admin_assist_tenant_id',
        ]);

        if (! Auth::guard('admin')->check() && $adminId > 0) {
            Auth::guard('admin')->loginUsingId($adminId);
        }

        if (Auth::guard('admin')->check()) {
            if ($tenantId > 0) {
                return redirect()->route('admin.tenancy.tenants.show', $tenantId);
            }

            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('admin.login');
    }
}
