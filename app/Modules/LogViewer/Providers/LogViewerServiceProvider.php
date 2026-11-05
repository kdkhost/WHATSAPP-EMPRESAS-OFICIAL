<?php

namespace App\Modules\LogViewer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LogViewerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/LogViewer/Resources/views'), 'log-viewer');

        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/logs')->name('admin.logs.')->group(function () {
            $ctrl = \App\Modules\LogViewer\Http\Controllers\LogViewerController::class;
            Route::get('/',                [$ctrl, 'index'])->name('index');
            Route::get('/tail',            [$ctrl, 'tail'])->name('tail');
            Route::post('/clear',          [$ctrl, 'clear'])->name('clear');
            Route::get('/check-permissions', [$ctrl, 'checkPermissions'])->name('check-permissions');
        });
    }
}
