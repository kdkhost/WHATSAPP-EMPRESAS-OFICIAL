<?php

namespace App\Modules\Settings\Providers;

use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Settings\Services\PublicPageContentService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(PublicPageContentService::class);
    }

    public function boot(): void
    {
        // Helper @setting('app.logo')
        Blade::directive('setting', function ($expression) {
            return "<?php echo e(app(\\App\\Modules\\Settings\\Services\\SettingsManager::class)->get($expression)); ?>";
        });

        // Compartilha em todas as views
        View::composer('*', function ($view) {
            $view->with('settingsManager', app(SettingsManager::class));
        });

        // Rotas admin para configuracoes gerais
        Route::middleware(['web', 'admin', '2fa', 'module.permission:settings.view'])
            ->prefix('admin/settings')
            ->name('admin.settings.')
            ->group(function () {
                $general = \App\Modules\Settings\Http\Controllers\GeneralSettingsController::class;
                Route::get('/general',  [$general, 'general'])->name('general');
                Route::post('/general', [$general, 'updateGeneral'])->name('general.update');
                Route::get('/lgpd',     [$general, 'lgpd'])->name('lgpd');
                Route::post('/lgpd',    [$general, 'updateLgpd'])->name('lgpd.update');
                Route::get('/google',   [$general, 'google'])->name('google');
                Route::post('/google',  [$general, 'updateGoogle'])->name('google.update');
                Route::get('/upload',   [$general, 'upload'])->name('upload');
                Route::post('/upload',  [$general, 'updateUpload'])->name('upload.update');

                // Tema / Cores
                Route::get('/theme',  [$general, 'theme'])->name('theme');
                Route::post('/theme', [$general, 'updateTheme'])->name('theme.update');

                // Conteudo frontend (slides, popups, depoimentos)
                $content = \App\Modules\Settings\Http\Controllers\ContentController::class;
                Route::get('/slides',         [$content, 'slides'])->name('slides');
                Route::post('/slides',        [$content, 'storeSlide'])->name('slides.store');
                Route::put('/slides/{id}',    [$content, 'updateSlide'])->name('slides.update');
                Route::delete('/slides/{id}', [$content, 'destroySlide'])->name('slides.destroy');
                Route::post('/slides/reorder',     [$content, 'reorderSlides'])->name('slides.reorder');
                Route::post('/slides/{id}/toggle', [$content, 'toggleSlide'])->name('slides.toggle');

                Route::get('/popups',         [$content, 'popups'])->name('popups');
                Route::post('/popups',        [$content, 'storePopup'])->name('popups.store');
                Route::put('/popups/{id}',    [$content, 'updatePopup'])->name('popups.update');
                Route::post('/popups/{id}/toggle', [$content, 'togglePopup'])->name('popups.toggle');
                Route::delete('/popups/{id}', [$content, 'destroyPopup'])->name('popups.destroy');

                Route::get('/testimonials',         [$content, 'testimonials'])->name('testimonials');
                Route::post('/testimonials',        [$content, 'storeTestimonial'])->name('testimonials.store');
                Route::put('/testimonials/{id}',    [$content, 'updateTestimonial'])->name('testimonials.update');
                Route::post('/testimonials/{id}/toggle', [$content, 'toggleTestimonial'])->name('testimonials.toggle');
                Route::post('/testimonials/reorder',     [$content, 'reorderTestimonials'])->name('testimonials.reorder');
                Route::delete('/testimonials/{id}', [$content, 'destroyTestimonial'])->name('testimonials.destroy');

                Route::get('/pages',             [$content, 'pages'])->name('pages');
                Route::post('/pages/toggle',     [$content, 'togglePageStatus'])->name('pages.toggle');
                Route::post('/pages/toggle-all', [$content, 'setAllPagesStatus'])->name('pages.toggle-all');
                Route::post('/pages/{page}',     [$content, 'savePage'])->name('pages.save');
            });
    }
}
