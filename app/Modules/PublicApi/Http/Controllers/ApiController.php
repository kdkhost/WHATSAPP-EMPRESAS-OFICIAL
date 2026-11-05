<?php

namespace App\Modules\PublicApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WhatsAppRecipientPreference;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

/**
 * API REST publica - endpoints para integracoes externas.
 * Auth: Bearer token via Laravel Sanctum.
 */
class ApiController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $q = Customer::where('tenant_id', $tenantId)->orderByDesc('id');
        if ($s = $request->query('search')) {
            $q->where(fn($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('whatsapp', 'like', "%{$s}%"));
        }
        return response()->json($q->paginate($request->query('per_page', 25)));
    }

    public function createCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'nullable|email',
            'whatsapp' => 'nullable|string|max:20',
            'document' => 'nullable|string|max:20',
            'phone'    => 'nullable|string|max:20',
            'whatsapp_opt_in' => 'nullable|boolean',
            'lgpd_consented' => 'nullable|boolean',
        ]);
        $data['tenant_id'] = $this->tenantId($request);
        $data['active'] = true;
        $customer = Customer::create($data);

        if (! empty($data['whatsapp'])) {
            WhatsAppRecipientPreference::query()->updateOrCreate(
                [
                    'tenant_id' => $data['tenant_id'],
                    'phone_hash' => hash('sha256', Format::whatsappNumber((string) $data['whatsapp'])),
                ],
                [
                    'customer_id' => $customer->id,
                    'phone' => Format::whatsappNumber((string) $data['whatsapp']),
                    'opt_in' => (bool) ($data['whatsapp_opt_in'] ?? true),
                    'opt_in_at' => ($data['whatsapp_opt_in'] ?? true) ? now() : null,
                    'opt_out' => ! (bool) ($data['whatsapp_opt_in'] ?? true),
                    'opt_out_at' => ($data['whatsapp_opt_in'] ?? true) ? null : now(),
                    'opt_out_reason' => ($data['whatsapp_opt_in'] ?? true) ? null : 'api_customer_without_opt_in',
                    'lgpd_consented' => (bool) ($data['lgpd_consented'] ?? false),
                    'lgpd_consented_at' => ($data['lgpd_consented'] ?? false) ? now() : null,
                ]
            );
        }

        return response()->json(['ok' => true, 'customer' => $customer], 201);
    }

    public function invoices(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $q = Invoice::with('customer')->where('tenant_id', $tenantId)->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('status', $s);
        return response()->json($q->paginate($request->query('per_page', 25)));
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $request->merge([
            'amount' => Format::decimal($request->input('amount')),
        ]);

        $tenantId = $this->tenantId($request);

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'description' => 'required|string|max:500',
            'amount'      => 'required|numeric|min:0.01',
            'due_date'    => 'required|date',
        ]);
        $this->assertBusinessDueDate($data['due_date']);

        $duplicate = Invoice::findOpenDuplicate(
            $tenantId,
            (int) $data['customer_id'],
            (string) $data['description'],
            (float) $data['amount'],
            (string) $data['due_date']
        );

        if ($duplicate) {
            return response()->json([
                'ok' => false,
                'error' => 'Ja existe uma fatura aberta com os mesmos dados para este cliente.',
                'invoice_id' => $duplicate->id,
            ], 422);
        }

        $data['tenant_id'] = $tenantId;
        $data['number'] = 'INV-' . str_pad((string) (Invoice::max('id') + 1), 6, '0', STR_PAD_LEFT);
        $data['status'] = 'pending';
        $data['webhook_token'] = Str::random(48);
        $invoice = Invoice::create($data);
        return response()->json(['ok' => true, 'invoice' => $invoice], 201);
    }

    public function sendInvoice(int $id, Request $request, BillingDispatcher $dispatcher): JsonResponse
    {
        $invoice = Invoice::where('tenant_id', $this->tenantId($request))->findOrFail($id);

        $localStatus = strtolower((string) $invoice->status);
        $inactiveStatuses = ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'];
        if (in_array($localStatus, $inactiveStatuses, true)) {
            $tipoMsg = match (true) {
                in_array($localStatus, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'Esta fatura está cancelada e não pode receber cobranças.',
                in_array($localStatus, ['refunded', 'estornada', 'estornado'], true) => 'Esta fatura está com status estornado e não pode receber cobranças.',
                default => 'Esta fatura já consta como paga e não pode receber cobranças.',
            };
            return response()->json(['ok' => false, 'error' => $tipoMsg], 422);
        }

        $dispatch = $invoice->dispatches()->create([
            'schedule_offset' => 'api',
            'scheduled_at'    => now(),
            'status'          => 'scheduled',
            'channel'         => $request->input('channel', 'whatsapp'),
        ]);
        $ok = $dispatcher->dispatchSpecific($dispatch->id);
        return response()->json(['ok' => $ok, 'dispatch_id' => $dispatch->id]);
    }

    public function dispatches(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $q = DB::table('billing_dispatches')
            ->join('invoices', 'invoices.id', '=', 'billing_dispatches.invoice_id')
            ->where('invoices.tenant_id', $tenantId)
            ->select('billing_dispatches.*')
            ->orderByDesc('billing_dispatches.id');
        return response()->json($q->paginate($request->query('per_page', 25)));
    }

    public function templates(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $templates = WhatsAppTemplate::where('tenant_id', $tenantId)->where('active', true)->get();
        return response()->json($templates);
    }

    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        return response()->json([
            'customers' => Customer::where('tenant_id', $tenantId)->count(),
            'invoices_pending' => Invoice::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'invoices_paid_30d' => Invoice::where('tenant_id', $tenantId)->where('status', 'paid')
                ->where('paid_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    protected function tenantId(Request $request): int
    {
        $tenant = $request->attributes->get('tenant')
            ?: \App\Modules\Tenancy\Models\Tenant::where('user_id', $request->user()->id)->first();

        if (! $tenant) {
            abort(403, 'Tenant nao encontrado para este token de API.');
        }

        return (int) $tenant->id;
    }

    protected function assertBusinessDueDate(string $dueDate): void
    {
        $date = Carbon::parse($dueDate, 'America/Sao_Paulo')->startOfDay();
        $holidays = app(HolidayService::class);

        if (! $holidays->isNonBusinessDay($date)) {
            return;
        }

        throw ValidationException::withMessages([
            'due_date' => 'Vencimentos nao podem cair em fim de semana ou feriado. Proximo dia util: '.$holidays->nextBusinessDay($date)->format('d/m/Y').'.',
        ]);
    }
}
