<?php

namespace App\Modules\InvoiceEditor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Billing\Services\InvoicePaymentService;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceEditorController extends Controller
{
    public function __construct(
        protected ?InvoicePaymentService $invoicePayment = null,
    ) {
        $this->invoicePayment ??= app(InvoicePaymentService::class);
    }

    /** Retorna tenant_id ativo. Null = superadmin (vê tudo). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    protected function invoiceQuery()
    {
        $tenantId = $this->activeTenantId();
        return Invoice::query()->when(
            $tenantId !== null,
            fn ($q) => $q->where('tenant_id', $tenantId)
        );
    }

    protected function customerQuery()
    {
        if (Auth::guard('admin')->check()) {
            return Customer::query()->where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', 0));
        }
        $tenantId = $this->activeTenantId();
        return Customer::query()->when(
            $tenantId !== null,
            fn ($q) => $q->where('tenant_id', $tenantId)
        );
    }

    public function index(Request $request)
    {
        $q = $this->invoiceQuery()->with('customer')->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('status', $s);
        if ($search = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }
        $invoices = $q->paginate(25)->withQueryString();
        return view('invoice-editor::index', compact('invoices'));
    }

    public function create()
    {
        $invoice  = new Invoice(['status' => 'pending', 'due_date' => now()->addDays(7)->toDateString()]);
        $gateways = $this->availableGateways();
        return view('invoice-editor::form', compact('invoice', 'gateways'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateInvoice($request);

        // Associa tenant_id do contexto (nunca do request)
        $tenantId = $this->activeTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        $duplicate = Invoice::findOpenDuplicate(
            $data['tenant_id'] ?? null,
            (int) $data['customer_id'],
            (string) $data['description'],
            (float) $data['amount'],
            (string) $data['due_date']
        );

        if ($duplicate) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ja existe uma cobranca aberta com os mesmos dados para este cliente.',
                'duplicate_invoice_id' => $duplicate->id,
                'redirect' => route('admin.invoice-editor.edit', $duplicate->id),
            ], 422);
        }

        $data['number']        = $this->generateNumber();
        $data['webhook_token'] = Str::random(48);

        try {
            $invoice = Invoice::create($data);
        } catch (\Throwable $e) {
            Log::error('[InvoiceEditor] Falha ao criar fatura', ['error' => $e->getMessage(), 'customer_id' => $data['customer_id'] ?? null]);
            return response()->json(['ok' => false, 'message' => $this->invoiceSaveErrorMessage($e)], 500);
        }

        app(NotificationDispatcher::class)->send([
            'channels' => ['in_app'],
            'group'    => 'billing',
            'type'     => 'invoice_created',
            'title'    => "Fatura {$invoice->number} criada",
            'message'  => "Cliente: {$invoice->customer?->name} | Valor: ".Format::money($invoice->amount),
            'url'      => "/admin/invoice-editor/{$invoice->id}/editar",
        ]);

        return response()->json([
            'ok'       => true,
            'id'       => $invoice->id,
            'number'   => $invoice->number,
            'redirect' => route('admin.invoice-editor.edit', $invoice->id),
        ]);
    }

    public function edit(int $id)
    {
        $invoice  = $this->invoiceQuery()->with('customer', 'dispatches')->findOrFail($id);
        $gateways = $this->availableGateways();
        return view('invoice-editor::form', compact('invoice', 'gateways'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceQuery()->findOrFail($id);
        $data    = $this->validateInvoice($request);

        $oldStatus = $invoice->status;

        try {
            $invoice->update($data);

            if (isset($data['status']) && $data['status'] === 'canceled' && $oldStatus !== 'canceled') {
                if ($request->boolean('cascade_cancel')) {
                    if (isset($invoice->meta['installment']) && isset($invoice->meta['total_installments']) && $invoice->meta['total_installments'] > 1) {
                        $cascadeQuery = Invoice::where('customer_id', $invoice->customer_id)
                            ->where('id', '!=', $invoice->id)
                            ->whereIn('status', ['pending', 'sent', 'overdue']);
                        
                        if (isset($invoice->meta['batch_id'])) {
                            $cascadeQuery->whereJsonContains('meta->batch_id', $invoice->meta['batch_id']);
                        } else {
                            $cascadeQuery->where('created_at', $invoice->created_at);
                        }
                        
                        $cascadeQuery->update(['status' => 'canceled', 'updated_at' => now()]);
                    } else {
                        $installment = \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('invoice_id', $invoice->id)->first();
                        if ($installment && $installment->subscription_id) {
                            $relatedInvoiceIds = \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('subscription_id', $installment->subscription_id)
                                ->where('id', '!=', $installment->id)
                                ->pluck('invoice_id');
                            
                            if ($relatedInvoiceIds->isNotEmpty()) {
                                Invoice::whereIn('id', $relatedInvoiceIds)
                                    ->whereIn('status', ['pending', 'sent', 'overdue'])
                                    ->update(['status' => 'canceled', 'updated_at' => now()]);
                            }
                            
                            $installment->subscription()->update(['status' => 'cancelled']);
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('[InvoiceEditor] Falha ao atualizar fatura', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
            return response()->json(['ok' => false, 'message' => $this->invoiceSaveErrorMessage($e)], 500);
        }

        return response()->json(['ok' => true]);
    }

    public function anticipateLast(int $id): JsonResponse
    {
        $invoice = $this->invoiceQuery()->findOrFail($id);
        
        if (isset($invoice->meta['batch_id'])) {
            $lastInvoice = Invoice::where('customer_id', $invoice->customer_id)
                ->whereJsonContains('meta->batch_id', $invoice->meta['batch_id'])
                ->whereIn('status', ['pending', 'overdue'])
                ->orderBy('due_date', 'desc')
                ->first();
                
            if ($lastInvoice) {
                $lastInvoice->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
                return response()->json(['ok' => true, 'message' => 'Última parcela pendente antecipada com sucesso.']);
            }
        }
        
        return response()->json(['ok' => false, 'error' => 'Nenhuma parcela pendente encontrada para este parcelamento.'], 404);
    }

    public function destroy(int $id): JsonResponse
    {
        $invoice = $this->invoiceQuery()->findOrFail($id);
        $invoice->dispatches()->delete();
        $invoice->delete();
        return response()->json(['ok' => true]);
    }

    public function sendWhatsApp(int $id, BillingDispatcher $dispatcher): JsonResponse
    {
        $invoice = $this->invoiceQuery()->with('customer')->findOrFail($id);

        $localStatus = strtolower((string) $invoice->status);
        $inactiveStatuses = ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'];

        if (in_array($localStatus, $inactiveStatuses, true)) {
            $tipoMsg = match (true) {
                in_array($localStatus, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'Esta fatura está CANCELADA e não pode receber cobranças.',
                in_array($localStatus, ['refunded', 'estornada', 'estornado'], true) => 'Esta fatura está com status ESTORNADO e não pode receber cobranças.',
                default => 'Esta fatura já consta como PAGA e não pode receber cobranças.',
            };
            return response()->json(['ok' => false, 'error' => $tipoMsg], 422);
        }

        $whmcsId = data_get($invoice->meta, 'whmcs_invoice_id');
        $connId  = data_get($invoice->meta, 'whmcs_connection_id');
        if ($whmcsId) {
            try {
                $whmcsConn = $connId
                    ? \App\Modules\WhmcsIntegration\Models\WhmcsConnection::find($connId)
                    : \App\Modules\WhmcsIntegration\Models\WhmcsConnection::where('active', true)->first();

                if ($whmcsConn && $whmcsConn->active) {
                    (new \App\Modules\WhmcsIntegration\Services\WhmcsClient($whmcsConn))->syncSingleInvoice($whmcsId);
                    $invoice->refresh();

                    $refreshedStatus = strtolower((string) $invoice->status);
                    $invMeta = is_string($invoice->meta) ? (json_decode($invoice->meta, true) ?: []) : ((array) ($invoice->meta ?? []));
                    $whmcsStatus = strtolower((string) ($invMeta['whmcs_status'] ?? ''));

                    if (in_array($refreshedStatus, $inactiveStatuses, true) || in_array($whmcsStatus, $inactiveStatuses, true)) {
                        $statusRef = in_array($whmcsStatus, $inactiveStatuses, true) ? $whmcsStatus : $refreshedStatus;
                        $tipoMsg = match (true) {
                            in_array($statusRef, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'Esta fatura consta como CANCELADA no WHMCS e não pode ser cobrada.',
                            in_array($statusRef, ['refunded', 'estornada', 'estornado'], true) => 'Esta fatura consta como ESTORNADA no WHMCS e não pode ser cobrada.',
                            default => 'Esta fatura consta como PAGA no WHMCS e não pode ser cobrada.',
                        };
                        return response()->json(['ok' => false, 'error' => $tipoMsg], 422);
                    }
                }
            } catch (\Throwable) {}
        }

        if (app(HolidayService::class)->isNonBusinessDay(now('America/Sao_Paulo'))) {
            return response()->json(['ok' => false, 'error' => 'Cobranca manual bloqueada em fim de semana ou feriado.'], 422);
        }

        if (! $invoice->customer?->whatsapp) {
            return response()->json(['ok' => false, 'error' => 'Cliente sem numero de WhatsApp.'], 422);
        }

        $dispatch = $invoice->dispatches()->create([
            'schedule_offset' => 'manual',
            'scheduled_at'    => now(),
            'status'          => 'scheduled',
        ]);

        $ok = $dispatcher->dispatchSpecific($dispatch->id);
        return response()->json(['ok' => $ok, 'dispatch_id' => $dispatch->id]);
    }

    public function generatePaymentLink(int $id): JsonResponse
    {
        $invoice = $this->invoiceQuery()->with('customer')->findOrFail($id);

        try {
            $result = $this->invoicePayment->hydrateGatewayPayment($invoice);
            return response()->json([
                'ok'         => true,
                'payment_url'=> $result['payment_url'] ?? $invoice->fresh()->payment_url,
                'charge_id'  => $result['charge_id'] ?? $invoice->fresh()->gateway_charge_id,
                'reused'     => (bool) ($result['reused'] ?? false),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q = $request->query('q', '');
        $customers = $this->customerQuery()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('document', 'like', "%{$q}%")
                ->orWhere('whatsapp', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name', 'email', 'whatsapp', 'document']);

        return response()->json($customers);
    }

    public function duplicate(int $id): JsonResponse
    {
        $original = $this->invoiceQuery()->findOrFail($id);
        $copy = $original->replicate(['number', 'webhook_token', 'gateway_charge_id', 'payment_url', 'paid_at']);
        $copy->number        = $this->generateNumber();
        $copy->status        = 'pending';
        $copy->webhook_token = Str::random(48);
        $copy->due_date      = now()->addDays(7);
        $copy->save();

        return response()->json(['ok' => true, 'id' => $copy->id, 'number' => $copy->number]);
    }

    protected function validateInvoice(Request $request): array
    {
        $request->merge(['amount' => Format::decimal($request->input('amount'))]);
        $tenantId = $this->activeTenantId();

        $customerRule = $tenantId !== null
            ? Rule::exists('customers', 'id')->where('tenant_id', $tenantId)->where('active', true)
            : 'exists:customers,id';

        $data = $request->validate([
            'customer_id' => ['required', $customerRule],
            'description' => 'required|string|max:20000',
            'amount'      => 'required|numeric|min:0.01',
            'due_date'    => 'required|date',
            'gateway'     => 'nullable|in:mercadopago,efipro,sumup',
            'status'      => 'nullable|in:pending,sent,paid,overdue,canceled',
            'meta'        => 'nullable|array',
        ]);

        $this->assertBusinessDueDate($data['due_date']);
        return $data;
    }

    protected function assertBusinessDueDate(string $dueDate): void
    {
        $date     = Carbon::parse($dueDate, 'America/Sao_Paulo')->startOfDay();
        $holidays = app(HolidayService::class);

        if (! $holidays->isNonBusinessDay($date)) return;

        throw ValidationException::withMessages([
            'due_date' => 'Vencimentos nao podem cair em fim de semana ou feriado. Proximo dia util: '.$holidays->nextBusinessDay($date)->format('d/m/Y').'.',
        ]);
    }

    protected function invoiceSaveErrorMessage(\Throwable $e): string
    {
        if (str_contains($e->getMessage(), 'Data too long for column') && str_contains($e->getMessage(), 'description')) {
            return 'A descricao da cobranca ficou maior que o banco atual permite. Execute as migrations e tente novamente.';
        }
        return 'Nao foi possivel salvar a fatura agora. Verifique os dados e tente novamente.';
    }

    protected function generateNumber(): string
    {
        $last = Invoice::max('id') ?? 0;
        return 'INV-'.str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }

    protected function availableGateways(): array
    {
        return ['mercadopago' => 'Mercado Pago', 'efipro' => 'Efi Pro (Gerencianet)', 'sumup' => 'SumUp'];
    }
}
