<?php

namespace App\Modules\OpenGraph\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OpenGraph\Services\OgImageGenerator;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpenGraphController extends Controller
{
    /**
     * Pagina admin de configuracao do Open Graph.
     */
    public function index(SettingsManager $s)
    {
        $config = [
            'bg_color'     => $s->get('og.bg_color', $s->get('theme.primary', '#0d6efd')),
            'text_color'   => $s->get('og.text_color', '#ffffff'),
            'accent_color' => $s->get('og.accent_color', $s->get('theme.secondary', '#6610f2')),
            'cache_hours'  => $s->get('og.cache_hours', 24),
            'auto_generate' => $s->get('og.auto_generate', true),
        ];
        return view('opengraph::admin', compact('config'));
    }

    /**
     * Salvar configuracoes OG.
     */
    public function update(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'bg_color'      => 'required|string|max:7',
            'text_color'    => 'required|string|max:7',
            'accent_color'  => 'required|string|max:7',
            'cache_hours'   => 'required|integer|min:1|max:720',
            'auto_generate' => 'required|boolean',
        ]);

        foreach ($data as $k => $v) {
            $type = match ($k) {
                'cache_hours' => 'int',
                'auto_generate' => 'bool',
                default => 'string',
            };
            $s->set("og.{$k}", $v, $type);
        }

        return response()->json(['ok' => true, 'message' => 'Configuracoes OG salvas!']);
    }

    /**
     * Gerar imagem OG sob demanda (GET /og-image?title=...&subtitle=...&slug=...).
     */
    public function generateImage(Request $request, OgImageGenerator $generator): Response
    {
        $title = $request->input('title', config('app.name'));
        $subtitle = $request->input('subtitle');
        $slug = $request->input('slug');

        $path = $generator->generate($title, $subtitle, $slug);
        $fullPath = public_path(ltrim($path, '/'));

        if (!file_exists($fullPath)) {
            abort(500, 'Falha ao gerar imagem OG');
        }

        return response(file_get_contents($fullPath), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Limpar cache de imagens OG.
     */
    public function clearCache(OgImageGenerator $generator): JsonResponse
    {
        $count = $generator->clearCache();
        return response()->json(['ok' => true, 'message' => "{$count} imagens removidas do cache."]);
    }

    /**
     * Preview: gera imagem temporaria para visualizacao no admin.
     */
    public function preview(Request $request, OgImageGenerator $generator): Response
    {
        $title = $request->input('title', 'Titulo de exemplo');
        $subtitle = $request->input('subtitle', 'Subtitulo descritivo da pagina');

        $path = $generator->generate($title, $subtitle, 'preview-' . time());
        $fullPath = public_path(ltrim($path, '/'));

        $content = file_get_contents($fullPath);
        @unlink($fullPath); // Remove preview temporario

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
