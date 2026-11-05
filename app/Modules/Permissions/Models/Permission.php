<?php

namespace App\Modules\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['module', 'action', 'slug', 'label', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withTimestamps();
    }

    /**
     * Registra uma permission se nao existir (usado por modulos/addons no boot).
     */
    public static function register(string $module, string $action, string $label, ?string $group = null): self
    {
        $slug = $module . '.' . $action;
        return self::firstOrCreate(
            ['slug' => $slug],
            [
                'module' => $module,
                'action' => $action,
                'label'  => $label,
                'group'  => $group ?? ucfirst($module),
            ]
        );
    }

    /** Registra todas as acoes CRUD padrao para um modulo. */
    public static function registerCrud(string $module, string $label, ?string $group = null): void
    {
        $group = $group ?? ucfirst($module);
        $actions = [
            'view'   => "Visualizar {$label}",
            'create' => "Criar {$label}",
            'edit'   => "Editar {$label}",
            'delete' => "Excluir {$label}",
            'manage' => "Gerenciar {$label}",
            'export' => "Exportar {$label}",
        ];
        foreach ($actions as $action => $desc) {
            self::register($module, $action, $desc, $group);
        }
    }
}
