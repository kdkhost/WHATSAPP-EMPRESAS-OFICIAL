<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kanban generico - serve para qualquer contexto:
 *   menus, categorias, fotos, modulos, cards, etc.
 *
 * URL base: /admin/kanban/{slug}
 */
class KanbanController extends Controller
{
    private array $contextLabels = [
        'menus'      => ['name' => 'Menus do site',         'desc' => 'Organize os itens dos menus do frontend e admin em colunas. Arraste para reordenar e mover entre grupos.', 'icon' => 'bi-list-ul'],
        'categorias' => ['name' => 'Categorias',            'desc' => 'Categorias gerais do conteudo. Cada coluna agrupa categorias relacionadas.',                              'icon' => 'bi-tags'],
        'cards'      => ['name' => 'Cards',                 'desc' => 'Blocos de conteudo destacavel para a home e paginas internas.',                                          'icon' => 'bi-card-heading'],
        'fotos'      => ['name' => 'Fotos / Galeria',       'desc' => 'Albuns e galerias de imagens organizados em colunas.',                                                   'icon' => 'bi-images'],
        'modulos'    => ['name' => 'Modulos',               'desc' => 'Modulos do sistema agrupados por estado (instalado, ativo, em teste).',                                  'icon' => 'bi-puzzle'],
    ];

    public function index(string $slug)
    {
        $board = DB::table('kanban_boards')->where('slug', $slug)->first();
        if (! $board) {
            $id = DB::table('kanban_boards')->insertGetId([
                'slug' => $slug, 'name' => ucfirst($slug), 'context' => $slug,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $board = DB::table('kanban_boards')->where('id', $id)->first();
        }

        $columns = DB::table('kanban_columns')->where('board_id', $board->id)->orderBy('sort')->get();
        $cards   = DB::table('kanban_cards')
            ->whereIn('column_id', $columns->pluck('id'))
            ->orderBy('sort')->get();

        $meta = $this->contextLabels[$slug] ?? ['name' => $board->name, 'desc' => 'Quadro Kanban generico.', 'icon' => 'bi-kanban'];

        $stats = [
            'columns'     => $columns->count(),
            'cards'       => $cards->count(),
            'with_image'  => $cards->where('image', '!=', null)->count(),
            'last_update' => $cards->max('updated_at'),
        ];

        return view('admin.kanban.index', compact('board', 'columns', 'cards', 'meta', 'stats'));
    }

    /* ============================================================
     | CARDS
     ============================================================ */

    public function moveCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_id'   => 'required|integer',
            'column_id' => 'required|integer',
            'sort'      => 'required|integer',
        ]);
        DB::table('kanban_cards')->where('id', $data['card_id'])->update([
            'column_id'  => $data['column_id'],
            'sort'       => $data['sort'],
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function storeCard(string $slug, Request $request): JsonResponse
    {
        $data = $request->validate([
            'column_id' => 'required|integer',
            'title'     => 'required|string|max:120',
            'content'   => 'nullable|string',
            'image'     => 'nullable|string|max:500',
        ]);

        $card = $data + [
            'sort'       => (int) DB::table('kanban_cards')->where('column_id', $data['column_id'])->max('sort') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('kanban_cards')->insertGetId($card);
        $created = DB::table('kanban_cards')->where('id', $id)->first();

        return response()->json(['ok' => true, 'card' => $created]);
    }

    public function updateCard(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'     => 'sometimes|string|max:120',
            'content'   => 'nullable|string',
            'image'     => 'nullable|string|max:500',
            'column_id' => 'sometimes|integer',
        ]);
        if (empty($data)) {
            return response()->json(['ok' => false, 'message' => 'Nada para atualizar.'], 422);
        }
        DB::table('kanban_cards')->where('id', $id)->update($data + ['updated_at' => now()]);
        return response()->json(['ok' => true, 'card' => DB::table('kanban_cards')->where('id', $id)->first()]);
    }

    public function destroyCard(int $id): JsonResponse
    {
        DB::table('kanban_cards')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    /* ============================================================
     | COLUMNS
     ============================================================ */

    public function storeColumn(string $slug, Request $request): JsonResponse
    {
        $board = DB::table('kanban_boards')->where('slug', $slug)->first();
        if (! $board) {
            return response()->json(['ok' => false, 'message' => 'Quadro nao encontrado.'], 404);
        }

        $data = $request->validate([
            'name'  => 'required|string|max:80',
            'color' => 'nullable|string|max:30',
        ]);

        $id = DB::table('kanban_columns')->insertGetId([
            'board_id' => $board->id,
            'name'     => $data['name'],
            'color'    => $data['color'] ?? 'primary',
            'sort'     => (int) DB::table('kanban_columns')->where('board_id', $board->id)->max('sort') + 1,
        ]);

        return response()->json(['ok' => true, 'column' => DB::table('kanban_columns')->where('id', $id)->first()]);
    }

    public function updateColumn(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:80',
            'color' => 'nullable|string|max:30',
            'sort'  => 'sometimes|integer',
        ]);
        if (empty($data)) {
            return response()->json(['ok' => false, 'message' => 'Nada para atualizar.'], 422);
        }
        DB::table('kanban_columns')->where('id', $id)->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroyColumn(int $id): JsonResponse
    {
        DB::table('kanban_columns')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }
}
