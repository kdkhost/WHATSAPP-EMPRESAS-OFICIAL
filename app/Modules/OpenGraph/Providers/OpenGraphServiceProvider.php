<?php

namespace App\Modules\OpenGraph\Providers;

use App\Modules\OpenGraph\Services\OgImageGenerator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OpenGraphServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OgImageGenerator::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/OpenGraph/Resources/views'), 'opengraph');

        // Rota publica para gerar imagem OG sob demanda
        Route::middleware('web')
            ->get('/og-image', [\App\Modules\OpenGraph\Http\Controllers\OpenGraphController::class, 'generateImage'])
            ->name('og.image');

        // Rotas admin
        Route::middleware(['web', 'admin', '2fa'])
            ->prefix('admin/opengraph')
            ->name('admin.opengraph.')
            ->group(function () {
                $ctrl = \App\Modules\OpenGraph\Http\Controllers\OpenGraphController::class;
                Route::get('/',        [$ctrl, 'index'])->name('index');
                Route::post('/',       [$ctrl, 'update'])->name('update');
                Route::post('/clear',  [$ctrl, 'clearCache'])->name('clear');
                Route::get('/preview', [$ctrl, 'preview'])->name('preview');
            });

        // Blade directive: @ogImage('titulo', 'subtitulo', 'slug')
        Blade::directive('ogImage', function ($expression) {
            return "<?php echo app(\\App\\Modules\\OpenGraph\\Services\\OgImageGenerator::class)->getOrGenerate({$expression}); ?>";
        });

        // Blade directive: @ogMeta('titulo', 'descricao', 'slug')
        Blade::directive('ogMeta', function ($expression) {
            return "<?php
                \$__ogArgs = [{$expression}];
                \$__ogTitle = \$__ogArgs[0] ?? config('app.name');
                \$__ogDesc = \$__ogArgs[1] ?? '';
                \$__ogSlug = \$__ogArgs[2] ?? null;
                \$__ogUrl = url()->current();
                \$__ogImg = url(app(\\App\\Modules\\OpenGraph\\Services\\OgImageGenerator::class)->getOrGenerate(\$__ogTitle, \$__ogDesc, \$__ogSlug));
                echo '<meta property=\"og:title\" content=\"' . e(\$__ogTitle) . '\">';
                echo '<meta property=\"og:description\" content=\"' . e(\$__ogDesc) . '\">';
                echo '<meta property=\"og:image\" content=\"' . \$__ogImg . '\">';
                echo '<meta property=\"og:url\" content=\"' . \$__ogUrl . '\">';
                echo '<meta property=\"og:type\" content=\"website\">';
                echo '<meta name=\"twitter:card\" content=\"summary_large_image\">';
                echo '<meta name=\"twitter:title\" content=\"' . e(\$__ogTitle) . '\">';
                echo '<meta name=\"twitter:description\" content=\"' . e(\$__ogDesc) . '\">';
                echo '<meta name=\"twitter:image\" content=\"' . \$__ogImg . '\">';
            ?>";
        });
    }
}
