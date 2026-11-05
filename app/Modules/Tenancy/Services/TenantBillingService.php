<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Tenancy\Models\ExtraInstancePrice;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Models\TenantInvoice;
use App\Services\ProRataCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cobranca recorrente dos tenants.
 *
 * Regras:
 *  - Pro-rata para tenants que assinam meio do mes
 *  - NAO gera fatura/cobra/suspende em sabados, domingos e feriados
 *  - Adia automaticamente para o proximo dia util
 *  - Suspensao tem grace period configuravel
 */
class TenantBillingService
{
    public function __construct(
        protected ?HolidayService $holidays = null,
        protected ?ProRataCalculator $proRata = null,
    ) {
        $this->holidays = $holidays ?? app(HolidayService::class);
        $this->proRata = $proRata ?? app(ProRataCalculator::class);
    }

    /** Cria fatura inicial pro-rata (quando o tenant assina meio do mes). */
    public function createProRataInvoice(Tenant $tenant): ?TenantInvoice
    {
        if (! $tenant->plan) return null;

        $today = now('America/Sao_Paulo');
        $periodEnd = $today->copy()->endOfMonth();
        $calc = $this->proRata->calculate(
            (float) $tenant->plan->price,
            $today,
            $periodEnd
        );

        if ($calc['amount'] <= 0) return null;

        $dueDate = $this->adjustToBusinessDay($today->copy()->addDays(3), $tenant);
        $amount = (float) $calc['amount'];
        $lineItems = [[
            'type' => 'plan_prorata',
            'description' => 'Pro-rata do plano '.$tenant->plan->name,
            'amount' => $amount,
            'period_start' => $calc['period_start'],
            'period_end' => $calc['period_end'],
        ]];

        try {
            $affiliateDiscount = app(\App\Modules\Affiliates\Services\AffiliateService::class)->calculateReferralDiscount($tenant, $amount);
            if ($affiliateDiscount) {
                $lineItems[] = $affiliateDiscount;
                $amount = max(0, round($amount - (float) $affiliateDiscount['discount_amount'], 2));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[Tenant Billing] Erro ao calcular desconto de afiliado pro-rata: " . $e->getMessage());
        }

        return TenantInvoice::create([
            'tenant_id' => $tenant->id,
            'number'    => $this->nextInvoiceNumber(),
            'amount'    => $amount,
            'description' => 'Pro-rata da assinatura '.$tenant->plan->name,
            'due_date'  => $dueDate,
            'status'    => 'pending',
            'meta'      => [
                'line_items' => $lineItems,
            ],
        ]);
    }

    public function generateDueInvoices(int $daysAhead = 3): int
    {
        // Nao gera em fim de semana/feriado
        if ($this->holidays->isNonBusinessDay(now())) {
            return 0;
        }

        $count = 0;
        $targetDate = now()->addDays($daysAhead)->toDateString();

        $tenants = Tenant::where('status', 'active')
            ->whereDate('current_period_end', '<=', $targetDate)
            ->whereDoesntHave('tenantInvoices', function ($q) {
                $q->where('due_date', '>=', now()->toDateString())->where('status', 'pending');
            })
            ->with('plan')
            ->get();

        foreach ($tenants as $tenant) {
            $this->createInvoice($tenant);
            $count++;
        }

        return $count;
    }

    public function createInvoice(Tenant $tenant): TenantInvoice
    {
        $tenant->loadMissing(['plan', 'instances']);
        $plan = $tenant->plan;
        $dueDate = $tenant->current_period_end ?? now()->addDays(7);
        $dueDate = $this->adjustToBusinessDay(Carbon::parse($dueDate), $tenant);
        $cycleMonths = $this->billingCycleMonths((string) $plan->billing_cycle);
        $lineItems = [[
            'type' => 'plan',
            'description' => 'Mensalidade do plano '.$plan->name,
            'quantity' => 1,
            'unit_amount' => (float) $plan->price,
            'amount' => (float) $plan->price,
        ]];

        foreach ($tenant->extraBillableInstances()->get() as $index => $instance) {
            $priceTable = ExtraInstancePrice::resolveForTenant($tenant, $index + 1);
            $unitAmount = (float) ($instance->recurring_amount ?: ($priceTable?->price ?? $plan->extra_instance_price));
            if ($unitAmount <= 0) {
                continue;
            }

            $lineItems[] = [
                'type' => 'extra_instance_recurring',
                'instance_id' => $instance->id,
                'instance_name' => $instance->instance_name,
                'description' => 'Instancia extra WhatsApp - '.($instance->display_name ?: $instance->instance_name),
                'quantity' => $cycleMonths,
                'unit_amount' => $unitAmount,
                'amount' => round($unitAmount * $cycleMonths, 2),
            ];
        }

        $amount = round(array_sum(array_map(fn (array $item) => (float) $item['amount'], $lineItems)), 2);

        // Verifica se há desconto de indicação / programa de afiliado
        try {
            $affiliateDiscount = app(\App\Modules\Affiliates\Services\AffiliateService::class)->calculateReferralDiscount($tenant, $amount);
            if ($affiliateDiscount) {
                $lineItems[] = $affiliateDiscount;
                $amount = max(0, round($amount - (float) $affiliateDiscount['discount_amount'], 2));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[Tenant Billing] Erro ao calcular desconto de afiliado: " . $e->getMessage());
        }

        $inv = TenantInvoice::create([
            'tenant_id' => $tenant->id,
            'number'    => $this->nextInvoiceNumber(),
            'amount'    => $amount,
            'description' => count($lineItems) > 1 ? 'Mensalidade e instancias extras' : 'Mensalidade do plano',
            'due_date'  => $dueDate,
            'status'    => 'pending',
            'meta'      => [
                'line_items' => $lineItems,
                'billing_cycle' => $plan->billing_cycle,
            ],
        ]);

        try {
            $formattedAmount = number_format($amount, 2, ',', '.');
            $dispatcher = app(\App\Modules\Notifications\Services\NotificationDispatcher::class);

            // Notifica o Tenant
            $dispatcher->send([
                'channels'   => ['in_app', 'email'],
                'group'      => 'billing',
                'type'       => 'new_invoice',
                'title'      => 'Nova Fatura de Mensalidade',
                'message'    => "Fatura {$inv->number} gerada no valor de R$ {$formattedAmount} com vencimento em " . \Carbon\Carbon::parse($dueDate)->format('d/m/Y') . ".",
                'url'        => route('tenant.invoices'),
                'icon'       => 'bi-receipt',
                'color'      => 'primary',
                'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[Tenant Billing] Falha ao notificar fatura de mensalidade: " . $e->getMessage());
        }

        return $inv;
    }

    public function quoteExtraInstance(Tenant $tenant, ?Carbon $startedAt = null): array
    {
        $tenant->loadMissing('plan');
        $plan = $tenant->plan;
        $startedAt = ($startedAt ?? now('America/Sao_Paulo'))->copy();
        $nextExtraNumber = max(1, $tenant->currentInstanceCount() - $tenant->includedInstanceLimit() + 1);
        $priceTable = ExtraInstancePrice::resolveForTenant($tenant, $nextExtraNumber);
        $monthlyPrice = round((float) ($priceTable?->price ?? $plan?->extra_instance_price ?? 0), 2);

        if ($monthlyPrice <= 0) {
            throw new \RuntimeException('O valor da instancia extra nao foi configurado pelo superadmin.');
        }

        $cycleMonths = $this->billingCycleMonths((string) $plan->billing_cycle);
        $periodStart = $tenant->current_period_start
            ? Carbon::parse($tenant->current_period_start)->startOfDay()
            : $startedAt->copy()->startOfDay();
        $periodEnd = $tenant->current_period_end
            ? Carbon::parse($tenant->current_period_end)->endOfDay()
            : $periodStart->copy()->addMonths($cycleMonths)->endOfDay();

        if ($periodEnd->lessThanOrEqualTo($startedAt)) {
            $periodStart = $startedAt->copy()->startOfDay();
            $periodEnd = $periodStart->copy()->addMonths($cycleMonths)->endOfDay();
        }

        $totalDays = max(1, (int) ceil($periodStart->diffInHours($periodEnd) / 24));
        $remainingDays = max(1, (int) ceil($startedAt->copy()->startOfDay()->diffInHours($periodEnd) / 24));
        $remainingDays = min($remainingDays, $totalDays);
        $cycleAmount = round($monthlyPrice * $cycleMonths, 2);
        $dailyRate = $cycleAmount / $totalDays;
        $prorata = round($dailyRate * $remainingDays, 2);

        return [
            'recurring_amount' => $monthlyPrice,
            'cycle_amount' => $cycleAmount,
            'prorata_amount' => $prorata,
            'daily_rate' => round($dailyRate, 4),
            'remaining_days' => $remainingDays,
            'days_total' => $totalDays,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'due_date' => $this->adjustToBusinessDay($startedAt->copy()->addDays(3), $tenant)->toDateString(),
            'price_table_id' => $priceTable?->id,
            'price_table_name' => $priceTable?->name,
            'extra_quantity' => $nextExtraNumber,
        ];
    }

    public function createExtraInstanceCharge(Tenant $tenant, TenantInstance $instance, array $quote): ?TenantInvoice
    {
        $amount = round((float) ($quote['prorata_amount'] ?? 0), 2);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($tenant, $instance, $quote, $amount) {
            $line = [
                'type' => 'extra_instance_prorata',
                'instance_id' => $instance->id,
                'instance_name' => $instance->instance_name,
                'description' => 'Pro-rata da instancia extra WhatsApp - '.($instance->display_name ?: $instance->instance_name),
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
                'recurring_amount' => (float) ($quote['recurring_amount'] ?? $instance->recurring_amount),
                'period_start' => $quote['period_start'] ?? null,
                'period_end' => $quote['period_end'] ?? null,
                'remaining_days' => $quote['remaining_days'] ?? null,
            ];

            $invoice = $tenant->tenantInvoices()
                ->where('status', 'pending')
                ->whereDate('due_date', '>=', now()->toDateString())
                ->orderBy('due_date')
                ->lockForUpdate()
                ->first();

            if ($invoice) {
                $meta = $invoice->meta ?? [];
                $lineItems = $meta['line_items'] ?? [];
                $lineItems[] = $line;
                $meta['line_items'] = $lineItems;
                $meta['grouped_addons'] = true;

                $invoice->forceFill([
                    'amount' => round((float) $invoice->amount + $amount, 2),
                    'description' => 'Mensalidade e adicionais recorrentes',
                    'meta' => $meta,
                ])->save();

                return $invoice->refresh();
            }

            return TenantInvoice::create([
                'tenant_id' => $tenant->id,
                'number' => $this->nextInvoiceNumber(),
                'amount' => $amount,
                'description' => 'Pro-rata de instancia extra WhatsApp',
                'due_date' => $quote['due_date'] ?? $this->adjustToBusinessDay(now()->addDays(3), $tenant)->toDateString(),
                'status' => 'pending',
                'meta' => [
                    'line_items' => [$line],
                    'grouped_addons' => true,
                ],
            ]);
        });
    }

    /** Ajusta data para o proximo dia util (pulando finais de semana e feriados). */
    public function adjustToBusinessDay(Carbon $date, ?\App\Modules\Tenancy\Models\Tenant $tenant = null): Carbon
    {
        $ignore = false;
        if ($tenant) {
            $tenant->loadMissing('billingConfig');
            if (isset($tenant->billingConfig) && $tenant->billingConfig->skip_weekends_holidays === false) {
                $ignore = true;
            }
        }
        
        if ($tenant && function_exists('tenancy')) \tenancy()->initialize($tenant);
        $next = $this->holidays->nextBusinessDay($date, $ignore);
        if ($tenant && function_exists('tenancy')) \tenancy()->end();
        
        return $next;
    }

    public function suspendOverdue(): int
    {
        // NAO suspende em fim de semana/feriado
        if ($this->holidays->isNonBusinessDay(now())) {
            return 0;
        }

        $count = 0;
        $graceDays = 3;

        $overdue = Tenant::where('status', 'active')
            ->whereHas('tenantInvoices', function ($q) use ($graceDays) {
                $q->where('status', 'pending')
                  ->where('due_date', '<', now()->subDays($graceDays));
            })
            ->with('billingConfig')
            ->get();

        foreach ($overdue as $tenant) {
            $tenant->update(['status' => 'suspended']);
            $count++;
        }

        return $count;
    }

    public function expireTrials(): int
    {
        return Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => 'suspended']);
    }

    public function confirmPayment(TenantInvoice $invoice): void
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        if (class_exists(\App\Modules\Marketplace\Services\AddonMarketplaceService::class)) {
            try {
                app(\App\Modules\Marketplace\Services\AddonMarketplaceService::class)->syncTenantAddonPayment($invoice);
            } catch (\Throwable) {
                // Nao bloquear confirmacao da fatura principal por falha secundaria de addon.
            }
        }

        $lineTypes = collect(data_get($invoice->meta ?? [], 'line_items', []))
            ->pluck('type')
            ->filter()
            ->values();

        if ($lineTypes->isNotEmpty() && ! $lineTypes->contains(fn (string $type) => in_array($type, ['plan', 'plan_prorata'], true))) {
            return;
        }

        $tenant = $invoice->tenant;
        $plan = $tenant->plan;
        $months = match ($plan->billing_cycle) {
            'monthly'    => 1,
            'quarterly'  => 3,
            'semiannual' => 6,
            'annual'     => 12,
            default      => 1,
        };

        $start = $tenant->current_period_end ?? now();
        $tenant->update([
            'status'               => 'active',
            'current_period_start' => $start,
            'current_period_end'   => $start->copy()->addMonths($months),
        ]);
    }

    protected function billingCycleMonths(string $cycle): int
    {
        return match ($cycle) {
            'quarterly' => 3,
            'semiannual' => 6,
            'annual' => 12,
            default => 1,
        };
    }

    protected function nextInvoiceNumber(): string
    {
        return 'TI-' . str_pad((string) (TenantInvoice::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
