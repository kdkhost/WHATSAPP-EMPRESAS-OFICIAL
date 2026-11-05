<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        if (Auth::guard('web')->check()) {
            return redirect('/painel');
        }

        $activeCoupon = null;
        try {
            $activeCoupon = app(\App\Modules\Affiliates\Services\AffiliateService::class)->resolveActiveCoupon($request);
            if ($activeCoupon) {
                session([
                    'affiliate_ref'    => $activeCoupon['slug'],
                    'affiliate_coupon' => $activeCoupon['code'],
                ]);
            }
        } catch (\Throwable) {}

        $selectedPlan = null;
        if ($planId = $request->input('plan')) {
            $selectedPlan = is_numeric($planId)
                ? Plan::where('id', $planId)->where('active', true)->first()
                : Plan::where('slug', $planId)->where('active', true)->first();
        }

        return view('auth.register', compact('activeCoupon', 'selectedPlan'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
            'document'      => 'nullable|string|max:20',
            'phone'         => 'nullable|string|max:20',
            'cep'           => 'nullable|string|max:9',
            'logradouro'    => 'nullable|string',
            'numero'        => 'nullable|string|max:20',
            'complemento'   => 'nullable|string',
            'bairro'        => 'nullable|string',
            'cidade'        => 'nullable|string',
            'uf'            => 'nullable|string|size:2',
            'plan_id'       => 'nullable|integer|exists:plans,id',
            'ref'           => 'nullable|string|max:100',
            'cupom'         => 'nullable|string|max:100',
            'lgpd_accepted' => 'required|accepted',
        ]);

        Log::info('Iniciando registro de usuario', ['email' => $data['email']]);

        $user = DB::transaction(function () use ($data) {
            $data['lgpd_accepted_at'] = now();
            $userData = collect($data)->except(['ref', 'cupom', 'plan_id'])->all();
            $user = User::create($userData);
            Log::info('Usuario criado', ['user_id' => $user->id, 'email' => $user->email]);

            $plan = null;
            if (!empty($data['plan_id'])) {
                $plan = Plan::where('id', $data['plan_id'])->where('active', true)->first();
            }
            $plan ??= $this->registrationPlan();
            Log::info('Plano selecionado', ['plan_id' => $plan->id, 'plan_name' => $plan->name, 'trial_days' => $plan->trial_days]);

            Tenant::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'company_name' => $user->name,
                'slug' => $this->uniqueTenantSlug($user->name),
                'status' => ((int) $plan->trial_days) > 0 ? 'trial' : 'active',
                'trial_ends_at' => ((int) $plan->trial_days) > 0 ? now()->addDays((int) $plan->trial_days) : null,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            Log::info('Tenant criado', ['user_id' => $user->id, 'plan_id' => $plan->id]);

            return $user;
        });

        Log::info('Transacao concluida', ['user_id' => $user->id]);

        // Registra conversão de afiliado se houver cookie, sessão ou parâmetro ref/cupom
        $couponCode = $request->input('cupom') ?: $request->input('ref') ?: session('affiliate_coupon') ?: session('affiliate_ref');
        try {
            $tenant = Tenant::where('user_id', $user->id)->first();
            app(\App\Modules\Affiliates\Services\AffiliateService::class)->registerConversion($user->id, $tenant?->id, $couponCode);

            // Se o plano assinado for pago e não tiver período de teste (trial 0), gera a primeira fatura com o desconto sugerido do cupom já aplicado!
            $plan = $tenant?->plan;
            if ($tenant && $plan && (float) $plan->price > 0 && ((int) $plan->trial_days) <= 0) {
                app(\App\Modules\Tenancy\Services\TenantBillingService::class)->createInvoice($tenant);
            }
        } catch (\Throwable $e) {
            Log::warning('[Register] Erro ao registrar conversão de afiliado: ' . $e->getMessage());
        }

        try {
            $dispatcher = app(\App\Modules\Notifications\Services\NotificationDispatcher::class);
            // Notifica Administradores
            $dispatcher->send([
                'channels'   => ['in_app'],
                'group'      => 'registration',
                'type'       => 'new_tenant',
                'title'      => 'Novo Tenant Registrado',
                'message'    => "Usuário {$user->name} ({$user->email}) realizou cadastro na plataforma.",
                'url'        => route('admin.tenants.index'),
                'icon'       => 'bi-person-plus-fill',
                'color'      => 'info',
                'recipients' => ['all_admins' => true],
            ]);

            // Mensagem de boas-vindas ao Tenant
            $dispatcher->send([
                'channels'   => ['in_app'],
                'group'      => 'system',
                'type'       => 'welcome',
                'title'      => 'Bem-vindo ao Sistema!',
                'message'    => 'Sua conta foi criada com sucesso. Comece configurando sua instância de WhatsApp e faturas.',
                'url'        => '/painel',
                'icon'       => 'bi-hand-thumbs-up-fill',
                'color'      => 'primary',
                'recipients' => ['user_id' => $user->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Register] Falha ao despachar notificacao de novo tenant: ' . $e->getMessage());
        }

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect('/painel')->with('success', 'Cadastro realizado com sucesso!');
    }

    private function registrationPlan(): Plan
    {
        $plan = Plan::query()
            ->where('active', true)
            ->where('is_demo', false)
            ->where('max_instances', '<=', 10)
            ->orderBy('sort')
            ->orderBy('price')
            ->first();

        if ($plan) {
            return $plan;
        }

        $plan = Plan::query()
            ->where('active', true)
            ->where('max_instances', '<=', 10)
            ->orderBy('sort')
            ->orderBy('price')
            ->first();

        if ($plan) {
            return $plan;
        }

        return Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Plano inicial para novos clientes.',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'trial_days' => 7,
            'max_instances' => 1,
            'max_customers' => 100,
            'max_invoices_month' => 500,
            'max_messages_month' => 1000,
            'custom_gateway' => false,
            'custom_templates' => true,
            'installment_enabled' => false,
            'fee_passthrough' => false,
            'features' => ['Cadastro inicial'],
            'active' => true,
            'sort' => 1,
            'is_demo' => false,
        ]);
    }

    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'cliente';
        $slug = Str::limit($base, 50, '');
        $candidate = $slug;
        $counter = 2;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $suffix = '-'.$counter++;
            $candidate = Str::limit($slug, 60 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }
}
