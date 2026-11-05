<?php

namespace App\Modules\WhmcsIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;
use App\Modules\WhmcsIntegration\Services\WhmcsServiceAutomation;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhmcsController extends Controller
{
    private const ALLOWED_STATUSES = ['Unpaid', 'Paid', 'Cancelled', 'Refunded', 'Collections'];
    private const ALLOWED_SERVICE_STATUSES = ['Active', 'Suspended', 'Pending', 'Terminated', 'Cancelled'];

    protected function currentTenantId(): ?int
    {
        if (request()->is('painel') || request()->is('painel/*')) {
            $user = auth('web')->user();
            if (! $user) {
                return null;
            }
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            return $tenant?->id;
        }

        if (auth('admin')->check()) {
            return null;
        }

        $user = auth('web')->user();
        if (! $user) {
            return null;
        }

        $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
        return $tenant?->id;
    }

    public function index()
    {
        $tenantId = $this->currentTenantId();

        $connections = WhmcsConnection::with('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('id')
            ->get();

        $tenants = $tenantId === null
            ? Tenant::orderBy('company_name')->get(['id', 'company_name', 'slug'])
            : collect();

        $recentLogs = Schema::hasTable('whmcs_sync_log')
            ? DB::table('whmcs_sync_log')
                ->leftJoin('whmcs_connections', 'whmcs_connections.id', '=', 'whmcs_sync_log.connection_id')
                ->select('whmcs_sync_log.*', 'whmcs_connections.name as connection_name')
                ->when($tenantId !== null, fn ($q) => $q->where('whmcs_connections.tenant_id', $tenantId))
                ->latest('whmcs_sync_log.created_at')
                ->limit(12)
                ->get()
            : collect();

        $recentWebhookEvents = Schema::hasTable('whmcs_webhook_events')
            ? DB::table('whmcs_webhook_events')
                ->leftJoin('whmcs_connections', 'whmcs_connections.id', '=', 'whmcs_webhook_events.connection_id')
                ->select('whmcs_webhook_events.*', 'whmcs_connections.name as connection_name')
                ->when($tenantId !== null, fn ($q) => $q->where('whmcs_connections.tenant_id', $tenantId))
                ->latest('whmcs_webhook_events.created_at')
                ->limit(12)
                ->get()
            : collect();

        $serviceStats = Schema::hasTable('whmcs_services')
            ? [
                'total' => WhmcsService::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))->count(),
                'active' => WhmcsService::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))->where('status', 'Active')->count(),
                'suspended' => WhmcsService::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))->where('status', 'Suspended')->count(),
                'overdue' => WhmcsService::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where('status', 'Active')
                    ->whereNotNull('next_due_date')
                    ->whereDate('next_due_date', '<', now('America/Sao_Paulo')->toDateString())
                    ->count(),
            ]
            : ['total' => 0, 'active' => 0, 'suspended' => 0, 'overdue' => 0];

        return view('whmcs::index', compact('connections', 'tenants', 'recentLogs', 'recentWebhookEvents', 'serviceStats'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedConnectionData($request, true);
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        $candidate = new WhmcsConnection($data + [
            'active' => true,
            'webhook_token' => Str::random(64),
            'webhook_secret' => Str::random(64),
        ]);

        $test = (new WhmcsClient($candidate))->validateCredentials();
        if (! $test['ok']) {
            return response()->json([
                'ok' => false,
                'error' => 'Falha ao conectar com WHMCS. '.$test['message'],
            ], 422);
        }

        $connection = WhmcsConnection::create($data + [
            'active' => true,
            'last_test_at' => now(),
            'last_test_status' => 'ok',
            'last_error' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Conexao WHMCS criada e validada com sucesso.',
            'connection' => $connection->fresh('tenant'),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        $connection = WhmcsConnection::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        $data = $this->validatedConnectionData($request, false);
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        $connection->fill($data);
        $connection->save();

        return response()->json([
            'ok' => true,
            'message' => 'Conexao WHMCS atualizada com sucesso.',
            'connection' => $connection->fresh('tenant'),
        ]);
    }

    public function cleanOrphans(): JsonResponse
    {
        try {
            // Get all valid tenant_ids that currently have a connection
            $validTenantIds = WhmcsConnection::whereNotNull('tenant_id')->pluck('tenant_id')->toArray();
            $validConnectionIds = WhmcsConnection::pluck('id')->toArray();

            // Delete orphaned services
            if (!empty($validConnectionIds)) {
                WhmcsService::whereNotIn('connection_id', $validConnectionIds)->delete();
            } else {
                WhmcsService::query()->delete();
            }

            // Delete orphaned Invoices and their messages in chunks to prevent server overload
            $invoicesQuery = Invoice::whereNotNull('meta->whmcs_invoice_id');
            if (!empty($validTenantIds)) {
                $invoicesQuery->whereNotIn('tenant_id', $validTenantIds);
            }

            $invoicesQuery->select('id')->chunk(500, function ($invoices) {
                $invoiceIds = $invoices->pluck('id')->toArray();
                if (empty($invoiceIds)) return;

                $dispatchIds = \Illuminate\Support\Facades\DB::table('billing_dispatches')->whereIn('invoice_id', $invoiceIds)->pluck('id')->toArray();
                
                if (!empty($dispatchIds)) {
                    \Illuminate\Support\Facades\DB::table('whatsapp_messages')->whereIn('dispatch_id', $dispatchIds)->delete();
                    if (\Illuminate\Support\Facades\Schema::hasTable('email_messages')) {
                        \Illuminate\Support\Facades\DB::table('email_messages')->whereIn('dispatch_id', $dispatchIds)->delete();
                    }
                }
                
                Invoice::whereIn('id', $invoiceIds)->delete();
            });

            // Delete orphaned Customers in chunks
            $customersQuery = \App\Models\Customer::whereNotNull('meta->whmcs_id');
            if (!empty($validTenantIds)) {
                $customersQuery->whereNotIn('tenant_id', $validTenantIds);
            }
            $customersQuery->select('id')->chunk(500, function ($customers) {
                $customerIds = $customers->pluck('id')->toArray();
                if (!empty($customerIds)) {
                    \App\Models\Customer::whereIn('id', $customerIds)->delete();
                }
            });

            return response()->json(['ok' => true, 'message' => 'Todos os dados órfãos do WHMCS foram removidos com sucesso.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Erro ao limpar dados órfãos: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        $connection = WhmcsConnection::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        try {
            // Delete services (they belong to this tenant or connection)
            WhmcsService::where('tenant_id', $connection->tenant_id ?? $tenantId)->delete();

            // Delete Invoices and their messages in chunks to prevent server overload
            $invoicesQuery = $connection->tenant_id 
                ? Invoice::where('tenant_id', $connection->tenant_id)->whereNotNull('meta->whmcs_invoice_id')
                : Invoice::where('meta->whmcs_connection_id', $connection->id);

            $invoicesQuery->select('id')->chunk(500, function ($invoices) {
                $invoiceIds = $invoices->pluck('id')->toArray();
                if (empty($invoiceIds)) return;

                $dispatchIds = \Illuminate\Support\Facades\DB::table('billing_dispatches')->whereIn('invoice_id', $invoiceIds)->pluck('id')->toArray();
                
                if (!empty($dispatchIds)) {
                    \Illuminate\Support\Facades\DB::table('whatsapp_messages')->whereIn('dispatch_id', $dispatchIds)->delete();
                    if (\Illuminate\Support\Facades\Schema::hasTable('email_messages')) {
                        \Illuminate\Support\Facades\DB::table('email_messages')->whereIn('dispatch_id', $dispatchIds)->delete();
                    }
                }
                
                Invoice::whereIn('id', $invoiceIds)->delete();
            });

            // Delete Customers in chunks
            if ($connection->tenant_id) {
                \App\Models\Customer::where('tenant_id', $connection->tenant_id)->whereNotNull('meta->whmcs_id')->select('id')->chunk(500, function ($customers) {
                    $customerIds = $customers->pluck('id')->toArray();
                    if (!empty($customerIds)) {
                        \App\Models\Customer::whereIn('id', $customerIds)->delete();
                    }
                });
            }

            $connection->delete();

            return response()->json(['ok' => true, 'message' => 'Conexão e todos os dados importados foram excluídos com sucesso.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Erro ao excluir dados: ' . $e->getMessage()], 500);
        }
    }

    public function test(int $id): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        $connection = WhmcsConnection::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        $result = (new WhmcsClient($connection))->validateCredentials();

        return response()->json($result + ['ok' => $result['ok']], $result['ok'] ? 200 : 422);
    }

    public function logs(int $id): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        $connection = WhmcsConnection::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        $syncLogs = Schema::hasTable('whmcs_sync_log')
            ? DB::table('whmcs_sync_log')
                ->where('connection_id', $connection->id)
                ->latest('created_at')
                ->limit(30)
                ->get()
            : collect();

        $webhookEvents = Schema::hasTable('whmcs_webhook_events')
            ? DB::table('whmcs_webhook_events')
                ->where('connection_id', $connection->id)
                ->latest('created_at')
                ->limit(30)
                ->get()
            : collect();

        return response()->json([
            'ok' => true,
            'sync_logs' => $syncLogs,
            'webhook_events' => $webhookEvents,
        ]);
    }

    public function syncNow(int $id, BillingDispatcher $dispatcher): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        $connection = WhmcsConnection::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        try {
            // Libera a trava de sessão do PHP imediatamente para não bloquear outras abas ou requisições no sistema
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            ignore_user_abort(true);
            set_time_limit(300);

            // Executa a sincronização direta de forma ultra-rápida e resiliente
            $client = new WhmcsClient($connection);
            $stats = $client->syncAll();

            // Cancela imediatamente disparos pendentes de faturas que ficaram pagas ou canceladas
            $canceledDispatches = $dispatcher->cancelPaidWhmcsInvoiceDispatches();

            $scheduledDispatches = $connection->auto_send_invoices
                ? $dispatcher->scheduleNewInvoices()
                : 0;

            $automationStats = app(WhmcsServiceAutomation::class)->run($connection);

            $finalStats = $stats + [
                'canceled_paid_dispatches' => $canceledDispatches,
                'billing_dispatches_scheduled' => $scheduledDispatches,
                'service_automation' => $automationStats,
            ];

            // Grava estatísticas no cache
            \Illuminate\Support\Facades\Cache::put("whmcs_sync_stats_{$connection->id}", $finalStats, now()->addMinutes(10));
            \Illuminate\Support\Facades\Cache::forget("whmcs_sync_error_{$connection->id}");

            // Limpa eventuais jobs antigos na fila para essa conexão
            try {
                \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('payload', 'like', '%SyncWhmcsConnectionJob%')
                    ->delete();
            } catch (\Throwable) {}

            return response()->json([
                'ok' => true,
                'status' => 'completed',
                'message' => 'Sincronização concluída com sucesso.',
                'stats' => $finalStats,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WHMCS] Sincronizacao manual falhou', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            \Illuminate\Support\Facades\Cache::put("whmcs_sync_error_{$connection->id}", $e->getMessage(), now()->addMinutes(10));

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function syncStatus(int $id): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        $connection = WhmcsConnection::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        if (\Illuminate\Support\Facades\Cache::has("whmcs_sync_error_{$connection->id}")) {
            $error = \Illuminate\Support\Facades\Cache::pull("whmcs_sync_error_{$connection->id}");
            return response()->json(['status' => 'error', 'error' => $error]);
        }

        if (\Illuminate\Support\Facades\Cache::has("whmcs_sync_stats_{$connection->id}")) {
            $stats = \Illuminate\Support\Facades\Cache::pull("whmcs_sync_stats_{$connection->id}");
            return response()->json(['status' => 'completed', 'stats' => $stats]);
        }

        if ($connection->last_sync_at && $connection->last_sync_at->gt(now()->subSeconds(30))) {
            return response()->json([
                'status' => $connection->last_sync_status === 'error' ? 'error' : 'completed',
                'stats' => $connection->last_sync_stats ?: [],
                'error' => $connection->last_sync_error,
            ]);
        }

        return response()->json(['status' => 'processing']);
    }

    public function webhookStatus(string $token): JsonResponse
    {
        $connection = WhmcsConnection::where('webhook_token', $token)
            ->where('active', true)
            ->first();

        if (! $connection) {
            abort(404);
        }

        return response()->json([
            'ok' => true,
            'status' => 'active',
            'message' => 'Webhook WHMCS ativo. Configure o WHMCS para enviar eventos por POST para esta URL.',
            'method' => 'POST',
            'signature_required' => $this->webhookSignatureRequired($connection),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function webhook(Request $request, string $token): JsonResponse
    {
        $connection = WhmcsConnection::where('webhook_token', $token)
            ->where('active', true)
            ->first();

        if (! $connection) {
            abort(404);
        }

        $payload = $this->webhookPayload($request);
        $rawBody = $request->getContent();
        $signatureValid = $this->signatureIsValid($connection, $request, $rawBody);
        $eventName = $this->webhookValue($payload, ['event', 'hook', 'action', 'type']) ?: 'unknown';
        $invoiceId = $this->webhookValue($payload, ['invoiceid', 'invoice_id', 'id', 'data.invoiceid', 'invoice.id']);
        $status = $this->webhookValue($payload, ['status', 'invoice_status', 'data.status', 'invoice.status']);

        if ($this->webhookSignatureRequired($connection) && ! $signatureValid) {
            $this->recordWebhookEvent($connection, $eventName, $invoiceId, $status, false, $payload, $request, null, 'Assinatura ausente ou invalida.');

            return response()->json([
                'ok' => false,
                'error' => 'invalid_signature',
            ], 401);
        }

        $fingerprint = $rawBody !== '' ? $rawBody : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $replayKey = hash('sha256', $connection->id.'|'.$fingerprint);
        $eventId = $this->recordWebhookEvent($connection, $eventName, $invoiceId, $status, $signatureValid, $payload, $request, $replayKey);

        if ($eventId === 'duplicate') {
            return response()->json([
                'ok' => true,
                'status' => 'duplicate_ignored',
            ]);
        }

        try {
            $result = $this->processWebhookEvent($connection, $eventName, $invoiceId, $status, $payload);

            if (is_int($eventId)) {
                DB::table('whmcs_webhook_events')->where('id', $eventId)->update([
                    'status' => $result['status'] ?? $status,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $connection->forceFill(['last_webhook_at' => now(), 'last_error' => null])->save();

            return response()->json(['ok' => true] + $result);
        } catch (\Throwable $e) {
            if (is_int($eventId)) {
                DB::table('whmcs_webhook_events')->where('id', $eventId)->update([
                    'error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
            }

            $connection->forceFill(['last_error' => 'Webhook: '.$e->getMessage()])->save();

            Log::warning('[WHMCS] Webhook falhou', [
                'connection_id' => $connection->id,
                'event' => $eventName,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function validatedConnectionData(Request $request, bool $creating): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'api_url' => ['required', 'url', 'max:255'],
            'identifier' => ['required', 'string', 'max:255'],
            'secret' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'active' => ['nullable', 'boolean'],
            'sync_customers' => ['nullable', 'boolean'],
            'sync_invoices' => ['nullable', 'boolean'],
            'sync_services' => ['nullable', 'boolean'],
            'auto_send_invoices' => ['nullable', 'boolean'],
            'auto_suspend_overdue_services' => ['nullable', 'boolean'],
            'auto_unsuspend_paid_services' => ['nullable', 'boolean'],
            'suspend_after_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sync_paid_invoices' => ['nullable', 'boolean'],
            'sync_cancelled_invoices' => ['nullable', 'boolean'],
            'webhook_require_signature' => ['nullable', 'boolean'],
            'default_gateway' => ['nullable', 'string', 'max:40'],
            'settings' => ['nullable', 'array'],
            'settings.generate_local_gateway_charge' => ['nullable', 'boolean'],
            'settings.respect_payment_method' => ['nullable', 'boolean'],
            'settings.attach_boleto_pdf' => ['nullable', 'boolean'],
            'settings.late_fee_grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'settings.late_fee_fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'settings.late_fee_fixed_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settings.late_fee_daily_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'settings.late_fee_daily_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sync_statuses' => ['nullable', 'array'],
            'sync_statuses.*' => ['string', 'in:'.implode(',', self::ALLOWED_STATUSES)],
            'sync_service_statuses' => ['nullable', 'array'],
            'sync_service_statuses.*' => ['string', 'in:'.implode(',', self::ALLOWED_SERVICE_STATUSES)],
        ]);

        $statuses = collect($request->input('sync_statuses', ['Unpaid']))
            ->filter(fn ($status) => in_array($status, self::ALLOWED_STATUSES, true))
            ->values()
            ->all();

        if ($statuses === []) {
            $statuses = ['Unpaid'];
        }

        $serviceStatuses = collect($request->input('sync_service_statuses', ['Active', 'Suspended', 'Pending']))
            ->filter(fn ($status) => in_array($status, self::ALLOWED_SERVICE_STATUSES, true))
            ->values()
            ->all();

        if ($serviceStatuses === []) {
            $serviceStatuses = ['Active', 'Suspended', 'Pending'];
        }

        $data = [
            'name' => trim((string) $request->input('name')),
            'api_url' => rtrim(trim((string) $request->input('api_url')), '/'),
            'identifier' => trim((string) $request->input('identifier')),
            'tenant_id' => $request->filled('tenant_id') ? (int) $request->input('tenant_id') : null,
            'active' => $request->boolean('active', true),
            'sync_customers' => $request->boolean('sync_customers', true),
            'sync_invoices' => $request->boolean('sync_invoices', true),
            'sync_services' => $request->boolean('sync_services', true),
            'auto_send_invoices' => $request->boolean('auto_send_invoices', true),
            'auto_suspend_overdue_services' => $request->boolean('auto_suspend_overdue_services', false),
            'auto_unsuspend_paid_services' => $request->boolean('auto_unsuspend_paid_services', true),
            'suspend_after_days' => max(0, (int) $request->input('suspend_after_days', 0)),
            'sync_paid_invoices' => $request->boolean('sync_paid_invoices', true),
            'sync_cancelled_invoices' => $request->boolean('sync_cancelled_invoices', true),
            'webhook_require_signature' => (bool) config('whmcs.webhook_signature_required', true)
                ? true
                : $request->boolean('webhook_require_signature', true),
            'default_gateway' => $request->input('default_gateway') ?: 'mercadopago',
            'settings' => [
                'generate_local_gateway_charge' => $request->boolean('settings.generate_local_gateway_charge', true),
                'respect_payment_method' => $request->boolean('settings.respect_payment_method', true),
                'attach_boleto_pdf' => $request->boolean('settings.attach_boleto_pdf', true),
                'late_fee_grace_days' => max(0, (int) $request->input('settings.late_fee_grace_days', 0)),
                'late_fee_fixed_amount' => round((float) $request->input('settings.late_fee_fixed_amount', 0), 2),
                'late_fee_fixed_percent' => round((float) $request->input('settings.late_fee_fixed_percent', 0), 4),
                'late_fee_daily_amount' => round((float) $request->input('settings.late_fee_daily_amount', 0), 2),
                'late_fee_daily_percent' => round((float) $request->input('settings.late_fee_daily_percent', 0), 4),
            ],
            'sync_statuses' => $statuses,
            'sync_service_statuses' => $serviceStatuses,
        ];

        if ($creating || $request->filled('secret')) {
            $data['secret'] = (string) $request->input('secret');
        }

        return $data;
    }

    protected function processWebhookEvent(WhmcsConnection $connection, string $event, mixed $invoiceId, mixed $status, array $payload): array
    {
        $invoiceId = trim((string) $invoiceId);
        $localStatus = $this->mapWebhookStatus($event, (string) $status);

        if ($invoiceId === '') {
            return [
                'status' => 'ignored',
                'message' => 'Webhook sem invoiceid.',
            ];
        }

        $invoice = $this->findLocalInvoice($connection, $invoiceId);

        if (! $invoice || ! in_array($localStatus, ['paid', 'canceled', 'refunded'], true)) {
            try {
                (new WhmcsClient($connection))->syncSingleInvoice($invoiceId);
                $invoice = $this->findLocalInvoice($connection, $invoiceId);
            } catch (\Throwable $e) {
                if (! $invoice) {
                    throw $e;
                }
            }
        }

        if (! $invoice) {
            return [
                'status' => 'synced_without_local_match',
                'invoice_id' => $invoiceId,
            ];
        }

        if ($localStatus) {
            $meta = array_merge($invoice->meta ?: [], [
                'whmcs_invoice_id' => $invoiceId,
                'whmcs_connection_id' => $connection->id,
                'whmcs_last_webhook_event' => $event,
                'whmcs_last_webhook_at' => now()->toISOString(),
                'whmcs_paid_origin' => $localStatus === 'paid' ? 'webhook' : null,
                'whmcs_status' => $status ?: $event,
            ]);

            $payload = [
                'status' => $localStatus,
                'meta' => Arr::where($meta, fn ($value) => $value !== null),
            ];

            if ($localStatus === 'paid' && ! $invoice->paid_at) {
                $payload['paid_at'] = now();
            }

            $invoice->update($payload);

            // Cancela imediatamente quaisquer disparos pendentes se a fatura foi liquidada ou cancelada
            if (in_array($localStatus, ['paid', 'canceled', 'cancelled', 'refunded'], true)) {
                \Illuminate\Support\Facades\DB::table('billing_dispatches')
                    ->where('invoice_id', $invoice->id)
                    ->whereIn('status', ['scheduled', 'failed', 'sending'])
                    ->update([
                        'status'     => 'canceled',
                        'last_error' => "Cancelado via webhook WHMCS ({$event}).",
                        'updated_at' => now(),
                    ]);
            }
        }

        $scheduledDispatches = $connection->auto_send_invoices
            ? app(BillingDispatcher::class)->scheduleNewInvoices()
            : 0;
        $serviceAutomation = $localStatus === 'paid'
            ? app(WhmcsServiceAutomation::class)->unsuspendPaidServicesForInvoice($invoice->fresh())
            : ['suspended' => 0, 'unsuspended' => 0, 'skipped' => 0, 'errors' => 0];

        return [
            'status' => $localStatus ?: 'synced',
            'invoice_id' => $invoiceId,
            'local_invoice_id' => $invoice->id,
            'billing_dispatches_scheduled' => $scheduledDispatches,
            'service_automation' => $serviceAutomation,
        ];
    }

    protected function findLocalInvoice(WhmcsConnection $connection, string $invoiceId): ?Invoice
    {
        $numbers = ['WHMCS-'.$invoiceId];
        if ($connection->id) {
            $numbers[] = 'WHMCS-'.$connection->id.'-'.$invoiceId;
        }

        return Invoice::query()
            ->where(function ($query) use ($numbers, $invoiceId) {
                $query->whereIn('number', $numbers)
                    ->orWhere('meta->whmcs_invoice_id', $invoiceId)
                    ->orWhere('meta->whmcs_invoice_id', (int) $invoiceId);
            })
            ->when($connection->tenant_id, fn ($query) => $query->where('tenant_id', $connection->tenant_id))
            ->first();
    }

    protected function mapWebhookStatus(string $event, string $status): ?string
    {
        $value = Str::lower(trim($status.' '.$event));

        $eventKey = Str::lower(preg_replace('/[^a-z0-9]/i', '', $event));

        if (str_contains($value, 'paid') || in_array($eventKey, ['invoicepaid', 'addinvoicepayment'], true)) {
            return 'paid';
        }

        if (str_contains($value, 'refund')) {
            return 'refunded';
        }

        if (str_contains($value, 'cancel') || str_contains($value, 'void')) {
            return 'canceled';
        }

        return null;
    }

    protected function webhookPayload(Request $request): array
    {
        $payload = $request->all();
        if ($payload !== []) {
            return $payload;
        }

        $raw = trim($request->getContent());
        if ($raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        parse_str($raw, $parsed);

        return is_array($parsed) ? $parsed : [];
    }

    protected function webhookValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function signatureIsValid(WhmcsConnection $connection, Request $request, string $rawBody): bool
    {
        $signature = $request->header('X-Whmcs-Signature')
            ?: $request->header('X-WHMCS-Signature')
            ?: $request->header('X-Signature')
            ?: $request->header('X-Hub-Signature-256');

        if (! $signature || ! $connection->webhook_secret) {
            return false;
        }

        $signature = Str::of($signature)->replace('sha256=', '')->trim()->toString();
        $expected = hash_hmac('sha256', $rawBody, $connection->webhook_secret);

        return hash_equals($expected, $signature);
    }

    protected function webhookSignatureRequired(WhmcsConnection $connection): bool
    {
        return (bool) config('whmcs.webhook_signature_required', true)
            || (bool) $connection->webhook_require_signature;
    }

    protected function recordWebhookEvent(
        WhmcsConnection $connection,
        string $event,
        mixed $invoiceId,
        mixed $status,
        ?bool $signatureValid,
        array $payload,
        Request $request,
        ?string $replayKey,
        ?string $error = null
    ): int|string|null {
        if (! Schema::hasTable('whmcs_webhook_events')) {
            return null;
        }

        try {
            return DB::table('whmcs_webhook_events')->insertGetId([
                'connection_id' => $connection->id,
                'event' => $event,
                'invoice_id' => $invoiceId !== null ? (string) $invoiceId : null,
                'status' => $status !== null ? (string) $status : null,
                'replay_key' => $replayKey,
                'signature_valid' => $signatureValid,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'error' => $error,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $message = Str::lower($e->getMessage());
            if ($replayKey && (str_contains($message, 'duplicate') || str_contains($message, 'unique constraint'))) {
                return 'duplicate';
            }

            throw $e;
        }
    }
}
