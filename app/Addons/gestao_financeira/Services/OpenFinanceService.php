<?php

namespace App\Addons\gestao_financeira\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class OpenFinanceService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $settings = class_exists(\App\Modules\Settings\Services\SettingsManager::class) ? app(\App\Modules\Settings\Services\SettingsManager::class) : null;
        $this->baseUrl = $settings?->get('pluggy_base_url') ?: config('pluggy.base_url', 'https://api.pluggy.ai');
        $this->clientId = $settings?->get('pluggy_client_id') ?: config('pluggy.client_id', '');
        $this->clientSecret = $settings?->get('pluggy_client_secret') ?: config('pluggy.client_secret', '');
    }

    /**
     * Verifica se as credenciais do provedor Open Finance estão configuradas.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Obtém o token de acesso à API da Pluggy (API Key).
     * O token expira em X tempo, então usamos cache por 1 hora.
     */
    protected function getAccessToken(): string
    {
        return Cache::remember('pluggy_access_token', 3600, function () {
            $response = Http::post("{$this->baseUrl}/auth", [
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ]);

            if ($response->failed()) {
                throw new Exception("Falha ao autenticar na API do Pluggy: " . $response->body());
            }

            return $response->json('apiKey');
        });
    }

    /**
     * Retorna uma instância do client HTTP configurada com o token.
     */
    protected function client()
    {
        return Http::withHeaders([
            'X-API-KEY' => $this->getAccessToken(),
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    /**
     * Gera um token temporário (Connect Token) para inicializar o Widget de conexão no frontend.
     */
    public function createConnectToken(?string $itemId = null, ?string $clientUserId = null): string
    {
        $payload = [];
        if ($itemId) {
            $payload['itemId'] = $itemId;
        }
        if ($clientUserId) {
            $payload['clientUserId'] = $clientUserId; // Útil para webhooks
        }

        $response = $this->client()->post('/connect_token', $payload);

        if ($response->failed()) {
            throw new Exception("Falha ao criar Connect Token: " . $response->body());
        }

        return $response->json('accessToken');
    }

    /**
     * Obtém detalhes de um Item (conexão) específico.
     */
    public function getItem(string $itemId): array
    {
        $response = $this->client()->get("/items/{$itemId}");
        if ($response->failed()) {
            throw new Exception("Falha ao buscar Item: " . $response->body());
        }
        return $response->json();
    }

    /**
     * Exclui um Item (desconecta o banco).
     */
    public function deleteItem(string $itemId): void
    {
        $this->client()->delete("/items/{$itemId}");
    }

    /**
     * Lista contas bancárias associadas a um Item.
     */
    public function getAccounts(string $itemId): array
    {
        $response = $this->client()->get("/accounts", ['itemId' => $itemId]);
        if ($response->failed()) {
            throw new Exception("Falha ao buscar Accounts: " . $response->body());
        }
        return $response->json('results') ?? [];
    }

    /**
     * Lista transações de uma conta específica, com paginação/filtros.
     */
    public function getTransactions(string $accountId, array $filters = []): array
    {
        $query = array_merge(['accountId' => $accountId], $filters);
        $response = $this->client()->get("/transactions", $query);
        if ($response->failed()) {
            throw new Exception("Falha ao buscar Transações: " . $response->body());
        }
        return $response->json('results') ?? [];
    }
}
