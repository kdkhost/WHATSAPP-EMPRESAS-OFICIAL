<?php

namespace App\Modules\DataTable\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DataTable server-side processing service.
 *
 * Compativel com DataTables.js (jQuery) e equivalentes.
 * Suporta: paginacao, ordenacao, busca global, filtros por coluna, export CSV/Excel.
 *
 * Uso:
 *   return DataTableService::from('invoices')
 *       ->searchable(['number', 'description'])
 *       ->handle($request);
 */
class DataTableService
{
    protected Builder $query;
    protected array $searchable = [];
    protected array $orderable = [];

    public static function from(string $table): self
    {
        $instance = new self();
        $instance->query = DB::table($table);
        return $instance;
    }

    public static function fromQuery(Builder $query): self
    {
        $instance = new self();
        $instance->query = $query;
        return $instance;
    }

    public function searchable(array $columns): self
    {
        $this->searchable = $columns;
        return $this;
    }

    public function orderable(array $columns): self
    {
        $this->orderable = $columns;
        return $this;
    }

    public function handle(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $search = (string) $request->input('search.value', '');

        // Total sem filtro
        $totalRecords = (clone $this->query)->count();

        // Busca global
        if ($search !== '' && !empty($this->searchable)) {
            $this->query->where(function ($q) use ($search) {
                foreach ($this->searchable as $col) {
                    $q->orWhere($col, 'LIKE', "%{$search}%");
                }
            });
        }

        // Filtros por coluna
        $columns = $request->input('columns', []);
        foreach ($columns as $col) {
            $colSearch = $col['search']['value'] ?? '';
            $colName = $col['data'] ?? null;
            if ($colSearch !== '' && $colName && in_array($colName, $this->searchable, true)) {
                $this->query->where($colName, 'LIKE', "%{$colSearch}%");
            }
        }

        $filteredRecords = (clone $this->query)->count();

        // Ordenacao
        $orderCol = $request->input('order.0.column');
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($orderCol !== null && isset($columns[$orderCol]['data'])) {
            $this->query->orderBy($columns[$orderCol]['data'], $orderDir);
        } else {
            $this->query->orderByDesc('id');
        }

        // Paginacao
        $data = $this->query->offset($start)->limit($length)->get();

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    /** Export CSV de todos os registros (sem paginacao). */
    public function exportCsv(Request $request, string $filename = 'export.csv')
    {
        $search = (string) $request->input('search', '');
        if ($search !== '' && !empty($this->searchable)) {
            $this->query->where(function ($q) use ($search) {
                foreach ($this->searchable as $col) {
                    $q->orWhere($col, 'LIKE', "%{$search}%");
                }
            });
        }

        $rows = $this->query->limit(10000)->get();

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys((array) $rows->first()));
            }
            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
