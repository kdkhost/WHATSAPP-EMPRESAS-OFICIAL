<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionPlan;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /** Tenant_id ativo. Null = superadmin (vê tudo). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    /**
     * Retorna dados reais do banco para popular o painel, com tenant isolation.
     */
    public function getData(): JsonResponse
    {
        try {
            $tenantId = $this->activeTenantId();

            $plans = SubscriptionPlan::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderBy('price')->get();

            $subscriberCounts = DB::table('subscriptions')
                ->select('subscription_plan_id', DB::raw('COUNT(*) as total'))
                ->where('status', 'active')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->groupBy('subscription_plan_id')
                ->pluck('total', 'subscription_plan_id');

            $subscriptions = Subscription::with('plan')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $base = fn () => Subscription::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

            $activeCount  = $base()->where('status', 'active')->count();
            $pendingCount = $base()->where('status', 'pending')->count();
            $churnCount   = $base()->where('status', 'canceled')
                ->where('canceled_at', '>=', Carbon::now()->startOfMonth())->count();

            $activeSubs = $base()->where('status', 'active')->with('plan')->get();
            $mrr = 0;
            foreach ($activeSubs as $sub) {
                $price = (float) $sub->price;
                $cycle = $sub->plan->cycle ?? 'monthly';
                if (is_numeric($cycle)) {
                    $days  = (int) $cycle;
                    $mrr  += $days > 0 ? ($price / $days) * 30 : $price;
                } else {
                    $mrr += match ($cycle) {
                        'quarterly'  => $price / 3,
                        'semiannual' => $price / 6,
                        'annual'     => $price / 12,
                        default      => $price,
                    };
                }
            }

            return response()->json([
                'metrics' => [
                    'active'  => $activeCount,
                    'mrr'     => round($mrr, 2),
                    'pending' => $pendingCount,
                    'churn'   => $churnCount,
                ],
                'plans' => $plans->map(fn ($p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'price'       => (float) $p->price,
                    'cycle'       => $p->cycle,
                    'description' => $p->description,
                    'trial_days'  => $p->trial_days,
                    'grace_days'  => $p->grace_days,
                    'gateway'     => $p->gateway,
                    'subscribers' => $subscriberCounts[$p->id] ?? 0,
                    'active'      => (bool) $p->active,
                ]),
                'subscriptions' => $subscriptions->map(fn ($s) => [
                    'id'            => $s->id,
                    'customer_name' => $s->customer_name,
                    'plan_name'     => $s->plan?->name ?? '-',
                    'price'         => (float) $s->price,
                    'started_at'    => $s->started_at?->format('Y-m-d'),
                    'next_billing'  => $s->next_billing?->format('Y-m-d'),
                    'status'        => $s->status,
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error'         => $e->getMessage(),
                'metrics'       => ['active' => 0, 'mrr' => 0, 'pending' => 0, 'churn' => 0],
                'plans'         => [],
                'subscriptions' => [],
            ], 200);
        }
    }

    /** Criar novo plano. */
    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required',
            'cycle'       => 'required|in:monthly,quarterly,semiannual,annual,7,15,30,60,90',
            'description' => 'nullable|string|max:1000',
            'trial_days'  => 'nullable|integer|min:0',
            'grace_days'  => 'nullable|integer|min:0',
            'gateway'     => 'nullable|string|max:50',
        ]);

        $price = $validated['price'];
        if (is_string($price)) {
            $price = str_replace(['R$', ' ', '.'], '', $price);
            $price = str_replace(',', '.', $price);
        }
        $validated['price']     = (float) $price;
        $validated['tenant_id'] = $this->activeTenantId();

        $plan = SubscriptionPlan::create($validated);
        return response()->json(['ok' => true, 'plan' => $plan], 201);
    }

    /** Atualizar plano existente. */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $plan     = SubscriptionPlan::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'price'       => 'sometimes',
            'cycle'       => 'sometimes|in:monthly,quarterly,semiannual,annual,7,15,30,60,90',
            'description' => 'nullable|string|max:1000',
            'trial_days'  => 'nullable|integer|min:0',
            'grace_days'  => 'nullable|integer|min:0',
            'gateway'     => 'nullable|string|max:50',
            'active'      => 'sometimes|boolean',
        ]);

        if (isset($validated['price'])) {
            $price = $validated['price'];
            if (is_string($price)) {
                $price = str_replace(['R$', ' ', '.'], '', $price);
                $price = str_replace(',', '.', $price);
            }
            $validated['price'] = (float) $price;
        }

        $plan->update($validated);
        return response()->json(['ok' => true, 'plan' => $plan->fresh()]);
    }

    /** Excluir plano. */
    public function deletePlan(int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $plan     = SubscriptionPlan::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        $plan->delete();
        return response()->json(['ok' => true]);
    }

    /** Cancelar assinatura. */
    public function cancelSubscription(int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $sub      = Subscription::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        $sub->update(['status' => 'canceled', 'canceled_at' => Carbon::now()]);
        return response()->json(['ok' => true]);
    }

    /** Reativar assinatura. */
    public function reactivateSubscription(int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $sub      = Subscription::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        $sub->update(['status' => 'active', 'canceled_at' => null]);
        return response()->json(['ok' => true]);
    }

    /** Excluir assinatura permanentemente. */
    public function deleteSubscription(int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $sub      = Subscription::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        $sub->delete();
        return response()->json(['ok' => true]);
    }

    /** Criar nova assinatura manualmente. */
    public function storeSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'customer_name'        => 'required|string|max:255',
            'customer_email'       => 'nullable|email|max:255',
            'customer_phone'       => 'nullable|string|max:30',
        ]);

        $plan  = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
        $cycle = $plan->cycle;

        if (is_numeric($cycle)) {
            $nextBilling = Carbon::now()->addDays((int) $cycle);
        } else {
            $nextBilling = match ($cycle) {
                'quarterly'  => Carbon::now()->addMonths(3),
                'semiannual' => Carbon::now()->addMonths(6),
                'annual'     => Carbon::now()->addYear(),
                default      => Carbon::now()->addMonth(),
            };
        }

        $sub = Subscription::create([
            'subscription_plan_id' => $plan->id,
            'tenant_id'            => $this->activeTenantId(),
            'customer_name'        => $validated['customer_name'],
            'customer_email'       => $validated['customer_email'] ?? null,
            'customer_phone'       => $validated['customer_phone'] ?? null,
            'price'                => $plan->price,
            'status'               => 'active',
            'started_at'           => Carbon::now(),
            'next_billing'         => $nextBilling,
        ]);

        return response()->json(['ok' => true, 'subscription' => $sub], 201);
    }
}
