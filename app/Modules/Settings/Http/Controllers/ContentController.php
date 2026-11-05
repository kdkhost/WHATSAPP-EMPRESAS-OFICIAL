<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\PublicPageContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD AJAX para conteudos do frontend: Slides, Popups, Depoimentos.
 */
class ContentController extends Controller
{
    public function pages(PublicPageContentService $pages)
    {
        $content = $pages->all();
        $catalog = $pages->pageCatalog();
        $statuses = $pages->pageStatuses();

        return view('admin.settings.pages', compact('content', 'catalog', 'statuses'));
    }

    public function togglePageStatus(Request $request, PublicPageContentService $pages): JsonResponse
    {
        $page = (string) $request->input('page');
        $catalog = $pages->pageCatalog();

        if (! array_key_exists($page, $catalog)) {
            return response()->json(['ok' => false, 'message' => 'Página não encontrada.'], 404);
        }

        $newStatus = $pages->togglePageStatus($page);
        $pageName = $catalog[$page]['name'] ?? $page;
        $label = $newStatus ? 'ativada' : 'desativada';

        return response()->json([
            'ok'        => true,
            'active'    => $newStatus,
            'message'   => "Página \"{$pageName}\" {$label} com sucesso!",
            'page'      => $page,
        ]);
    }

    public function setAllPagesStatus(Request $request, PublicPageContentService $pages): JsonResponse
    {
        $active = $request->boolean('active');
        $pages->setAllPageStatuses($active);
        $label = $active ? 'ativadas' : 'desativadas';

        return response()->json([
            'ok'      => true,
            'message' => "Todas as páginas públicas foram {$label} com sucesso!",
            'active'  => $active,
        ]);
    }

