<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Models;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Modules\Permissions\Services\PermissionManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    use HasFactory;

    protected $table = 'admin_users';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'active', 'theme_dark', 'last_login_at',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled', 'two_factor_confirmed_at',
        'phone', 'avatar_url', 'tour_completed_at', 'notification_sound_enabled',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'           => 'hashed',
            'active'             => 'boolean',
            'theme_dark'         => 'boolean',
            'notification_sound_enabled' => 'boolean',
            'last_login_at'      => 'datetime',
            'tour_completed_at'  => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // =========================================================
    // RELACOES DE PERMISSAO
    // =========================================================

    /** Roles atribuidas a este admin. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_user_roles');
    }

    /** Permissions diretas (override grant/deny). */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'admin_user_permissions')
            ->withPivot('type');
    }

    // =========================================================
    // HELPERS DE PERMISSAO
    // =========================================================

    /** Verifica se eh superadmin (campo legado OU role). */
    public function isSuperAdmin(): bool
    {
        return app(PermissionManager::class)->isSuperAdmin($this);
    }

    /** Verifica se tem determinada permission. Ex: $user->can('billing.edit') */
    public function hasPermission(string $permissionSlug): bool
    {
        return app(PermissionManager::class)->can($this, $permissionSlug);
    }

    /** Verifica se tem permissao para modulo+acao. Ex: $user->canModule('billing', 'delete') */
    public function canModule(string $module, string $action = 'view'): bool
    {
        return app(PermissionManager::class)->canModule($this, $module, $action);
    }

    /** Verifica se possui alguma das roles informadas. */
    public function hasRole(string ...$roles): bool
    {
        if ($this->isSuperAdmin()) return true;
        $userRoles = app(PermissionManager::class)->getUserRoles($this);
        return ! empty(array_intersect($roles, $userRoles));
    }

    /** Retorna array com slugs de todas as roles. */
    public function getRoleSlugs(): array
    {
        return app(PermissionManager::class)->getUserRoles($this);
    }

    /** Retorna array com slugs de todas as permissions (granted). */
    public function getPermissionSlugs(): array
    {
        $perms = app(PermissionManager::class)->getUserPermissions($this);
        return $perms['granted'] ?? [];
    }

    // =========================================================
    // MANAGEMENT HELPERS (para uso no painel admin)
    // =========================================================

    /** Atribui roles (substitui todas). */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
        app(PermissionManager::class)->clearCache($this);
    }

    /** Adiciona uma role. */
    public function assignRole(string|int|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }
        $roleId = $role instanceof Role ? $role->id : $role;
        $this->roles()->syncWithoutDetaching([$roleId]);
        app(PermissionManager::class)->clearCache($this);
    }

    /** Remove uma role. */
    public function removeRole(string|int|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }
        $roleId = $role instanceof Role ? $role->id : $role;
        $this->roles()->detach($roleId);
        app(PermissionManager::class)->clearCache($this);
    }

    /** Concede permission direta (grant). */
    public function grantPermission(string|int $permission): void
    {
        $permId = is_string($permission)
            ? Permission::where('slug', $permission)->value('id')
            : $permission;
        if (! $permId) return;
        $this->directPermissions()->syncWithoutDetaching([$permId => ['type' => 'grant']]);
        app(PermissionManager::class)->clearCache($this);
    }

    /** Nega permission direta (deny - override). */
    public function denyPermission(string|int $permission): void
    {
        $permId = is_string($permission)
            ? Permission::where('slug', $permission)->value('id')
            : $permission;
        if (! $permId) return;
        $this->directPermissions()->syncWithoutDetaching([$permId => ['type' => 'deny']]);
        app(PermissionManager::class)->clearCache($this);
    }

    /** Remove override direto (volta ao padrao da role). */
    public function removeDirectPermission(string|int $permission): void
    {
        $permId = is_string($permission)
            ? Permission::where('slug', $permission)->value('id')
            : $permission;
        if (! $permId) return;
        $this->directPermissions()->detach($permId);
        app(PermissionManager::class)->clearCache($this);
    }

    // =========================================================
    // SCOPES
    // =========================================================

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByRole($query, string $roleSlug)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('slug', $roleSlug));
    }
}
