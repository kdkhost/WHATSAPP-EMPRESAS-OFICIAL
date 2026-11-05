<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Services\MarketingService;
use App\Modules\Marketing\Models\MarketingVisit;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingCampaignController extends Controller
{
    public function __construct(
        protected MarketingService $marketing,
        protected SettingsManager $settings
    ) {}

    /**
     * Exibe a pagina de marketing exclusiva.
     * Esta pagina NUNCA aparece no menu do site publico.
     */
    public function show(Request $request)
    {
        $campaign = $this->marketing->getSettings();

        if (! ($campaign['enabled'] ?? true)) {
            return redirect('/')->with('info', 'Esta promoção foi encerrada. Conheça nossos planos oficiais!');
        }

        // Registra a visita e a rota de onde veio
        $this->marketing->recordVisit($request);

        // Contador de visitas
        $totalVisits = 0;
        try {
            $totalVisits = MarketingVisit::count();
        } catch (\Throwable) {}

        // Resolve cupom ativo de afiliado (via URL, sessão ou cookie)
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

        // Busca planos para exibir na seção promocional (máximo 3 escolhidos no administrativo)
        $featuredPlanIds = (array) ($campaign['featured_plans'] ?? []);
        $discountPercent = max(1, min(99, (int) ($campaign['discount_percent'] ?? 50)));
        $pricingMode = $campaign['pricing_mode'] ?? 'apply_discount';

        $plans = collect();
        try {
            if (! empty($featuredPlanIds)) {
                $rawPlans = Plan::query()
                    ->whereIn('id', array_slice($featuredPlanIds, 0, 3))
                    ->where('active', true)
                    ->get();

                // Ordena na mesma ordem definida na seleção
                $plans = $rawPlans->sortBy(function ($p) use ($featuredPlanIds) {
                    $idx = array_search($p->id, $featuredPlanIds);
                    return $idx !== false ? $idx : 999;
                })->values();
            }

            // Fallback: se nenhum plano foi selecionado pelo admin, seleciona os mesmos 3 planos oficiais do site (Starter, Pro e Enterprise)
            if ($plans->isEmpty()) {
                $allPlans = Plan::query()
                    ->where('active', true)
                    ->where('is_demo', false)
                    ->get();

                // Aplica o mesmo algoritmo de ranqueamento do site público
                $plans = $allPlans->filter(function ($p) {
                    $val = strtolower($p->name . ' ' . ($p->slug ?? ''));
                    return ! collect(['admin', 'operacional', 'sandbox', 'demo', 'interno', 'internal'])->contains(fn ($w) => str_contains($val, $w));
                })->sort(function ($a, $b) {
                    $getRank = function ($p) {
                        $val = strtolower($p->name . ' ' . ($p->slug ?? ''));
                        if (str_contains($val, 'starter')) return 0;
                        if ($p->highlight || str_contains($val, 'profissional') || str_contains($val, 'professional') || str_contains($val, 'pro')) return 1;
                        if (str_contains($val, 'empresarial') || str_contains($val, 'enterprise') || str_contains($val, 'business')) return 2;
                        return 9;
                    };
                    $diff = $getRank($a) - $getRank($b);
                    return $diff !== 0 ? $diff : (($a->sort ?? 0) - ($b->sort ?? 0));
                })->take(3)->values();

                if ($plans->isEmpty()) {
                    $plans = Plan::query()
                        ->where('active', true)
                        ->orderBy('sort')
                        ->limit(3)
                        ->get();
                }
            }

            // Transforma e calcula preços e badges respeitando rigorosamente as configurações da campanha
            $plans->transform(function ($p) use ($discountPercent, $pricingMode, $campaign, $activeCoupon) {
                $basePrice = (float) $p->price;

                if ($basePrice <= 0) {
                    $p->campaign_original_price = 0.0;
                    $p->campaign_promo_price = 0.0;
                    $p->campaign_discount_amount = 0.0;
                } elseif ($pricingMode === 'nominal_is_promo') {
                    // O preço do banco já é o promocional: calcula o valor "De" proporcional
                    $p->campaign_promo_price = $basePrice;
                    $ratio = (100 - $discountPercent) / 100;
                    $p->campaign_original_price = $ratio > 0 ? round($basePrice / $ratio, 2) : round($basePrice * 2, 2);
                    $p->campaign_discount_amount = max(0.0, $p->campaign_original_price - $p->campaign_promo_price);
                } else {
                    // apply_discount (padrão): o preço base é o valor nominal ("De") e aplica o desconto promocional ("Por")
                    $p->campaign_original_price = $basePrice;
                    $p->campaign_promo_price = round($basePrice * ((100 - $discountPercent) / 100), 2);
                    $p->campaign_discount_amount = max(0.0, $p->campaign_original_price - $p->campaign_promo_price);
                }

                $p->campaign_discount_percent = $discountPercent;
                $p->campaign_badge = ! empty($campaign['discount_badge']) ? $campaign['discount_badge'] : ($discountPercent . '% OFF');

                // Garante decodificação completa mesmo se features foi salvo com dupla codificação JSON
                $feats = $p->features;
                while (is_string($feats)) {
                    $decoded = json_decode($feats, true);
                    if ($decoded === null || $decoded === $feats) break;
                    $feats = $decoded;
                }
                if (! is_array($feats)) {
                    $feats = [];
                }
                $p->features = collect($feats)->map(function ($f) {
                    if (is_string($f) && mb_strtolower($f) === 'contracts') {
                        return 'Módulo de Contratos Digitais';
                    }
                    return $f;
                })->values()->all();

                if ($activeCoupon) {
                    $p->coupon_code = $activeCoupon['code'];
                    $p->coupon_label = $activeCoupon['label'];
                    $p->coupon_formatted_discount = $activeCoupon['discount_formatted'];
                    $p->register_url = route('register', [
                        'ref'   => $activeCoupon['slug'],
                        'cupom' => $activeCoupon['code'],
                        'plan'  => $p->id,
                    ]);
                } else {
                    $p->register_url = route('register', ['plan' => $p->id]);
                }

                return $p;
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[MarketingCampaign] Falha no cálculo de planos da campanha: ' . $e->getMessage());
        }

        // Depoimentos
        $testimonials = collect();
        try {
            $testimonials = DB::table('testimonials')->where('active', true)->orderBy('sort')->limit(6)->get();
        } catch (\Throwable) {}

        $appName = $this->settings->get('app.name', config('app.name'));
        $appLogo = $this->settings->get('app.logo');
        $appFavicon = $this->settings->get('app.favicon');

        return view('marketing.campaign', compact(
            'campaign',
            'totalVisits',
            'plans',
            'testimonials',
            'appName',
            'appLogo',
            'appFavicon',
            'activeCoupon'
        ));
    }

    /**
     * Captura de lead via AJAX na pagina promocional.
     */
    public function captureLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:120'],
            'whatsapp' => ['required', 'string', 'min:8', 'max:30'],
            'email'    => ['nullable', 'email', 'max:120'],
            'interest' => ['nullable', 'string', 'max:120'],
        ], [
            'name.required'     => 'Por favor, informe seu nome.',
            'whatsapp.required' => 'Por favor, informe seu WhatsApp para receber a oferta.',
            'email.email'       => 'Informe um e-mail válido.',
        ]);

        try {
            $lead = $this->marketing->captureLead($request);

            $salesNumber = preg_replace('/\D/', '', (string) $this->settings->get('marketing.whatsapp', $this->settings->get('app.whatsapp_support', '5511999999999')));
            if (! str_starts_with($salesNumber, '55') && strlen($salesNumber) >= 10 && strlen($salesNumber) <= 11) {
                $salesNumber = '55' . $salesNumber;
            }

            $activeCoupon = null;
            try {
                $activeCoupon = app(\App\Modules\Affiliates\Services\AffiliateService::class)->resolveActiveCoupon($request);
                if ($activeCoupon) {
                    $cupomInfo = " [Cupom: {$activeCoupon['code']} - {$activeCoupon['label']}]";
                    $lead->update([
                        'notes' => trim(($lead->notes ?? '') . $cupomInfo),
                    ]);
                }
            } catch (\Throwable) {}

            $leadName = urlencode($lead->name);
            $interest = urlencode($lead->interest ?: 'Condição Promocional');
            $couponMsgPart = $activeCoupon ? " com o cupom {$activeCoupon['code']}" : "";
            $whatsappUrl = "https://wa.me/{$salesNumber}?text=" . urlencode("Olá! Me cadastrei na página promocional exclusiva{$couponMsgPart}. Meu nome é {$lead->name} e quero resgatar meu desconto para {$lead->interest}!");

            $registerUrl = route('register', array_filter([
                'ref'   => $activeCoupon['slug'] ?? $request->input('ref'),
                'cupom' => $activeCoupon['code'] ?? $request->input('cupom'),
            ]));

            return response()->json([
                'ok'            => true,
                'message'       => 'Parabéns! Sua condição especial foi reservada com sucesso.',
                'lead_id'       => $lead->id,
                'whatsapp_url'  => $whatsappUrl,
                'active_coupon' => $activeCoupon,
                'register_url'  => $registerUrl,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ocorreu uma falha ao registrar seus dados. Tente novamente em instantes.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
