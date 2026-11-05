<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Engine Oficial da Régua de Cobrança WhatsApp (Meta Cloud API).
 */

namespace App\Modules\Billing\Console\Commands;

use App\Models\Invoice;
use App\Modules\MercadoPago\Services\MercadoPagoPaymentService;
use App\Modules\WhatsApp\Services\MetaCloudApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcessBillingScheduleCommand extends Command
{
    protected $signature = 'billing:process-schedule';
    protected $description = 'Executa o ciclo oficial da régua de cobrança automática via WhatsApp Cloud API e Mercado Pago';

    public function handle(MetaCloudApiClient $metaClient, MercadoPagoPaymentService $mpService): int
    {
        $today = now('America/Sao_Paulo')->toDateString();
        $this->info("[Régua de Cobrança] Iniciando ciclo para {$today}...");

        // 1. Atualizar faturas vencidas para status 'overdue'
        DB::table('invoices')
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->update([
                'status' => 'overdue',
                'updated_at' => now(),
            ]);

        // 2. Gerar agendamentos da régua (D-3, D0, D+1, D+3, D+5) para faturas pendentes que ainda não possuem regras
        $this->generateScheduleRulesForPendingInvoices();

        // 3. Processar regras agendadas para hoje ou pendentes de envio
        if (!Schema::hasTable('billing_rules')) {
            $this->warn('Tabela billing_rules ainda não criada. Execute as migrações.');
            return 0;
        }

        $rules = DB::table('billing_rules')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<=', $today)
            ->orderBy('id')
            ->limit(100)
            ->get();

        $processed = 0;
        $sentCount = 0;
        $canceledCount = 0;

        foreach ($rules as $rule) {
            $processed++;
            $invoice = DB::table('invoices')->where('id', $rule->invoice_id)->first();

            // Verificação de Segurança 1: Se a fatura já estiver paga ou cancelada, cancela a regra
            if (!$invoice || in_array($invoice->status, ['paid', 'canceled', 'refunded'], true)) {
                DB::table('billing_rules')->where('id', $rule->id)->update([
                    'status' => 'canceled',
                    'canceled_reason' => 'Fatura não está mais pendente (Status: ' . ($invoice->status ?? 'inexistente') . ')',
                    'updated_at' => now(),
                ]);
                $canceledCount++;
                continue;
            }

            // Garante telefone do cliente
            $phone = $invoice->client_phone ?? $invoice->customer_phone ?? null;
            if (!$phone && isset($invoice->customer_id)) {
                $phone = DB::table('customers')->where('id', $invoice->customer_id)->value('phone');
            }

            if (empty($phone)) {
                DB::table('billing_rules')->where('id', $rule->id)->update([
                    'status' => 'failed',
                    'error_message' => 'Telefone do cliente ausente.',
                    'updated_at' => now(),
                ]);
                continue;
            }

            // Garante token público e Pix
            $token = $invoice->token ?: $invoice->webhook_token;
            if (empty($token)) {
                $token = Str::random(48);
                DB::table('invoices')->where('id', $invoice->id)->update(['token' => $token, 'webhook_token' => $token]);
                $invoice->token = $token;
            }

            $pixCode = $invoice->pix_code;
            if (empty($pixCode)) {
                try {
                    $pixResult = $mpService->createPixCharge((array) $invoice);
                    $pixCode = $pixResult['pix_code'] ?? '';
                } catch (\Throwable $e) {
                    Log::warning("[ProcessBillingSchedule] Falha ao gerar Pix para fatura #{$invoice->id}: " . $e->getMessage());
                }
            }

            $clientName = $invoice->client_name ?: 'Cliente';
            $amount = (float) $invoice->amount;
            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : date('d/m/Y');

            // Disparo oficial via Meta Graph API v21.0
            $response = $metaClient->sendInvoiceBillingTemplateMessage(
                to: $phone,
                clientName: $clientName,
                amount: $amount,
                dueDate: $dueDate,
                pixCode: $pixCode ?: 'Disponível na fatura online',
                invoiceToken: $token
            );

            if (!empty($response['ok'])) {
                DB::table('billing_rules')->where('id', $rule->id)->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'meta_message_id' => $response['message_id'] ?? null,
                    'updated_at' => now(),
                ]);
                $sentCount++;
                $this->info("Mensagem enviada com sucesso para {$phone} (Fatura #{$invoice->id})");
            } else {
                $errMsg = $response['error'] ?? 'Erro desconhecido no envio da Meta.';
                DB::table('billing_rules')->where('id', $rule->id)->update([
                    'status' => 'failed',
                    'error_message' => $errMsg,
                    'updated_at' => now(),
                ]);
                $this->error("Falha no envio para {$phone}: {$errMsg}");
            }
        }

        $this->info("[Régua de Cobrança] Concluído: {$processed} analisadas, {$sentCount} enviadas, {$canceledCount} canceladas.");
        return 0;
    }

    /**
     * Gera os agendamentos D-3, D0, D+1, D+3, D+5 para faturas pendentes.
     */
    protected function generateScheduleRulesForPendingInvoices(): void
    {
        if (!Schema::hasTable('billing_rules') || !Schema::hasTable('invoices')) {
            return;
        }

        $invoices = DB::table('invoices')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('due_date')
            ->get();

        $offsets = [-3, 0, 1, 3, 5];

        foreach ($invoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date, 'America/Sao_Paulo');

            foreach ($offsets as $offset) {
                $scheduledDate = $dueDate->copy()->addDays($offset)->toDateString();

                // Evita duplicar agendamento
                $exists = DB::table('billing_rules')
                    ->where('invoice_id', $invoice->id)
                    ->where('trigger_offset', $offset)
                    ->exists();

                if (!$exists) {
                    DB::table('billing_rules')->insert([
                        'tenant_id' => $invoice->tenant_id ?? null,
                        'invoice_id' => $invoice->id,
                        'trigger_offset' => $offset,
                        'scheduled_date' => $scheduledDate,
                        'status' => 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}

