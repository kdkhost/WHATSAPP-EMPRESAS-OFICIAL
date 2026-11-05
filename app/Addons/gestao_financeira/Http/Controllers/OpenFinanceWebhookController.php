<?php

namespace App\Addons\gestao_financeira\Http\Controllers;

use App\Addons\gestao_financeira\Models\OpenFinanceConnection;
use App\Addons\gestao_financeira\Models\Transaction;
use App\Addons\gestao_financeira\Services\OpenFinanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class OpenFinanceWebhookController extends Controller
{
    protected OpenFinanceService $openFinance;

    public function __construct(OpenFinanceService $openFinance)
    {
        $this->openFinance = $openFinance;
    }

    /**
     * Webhook chamado pelo Pluggy para notificar eventos ou verificação pelo navegador.
     */
    public function handle(Request $request)
    {
        // Se acessado via GET ou HEAD (ex.: navegador ou verificação de disponibilidade)
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            // Se aberto pelo navegador esperando HTML, exibe painel visual sofisticado de status
            if ($request->acceptsHtml() && ! $request->expectsJson() && ! str_contains($request->header('Accept', ''), 'application/json')) {
                return response()->view('gestao-financeira::webhook_status');
            }

            return response()->json([
                'ok' => true,
                'status' => 'online',
                'service' => 'Open Finance Webhook Receiver (Pluggy)',
                'message' => 'Endpoint ativo e pronto para receber notificações de eventos bancários via POST.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $event = $request->input('event');
        $itemId = $request->input('itemId');

        Log::info("[OpenFinance Webhook] Evento: {$event} | ItemID: {$itemId}");

        // Ping de teste ou webhook vazio de validação
        if (! $itemId && ($event === 'test' || $request->input('type') === 'test' || empty($request->all()))) {
            return response()->json([
                'ok' => true,
                'status' => 'online',
                'message' => 'Ping de teste do webhook recebido com sucesso.',
            ]);
        }

        if (! $itemId) {
            return response()->json(['ok' => false, 'message' => 'Missing itemId'], 400);
        }

        $connection = OpenFinanceConnection::where('item_id', $itemId)->first();
        if (!$connection) {
            return response()->json(['ok' => true, 'message' => 'Connection not found in our database (maybe deleted).']);
        }

        try {
            switch ($event) {
                case 'item/created':
                case 'item/updated':
                case 'transactions/created':
                case 'transactions/updated':
                    $this->syncTransactions($connection);
                    $connection->update([
                        'status' => 'UPDATED',
                        'error_message' => null,
                        'last_sync_at' => now()
                    ]);
                    break;
                case 'item/deleted':
                    $connection->update([
                        'status' => 'OUTDATED',
                        'error_message' => 'Conexão encerrada ou removida na instituição financeira.',
                    ]);
                    break;
                case 'item/error':
                    $errorData = $request->input('data.error', []);
                    $connection->update([
                        'status' => 'LOGIN_ERROR',
                        'error_message' => $errorData['message'] ?? 'Erro na sincronização com a instituição financeira.'
                    ]);
                    break;
                case 'item/waiting_user_input':
                case 'item/waiting_user_action':
                    $connection->update([
                        'status' => 'WAITING_USER_INPUT',
                        'error_message' => 'Ação do usuário ou reautenticação necessária no app do banco (MFA).'
                    ]);
                    break;
            }
        } catch (\Exception $e) {
            Log::error("[OpenFinance Webhook Error] " . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error processing webhook'], 500);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Sincroniza as transações da API do Pluggy para o banco local.
     */
    protected function syncTransactions(OpenFinanceConnection $connection)
    {
        if (!$connection->account_id) {
            // Conta local não foi vinculada ainda, não fazemos sync de transactions.
            return;
        }

        // 1. Pega a conta bancária mapeada no Pluggy para esse item
        $accounts = $this->openFinance->getAccounts($connection->item_id);
        
        foreach ($accounts as $pluggyAccount) {
            // Para simplificar, sincronizamos transações dos últimos 30 dias na atualização
            $transactions = $this->openFinance->getTransactions($pluggyAccount['id']);
            
            foreach ($transactions as $tx) {
                // $tx['amount'] é negativo para despesas, positivo para receitas no Pluggy
                $amount = (float) $tx['amount'];
                $type = $amount >= 0 ? 'receita' : 'despesa';
                
                // Formatar para salvar
                $absAmount = abs($amount);
                
                // Salvar ou atualizar
                Transaction::updateOrCreate(
                    [
                        'provider_transaction_id' => $tx['id'],
                        'tenant_id' => $connection->tenant_id,
                    ],
                    [
                        'account_id' => $connection->account_id,
                        'type' => $type,
                        'description' => substr($tx['description'], 0, 255),
                        'amount' => $absAmount,
                        'due_date' => substr($tx['date'], 0, 10),
                        'payment_date' => $tx['status'] === 'POSTED' ? substr($tx['date'], 0, 10) : null,
                        'status' => $tx['status'] === 'POSTED' ? 'pago' : 'pendente',
                        'notes' => 'Importado via Open Finance (Pluggy)',
                    ]
                );
            }
        }
    }
}
