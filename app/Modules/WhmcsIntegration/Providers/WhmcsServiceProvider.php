<?php

namespace App\Modules\WhmcsIntegration\Providers;

use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;
use App\Modules\WhmcsIntegration\Services\WhmcsServiceAutomation;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WhmcsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/WhmcsIntegration/Resources/views'), 'whmcs');

        // Webhook publico (sem auth, autenticado por token)
        Route::middleware('web')->get('/webhooks/whmcs/{token}',
            [\App\Modules\WhmcsIntegration\Http\Controllers\WhmcsController::class, 'webhookStatus']
        )->name('whmcs.webhook.status');

        Route::middleware('web')->post('/webhooks/whmcs/{token}',
            [\App\Modules\WhmcsIntegration\Http\Controllers\WhmcsController::class, 'webhook']
        )->name('whmcs.webhook');

        // Admin (apenas superadmin)
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/whmcs')->name('admin.whmcs.')->group(function () {
            $ctrl = \App\Modules\WhmcsIntegration\Http\Controllers\WhmcsController::class;
            Route::get('/',              [$ctrl, 'index'])->name('index');
            Route::post('/',             [$ctrl, 'store'])->name('store');
            Route::put('/{id}',          [$ctrl, 'update'])->name('update');
            Route::delete('/clean-orphans', [$ctrl, 'cleanOrphans'])->name('clean_orphans');
            Route::delete('/{id}',       [$ctrl, 'destroy'])->name('destroy');
            Route::post('/{id}/test',    [$ctrl, 'test'])->name('test');
            Route::get('/{id}/logs',     [$ctrl, 'logs'])->name('logs');
            Route::post('/{id}/sync',    [$ctrl, 'syncNow'])->name('sync');
            Route::get('/{id}/sync/status', [$ctrl, 'syncStatus'])->name('sync.status');
        });

        // Painel do Tenant
        Route::middleware(['web', 'auth', '2fa', 'tenant.addon:whmcs'])->prefix('painel/whmcs')->name('tenant.whmcs.')->group(function () {
            $ctrl = \App\Modules\WhmcsIntegration\Http\Controllers\WhmcsController::class;
            Route::get('/',              [$ctrl, 'index'])->name('index');
            Route::post('/',             [$ctrl, 'store'])->name('store');
            Route::put('/{id}',          [$ctrl, 'update'])->name('update');
            Route::delete('/{id}',       [$ctrl, 'destroy'])->name('destroy');
            Route::post('/{id}/test',    [$ctrl, 'test'])->name('test');
            Route::get('/{id}/logs',     [$ctrl, 'logs'])->name('logs');
            Route::post('/{id}/sync',    [$ctrl, 'syncNow'])->name('sync');
            Route::get('/{id}/sync/status', [$ctrl, 'syncStatus'])->name('sync.status');
        });

        // Rotas internas do addon (logs, configuracoes)
        Route::middleware(['web', 'auth', '2fa', 'tenant.addon:whmcs'])
            ->prefix('painel/addons/whmcs')
            ->name('tenant.addons.whmcs.')
            ->group(function () {
                $ctrl = \App\Modules\WhmcsIntegration\Http\Controllers\Tenant\WhmcsTenantController::class;
                Route::get('/logs', [$ctrl, 'logs'])->name('logs');
                Route::get('/configuracoes', [$ctrl, 'settings'])->name('settings');
                Route::delete('/limpar', [$ctrl, 'wipeData'])->name('wipe');
            });

        // Cron: sincronizacao automatica a cada 5 minutos
        CronManager::register(
            name: 'whmcs.auto_sync',
            callback: function () {
                $dispatcher = app(BillingDispatcher::class);

                foreach (WhmcsConnection::where('active', true)->get() as $conn) {
                    try {
                        // 1. Sincroniza clientes, faturas e servicos com o WHMCS
                        (new WhmcsClient($conn))->syncAll();

                        // 2. Apos sync, cancela IMEDIATAMENTE qualquer dispatch pendente
                        //    de fatura que agora consta como paga/cancelada no WHMCS.
                        //    Isso previne cobrancas indevidas para quem pagou antecipadamente.
                        $dispatcher->cancelPaidWhmcsInvoiceDispatches();

                        // 3. Agenda novos disparos (apenas para faturas nao pagas)
                        if ($conn->auto_send_invoices) {
                            $dispatcher->scheduleNewInvoices();
                        }

                        // 4. Automacao de suspensao/reativacao de servicos
                        app(WhmcsServiceAutomation::class)->run($conn);
                    } catch (\Throwable $e) {
                        $conn->forceFill([
                            'last_sync_at'     => now(),
                            'last_sync_status' => 'error',
                            'last_sync_error'  => $e->getMessage(),
                            'last_error'       => $e->getMessage(),
                        ])->save();
                    }
                }
            },
            intervalMinutes: 5,
            description: 'Sincroniza clientes e faturas com WHMCS e cancela cobrancas de faturas ja pagas.'
        );
    }
}
