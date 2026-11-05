<?php

namespace App\Modules\EmailBilling\Services;

use App\Models\User;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmailBillingTenantResolver
{
    public function __construct(
        protected EmailTemplateDefaults $templateDefaults,
        protected SettingsManager $settings
    ) {}

    public function resolve(Request $request): Tenant
    {
        // Rota admin/* — usa guard admin
        if ($request->routeIs('admin.email.*')) {
            $tenant = $this->resolveForAdmin($request);
            $this->templateDefaults->ensureForTenant($tenant);
            return $tenant;
        }

        // Rota painel/* — usa guard web (tenant logado)
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Não autenticado.');
        }
        $tenant = Tenant::where('user_id', $user->id)->firstOrFail();
        $this->templateDefaults->ensureForTenant($tenant);
        return $tenant;
    }

    private function resolveForAdmin(Request $request): Tenant
    {
        $admin = Auth::guard('admin')->user();

        if ($admin?->isSuperAdmin() && $request->filled('tenant_id')) {
            return Tenant::findOrFail((int) $request->integer('tenant_id'));
        }

        $user = User::firstOrCreate(
            ['email' => $admin->email],
            [
                'name' => $admin->name,
                'password' => Hash::make(Str::random(48)),
                'phone' => $admin->getAttributes()['phone'] ?? null,
                'lgpd_accepted' => true,
                'lgpd_accepted_at' => now(),
            ]
        );

        return Tenant::firstOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $this->adminPlan()->id,
                'company_name' => $this->settings->get('app.name', config('app.name', 'Sistema de Cobranca WhatsApp')),
                'slug' => $this->uniqueTenantSlug('admin-'.$admin->id),
                'status' => 'active',
                'current_period_start' => now()->toDateString(),
                'current_period_end' => now()->addYears(10)->toDateString(),
                'settings' => [
                    'owner_type' => 'admin',
                    'admin_user_id' => $admin->id,
                    'created_by' => 'admin_email_billing',
                ],
            ]
        );
    }

    private function adminPlan(): Plan
    {
        return Plan::firstOrCreate(
            ['slug' => 'admin-operacional'],
            [
                'name' => 'Admin Operacional',
                'description' => 'Plano interno para cobrancas proprias do administrador.',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'trial_days' => 0,
                'max_instances' => 999,
                'max_customers' => 999999,
                'max_invoices_month' => 999999,
                'max_messages_month' => 999999,
                'custom_gateway' => true,
                'custom_templates' => true,
                'installment_enabled' => true,
                'fee_passthrough' => true,
                'features' => ['Conta operacional do admin'],
                'active' => true,
                'sort' => 0,
            ]
        );
    }

    private function uniqueTenantSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'admin';
        $candidate = $slug;
        $suffix = 2;

        while (Tenant::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}
