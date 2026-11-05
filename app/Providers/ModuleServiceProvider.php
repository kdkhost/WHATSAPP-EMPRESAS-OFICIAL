<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Descobre e carrega addons de terceiros em app/Addons/<slug>/.
 * Cada addon deve conter um addon.json e uma classe AddonServiceProvider.
 *
 * addon.json:
 * {
 *   "name": "Meu Addon",
 *   "version": "1.0.0",
 *   "author": "Fulano",
 *   "provider": "App\\Addons\\meu_addon\\AddonServiceProvider",
 *   "enabled": true
 * }
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('modules.addons.autoload', true)) {
            return;
        }

        $path = config('modules.addons.path', app_path('Addons'));
        if (! is_dir($path)) {
            return;
        }

        foreach (File::directories($path) as $dir) {
            $manifest = $this->manifestPath($dir);
            if (! $manifest) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($manifest), true) ?: [];
            if (! $this->shouldLoadAddon($meta)) {
                continue;
            }
            $provider = $meta['provider'] ?? null;
            if ($provider && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        //
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

    protected function shouldLoadAddon(array $meta): bool
    {
        $slug = $meta['slug'] ?? null;

        try {
            if ($slug && Schema::hasTable('registered_modules')) {
                $record = DB::table('registered_modules')
                    ->where('slug', $slug)
                    ->where('installed', true)
                    ->first();

                if ($record) {
                    return (bool) $record->enabled;
                }
            }
        } catch (\Throwable) {
            //
        }

        return (bool) ($meta['enabled'] ?? $meta['active'] ?? true);
    }
}
