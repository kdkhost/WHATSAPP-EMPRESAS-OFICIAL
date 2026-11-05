<?php

namespace App\Services;

use App\Modules\Permissions\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddonManagerService
{
    protected array $roots;

    public function __construct()
    {
        $this->roots = [
            app_path('Addons'),
            app_path('Modules'),
        ];
    }

    public function discover(): array
    {
        $addons = [];

        foreach ($this->roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (File::directories($root) as $dir) {
                $manifest = $this->manifestPath($dir);
                if (! $manifest) {
                    continue;
                }

                $meta = $this->loadManifest($dir);
                if (! $meta) {
                    continue;
                }

                $record = $this->registeredModule($meta['slug']);
                $addons[] = [
                    'slug' => $meta['slug'],
                    'name' => $meta['name'],
                    'version' => $meta['version'] ?? '0.0.0',
                    'author' => $meta['author'] ?? 'Sistema',
                    'description' => $meta['description'] ?? '',
                    'provider' => $meta['provider'] ?? null,
                    'enabled' => (bool) ($meta['enabled'] ?? $meta['active'] ?? true),
                    'installed' => (bool) ($record?->installed ?? false),
                    'db_enabled' => (bool) ($record?->enabled ?? false),
                    'requires' => $meta['requires'] ?? [],
                    'permissions' => $meta['permissions'] ?? [],
                    'hooks' => $meta['hooks'] ?? [],
                    'route' => $this->resolveAddonRoute($meta, $meta['slug']),
                    'icon' => data_get($meta, 'menu.icon') ?: ($meta['icon'] ?? 'bi-puzzle'),
                    'path' => $dir,
                    'meta' => $meta,
                ];
            }
        }

        return $addons;
    }

    public function install(string $module): array
    {
        return $this->guarded($module, function (array $addon) {
            $migrationResult = $this->runMigrations($addon['slug']);
            if (! ($migrationResult['ok'] ?? false)) {
                return $migrationResult;
            }

            DB::transaction(function () use ($addon) {
                $permissionResult = $this->registerPermissions($addon['slug']);
                if (! ($permissionResult['ok'] ?? false)) {
                    throw new \RuntimeException($permissionResult['message'] ?? $permissionResult['error'] ?? 'Falha ao registrar permissoes.');
                }

                $menuResult = $this->registerMenus($addon['slug'], true);
                if (! ($menuResult['ok'] ?? false)) {
                    throw new \RuntimeException($menuResult['message'] ?? $menuResult['error'] ?? 'Falha ao registrar menu do modulo.');
                }

                $this->upsertModule($addon, true, true);
                $this->updateManifestState($addon, true);
                $this->callHook($addon, 'install');
            });

            $this->clearSystemCache();

            return $this->ok('Modulo instalado com sucesso.');
        });
    }

    public function activate(string $module): array
    {
        return $this->guarded($module, function (array $addon) {
            if (! $this->registeredModule($addon['slug'])) {
                $installResult = $this->install($addon['slug']);
                if (! ($installResult['ok'] ?? false)) {
                    return $this->fail($installResult['message'] ?? $installResult['error'] ?? 'Falha ao instalar o modulo.');
                }
            }

            $migrationResult = $this->runMigrations($addon['slug']);
            if (! ($migrationResult['ok'] ?? false)) {
                return $migrationResult;
            }

            $permissionResult = $this->registerPermissions($addon['slug']);
            if (! ($permissionResult['ok'] ?? false)) {
                return $permissionResult;
            }

            $this->upsertModule($addon, true, true);
            $this->updateManifestState($addon, true);

            $menuResult = $this->registerMenus($addon['slug'], true);
            if (! ($menuResult['ok'] ?? false)) {
                return $menuResult;
            }

            $this->callHook($addon, 'enable');
            $this->clearSystemCache();

            return $this->ok('Modulo ativado com sucesso.');
        });
    }

    public function deactivate(string $module): array
    {
        return $this->guarded($module, function (array $addon) {
            $this->upsertModule($addon, true, false);
            $this->updateManifestState($addon, false);
            if (Schema::hasTable('addon_menus')) {
                DB::table('addon_menus')->where('module_slug', $addon['slug'])->update([
                    'active' => false,
                    'updated_at' => now(),
                ]);
            }
            $this->callHook($addon, 'disable');
            $this->clearSystemCache();

            return $this->ok('Modulo desativado com sucesso.');
        });
    }

    public function uninstall(string $module): array
    {
        return $this->guarded($module, function (array $addon) {
            $this->callHook($addon, 'uninstall');

            $rollbackResult = $this->rollbackMigrations($addon['slug']);
            if (! ($rollbackResult['ok'] ?? false)) {
                return $rollbackResult;
            }

            if (Schema::hasTable('addon_menus')) {
                DB::table('addon_menus')->where('module_slug', $addon['slug'])->delete();
            }
            Permission::where('module', $addon['slug'])->delete();
            if (Schema::hasTable('registered_modules')) {
                DB::table('registered_modules')->where('slug', $addon['slug'])->delete();
            }
            $this->updateManifestState($addon, false);
            $this->clearSystemCache();

            return $this->ok('Modulo desinstalado do painel.');
        });
    }

    public function runMigrations(string $module): array
    {
        $addon = $this->findBySlug($module);
        if (! $addon) {
            return $this->fail('Modulo nao encontrado.');
        }

        $path = $addon['path'].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($path)) {
            return $this->ok('Modulo sem migrations pendentes.');
        }

        $exitCode = Artisan::call('migrate', [
            '--path' => Str::of($path)->replace(base_path().DIRECTORY_SEPARATOR, '')->replace('\\', '/')->toString(),
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            return $this->fail('Falha ao executar migrations do modulo: ' . trim(Artisan::output()));
        }

        return $this->ok('Migrations do modulo executadas.');
    }

    public function rollbackMigrations(string $module): array
    {
        $addon = $this->findBySlug($module);
        if (! $addon) {
            return $this->fail('Modulo nao encontrado.');
        }

        $path = $addon['path'].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($path)) {
            return $this->ok('Modulo nao possui migrations para rollback.');
        }

        $exitCode = Artisan::call('migrate:rollback', [
            '--path' => Str::of($path)->replace(base_path().DIRECTORY_SEPARATOR, '')->replace('\\', '/')->toString(),
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            return $this->fail('Falha ao rollback das migrations do modulo: ' . trim(Artisan::output()));
        }

        return $this->ok('Rollback das migrations do modulo executado.');
    }

    public function registerMenus(string $module, bool $active = true): array
    {
        $addon = $this->findBySlug($module);
        if (! $addon || ! Schema::hasTable('addon_menus')) {
            return $this->fail('Modulo ou tabela de menus nao encontrada.');
        }

        $routes = [];
        foreach ($this->addonMenuItems($addon) as $menu) {
            $route = $this->resolveAddonRoute($menu, $addon['slug']);
            $routes[] = $route;

            $menuData = [
                'title' => $menu['title'] ?? $menu['label'] ?? $addon['name'],
                'icon' => $menu['icon'] ?? data_get($addon, 'meta.icon', 'bi-puzzle'),
                'menu_order' => (int) ($menu['order'] ?? 100),
                'active' => $active,
                'meta' => json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            $query = DB::table('addon_menus')->where('module_slug', $addon['slug'])->where('route', $route);
            if ($query->exists()) {
                $query->update($menuData);
            } else {
                DB::table('addon_menus')->insert(array_merge(
                    ['module_slug' => $addon['slug'], 'route' => $route, 'created_at' => now()],
                    $menuData
                ));
            }
        }

        if ($active && ! empty($routes)) {
            DB::table('addon_menus')
                ->where('module_slug', $addon['slug'])
                ->whereNotIn('route', $routes)
                ->update(['active' => false, 'updated_at' => now()]);
        }

        return $this->ok('Menu do modulo registrado.');
    }

    public function registerPermissions(string $module): array
    {
        $addon = $this->findBySlug($module);
        if (! $addon) {
            return $this->fail('Modulo nao encontrado.');
        }

        foreach ($addon['permissions'] as $permission) {
            if (is_string($permission)) {
                [$moduleName, $action] = array_pad(explode('.', $permission, 2), 2, 'view');
                Permission::register($moduleName, $action, ucfirst(str_replace('.', ' ', $permission)), $addon['name']);

                continue;
            }

            $action = $permission['action'] ?? 'view';
            Permission::register($addon['slug'], $action, $permission['label'] ?? ucfirst($action).' '.$addon['name'], $addon['name']);
        }

        return $this->ok('Permissoes do modulo registradas.');
    }

    public function clearSystemCache(): void
    {
        foreach (['optimize:clear', 'route:clear', 'config:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $e) {
                Log::warning('Falha ao limpar cache do sistema', ['command' => $command, 'error' => $e->getMessage()]);
            }
        }

        if (app()->environment('production')) {
            foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
                try {
                    Artisan::call($command);
                } catch (\Throwable $e) {
                    Log::warning('Falha ao recriar cache do sistema', ['command' => $command, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    public function loadManifest(string $pathOrSlug): ?array
    {
        $path = is_dir($pathOrSlug) ? $pathOrSlug : ($this->findBySlug($pathOrSlug)['path'] ?? null);
        if (! $path) {
            return null;
        }

        $manifest = $this->manifestPath($path);
        if (! $manifest) {
            return null;
        }

        $data = json_decode(File::get($manifest), true);
        if (! is_array($data) || empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'] ?? basename($path));
        }

        $data['name'] = $data['name'] ?? Str::headline($data['slug']);

        return $data;
    }

    public function enable(string $module): array
    {
        return $this->activate($module);
    }

    public function disable(string $module): array
    {
        return $this->deactivate($module);
    }

    public function update(string $module): array
    {
        return $this->guarded($module, function () use ($module) {
            $migrationResult = $this->runMigrations($module);
            if (! ($migrationResult['ok'] ?? false)) {
                return $migrationResult;
            }

            $permissionResult = $this->registerPermissions($module);
            if (! ($permissionResult['ok'] ?? false)) {
                return $permissionResult;
            }

            $menuResult = $this->registerMenus($module, true);
            if (! ($menuResult['ok'] ?? false)) {
                return $menuResult;
            }

            $addon = $this->findBySlug($module);
            if ($addon) {
                $this->upsertModule($addon, true, true);
            }

            $this->clearSystemCache();

            return $this->ok('Modulo atualizado com sucesso.');
        });
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->discover() as $addon) {
            if ($addon['slug'] === $slug) {
                return $addon;
            }
        }

        return null;
    }

    protected function guarded(string $module, callable $callback): array
    {
        $addon = $this->findBySlug($module);
        if (! $addon) {
            return $this->fail('Modulo nao encontrado.');
        }

        try {
            return $callback($addon);
        } catch (\Throwable $e) {
            Log::error('Falha no ciclo de vida do addon', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return $this->fail($e->getMessage());
        }
    }

    protected function resolveAddonRoute(array $manifest, string $slug): string
    {
        $slugSnake = Str::snake(str_replace('-', '_', $slug));

        if (in_array($slug, ['analytics', 'analytics-pro', 'analytics_pro'], true) || str_contains($slug, 'analytics')) {
            if (\Illuminate\Support\Facades\Route::has('admin.analytics.index')) {
                return 'admin.analytics.index';
            }
            return '/admin/analytics';
        }

        $candidates = [
            data_get($manifest, 'menu.route'),
            data_get($manifest, 'menu.items.0.route'),
            data_get($manifest, 'tenant_menu.route'),
            data_get($manifest, 'tenant_menu.items.0.route'),
            $manifest['route'] ?? null,
            $manifest['url'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && ! str_contains($candidate, 'admin.addons.')) {
                return trim($candidate);
            }
        }

        if (\Illuminate\Support\Facades\Route::has("admin.{$slugSnake}.index")) {
            return "admin.{$slugSnake}.index";
        }
        if (\Illuminate\Support\Facades\Route::has("admin.{$slug}.index")) {
            return "admin.{$slug}.index";
        }
        if (\Illuminate\Support\Facades\Route::has("admin.{$slugSnake}.dashboard")) {
            return "admin.{$slugSnake}.dashboard";
        }

        return "/admin/{$slugSnake}";
    }

    protected function addonMenuItems(array $addon): array
    {
        $manifest = $addon['meta'];
        $menu = $manifest['menu'] ?? [];
        
        // Regra de Exclusividade de Menu:
        // O menu registrado na sidebar principal do sistema deve ser ÚNICO e exclusivo como ponto de entrada do addon.
        // Submenus e navegações internas pertencem exclusivamente à interface interna do próprio módulo/addon.
        $entryRoute = $this->resolveAddonRoute($manifest, $addon['slug']);

        return [
            [
                'title' => $menu['title'] ?? $menu['label'] ?? $addon['name'],
                'icon' => $menu['icon'] ?? ($manifest['icon'] ?? 'bi-puzzle'),
                'order' => (int) ($menu['order'] ?? 100),
                'route' => $entryRoute,
            ]
        ];
    }

    protected function upsertModule(array $addon, bool $installed, bool $enabled): void
    {
        if (! Schema::hasTable('registered_modules')) {
            return;
        }

        $manifest = $addon['meta'];
        $menu = $manifest['menu'] ?? [];

        $moduleData = [
            'name' => $addon['name'],
            'type' => 'addon',
            'version' => $addon['version'],
            'author' => $addon['author'],
            'description' => $addon['description'],
            'route' => $this->resolveAddonRoute($manifest, $addon['slug']),
            'icon' => $menu['icon'] ?? ($manifest['icon'] ?? 'bi-puzzle'),
            'menu_title' => $menu['title'] ?? $addon['name'],
            'menu_order' => (int) ($menu['order'] ?? 100),
            'enabled' => $enabled,
            'installed' => $installed,
            'meta' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
            'installed_at' => now(),
            'updated_at' => now(),
        ];

        $query = DB::table('registered_modules')->where('slug', $addon['slug']);
        if ($query->exists()) {
            $query->update($moduleData);
        } else {
            DB::table('registered_modules')->insert(array_merge(
                ['slug' => $addon['slug'], 'created_at' => now()],
                $moduleData
            ));
        }
    }

    protected function updateManifestState(array $addon, bool $enabled): void
    {
        $path = $this->manifestPath($addon['path']);
        if (! $path) {
            return;
        }

        try {
            $data = json_decode(File::get($path), true) ?: [];
            $data['enabled'] = $enabled;
            if (array_key_exists('active', $data)) {
                $data['active'] = $enabled;
            }

            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        } catch (\Throwable $e) {
            Log::warning('Falha ao atualizar estado do manifesto do addon', [
                'module' => $addon['slug'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function registeredModule(string $slug): ?object
    {
        try {
            return Schema::hasTable('registered_modules')
                ? DB::table('registered_modules')->where('slug', $slug)->first()
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function manifestPath(string $dir): ?string
    {
        foreach (['module.json', 'addon.json'] as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function callHook(array $addon, string $hook): void
    {
        $callable = data_get($addon, "hooks.{$hook}");
        if (! is_string($callable) || ! str_contains($callable, '::')) {
            return;
        }

        [$class, $method] = explode('::', $callable, 2);
        if (class_exists($class) && method_exists($class, $method)) {
            $class::$method($addon);
        }
    }

    protected function ok(string $message): array
    {
        return ['ok' => true, 'success' => true, 'message' => $message, 'reload_menu' => true];
    }

    protected function fail(string $message): array
    {
        return ['ok' => false, 'success' => false, 'error' => $message, 'message' => $message, 'reload_menu' => false];
    }
}
