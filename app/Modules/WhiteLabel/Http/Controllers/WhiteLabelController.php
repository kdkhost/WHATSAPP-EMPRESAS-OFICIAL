<?php

namespace App\Modules\WhiteLabel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WhiteLabel\Services\TenantWhiteLabelService;
use App\Support\ManagedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhiteLabelController extends Controller
{
    public function index(TenantWhiteLabelService $whiteLabel)
    {
        $tenantDomains = Tenant::query()
            ->whereNotNull('custom_domain')
            ->orderByRaw("custom_domain_status = 'active' desc")
            ->orderByDesc('custom_domain_last_checked_at')
            ->limit(20)
            ->get();

        return view('whitelabel::index', [
            'tenantDomains' => $tenantDomains,
            'dnsTarget' => $whiteLabel->dnsTarget(request()),
        ]);
    }

    public function save(Request $request, SettingsManager $s): JsonResponse
    {
        $currentLogo = $s->get('whitelabel.logo_url');
        $currentFavicon = $s->get('whitelabel.favicon_url');

        $request->merge([
            'tenant_enabled' => $request->boolean('tenant_enabled') ? '1' : '0',
            'ssl_enabled' => $request->boolean('ssl_enabled') ? '1' : '0',
            'hide_powered_by' => $request->boolean('hide_powered_by') ? '1' : '0',
            'custom_login' => $request->boolean('custom_login') ? '1' : '0',
        ]);

        $data = $request->validate([
            'brand_name'      => 'nullable|string|max:100',
            'slogan'          => 'nullable|string|max:200',
            'logo_url'        => 'nullable|string|max:500',
            'favicon_url'     => 'nullable|string|max:500',
            'primary_color'   => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'sidebar_color'   => 'nullable|string|max:7',
            'custom_domain'   => 'nullable|string|max:200',
            'dns_target'      => 'nullable|string|max:200',
            'tenant_enabled'  => 'nullable|in:0,1',
            'ssl_enabled'     => 'nullable|in:0,1',
            'custom_css'      => 'nullable|string|max:10000',
            'footer_text'     => 'nullable|string|max:200',
            'hide_powered_by' => 'nullable|in:0,1',
            'custom_login'    => 'nullable|in:0,1',
            'cloudflare_api_token' => 'nullable|string|max:255',
            'cloudflare_zone_id'   => 'nullable|string|max:100',
        ]);

        ManagedAsset::replaceIfChanged($currentLogo, $data['logo_url'] ?? null);
        ManagedAsset::replaceIfChanged($currentFavicon, $data['favicon_url'] ?? null);

        foreach ($data as $key => $value) {
            $type = in_array($key, ['tenant_enabled', 'ssl_enabled', 'hide_powered_by', 'custom_login'], true) ? 'bool' : 'string';
            $s->set('whitelabel.'.$key, $value ?? '', $type);
        }

        return response()->json(['ok' => true]);
    }
}