    public function savePage(Request $request, string $page, PublicPageContentService $pages): JsonResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'array'],
        ]);

        $pages->save($page, $payload['payload']);

        return response()->json(['ok' => true, 'message' => 'Pagina publica atualizada.']);
    }

    // ===================== SLIDES =====================
    public function slides()
    {
        $slides = DB::table('slides')->orderBy('sort')->get();
        $stats = [
            'total'    => $slides->count(),
            'active'   => $slides->where('active', true)->count(),
            'inactive' => $slides->where('active', false)->count(),
            'with_cta' => $slides->filter(fn ($s) => $s->cta_label && $s->cta_url)->count(),
        ];
        return view('admin.slides.index', compact('slides', 'stats'));
    }

    public function storeSlide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:120', 'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string', 'cta_label' => 'nullable|string|max:60',
            'cta_url' => 'nullable|string|max:255', 'animation' => 'nullable|in:fade,slide,zoom',
            'active' => 'nullable|boolean',
        ]);
        $data['sort'] = DB::table('slides')->max('sort') + 1;
        $data['active'] = $data['active'] ?? true;
        $id = DB::table('slides')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function updateSlide(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:120', 'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string', 'cta_label' => 'nullable|string|max:60',
            'cta_url' => 'nullable|string|max:255', 'animation' => 'nullable|in:fade,slide,zoom',
            'active' => 'nullable|boolean', 'sort' => 'nullable|integer',
        ]);
        DB::table('slides')->where('id', $id)->update($data + ['updated_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function destroySlide(int $id): JsonResponse
    {
        DB::table('slides')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    public function reorderSlides(Request $request): JsonResponse
    {
        $data = $request->validate(['order' => 'required|array']);
        foreach ($data['order'] as $position => $id) {
            DB::table('slides')->where('id', (int) $id)->update([
                'sort'       => $position,
                'updated_at' => now(),
            ]);
        }
        return response()->json(['ok' => true]);
    }

    public function toggleSlide(int $id): JsonResponse
    {
        $row = DB::table('slides')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Slide nao encontrado.'], 404);
        }
        DB::table('slides')->where('id', $id)->update([
            'active'     => ! $row->active,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'active' => ! $row->active]);
    }

    // ===================== POPUPS =====================
    public function popups()
    {
        $popups = DB::table('popups')->orderByDesc('id')->get();
        $now = now()->toDateString();
        $stats = [
            'total'      => $popups->count(),
            'active'     => $popups->where('active', true)->count(),
            'inactive'   => $popups->where('active', false)->count(),
            'scheduled'  => $popups->filter(fn ($p) => ($p->starts_at && $p->starts_at > $now) || ($p->ends_at && $p->ends_at < $now))->count(),
        ];
        return view('admin.popups.index', compact('popups', 'stats'));
    }

    public function storePopup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'trigger' => 'required|in:time,scroll,exit,click',
            'trigger_value' => 'nullable|integer|min:0', 'image' => 'nullable|string',
            'html' => 'nullable|string', 'cta_label' => 'nullable|string|max:60',
            'cta_url' => 'nullable|string|max:255', 'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date', 'active' => 'nullable|boolean',
        ]);
        $data['active'] = $data['active'] ?? true;
        $id = DB::table('popups')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function updatePopup(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'trigger' => 'required|in:time,scroll,exit,click',
            'trigger_value' => 'nullable|integer|min:0', 'image' => 'nullable|string',
            'html' => 'nullable|string', 'cta_label' => 'nullable|string|max:60',
            'cta_url' => 'nullable|string|max:255', 'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date', 'active' => 'nullable|boolean',
        ]);
        DB::table('popups')->where('id', $id)->update($data + ['updated_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function togglePopup(int $id): JsonResponse
    {
        $row = DB::table('popups')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Popup nao encontrado.'], 404);
        }
        DB::table('popups')->where('id', $id)->update([
            'active'     => ! $row->active,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'active' => ! $row->active]);
    }

    public function destroyPopup(int $id): JsonResponse
    {
        DB::table('popups')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    // ===================== DEPOIMENTOS =====================
    public function testimonials()
    {
        $testimonials = DB::table('testimonials')->orderBy('sort')->get();
        $stats = [
            'total'    => $testimonials->count(),
            'active'   => $testimonials->where('active', true)->count(),
            'avg'      => $testimonials->count() ? round($testimonials->avg('rating'), 2) : 0,
            'google'   => $testimonials->where('source', 'google')->count(),
        ];
        return view('admin.testimonials.index', compact('testimonials', 'stats'));
    }

    public function storeTestimonial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'role' => 'nullable|string|max:80',
            'avatar' => 'nullable|string', 'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string|max:500', 'source' => 'nullable|in:manual,google',
            'active' => 'nullable|boolean',
        ]);
        $data['sort'] = DB::table('testimonials')->max('sort') + 1;
        $data['active'] = $data['active'] ?? true;
        $data['source'] = $data['source'] ?? 'manual';
        $id = DB::table('testimonials')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function updateTestimonial(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'role' => 'nullable|string|max:80',
            'avatar' => 'nullable|string', 'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string|max:500', 'source' => 'nullable|in:manual,google',
            'active' => 'nullable|boolean',
        ]);
        DB::table('testimonials')->where('id', $id)->update($data + ['updated_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function toggleTestimonial(int $id): JsonResponse
    {
        $row = DB::table('testimonials')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Depoimento nao encontrado.'], 404);
        }
        DB::table('testimonials')->where('id', $id)->update([
            'active'     => ! $row->active,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'active' => ! $row->active]);
    }

    public function reorderTestimonials(Request $request): JsonResponse
    {
        $data = $request->validate(['order' => 'required|array']);
        foreach ($data['order'] as $position => $id) {
            DB::table('testimonials')->where('id', (int) $id)->update([
                'sort'       => $position,
                'updated_at' => now(),
            ]);
        }
        return response()->json(['ok' => true]);
    }

    public function destroyTestimonial(int $id): JsonResponse
    {
        DB::table('testimonials')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }
}
