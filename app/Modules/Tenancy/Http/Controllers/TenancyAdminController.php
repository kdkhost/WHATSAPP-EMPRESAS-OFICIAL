<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\ExtraInstancePrice;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\Tenancy\Services\TenantPurgeService;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Painel superadmin: gerencia planos, tenants, periodos de teste.
 * CRUD 100% AJAX.
 */
class TenancyAdminController extends Controller
{
    public function index()
    {
        $stats = [
            'plans'           => Plan::count(),
            'tenants_active'  => Tenant::where('status', 'active')->count(),
            'tenants_trial'   => Tenant::where('status', 'trial')->count(),
            'tenants_suspended'=> Tenant::where('status', 'suspended')->count(),
        ];
        $plans = Plan::withCount('tenants')->orderBy('sort')->get();
        $extraInstancePrices = ExtraInstancePrice::with('plan')->orderBy('sort')->orderBy('id')->get();
        return view('tenancy::admin.index', compact('stats', 'plans', 'extraInstancePrices'));
    }

    public function plans()
    {
        return response()->json(Plan::orderBy('sort')->get());
    }

    public function storePlan(Request $request): JsonResponse
    {
        $request->merge([
            'price' => Format::decimal($request->input('price')),
            'extra_instance_price' => Format::decimal($request->input('extra_instance_price')),
        ]);

        $data = $request->validate([
            'name'                  => 'required|string|max:80',
            'price'                 => 'required|numeric|min:0',
            'extra_instance_price'  => 'nullable|numeric|min:0',
            'billing_cycle'         => 'required|in:monthly,quarterly,semiannual,annual',
            'trial_days'            => 'required|integer|min:0|max:90',
            'max_instances'         => 'required|integer|min:1',
            'max_customers'         => 'required|integer|min:1',
            'max_invoices_month'    => 'required|integer|min:1',
            'max_messages_month'    => 'required|integer|min:1',
            'max_contracts_month'   => 'nullable|integer|min:0',
            'custom_gateway'        => 'required|boolean',
            'custom_templates'      => 'required|boolean',
            'installment_enabled'   => 'required|boolean',
            'fee_passthrough'       => 'required|boolean',
            'contracts_module'      => 'nullable|boolean',
            'description'           => 'nullable|string',
            'highlight'             => 'boolean',
            'badge'                 => 'nullable|string|max:50',
            'features'              => 'nullable|string', // Vem como texto por linhas
        ]);

        $data['slug'] = Str::slug($data['name']);

        // Transforma textarea em JSON array
        $featuresArray = [];
        if (!empty($data['features'])) {
            $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $data['features']))));
        }

        // Checkbox "Incluir módulo Contratos" — gerencia a feature 'contracts'
        $contractsEnabled = (bool) ($data['contracts_module'] ?? false);
        $featuresArray = array_values(array_filter($featuresArray, fn ($f) => mb_strtolower($f) !== 'contracts'));
        if ($contractsEnabled) {
            $featuresArray[] = 'contracts';
        }
        $data['features'] = json_encode($featuresArray);
        unset($data['contracts_module']);

        $plan = Plan::create($data);
        return response()->json(['ok' => true, 'plan' => $plan]);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $request->merge([
            'price' => Format::decimal($request->input('price')),
            'extra_instance_price' => Format::decimal($request->input('extra_instance_price')),
        ]);

        $data = $request->validate([
            'name'                  => 'required|string|max:80',
            'price'                 => 'required|numeric|min:0',
            'extra_instance_price'  => 'nullable|numeric|min:0',
            'billing_cycle'         => 'required|in:monthly,quarterly,semiannual,annual',
            'trial_days'            => 'required|integer|min:0|max:90',
            'max_instances'         => 'required|integer|min:1',
            'max_customers'         => 'required|integer|min:1',
            'max_invoices_month'    => 'required|integer|min:1',
            'max_messages_month'    => 'required|integer|min:1',
            'max_contracts_month'   => 'nullable|integer|min:0',
            'custom_gateway'        => 'required|boolean',
            'custom_templates'      => 'required|boolean',
            'installment_enabled'   => 'required|boolean',
            'fee_passthrough'       => 'required|boolean',
            'contracts_module'      => 'nullable|boolean',
            'description'           => 'nullable|string',
            'active'                => 'nullable|boolean',
            'highlight'             => 'boolean',
            'badge'                 => 'nullable|string|max:50',
            'features'              => 'nullable|string', // Vem como texto por linhas
        ]);

        // Transforma textarea em JSON array
        $featuresArray = [];
        if (isset($data['features']) && !empty($data['features'])) {
            $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $data['features']))));
        }

        // Checkbox "Incluir módulo Contratos" — gerencia a feature 'contracts'
        $contractsEnabled = (bool) ($data['contracts_module'] ?? false);
        $featuresArray = array_values(array_filter($featuresArray, fn ($f) => mb_strtolower($f) !== 'contracts'));
        if ($contractsEnabled) {
            $featuresArray[] = 'contracts';
        }
        $data['features'] = json_encode($featuresArray);
        unset($data['contracts_module']);

        $plan->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroyPlan(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        if ($plan->tenants()->exists()) {
            return response()->json(['ok' => false, 'error' => 'Plano possui tenants ativos. Migre-os antes.'], 422);
        }
        $plan->delete();
        return response()->json(['ok' => true]);
    }

    public function extraInstancePrices(): JsonResponse
    {
        return response()->json(
            ExtraInstancePrice::with('plan')->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function storeExtraInstancePrice(Request $request): JsonResponse
    {
        $price = ExtraInstancePrice::create($this->validateExtraInstancePrice($request));

        return response()->json(['ok' => true, 'price' => $price->load('plan')]);
    }

    public function updateExtraInstancePrice(Request $request, int $id): JsonResponse
    {
        ExtraInstancePrice::findOrFail($id)->update($this->validateExtraInstancePrice($request));

        return response()->json(['ok' => true]);
    }

    public function destroyExtraInstancePrice(int $id): JsonResponse
    {
        ExtraInstancePrice::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function tenants(Request $request, TenantPurgeService $purger)
    {
        $allTenants = Tenant::with(['user', 'plan', 'instances', 'emailConfig'])->get();
        $nonBrazilianCount = $allTenants->filter(fn ($t) => ! $purger->isBrazilian($t))->count();

        $stats = [
            'total'          => $allTenants->count(),
            'active'         => $allTenants->whereIn('status', ['active', 'trial'])->count(),
            'suspended'      => $allTenants->where('status', 'suspended')->count(),
            'non_brazilians' => $nonBrazilianCount,
        ];

        $q = Tenant::with(['user', 'plan', 'instances', 'emailConfig'])->orderByDesc('id');

        if ($search = trim((string) $request->query('q'))) {
            $q->where(function ($builder) use ($search) {
                $builder->where('company_name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('document', 'like', "%{$search}%");
                    });
            });
        }

        if ($s = $request->query('status')) {
            if ($s === 'active') {
                $q->whereIn('status', ['active', 'trial']);
            } else {
                $q->where('status', $s);
            }
        }

        // Filtro rápido de Não-Brasileiros
        if ($request->query('filter') === 'non_brazilian') {
            $nonBrIds = $allTenants->filter(fn ($t) => ! $purger->isBrazilian($t))->pluck('id')->toArray();
            $q->whereIn('id', $nonBrIds);
        }

        $tenants = $q->paginate(25)->withQueryString();

        // Mapeia flag is_brazilian em cada item para renderização direta
        $tenants->getCollection()->transform(function ($tenant) use ($purger) {
            $tenant->is_brazilian = $purger->isBrazilian($tenant);
            return $tenant;
        });

        return view('tenancy::admin.tenants', compact('tenants', 'stats', 'nonBrazilianCount'));
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $newStatus = in_array($tenant->status, ['active', 'trial']) ? 'suspended' : 'active';

        $payload = ['status' => $newStatus];
        if ($newStatus === 'active' && ! $tenant->current_period_end) {
            $payload['current_period_start'] = now();
            $payload['current_period_end']   = now()->addMonth();
        }

        $tenant->update($payload);

        return response()->json([
            'ok'        => true,
            'status'    => $newStatus,
            'label'     => $newStatus === 'active' ? 'Ativo' : 'Suspenso',
            'message'   => "Tenant {$tenant->company_name} " . ($newStatus === 'active' ? 'ativado com sucesso.' : 'suspenso com sucesso.'),
        ]);
    }

    public function destroyTenant(int $id, TenantPurgeService $purger): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $result = $purger->purgeTenant($tenant);

            return response()->json([
                'ok'      => true,
                'message' => "Conta da empresa {$result['tenant_name']} e todos os dados vinculados foram removidos com sucesso.",
                'details' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error("Erro ao excluir tenant #{$id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok'    => false,
                'error' => 'Falha ao excluir tenant: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function purgeNonBrazilians(TenantPurgeService $purger): JsonResponse
    {
        try {
            $result = $purger->purgeNonBrazilians();

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error("Erro no expurgo de nao-brasileiros: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok'    => false,
                'error' => 'Falha no expurgo: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function showTenant(int $id)
    {
        $tenant = Tenant::with(['user', 'plan', 'instances', 'gateways', 'billingConfig', 'tenantInvoices'])->findOrFail($id);
        
        $affiliateStats = app(\App\Modules\Affiliates\Services\AffiliateService::class)->getTenantAffiliateStats($tenant);

        // Estatísticas financeiras e de faturas para gráficos interativos
        $invoices = $tenant->tenantInvoices;
        $totalPaid = (float) $invoices->where('status', 'paid')->sum('amount');
        $totalPending = (float) $invoices->where('status', 'pending')->sum('amount');
        $paidCount = $invoices->where('status', 'paid')->count();
        $pendingCount = $invoices->where('status', 'pending')->count();

        // Gráfico dos últimos 6 meses de faturamento do tenant
        $monthsLabels = [];
        $monthsRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthsLabels[] = ucfirst($monthDate->translatedFormat('M/y'));
            $rev = $invoices->filter(function ($inv) use ($monthDate) {
                return $inv->status === 'paid' 
                    && $inv->paid_at 
                    && \Carbon\Carbon::parse($inv->paid_at)->format('Y-m') === $monthDate->format('Y-m');
            })->sum('amount');
            $monthsRevenue[] = round((float) $rev, 2);
        }

        $analytics = [
            'total_paid'     => $totalPaid,
            'total_pending'  => $totalPending,
            'paid_count'     => $paidCount,
            'pending_count'  => $pendingCount,
            'months_labels'  => $monthsLabels,
            'months_revenue' => $monthsRevenue,
        ];

        return view('tenancy::admin.tenant-detail', compact('tenant', 'affiliateStats', 'analytics'));
    }

    public function saveTenantAffiliate(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::with('user')->findOrFail($id);

        $request->merge([
            'commission_pct'   => $request->filled('commission_pct') ? Format::decimal($request->input('commission_pct')) : null,
            'commission_fixed' => $request->filled('commission_fixed') ? Format::decimal($request->input('commission_fixed')) : null,
            'discount_pct'     => $request->filled('discount_pct') ? Format::decimal($request->input('discount_pct')) : null,
            'discount_fixed'   => $request->filled('discount_fixed') ? Format::decimal($request->input('discount_fixed')) : null,
            'min_withdrawal'   => $request->filled('min_withdrawal') ? Format::decimal($request->input('min_withdrawal')) : null,
        ]);

        $data = $request->validate([
            'status'           => 'required|in:active,pending,rejected,suspended',
            'commission_mode'  => 'required|in:percentage,fixed',
            'commission_pct'   => 'nullable|numeric|min:0|max:100',
            'commission_fixed' => 'nullable|numeric|min:0',
            'commission_type'  => 'required|in:recurring,first_payment,lifetime',
            'discount_mode'    => 'required|in:percentage,fixed',
            'discount_pct'     => 'nullable|numeric|min:0|max:100',
            'discount_fixed'   => 'nullable|numeric|min:0',
            'discount_type'    => 'required|in:first_payment,recurring',
            'min_withdrawal'   => 'nullable|numeric|min:1',
            'slug'             => 'nullable|string|max:60',
            'referral_code'    => 'nullable|string|max:20',
            'split_enabled'    => 'nullable|boolean',
            'split_pct'        => 'nullable|numeric|min:0|max:100',
            'mp_collector_id'  => 'nullable|string|max:60',
            'admin_notes'      => 'nullable|string|max:500',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $affiliate = app(\App\Modules\Affiliates\Services\AffiliateService::class)->syncTenantAsAffiliate($tenant, $data);

        return response()->json([
            'ok'        => true,
            'message'   => 'Programa de afiliado deste tenant configurado com sucesso!',
            'affiliate' => $affiliate,
        ]);
    }

    public function editTenant(int $id)
    {
        $tenant = Tenant::with(['user', 'plan'])->findOrFail($id);
        $plans = Plan::where('active', true)->orderBy('sort')->orderBy('name')->get();

        return view('tenancy::admin.tenant-edit', compact('tenant', 'plans'));
    }

    public function updateTenant(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::with('user')->findOrFail($id);

        $request->merge([
            'api_monthly_fee' => Format::decimal($request->input('api_monthly_fee')),
        ]);

        $data = $request->validate([
            'company_name' => 'required|string|max:160',
            'slug' => ['required', 'string', 'max:60', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active,suspended,canceled,cancelled',
            'trial_ends_at' => 'nullable|date',
            'current_period_start' => 'nullable|date',
            'current_period_end' => 'nullable|date|after_or_equal:current_period_start',
            'gateway' => 'nullable|string|max:30',
            'user_name' => 'required|string|max:120',
            'user_email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($tenant->user_id)],
            'api_enabled' => 'nullable|boolean',
            'api_monthly_fee' => 'nullable|numeric|min:0|max:999999.99',
            'api_notes' => 'nullable|string|max:500',
        ]);

        $status = $data['status'] === 'cancelled' ? 'canceled' : $data['status'];
        $settings = $tenant->settings ?? [];
        $settings['public_api'] = [
            'enabled' => (bool) ($data['api_enabled'] ?? false),
            'monthly_fee' => (float) ($data['api_monthly_fee'] ?? 0),
            'notes' => $data['api_notes'] ?? null,
            'updated_by_admin_at' => now()->toDateTimeString(),
        ];

        $tenant->update([
            'company_name' => $data['company_name'],
            'slug' => Str::slug($data['slug']),
            'plan_id' => $data['plan_id'],
            'status' => $status,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'current_period_start' => $data['current_period_start'] ?? null,
            'current_period_end' => $data['current_period_end'] ?? null,
            'gateway' => $data['gateway'] ?? null,
            'settings' => $settings,
        ]);

        if ($tenant->user) {
            $tenant->user->update([
                'name' => $data['user_name'],
                'email' => $data['user_email'],
            ]);
        } else {
            $user = User::create([
                'name' => $data['user_name'],
                'email' => $data['user_email'],
                'password' => Str::random(32),
            ]);
            $tenant->update(['user_id' => $user->id]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Tenant atualizado com sucesso.',
            'redirect' => route('admin.tenancy.tenants.show', $tenant->id),
        ]);
    }

    public function suspendTenant(int $id): JsonResponse
    {
        Tenant::findOrFail($id)->update(['status' => 'suspended']);
        return response()->json(['ok' => true]);
    }

    public function activateTenant(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function destroyTenantInstance(int $tenantId, int $instanceId, InstanceManager $mgr): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $instance = $tenant->instances()->findOrFail($instanceId);

        try {
            $mgr->deleteInstance($instance);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    protected function validateExtraInstancePrice(Request $request): array
    {
        $request->merge([
            'price' => Format::decimal($request->input('price')),
            'plan_id' => $request->filled('plan_id') ? $request->input('plan_id') : null,
            'max_quantity' => $request->filled('max_quantity') ? $request->input('max_quantity') : null,
        ]);

        return $request->validate([
            'plan_id' => 'nullable|exists:plans,id',
            'name' => 'required|string|max:120',
            'min_quantity' => 'required|integer|min:1|max:999',
            'max_quantity' => 'nullable|integer|min:1|max:999|gte:min_quantity',
            'price' => 'required|numeric|min:0.01',
            'billing_cycle' => 'required|in:monthly',
            'active' => 'required|boolean',
            'sort' => 'nullable|integer|min:0|max:9999',
        ]);
    }

    public function disableUserTwoFactor(int $id, TwoFactorService $twoFactor): JsonResponse
    {
        $tenant = Tenant::with('user')->findOrFail($id);
        $user = $tenant->user;

        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Tenant nao possui usuario associado.'], 404);
        }

        if (! $user->two_factor_enabled) {
            return response()->json(['ok' => false, 'message' => 'O usuario ja nao possui 2FA ativo.'], 422);
        }

        $twoFactor->disable($user);
        return response()->json(['ok' => true, 'message' => 'Autenticacao de 2 fatores desativada com sucesso para o usuario.']);
    }

    public function syncTenantInstance(Request $request, int $tenantId, int $instanceId, InstanceManager $mgr): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $instance = TenantInstance::where('tenant_id', $tenant->id)->where('id', $instanceId)->firstOrFail();

        try {
            $syncResult = $mgr->syncProfile($instance);
            $instance->refresh();

            return response()->json([
                'ok' => true,
                'message' => 'Perfil e foto do WhatsApp sincronizados com sucesso!',
                'instance' => [
                    'id' => $instance->id,
                    'name' => $instance->whatsapp_profile_name,
                    'avatar' => $instance->profile_avatar_url,
                    'status' => $instance->status,
                    'number' => $instance->profile_number ?: $instance->phone,
                    'devices' => is_array($instance->profile_devices) ? count($instance->profile_devices) : 0,
                ],
                'sync' => $syncResult,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Falha ao sincronizar perfil: ' . $e->getMessage(),
            ], 422);
        }
    }
}
