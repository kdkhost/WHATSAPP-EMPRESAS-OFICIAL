<?php

namespace App\Modules\Crm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/crm')->name('admin.crm.')->group(function () {
            Route::get('/',          [\App\Modules\Crm\Http\Controllers\CrmController::class, 'index'])->name('index');
            Route::get('/contacts',  [\App\Modules\Crm\Http\Controllers\CrmController::class, 'contacts'])->name('contacts');
            Route::get('/funnel',    [\App\Modules\Crm\Http\Controllers\CrmController::class, 'funnel'])->name('funnel');
        });
    }
}
