<?php

namespace App\Modules\Permissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Modules\Permissions\Services\PermissionManager;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Painel de gerenciamento de roles e permissoes.
 * Acessivel apenas por superadmin (ou quem tiver roles.manage).
 *
 * CRUD 100% AJAX sem refresh.
 */
class PermissionsController extends Controller
{
    public function __construct(protected PermissionManager $manager) {}

    /** Painel principal - overview de roles e permissoes. */
    public function index()
    {
        $roles = Role::withCount('adminUsers')->orderByDesc('level')->get();
        $permissionsGrouped = $this->manager->allGrouped();
        $totalAdmins = AdminUser::count();

        return view('permissions::index', compact('roles', 'permissionsGrouped', 'totalAdmins'));
    }

    /** Lista roles com suas permissions. */
    public function roles()
    {
        $roles = Role::with('permissions')->orderByDesc('level')->get();
        return response()->json($roles);
    }

    /** Criar nova role. */
    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:80',
            'slug'        => 'required|string|max:60|unique:roles,slug',
            'description' => 'nullable|string|max:255',
            'level'       => 'nullable|integer|min:0|max:99',
        ]);

        $role = Role::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'level'       => $data['level'] ?? 0,
            'is_system'   => false,
        ]);

        return response()->json(['ok' => true, 'role' => $role]);
    }

    /** Editar role (form data). */
    public function editRole(int $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $allPermissions = $this->manager->allGrouped();
        $allPermissionSlugs = collect($allPermissions)
            ->flatten(1)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
        $rolePermSlugs = $role->isSuperAdmin()
            ? $allPermissionSlugs
            : $role->permissions->pluck('slug')->all();

        return response()->json([
            'role'            => $role,
            'permissions'     => $allPermissions,
            'role_permissions'=> $rolePermSlugs,
            'readonly'        => $role->isSuperAdmin(),
        ]);
    }

    /** Atualizar role. */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system && $role->slug === 'superadmin') {
            return response()->json(['ok' => false, 'error' => 'Role superadmin nao pode ser editada.'], 403);
        }

        $data = $request->validate([
            'name'        => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'level'       => 'nullable|integer|min:0|max:99',
        ]);

        $role->update($data);
        $this->manager->clearAllCache();

        return response()->json(['ok' => true]);
    }

    /** Excluir role. */
    public function destroyRole(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['ok' => false, 'error' => 'Roles do sistema nao podem ser excluidas.'], 403);
        }

        $role->permissions()->detach();
        $role->adminUsers()->detach();
        $role->delete();
        $this->manager->clearAllCache();

        return response()->json(['ok' => true]);
    }

    /** Sincroniza permissions de uma role (checkboxes do painel). */
    public function syncRolePermissions(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->slug === 'superadmin') {
            $count = Permission::count();

            return response()->json([
                'ok' => true,
                'readonly' => true,
                'count' => $count,
                'message' => 'Superadmin tem acesso total automaticamente.',
            ]);
        }

        $slugs = $request->validate(['permissions' => 'required|array'])['permissions'];
        $role->syncPermissions($slugs);
        $this->manager->clearAllCache();

        return response()->json(['ok' => true, 'count' => count($slugs)]);
    }

    /** Lista usuarios admin com suas roles. */
    public function users()
    {
        $users = AdminUser::with('roles')->orderByDesc('id')->get();
        $roles = Role::orderByDesc('level')->get();

        $users->each(function (AdminUser $user): void {
            $user->setAttribute('is_superadmin', $user->isSuperAdmin());
        });

        return response()->json(['users' => $users, 'roles' => $roles]);
    }

    /** Sincroniza roles de um usuario. */
    public function syncUserRoles(Request $request, int $id): JsonResponse
    {
        $user = AdminUser::findOrFail($id);

        if ($user->isSuperAdmin()) {
            return response()->json([
                'ok' => true,
                'readonly' => true,
                'message' => 'Superadmin mantem acesso total automaticamente.',
            ]);
        }

        $roleIds = $request->validate(['roles' => 'required|array'])['roles'];
        $user->syncRoles($roleIds);

        return response()->json(['ok' => true]);
    }

    /** Sincroniza permissions diretas de um usuario (overrides). */
    public function syncUserPermissions(Request $request, int $id): JsonResponse
    {
        $user = AdminUser::findOrFail($id);

        if ($user->isSuperAdmin()) {
            return response()->json([
                'ok' => true,
                'readonly' => true,
                'message' => 'Superadmin mantem acesso total automaticamente.',
            ]);
        }

        $data = $request->validate([
            'grants' => 'nullable|array',
            'denies' => 'nullable|array',
        ]);

        // Remove todos os overrides atuais
        $user->directPermissions()->detach();

        // Adiciona grants
        foreach ($data['grants'] ?? [] as $slug) {
            $user->grantPermission($slug);
        }
        // Adiciona denies
        foreach ($data['denies'] ?? [] as $slug) {
            $user->denyPermission($slug);
        }

        return response()->json(['ok' => true]);
    }

    /** Desativa 2FA de um usuario admin a pedido do proprio usuario. */
    public function disableUserTwoFactor(int $id): JsonResponse
    {
        $user = AdminUser::findOrFail($id);

        app(TwoFactorService::class)->disable($user);

        return response()->json([
            'ok' => true,
            'message' => '2FA desativado para ' . $user->name . '.',
        ]);
    }
}
