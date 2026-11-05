<?php

namespace App\Modules\Reports\Providers;

use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Relatorios em PDF agendados.
 *
 * Gera relatorio semanal/mensal (receita, inadimplencia, envios) e envia por email ao admin.
 *
 * Tipos de relatorio:
 *  - Receita por periodo
 *  - Inadimplencia (vencidos vs total)
 *  - Envios por canal (WhatsApp vs email)
 *  - Clientes novos
 *  - Tenants ativos/inativos (superadmin)
 */
class ReportsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/reports')->name('admin.reports.')->group(function () {
            Route::get('/', fn () => view('admin.reports.index'))->name('index');
        });

        CronManager::register(
            name: 'reports.weekly',
            callback: fn () => null, // TODO: gerar e enviar
            intervalMinutes: 10080, // 7 dias
            description: 'Gera relatorio semanal e envia por email ao admin.'
        );
    }
}
