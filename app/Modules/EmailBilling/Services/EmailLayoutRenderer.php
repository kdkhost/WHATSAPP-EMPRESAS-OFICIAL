<?php

namespace App\Modules\EmailBilling\Services;

use App\Modules\EmailBilling\Models\TenantEmailConfig;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;

class EmailLayoutRenderer
{
    public function __construct(protected SettingsManager $settings) {}

    public function wrap(string $contentHtml, array $branding = []): string
    {
        $branding = $this->mergeBranding($branding);

        $company = e($branding['brand_name']);
        $logo = trim((string) $branding['logo_url']);
        $primary = e($branding['primary_color']);
        $accent = e($branding['accent_color']);
        $footerText = e($branding['footer_text']);
        $footerSignature = e($branding['footer_signature']);
        $footerSubtext = e($branding['footer_subtext']);
        $year = now('America/Sao_Paulo')->year;

        $brandText = '<div style="font-size:22px;font-weight:800;line-height:1.2;color:#ffffff;text-shadow:0 1px 2px rgba(15,23,42,.28);">'.$company.'</div>'
            . '<div style="font-size:12px;line-height:1.5;color:rgba(255,255,255,.78);margin-top:3px;">Mensagem oficial</div>';

        // Com logo: a logo é a protagonista do cabeçalho (maior e padronizada),
        // sem repetir o nome da empresa ao lado para não poluir.
        $header = $logo !== ''
            ? self::logoHtml($logo, $branding['brand_name'], false)
                . '<div style="font-size:12px;line-height:1.5;color:rgba(255,255,255,.78);margin-top:2px;letter-spacing:.04em;">Mensagem oficial</div>'
            : $brandText;

        return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:28px;background:#eef2ff;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid rgba(15,23,42,.08);border-radius:22px;overflow:hidden;box-shadow:0 18px 44px rgba(15,23,42,.08);">'
            . '<div style="padding:26px 30px;background:linear-gradient(135deg,'. $primary .', '. $accent .');">'
            . '<div style="padding:6px 0;min-height:56px;">'.$header.'</div>'
            . '</div>'
            . '<div style="padding:30px;line-height:1.75;font-size:15px;color:#334155;">'.$contentHtml.'</div>'
            . '<div style="padding:22px 30px;border-top:1px solid rgba(15,23,42,.08);background:#f8fafc;">'
            . '<div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px;">'.$footerSignature.'</div>'
            . '<div style="font-size:12px;line-height:1.7;color:#64748b;">'.$footerText.'</div>'
            . ($footerSubtext !== '' ? '<div style="font-size:12px;line-height:1.7;color:#94a3b8;margin-top:6px;">'.$footerSubtext.'</div>' : '')
            . '<div style="font-size:11px;line-height:1.7;color:#94a3b8;margin-top:10px;">&copy; '.$year.' '.$company.'. Todos os direitos reservados.</div>'
            . '</div></div></body></html>';
    }

    /**
     * Bloco de logo padronizado para todos os e-mails (layout e templates).
     * Tamanho maior e consistente: até 220x72px, alinhado à esquerda, com
     * separador sutil abaixo para destacar a marca no cabeçalho.
     */
    public static function logoHtml(string $logoUrl, string $alt = '', bool $withDivider = true): string
    {
        $logoUrl = trim($logoUrl);
        if ($logoUrl === '') {
            return '';
        }

        $divider = $withDivider
            ? 'padding:0 0 18px 0;margin:0 0 18px 0;border-bottom:1px solid rgba(255,255,255,.14);'
            : 'padding:0 0 10px 0;margin:0;';

        return '<div class="email-brand-logo" style="'.$divider.'text-align:left;line-height:0;">'
            . '<img src="'.e($logoUrl).'" alt="'.e($alt).'" height="72" '
            . 'style="display:inline-block;height:auto;max-height:72px;width:auto;max-width:220px;border:0;outline:none;text-decoration:none;object-fit:contain;vertical-align:middle;">'
            . '</div>';
    }

