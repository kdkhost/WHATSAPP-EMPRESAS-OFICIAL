<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\TenantTemplateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /** Retorna tenant_id ativo. Null = superadmin (vê tudo). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    public function index(Request $request)
    {
        $tenantId = $this->activeTenantId();

        $q = Invoice::with('customer')->orderByDesc('id');
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }
        if ($s = $request->query('status'))   $q->where('status', $s);
        if ($gateway = $request->query('gateway')) $q->where('gateway', $gateway);
        if ($search  = $request->query('q')) {
            $q->where(fn ($w) => $w
                ->where('number', 'like', "%$search%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$search%")));
        }
        $invoices = $q->paginate(25)->withQueryString();

        // Modo de visualização: agrupado por carnê (padrão) ou individual
        $viewMode = $request->query('view_mode', 'grouped');
        $displayRows = [];

        if ($viewMode === 'grouped') {
            $batchIds = $invoices->getCollection()
                ->filter(fn ($i) => !empty($i->meta['batch_id']) && (int) ($i->meta['total_installments'] ?? 1) > 1)
                ->map(fn ($i) => $i->meta['batch_id'])
                ->unique()
                ->values()
                ->all();

            $carnesMap = [];
            if (!empty($batchIds)) {
                $carnesQuery = Invoice::with('customer')
                    ->whereIn('meta->batch_id', $batchIds);
                if ($tenantId !== null) {
                    $carnesQuery->where('tenant_id', $tenantId);
                }
                $carnesMap = $carnesQuery->get()
                    ->sortBy(fn ($i) => (int) ($i->meta['installment'] ?? 1))
                    ->groupBy(fn ($i) => $i->meta['batch_id']);
            }

            $processedBatches = [];

            foreach ($invoices as $inv) {
                $batchId = $inv->meta['batch_id'] ?? null;
                $totalInst = (int) ($inv->meta['total_installments'] ?? 1);

                if ($batchId && $totalInst > 1) {
                    if (isset($processedBatches[$batchId])) {
                        continue;
                    }
                    $processedBatches[$batchId] = true;

                    $items = $carnesMap[$batchId] ?? collect([$inv]);
                    $paidCount = $items->where('status', 'paid')->count();
                    $overdueCount = $items->where('status', 'overdue')->count();
                    $pendingCount = $items->whereIn('status', ['pending', 'sent'])->count();
                    $canceledCount = $items->whereIn('status', ['canceled', 'cancelled'])->count();

                    $totalAmount = $items->sum('amount');
                    $paidAmount = $items->where('status', 'paid')->sum('amount');
                    $overdueAmount = $items->where('status', 'overdue')->sum('amount');
                    $pendingAmount = $items->whereIn('status', ['pending', 'sent'])->sum('amount');

                    $cleanDesc = preg_replace('/\s*\(Parcela\s+\d+\/\d+\)/i', '', $inv->description ?: 'Carnê de Cobrança');

                    $nextDue = $items->where('status', 'overdue')->sortBy('due_date')->first()
                        ?: $items->whereIn('status', ['pending', 'sent'])->sortBy('due_date')->first()
                        ?: $items->last();

                    $progressPercent = $totalInst > 0 ? round(($paidCount / $totalInst) * 100) : 0;

                    $displayRows[] = (object) [
                        'type'               => 'carne',
                        'batch_id'           => $batchId,
                        'customer'           => $inv->customer,
                        'description'        => $cleanDesc,
                        'total_installments' => $totalInst,
                        'installment_amount' => (float) $inv->amount,
                        'total_amount'       => (float) $totalAmount,
                        'paid_count'         => $paidCount,
                        'paid_amount'        => (float) $paidAmount,
                        'overdue_count'      => $overdueCount,
                        'overdue_amount'     => (float) $overdueAmount,
                        'pending_count'      => $pendingCount,
                        'pending_amount'     => (float) $pendingAmount,
                        'canceled_count'     => $canceledCount,
                        'progress_percent'   => $progressPercent,
                        'next_due_invoice'   => $nextDue,
                        'items'              => $items,
                    ];
                } else {
                    $displayRows[] = (object) [
                        'type'    => 'single',
                        'invoice' => $inv,
                    ];
                }
            }
        } else {
            foreach ($invoices as $inv) {
                $displayRows[] = (object) [
                    'type'    => 'single',
                    'invoice' => $inv,
                ];
            }
        }

        // KPIs — escopados ao tenant
        $statsBase = $tenantId !== null
            ? DB::table('invoices')->where('tenant_id', $tenantId)
            : DB::table('invoices');

        $stats = (clone $statsBase)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) AS overdue,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS revenue
        ")->first();

        return view('billing::invoices.index', compact('invoices', 'stats', 'displayRows', 'viewMode'));
    }

    public function create()
    {
        $tenantId = $this->activeTenantId();
        $query = \App\Models\Customer::with('tenant:id,company_name');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $customers = $query->orderBy('name')->limit(200)->get();

        return view('billing::invoices.create', compact('customers'));
    }

    public function store(Request $request, \App\Modules\Billing\Services\BillingDispatcher $dispatcher, \App\Modules\Billing\Services\InvoicePaymentService $paymentService)
    {
        $tenantId = $this->activeTenantId();

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|string',
            'installments' => 'required|integer|min:1|max:24',
            'interest_rate' => 'required|numeric|min:0',
            'first_due_date' => 'required|date',
            'gateway' => 'nullable|string',
            'send_behavior' => 'nullable|string',
            'custom_send_date' => 'nullable|date'
        ]);

        $amountStr = str_replace(['.', ','], ['', '.'], $validated['amount']);
        $baseAmount = (float) $amountStr;
        
        $installments = (int) $validated['installments'];
        $interestRate = (float) $validated['interest_rate'];

        // Formula de juros simples mensal
        $interestAmount = $baseAmount * ($interestRate / 100) * $installments;
        $totalAmount = $baseAmount + $interestAmount;
        $portionAmount = $totalAmount / $installments;

        $firstDueDate = \Carbon\Carbon::parse($validated['first_due_date']);
        $batchId = (string) \Illuminate\Support\Str::uuid();
        
        $targetCustomer = \App\Models\Customer::find($validated['customer_id']);
        $effectiveTenantId = $tenantId ?: ($targetCustomer ? $targetCustomer->tenant_id : null);

        DB::beginTransaction();
        try {
            $createdIds = [];
            for ($i = 1; $i <= $installments; $i++) {
                $dueDate = clone $firstDueDate;
                $dueDate->addMonthsNoOverflow($i - 1);
                
                $desc = $validated['description'];
                if ($installments > 1) {
                    $desc .= " (Parcela {$i}/{$installments})";
                }

                $invoice = Invoice::create([
                    'tenant_id' => $effectiveTenantId,
                    'customer_id' => $validated['customer_id'],
                    'number' => 'AV-' . strtoupper(\Illuminate\Support\Str::random(8)),
                    'description' => $desc,
                    'amount' => round($portionAmount, 2),
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'pending',
                    'gateway' => $validated['gateway'] ?: null,
                    'webhook_token' => \Illuminate\Support\Str::random(48),
                    'meta' => [
                        'is_standalone' => true,
                        'installment' => $i,
                        'total_installments' => $installments,
                        'batch_id' => $batchId
                    ]
                ]);

                // Aciona gatilho de criacao do gateway (Pix/Boleto)
                try {
                    $paymentService->hydrateGatewayPayment($invoice);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Erro gerar pagamento na fatura avulsa: " . $e->getMessage());
                }
                
                $createdIds[] = $invoice->id;

                $sendBehavior = $validated['send_behavior'] ?? 'none';
                if ($i === 1 && $sendBehavior !== 'none') {
                    try {
                        $template = $this->manualWhatsAppTemplate($invoice);
                        if ($template) {
                            $scheduledAt = now();
                            if ($sendBehavior === 'custom_date' && !empty($validated['custom_send_date'])) {
                                $scheduledAt = \Carbon\Carbon::parse($validated['custom_send_date']);
                            }
                            
                            $dispatch = $invoice->dispatches()->create([
                                'template_id'     => $template->id,
                                'channel'         => 'whatsapp',
                                'schedule_offset' => 'manual',
                                'scheduled_at'    => $scheduledAt,
                                'status'          => 'scheduled',
                            ]);
                            
                            if ($sendBehavior === 'immediate') {
                                $dispatcher->dispatchSpecific($dispatch->id);
                            }
                        }
                    } catch (\Throwable $e) {
                        // Ignora erro no envio p/ nao cancelar a fatura
                    }
                }
            }
            DB::commit();

            // Agenda a régua de cobrança automática para as novas faturas
            try {
                $dispatcher->scheduleNewInvoices();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[InvoiceController] Falha ao agendar régua automática: " . $e->getMessage());
            }

            $msg = $installments > 1 ? "$installments faturas geradas com sucesso." : "Fatura gerada com sucesso.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => $msg,
                    'invoice_id' => $createdIds[0] ?? null
                ]);
            }

            return redirect()->route('admin.billing.invoices.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            $errMsg = 'Erro ao criar fatura: ' . $e->getMessage();
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $errMsg], 500);
            }
            return back()->withInput()->with('error', $errMsg);
        }
    }

    public function show(int $id)
    {
        $tenantId = $this->activeTenantId();

        $invoice = Invoice::with(['customer', 'dispatches'])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        return view('billing::invoices.show', compact('invoice'));
    }

    public function sendNow(int $id, BillingDispatcher $dispatcher)
    {
        $tenantId = $this->activeTenantId();

        $invoice = Invoice::with('customer')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

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

        // Se for fatura vinculada ao WHMCS, valida o status atualizado no WHMCS antes do envio
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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[InvoiceController] Erro ao checar WHMCS antes de sendNow: " . $e->getMessage());
            }
        }

        if (! filled($invoice->customer?->whatsapp)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Cliente sem WhatsApp cadastrado. Atualize o cadastro antes de enviar a cobranca.',
            ], 422);
        }

        $template = $this->manualWhatsAppTemplate($invoice);
        if (! $template) {
            return response()->json([
                'ok'    => false,
                'error' => 'Nenhum template de WhatsApp ativo encontrado para envio manual.',
            ], 422);
        }

        $dispatch = $invoice->dispatches()->create([
            'template_id'     => $template->id,
            'channel'         => 'whatsapp',
            'schedule_offset' => 'manual',
            'scheduled_at'    => now(),
            'status'          => 'scheduled',
        ]);

        $ok = $dispatcher->dispatchSpecific($dispatch->id);
        $dispatch->refresh();

        if ($ok) {
            return response()->json([
                'ok'           => true,
                'status'       => $dispatch->status,
                'scheduled_at' => optional($dispatch->scheduled_at)->toIso8601String(),
                'message'      => $dispatch->status === 'scheduled'
                    ? 'Cobranca reagendada pela camada anti-ban para envio seguro.'
                    : 'Cobranca enviada com sucesso.',
            ]);
        }

        return response()->json([
            'ok'     => false,
            'status' => $dispatch->status,
            'error'  => $dispatch->last_error ?: 'Nao foi possivel enviar a cobranca pelo WhatsApp.',
        ], 422);
    }

    public function installmentContext(int $id): \Illuminate\Http\JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $invoice = Invoice::with('customer')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        $context = app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->getInstallmentContext($invoice);

        return response()->json([
            'ok'      => true,
            'context' => $context,
        ]);
    }

    public function settleInvoice(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $invoice = Invoice::with('customer')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => false, 'error' => 'Esta fatura já está quitada.'], 422);
        }

        $data = $request->validate([
            'gateway'         => 'nullable|string|in:manual,pix,dinheiro,cartao,transferencia,boleto,outro',
            'paid_at'         => 'nullable|date',
            'notify_customer' => 'nullable|boolean',
            'notes'           => 'nullable|string|max:500',
            'settle_mode'     => 'nullable|string|in:single,antecipated,all_open,selected',
            'selected_ids'    => 'nullable|array',
            'selected_ids.*'  => 'integer',
        ]);

        $gateway = $data['gateway'] ?: 'manual';
        $paidAt = ! empty($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now();
        $notifyCustomer = $request->boolean('notify_customer', true);
        $notes = $data['notes'] ?? null;
        $settleMode = $data['settle_mode'] ?? 'single';

        $service = app(\App\Modules\Billing\Services\InvoiceSettlementService::class);
        $targetIds = [$invoice->id];

        if ($settleMode !== 'single') {
            $context = $service->getInstallmentContext($invoice);

            if ($settleMode === 'antecipated') {
                foreach ($context['antecipated_installments'] as $ant) {
                    $targetIds[] = $ant['id'];
                }
            } elseif ($settleMode === 'all_open') {
                foreach ($context['open_installments'] as $op) {
                    $targetIds[] = $op['id'];
                }
            } elseif ($settleMode === 'selected' && ! empty($data['selected_ids'])) {
                $allowedIds = collect($context['open_installments'])->pluck('id')->all();
                foreach ($data['selected_ids'] as $selId) {
                    if (in_array($selId, $allowedIds, true)) {
                        $targetIds[] = $selId;
                    }
                }
            }
        }

        $targetIds = array_values(array_unique($targetIds));

        $res = $service->settleMultiple(
            $targetIds,
            $gateway,
            null,
            $notifyCustomer,
            $paidAt,
            $notes,
            $tenantId
        );

        $count = $res['count'];
        $totalFormatted = number_format($res['total_amount'], 2, ',', '.');
        $msg = $count > 1
            ? "Baixa realizada com sucesso em {$count} parcelas vinculadas (Total: R$ {$totalFormatted})."
            : "Fatura #{$invoice->number} baixada como paga com sucesso (R$ {$totalFormatted}).";

        return response()->json([
            'ok'           => true,
            'message'      => $msg,
            'settled_ids'  => $res['settled_ids'],
            'count'        => $count,
            'total_amount' => $res['total_amount'],
        ]);
    }

    public function bulkSettle(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $data = $request->validate([
            'ids'             => 'required|array|min:1',
            'ids.*'           => 'integer',
            'gateway'         => 'nullable|string|in:manual,pix,dinheiro,cartao,transferencia,boleto,outro',
            'paid_at'         => 'nullable|date',
            'notify_customer' => 'nullable|boolean',
            'notes'           => 'nullable|string|max:500',
        ]);

        $gateway = $data['gateway'] ?: 'manual';
        $paidAt = ! empty($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now();
        $notifyCustomer = $request->boolean('notify_customer', true);
        $notes = $data['notes'] ?? null;

        $res = app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->settleMultiple(
            $data['ids'],
            $gateway,
            null,
            $notifyCustomer,
            $paidAt,
            $notes,
            $tenantId
        );

        $totalFormatted = number_format($res['total_amount'], 2, ',', '.');
        return response()->json([
            'ok'           => true,
            'message'      => "Baixa manual realizada com sucesso em {$res['count']} fatura(s) (Total: R$ {$totalFormatted}).",
            'settled_ids'  => $res['settled_ids'],
            'count'        => $res['count'],
            'total_amount' => $res['total_amount'],
        ]);
    }

    /**
     * Executa estorno total ou parcial no Mercado Pago SDK v2.
     */
    public function refundInvoice(int $id, Request $request)
    {
        $invoice = Invoice::findOrFail($id);
        $amount = $request->input('amount') ? (float) $request->input('amount') : (float) $invoice->amount;

        $chargeId = $invoice->gateway_charge_id;
        if (empty($chargeId)) {
            return response()->json(['ok' => false, 'error' => 'Esta fatura não possui identificador do Mercado Pago para estorno.'], 422);
        }

        $mpService = app(\App\Modules\MercadoPago\Services\MercadoPagoPaymentService::class);
        $res = $mpService->refund((string) $chargeId, $amount);

        if (!empty($res['ok'])) {
            return response()->json([
                'ok' => true,
                'message' => 'Estorno confirmado com sucesso no Mercado Pago!',
                'data' => $res,
            ]);
        }

        return response()->json([
            'ok' => false,
            'error' => $res['error'] ?? 'Falha ao estornar pagamento no Mercado Pago.',
        ], 422);
    }

    protected function manualWhatsAppTemplate(Invoice $invoice): ?WhatsAppTemplate
    {
        $resolver = app(TenantTemplateResolver::class);
        $tenantId = $invoice->tenant_id ? (int) $invoice->tenant_id : null;

        // Se a fatura for exclusivamente boleto bancário (com linha digitável cadastrada), permite o template de boleto
        $metodo = strtolower((string) ($invoice->gateway ?? data_get($invoice->meta, 'metodo_pagamento', '')));
        $isBoleto = str_contains($metodo, 'boleto') && filled(data_get($invoice->meta, 'linha_digitavel'));
        if ($isBoleto) {
            $boletoTpl = $resolver->whatsapp($tenantId, null, 'cobranca', 'boleto-fatura');
            if ($boletoTpl) {
                return $boletoTpl;
            }
        }

        // 1. Se o tenant possui preferência ativa gravada para manual/cobrança
        $preferred = $resolver->preferredTemplate('whatsapp', WhatsAppTemplate::class, $tenantId, 'cobranca', 'manual');
        if ($preferred instanceof WhatsAppTemplate && $preferred->slug !== 'boleto-fatura') {
            return $preferred;
        }

        // 2. Se o próprio tenant criou um template customizado manual no seu tenant
        if ($tenantId) {
            $customTenantTpl = WhatsAppTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('active', true)
                ->where(function ($q) {
                    $q->whereIn('schedule_offset', ['manual', 'send_now', 'custom'])
                      ->orWhere('slug', 'cobranca-gerada');
                })
                ->where('slug', '!=', 'boleto-fatura')
                ->first();

            if ($customTenantTpl) {
                return $customTenantTpl;
            }
        }

        // 3. Resolve com base na data de vencimento real cadastrada na fatura (idêntico ao Administrador Geral)
        $dueDate = $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date, 'America/Sao_Paulo')->startOfDay() : null;
        $today = now('America/Sao_Paulo')->startOfDay();

        if ($dueDate) {
            if ($dueDate->equalTo($today)) {
                // Vencimento cadastrado é HOJE: usa template 0d (vencimento-hoje)
                return $resolver->whatsapp($tenantId, '0d', 'cobranca', 'vencimento-hoje')
                    ?: $resolver->whatsapp($tenantId, '0d', 'cobranca')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }

            $diffDays = (int) $today->diffInDays($dueDate, false); // > 0 futuro, < 0 passado

            if ($diffDays === 1) {
                // Vencimento cadastrado é AMANHÃ: usa template de véspera (-1d) (lembrete-1d-antes)
                return $resolver->whatsapp($tenantId, '-1d', 'lembrete', 'lembrete-1d-antes')
                    ?: $resolver->whatsapp($tenantId, '-1d', 'lembrete')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }

            if ($diffDays > 1) {
                // Vencimento cadastrado no FUTURO (2 ou mais dias): usa lembrete-3d-antes ou cobranca-gerada
                return $resolver->whatsapp($tenantId, '-3d', 'lembrete', 'lembrete-3d-antes')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada')
                    ?: $resolver->whatsapp($tenantId, '-3d', 'lembrete');
            }

            if ($diffDays < 0) {
                // Fatura já criada com data de vencimento retroativa (em atraso)
                $offset = abs($diffDays) >= 7 ? '+7d' : '+1d';
                $slug = abs($diffDays) >= 7 ? 'atraso-7d' : 'atraso-1d';
                return $resolver->whatsapp($tenantId, $offset, 'atraso', $slug)
                    ?: $resolver->whatsapp($tenantId, '+1d', 'atraso')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }
        }

        // 4. Fallback padrão: template de cobrança oficial do dia ou cobrança gerada
        return $resolver->whatsapp($tenantId, '0d', 'cobranca', 'vencimento-hoje')
            ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada')
            ?: WhatsAppTemplate::query()
                ->where(function ($q) use ($tenantId) {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                })
                ->where('active', true)
                ->where('slug', '!=', 'boleto-fatura')
                ->whereIn('slug', ['vencimento-hoje', 'cobranca-gerada', 'lembrete-1d-antes', 'lembrete-3d-antes', 'pix-copia-cola'])
                ->orderByDesc('is_default')
                ->first();
    }
}
