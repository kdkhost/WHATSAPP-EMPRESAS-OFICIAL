<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\CacheManager\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CacheManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/CacheManager/Resources/views'), 'cachemanager');

        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])
            ->prefix('admin/cache')
            ->name('admin.cache.')
            ->group(function () {
                $ctrl = \App\Modules\CacheManager\Http\Controllers\CacheManagerController::class;
                Route::get('/',          [$ctrl, 'index'])->name('index');
                Route::get('/stats',     [$ctrl, 'stats'])->name('stats');
                Route::post('/clear-all', [$ctrl, 'clearAll'])->name('clear-all');
                Route::post('/optimize',  [$ctrl, 'optimize'])->name('optimize');
                Route::post('/auto',      [$ctrl, 'toggleAuto'])->name('auto');
                Route::post('/{type}',    [$ctrl, 'clear'])->name('clear');
            });
    }
}
