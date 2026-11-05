<?php

namespace App\Modules\Subscription\Providers;

use App\Modules\Subscription\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Cobranca recorrente para clientes finais do tenant.
 */
class SubscriptionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Subscription/Resources/views'), 'subscription');

        // Admin routes
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/subscriptions')->name('admin.subscriptions.')->group(function () {
            Route::get('/', fn () => view('subscription::index'))->name('index');
            Route::get('/data', [SubscriptionController::class, 'getData'])->name('data');
            Route::post('/plans', [SubscriptionController::class, 'storePlan'])->name('plans.store');
            Route::put('/plans/{id}', [SubscriptionController::class, 'updatePlan'])->name('plans.update');
            Route::delete('/plans/{id}', [SubscriptionController::class, 'deletePlan'])->name('plans.delete');
            Route::post('/subscribers', [SubscriptionController::class, 'storeSubscription'])->name('subscribers.store');
            Route::post('/{id}/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('cancel');
            Route::post('/{id}/reactivate', [SubscriptionController::class, 'reactivateSubscription'])->name('reactivate');
            Route::delete('/{id}', [SubscriptionController::class, 'deleteSubscription'])->name('delete');
        });

        // Tenant-facing routes
        Route::middleware(['web', 'auth', '2fa'])->prefix('painel/assinaturas')->name('tenant.subscriptions.')->group(function () {
            Route::get('/', fn () => 'Modulo de assinaturas recorrentes - em implementacao')->name('index');
        });
    }
}
