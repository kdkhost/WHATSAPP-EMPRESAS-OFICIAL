<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\MarketingLead;
use App\Modules\Marketing\Models\MarketingVisit;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingService
{
    public function __construct(protected SettingsManager $settings) {}

    /**
     * Retorna configuracoes da campanha promocional.
     */
    public function getSettings(): array
    {
        return [
            'enabled'           => (bool) $this->settings->get('marketing.enabled', true),
            'headline'          => (string) $this->settings->get('marketing.headline', 'Oferta Exclusiva: Automatize Suas Cobranças e Aumente Sua Receita em até 72%'),
            'subtitle'          => (string) $this->settings->get('marketing.subtitle', 'Garanta agora condições especiais de lançamento com setup prioritário, suporte VIP e teste estendido sem compromisso.'),
            'discount_badge'    => (string) $this->settings->get('marketing.discount_badge', '🔥 ATÉ 50% OFF NO PRIMEIRO MÊS'),
            'cta_text'          => (string) $this->settings->get('marketing.cta_text', 'Garantir Minha Condição Especial'),
            'whatsapp'          => (string) $this->settings->get('marketing.whatsapp', $this->settings->get('app.whatsapp_support', '5511999999999')),
            'countdown_hours'   => (int) $this->settings->get('marketing.countdown_hours', 6),
            'banner_alert'      => (string) $this->settings->get('marketing.banner_alert', '⚡ Restam poucas vagas para a condição promocional deste mês!'),
            'featured_plans'    => (array) ($this->settings->get('marketing.featured_plans') ?: []),
            'discount_percent'  => (int) $this->settings->get('marketing.discount_percent', 50),
            'pricing_mode'      => (string) $this->settings->get('marketing.pricing_mode', 'apply_discount'),
        ];
    }

    /**
     * Salva as configuracoes da campanha.
     */
    public function saveSettings(array $data): void
    {
        if (isset($data['enabled'])) {
            $this->settings->set('marketing.enabled', (bool) $data['enabled'], 'bool');
        }
        if (isset($data['headline'])) {
            $this->settings->set('marketing.headline', (string) $data['headline'], 'string');
        }
        if (isset($data['subtitle'])) {
            $this->settings->set('marketing.subtitle', (string) $data['subtitle'], 'string');
        }
        if (isset($data['discount_badge'])) {
            $this->settings->set('marketing.discount_badge', (string) $data['discount_badge'], 'string');
        }
        if (isset($data['cta_text'])) {
            $this->settings->set('marketing.cta_text', (string) $data['cta_text'], 'string');
        }
        if (isset($data['whatsapp'])) {
            $this->settings->set('marketing.whatsapp', (string) $data['whatsapp'], 'string');
        }
        if (isset($data['countdown_hours'])) {
            $this->settings->set('marketing.countdown_hours', max(1, (int) $data['countdown_hours']), 'string');
        }
        if (isset($data['banner_alert'])) {
            $this->settings->set('marketing.banner_alert', (string) $data['banner_alert'], 'string');
        }
        if (array_key_exists('featured_plans', $data)) {
            $rawPlans = (array) ($data['featured_plans'] ?? []);
            $planIds = array_slice(array_values(array_filter(array_map('intval', $rawPlans))), 0, 3);
            $this->settings->set('marketing.featured_plans', $planIds, 'json');
        }
        if (isset($data['discount_percent'])) {
            $percent = max(1, min(99, (int) $data['discount_percent']));
            $this->settings->set('marketing.discount_percent', $percent, 'int');
        }
        if (isset($data['pricing_mode'])) {
            $mode = in_array($data['pricing_mode'], ['apply_discount', 'nominal_is_promo'], true) ? $data['pricing_mode'] : 'apply_discount';
            $this->settings->set('marketing.pricing_mode', $mode, 'string');
        }
    }

    /**
     * Registra o acesso a pagina de marketing e classifica a origem.
     */
    public function recordVisit(Request $request): MarketingVisit
    {
        $ip = $request->ip();
        $userAgent = (string) $request->userAgent();
        $referer = (string) $request->headers->get('referer', '');
        $utmSource = (string) $request->input('utm_source', '');
        $utmMedium = (string) $request->input('utm_medium', '');
        $utmCampaign = (string) $request->input('utm_campaign', '');
        $utmTerm = (string) $request->input('utm_term', '');
        $utmContent = (string) $request->input('utm_content', '');

        $sessionId = (string) $request->session()->getId();
        if (! $sessionId) {
            $sessionId = $request->cookie('mkt_session', Str::random(40));
        }

        $platform = $this->detectOriginPlatform($referer, $utmSource);
        $device = $this->detectDevice($userAgent);

        $refererHost = '';
        if ($referer !== '') {
            $parsed = parse_url($referer, PHP_URL_HOST);
            $refererHost = is_string($parsed) ? strtolower($parsed) : '';
        }

        // Verifica se ja houve visita deste IP ou Sessao nas ultimas 24 horas para definir visitante unico
        $recentVisit = null;
        try {
            $recentVisit = MarketingVisit::where(function ($q) use ($sessionId, $ip) {
                if ($sessionId) {
                    $q->where('session_id', $sessionId);
                }
                if ($ip) {
                    $q->orWhere('ip_address', $ip);
                }
            })->where('created_at', '>=', now()->subHours(24))->first();
        } catch (\Throwable) {
            // Em caso de tabela nao migrada
        }

        $isUnique = ($recentVisit === null);

        $visit = new MarketingVisit([
            'session_id'      => $sessionId,
            'ip_address'      => $ip,
            'user_agent'      => Str::limit($userAgent, 500),
            'device_type'     => $device,
            'referer'         => Str::limit($referer, 500),
            'referer_host'    => Str::limit($refererHost, 190),
            'origin_platform' => $platform,
            'url'             => Str::limit($request->fullUrl(), 500),
            'utm_source'      => Str::limit($utmSource, 100),
            'utm_medium'      => Str::limit($utmMedium, 100),
            'utm_campaign'    => Str::limit($utmCampaign, 100),
            'utm_term'        => Str::limit($utmTerm, 100),
            'utm_content'     => Str::limit($utmContent, 100),
            'is_unique'       => $isUnique,
        ]);

        try {
            $visit->save();
        } catch (\Throwable) {
            // Silencia falha se DB indisponivel
        }

        return $visit;
    }

    /**
     * Registra um lead capturado na landing page.
     */
    public function captureLead(Request $request): MarketingLead
    {
        $ip = $request->ip();
        $userAgent = (string) $request->userAgent();
        $referer = (string) $request->headers->get('referer', (string) $request->input('referer', ''));
        $utmSource = (string) $request->input('utm_source', '');

        $platform = $this->detectOriginPlatform($referer, $utmSource);
        $device = $this->detectDevice($userAgent);

        $lead = new MarketingLead([
            'name'            => trim((string) $request->input('name')),
            'whatsapp'        => preg_replace('/[^\d\+\-\(\)\s]/', '', (string) $request->input('whatsapp')),
            'email'           => filter_var($request->input('email'), FILTER_VALIDATE_EMAIL) ? strtolower(trim((string) $request->input('email'))) : null,
            'interest'        => Str::limit(trim((string) $request->input('interest', 'Oferta Promocional')), 120),
            'notes'           => $request->input('notes'),
            'status'          => 'new',
            'origin_platform' => $platform,
            'referer'         => Str::limit($referer, 500),
            'utm_source'      => Str::limit($utmSource, 100),
            'utm_medium'      => Str::limit((string) $request->input('utm_medium', ''), 100),
            'utm_campaign'    => Str::limit((string) $request->input('utm_campaign', ''), 100),
            'ip_address'      => $ip,
            'device_type'     => $device,
        ]);

        $lead->save();

        return $lead;
    }

    /**
     * Identifica a plataforma de origem do cliente (Instagram, Facebook, Google, etc.).
     */
    public function detectOriginPlatform(string $referer, string $utmSource = ''): string
    {
        $source = strtolower(trim($utmSource));
        $ref = strtolower(trim($referer));

        // Prioridade 1: UTM Source explicito
        if ($source !== '') {
            if (str_contains($source, 'insta')) return 'Instagram';
            if (str_contains($source, 'face') || str_contains($source, 'meta') || str_contains($source, 'fb')) return 'Facebook';
            if (str_contains($source, 'whats') || str_contains($source, 'wa')) return 'WhatsApp';
            if (str_contains($source, 'goog') || str_contains($source, 'gads')) return 'Google Ads';
            if (str_contains($source, 'tik')) return 'TikTok';
            if (str_contains($source, 'you') || str_contains($source, 'yt')) return 'YouTube';
            if (str_contains($source, 'twit') || str_contains($source, 'x.com')) return 'Twitter / X';
            if (str_contains($source, 'link')) return 'LinkedIn';
            if (str_contains($source, 'email') || str_contains($source, 'mail')) return 'E-mail Marketing';
            return ucfirst($source);
        }

        // Prioridade 2: Referer HTTP
        if ($ref === '') {
            return 'Acesso Direto';
        }

        if (str_contains($ref, 'instagram.com') || str_contains($ref, 'cdninstagram')) return 'Instagram';
        if (str_contains($ref, 'facebook.com') || str_contains($ref, 'fb.com') || str_contains($ref, 'messenger')) return 'Facebook';
        if (str_contains($ref, 'whatsapp.com') || str_contains($ref, 'wa.me')) return 'WhatsApp';
        if (str_contains($ref, 'google.') || str_contains($ref, 'doubleclick.')) return 'Google';
        if (str_contains($ref, 'tiktok.com')) return 'TikTok';
        if (str_contains($ref, 'youtube.com') || str_contains($ref, 'youtu.be')) return 'YouTube';
        if (str_contains($ref, 't.co') || str_contains($ref, 'twitter.com') || str_contains($ref, 'x.com')) return 'Twitter / X';
        if (str_contains($ref, 'linkedin.com') || str_contains($ref, 'lnkd.in')) return 'LinkedIn';
        if (str_contains($ref, 'bing.com')) return 'Bing';
        if (str_contains($ref, 'yahoo.com')) return 'Yahoo';

        $parsed = parse_url($ref, PHP_URL_HOST);
        return $parsed ? ucfirst(str_replace('www.', '', $parsed)) : 'Site Externo';
    }

    /**
     * Detecta o tipo de dispositivo (Mobile, Tablet, Desktop).
     */
    public function detectDevice(string $ua): string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'tablet') || str_contains($uaLower, 'ipad')) {
            return 'Tablet';
        }
        if (str_contains($uaLower, 'mobile') || str_contains($uaLower, 'android') || str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipod')) {
            return 'Mobile';
        }
        return 'Desktop';
    }

    /**
     * Metricas consolidadas para o painel administrativo.
     */
    public function getAnalytics(): array
    {
        try {
            $totalVisits = MarketingVisit::count();
            $uniqueVisits = MarketingVisit::where('is_unique', true)->count();
            $totalLeads = MarketingLead::count();
            $newLeads = MarketingLead::where('status', 'new')->count();

            $conversionRate = $totalVisits > 0 ? round(($totalLeads / $totalVisits) * 100, 1) : 0;

            // Top Plataformas de Origem
            $origins = MarketingVisit::select('origin_platform', DB::raw('count(*) as count'))
                ->groupBy('origin_platform')
                ->orderByDesc('count')
                ->limit(8)
                ->get()
                ->map(fn ($r) => [
                    'platform'   => $r->origin_platform,
                    'count'      => $r->count,
                    'percentage' => $totalVisits > 0 ? round(($r->count / $totalVisits) * 100, 1) : 0,
                    'icon'       => $this->getPlatformIcon($r->origin_platform),
                    'color'      => $this->getPlatformColor($r->origin_platform),
                ]);

            // Top Campanhas UTM
            $campaigns = MarketingVisit::whereNotNull('utm_campaign')
                ->where('utm_campaign', '!=', '')
                ->select('utm_campaign', DB::raw('count(*) as count'))
                ->groupBy('utm_campaign')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            // Dispositivos
            $devices = MarketingVisit::select('device_type', DB::raw('count(*) as count'))
                ->groupBy('device_type')
                ->get()
                ->pluck('count', 'device_type')
                ->all();

            return [
                'total_visits'    => $totalVisits,
                'unique_visits'   => $uniqueVisits,
                'total_leads'     => $totalLeads,
                'new_leads'       => $newLeads,
                'conversion_rate' => $conversionRate,
                'origins'         => $origins,
                'campaigns'       => $campaigns,
                'devices'         => $devices,
            ];
        } catch (\Throwable $e) {
            return [
                'total_visits'    => 0,
                'unique_visits'   => 0,
                'total_leads'     => 0,
                'new_leads'       => 0,
                'conversion_rate' => 0,
                'origins'         => collect(),
                'campaigns'       => collect(),
                'devices'         => [],
            ];
        }
    }

    public function getPlatformIcon(string $platform): string
    {
        return match ($platform) {
            'Instagram'      => 'bi-instagram',
            'Facebook'       => 'bi-facebook',
            'WhatsApp'       => 'bi-whatsapp',
            'Google', 'Google Ads' => 'bi-google',
            'TikTok'         => 'bi-tiktok',
            'YouTube'        => 'bi-youtube',
            'Twitter / X'    => 'bi-twitter-x',
            'LinkedIn'       => 'bi-linkedin',
            'Acesso Direto'  => 'bi-box-arrow-in-right',
            default          => 'bi-globe2',
        };
    }

    public function getPlatformColor(string $platform): string
    {
        return match ($platform) {
            'Instagram'      => '#E1306C',
            'Facebook'       => '#1877F2',
            'WhatsApp'       => '#25D366',
            'Google', 'Google Ads' => '#EA4335',
            'TikTok'         => '#000000',
            'YouTube'        => '#FF0000',
            'Twitter / X'    => '#1DA1F2',
            'LinkedIn'       => '#0A66C2',
            'Acesso Direto'  => '#6c757d',
            default          => '#0d6efd',
        };
    }
}
