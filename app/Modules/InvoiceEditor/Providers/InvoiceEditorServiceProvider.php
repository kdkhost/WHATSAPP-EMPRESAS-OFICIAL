<?php

namespace App\Modules\InvoiceEditor\Providers;

use App\Modules\Permissions\Models\Permission;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Modulo Editor de Fatura - CRUD completo de faturas com:
 *  - Criacao/edicao via Summernote (descricao rica)
 *  - Selecao de cliente com busca AJAX
 *  - Selecao de gateway de pagamento
 *  - Preview da cobranca antes de enviar
 *  - Geracao automatica de link de pagamento
 *  - Envio direto via WhatsApp apos salvar
 */
class InvoiceEditorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/InvoiceEditor/Resources/views'), 'invoice-editor');

        // Registra permissions
        try {
            Permission::registerCrud('invoices', 'Faturas', 'Editor de Fatura');
            Permission::register('invoices', 'send', 'Enviar fatura via WhatsApp', 'Editor de Fatura');
            Permission::register('invoices', 'generate_link', 'Gerar link de pagamento', 'Editor de Fatura');
        } catch (\Throwable) {}

        // Rotas admin
        Route::middleware(['web', 'admin', '2fa', 'module.permission:invoices.view'])
            ->prefix('admin/invoice-editor')
            ->name('admin.invoice-editor.')
            ->group(function () {
                $ctrl = \App\Modules\InvoiceEditor\Http\Controllers\InvoiceEditorController::class;
                Route::get('/',              [$ctrl, 'index'])->name('index');
                Route::get('/criar',         [$ctrl, 'create'])->name('create');
                Route::post('/',             [$ctrl, 'store'])->name('store');
                Route::get('/{id}/editar',   [$ctrl, 'edit'])->name('edit');
                Route::put('/{id}',          [$ctrl, 'update'])->name('update');
                Route::delete('/{id}',       [$ctrl, 'destroy'])->name('destroy');
                Route::post('/{id}/send',    [$ctrl, 'sendWhatsApp'])->name('send');
                Route::post('/{id}/payment-link', [$ctrl, 'generatePaymentLink'])->name('payment-link');
                Route::get('/customers/search', [$ctrl, 'searchCustomers'])->name('customers.search');
                Route::post('/{id}/duplicate',[$ctrl, 'duplicate'])->name('duplicate');
                Route::post('/{id}/anticipate-last', [$ctrl, 'anticipateLast'])->name('anticipate-last');
            });
    }
}
