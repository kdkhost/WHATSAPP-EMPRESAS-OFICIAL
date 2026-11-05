<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Support\ManagedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Configuracoes gerais do sistema: logo, favicon, nome, cores, LGPD,
 * Google Ads/Business, upload limits.
 */
class GeneralSettingsController extends Controller
{
    public function general(SettingsManager $s)
    {
        $settings = [
            'name'    => $s->get('app.name', config('app.name')),
            'logo'    => $s->get('app.logo'),
            'favicon' => $s->get('app.favicon'),
            'primary_color' => $s->get('app.primary_color', '#0d6efd'),
            'footer_text'   => $s->get('app.footer_text', ''),
        ];
        return view('admin.settings.general', compact('settings'));
    }

    public function updateGeneral(Request $request, SettingsManager $s): JsonResponse
    {
        $currentLogo = $s->get('app.logo');
        $currentFavicon = $s->get('app.favicon');

        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'logo'          => 'nullable|string',
            'favicon'       => 'nullable|string',
            'primary_color' => 'nullable|string|max:7',
            'footer_text'   => 'nullable|string|max:255',
        ]);

        ManagedAsset::replaceIfChanged($currentLogo, $data['logo'] ?? null);
        ManagedAsset::replaceIfChanged($currentFavicon, $data['favicon'] ?? null);

        foreach ($data as $k => $v) {
            $s->set("app.{$k}", $v);
        }

        return response()->json(['ok' => true]);
    }

    public function lgpd(SettingsManager $s)
    {
        $lgpd = [
            'enabled'      => $s->get('lgpd.enabled', true),
            'text'         => $s->get('lgpd.text', 'Utilizamos cookies para melhorar sua experiencia.'),
            'policy_url'   => $s->get('lgpd.policy_url', '/politica-de-privacidade'),
            'btn_accept'   => $s->get('lgpd.btn_accept', 'Aceitar'),
            'btn_decline'  => $s->get('lgpd.btn_decline', 'Recusar'),
            'format'       => $s->get('lgpd.format', 'footer'),
            'bg_color'     => $s->get('lgpd.bg_color', '#1a1a2e'),
            'text_color'   => $s->get('lgpd.text_color', '#ffffff'),
            'btn_bg_color' => $s->get('lgpd.btn_bg_color', '#198754'),
            'btn_text_color' => $s->get('lgpd.btn_text_color', '#ffffff'),
        ];
        return view('admin.settings.lgpd', compact('lgpd'));
    }

    public function updateLgpd(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'enabled'        => 'required|boolean',
            'text'           => 'required|string|max:500',
            'policy_url'     => 'required|string|max:255',
            'btn_accept'     => 'required|string|max:30',
            'btn_decline'    => 'required|string|max:30',
            'format'         => 'required|in:footer,top,popup,central',
            'bg_color'       => 'nullable|string|max:7',
            'text_color'     => 'nullable|string|max:7',
            'btn_bg_color'   => 'nullable|string|max:7',
            'btn_text_color' => 'nullable|string|max:7',
        ]);
        foreach ($data as $k => $v) {
            $s->set("lgpd.{$k}", $v, $k === 'enabled' ? 'bool' : 'string');
        }
        return response()->json(['ok' => true]);
    }

    public function google(SettingsManager $s)
    {
        $google = [
            'ga_id'             => $s->get('google.ga_id'),
            'gads_id'           => $s->get('google.gads_id'),
            'gtm_id'            => $s->get('google.gtm_id'),
            'business_name'     => $s->get('google.business_name'),
            'business_address'  => $s->get('google.business_address'),
            'business_phone'    => $s->get('google.business_phone'),
            'business_hours'    => $s->get('google.business_hours'),
            'reviews_enabled'   => $s->get('google.reviews_enabled', false),
        ];
        return view('admin.settings.google', compact('google'));
    }

    public function updateGoogle(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'ga_id'            => 'nullable|string|max:30',
            'gads_id'          => 'nullable|string|max:30',
            'gtm_id'           => 'nullable|string|max:30',
            'business_name'    => 'nullable|string|max:120',
            'business_address' => 'nullable|string|max:255',
            'business_phone'   => 'nullable|string|max:20',
            'business_hours'   => 'nullable|string|max:255',
            'reviews_enabled'  => 'nullable|boolean',
        ]);

        $data['ga_id'] = $this->normalizeTrackingId($data['ga_id'] ?? null, 'G-');
        $data['gads_id'] = $this->normalizeTrackingId($data['gads_id'] ?? null, 'AW-');
        $data['gtm_id'] = $this->normalizeTrackingId($data['gtm_id'] ?? null, 'GTM-');

        foreach ($data as $k => $v) {
            $s->set("google.{$k}", $v, $k === 'reviews_enabled' ? 'bool' : 'string');
        }
        return response()->json(['ok' => true]);
    }

    protected function normalizeTrackingId(?string $value, string $prefix): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = Str::upper(preg_replace('/[^A-Z0-9\-]/i', '', $value) ?? $value);
        $prefix = Str::upper($prefix);

        if (! str_starts_with($normalized, $prefix)) {
            $normalized = $prefix.ltrim($normalized, '-');
        }

        return $normalized;
    }

    public function upload(SettingsManager $s)
    {
        $upload = [
            'max_size_mb'   => $s->get('upload.max_size_mb', 50),
            'allowed_mimes' => $s->get('upload.allowed_mimes', 'image/jpeg,image/png,image/webp,application/pdf'),
        ];
        return view('admin.settings.upload', compact('upload'));
    }

    public function updateUpload(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'max_size_mb'   => 'required|integer|min:1|max:500',
            'allowed_mimes' => 'required|string',
        ]);
        $s->set('upload.max_size_mb', $data['max_size_mb'], 'int');
        $s->set('upload.allowed_mimes', $data['allowed_mimes']);
        return response()->json(['ok' => true]);
    }

    public function theme(SettingsManager $s)
    {
        $theme = [
            'primary'    => $s->get('theme.primary', '#5b4df6'),
            'secondary'  => $s->get('theme.secondary', '#7c3aed'),
            'success'    => $s->get('theme.success', '#198754'),
            'danger'     => $s->get('theme.danger', '#dc3545'),
            'warning'    => $s->get('theme.warning', '#ffc107'),
            'info'       => $s->get('theme.info', '#0dcaf0'),
            'dark'       => $s->get('theme.dark', '#212529'),
            'light'      => $s->get('theme.light', '#f8f9fa'),
            'sidebar_bg' => $s->get('theme.sidebar_bg', '#1a1d21'),
            'sidebar_active' => $s->get('theme.sidebar_active', '#5b4df6'),
            'navbar_bg'  => $s->get('theme.navbar_bg', '#f8fafc'),
            'body_bg'    => $s->get('theme.body_bg', '#eef2f7'),
        ];
        return view('admin.settings.theme', compact('theme'));
    }

    public function updateTheme(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'primary'        => 'required|string|max:7',
            'secondary'      => 'required|string|max:7',
            'success'        => 'required|string|max:7',
            'danger'         => 'required|string|max:7',
            'warning'        => 'required|string|max:7',
            'info'           => 'required|string|max:7',
            'dark'           => 'required|string|max:7',
            'light'          => 'required|string|max:7',
            'sidebar_bg'     => 'required|string|max:7',
            'sidebar_active' => 'required|string|max:7',
            'navbar_bg'      => 'required|string|max:7',
            'body_bg'        => 'required|string|max:7',
        ]);
        foreach ($data as $k => $v) {
            $s->set("theme.{$k}", $v);
        }
        return response()->json(['ok' => true, 'message' => 'Cores atualizadas!']);
    }
}
