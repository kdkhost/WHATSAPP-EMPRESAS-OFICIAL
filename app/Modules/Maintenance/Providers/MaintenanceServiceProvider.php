<?php

namespace App\Modules\Maintenance\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MaintenanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/maintenance')->name('admin.maintenance.')->group(function () {
            Route::get('/',  [\App\Modules\Maintenance\Http\Controllers\MaintenanceController::class, 'index'])->name('index');
            Route::post('/', [\App\Modules\Maintenance\Http\Controllers\MaintenanceController::class, 'update'])->name('update');
            Route::post('/devices', [\App\Modules\Maintenance\Http\Controllers\MaintenanceController::class, 'storeDevice'])->name('devices.store');
            Route::delete('/devices/{id}', [\App\Modules\Maintenance\Http\Controllers\MaintenanceController::class, 'destroyDevice'])->name('devices.destroy');
        });
    }
}
