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
 * Middleware de verificação granular de permissão.
 * Uso: ->middleware('module.permission:billing.view')
 * Superadmin sempre passa (bypass total).
 */
class CheckModulePermission
{
    public function __construct(protected PermissionManager $permissions) {}

    public function handle(Request $request, Closure $next, string ...$requiredPermissions): Response
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

        // Superadmin bypass total
        if ($this->permissions->isSuperAdmin($user)) {
            return $next($request);
        }

        // Verifica cada permissão requerida (lógica OR)
        foreach ($requiredPermissions as $perm) {
            if ($this->permissions->can($user, $perm)) {
                return $next($request);
            }
        }

        // Acesso negado — resposta padronizada em PT-BR
        if ($request->expectsJson()) {
            return response()->json([
                'error'   => 'sem_permissao',
                'message' => 'Você não tem permissão para acessar este recurso.',
            ], 403);
        }

        abort(403, 'Você não tem permissão para acessar este recurso.');
    }
}
