<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\Permissions\Http\Middleware;

use App\Modules\Permissions\Services\PermissionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de verificação por role (papel).
 * Uso: ->middleware('role:superadmin')
 */
class CheckRole
{
    public function __construct(protected PermissionManager $permissions) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json([
                    'error'   => 'nao_autenticado',
                    'message' => 'Autenticação necessária.',
                ], 401)
                : redirect()->route('admin.login');
        }

        // Superadmin tem bypass total — nunca bloquear
        if ($user instanceof \App\Models\AdminUser) {
            if ($this->permissions->isSuperAdmin($user)) {
                return $next($request);
            }
            $userRoles = $this->permissions->getUserRoles($user);
        } else {
            $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
        }

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return $next($request);
            }
        }

        // Acesso negado — resposta padronizada
        if ($request->expectsJson()) {
            return response()->json([
                'error'   => 'sem_permissao',
                'message' => 'Você não tem permissão para acessar este recurso.',
            ], 403);
        }

        abort(403, 'Você não tem permissão para acessar este recurso.');
    }
}
