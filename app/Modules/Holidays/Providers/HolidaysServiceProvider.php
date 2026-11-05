<?php

namespace App\Modules\Holidays\Providers;

use App\Modules\Holidays\Services\HolidayService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HolidaysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HolidayService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Holidays/Resources/views'), 'holidays');

        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/holidays')->name('admin.holidays.')->group(function () {
            $ctrl = \App\Modules\Holidays\Http\Controllers\HolidaysController::class;
            Route::get('/',                [$ctrl, 'index'])->name('index');
            Route::get('/events',          [$ctrl, 'calendarEvents'])->name('events');
            Route::post('/',               [$ctrl, 'store'])->name('store');
            Route::put('/{id}',            [$ctrl, 'update'])->name('update');
            Route::delete('/{id}',         [$ctrl, 'destroy'])->name('destroy');
            Route::post('/import-brazilian', [$ctrl, 'importBrazilian'])->name('import.brazilian');
        });
    }
}
