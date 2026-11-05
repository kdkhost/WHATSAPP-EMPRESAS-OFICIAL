<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function index(SettingsManager $s)
    {
        $seo = [
            'title'       => $s->get('seo.title'),
            'description' => $s->get('seo.description'),
            'keywords'    => $s->get('seo.keywords'),
            'hashtags'    => $s->get('seo.hashtags'),
            'og_image'    => $s->get('seo.og_image'),
            'ga_id'       => $s->get('seo.ga_id'),
            'gads_id'     => $s->get('seo.gads_id'),
            'gtm_id'      => $s->get('seo.gtm_id'),
            'fb_pixel'    => $s->get('seo.fb_pixel'),
        ];
        return view('admin.seo.index', compact('seo'));
    }

    public function update(Request $request, SettingsManager $s)
    {
        $data = $request->validate([
            'title'       => 'nullable|string|max:120',
            'description' => 'nullable|string|max:255',
            'keywords'    => 'nullable|string|max:500',
            'hashtags'    => 'nullable|string|max:500',
            'og_image'    => 'nullable|string',
            'ga_id'       => 'nullable|string|max:30',
            'gads_id'     => 'nullable|string|max:30',
            'gtm_id'      => 'nullable|string|max:30',
            'fb_pixel'    => 'nullable|string|max:30',
        ]);
        foreach ($data as $k => $v) $s->set("seo.{$k}", $v);
        return response()->json(['ok' => true]);
    }

    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ([url('/'), url('/login'), url('/cadastro')] as $u) {
            $xml .= "<url><loc>".htmlspecialchars($u)."</loc></url>";
        }
        $xml .= '</urlset>';
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: " . url('/sitemap.xml') . "\n";
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
