<?php

namespace App\Modules\WhiteLabel\Services;

use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\ManagedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TenantWhiteLabelService
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly CloudflareSaaSService $cloudflareSaaS
    ) {
    }

    public function normalizeDomain(?string $domain): ?string
    {
        $domain = trim((string) $domain);
        if ($domain === '') {
            return null;
        }

        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
        $domain = strtolower(trim($domain, ". \t\n\r\0\x0B"));

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $domain = strtolower($ascii);
            }
        }

        return $this->isValidDomain($domain) ? $domain : null;
    }

    public function isValidDomain(?string $domain): bool
    {
        if (! is_string($domain) || $domain === '' || strlen($domain) > 253) {
            return false;
        }

        if (in_array($domain, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
    }

    public function dnsTarget(?Request $request = null): string
    {
        $configured = $this->normalizeDomain($this->settings->get('whitelabel.dns_target'));
        if ($configured) {
            return $configured;
        }

        $globalDomain = $this->normalizeDomain($this->settings->get('whitelabel.custom_domain'));
        if ($globalDomain) {
            return $globalDomain;
        }

        $appHost = $this->normalizeDomain(parse_url((string) config('app.url'), PHP_URL_HOST) ?: null);
        if ($appHost) {
            return $appHost;
        }

        return $this->normalizeDomain($request?->getHost()) ?: 'localhost';
    }

    public function reservedDomains(?Request $request = null): array
    {
        return array_values(array_unique(array_filter([
            $this->normalizeDomain(parse_url((string) config('app.url'), PHP_URL_HOST) ?: null),
            $this->normalizeDomain($this->settings->get('whitelabel.custom_domain')),
            $this->normalizeDomain($this->settings->get('whitelabel.dns_target')),
        ])));
    }

    public function save(Tenant $tenant, array $data, ?Request $request = null): Tenant
    {
        $domain = $this->normalizeDomain($data['custom_domain'] ?? null);
        $currentDomain = $this->normalizeDomain($tenant->custom_domain);
        $settings = $tenant->settings ?? [];
        $whiteLabel = $settings['whitelabel'] ?? [];
        $currentLogo = $whiteLabel['logo_url'] ?? null;
        $currentFavicon = $whiteLabel['favicon_url'] ?? null;

        foreach ([
            'enabled', 'brand_name', 'slogan', 'logo_url', 'favicon_url',
            'primary_color', 'secondary_color', 'sidebar_color', 'custom_css',
            'footer_text', 'hide_powered_by', 'custom_login',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $whiteLabel[$key] = $key === 'custom_css'
                    ? $this->sanitizeCss((string) $data[$key])
                    : $data[$key];
            }
        }

        ManagedAsset::replaceIfChanged($currentLogo, $whiteLabel['logo_url'] ?? null);
        ManagedAsset::replaceIfChanged($currentFavicon, $whiteLabel['favicon_url'] ?? null);

        if ($currentDomain && $domain !== $currentDomain) {
            $this->cloudflareSaaS->deleteCustomHostname($currentDomain);
        }

        $settings['whitelabel'] = $whiteLabel;

        $payload = [
            'settings' => $settings,
            'custom_domain' => $domain,
            'custom_domain_dns_target' => $domain ? $this->dnsTarget($request) : null,
        ];

        if (! $domain) {
            $payload += [
                'custom_domain_status' => 'not_configured',
                'custom_domain_verified_at' => null,
                'custom_domain_last_checked_at' => null,
                'custom_domain_last_error' => null,
            ];
        } elseif ($domain !== $currentDomain) {
            $payload += [
                'custom_domain_status' => 'pending',
                'custom_domain_verified_at' => null,
                'custom_domain_last_checked_at' => null,
                'custom_domain_last_error' => 'Aguardando propagacao DNS.',
            ];
        }

        $tenant->forceFill($payload)->save();

        return $tenant->fresh();
    }

    public function checkDomain(Tenant $tenant, ?Request $request = null): array
    {
        $domain = $this->normalizeDomain($tenant->custom_domain);
        if (! $domain) {
            $tenant->forceFill([
                'custom_domain_status' => 'not_configured',
                'custom_domain_verified_at' => null,
                'custom_domain_last_checked_at' => now(),
                'custom_domain_last_error' => 'Nenhum dominio configurado.',
            ])->save();

            return $this->statusPayload($tenant->fresh(), [
                'verified' => false,
                'message' => 'Nenhum dominio configurado.',
            ]);
        }

        $target = $this->normalizeDomain($tenant->custom_domain_dns_target) ?: $this->dnsTarget($request);
        $cnameRecords = $this->cnameRecords($domain);
        $domainAddresses = $this->addressRecords($domain);
        $targetAddresses = $this->addressRecords($target);

        $cnameMatches = in_array($target, array_map([$this, 'normalizeRecordTarget'], $cnameRecords), true);
        $addressMatches = count(array_intersect($domainAddresses, $targetAddresses)) > 0;
        $verified = $cnameMatches || $addressMatches;

        if (! $verified) {
            $urlsToTest = [
                "https://{$domain}/api/v1/health",
                "http://{$domain}/api/v1/health",
            ];

            foreach ($urlsToTest as $url) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(8)
                        ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                        ->withoutVerifying()
                        ->get($url);

                    if ($response->ok() && $response->json('ok') === true) {
                        $verified = true;
                        break;
                    }
                } catch (\Throwable) {
                    // Ignore fallback errors, try next URL
                }
            }
        }

        $statusName = 'pending';
        $message = 'Aguardando propagacao DNS. Mantenha o CNAME/A apontando para o alvo informado.';

        if ($verified) {
            $cfResult = $this->cloudflareSaaS->addCustomHostname($domain);
            if ($this->cloudflareSaaS->isConfigured() && $cfResult) {
                $cfStatus = $this->cloudflareSaaS->getCustomHostnameStatus($domain);
                if ($cfStatus === 'pending_ssl' || $cfStatus === 'pending') {
                    $verified = false;
                    $statusName = 'provisioning_ssl';
                    $message = 'Gerando Certificado SSL no Cloudflare. Aguarde alguns minutos e valide novamente.';
                } else {
                    $statusName = 'active';
                    $message = 'Dominio validado. O acesso por dominio proprio esta liberado.';
                }
            } else {
                $statusName = 'active';
                $message = 'Dominio validado. O acesso por dominio proprio esta liberado.';
            }
        }

        $tenant->forceFill([
            'custom_domain_dns_target' => $target,
            'custom_domain_status' => $statusName,
            'custom_domain_verified_at' => $statusName === 'active' ? now() : null,
            'custom_domain_last_checked_at' => now(),
            'custom_domain_last_error' => ($statusName === 'active' || $statusName === 'provisioning_ssl')
                ? null
                : 'DNS ainda nao aponta para o alvo informado ou proxy esta bloqueando a verificacao.',
        ])->save();

        return $this->statusPayload($tenant->fresh(), [
            'verified' => $verified,
            'message' => $message,
            'cname_records' => $cnameRecords,
            'domain_addresses' => $domainAddresses,
            'target_addresses' => $targetAddresses,
        ]);
    }

    public function statusPayload(Tenant $tenant, array $extra = []): array
    {
        return array_merge([
            'domain' => $tenant->custom_domain,
            'status' => $tenant->custom_domain_status ?: 'not_configured',
            'status_label' => $this->statusLabel($tenant->custom_domain_status ?: 'not_configured'),
            'dns_target' => $tenant->custom_domain_dns_target,
            'verified_at' => $tenant->custom_domain_verified_at?->format('d/m/Y H:i'),
            'last_checked_at' => $tenant->custom_domain_last_checked_at?->format('d/m/Y H:i'),
            'last_error' => $tenant->custom_domain_last_error,
        ], $extra);
    }

    public function tenantForHost(string $host): ?Tenant
    {
        $domain = $this->normalizeDomain($host);
        if (! $domain) {
            return null;
        }

        try {
            if (! Schema::hasColumn('tenants', 'custom_domain')) {
                return null;
            }

            return Tenant::query()
                ->where('custom_domain', $domain)
                ->whereIn('custom_domain_status', ['active', 'provisioning_ssl'])
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Validado',
            'pending' => 'Aguardando DNS',
            'provisioning_ssl' => 'Gerando SSL',
            'failed' => 'Falhou',
            default => 'Nao configurado',
        };
    }

    public function sanitizeCss(string $css): string
    {
        $css = preg_replace('/<\/?\s*script[^>]*>/i', '', $css) ?? '';
        $css = str_ireplace('</style', '<\/style', $css);

        return mb_substr($css, 0, 10000);
    }

    protected function cnameRecords(string $domain): array
    {
        if (! function_exists('dns_get_record')) {
            return [];
        }

        $records = @dns_get_record($domain, DNS_CNAME);
        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record) => $record['target'] ?? null,
            $records
        )));
    }

    protected function addressRecords(string $domain): array
    {
        $records = [];

        if (function_exists('dns_get_record')) {
            foreach ([DNS_A, defined('DNS_AAAA') ? DNS_AAAA : DNS_A] as $type) {
                $result = @dns_get_record($domain, $type);
                if (! is_array($result)) {
                    continue;
                }

                foreach ($result as $record) {
                    foreach (['ip', 'ipv6'] as $key) {
                        if (! empty($record[$key])) {
                            $records[] = (string) $record[$key];
                        }
                    }
                }
            }
        }

        if ($records === []) {
            $ip = @gethostbyname($domain);
            if (is_string($ip) && $ip !== $domain) {
                $records[] = $ip;
            }
        }

        return array_values(array_unique($records));
    }

    protected function normalizeRecordTarget(?string $target): ?string
    {
        return $this->normalizeDomain($target);
    }
}
