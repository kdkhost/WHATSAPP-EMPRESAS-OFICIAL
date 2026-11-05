<?php

namespace App\Modules\Affiliates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateWithdrawal;
use App\Modules\Affiliates\Services\AffiliateNotificationService;
use App\Modules\Affiliates\Services\AffiliateService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function __construct(protected AffiliateService $service) {}

    public function dashboard()
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->tenant_id ?? null;
        if (! $tenantId) {
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            $tenantId = $tenant?->id;
        }

        $affiliate = Affiliate::where('user_id', $user->id)
            ->when($tenantId, fn($q) => $q->orWhere('tenant_id', $tenantId))
            ->first();

        $globalSettings = $this->service->getGlobalSettings();

        // Se ainda não se inscreveu ou o cadastro não estiver ativo
        if (! $affiliate || ! $affiliate->isActive()) {
            return view('affiliates::dashboard', compact('affiliate', 'globalSettings'));
        }

        $commissionColumn = $this->commissionAmountColumn();

        $stats = [
            'clicks_30d'         => $affiliate->clicks()->where('created_at', '>=', now()->subDays(30))->count(),
            'conversions_30d'    => $affiliate->referrals()->where('created_at', '>=', now()->subDays(30))->count(),
            'commission_pending' => $commissionColumn ? $affiliate->commissions()->where('status', 'pending')->sum($commissionColumn) : 0,
            'commission_paid'    => $commissionColumn ? $affiliate->commissions()->where('status', 'paid')->sum($commissionColumn) : 0,
        ];

        $recentReferrals = $affiliate->referrals()->with(['user', 'tenant'])->latest()->limit(10)->get();
        $recentCommissions = $affiliate->commissions()->latest()->limit(10)->get();

        // Séries temporais para o gráfico de cliques e conversões (últimos 15 dias)
        $days = collect(range(14, 0))->map(fn($d) => now()->subDays($d)->format('Y-m-d'));
        $clicksByDay = $affiliate->clicks()
            ->where('created_at', '>=', now()->subDays(15))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $referralsByDay = $affiliate->referrals()
            ->where('created_at', '>=', now()->subDays(15))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $chartData = [
            'labels'      => $days->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))->values()->toArray(),
            'clicks'      => $days->map(fn($d) => (int) ($clicksByDay[$d] ?? 0))->values()->toArray(),
            'conversions' => $days->map(fn($d) => (int) ($referralsByDay[$d] ?? 0))->values()->toArray(),
        ];

        return view('affiliates::dashboard', compact('affiliate', 'globalSettings', 'stats', 'recentReferrals', 'recentCommissions', 'chartData'));
    }

    public function apply(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->tenant_id ?? null;
        if (! $tenantId) {
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            $tenantId = $tenant?->id;
        }

        $affiliate = Affiliate::where('user_id', $user->id)
            ->when($tenantId, fn($q) => $q->orWhere('tenant_id', $tenantId))
            ->first();

        if ($affiliate && $affiliate->isActive()) {
            return response()->json(['ok' => false, 'error' => 'Seu cadastro de afiliado já está aprovado e ativo!'], 422);
        }

        $data = $request->validate([
            'promotion_channel' => 'required|string|max:100',
            'pix_type'          => 'required|in:cpf,cnpj,email,phone,random',
            'pix_key'           => 'required|string|max:120',
            'mp_collector_id'   => 'nullable|string|max:60',
            'terms_accepted'    => $affiliate ? 'nullable' : 'accepted',
        ], [
            'promotion_channel.required' => 'Informe o canal principal onde você pretende divulgar.',
            'pix_type.required'          => 'Selecione o tipo de chave PIX.',
            'pix_key.required'           => 'Informe a chave PIX para recebimento.',
            'terms_accepted.accepted'    => 'Você precisa aceitar os termos do programa de afiliados.',
        ]);

        $global = $this->service->getGlobalSettings();

        if (! $affiliate) {
            $affiliate = new Affiliate();
            $affiliate->user_id = $user->id;
            $affiliate->tenant_id = $tenantId;
            $slugBase = $user->name ?: 'afiliado';
            $affiliate->slug = Str::slug($slugBase) . '-' . Str::lower(Str::random(5));
            $affiliate->referral_code = strtoupper(Str::random(8));
            $affiliate->commission_mode = $global['commission_mode'];
            $affiliate->commission_pct = $global['default_commission_pct'];
            $affiliate->commission_fixed = $global['default_commission_fixed'];
            $affiliate->commission_type = $global['default_commission_type'];
            $affiliate->discount_mode = $global['buyer_discount_mode'];
            $affiliate->discount_pct = $global['buyer_discount_pct'];
            $affiliate->discount_fixed = $global['buyer_discount_fixed'];
            $affiliate->discount_type = $global['buyer_discount_type'];
            $affiliate->min_withdrawal = $global['min_withdrawal'];
        } else {
            if ($tenantId && ! $affiliate->tenant_id) {
                $affiliate->tenant_id = $tenantId;
            }
        }

        $affiliate->promotion_channel = $data['promotion_channel'];
        $affiliate->payment_info = [
            'pix_type' => $data['pix_type'],
            'pix_key'  => $data['pix_key'],
        ];
        if (!empty($data['mp_collector_id'])) {
            $affiliate->mp_collector_id = trim($data['mp_collector_id']);
        }
        $affiliate->status = 'pending';
        $affiliate->applied_at = now();
        $affiliate->rejection_reason = null;
        $affiliate->save();

        // Dispara notificações (e-mail, whatsapp e admin in-app)
        try {
            app(AffiliateNotificationService::class)->notifyApplicationSubmitted($affiliate);
        } catch (\Throwable $e) {
            Log::warning('[AffiliateApply] Falha na notificação: ' . $e->getMessage());
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Sua inscrição no Programa de Afiliados foi enviada com sucesso! Ela está em análise pela nossa equipe e em breve você receberá a confirmação no WhatsApp e E-mail.',
        ]);
    }

    private function commissionAmountColumn(): ?string
    {
        if (! Schema::hasTable('affiliate_commissions')) {
            return null;
        }

        if (Schema::hasColumn('affiliate_commissions', 'commission_amount')) {
            return 'commission_amount';
        }

        return Schema::hasColumn('affiliate_commissions', 'amount') ? 'amount' : null;
    }

    public function track(string $slug, Request $request)
    {
        $affiliate = $this->service->trackClick($slug, $request);
        $couponCode = $affiliate?->referral_code ?: $slug;

        session([
            'affiliate_ref'    => $slug,
            'affiliate_coupon' => $couponCode,
        ]);

        $params = $request->query();
        $params['ref'] = $slug;
        $params['cupom'] = $couponCode;

        $campaignEnabled = (bool) app(\App\Modules\Settings\Services\SettingsManager::class)->get('marketing.enabled', true);
        $targetRoute = $campaignEnabled ? '/promocao' : '/cadastro';

        return redirect($targetRoute . '?' . http_build_query($params));
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $affiliate = Affiliate::where('user_id', Auth::id())->firstOrFail();
        if (!$affiliate->canWithdraw()) {
            return response()->json([
                'ok' => false,
                'error' => 'Saldo insuficiente ou cadastro não ativo. Minimo: R$ ' . number_format($affiliate->min_withdrawal, 2, ',', '.'),
            ], 422);
        }

        $request->merge([
            'amount' => Format::decimal($request->input('amount')),
        ]);

        $data = $request->validate([
            'amount'   => 'required|numeric|min:' . $affiliate->min_withdrawal . '|max:' . $affiliate->balance,
            'pix_key'  => 'required|string|max:120',
            'pix_type' => 'required|in:cpf,cnpj,email,phone,random',
        ]);

        AffiliateWithdrawal::create([
            'affiliate_id' => $affiliate->id,
            'amount'       => $data['amount'],
            'status'       => 'pending',
            'payment_info' => ['pix_key' => $data['pix_key'], 'pix_type' => $data['pix_type']],
        ]);

        $affiliate->decrement('balance', $data['amount']);

        return response()->json(['ok' => true, 'message' => 'Saque solicitado com sucesso!']);
    }

    public function update(Request $request): JsonResponse
    {
        $affiliate = Affiliate::where('user_id', Auth::id())->firstOrFail();
        $request->merge([
            'min_withdrawal' => $request->filled('min_withdrawal') ? Format::decimal($request->input('min_withdrawal')) : null,
        ]);
        $data = $request->validate([
            'min_withdrawal'  => 'nullable|numeric|min:10',
            'mp_collector_id' => 'nullable|string|max:60',
        ]);
        $affiliate->update(array_filter($data, fn($v) => $v !== null));
        return response()->json(['ok' => true, 'message' => 'Dados atualizados com sucesso!']);
    }
}
