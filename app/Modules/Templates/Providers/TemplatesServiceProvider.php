<?php

namespace App\Modules\Templates\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Templates/Resources/views'), 'templates');

        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/templates')->name('admin.templates.')->group(function () {
            Route::get('/',           [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'index'])->name('index');
            Route::get('/criar',      [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'create'])->name('create');
            Route::post('/',          [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'store'])->name('store');
            Route::get('/{id}/editar',[\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'edit'])->name('edit');
            Route::put('/{id}',       [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'update'])->name('update');
            Route::delete('/{id}',    [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/clonar', [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'clone'])->name('clone');
            Route::post('/preview',   [\App\Modules\Templates\Http\Controllers\TemplatesController::class, 'preview'])->name('preview');
        });
    }
}
