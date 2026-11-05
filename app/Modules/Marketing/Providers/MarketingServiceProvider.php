<?php

namespace App\Modules\Marketing\Providers;

use App\Modules\Marketing\Http\Controllers\AdminMarketingController;
use App\Modules\Marketing\Http\Controllers\MarketingCampaignController;
use App\Modules\Marketing\Services\MarketingService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MarketingService::class);
    }

    public function boot(): void
    {
        // 1. Rotas Publicas de Marketing (PAGINA EXCLUSIVA - NAO FICA NO MENU)
        Route::middleware(['web'])->group(function () {
            Route::get('/promocao', [MarketingCampaignController::class, 'show'])->name('marketing.promocao');
            Route::get('/oferta',   [MarketingCampaignController::class, 'show'])->name('marketing.oferta');
            Route::post('/promocao/lead', [MarketingCampaignController::class, 'captureLead'])->name('marketing.lead');
        });

        // 2. Rotas do Painel Administrativo
        Route::middleware(['web', 'admin', '2fa'])
            ->prefix('admin/marketing')
            ->name('admin.marketing.')
            ->group(function () {
                Route::get('/',                   [AdminMarketingController::class, 'index'])->name('index');
                Route::post('/settings',          [AdminMarketingController::class, 'updateSettings'])->name('settings');
                Route::post('/leads/{id}/status', [AdminMarketingController::class, 'updateLeadStatus'])->name('leads.status');
                Route::delete('/leads/clear-all', [AdminMarketingController::class, 'clearAllLeads'])->name('leads.clear_all');
                Route::delete('/leads/{id}',      [AdminMarketingController::class, 'deleteLead'])->name('leads.destroy');
                Route::get('/leads/export',       [AdminMarketingController::class, 'exportLeads'])->name('leads.export');
            });
    }
}
