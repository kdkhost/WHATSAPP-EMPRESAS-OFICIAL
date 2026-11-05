<?php

namespace App\Modules\Pwa\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PwaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Rotas publicas PWA
        Route::middleware(['web'])->group(function () {
            Route::get('/manifest.json', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
            Route::get('/site.webmanifest', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.site-manifest');
            Route::get('/sw.js', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');
            Route::get('/service-worker.js', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
            Route::get('/pwa/splash', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'splash'])->name('pwa.splash');
            Route::get('/pwa', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'entry'])->name('pwa.entry');
            Route::get('/pwa/login', [\App\Http\Controllers\Auth\LoginController::class, 'showPwa'])->name('pwa.login');
        });

        // Rotas privadas PWA (dispatcher context-aware: tenant primeiro, depois admin)
        Route::middleware(['web'])
            ->prefix('pwa')
            ->name('pwa.')
            ->group(function () {
                Route::get('/dashboard', [\App\Modules\Pwa\Http\Controllers\PwaController::class, 'dashboard'])->name('dashboard');

                Route::get('/mensagens', function () {
                    if (auth()->check()) {
                        if (Route::has('tenant.whatsapp.messages')) {
                            return redirect()->route('tenant.whatsapp.messages');
                        }
                        if (Route::has('tenant.messages')) {
                            return redirect()->route('tenant.messages');
                        }
                        if (Route::has('tenant.instances')) {
                            return redirect()->route('tenant.instances');
                        }
                        if (Route::has('tenant.dashboard')) {
                            return redirect()->route('tenant.dashboard');
                        }
                        return redirect('/painel');
                    }
                    if (auth('admin')->check()) {
                        if (Route::has('admin.whatsapp.messages')) {
                            return redirect()->route('admin.whatsapp.messages');
                        }
                        if (Route::has('admin.whatsapp.conversations')) {
                            return redirect()->route('admin.whatsapp.conversations');
                        }
                        if (Route::has('admin.whatsapp.chats')) {
                            return redirect()->route('admin.whatsapp.chats');
                        }
                        if (Route::has('admin.whatsapp.instance')) {
                            return redirect()->route('admin.whatsapp.instance');
                        }
                        return Route::has('admin.dashboard') ? redirect()->route('admin.dashboard') : redirect('/admin');
                    }
                    return Route::has('login') ? redirect()->route('login') : redirect('/login');
                })->name('messages');

                Route::get('/cobrancas', function () {
                    if (auth()->check()) {
                        if (Route::has('tenant.invoices')) {
                            return redirect()->route('tenant.invoices');
                        }
                        if (Route::has('tenant.billing')) {
                            return redirect()->route('tenant.billing');
                        }
                        if (Route::has('tenant.customers')) {
                            return redirect()->route('tenant.customers');
                        }
                        if (Route::has('tenant.dashboard')) {
                            return redirect()->route('tenant.dashboard');
                        }
                        return redirect('/painel');
                    }
                    if (auth('admin')->check()) {
                        if (Route::has('admin.billing.invoices.index')) {
                            return redirect()->route('admin.billing.invoices.index');
                        }
                        if (Route::has('admin.billing.index')) {
                            return redirect()->route('admin.billing.index');
                        }
                        if (Route::has('admin.invoices.index')) {
                            return redirect()->route('admin.invoices.index');
                        }
                        if (Route::has('admin.billing.customers.index')) {
                            return redirect()->route('admin.billing.customers.index');
                        }
                        if (Route::has('admin.billing.dashboard')) {
                            return redirect()->route('admin.billing.dashboard');
                        }
                        return Route::has('admin.dashboard') ? redirect()->route('admin.dashboard') : redirect('/admin');
                    }
                    return Route::has('login') ? redirect()->route('login') : redirect('/login');
                })->name('billing');

                Route::get('/instancia', function () {
                    if (auth()->check()) {
                        if (Route::has('tenant.instances')) {
                            return redirect()->route('tenant.instances');
                        }
                        if (Route::has('tenant.whatsapp.instance')) {
                            return redirect()->route('tenant.whatsapp.instance');
                        }
                        if (Route::has('tenant.dashboard')) {
                            return redirect()->route('tenant.dashboard');
                        }
                        return redirect('/painel');
                    }
                    if (auth('admin')->check()) {
                        if (Route::has('admin.whatsapp.instance')) {
                            return redirect()->route('admin.whatsapp.instance');
                        }
                        return Route::has('admin.dashboard') ? redirect()->route('admin.dashboard') : redirect('/admin');
                    }
                    return Route::has('login') ? redirect()->route('login') : redirect('/login');
                })->name('instance');

                Route::get('/perfil', function () {
                    if (auth()->check()) {
                        if (Route::has('tenant.profile')) {
                            return redirect()->route('tenant.profile');
                        }
                        if (Route::has('tenant.dashboard')) {
                            return redirect()->route('tenant.dashboard');
                        }
                        return redirect('/painel');
                    }
                    if (auth('admin')->check()) {
                        if (Route::has('admin.profile')) {
                            return redirect()->route('admin.profile');
                        }
                        if (Route::has('admin.settings.profile')) {
                            return redirect()->route('admin.settings.profile');
                        }
                        return Route::has('admin.dashboard') ? redirect()->route('admin.dashboard') : redirect('/admin');
                    }
                    return Route::has('login') ? redirect()->route('login') : redirect('/login');
                })->name('profile');
            });

        // Rotas admin de configuracao PWA
        Route::middleware(['web', 'admin', '2fa', 'admin.cache'])
            ->prefix('admin/pwa')
            ->name('admin.pwa.')
            ->group(function () {
                $ctrl = \App\Modules\Pwa\Http\Controllers\PwaAdminController::class;
                Route::get('/', [$ctrl, 'index'])->name('index');
                Route::post('/', [$ctrl, 'update'])->name('update');
                Route::post('/icons', [$ctrl, 'uploadIcons'])->name('icons.upload');
                Route::post('/cache/refresh', [$ctrl, 'refreshCacheVersion'])->name('cache.refresh');

                // Menu footer configuravel
                Route::get('/menu', [$ctrl, 'menuIndex'])->name('menu.index');
                Route::post('/menu', [$ctrl, 'menuStore'])->name('menu.store');
                Route::put('/menu/{id}', [$ctrl, 'menuUpdate'])->name('menu.update');
                Route::delete('/menu/{id}', [$ctrl, 'menuDestroy'])->name('menu.destroy');
                Route::post('/menu/reorder', [$ctrl, 'menuReorder'])->name('menu.reorder');
            });
    }
}
