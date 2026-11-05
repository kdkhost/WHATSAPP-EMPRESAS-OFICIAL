<?php

namespace App\Modules\I18n\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

/**
 * Suporte multi-idioma (i18n).
 * Idiomas: pt_BR (padrao), en, es.
 *
 * Os arquivos de traducao ficam em app/Modules/I18n/Resources/lang/{locale}/
 * Detecta locale via: cookie > Accept-Language header > config padrao.
 */
class I18nServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(app_path('Modules/I18n/Resources/lang'), 'app');

        // Detecta idioma (sem usar session no boot - evita erro de encriptacao)
        try {
            $locale = request()->cookie('locale')
                ?? request()->getPreferredLanguage(['pt_BR', 'en', 'es'])
                ?? config('app.locale', 'pt_BR');
            App::setLocale($locale);
        } catch (\Throwable) {
            App::setLocale(config('app.locale', 'pt_BR'));
        }
    }
}
