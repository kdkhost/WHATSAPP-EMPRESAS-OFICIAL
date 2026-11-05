<?php

namespace App\Modules\Seo\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SeoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/settings/seo')->name('admin.seo.')->group(function () {
            Route::get('/',  [\App\Modules\Seo\Http\Controllers\SeoController::class, 'index'])->name('index');
            Route::post('/', [\App\Modules\Seo\Http\Controllers\SeoController::class, 'update'])->name('update');
        });

        // Sitemap publico
        Route::get('/sitemap.xml', [\App\Modules\Seo\Http\Controllers\SeoController::class, 'sitemap'])->name('sitemap');
        Route::get('/robots.txt',  [\App\Modules\Seo\Http\Controllers\SeoController::class, 'robots'])->name('robots');
    }
}
