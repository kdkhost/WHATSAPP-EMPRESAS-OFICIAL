<?php

namespace App\Modules\CustomerImport\Providers;

use App\Modules\CustomerImport\Services\CsvImporter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomerImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CsvImporter::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/CustomerImport/Resources/views'), 'customer-import');

        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/import')->name('admin.import.')->group(function () {
            $ctrl = \App\Modules\CustomerImport\Http\Controllers\ImportController::class;
            Route::get('/customers',         [$ctrl, 'index'])->name('customers');
            Route::post('/customers/preview',[$ctrl, 'preview'])->name('customers.preview');
            Route::post('/customers/import', [$ctrl, 'import'])->name('customers.import');
        });
    }
}
