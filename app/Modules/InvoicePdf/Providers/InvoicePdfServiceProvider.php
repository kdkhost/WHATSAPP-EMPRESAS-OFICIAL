<?php

namespace App\Modules\InvoicePdf\Providers;

use App\Models\Invoice;
use App\Modules\InvoicePdf\Services\InvoicePdfService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InvoicePdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InvoicePdfService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/InvoicePdf/Resources/views'), 'invoice-pdf');

        Route::middleware(['web', 'admin', '2fa'])->group(function () {
            Route::get('/admin/invoices/{id}/pdf', function (int $id, InvoicePdfService $svc) {
                $invoice = Invoice::findOrFail($id);
                $binary = $svc->generatePdfBinary($invoice);

                if ($binary !== false) {
                    return response($binary, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="fatura-'.$invoice->number.'.pdf"',
                    ]);
                }
                // Fallback: HTML printavel
                return response($svc->renderHtml($invoice));
            })->name('admin.invoices.pdf');
        });
    }
}
