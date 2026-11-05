<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function show()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function attempt(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        // Registra tentativa
        $logged = Auth::guard('admin')->attempt($data, $remember);

        DB::table('security_login_attempts')->insert([
            'guard'      => 'admin',
            'email'      => $data['email'],
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success'    => $logged,
            'created_at' => now(),
        ]);

        if (! $logged) {
            throw ValidationException::withMessages([
                'email' => 'Credenciais invalidas.',
            ]);
        }

        DB::table('admin_users')->where('id', Auth::guard('admin')->id())
            ->update(['last_login_at' => now()]);

        $request->session()->regenerate();

        // Se política de sessão única estiver ativa, desconecta outras máquinas/sessões anteriores deste admin
        try {
            $sessionManager = app(\App\Services\SessionManagerService::class);
            if (! $sessionManager->isConcurrentSessionsAllowed()) {
                $sessionManager->terminateOtherSessions(Auth::guard('admin')->id(), 'admin', $request->session()->getId());
            }
        } catch (\Throwable) {}

        \Illuminate\Support\Facades\Log::debug('[LOGIN] Login admin sucesso', [
            'admin_id' => Auth::guard('admin')->id(),
            'email' => $data['email'],
            'session_id' => $request->session()->getId(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
