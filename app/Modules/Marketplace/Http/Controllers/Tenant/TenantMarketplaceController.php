<?php

namespace App\Modules\Marketplace\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\MarketplaceAddon;
use App\Modules\Marketplace\Services\AddonMarketplaceService;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\TenantBillingService;
use App\Services\AddonManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMarketplaceController extends Controller
{
    public function __construct(
        protected AddonMarketplaceService $marketplace,
        protected TenantBillingService $billing,
        protected AddonManagerService $addons,
    ) {}

    protected function tenant(): Tenant
    {
        $tenant = Tenant::query()->where('user_id', Auth::id())->first();

        abort_if(! $tenant, 403, 'Voce nao possui tenant ativo.');

        return $tenant;
    }

    public function index()
    {
        $tenant = $this->tenant();

        // Sincroniza modulos do sistema que tem secao marketplace no manifest
        foreach ($this->addons->discover() as $sysAddon) {
            $meta = $sysAddon['meta'] ?? [];
            if (! empty($meta['marketplace'])) {
                $addon = MarketplaceAddon::query()->firstOrNew(
                    ['slug' => $sysAddon['slug']]
                );
                $addon->fill([
                    'name' => $sysAddon['name'],
                    'description' => $sysAddon['description'] ?: 'Modulo do sistema',
                    'version' => $sysAddon['version'],
                    'price' => (float) data_get($meta, 'marketplace.price', 0),
                    'category' => data_get($meta, 'marketplace.category', 'Sistema'),
                    'billing_cycle' => data_get($meta, 'marketplace.billing_cycle', 'monthly'),
                    'manifest' => $meta,
                    'is_active' => true,
                    'is_installed' => true,
                ]);
                $addon->save();
            }
        }

        $catalog = $this->marketplace->tenantCatalog($tenant);
        $myAddons = $tenant->addons()->with('addon')->latest()->get();

        // Descobre addons do sistema SEM secao marketplace (nao aparecem no catalogo)
        $catalogSlugs = $catalog->pluck('slug')->all();
        $systemAddons = collect($this->addons->discover())
            ->reject(fn ($a) => in_array($a['slug'], $catalogSlugs, true))
            ->values()
            ->all();

        return view('marketplace::tenant.index', compact('tenant', 'catalog', 'myAddons', 'systemAddons'));
    }

    public function submit(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $submission = $this->marketplace->submitZip($tenant, (int) Auth::id(), $data['package']);

        return response()->json([
            'ok' => true,
            'message' => 'Pacote recebido. O administrador precisa aprovar antes de publicar.',
            'submission' => $submission,
        ]);
    }

    public function subscribe(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $addon = MarketplaceAddon::query()->findOrFail($id);
        $tenantAddon = $this->marketplace->subscribeTenant($tenant, $addon, $this->billing);

        return response()->json([
            'ok' => true,
            'message' => (float) $addon->price > 0
                ? 'Addon reservado. A liberacao ocorre apos o pagamento da fatura do tenant.'
                : 'Addon liberado para a sua conta.',
            'tenant_addon' => $tenantAddon->fresh('addon'),
        ]);
    }

    public function rent(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate(['slug' => 'required|string']);

        $systemAddon = $this->addons->findBySlug($data['slug']);
        if (! $systemAddon) {
            return response()->json(['ok' => false, 'error' => 'Modulo nao encontrado no sistema.'], 404);
        }

        $meta = $systemAddon['meta'] ?? [];
        $price = (float) data_get($meta, 'marketplace.price', 0);
        $category = data_get($meta, 'marketplace.category', 'Sistema');

        // Cria ou atualiza o registro de marketplace para este addon do sistema
        $addon = MarketplaceAddon::query()->firstOrNew(
            ['slug' => $systemAddon['slug']]
        );
        $addon->fill([
            'name' => $systemAddon['name'],
            'description' => $systemAddon['description'] ?: 'Modulo do sistema',
            'version' => $systemAddon['version'],
            'price' => $price,
            'category' => $category,
            'billing_cycle' => data_get($meta, 'marketplace.billing_cycle', 'monthly'),
            'manifest' => $meta,
            'is_active' => true,
            'is_installed' => true,
        ]);
        $addon->save();

        $billingMode = $request->input('billing_mode');
        $tenantAddon = $this->marketplace->subscribeTenant($tenant, $addon, $this->billing, $billingMode);

        // Se gratuito, ativa o modulo imediatamente
        if ($price <= 0) {
            $this->addons->activate($systemAddon['slug']);
            return response()->json([
                'ok' => true,
                'message' => 'Modulo instalado e ativado com sucesso.',
                'tenant_addon' => $tenantAddon->fresh('addon'),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Modulo reservado. A liberacao ocorre apos o pagamento da fatura do tenant.',
            'tenant_addon' => $tenantAddon->fresh('addon'),
        ]);
    }

    public function deactivate(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $tenantAddon = \App\Modules\Tenancy\Models\TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($tenantAddon->status !== 'active') {
            return response()->json(['ok' => false, 'error' => 'Addon nao esta ativo.'], 422);
        }

        $tenantAddon->update([
            'status' => 'inactive',
            'suspended_at' => now(),
        ]);

        $slug = $tenantAddon->addon?->slug;
        if ($slug) {
            $this->addons->deactivate($slug);
        }

        return response()->json(['ok' => true, 'message' => 'Addon desativado com sucesso.']);
    }

    public function uninstall(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $tenantAddon = \App\Modules\Tenancy\Models\TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $slug = $tenantAddon->addon?->slug;
        $tenantAddon->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        if ($slug) {
            $this->addons->deactivate($slug);
        }

        return response()->json(['ok' => true, 'message' => 'Addon desinstalado com sucesso.']);
    }
}
