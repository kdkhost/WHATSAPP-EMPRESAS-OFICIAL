<?php

namespace App\Modules\Pwa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pwa\Models\PwaFooterMenuItem;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PwaAdminController extends Controller
{
    public function index(SettingsManager $s)
    {
        $pwa = [
            'name'             => $s->get('pwa.name', config('app.name')),
            'short_name'       => $s->get('pwa.short_name', 'Cobranca'),
            'description'      => $s->get('pwa.description'),
            'theme_color'      => $s->get('pwa.theme_color', '#0d6efd'),
            'background_color' => $s->get('pwa.background_color', '#ffffff'),
            'start_url'        => $s->get('pwa.start_url', '/?source=pwa'),
            'install_prompt'   => $s->get('pwa.install_prompt', true),
            'push_enabled'     => $s->get('pwa.push_enabled', false),
        ];
        return view('admin.pwa.index', compact('pwa'));
    }

    public function update(Request $request, SettingsManager $s)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:120',
            'short_name'       => 'required|string|max:30',
            'description'      => 'nullable|string|max:255',
            'theme_color'      => 'required|string|max:7',
            'background_color' => 'required|string|max:7',
            'start_url'        => 'required|string|max:120',
            'install_prompt'   => 'required|boolean',
            'push_enabled'     => 'required|boolean',
        ]);
        foreach ($data as $k => $v) {
            $type = in_array($k, ['install_prompt', 'push_enabled'], true) ? 'bool' : 'string';
            $s->set("pwa.{$k}", $v, $type);
        }
        $s->set('pwa.cache_version', (string) time());
        return response()->json(['ok' => true]);
    }

    public function refreshCacheVersion(SettingsManager $s): JsonResponse
    {
        $s->set('pwa.cache_version', (string) time());

        return response()->json(['ok' => true, 'message' => 'Versao de cache PWA atualizada.']);
    }

    public function uploadIcons(Request $request, SettingsManager $settings): JsonResponse
    {
        $request->validate([
            'icon' => 'required|file|max:5120|mimes:png,jpg,jpeg,webp,svg',
        ]);

        $file = $request->file('icon');
        $ext = strtolower($file->getClientOriginalExtension());
        $disk = Storage::disk('public');
        $baseDir = 'uploads/pwa/icons';
        $disk->makeDirectory($baseDir);

        if ($ext === 'svg') {
            $svg = (string) file_get_contents($file->getRealPath());
            if (preg_match('/<\s*script/i', $svg) || preg_match('/on\w+\s*=/i', $svg)) {
                return response()->json(['ok' => false, 'error' => 'SVG invalido para seguranca.'], 422);
            }
            $svgPath = $baseDir.'/icon.svg';
            $disk->put($svgPath, $svg);
            $settings->set('pwa.icon_svg', Storage::url($svgPath));
            $settings->set('pwa.cache_version', (string) time());

            return response()->json(['ok' => true, 'message' => 'Icone SVG salvo.', 'icon' => Storage::url($svgPath)]);
        }

        $raw = @file_get_contents($file->getRealPath());
        $image = $raw ? @imagecreatefromstring($raw) : false;
        if (! $image) {
            return response()->json(['ok' => false, 'error' => 'Arquivo de imagem invalido.'], 422);
        }

        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
        $urls = [];
        foreach ($sizes as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

            $srcW = imagesx($image);
            $srcH = imagesy($image);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $size, $size, $srcW, $srcH);

            $target = $baseDir.'/icon-'.$size.'.png';
            $full = storage_path('app/public/'.$target);
            imagepng($canvas, $full, 9);
            imagedestroy($canvas);
            $url = Storage::url($target);
            $urls[$size] = $url;
            $settings->set('pwa.icon_'.$size, $url);
        }

        imagedestroy($image);

        // Maskable defaults apontam para os tamanhos grandes gerados.
        $settings->set('pwa.maskable_icon_192', $urls[192] ?? '');
        $settings->set('pwa.maskable_icon_512', $urls[512] ?? '');
        $settings->set('pwa.icon', $urls[192] ?? ($urls[512] ?? ''));
        $settings->set('pwa.icon_192', $urls[192] ?? '');
        $settings->set('pwa.icon_512', $urls[512] ?? '');
        $settings->set('pwa.cache_version', (string) time());

        return response()->json([
            'ok' => true,
            'message' => 'Icones PWA salvos com sucesso.',
            'icons' => $urls,
            'maskable_192' => $urls[192] ?? null,
            'maskable_512' => $urls[512] ?? null,
        ]);
    }

    // === MENU FOOTER PWA ===
    public function menuIndex()
    {
        $items = PwaFooterMenuItem::ordered()->get();
        return view('admin.pwa.menu', compact('items'));
    }

    public function menuStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:60',
            'icon' => 'required|string|max:60',
            'url' => 'nullable|string|max:255',
            'route_name' => 'nullable|string|max:120',
            'position' => 'nullable|integer|min:0|max:255',
            'is_active' => 'nullable|boolean',
            'match_patterns' => 'nullable|string|max:500',
            'role' => 'nullable|string|in:tenant,admin,both',
        ]);

        $data['position'] = $data['position'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['match_patterns'] = $this->parsePatterns($data['match_patterns'] ?? '');

        $item = PwaFooterMenuItem::create($data);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function menuUpdate(Request $request, int $id): JsonResponse
    {
        $item = PwaFooterMenuItem::findOrFail($id);

        $data = $request->validate([
            'label' => 'required|string|max:60',
            'icon' => 'required|string|max:60',
            'url' => 'nullable|string|max:255',
            'route_name' => 'nullable|string|max:120',
            'position' => 'nullable|integer|min:0|max:255',
            'is_active' => 'nullable|boolean',
            'match_patterns' => 'nullable|string|max:500',
            'role' => 'nullable|string|in:tenant,admin,both',
        ]);

        $data['is_active'] = $request->boolean('is_active', $item->is_active);
        $data['match_patterns'] = $this->parsePatterns($data['match_patterns'] ?? '');

        $item->update($data);

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function menuDestroy(int $id): JsonResponse
    {
        $item = PwaFooterMenuItem::findOrFail($id);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function menuReorder(Request $request): JsonResponse
    {
        $order = $request->input('order', []);

        if (! is_array($order)) {
            return response()->json(['ok' => false, 'error' => 'Formato invalido.'], 422);
        }

        foreach ($order as $index => $id) {
            PwaFooterMenuItem::where('id', (int) $id)->update(['position' => $index + 1]);
        }

        return response()->json(['ok' => true]);
    }

    private function parsePatterns(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }
}
