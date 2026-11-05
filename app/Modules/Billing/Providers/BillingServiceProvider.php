<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Console\Commands\ProcessBillingScheduleCommand;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Billing\Services\BillingMessageHistoryService;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingDispatcher::class);
        $this->app->singleton(BillingMessageHistoryService::class);
        $this->app->singleton(\App\Modules\Billing\Services\InvoiceSettlementService::class);
        $this->commands([
            ProcessBillingScheduleCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Billing/Resources/views'), 'billing');

        // Job: a cada 1 minuto, processa envios agendados vencidos (pacing inteligente)
        CronManager::register(
            name: 'billing.dispatch_due',
            callback: fn () => app(BillingDispatcher::class)->dispatchDue(),
            intervalMinutes: 1,
            description: 'Envia cobrancas agendadas via Evolution API com pacing inteligente (3-5 min).'
        );

        // Job: a cada 30 minutos, agenda envios para faturas que ainda nao tem dispatches
        CronManager::register(
            name: 'billing.schedule_invoices',
            callback: fn () => app(BillingDispatcher::class)->scheduleNewInvoices(),
            intervalMinutes: 30,
            description: 'Cria envios futuros (-3d, -1d, 0d, +1d, +3d, +7d, +15d) para novas faturas.'
        );

        // Job: a cada 60 minutos, atualiza status de pagamento via gateways
        CronManager::register(
            name: 'billing.poll_gateways',
            callback: fn () => app(BillingDispatcher::class)->pollGateways(),
            intervalMinutes: 60,
            description: 'Faz polling nos gateways para faturas ainda pendentes (fallback ao webhook).'
        );

        CronManager::register(
            name: 'billing.message_history.cleanup',
            callback: fn () => app(BillingMessageHistoryService::class)->cleanup(),
            intervalMinutes: 1440,
            description: 'Remove historico de mensagens de cobranca com mais de 14 dias.'
        );

        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/billing')->name('admin.billing.')->group(function () {
            Route::get('/invoices', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('/invoices/criar', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('/invoices', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('/invoices/{id}', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'show'])->name('invoices.show');
            Route::post('/invoices/{id}/send', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'sendNow'])->name('invoices.send');
            Route::post('/invoices/{id}/settle', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'settleInvoice'])->name('invoices.settle');
            Route::post('/invoices/{id}/refund', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'refundInvoice'])->name('invoices.refund');
            Route::get('/invoices/{id}/installment-context', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'installmentContext'])->name('invoices.installment-context');
            Route::post('/invoices/bulk-settle', [\App\Modules\Billing\Http\Controllers\InvoiceController::class, 'bulkSettle'])->name('invoices.bulk-settle');

            // Customers CRUD
            Route::get('/customers', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'index'])->name('customers.index');
            Route::post('/customers/sync-whmcs', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'syncWhmcs'])->name('customers.sync-whmcs');
            Route::get('/customers/criar', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'create'])->name('customers.create');
            Route::post('/customers', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'store'])->name('customers.store');
            Route::get('/customers/{id}/editar', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'edit'])->name('customers.edit');
            Route::put('/customers/{id}', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'update'])->name('customers.update');
            Route::delete('/customers/{id}', [\App\Modules\Billing\Http\Controllers\CustomerController::class, 'destroy'])->name('customers.destroy');

            Route::get('/dispatches', [\App\Modules\Billing\Http\Controllers\DispatchController::class, 'index'])->name('dispatches.index');
            Route::get('/message-history', [\App\Modules\Billing\Http\Controllers\DispatchController::class, 'messageHistory'])->name('message-history.index');
            Route::get('/calendar', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'index'])->name('calendar');
            Route::get('/calendar/events', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'events'])->name('calendar.events');
            Route::post('/calendar/events/move', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'moveEvent'])->name('calendar.events.move');
            Route::post('/calendar/invoices', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'storeInvoice'])->name('calendar.invoices.store');
            Route::put('/calendar/invoices/{id}', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'updateInvoice'])->name('calendar.invoices.update');
            Route::post('/calendar/events/cancel', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'cancelEvent'])->name('calendar.events.cancel');
            Route::get('/calendar/invoice-installments/{id}', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'checkInstallments'])->name('calendar.invoice-installments');
            Route::get('/calendar/customers/search', [\App\Modules\Billing\Http\Controllers\CalendarController::class, 'searchCustomers'])->name('calendar.customers.search');
        });
    }
}
