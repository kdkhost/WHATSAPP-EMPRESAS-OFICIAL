<?php

namespace App\Modules\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_system', 'level'];

    protected $casts = [
        'is_system' => 'boolean',
        'level'     => 'integer',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    public function adminUsers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\AdminUser::class, 'admin_user_roles');
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }

    public function givePermission(string|Permission $permission): void
    {
        $perm = $permission instanceof Permission
            ? $permission
            : Permission::where('slug', $permission)->firstOrFail();
        $this->permissions()->syncWithoutDetaching([$perm->id]);
    }

    public function revokePermission(string|Permission $permission): void
    {
        $perm = $permission instanceof Permission
            ? $permission
            : Permission::where('slug', $permission)->first();
        if ($perm) {
            $this->permissions()->detach($perm->id);
        }
    }

    /** Sincroniza multiplas permissions de uma vez (array de slugs). */
    public function syncPermissions(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();
        $this->permissions()->sync($ids);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'superadmin';
    }
}
