<?php

namespace App\Http\Controllers;

use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Settings\Services\PublicPageContentService;
use App\Modules\WhiteLabel\Services\TenantWhiteLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PublicSiteController extends Controller
{
    public function home(Request $request, SettingsManager $settings, TenantWhiteLabelService $whiteLabel)
    {
        if ($whiteLabel->tenantForHost($request->getHost())) {
            return redirect(Auth::check() ? '/painel' : '/login');
        }

        $slides = $this->safeGet('slides', ['active' => true], 'sort');
        $testimonials = $this->safeGet('testimonials', ['active' => true], 'sort');
        $popups = $this->safeGet('popups', ['active' => true]);

        return view('frontend.home', compact('slides', 'testimonials', 'popups'));
    }

    /**
     * API publica de dados do site para o React/Vue.
     */
    public function siteData(SettingsManager $settings, PublicPageContentService $pages)
    {
        return response()->json([
            'app' => [
                'name'        => $settings->get('app.name', config('app.name')),
                'description' => $settings->get('seo.description'),
                'logo'        => $settings->get('app.logo'),
                'favicon'     => $settings->get('app.favicon'),
                'whatsapp'    => $settings->get('app.whatsapp_support'),
                'email'       => $settings->get('app.email_support'),
            ],
            'slides'       => $this->safeGet('slides', ['active' => true], 'sort'),
            'testimonials' => $this->safeGet('testimonials', ['active' => true], 'sort'),
            'plans'        => $this->safeGet('plans', ['active' => true], 'sort')
                ->map(function ($p) {
                    $p->features = is_string($p->features) ? json_decode($p->features, true) : ($p->features ?? []);
                    return $p;
                })
                ->values(),
            'theme' => [
                'primary'   => $settings->get('theme.primary', '#0d6efd'),
                'secondary' => $settings->get('theme.secondary', '#6610f2'),
            ],
            'pages'        => $pages->all(),
            'pages_status' => $pages->pageStatuses(),
        ]);
    }

    public function maintenance(SettingsManager $settings)
    {
        return view('frontend.maintenance', [
            'title'              => $settings->get('maintenance.title', 'Em manutencao'),
            'message'            => $settings->get('maintenance.message'),
            'until_at'           => $settings->get('maintenance.until_at'),
            'logo'               => $settings->get('app.logo'),
            'background_image'   => $settings->get('maintenance.background_image', ''),
            'background_opacity' => $settings->get('maintenance.background_opacity', 50),
            'overlay_color'      => $settings->get('maintenance.overlay_color', '#000000'),
            'text_color'         => $settings->get('maintenance.text_color', '#ffffff'),
        ]);
    }

    protected function safeGet(string $table, array $where = [], string $orderBy = 'id'): \Illuminate\Support\Collection
    {
        try {
            return DB::table($table)->where($where)->orderBy($orderBy)->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
