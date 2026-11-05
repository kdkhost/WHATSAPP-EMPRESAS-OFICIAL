<?php

namespace App\Modules\EmailBilling\Providers;

use App\Modules\EmailBilling\Services\EmailTemplateRenderer;
use App\Modules\EmailBilling\Services\EmailLayoutRenderer;
use App\Modules\EmailBilling\Services\EmailBillingTenantResolver;
use App\Modules\EmailBilling\Services\SmtpMailer;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EmailBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmtpMailer::class);
        $this->app->singleton(EmailLayoutRenderer::class);
        $this->app->singleton(EmailTemplateRenderer::class);
        $this->app->singleton(EmailBillingTenantResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/EmailBilling/Resources/views'), 'email-billing');

        CronManager::register(
            name: 'email.queue.process',
            callback: fn () => app(SmtpMailer::class)->processQueue((int) config('modules.email_queue.batch_size', 100)),
            intervalMinutes: (int) config('modules.email_queue.interval_minutes', 1),
            description: 'Processa fila de emails com limite por tenant e retentativa com backoff.'
        );

        // Painel do tenant: /painel/email/* — usa guard web (mesmo que demais rotas /painel/*)
        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/email')->name('tenant.email.')->group(function () {
            $cfg = \App\Modules\EmailBilling\Http\Controllers\EmailConfigController::class;
            Route::get('/config',       [$cfg, 'show'])->name('config');
            Route::post('/config',      [$cfg, 'save'])->name('config.save');
            Route::post('/config/test', [$cfg, 'test'])->name('config.test');

            $tpl = \App\Modules\EmailBilling\Http\Controllers\EmailTemplatesController::class;
            Route::get('/templates',           [$tpl, 'index'])->name('templates');
            Route::get('/templates/criar',     [$tpl, 'create'])->name('templates.create');
            Route::post('/templates',          [$tpl, 'store'])->name('templates.store');
            Route::get('/templates/{id}',      [$tpl, 'edit'])->name('templates.edit');
            Route::put('/templates/{id}',      [$tpl, 'update'])->name('templates.update');
            Route::delete('/templates/{id}',   [$tpl, 'destroy'])->name('templates.destroy');
            Route::post('/templates/{id}/padrao', [$tpl, 'setDefault'])->name('templates.default');
            Route::post('/templates/preview',  [$tpl, 'preview'])->name('templates.preview');
        });

        // Painel administrativo: conta operacional do admin como tenant proprio.
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/email')->name('admin.email.')->group(function () {
            $cfg = \App\Modules\EmailBilling\Http\Controllers\EmailConfigController::class;
            Route::get('/config',       [$cfg, 'show'])->name('config');
            Route::post('/config',      [$cfg, 'save'])->name('config.save');
            Route::post('/config/test', [$cfg, 'test'])->name('config.test');

            $tpl = \App\Modules\EmailBilling\Http\Controllers\EmailTemplatesController::class;
            Route::get('/templates',           [$tpl, 'index'])->name('templates');
            Route::get('/templates/criar',     [$tpl, 'create'])->name('templates.create');
            Route::post('/templates',          [$tpl, 'store'])->name('templates.store');
            Route::get('/templates/{id}',      [$tpl, 'edit'])->name('templates.edit');
            Route::put('/templates/{id}',      [$tpl, 'update'])->name('templates.update');
            Route::delete('/templates/{id}',   [$tpl, 'destroy'])->name('templates.destroy');
            Route::post('/templates/{id}/padrao', [$tpl, 'setDefault'])->name('templates.default');
            Route::post('/templates/preview',  [$tpl, 'preview'])->name('templates.preview');
        });

        // Adiciona relacao emailConfig ao Tenant model
        \App\Modules\Tenancy\Models\Tenant::resolveRelationUsing('emailConfig', function ($tenant) {
            return $tenant->hasOne(\App\Modules\EmailBilling\Models\TenantEmailConfig::class);
        });
        \App\Modules\Tenancy\Models\Tenant::resolveRelationUsing('emailTemplates', function ($tenant) {
            return $tenant->hasMany(\App\Modules\EmailBilling\Models\EmailTemplate::class);
        });
    }
}
