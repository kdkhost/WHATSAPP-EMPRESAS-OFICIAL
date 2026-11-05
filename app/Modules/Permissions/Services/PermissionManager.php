<?php

namespace App\Modules\Permissions\Services;

use App\Models\AdminUser;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service central de permissoes.
 *
 * Hierarquia:
 *   1. superadmin SEMPRE tem acesso total (bypass)
 *   2. admin_user_permissions com type=deny NEGA mesmo se a role permita
 *   3. admin_user_permissions com type=grant CONCEDE extra (fora da role)
 *   4. role_permissions define o padrao
 *
 * Cache: permissoes do usuario sao cacheadas por 5 minutos.
 */
class PermissionManager
{
    /** Verifica se um admin tem determinada permission (slug). */
    public function can(AdminUser $user, string $permissionSlug): bool
    {
        // Superadmin bypass total
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $perms = $this->getUserPermissions($user);

        // Deny direto nega tudo
        if (in_array($permissionSlug, $perms['denied'] ?? [], true)) {
            return false;
        }

        // Grant direto ou via role
        return in_array($permissionSlug, $perms['granted'] ?? [], true);
    }

    /** Verifica se tem permissao para um modulo+acao. Ex.: canModule($user, 'billing', 'edit') */
    public function canModule(AdminUser $user, string $module, string $action = 'view'): bool
    {
        return $this->can($user, $module . '.' . $action);
    }

    /** Verifica se eh superadmin (via role ou campo legado). */
    public function isSuperAdmin(AdminUser $user): bool
    {
        // Campo legado
        if (($user->role ?? '') === 'superadmin') {
            return true;
        }
        // Via roles table
        $roles = $this->getUserRoles($user);
        return in_array('superadmin', $roles, true);
    }

    /** Retorna todas as roles (slugs) de um admin. */
    public function getUserRoles(AdminUser $user): array
    {
        return Cache::remember("admin_roles:{$user->id}", 300, function () use ($user) {
            return DB::table('admin_user_roles')
                ->join('roles', 'roles.id', '=', 'admin_user_roles.role_id')
                ->where('admin_user_roles.admin_user_id', $user->id)
                ->pluck('roles.slug')
                ->all();
        });
    }

    /** Retorna array com 'granted' e 'denied' permissions (slugs). */
    public function getUserPermissions(AdminUser $user): array
    {
        return Cache::remember("admin_perms:{$user->id}", 300, function () use ($user) {
            // Permissions via roles
            $rolePerms = DB::table('admin_user_roles')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'admin_user_roles.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('admin_user_roles.admin_user_id', $user->id)
                ->pluck('permissions.slug')
                ->all();

            // Permissions diretas (grant)
            $directGrant = DB::table('admin_user_permissions')
                ->join('permissions', 'permissions.id', '=', 'admin_user_permissions.permission_id')
                ->where('admin_user_permissions.admin_user_id', $user->id)
                ->where('admin_user_permissions.type', 'grant')
                ->pluck('permissions.slug')
                ->all();

            // Permissions diretas (deny) - override
            $directDeny = DB::table('admin_user_permissions')
                ->join('permissions', 'permissions.id', '=', 'admin_user_permissions.permission_id')
                ->where('admin_user_permissions.admin_user_id', $user->id)
                ->where('admin_user_permissions.type', 'deny')
                ->pluck('permissions.slug')
                ->all();

            $granted = array_unique(array_merge($rolePerms, $directGrant));
            // Remove denied da lista de granted
            $granted = array_values(array_diff($granted, $directDeny));

            return [
                'granted' => $granted,
                'denied'  => $directDeny,
            ];
        });
    }

    /** Lista todas as permissions agrupadas por modulo (para o painel). */
    public function allGrouped(): array
    {
        return Permission::orderBy('module')->orderBy('action')->get()
            ->groupBy('module')
            ->map(fn ($perms) => $perms->map(fn ($p) => [
                'id'     => $p->id,
                'slug'   => $p->slug,
                'action' => $p->action,
                'label'  => $p->label,
                'group'  => $p->group,
            ])->all())
            ->all();
    }

    /** Limpa cache de permissoes de um usuario. */
    public function clearCache(AdminUser|int $user): void
    {
        $id = $user instanceof AdminUser ? $user->id : $user;
        Cache::forget("admin_perms:{$id}");
        Cache::forget("admin_roles:{$id}");
    }

    /** Limpa cache de todos os usuarios (ex.: ao editar uma role). */
    public function clearAllCache(): void
    {
        // Invalidar por pattern (se redis/memcached suportar)
        // Fallback: limpa individualmente
        $ids = DB::table('admin_users')->pluck('id');
        foreach ($ids as $id) {
            Cache::forget("admin_perms:{$id}");
            Cache::forget("admin_roles:{$id}");
        }
    }
}
