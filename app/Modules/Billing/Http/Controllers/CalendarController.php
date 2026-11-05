<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingDispatch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FullCalendar 6 - eventos sao faturas (vencimentos) + dispatches (envios agendados).
 * Isolamento por tenant: queries filtradas por tenant_id quando em contexto de tenant.
 * Superadmin vê todos os dados.
 */
class CalendarController extends Controller
{
    public function __construct(protected HolidayService $holidays) {}

    /** Retorna o tenant_id da sessão ativa (null = superadmin, vê tudo). */
    protected function activeTenantId(): ?int
    {
        // Modo assistido: admin opera como tenant via guard web
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            return Tenant::where('user_id', $user?->id)->value('id');
        }
        // Superadmin pode filtrar por tenant_id via query param
        if (Auth::guard('admin')->check() && request()->filled('tenant_id')) {
            return (int) request()->query('tenant_id');
        }
        return null; // superadmin sem filtro = vê tudo
    }

    public function index()
    {
        return view('billing::calendar.index');
    }

    public function events(Request $request): JsonResponse
    {
        $start    = $request->query('start');
        $end      = $request->query('end');
        $tenantId = $this->activeTenantId();

        $invoices = Invoice::with('customer')
            ->whereBetween('due_date', [$start, $end])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->map(fn ($i) => [
                'id'       => 'inv-'.$i->id,
                'title'    => "Fatura {$i->number} - {$i->customer?->name}",
                'start'    => $i->due_date->toDateString(),
                'allDay'   => true,
                'color'    => $this->statusColor($i->status),
                'editable' => in_array($i->status, ['pending', 'sent']),
                'classNames' => ['billing-event', 'billing-invoice-event'],
                'extendedProps' => [
                    'kind'             => 'invoice',
                    'invoice_id'       => $i->id,
                    'number'           => $i->number,
                    'customer_id'      => $i->customer_id,
                    'customer_name'    => $i->customer?->name,
                    'description'      => $i->description,
                    'amount'           => (float) $i->amount,
                    'amount_formatted' => Format::money($i->amount),
                    'due_date'         => $i->due_date->toDateString(),
                    'status'           => $i->status,
                    'gateway'          => $i->gateway,
                    'show_url'         => route('admin.billing.invoices.show', $i->id),
                    'edit_url'         => route('admin.invoice-editor.edit', $i->id),
                ],
            ]);

        $dispatches = BillingDispatch::with('invoice.customer')
            ->whereBetween('scheduled_at', [$start, $end])
            ->when($tenantId !== null, fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where('tenant_id', $tenantId)))
            ->get()
            ->map(fn ($d) => [
                'id'       => 'dsp-'.$d->id,
                'title'    => "Envio: {$d->invoice?->customer?->name} ({$d->schedule_offset})",
                'start'    => $d->scheduled_at->toIso8601String(),
                'color'    => '#0dcaf0',
                'editable' => $d->status === 'scheduled',
                'classNames' => ['billing-event', 'billing-dispatch-event'],
                'extendedProps' => [
                    'kind'             => 'dispatch',
                    'dispatch_id'      => $d->id,
                    'invoice_id'       => $d->invoice_id,
                    'invoice_number'   => $d->invoice?->number,
                    'customer_name'    => $d->invoice?->customer?->name,
                    'scheduled_at'     => $d->scheduled_at?->toIso8601String(),
                    'schedule_offset'  => $d->schedule_offset,
                    'status'           => $d->status,
                ],
            ]);

        $holidays = collect($this->holidayEventsForRange($start, $end));

        return response()->json($holidays->concat($invoices)->concat($dispatches)->values());
    }

    public function moveEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'    => 'required|string',
            'start' => 'required|date',
            'end'   => 'nullable|date',
        ]);

        [$type, $id] = explode('-', $data['id'], 2);
        $tenantId    = $this->activeTenantId();

        if ($type === 'inv') {
            $invoice = $tenantId !== null
                ? Invoice::where('tenant_id', $tenantId)->findOrFail((int) $id)
                : Invoice::findOrFail((int) $id);

            if (! in_array($invoice->status, ['pending', 'sent'])) {
                return response()->json(['ok' => false, 'message' => 'Fatura nao pode ser movida neste status.'], 422);
            }
            $dueDate = $this->billingDateOrFail($data['start']);
            $invoice->update(['due_date' => $dueDate->toDateString()]);

        } elseif ($type === 'dsp') {
            $dispatch = BillingDispatch::findOrFail((int) $id);

            // Verificar isolamento de tenant via invoice
            if ($tenantId !== null && (int) $dispatch->invoice?->tenant_id !== $tenantId) {
                return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
            }

            if ($dispatch->status !== 'scheduled') {
                return response()->json(['ok' => false, 'message' => 'Envio ja processado.'], 422);
            }
            $scheduledAt = Carbon::parse($data['start'], 'America/Sao_Paulo');
            $this->businessDateOrFail($scheduledAt, 'Envios de cobranca nao podem ser agendados em fim de semana ou feriado.');
            $dispatch->update(['scheduled_at' => $scheduledAt]);

        } else {
            return response()->json(['ok' => false, 'message' => 'Tipo invalido.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $request->merge(['amount' => Format::decimal($request->input('amount'))]);

        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'description' => 'required|string|max:20000',
            'amount'      => 'required|numeric|min:0.01',
            'due_date'    => 'required|date',
            'gateway'     => 'nullable|in:mercadopago,efipro,sumup',
        ]);

        $tenantId = $this->activeTenantId();

        // Verificar que o cliente pertence ao tenant ativo
        $customer = $tenantId !== null
            ? Customer::where('tenant_id', $tenantId)->findOrFail((int) $data['customer_id'])
            : Customer::findOrFail((int) $data['customer_id']);

        $data['tenant_id'] = $tenantId ?? $customer->tenant_id;
        $data['due_date']  = $this->billingDateOrFail($data['due_date'])->toDateString();

        $duplicate = Invoice::findOpenDuplicate(
            $data['tenant_id'],
            (int) $data['customer_id'],
            (string) $data['description'],
            (float) $data['amount'],
            (string) $data['due_date']
        );

        if ($duplicate) {
            return response()->json([
                'ok'         => false,
                'message'    => 'Ja existe uma cobranca aberta com os mesmos dados para este cliente.',
                'invoice_id' => $duplicate->id,
            ], 422);
        }

        $data['number']        = $this->generateNumber();
        $data['status']        = 'pending';
        $data['webhook_token'] = Str::random(48);

        $invoice = Invoice::create($data);

        // Agenda a régua de cobrança automática para a nova fatura
        try {
            app(\App\Modules\Billing\Services\BillingDispatcher::class)->scheduleNewInvoices();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[CalendarController] Falha ao agendar régua automática: " . $e->getMessage());
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Cobranca criada com sucesso.',
            'invoice' => $invoice->load('customer'),
        ], 201);
    }

    public function updateInvoice(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();

        $invoice = $tenantId !== null
            ? Invoice::where('tenant_id', $tenantId)->findOrFail($id)
            : Invoice::findOrFail($id);

        if (! in_array($invoice->status, ['pending', 'sent', 'overdue', 'canceled'], true)) {
            return response()->json(['ok' => false, 'message' => 'Esta cobranca nao pode ser editada neste status.'], 422);
        }

        $request->merge(['amount' => Format::decimal($request->input('amount'))]);

        $data = $request->validate([
            'description' => 'required|string|max:20000',
            'amount'      => 'required|numeric|min:0.01',
            'due_date'    => 'required|date',
            'status'      => 'required|in:pending,sent,overdue,canceled',
            'gateway'     => 'nullable|in:mercadopago,efipro,sumup',
        ]);

        $data['due_date'] = $this->billingDateOrFail($data['due_date'])->toDateString();
        $invoice->update($data);

        return response()->json([
            'ok'      => true,
            'message' => 'Cobranca atualizada.',
            'invoice' => $invoice->fresh('customer'),
        ]);
    }

    public function checkInstallments(int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        
        $invoice = $tenantId !== null
            ? Invoice::where('tenant_id', $tenantId)->findOrFail($id)
            : Invoice::findOrFail($id);

        $count = Invoice::where('customer_id', $invoice->customer_id)
            ->where('amount', $invoice->amount)
            ->where('id', '!=', $invoice->id)
            ->whereIn('status', ['pending', 'sent', 'overdue'])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        return response()->json([
            'ok' => true,
            'has_installments' => $count > 0,
            'count' => $count,
            'amount' => (float) $invoice->amount,
        ]);
    }

    public function cancelEvent(Request $request): JsonResponse
    {
        $data     = $request->validate(['id' => 'required|string']);
        [$type, $id] = explode('-', $data['id'], 2);
        $tenantId = $this->activeTenantId();

        if ($type === 'inv') {
            $invoice = $tenantId !== null
                ? Invoice::where('tenant_id', $tenantId)->findOrFail((int) $id)
                : Invoice::findOrFail((int) $id);

            if (in_array($invoice->status, ['paid', 'refunded'], true)) {
                return response()->json(['ok' => false, 'message' => 'Cobranca paga ou estornada nao pode ser cancelada.'], 422);
            }

            $cancelInstallments = $request->boolean('cancel_installments', false);

            if ($cancelInstallments) {
                // Cancelar a fatura atual e outras do mesmo valor/cliente
                $invoicesToCancel = Invoice::where('customer_id', $invoice->customer_id)
                    ->where('amount', $invoice->amount)
                    ->whereIn('status', ['pending', 'sent', 'overdue'])
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->get();
                
                $ids = $invoicesToCancel->pluck('id')->toArray();
                if (!in_array($invoice->id, $ids)) {
                    $ids[] = $invoice->id;
                }

                Invoice::whereIn('id', $ids)->update(['status' => 'canceled']);
                \App\Models\BillingDispatch::whereIn('invoice_id', $ids)
                    ->whereIn('status', ['scheduled', 'failed'])
                    ->update(['status' => 'canceled', 'updated_at' => now()]);

                $count = count($ids);
                return response()->json(['ok' => true, 'message' => "{$count} cobranca(s) cancelada(s)."]);
            }

            $invoice->update(['status' => 'canceled']);
            $invoice->dispatches()->whereIn('status', ['scheduled', 'failed'])->update([
                'status'     => 'canceled',
                'updated_at' => now(),
            ]);

            return response()->json(['ok' => true, 'message' => 'Cobranca cancelada.']);
        }

        if ($type === 'dsp') {
            $dispatch = BillingDispatch::findOrFail((int) $id);

            if ($tenantId !== null && (int) $dispatch->invoice?->tenant_id !== $tenantId) {
                return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
            }

            if ($dispatch->status !== 'scheduled') {
                return response()->json(['ok' => false, 'message' => 'Este envio nao pode ser cancelado.'], 422);
            }

            $dispatch->update(['status' => 'canceled']);
            return response()->json(['ok' => true, 'message' => 'Envio cancelado.']);
        }

        return response()->json(['ok' => false, 'message' => 'Tipo invalido.'], 422);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q        = trim((string) $request->query('q', ''));
        $tenantId = $this->activeTenantId();

        $query = Customer::with('tenant:id,company_name');

        if (Auth::guard('admin')->check()) {
            // Superadmin pode buscar em todos os clientes, a menos que especifique um tenant_id
            if ($filterTenantId = $request->query('tenant_id')) {
                if ($filterTenantId === 'master') {
                    $query->where(fn($sq) => $sq->whereNull('tenant_id')->orWhere('tenant_id', 0));
                } else {
                    $query->where('tenant_id', (int) $filterTenantId);
                }
            }
        } else {
            // Tenant vê apenas seus próprios clientes
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }
        }

        return response()->json($query
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('document', 'like', "%{$q}%")
                ->orWhere('whatsapp', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'tenant_id', 'name', 'email', 'whatsapp', 'document']));
    }

    protected function statusColor(string $status): string
    {
        return match ($status) {
            'paid'     => '#198754',
            'overdue'  => '#dc3545',
            'pending'  => '#ffc107',
            'sent'     => '#0d6efd',
            'canceled' => '#6c757d',
            default    => '#0d6efd',
        };
    }

    protected function billingDateOrFail(string $date): Carbon
    {
        $dueDate = Carbon::parse($date, 'America/Sao_Paulo')->startOfDay();
        $this->businessDateOrFail($dueDate, 'Vencimentos nao podem cair em fim de semana ou feriado.');
        return $dueDate;
    }

    protected function businessDateOrFail(Carbon $date, string $message): void
    {
        if (! $this->holidays->isNonBusinessDay($date)) {
            return;
        }
        $next = $this->holidays->nextBusinessDay($date)->format('d/m/Y');
        throw ValidationException::withMessages([
            'due_date' => "{$message} Proximo dia util: {$next}.",
        ]);
    }

    protected function holidayEventsForRange(?string $start, ?string $end): array
    {
        $startYear = $start ? Carbon::parse($start)->year : now('America/Sao_Paulo')->year;
        $endYear   = $end ? Carbon::parse($end)->year : $startYear;
        $events    = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            foreach ($this->holidays->calendarEvents($year) as $event) {
                $event['editable']         = false;
                $event['backgroundColor']  = $event['color'] ?? '#dc3545';
                $event['borderColor']      = $event['color'] ?? '#dc3545';
                $event['classNames']       = array_values(array_unique(array_merge($event['classNames'] ?? [], ['billing-holiday-event'])));
                $blocksBilling             = (bool) ($event['extendedProps']['block_billing'] ?? true);
                $event['extendedProps']    = array_merge($event['extendedProps'] ?? [], [
                    'kind'    => 'holiday',
                    'blocked' => $blocksBilling,
                ]);
                $events[] = $event;
            }
        }

        return $events;
    }

    protected function generateNumber(): string
    {
        return 'INV-'.str_pad((string) ((int) Invoice::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
