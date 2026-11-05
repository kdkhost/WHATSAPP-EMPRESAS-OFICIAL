<?php

namespace App\Modules\DataTable\Providers;

use App\Modules\DataTable\Services\DataTableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * DataTable server-side - rotas genericas para tabelas com whitelist.
 *
 * Cada tabela suportada precisa estar no whitelist abaixo com suas
 * colunas pesquisaveis para evitar SQL injection / vazamento.
 */
class DataTableServiceProvider extends ServiceProvider
{
    /**
     * Whitelist de tabelas + colunas pesquisaveis + colunas exibidas.
     * Apenas estas tabelas podem ser consultadas via /admin/datatable/{table}.
     */
    public const TABLES = [
        'invoices' => [
            'searchable' => ['number', 'description'],
            'columns'    => ['id', 'number', 'description', 'amount', 'due_date', 'status', 'gateway', 'created_at'],
        ],
        'customers' => [
            'searchable' => ['name', 'email', 'document', 'whatsapp'],
            'columns'    => ['id', 'name', 'email', 'document', 'whatsapp', 'cidade', 'uf', 'created_at'],
        ],
        'whatsapp_messages' => [
            'searchable' => ['to', 'message'],
            'columns'    => ['id', 'to', 'message', 'status', 'direction', 'created_at'],
        ],
        'security_events' => [
            'searchable' => ['rule_code', 'ip', 'uri'],
            'columns'    => ['id', 'rule_code', 'severity', 'action_taken', 'ip', 'method', 'uri', 'created_at'],
        ],
    ];

    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/datatable')->name('admin.datatable.')->group(function () {
            Route::get('/{table}', function (Request $request, string $table) {
                $config = self::TABLES[$table] ?? null;
                abort_unless($config, 404, 'Tabela nao permitida.');

                return DataTableService::from($table)
                    ->searchable($config['searchable'])
                    ->orderable($config['columns'])
                    ->handle($request);
            })->name('query');

            Route::get('/{table}/export', function (Request $request, string $table) {
                $config = self::TABLES[$table] ?? null;
                abort_unless($config, 404, 'Tabela nao permitida.');

                return DataTableService::from($table)
                    ->searchable($config['searchable'])
                    ->exportCsv($request, $table . '-' . now()->format('Y-m-d-His') . '.csv');
            })->name('export');
        });
    }
}
