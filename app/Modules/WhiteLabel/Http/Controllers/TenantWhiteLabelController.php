<?php

namespace App\Modules\WhiteLabel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WhiteLabel\Services\TenantWhiteLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TenantWhiteLabelController extends Controller
{
    public function index(Request $request, TenantWhiteLabelService $whiteLabel, SettingsManager $settings)
    {
        $this->ensureEnabled($settings);
        $tenant = $this->tenant();
        $status = $whiteLabel->statusPayload($tenant, [
            'dns_target' => $tenant->custom_domain_dns_target ?: $whiteLabel->dnsTarget($request),
        ]);

        return view('whitelabel::tenant', compact('tenant', 'status'));
    }

    public function save(Request $request, TenantWhiteLabelService $whiteLabel, SettingsManager $settings): JsonResponse
    {
        $this->ensureEnabled($settings);
        $tenant = $this->tenant();
        $normalizedDomain = $whiteLabel->normalizeDomain($request->input('custom_domain'));

        $validator = Validator::make($request->all(), [
            'enabled'         => 'required|boolean',
            'brand_name'      => 'nullable|string|max:100',
            'slogan'          => 'nullable|string|max:200',
            'logo_url'        => 'nullable|string|max:500',
            'favicon_url'     => 'nullable|string|max:500',
            'primary_color'   => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'sidebar_color'   => 'nullable|string|max:7',
            'custom_domain'   => 'nullable|string|max:200',
            'custom_css'      => 'nullable|string|max:10000',
            'footer_text'     => 'nullable|string|max:200',
            'hide_powered_by' => 'required|boolean',
            'custom_login'    => 'required|boolean',
        ]);

        $validator->after(function ($validator) use ($request, $tenant, $whiteLabel, $normalizedDomain) {
            if ($request->filled('custom_domain') && ! $normalizedDomain) {
                $validator->errors()->add('custom_domain', 'Informe um dominio valido, sem caminho ou protocolo.');
                return;
            }

            if (! $normalizedDomain) {
                return;
            }

            if (in_array($normalizedDomain, $whiteLabel->reservedDomains($request), true)) {
                $validator->errors()->add('custom_domain', 'Este dominio esta reservado para o sistema principal.');
                return;
            }

            $alreadyInUse = Tenant::query()
                ->where('custom_domain', $normalizedDomain)
                ->whereKeyNot($tenant->id)
                ->exists();

            if ($alreadyInUse) {
                $validator->errors()->add('custom_domain', 'Este dominio ja esta vinculado a outra conta.');
            }
        });

        $data = $validator->validate();
        $data['custom_domain'] = $normalizedDomain;

        $tenant = $whiteLabel->save($tenant, $data, $request);

        return response()->json([
            'ok' => true,
            'status' => $whiteLabel->statusPayload($tenant, [
                'dns_target' => $tenant->custom_domain_dns_target ?: $whiteLabel->dnsTarget($request),
            ]),
        ]);
    }

    public function check(Request $request, TenantWhiteLabelService $whiteLabel, SettingsManager $settings): JsonResponse
    {
        $this->ensureEnabled($settings);
        $tenant = $this->tenant();

        return response()->json([
            'ok' => true,
            'status' => $whiteLabel->checkDomain($tenant, $request),
        ]);
    }

    public function status(Request $request, TenantWhiteLabelService $whiteLabel, SettingsManager $settings): JsonResponse
    {
        $this->ensureEnabled($settings);
        $tenant = $this->tenant();

        return response()->json([
            'ok' => true,
            'status' => $whiteLabel->statusPayload($tenant, [
                'dns_target' => $tenant->custom_domain_dns_target ?: $whiteLabel->dnsTarget($request),
            ]),
        ]);
    }

    protected function tenant(): Tenant
    {
        $tenant = Tenant::where('user_id', Auth::id())->first();
        if (! $tenant) {
            abort(403, 'Voce nao possui uma assinatura ativa.');
        }

        return $tenant;
    }

    protected function ensureEnabled(SettingsManager $settings): void
    {
        if (! $settings->get('whitelabel.tenant_enabled', true)) {
            abort(403, 'White Label para tenants esta desativado pelo superadmin.');
        }
    }
}
