<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\Permissions\Providers;

use App\Modules\Permissions\Http\Middleware\CheckModulePermission;
use App\Modules\Permissions\Http\Middleware\CheckRole;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Services\PermissionManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Permissions/Resources/views'), 'permissions');

        // Registra middlewares
        $router = $this->app['router'];
        $router->aliasMiddleware('module.permission', CheckModulePermission::class);
        $router->aliasMiddleware('role', CheckRole::class);

        // Gate generico: Gate::allows('billing.edit')
        Gate::before(function ($user, $ability) {
            $manager = app(PermissionManager::class);
            if ($manager->isSuperAdmin($user)) {
                return true;
            }
            if ($manager->can($user, $ability)) {
                return true;
            }
            return null; // fall through to deny
        });

        // Blade directives
        Blade::if('permission', function (string $perm) {
            $user = auth()->guard('admin')->user();
            return $user && app(PermissionManager::class)->can($user, $perm);
        });

        Blade::if('role', function (string ...$roles) {
            $user = auth()->guard('admin')->user();
            if (! $user) return false;
            $manager = app(PermissionManager::class);
            if ($manager->isSuperAdmin($user)) return true;
            $userRoles = $manager->getUserRoles($user);
            return ! empty(array_intersect($roles, $userRoles));
        });

        // Registrar permissions padrao de cada modulo oficial
        $this->app->booted(function () {
            $this->registerDefaultPermissions();
        });

        // Rotas admin para gerenciamento de roles/permissions — APENAS SUPERADMIN
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/permissions')->name('admin.permissions.')->group(function () {
            $ctrl = \App\Modules\Permissions\Http\Controllers\PermissionsController::class;
            Route::get('/',                [$ctrl, 'index'])->name('index');
            Route::get('/roles',           [$ctrl, 'roles'])->name('roles');
            Route::post('/roles',          [$ctrl, 'storeRole'])->name('roles.store');
            Route::put('/roles/{id}',      [$ctrl, 'updateRole'])->name('roles.update');
            Route::delete('/roles/{id}',   [$ctrl, 'destroyRole'])->name('roles.destroy');
            Route::get('/roles/{id}/edit', [$ctrl, 'editRole'])->name('roles.edit');
            Route::post('/roles/{id}/sync',[$ctrl, 'syncRolePermissions'])->name('roles.sync');
            Route::get('/users',           [$ctrl, 'users'])->name('users');
            Route::post('/users/{id}/roles', [$ctrl, 'syncUserRoles'])->name('users.roles.sync');
            Route::post('/users/{id}/permissions', [$ctrl, 'syncUserPermissions'])->name('users.permissions.sync');
            Route::post('/users/{id}/2fa/disable', [$ctrl, 'disableUserTwoFactor'])->name('users.2fa.disable');
        });
    }

    /** Registra as permissions padrao de todos os modulos oficiais. */
    protected function registerDefaultPermissions(): void
    {
        try {
            // Modulos oficiais com CRUD padrao
            $modules = [
                'billing'     => 'Cobrancas',
                'customers'   => 'Clientes',
                'templates'   => 'Templates',
                'whatsapp'    => 'WhatsApp',
                'security'    => 'Seguranca',
                'settings'    => 'Configuracoes',
                'pwa'         => 'PWA',
                'seo'         => 'SEO',
                'maintenance' => 'Manutencao',
                'crm'         => 'CRM',
                'gateways'    => 'Gateways',
                'menus'       => 'Menus',
                'slides'      => 'Slides',
                'popups'      => 'Popups',
                'testimonials'=> 'Depoimentos',
                'uploads'     => 'Uploads',
                'cron'        => 'Cron Jobs',
                'notifications'=> 'Notificacoes',
                'users'       => 'Usuarios',
                'roles'       => 'Papeis/Permissoes',
                'addons'      => 'Addons',
                'kanban'      => 'Kanban',
            ];

            foreach ($modules as $slug => $label) {
                Permission::registerCrud($slug, $label);
            }

            // Permissions especiais
            Permission::register('system', 'access', 'Acessar painel admin', 'Sistema');
            Permission::register('system', 'install', 'Reinstalar sistema', 'Sistema');
            Permission::register('security', 'waf_config', 'Configurar WAF', 'Seguranca');
            Permission::register('security', 'audit_view', 'Ver logs de auditoria', 'Seguranca');
            Permission::register('billing', 'send_manual', 'Enviar cobranca manual', 'Cobrancas');
            Permission::register('gateways', 'configure', 'Configurar gateways', 'Gateways');

        } catch (\Throwable) {
            // Tabelas ainda nao existem (pre-migration) - ignora silenciosamente
        }
    }
}
