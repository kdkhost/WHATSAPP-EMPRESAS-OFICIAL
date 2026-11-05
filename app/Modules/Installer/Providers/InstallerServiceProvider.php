<?php

namespace App\Modules\Installer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InstallerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Installer/Resources/views'), 'installer');

        // Redirecionar /install para o instalador standalone (PHP puro, sem middlewares)
        Route::middleware(['web', \App\Modules\Installer\Http\Middleware\RedirectIfInstalled::class])
            ->get('install', function () {
                return redirect('/installer.php');
            })->name('install.index');

        Route::middleware(['web', \App\Modules\Installer\Http\Middleware\RedirectIfInstalled::class])
            ->prefix('install')
            ->name('install.')
            ->group(function () {
                $ctrl = \App\Modules\Installer\Http\Controllers\InstallerController::class;

                Route::get('/requirements', [$ctrl, 'checkRequirements'])->name('requirements');
                Route::post('/database',    [$ctrl, 'testDatabase'])->name('database.test');
                Route::post('/config',      [$ctrl, 'saveConfig'])->name('config.save');
                Route::post('/migrate',     [$ctrl, 'runMigrations'])->name('migrate');
                Route::post('/admin',       [$ctrl, 'createSuperAdmin'])->name('admin.create');
                Route::post('/finalize',    [$ctrl, 'finalize'])->name('finalize');
            });
    }
}
