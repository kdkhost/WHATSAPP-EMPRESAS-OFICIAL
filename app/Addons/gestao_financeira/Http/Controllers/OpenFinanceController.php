<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\OpenFinanceConnection;
use App\Addons\gestao_financeira\Services\OpenFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenFinanceController extends BaseController
{
    protected OpenFinanceService $openFinance;

    public function __construct(OpenFinanceService $openFinance)
    {
        $this->openFinance = $openFinance;
    }

    /**
     * Retorna o token para inicializar o Pluggy Connect (Widget).
     */
    public function getConnectToken(Request $request)
    {
        $tenantId = $this->requireTenantId();

        if (! $this->openFinance->isConfigured()) {
            return response()->json([
                'ok' => false,
                'not_configured' => true,
                'can_manage' => $this->isSuperAdmin(),
                'message' => 'O Open Finance ainda não foi configurado pelo administrador do SaaS.'
            ], 422);
        }

        try {
            // ClientUserId permite vincular os webhooks ao tenant específico
            $clientUserId = 'tenant_' . $tenantId;

            $itemId = $request->input('item_id'); // Opcional: Para modo de atualização de conexão existente

            $accessToken = $this->openFinance->createConnectToken($itemId, $clientUserId);

            return response()->json([
                'ok' => true,
                'accessToken' => $accessToken
            ]);
        } catch (\Exception $e) {
            Log::error("[OpenFinance] Erro ao gerar connect token: " . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Erro ao inicializar conexão Open Finance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém as configurações e status do Open Finance.
     */
    public function getConfig()
    {
        $settings = class_exists(\App\Modules\Settings\Services\SettingsManager::class) ? app(\App\Modules\Settings\Services\SettingsManager::class) : null;
        $clientId = $settings?->get('pluggy_client_id') ?: config('pluggy.client_id', '');
        $clientSecret = $settings?->get('pluggy_client_secret') ?: config('pluggy.client_secret', '');
        $baseUrl = $settings?->get('pluggy_base_url') ?: config('pluggy.base_url', 'https://api.pluggy.ai');
        $canManage = $this->isSuperAdmin();

        return response()->json([
            'ok' => true,
            'is_configured' => !empty($clientId) && !empty($clientSecret),
            'client_id' => $canManage ? $clientId : '',
            'client_secret' => $canManage ? $clientSecret : '',
            'client_id_masked' => ($canManage && $clientId) ? Str::mask($clientId, '*', 4, -4) : '',
            'base_url' => $baseUrl,
            'webhook_url' => $canManage ? url('api/gestao-financeira/open-finance-webhook') : '',
            'can_manage' => $canManage
        ]);
    }

    /**
     * Salva as credenciais do Open Finance no SettingsManager (apenas Admin/SuperAdmin do SaaS).
     */
    public function saveConfig(Request $request)
    {
        if (! $this->isSuperAdmin()) {
            abort(403, 'Apenas administradores do SaaS podem configurar o Open Finance.');
        }

        $data = $request->validate([
            'client_id'     => 'required|string|max:150',
            'client_secret' => 'required|string|max:200',
            'base_url'      => 'nullable|string|max:150',
        ]);

        $settings = app(\App\Modules\Settings\Services\SettingsManager::class);
        $settings->set('pluggy_client_id', trim($data['client_id']));
        $settings->set('pluggy_client_secret', trim($data['client_secret']));
        if (! empty($data['base_url'])) {
            $settings->set('pluggy_base_url', trim($data['base_url']));
        }

        \Illuminate\Support\Facades\Cache::forget('pluggy_access_token');

        return response()->json([
            'ok' => true,
            'message' => 'Credenciais do Open Finance salvas com sucesso! Todos os tenants do SaaS já podem conectar contas bancárias.'
        ]);
    }

    /**
     * Callback após o usuário conectar o banco via widget do Pluggy com sucesso.
     * Recebe o itemId.
     */
    public function handleConnectCallback(Request $request)
    {
        $request->validate([
            'item_id' => 'required|string',
        ]);

        $tenantId = $this->requireTenantId();
        $itemId = $request->input('item_id');

        try {
            // Obtém os dados do item diretamente do Pluggy para salvar o nome da instituição, etc.
            $itemData = $this->openFinance->getItem($itemId);
            
            $connection = OpenFinanceConnection::updateOrCreate(
                ['item_id' => $itemId, 'tenant_id' => $tenantId],
                [
                    'provider' => 'pluggy',
                    'connector_id' => $itemData['connector']['id'] ?? null,
                    'connector_name' => $itemData['connector']['name'] ?? 'Banco Desconhecido',
                    'connector_image_url' => $itemData['connector']['imageUrl'] ?? null,
                    'status' => $itemData['status'] ?? 'UPDATING',
                    'error_message' => $itemData['executionStatus'] ?? null,
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Conexão Open Finance vinculada com sucesso. A sincronização de dados está em andamento.',
                'connection_id' => $connection->id
            ]);
        } catch (\Exception $e) {
            Log::error("[OpenFinance] Erro no callback de conexão: " . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Ocorreu um erro ao vincular a conexão Open Finance.'
            ], 500);
        }
    }

    /**
     * Vincula uma conexão Open Finance a uma Conta (Account) existente.
     */
    public function linkAccount(Request $request, OpenFinanceConnection $connection)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $connection->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $request->validate([
            'account_id' => 'required|exists:addon_gestao_financeira_accounts,id',
        ]);

        // Verificar se a account pertence ao tenant
        $accountId = $request->input('account_id');

        $connection->update(['account_id' => $accountId]);

        return response()->json(['ok' => true, 'message' => 'Conexão vinculada à conta com sucesso.']);
    }

    /**
     * Desconecta um item.
     */
    public function destroy(OpenFinanceConnection $connection)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $connection->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        try {
            // Se a conexão estava vinculada a uma conta local, removemos os lançamentos gerados pelo Pluggy
            if ($connection->account_id) {
                \App\Addons\gestao_financeira\Models\Transaction::where('account_id', $connection->account_id)
                    ->whereNotNull('provider_transaction_id')
                    ->delete();
            }

            // Tenta deletar no provedor (se falhar, não impede de deletar localmente)
            try {
                $this->openFinance->deleteItem($connection->item_id);
            } catch (\Exception $e) {
                Log::warning("[OpenFinance] Item já removido ou inacessível no provedor: " . $e->getMessage());
            }

            $connection->delete();
            
            return response()->json(['ok' => true, 'message' => 'Conexão removida e dados sincronizados limpos com sucesso.']);
        } catch (\Exception $e) {
            Log::error("[OpenFinance] Erro ao deletar conexão e limpar dados: " . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Erro ao excluir conexão e limpar os registros.'], 500);
        }
    }
}
