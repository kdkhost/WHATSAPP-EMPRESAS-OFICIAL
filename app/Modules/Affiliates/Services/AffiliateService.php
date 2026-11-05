<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliateCommission;
use App\Modules\Affiliates\Models\AffiliateReferral;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvoice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateService
{
    public const COOKIE_NAME = 'aff_ref';

    public function __construct(protected ?SettingsManager $settings = null)
    {
        $this->settings ??= app(SettingsManager::class);
    }

    /**
     * Retorna as configurações globais do programa de afiliados.
     */
    public function getGlobalSettings(): array
    {
        return [
            'enabled'                => (bool) $this->settings->get('affiliates.enabled', true),
            'commission_mode'        => (string) $this->settings->get('affiliates.commission_mode', 'percentage'), // 'percentage' | 'fixed'
            'default_commission_pct' => (float) $this->settings->get('affiliates.default_commission_pct', 20.00),
            'default_commission_fixed' => (float) $this->settings->get('affiliates.default_commission_fixed', 0.00),
            'default_commission_type' => (string) $this->settings->get('affiliates.default_commission_type', 'recurring'), // 'recurring', 'first_payment', 'lifetime'

            'buyer_discount_enabled' => (bool) $this->settings->get('affiliates.buyer_discount_enabled', true),
            'buyer_discount_mode'    => (string) $this->settings->get('affiliates.buyer_discount_mode', 'percentage'), // 'percentage' | 'fixed'
            'buyer_discount_pct'     => (float) $this->settings->get('affiliates.buyer_discount_pct', 10.00),
            'buyer_discount_fixed'   => (float) $this->settings->get('affiliates.buyer_discount_fixed', 0.00),
            'buyer_discount_type'    => (string) $this->settings->get('affiliates.buyer_discount_type', 'first_payment'), // 'first_payment', 'recurring'

            'cookie_days'            => (int) $this->settings->get('affiliates.cookie_days', 60),
            'min_withdrawal'         => (float) $this->settings->get('affiliates.min_withdrawal', 50.00),
            'auto_approve_days'      => (int) $this->settings->get('affiliates.auto_approve_days', 7),
        ];
    }

    /**
     * Atualiza as configurações globais do programa de afiliados.
     */
    public function updateGlobalSettings(array $data): void
    {
        $keys = [
            'affiliates.enabled'                => isset($data['enabled']) ? (bool) $data['enabled'] : true,
            'affiliates.commission_mode'        => in_array($data['commission_mode'] ?? '', ['percentage', 'fixed']) ? $data['commission_mode'] : 'percentage',
            'affiliates.default_commission_pct' => (float) ($data['default_commission_pct'] ?? 20.00),
            'affiliates.default_commission_fixed' => (float) ($data['default_commission_fixed'] ?? 0.00),
            'affiliates.default_commission_type' => in_array($data['default_commission_type'] ?? '', ['recurring', 'first_payment', 'lifetime']) ? $data['default_commission_type'] : 'recurring',

            'affiliates.buyer_discount_enabled' => (bool) ($data['buyer_discount_enabled'] ?? false),
            'affiliates.buyer_discount_mode'    => in_array($data['buyer_discount_mode'] ?? '', ['percentage', 'fixed']) ? $data['buyer_discount_mode'] : 'percentage',
            'affiliates.buyer_discount_pct'     => (float) ($data['buyer_discount_pct'] ?? 10.00),
            'affiliates.buyer_discount_fixed'   => (float) ($data['buyer_discount_fixed'] ?? 0.00),
            'affiliates.buyer_discount_type'    => in_array($data['buyer_discount_type'] ?? '', ['first_payment', 'recurring']) ? $data['buyer_discount_type'] : 'first_payment',

            'affiliates.cookie_days'            => max(1, (int) ($data['cookie_days'] ?? 60)),
            'affiliates.min_withdrawal'         => max(1, (float) ($data['min_withdrawal'] ?? 50.00)),
            'affiliates.auto_approve_days'      => max(0, (int) ($data['auto_approve_days'] ?? 7)),
        ];

        foreach ($keys as $key => $val) {
            $this->settings->set($key, $val);
        }
    }

    /**
     * Rastreia clique e visita através de link de afiliado.
     */
    public function trackClick(string $slug, Request $request): ?Affiliate
    {
        $global = $this->getGlobalSettings();
        if (! $global['enabled']) return null;

        $affiliate = Affiliate::where('slug', $slug)
            ->orWhere('referral_code', $slug)
            ->where('status', 'active')
            ->first();

        if (! $affiliate) return null;

        $ip = $request->ip();
        $userAgent = substr((string) $request->userAgent(), 0, 500);
        $referer = substr((string) $request->headers->get('referer'), 0, 500);

        // Previne duplicação artificial do mesmo IP no mesmo minuto
        $recentClick = AffiliateClick::where('affiliate_id', $affiliate->id)
            ->where('ip', $ip)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if (! $recentClick) {
            AffiliateClick::create([
                'affiliate_id' => $affiliate->id,
                'tenant_id'    => $affiliate->tenant_id,
                'ip'           => $ip,
                'user_agent'   => $userAgent,
                'referer'      => $referer,
                'created_at'   => now(),
            ]);

            $affiliate->increment('clicks');
        }

        $cookieDays = $global['cookie_days'];
        Cookie::queue(self::COOKIE_NAME, $affiliate->referral_code, $cookieDays * 1440);

        return $affiliate;
    }

    /**
     * Resolve os dados do afiliado/cupom ativo a partir da Request, Cookie ou Session.
     */
    public function resolveActiveCoupon(?Request $request = null): ?array
    {
        $global = $this->getGlobalSettings();
        if (! $global['enabled']) {
            return null;
        }

        $req = $request ?? request();
        $code = $req?->input('cupom')
            ?? $req?->input('coupon')
            ?? $req?->input('ref')
            ?? $req?->input('aff')
            ?? session('affiliate_coupon')
            ?? session('affiliate_ref')
            ?? $req?->cookie(self::COOKIE_NAME);

        if (! $code || ! is_string($code)) {
            return null;
        }

        $code = trim($code);

        $affiliate = Affiliate::with(['user'])
            ->where(function ($q) use ($code) {
                $q->where('referral_code', $code)
                  ->orWhere('slug', $code);
            })
            ->where('status', 'active')
            ->first();

        if (! $affiliate) {
            return null;
        }

        $discountMode = $affiliate->discount_mode ?: $global['buyer_discount_mode'];
        $discountPct = (float) ($affiliate->discount_pct > 0 ? $affiliate->discount_pct : $global['buyer_discount_pct']);
        $discountFixed = (float) ($affiliate->discount_fixed > 0 ? $affiliate->discount_fixed : $global['buyer_discount_fixed']);
        $discountType = $affiliate->discount_type ?: $global['buyer_discount_type'];

        $hasDiscount = $global['buyer_discount_enabled'] && (($discountMode === 'fixed' && $discountFixed > 0) || ($discountMode === 'percentage' && $discountPct > 0));

        $discountLabel = '';
        if ($discountMode === 'fixed' && $discountFixed > 0) {
            $discountLabel = 'R$ ' . number_format($discountFixed, 2, ',', '.') . ' de desconto';
        } elseif ($discountPct > 0) {
            $discountLabel = number_format($discountPct, 1, ',', '.') . '% de desconto';
        } else {
            $discountLabel = 'Condição especial exclusiva';
        }

        $typeLabel = match ($discountType) {
            'recurring' => 'Recorrente em todas as mensalidades',
            default     => 'Na primeira compra / 1ª mensalidade',
        };

        return [
            'affiliate_id'       => $affiliate->id,
            'code'               => $affiliate->referral_code,
            'slug'               => $affiliate->slug,
            'promoter_name'      => $affiliate->user?->name ?? 'Afiliado Parceiro',
            'discount_mode'      => $discountMode,
            'discount_pct'       => $discountPct,
            'discount_fixed'     => $discountFixed,
            'discount_type'      => $discountType,
            'discount_formatted' => $affiliate->discount_formatted,
            'type_label'         => $typeLabel,
            'has_discount'       => $hasDiscount,
            'label'              => $discountLabel . ' (' . $typeLabel . ')',
        ];
    }

    /**
     * Registra conversão de novo usuário / tenant através do código de afiliado.
     */
    public function registerConversion(int $userId, ?int $tenantId = null, ?string $cookieCode = null): ?AffiliateReferral
    {
        $global = $this->getGlobalSettings();
        if (! $global['enabled']) return null;

        $code = $cookieCode 
            ?? request()?->input('cupom')
            ?? request()?->input('coupon')
            ?? request()?->cookie(self::COOKIE_NAME) 
            ?? request()?->input('ref')
            ?? request()?->input('aff')
            ?? session('affiliate_coupon')
            ?? session('affiliate_ref');

        if (! $code) return null;

        $affiliate = Affiliate::where(function ($q) use ($code) {
                $q->where('referral_code', $code)
                  ->orWhere('slug', $code);
            })
            ->where('status', 'active')
            ->first();

        if (! $affiliate) return null;

        // Não permite auto-indicação
        if ($affiliate->user_id === $userId) {
            return null;
        }

        $existing = AffiliateReferral::where('affiliate_id', $affiliate->id)
            ->where(function ($q) use ($userId, $tenantId) {
                $q->where('user_id', $userId);
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })->first();

        if ($existing) {
            if ($tenantId && ! $existing->tenant_id) {
                $existing->update(['tenant_id' => $tenantId]);
            }
            return $existing;
        }

        $cookieDays = $global['cookie_days'];
        $referral = AffiliateReferral::create([
            'affiliate_id'      => $affiliate->id,
            'user_id'           => $userId,
            'tenant_id'         => $tenantId,
            'cookie_expires_at' => now()->addDays($cookieDays),
        ]);

        $affiliate->increment('conversions');

        // Marca clique recente como convertido
        AffiliateClick::where('affiliate_id', $affiliate->id)
            ->where('converted', false)
            ->latest('id')
            ->limit(1)
            ->update(['converted' => true]);

        return $referral;
    }

    /**
     * Calcula o desconto oferecido ao comprador pela indicação do afiliado.
     */
    public function calculateReferralDiscount(Tenant $tenant, float $subtotal): ?array
    {
        $global = $this->getGlobalSettings();
        if (! $global['enabled'] || ! $global['buyer_discount_enabled']) {
            return null;
        }

        $referral = AffiliateReferral::where('tenant_id', $tenant->id)->first();
        if (! $referral) {
            return null;
        }

        // Valida expiração de cookie se houver
        if ($referral->cookie_expires_at && $referral->cookie_expires_at->isPast()) {
            return null;
        }

        $affiliate = $referral->affiliate;
        if (! $affiliate || $affiliate->status !== 'active') {
            return null;
        }

        // Determina regras de desconto (específica do afiliado ou padrão global)
        $discountType = $affiliate->discount_type ?: $global['buyer_discount_type'];
        if ($discountType === 'first_payment' && $referral->first_payment_done) {
            return null;
        }

        $discountMode = $affiliate->discount_mode ?: $global['buyer_discount_mode'];
        $discountPct = (float) ($affiliate->discount_pct > 0 ? $affiliate->discount_pct : $global['buyer_discount_pct']);
        $discountFixed = (float) ($affiliate->discount_fixed > 0 ? $affiliate->discount_fixed : $global['buyer_discount_fixed']);

        $discountAmount = 0.0;
        $label = '';

        if ($discountMode === 'fixed' && $discountFixed > 0) {
            $discountAmount = min($subtotal, $discountFixed);
            $label = 'R$ ' . number_format($discountFixed, 2, ',', '.');
        } elseif ($discountPct > 0) {
            $discountAmount = round($subtotal * ($discountPct / 100), 2);
            $label = number_format($discountPct, 1, ',', '.') . '%';
        }

        if ($discountAmount <= 0) {
            return null;
        }

        return [
            'type'        => 'affiliate_discount',
            'description' => "Desconto de Indicação [{$affiliate->referral_code}] ({$label})",
            'quantity'    => 1,
            'unit_amount' => -1 * $discountAmount,
            'amount'      => -1 * $discountAmount,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Gera comissão para o afiliado quando uma fatura do tenant for paga.
     */
    public function generateCommission(TenantInvoice $invoice): ?AffiliateCommission
    {
        $global = $this->getGlobalSettings();
        if (! $global['enabled']) return null;

        $referral = AffiliateReferral::where('tenant_id', $invoice->tenant_id)->first();
        if (! $referral) return null;

        $affiliate = $referral->affiliate;
        if (! $affiliate || $affiliate->status !== 'active') return null;

        // Regra de recorrência vs primeira mensalidade
        $commissionType = $affiliate->commission_type ?: $global['default_commission_type'];
        if ($commissionType === 'first_payment' && $referral->first_payment_done) {
            return null;
        }

        // Verifica se já não gerou comissão para esta fatura
        $alreadyCreated = AffiliateCommission::where('tenant_invoice_id', $invoice->id)->first();
        if ($alreadyCreated) return $alreadyCreated;

        $commissionMode = $affiliate->commission_mode ?: $global['commission_mode'];
        $commissionPct = (float) ($affiliate->commission_pct > 0 ? $affiliate->commission_pct : $global['default_commission_pct']);
        $commissionFixed = (float) ($affiliate->commission_fixed > 0 ? $affiliate->commission_fixed : $global['default_commission_fixed']);

        $commissionAmount = 0.0;
        if ($commissionMode === 'fixed' && $commissionFixed > 0) {
            $commissionAmount = $commissionFixed;
        } else {
            $commissionAmount = round((float) $invoice->amount * ($commissionPct / 100), 2);
        }

        if ($commissionAmount <= 0) return null;

        $isSplitMp = (bool) ($affiliate->split_enabled && filled($affiliate->mp_collector_id));
        $invoiceMeta = is_array($invoice->meta) ? $invoice->meta : (json_decode($invoice->meta ?? '[]', true) ?: []);
        if (!empty($invoiceMeta['split_mercadopago'])) {
            $isSplitMp = true;
        }

        if ($isSplitMp) {
            $collectorId = $affiliate->mp_collector_id ?: ($invoiceMeta['split_mercadopago']['collector_id'] ?? 'MP');
            $notes = "Split Mercado Pago direto na conta MP Collector ID {$collectorId} (" . ($commissionMode === 'fixed' ? 'Fixo R$ '.$commissionFixed : $commissionPct.'%') . ")";
            $commissionStatus = 'paid';
        } else {
            $notes = "Comissão s/ fatura #{$invoice->number} (" . ($commissionMode === 'fixed' ? 'Fixo R$ '.$commissionFixed : $commissionPct.'%') . ")";
            $commissionStatus = 'pending';
        }

        $commission = AffiliateCommission::create([
            'affiliate_id'      => $affiliate->id,
            'referral_id'       => $referral->id,
            'tenant_invoice_id' => $invoice->id,
            'base_amount'       => $invoice->amount,
            'commission_pct'    => $commissionPct,
            'commission_amount' => $commissionAmount,
            'commission_type'   => $commissionType,
            'status'            => $commissionStatus,
            'approved_at'       => $isSplitMp ? now() : null,
            'notes'             => $notes,
        ]);

        if ($isSplitMp) {
            $affiliate->increment('total_earned', $commissionAmount);
            $affiliate->increment('total_paid', $commissionAmount);
        }

        if (! $referral->first_payment_done) {
            $referral->update(['first_payment_done' => true]);
        }

        return $commission;
    }

    /**
     * Aprova uma comissão pendente e credita no saldo do afiliado.
     */
    public function approveCommission(AffiliateCommission $commission): void
    {
        if ($commission->status !== 'pending') return;

        DB::transaction(function () use ($commission) {
            $commission->update(['status' => 'approved', 'approved_at' => now()]);
            $commission->affiliate->increment('balance', $commission->commission_amount);
            $commission->affiliate->increment('total_earned', $commission->commission_amount);
        });
    }

    /**
     * Aprova automaticamente comissões antigas com base nas configurações globais.
     */
    public function autoApproveOldCommissions(): int
    {
        $global = $this->getGlobalSettings();
        $days = $global['auto_approve_days'];
        if ($days <= 0) return 0;

        $count = 0;
        AffiliateCommission::where('status', 'pending')
            ->where('created_at', '<', now()->subDays($days))
            ->chunk(100, function ($commissions) use (&$count) {
                foreach ($commissions as $c) {
                    $this->approveCommission($c);
                    $count++;
                }
            });
        return $count;
    }

    /**
     * Retorna estatísticas completas e dados para gráficos interativos do programa de afiliados.
     */
    public function getKpisAndChartData(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Totais gerais
        $totalAffiliates = Affiliate::count();
        $activeAffiliates = Affiliate::where('status', 'active')->count();
        $totalClicks = (int) Affiliate::sum('clicks');
        $totalConversions = (int) Affiliate::sum('conversions');
        $conversionRate = $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 1) : 0.0;
        $totalPaid = (float) Affiliate::sum('total_paid');
        $totalEarned = (float) Affiliate::sum('total_earned');
        $totalBalance = (float) Affiliate::sum('balance');

        $pendingCommissions = (float) AffiliateCommission::where('status', 'pending')->sum('commission_amount');
        $approvedCommissions = (float) AffiliateCommission::where('status', 'approved')->sum('commission_amount');
        $pendingWithdrawals = (float) \App\Modules\Affiliates\Models\AffiliateWithdrawal::where('status', 'pending')->sum('amount');

        // Séries temporais diárias para o Chart.js
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);
        $chartLabels = [];
        $clicksMap = [];
        $conversionsMap = [];
        $commissionsMap = [];

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $chartLabels[] = $date->format('d/m');
            $clicksMap[$key] = 0;
            $conversionsMap[$key] = 0;
            $commissionsMap[$key] = 0.0;
        }

        // Consultas agrupadas por dia
        $clicksDb = AffiliateClick::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day')
            ->toArray();

        foreach ($clicksDb as $day => $val) {
            if (isset($clicksMap[$day])) $clicksMap[$day] = (int) $val;
        }

        $convDb = AffiliateReferral::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day')
            ->toArray();

        foreach ($convDb as $day => $val) {
            if (isset($conversionsMap[$day])) $conversionsMap[$day] = (int) $val;
        }

        $commDb = AffiliateCommission::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as day, SUM(commission_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();

        foreach ($commDb as $day => $val) {
            if (isset($commissionsMap[$day])) $commissionsMap[$day] = round((float) $val, 2);
        }

        // Distribuição de afiliados por tipo de comissão
        $typeDistribution = [
            'recurring'     => Affiliate::where('commission_type', 'recurring')->count(),
            'first_payment' => Affiliate::where('commission_type', 'first_payment')->count(),
            'lifetime'      => Affiliate::where('commission_type', 'lifetime')->count(),
        ];

        return [
            'total_affiliates'     => $totalAffiliates,
            'active_affiliates'    => $activeAffiliates,
            'total_clicks'         => $totalClicks,
            'total_conversions'    => $totalConversions,
            'conversion_rate'      => $conversionRate,
            'total_paid'           => $totalPaid,
            'total_earned'         => $totalEarned,
            'total_balance'        => $totalBalance,
            'pending_commissions'  => $pendingCommissions,
            'approved_commissions' => $approvedCommissions,
            'pending_withdrawals'  => $pendingWithdrawals,
            'type_distribution'    => $typeDistribution,
            'chart' => [
                'labels'      => $chartLabels,
                'clicks'      => array_values($clicksMap),
                'conversions' => array_values($conversionsMap),
                'commissions' => array_values($commissionsMap),
            ],
        ];
    }

    /**
     * Retorna dados consolidados de afiliação e métricas específicas de um Tenant.
     */
    public function getTenantAffiliateStats(Tenant $tenant): array
    {
        // 1. Procura se este Tenant é um afiliado
        $affiliate = Affiliate::where('tenant_id', $tenant->id)
            ->orWhere('user_id', $tenant->user_id)
            ->first();

        // 2. Procura se este Tenant foi indicado por alguém
        $referredBy = AffiliateReferral::with(['affiliate.user'])->where('tenant_id', $tenant->id)->first();
        $totalCommissionsPaidByTenant = 0.0;
        if ($referredBy) {
            $totalCommissionsPaidByTenant = (float) AffiliateCommission::where('referral_id', $referredBy->id)->sum('commission_amount');
        }

        $promoterStats = null;
        if ($affiliate) {
            // Histórico diário de cliques e conversões para este tenant (últimos 14 dias)
            $subPeriod = CarbonPeriod::create(now()->subDays(13)->startOfDay(), '1 day', now()->endOfDay());
            $pLabels = [];
            $pClicks = [];
            $pConv = [];

            $dbClicks = $affiliate->clicks()->where('created_at', '>=', now()->subDays(14))
                ->selectRaw('DATE(created_at) as day, COUNT(*) as c')
                ->groupBy('day')->pluck('c', 'day')->toArray();

            $dbConv = $affiliate->referrals()->where('created_at', '>=', now()->subDays(14))
                ->selectRaw('DATE(created_at) as day, COUNT(*) as c')
                ->groupBy('day')->pluck('c', 'day')->toArray();

            foreach ($subPeriod as $d) {
                $dayStr = $d->format('Y-m-d');
                $pLabels[] = $d->format('d/m');
                $pClicks[] = (int) ($dbClicks[$dayStr] ?? 0);
                $pConv[] = (int) ($dbConv[$dayStr] ?? 0);
            }

            $promoterStats = [
                'affiliate'            => $affiliate,
                'clicks'               => (int) $affiliate->clicks,
                'conversions'          => (int) $affiliate->conversions,
                'conversion_rate'      => $affiliate->conversion_rate,
                'balance'              => (float) $affiliate->balance,
                'total_earned'         => (float) $affiliate->total_earned,
                'total_paid'           => (float) $affiliate->total_paid,
                'pending_commissions'  => (float) $affiliate->commissions()->where('status', 'pending')->sum('commission_amount'),
                'referral_url'         => $affiliate->referral_url,
                'referral_code'        => $affiliate->referral_code,
                'commission_formatted' => $affiliate->commission_formatted,
                'commission_type_label'=> $affiliate->commission_type_label,
                'discount_formatted'   => $affiliate->discount_formatted,
                'discount_type_label'  => $affiliate->discount_type_label,
                'chart_labels'         => $pLabels,
                'chart_clicks'         => $pClicks,
                'chart_conversions'    => $pConv,
                'recent_referrals'     => $affiliate->referrals()->with(['tenant', 'user'])->latest()->limit(5)->get(),
            ];
        }

        return [
            'is_promoter'                   => (bool) $affiliate,
            'affiliate'                     => $affiliate,
            'promoter_stats'                => $promoterStats,
            'referred_by'                   => $referredBy,
            'commissions_paid_for_referrer' => $totalCommissionsPaidByTenant,
            'global_settings'               => $this->getGlobalSettings(),
        ];
    }

    /**
     * Sincroniza ou ativa um Tenant como Promotor / Afiliado.
     */
    public function syncTenantAsAffiliate(Tenant $tenant, array $attributes = []): Affiliate
    {
        $affiliate = Affiliate::where('tenant_id', $tenant->id)
            ->orWhere('user_id', $tenant->user_id)
            ->first();

        $global = $this->getGlobalSettings();

        if (! $affiliate) {
            $affiliate = new Affiliate();
            $affiliate->user_id = $tenant->user_id;
            $affiliate->tenant_id = $tenant->id;
            $affiliate->slug = Str::slug($tenant->company_name) . '-' . Str::lower(Str::random(5));
            $affiliate->referral_code = strtoupper(Str::random(8));
            $affiliate->status = 'active';
            $affiliate->commission_mode = $global['commission_mode'];
            $affiliate->commission_pct = $global['default_commission_pct'];
            $affiliate->commission_fixed = $global['default_commission_fixed'];
            $affiliate->commission_type = $global['default_commission_type'];
            $affiliate->discount_mode = $global['buyer_discount_mode'];
            $affiliate->discount_pct = $global['buyer_discount_pct'];
            $affiliate->discount_fixed = $global['buyer_discount_fixed'];
            $affiliate->discount_type = $global['buyer_discount_type'];
            $affiliate->min_withdrawal = $global['min_withdrawal'];
        }

        $previousStatus = $affiliate->status;

        if (isset($attributes['status'])) $affiliate->status = $attributes['status'];
        if (isset($attributes['commission_mode'])) $affiliate->commission_mode = $attributes['commission_mode'];
        if (isset($attributes['commission_pct'])) $affiliate->commission_pct = (float) $attributes['commission_pct'];
        if (isset($attributes['commission_fixed'])) $affiliate->commission_fixed = (float) $attributes['commission_fixed'];
        if (isset($attributes['commission_type'])) $affiliate->commission_type = $attributes['commission_type'];
        if (isset($attributes['discount_mode'])) $affiliate->discount_mode = $attributes['discount_mode'];
        if (isset($attributes['discount_pct'])) $affiliate->discount_pct = (float) $attributes['discount_pct'];
        if (isset($attributes['discount_fixed'])) $affiliate->discount_fixed = (float) $attributes['discount_fixed'];
        if (isset($attributes['discount_type'])) $affiliate->discount_type = $attributes['discount_type'];
        if (isset($attributes['min_withdrawal'])) $affiliate->min_withdrawal = (float) $attributes['min_withdrawal'];
        if (isset($attributes['split_enabled'])) $affiliate->split_enabled = (bool) $attributes['split_enabled'];
        if (isset($attributes['split_pct'])) $affiliate->split_pct = (float) $attributes['split_pct'];
        if (isset($attributes['mp_collector_id'])) $affiliate->mp_collector_id = trim($attributes['mp_collector_id']);
        if (isset($attributes['admin_notes'])) $affiliate->admin_notes = trim($attributes['admin_notes']);
        if (isset($attributes['rejection_reason'])) $affiliate->rejection_reason = trim($attributes['rejection_reason']);
        if (! empty($attributes['slug'])) $affiliate->slug = Str::slug($attributes['slug']);
        if (! empty($attributes['referral_code'])) $affiliate->referral_code = strtoupper(trim($attributes['referral_code']));

        if ($affiliate->status === 'active' && $previousStatus !== 'active') {
            $affiliate->approved_at = now();
            $affiliate->rejection_reason = null;
        }

        $affiliate->tenant_id = $tenant->id;
        $affiliate->save();

        if ($affiliate->status === 'active' && $previousStatus !== 'active') {
            try {
                app(\App\Modules\Affiliates\Services\AffiliateNotificationService::class)->notifyApproved($affiliate);
            } catch (\Throwable $e) {}
        } elseif ($affiliate->status === 'rejected' && $previousStatus !== 'rejected') {
            try {
                app(\App\Modules\Affiliates\Services\AffiliateNotificationService::class)->notifyRejected($affiliate, $affiliate->rejection_reason ?: 'Não aprovado pelo administrador.');
            } catch (\Throwable $e) {}
        }

        return $affiliate;
    }
}
