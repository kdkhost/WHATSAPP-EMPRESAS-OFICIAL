<?php

namespace App\Modules\WhiteLabel\Services;

use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareSaaSService
{
    private ?string $token;
    private ?string $zoneId;

    public function __construct(SettingsManager $settings)
    {
        $this->token = $settings->get('whitelabel.cloudflare_api_token');
        $this->zoneId = $settings->get('whitelabel.cloudflare_zone_id');
    }

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->zoneId);
    }

    /**
     * Adiciona o Custom Hostname no Cloudflare.
     * Retorna o array do resultado (id, status, etc) ou null em caso de erro.
     */
    public function addCustomHostname(string $hostname): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $existing = $this->getCustomHostname($hostname);
        if ($existing) {
            return $existing;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/custom_hostnames", [
                    'hostname' => $hostname,
                    'ssl' => [
                        'method' => 'http',
                        'type' => 'dv',
                        'settings' => [
                            'min_tls_version' => '1.2',
                        ],
                    ]
                ]);

            if ($response->successful()) {
                return $response->json('result');
            }

            Log::warning('Cloudflare API erro ao adicionar custom hostname ('.$hostname.'): ' . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error('Cloudflare API Excecao ('.$hostname.'): ' . $e->getMessage());
            return null;
        }
    }

    public function deleteCustomHostname(string $hostname): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $existing = $this->getCustomHostname($hostname);
        if (!$existing) {
            return true;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->delete("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/custom_hostnames/{$existing['id']}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Cloudflare API Excecao Delete ('.$hostname.'): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna o status atual do Custom Hostname.
     * Ex: 'active', 'pending', 'moved', etc.
     */
    public function getCustomHostnameStatus(string $hostname): ?string
    {
        $existing = $this->getCustomHostname($hostname);
        if (!$existing) {
            return null;
        }

        // Se SSL estiver pendente de validacao
        if (isset($existing['ssl']['status']) && $existing['ssl']['status'] !== 'active') {
            return 'pending_ssl';
        }

        return $existing['status'];
    }

    public function getCustomHostname(string $hostname): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(8)
                ->get("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/custom_hostnames", [
                    'hostname' => $hostname
                ]);

            if ($response->successful() && count($response->json('result', [])) > 0) {
                return $response->json('result.0');
            }
        } catch (\Throwable $e) {
            // fail silent
        }

        return null;
    }
}
