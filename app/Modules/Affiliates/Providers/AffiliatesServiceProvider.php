<?php

namespace App\Modules\Affiliates\Providers;

use App\Modules\Affiliates\Services\AffiliateService;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AffiliatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AffiliateService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Affiliates/Resources/views'), 'affiliates');

        // Tracking publico /a/{slug} — com throttle para evitar inflacao artificial
        Route::middleware(['web', 'throttle:60,1'])->get('/a/{slug}',
            [\App\Modules\Affiliates\Http\Controllers\AffiliateController::class, 'track']
        )->name('affiliate.track');

        // Painel do afiliado
        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/afiliado')->name('tenant.affiliate.')->group(function () {
            $ctrl = \App\Modules\Affiliates\Http\Controllers\AffiliateController::class;
            Route::get('/',          [$ctrl, 'dashboard'])->name('dashboard');
            Route::post('/apply',    [$ctrl, 'apply'])->name('apply');
            Route::post('/withdraw', [$ctrl, 'requestWithdrawal'])->name('withdraw');
            Route::put('/settings',  [$ctrl, 'update'])->name('update');
        });

        // Admin
        Route::middleware(['web', 'admin', 'role:superadmin'])->prefix('admin/affiliates')->name('admin.affiliates.')->group(function () {
            $ctrl = \App\Modules\Affiliates\Http\Controllers\AffiliateAdminController::class;
            Route::get('/',                          [$ctrl, 'index'])->name('index');
            Route::get('/list',                      [$ctrl, 'affiliates'])->name('list');
            Route::post('/settings',                 [$ctrl, 'saveSettings'])->name('settings.save');
            Route::post('/store',                    [$ctrl, 'createAffiliate'])->name('store');
            Route::put('/{id}',                      [$ctrl, 'updateAffiliate'])->name('update');
            Route::post('/{id}/approve',             [$ctrl, 'approveAffiliate'])->name('approve');
            Route::post('/{id}/reject',              [$ctrl, 'rejectAffiliate'])->name('reject');
            Route::post('/{id}/pend',                [$ctrl, 'pendAffiliate'])->name('pend');
            Route::post('/commissions/{id}/approve', [$ctrl, 'approveCommission'])->name('commissions.approve');
            Route::post('/commissions/{id}/reject',  [$ctrl, 'rejectCommission'])->name('commissions.reject');
            Route::post('/withdrawals/{id}/approve', [$ctrl, 'approveWithdrawal'])->name('withdrawals.approve');
            Route::post('/withdrawals/{id}/reject',  [$ctrl, 'rejectWithdrawal'])->name('withdrawals.reject');
        });

        CronManager::register(
            name: 'affiliates.auto_approve',
            callback: fn () => app(AffiliateService::class)->autoApproveOldCommissions(),
            intervalMinutes: 720,
            description: 'Aprova automaticamente comissoes pendentes >7 dias.'
        );
    }
}
