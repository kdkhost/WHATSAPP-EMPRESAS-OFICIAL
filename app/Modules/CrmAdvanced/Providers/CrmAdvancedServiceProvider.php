<?php

namespace App\Modules\CrmAdvanced\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * CRM Avancado: tags, scoring, funil visual drag-drop, historico de interacoes.
 *
 * Funcionalidades:
 *  - Tags coloridas por cliente (VIP, inadimplente, novo, etc.)
 *  - Lead scoring automatico (baseado em pagamentos, interacoes, tempo)
 *  - Funil visual (drag-drop entre colunas) com metricas de conversao
 *  - Timeline de interacoes por cliente (WhatsApp, email, pagamento, contrato)
 *  - Segmentacao para campanhas (filtrar por tag, score, cidade, etc.)
 *  - Notas internas por cliente
 */
class CrmAdvancedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/crm-advanced')->name('admin.crm-advanced.')->group(function () {
            Route::get('/', fn () => view('admin.crm.funnel'))->name('funnel');
            Route::get('/timeline/{id}', fn ($id) => 'Timeline cliente ' . $id)->name('timeline');
        });
    }
}
