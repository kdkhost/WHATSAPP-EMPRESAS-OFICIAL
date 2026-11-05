<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function show()
    {
        return view('auth.reset');
    }

    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar email de redefinicao de senha.', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Nao foi possivel enviar o email de redefinicao agora. Verifique a configuracao de email do sistema e tente novamente em instantes.',
                ]);
        }

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Enviamos o link de redefinicao para o e-mail informado.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $this->statusMessage($status)]);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Sua senha foi redefinida com sucesso. Entre com a nova senha.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $this->statusMessage($status)]);
    }

    protected function statusMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_USER => 'Nao encontramos uma conta com esse e-mail.',
            Password::INVALID_TOKEN => 'O link de redefinicao e invalido ou expirou.',
            Password::RESET_THROTTLED => 'Aguarde alguns instantes antes de solicitar um novo link.',
            default => 'Nao foi possivel concluir a redefinicao de senha agora.',
        };
    }
}
