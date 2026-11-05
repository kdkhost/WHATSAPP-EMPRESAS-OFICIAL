<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        if (Auth::guard('web')->check()) {
            if (! $this->hasActiveTenant((int) Auth::id())) {
                Auth::guard('web')->logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Sua conta nao possui uma empresa cadastrada para acesso ao painel.']);
            }

            return redirect('/painel');
        }

        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function showPwa()
    {
        if (Auth::guard('web')->check()) {
            if (! $this->hasActiveTenant((int) Auth::id())) {
                Auth::guard('web')->logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return redirect()->route('pwa.login')
                    ->withErrors(['email' => 'Sua conta nao possui uma empresa cadastrada para acesso ao painel.']);
            }

            return redirect('/painel');
        }

        return response()
            ->view('auth.pwa-login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $logged = Auth::guard('web')->attempt($data, $request->boolean('remember'));

        DB::table('security_login_attempts')->insert([
            'guard'      => 'web',
            'email'      => $data['email'],
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success'    => $logged,
            'created_at' => now(),
        ]);

        \Illuminate\Support\Facades\Log::debug('[LOGIN] Tentativa login web', [
            'email' => $data['email'],
            'logged' => $logged,
            'user_id' => Auth::guard('web')->id(),
        ]);

        if (! $logged) {
            throw ValidationException::withMessages(['email' => 'Credenciais invalidas.']);
        }
        $request->session()->regenerate();

        // Se política de sessão única estiver ativa, desconecta outras máquinas/sessões anteriores deste usuário
        try {
            $sessionManager = app(\App\Services\SessionManagerService::class);
            if (! $sessionManager->isConcurrentSessionsAllowed()) {
                $sessionManager->terminateOtherSessions((int) Auth::id(), 'web', $request->session()->getId());
            }
        } catch (\Throwable) {}

        $userId = (int) Auth::id();
        $hasTenant = $this->hasActiveTenant($userId);

        \Illuminate\Support\Facades\Log::debug('[LOGIN] Login web sucesso', [
            'user_id' => $userId,
            'has_active_tenant' => $hasTenant,
            'session_id' => $request->session()->getId(),
        ]);

        if (! $hasTenant) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Sua conta nao possui uma empresa cadastrada para acesso ao painel.',
            ]);
        }

        if ($request->boolean('pwa_login')) {
            return redirect('/painel');
        }

        return redirect('/painel');
    }

    public function logout(Request $request)
    {
        if ($request->session()->get('admin_assist_mode')) {
            return redirect('/painel')
                ->with('warning', 'Use o botao Voltar para Admin para encerrar o acesso supervisionado.');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    private function hasActiveTenant(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        // Se o usuário possui qualquer tenant associado a ele
        $hasTenantByUser = Tenant::query()
            ->where('user_id', $userId)
            ->exists();

        if ($hasTenantByUser) {
            return true;
        }

        // Ou se o usuário aponta para um tenant via tenant_id
        $user = Auth::guard('web')->user() ?? \App\Models\User::find($userId);
        if ($user && !empty($user->tenant_id)) {
            return Tenant::query()->where('id', $user->tenant_id)->exists();
        }

        return false;
    }
}
