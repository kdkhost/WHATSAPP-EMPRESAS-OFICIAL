<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PreloadSettings;
use App\Support\ManagedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreloadController extends Controller
{
    public function index()
    {
        $settings = PreloadSettings::active() ?? new PreloadSettings();
        return view('admin.settings.preload', compact('settings'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'style' => 'required|in:spinner,pulse,dots,wave,gradient,particles,logo,custom',
            'background_type' => 'required|in:solid,gradient,image,video',
            'background_color' => 'required|string|max:20',
            'background_gradient_start' => 'nullable|string|max:20',
            'background_gradient_end' => 'nullable|string|max:20',
            'background_gradient_angle' => 'nullable|integer|min:0|max:360',
            'background_image' => 'nullable|string|max:500',
            'background_video' => 'nullable|string|max:500',
            'logo' => 'nullable|string|max:500',
            'show_logo' => 'boolean',
            'show_text' => 'boolean',
            'text' => 'nullable|string|max:200',
            'text_color' => 'required|string|max:20',
            'spinner_color' => 'required|string|max:20',
            'accent_color' => 'required|string|max:20',
            'animation_speed' => 'required|integer|min:100|max:5000',
            'font_family' => 'required|string|max:200',
            'font_size' => 'required|integer|min:10|max:48',
            'show_progress_bar' => 'boolean',
            'progress_bar_color' => 'required|string|max:20',
            'blur_background' => 'boolean',
            'blur_amount' => 'required|integer|min:0|max:50',
            'custom_css' => 'nullable|string|max:10000',
            'custom_js' => 'nullable|string|max:10000',
            'enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Erro de Validação no PreloadController', [
                'errors' => $validator->errors()->toArray(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = PreloadSettings::first();
        
        if (!$settings) {
            $settings = new PreloadSettings();
        }

        $validated = $validator->validated();

        ManagedAsset::replaceIfChanged($settings->background_image, $validated['background_image'] ?? null);
        ManagedAsset::replaceIfChanged($settings->background_video, $validated['background_video'] ?? null);
        ManagedAsset::replaceIfChanged($settings->logo, $validated['logo'] ?? null);

        $settings->fill($validated);
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Configurações de preload atualizadas com sucesso!'
        ]);
    }

    public function preview(Request $request)
    {
        $settings = new PreloadSettings($request->all());
        return view('admin.settings.preload-preview', compact('settings'));
    }
}
