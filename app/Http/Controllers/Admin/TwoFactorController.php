<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller de 2FA (Two-Factor Authentication) para admins.
 *
 * Fluxos:
 *  1. Setup: gera secret -> mostra QR -> usuario confirma com codigo
 *  2. Verify: apos login, pede o codigo antes de acessar o painel
 *  3. Disable: desativa 2FA (requer senha atual)
 */
class TwoFactorController extends Controller
{
    public function __construct(protected TwoFactorService $twoFactor) {}

    /** Pagina de configuracao do 2FA (setup/disable). */
    public function showSetup()
    {
        $user = Auth::guard('admin')->user();
        $enabled = ! empty($user->two_factor_secret);
        return view('admin.two-factor.index', compact('user', 'enabled'));
    }

    /** Pagina de verificacao (pos-login). */
    public function showVerify()
    {
        return view('admin.auth.two-factor-verify');
    }

    /** Verifica o codigo TOTP ou recovery code. */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|min:6|max:15']);
        $user = Auth::guard('admin')->user();
        $code = $request->input('code');

        $secret = $this->twoFactor->getSecret($user);
        if (! $secret) {
            return response()->json(['ok' => false, 'error' => '2FA nao configurado.'], 422);
        }

        // Tenta TOTP primeiro, depois recovery code
        $valid = $this->twoFactor->verify($secret, $code)
            || $this->twoFactor->verifyRecoveryCode($user, $code);

        if (! $valid) {
            return response()->json(['ok' => false, 'error' => 'Codigo invalido.'], 422);
        }

        session(['2fa_confirmed' => true]);
        return response()->json(['ok' => true, 'redirect' => '/admin/dashboard']);
    }

    /** Inicia setup do 2FA (gera secret + QR URL). */
    public function setup(): JsonResponse
    {
        $user = Auth::guard('admin')->user();
        $secret = $this->twoFactor->generateSecret();

        // Armazena temporariamente na session ate confirmar
        session(['2fa_setup_secret' => $secret]);

        $qrUrl = $this->twoFactor->getQrUrl($user, $secret);

        return response()->json([
            'ok'     => true,
            'secret' => $secret,
            'qr_url' => $qrUrl,
        ]);
    }

    /** Confirma e ativa 2FA (usuario envia o codigo do app). */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = Auth::guard('admin')->user();
        $secret = session('2fa_setup_secret');

        if (! $secret) {
            return response()->json(['ok' => false, 'error' => 'Inicie o setup novamente.'], 422);
        }

        if (! $this->twoFactor->verify($secret, $request->input('code'))) {
            return response()->json(['ok' => false, 'error' => 'Codigo invalido. Tente novamente.'], 422);
        }

        $recoveryCodes = $this->twoFactor->enable($user, $secret);
        session()->forget('2fa_setup_secret');
        session(['2fa_confirmed' => true]);

        return response()->json([
            'ok'             => true,
            'recovery_codes' => $recoveryCodes,
            'message'        => '2FA ativado com sucesso! Guarde os codigos de recuperacao.',
        ]);
    }

    /** Desativa 2FA (requer senha). */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $user = Auth::guard('admin')->user();

        if (! password_verify($request->input('password'), $user->password)) {
            return response()->json(['ok' => false, 'error' => 'Senha incorreta.'], 422);
        }

        $this->twoFactor->disable($user);
        session()->forget('2fa_confirmed');

        return response()->json(['ok' => true, 'message' => '2FA desativado.']);
    }
}