    public function brandingFromTenantConfig(?TenantEmailConfig $config, ?Tenant $tenant = null): array
    {
        $raw = is_array($config?->settings) ? $config->settings : [];
        $whitelabel = is_array($tenant?->settings) ? ($tenant->settings['whitelabel'] ?? []) : [];
        $tenantSettings = is_array($tenant?->settings) ? $tenant->settings : [];

        $brandName = $raw['brand_name']
            ?? $whitelabel['brand_name']
            ?? $config?->from_name
            ?? $tenant?->company_name
            ?? null;

        $logoUrl = $raw['logo_url']
            ?? $whitelabel['logo_url']
            ?? $tenantSettings['logo_url']
            ?? $tenantSettings['logo']
            ?? null;

        $primaryColor = $raw['primary_color']
            ?? $whitelabel['primary_color']
            ?? $tenantSettings['primary_color']
            ?? null;

        $accentColor = $raw['accent_color']
            ?? $whitelabel['secondary_color']
            ?? $whitelabel['accent_color']
            ?? $tenantSettings['secondary_color']
            ?? $tenantSettings['accent_color']
            ?? null;

        $footerText = $raw['footer_text'] ?? null;
        $footerSignature = $raw['footer_signature'] ?? $brandName ?? null;
        $footerSubtext = $raw['footer_subtext'] ?? null;

        return $this->mergeBranding([
            'brand_name' => $brandName,
            'logo_url' => $logoUrl,
            'primary_color' => $primaryColor,
            'accent_color' => $accentColor,
            'footer_text' => $footerText,
            'footer_signature' => $footerSignature,
            'footer_subtext' => $footerSubtext,
        ]);
    }

    public function brandingFromContext(array $context = []): array
    {
        $raw = (array) ($context['email_branding'] ?? []);

        $tenant = $context['tenant'] ?? null;
        if (! $tenant && ! empty($context['tenant_id'])) {
            try {
                $tenant = Tenant::find($context['tenant_id']);
            } catch (\Throwable) {}
        }

        $fromTenant = [];
        if ($tenant) {
            $config = $tenant->emailConfig ?? null;
            $fromTenant = $this->brandingFromTenantConfig($config, $tenant);
        }

        return $this->mergeBranding(array_merge($fromTenant, array_filter([
            'brand_name' => $raw['brand_name'] ?? $context['empresa'] ?? null,
            'logo_url' => $raw['logo_url'] ?? $context['logo_empresa'] ?? null,
            'primary_color' => $raw['primary_color'] ?? $context['primary_color'] ?? $context['cor_primaria'] ?? null,
            'accent_color' => $raw['accent_color'] ?? $context['accent_color'] ?? $context['cor_destaque'] ?? $context['cor_secundaria'] ?? null,
            'footer_text' => $raw['footer_text'] ?? null,
            'footer_signature' => $raw['footer_signature'] ?? $context['empresa'] ?? null,
            'footer_subtext' => $raw['footer_subtext'] ?? null,
        ], fn ($val) => $val !== null && $val !== '')));
    }

    protected function mergeBranding(array $branding): array
    {
        $appName = $this->settings->get('app.name', config('app.name'));
        $footerBase = $this->settings->get('app.footer_text', '');
        $primary = $branding['primary_color'] ?? $this->settings->get('theme.primary', '#0d6efd');
        $accent = $branding['accent_color'] ?? $this->settings->get('theme.secondary', '#6610f2');

        return [
            'brand_name' => $branding['brand_name'] ?? $appName,
            'logo_url' => $branding['logo_url'] ?? $this->settings->get('app.logo', ''),
            'primary_color' => $primary ?: '#0d6efd',
            'accent_color' => $accent ?: ($primary ?: '#6610f2'),
            'footer_text' => $branding['footer_text'] ?? ($footerBase !== '' ? $footerBase : 'Esta mensagem faz parte da comunicacao oficial do sistema. Em caso de duvidas, responda este email ou use o canal de atendimento informado pelo remetente.'),
            'footer_signature' => $branding['footer_signature'] ?? $branding['brand_name'] ?? $appName,
            'footer_subtext' => $branding['footer_subtext'] ?? 'Mensagem transacional enviada automaticamente pelo sistema.',
        ];
    }
}
