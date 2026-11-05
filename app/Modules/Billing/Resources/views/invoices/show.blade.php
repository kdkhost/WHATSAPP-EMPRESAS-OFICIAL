@extends('admin.layouts.app')
@section('title', 'Fatura ' . $invoice->number)

@php
    $statusMap = [
        'pending'  => ['color' => 'warning', 'icon' => 'bi-hourglass-split', 'label' => 'Pendente'],
        'sent'     => ['color' => 'primary', 'icon' => 'bi-send', 'label' => 'Enviada'],
        'viewed'   => ['color' => 'info', 'icon' => 'bi-eye', 'label' => 'Visualizada'],
        'paid'     => ['color' => 'success', 'icon' => 'bi-check-circle', 'label' => 'Paga'],
        'overdue'  => ['color' => 'danger', 'icon' => 'bi-exclamation-triangle', 'label' => 'Vencida'],
        'canceled' => ['color' => 'secondary', 'icon' => 'bi-x-circle', 'label' => 'Cancelada'],
        'refunded' => ['color' => 'dark', 'icon' => 'bi-arrow-counterclockwise', 'label' => 'Reembolsada'],
    ];
    $status = $statusMap[$invoice->status] ?? ['color' => 'secondary', 'icon' => 'bi-circle', 'label' => ucfirst((string) $invoice->status)];

    $dispatchStatusMap = [
        'scheduled'  => ['color' => 'info', 'label' => 'Agendado'],
        'pending'    => ['color' => 'warning', 'label' => 'Pendente'],
        'processing' => ['color' => 'primary', 'label' => 'Processando'],
        'sent'       => ['color' => 'success', 'label' => 'Enviado'],
        'failed'     => ['color' => 'danger', 'label' => 'Falhou'],
        'canceled'   => ['color' => 'secondary', 'label' => 'Cancelado'],
    ];

    $rawDescription = html_entity_decode((string) $invoice->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descriptionHtml = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $rawDescription);
    $descriptionHtml = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $descriptionHtml);
    $descriptionHtml = preg_replace('/\bon\w+\s*=\s*[^\s>]*/i', '', $descriptionHtml);
    $descriptionHtml = preg_replace('/javascript\s*:/i', '', $descriptionHtml);
    $descriptionHtml = strip_tags($descriptionHtml, '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td><span><div>');
    $descriptionHtml = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $descriptionHtml);
    $descriptionPlain = trim((string) \Illuminate\Support\Str::of(strip_tags($descriptionHtml))->squish());
    if ($descriptionPlain !== '' && $descriptionHtml === strip_tags($descriptionHtml)) {
        $descriptionHtml = nl2br(e($descriptionPlain));
    }
@endphp

@section('content')
<div class="container-fluid invoice-show-page">
    <div class="invoice-hero mb-4">
        <div>
            <span class="hero-kicker"><i class="bi bi-receipt me-1"></i> Fatura</span>
            <h1>{{ $invoice->number }}</h1>
            <p>{{ $invoice->customer?->name ?? 'Cliente nao informado' }} · vencimento {{ \App\Support\Format::date($invoice->due_date) ?: 'sem data' }}</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
            <a href="{{ route('admin.invoice-editor.edit', $invoice->id) }}" class="btn btn-outline-light btn-sm">
                <i class="bi bi-pencil-square me-1"></i> Editar
            </a>
            @if(in_array($invoice->status, ['pending', 'sent', 'overdue'], true))
                <button type="button" class="btn btn-success btn-sm" id="btn-settle-invoice">
                    <i class="bi bi-check2-circle me-1"></i> Dar Baixa Manual
                </button>
                <button type="button" class="btn btn-outline-light btn-sm" id="btn-send-now">
                    <i class="bi bi-whatsapp me-1"></i> Enviar WhatsApp
                </button>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Cliente</span>
                <strong>{{ $invoice->customer?->name ?? '-' }}</strong>
                <small>{{ \App\Support\Format::phone($invoice->customer?->whatsapp) ?: 'WhatsApp nao informado' }}</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Valor</span>
                <strong>{{ \App\Support\Format::money($invoice->amount) }}</strong>
                <small>{{ $invoice->gateway ? ucfirst((string) $invoice->gateway) : 'Gateway padrao' }}</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Vencimento</span>
                <strong>{{ \App\Support\Format::date($invoice->due_date) ?: '-' }}</strong>
                <small>Emitida em {{ \App\Support\Format::date($invoice->created_at) }}</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Status</span>
                <strong><i class="bi {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}</strong>
                <small class="text-{{ $status['color'] }}">Situacao atual da cobranca</small>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="premium-panel h-100">
                <div class="panel-heading">
                    <div>
                        <h2>Detalhes da cobranca</h2>
                        <p>Descricao renderizada como texto rico, sem expor marcadores internos do editor.</p>
                    </div>
                    <span class="badge text-bg-{{ $status['color'] }}"><i class="bi {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}</span>
                </div>
                <div class="panel-body">
                    @if($descriptionPlain !== '')
                        <div class="invoice-description">
                            {!! $descriptionHtml !!}
                        </div>
                    @else
                        <div class="empty-state compact">
                            <i class="bi bi-card-text"></i>
                            <span>Nenhuma descricao informada.</span>
                        </div>
                    @endif

                    @if($invoice->webhook_token)
                        <div class="payment-link-box mt-4">
                            <div>
                                <strong>Link de pagamento</strong>
                                <span>{{ route('public.invoice.show', $invoice->webhook_token) }}</span>
                            </div>
                            <a href="{{ route('public.invoice.show', $invoice->webhook_token) }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Abrir
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="premium-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Envios agendados</h2>
                        <p>{{ $invoice->dispatches->count() }} registro(s) vinculados.</p>
                    </div>
                </div>
                <div class="dispatch-list">
                    @forelse($invoice->dispatches as $dispatch)
                        @php
                            $dispatchStatus = $dispatchStatusMap[$dispatch->status] ?? ['color' => 'secondary', 'label' => ucfirst((string) $dispatch->status)];
                            $offsetLabel = $dispatch->schedule_offset === 'manual'
                                ? 'Manual'
                                : str_replace(['-', '+'], ['Antes ', 'Depois '], (string) $dispatch->schedule_offset);
                        @endphp
                        <div class="dispatch-item">
                            <div>
                                <strong>{{ $offsetLabel }}</strong>
                                <small>{{ \App\Support\Format::datetime($dispatch->scheduled_at) }}</small>
                            </div>
                            <span class="badge text-bg-{{ $dispatchStatus['color'] }}">{{ $dispatchStatus['label'] }}</span>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-send"></i>
                            <span>Nenhum envio agendado.</span>
                        </div>
                    @endforelse
                </div>
            </div>

            @php
                $paymentAttempts = data_get($invoice->meta, 'payment_attempts', []);
                $paymentAttempts = array_reverse((array) $paymentAttempts);
            @endphp
            <div class="premium-panel mt-3">
                <div class="panel-heading">
                    <div>
                        <h2>Tentativas de Pagamento</h2>
                        <p>{{ count($paymentAttempts) }} registro(s) (Cartão).</p>
                    </div>
                </div>
                <div class="dispatch-list">
                    @forelse($paymentAttempts as $attempt)
                        @php
                            $st = $attempt['status'] ?? 'unknown';
                            $color = $st === 'approved' ? 'success' : ($st === 'rejected' ? 'danger' : 'warning');
                        @endphp
                        <div class="dispatch-item flex-column align-items-start">
                            <div class="d-flex justify-content-between w-100 mb-1">
                                <strong>Cartão {{ ucfirst($attempt['brand'] ?? 'Desconhecido') }} final {{ $attempt['last4'] ?? '****' }}</strong>
                                <span class="badge text-bg-{{ $color }}">{{ ucfirst($st) }}</span>
                            </div>
                            <div class="w-100 small text-muted">
                                <div><i class="bi bi-clock me-1"></i> {{ \Carbon\Carbon::parse($attempt['date'])->format('d/m/Y H:i:s') }}</div>
                                @if(!empty($attempt['error_message']) && $st !== 'approved')
                                    <div class="text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i> {{ $attempt['error_message'] }}</div>
                                @endif
                                @if(!empty($attempt['status_detail']) && $st !== 'approved')
                                    <div class="mt-1" style="font-size: 0.75rem;">(Ref: {{ $attempt['status_detail'] }})</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-credit-card"></i>
                            <span>Nenhuma tentativa de cartão registrada ainda.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Baixa Manual (Admin Show) --}}
    <div class="modal fade" id="modal-settle-invoice" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg border-0">
                <form id="frm-settle-invoice">
                    @csrf
                    <input type="hidden" id="settle-invoice-id" value="{{ $invoice->id }}">
                    <input type="hidden" id="settle-mode" value="single">

                    <div class="modal-header bg-success text-white py-3">
                        <div>
                            <h5 class="modal-title fs-5 fw-bold mb-0">
                                <i class="bi bi-check2-circle me-2"></i>Dar Baixa Manual na Fatura
                            </h5>
                            <p class="small mb-0 text-white-50">Confirme o recebimento e quitação da fatura e suas parcelas.</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border mb-3 flex-wrap gap-2">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold">Fatura</span>
                                <div class="fs-5 fw-bold text-dark" id="settle-summary-number">#{{ $invoice->number }}</div>
                                <div class="small text-muted" id="settle-summary-customer">{{ $invoice->customer?->name ?? 'Cliente' }}</div>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small text-uppercase fw-bold">Valor da Parcela/Fatura</span>
                                <div class="fs-4 fw-bold text-success" id="settle-summary-amount">R$ {{ number_format((float)$invoice->amount, 2, ',', '.') }}</div>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle" id="settle-summary-due">Vencimento: {{ \App\Support\Format::date($invoice->due_date) ?: '-' }}</span>
                            </div>
                        </div>

                        <div id="settle-installment-section" class="d-none mb-3">
                            <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                        <i class="bi bi-layers-fill text-primary fs-5"></i>
                                        <strong class="text-primary">Fatura com Parcelamento / Carnê Vinculado</strong>
                                        <span class="badge bg-primary ms-auto" id="settle-badge-installments">Parcelas vinculadas</span>
                                    </div>
                                    <p class="small text-muted mb-3">
                                        Esta fatura pertence a uma série de parcelas deste cliente. Escolha como deseja aplicar a baixa:
                                    </p>

                                    <div class="d-grid gap-2 mb-2">
                                        <div class="form-check p-2 bg-body-tertiary rounded border">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="settle_radio_mode" id="radio-mode-single" value="single" checked onchange="updateSettleModeUI()">
                                            <label class="form-check-label fw-semibold" for="radio-mode-single">
                                                Baixar apenas esta parcela (<span id="settle-single-amount-label">R$ {{ number_format((float)$invoice->amount, 2, ',', '.') }}</span>)
                                            </label>
                                        </div>

                                        <div class="form-check p-2 bg-body-tertiary rounded border d-none" id="container-mode-antecipated">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="settle_radio_mode" id="radio-mode-antecipated" value="antecipated" onchange="updateSettleModeUI()">
                                            <label class="form-check-label" for="radio-mode-antecipated">
                                                <strong class="text-primary"><i class="bi bi-lightning-charge me-1"></i>Baixar esta parcela + <span id="settle-antecipated-count">0</span> parcela(s) antecipada(s)</strong>
                                                <div class="small text-muted">Total combinado a liquidar: <span class="fw-bold text-success" id="settle-antecipated-total">R$ 0,00</span></div>
                                            </label>
                                        </div>

                                        <div class="form-check p-2 bg-body-tertiary rounded border" id="container-mode-all">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="settle_radio_mode" id="radio-mode-all" value="all_open" onchange="updateSettleModeUI()">
                                            <label class="form-check-label" for="radio-mode-all">
                                                <strong><i class="bi bi-check2-all me-1"></i>Quitar todas as <span id="settle-all-count">0</span> parcelas pendentes</strong> (Quitação Total Antecipada)
                                                <div class="small text-muted">Total geral de quitação: <span class="fw-bold text-success" id="settle-all-total">R$ 0,00</span></div>
                                            </label>
                                        </div>

                                        <div class="form-check p-2 bg-body-tertiary rounded border">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="settle_radio_mode" id="radio-mode-selected" value="selected" onchange="updateSettleModeUI()">
                                            <label class="form-check-label" for="radio-mode-selected">
                                                <strong><i class="bi bi-ui-checks me-1"></i>Selecionar parcelas específicas manualmente</strong>
                                            </label>
                                        </div>
                                    </div>

                                    <div id="settle-custom-installments-list" class="d-none mt-2 p-2 bg-body-tertiary rounded border small" style="max-height: 180px; overflow-y: auto;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Forma de Pagamento <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="settle-gateway" required>
                                    <option value="pix" selected>PIX</option>
                                    <option value="dinheiro">Dinheiro em Espécie</option>
                                    <option value="cartao">Cartão de Crédito / Débito</option>
                                    <option value="transferencia">Transferência Bancária / TED</option>
                                    <option value="boleto">Boleto Bancário</option>
                                    <option value="manual">Outro / Manual</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Data do Pagamento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="settle-paid-at" required value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Observações / Comprovante (opcional)</label>
                                <input type="text" class="form-control form-control-sm" id="settle-notes" placeholder="Ex.: Pago na recepção em dinheiro / Comprovante recebido">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="settle-notify-whatsapp" checked>
                                    <label class="form-check-label small fw-semibold" for="settle-notify-whatsapp">
                                        <i class="bi bi-whatsapp text-success me-1"></i>Enviar mensagem de confirmação/agradecimento via WhatsApp ao cliente
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light py-2 border-top d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small">Valor a Liquidar:</span>
                            <span class="fs-5 fw-bold text-success ms-1" id="settle-final-amount">R$ {{ number_format((float)$invoice->amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-success fw-semibold" id="btn-confirm-settle">
                                <i class="bi bi-check2-circle me-1"></i><span id="btn-settle-text">Confirmar Baixa</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.invoice-show-page { --invoice-border: rgba(148,163,184,.24); --invoice-shadow: 0 18px 42px rgba(15,23,42,.10); }
.invoice-hero { border: 1px solid rgba(255,255,255,.18); border-radius: 22px; padding: 28px; color: #fff; background: linear-gradient(135deg,#111827 0%,#274060 52%,#0d6efd 125%); box-shadow: var(--invoice-shadow); display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; overflow: hidden; position: relative; }
.invoice-hero::after { content: ""; position: absolute; inset: auto -90px -120px auto; width: 300px; height: 300px; background: rgba(255,255,255,.10); border-radius: 50%; }
.hero-kicker { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.invoice-hero h1 { margin: 14px 0 8px; font-size: clamp(1.7rem, 3vw, 2.55rem); font-weight: 800; letter-spacing: 0; }
.invoice-hero p { margin: 0; color: rgba(255,255,255,.82); }
.hero-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; position: relative; z-index: 1; }
.metric-card, .premium-panel { border: 1px solid var(--invoice-border); border-radius: 18px; background: var(--bs-body-bg); box-shadow: var(--invoice-shadow); overflow: hidden; }
.metric-card { padding: 18px 20px; min-height: 132px; display: grid; align-content: center; gap: 4px; }
.metric-card span { color: var(--bs-secondary-color); font-size: .76rem; text-transform: uppercase; letter-spacing: .04em; font-weight: 800; }
.metric-card strong { font-size: 1.1rem; line-height: 1.25; }
.metric-card small { color: var(--bs-secondary-color); }
.panel-heading { padding: 20px 22px; display: flex; justify-content: space-between; gap: 16px; align-items: center; border-bottom: 1px solid var(--invoice-border); background: rgba(148,163,184,.06); }
.panel-heading h2 { font-size: 1.08rem; margin: 0 0 4px; font-weight: 800; }
.panel-heading p { margin: 0; color: var(--bs-secondary-color); font-size: .9rem; }
.panel-body { padding: 24px; }
.invoice-description { color: var(--bs-body-color); line-height: 1.72; font-size: .98rem; }
.invoice-description :is(p, ul, ol, blockquote, table) { margin-bottom: 1rem; }
.invoice-description :is(h1, h2, h3, h4) { font-size: 1.08rem; margin: 1.1rem 0 .55rem; font-weight: 800; }
.invoice-description strong, .invoice-description b { font-weight: 800; }
.invoice-description blockquote { border-left: 3px solid var(--bs-primary); padding-left: 14px; color: var(--bs-secondary-color); }
.invoice-description table { width: 100%; border-collapse: collapse; }
.invoice-description :is(th, td) { border: 1px solid var(--invoice-border); padding: 8px 10px; }
.payment-link-box { display: flex; gap: 12px; justify-content: space-between; align-items: center; border: 1px solid rgba(13,110,253,.18); border-radius: 16px; padding: 14px 16px; background: rgba(13,110,253,.08); }
.payment-link-box div { min-width: 0; display: grid; gap: 2px; }
.payment-link-box span { color: var(--bs-secondary-color); overflow-wrap: anywhere; }
.dispatch-list { display: grid; }
.dispatch-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--invoice-border); }
.dispatch-item:last-child { border-bottom: 0; }
.dispatch-item div { display: grid; gap: 2px; min-width: 0; }
.dispatch-item small { color: var(--bs-secondary-color); }
.empty-state { min-height: 132px; display: grid; place-items: center; gap: 8px; text-align: center; color: var(--bs-secondary-color); padding: 22px; }
.empty-state.compact { min-height: 112px; border: 1px dashed var(--invoice-border); border-radius: 16px; }
.empty-state i { font-size: 2rem; color: var(--bs-primary); }
[data-bs-theme="dark"] .metric-card, [data-bs-theme="dark"] .premium-panel { background: rgba(17,24,39,.88); }
@media (max-width: 767.98px) { .invoice-hero, .panel-heading, .payment-link-box { flex-direction: column; align-items: stretch; } .hero-actions { justify-content: flex-start; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const button = document.getElementById('btn-send-now');
    if (!button) return;

    button.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'question',
            title: 'Enviar cobranca via WhatsApp?',
            text: 'A mensagem sera enviada imediatamente para o cliente desta fatura.',
            showCancelButton: true,
            confirmButtonText: 'Enviar agora',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#64748b',
            background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
            color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
        });
        if (!ok.isConfirmed) return;

        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
        try {
            const response = await fetch(`{{ route('admin.billing.invoices.send', $invoice->id) }}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.ok) {
                window.toast?.success(data.message || 'Cobranca enviada com sucesso!');
            } else {
                window.toast?.error(data.error || 'Falha no envio da cobranca.');
            }
        } catch (error) {
            window.toast?.error('Erro de rede ao enviar.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });

    // Baixa Manual
    const btnSettle = document.getElementById('btn-settle-invoice');
    const settleModalEl = document.getElementById('modal-settle-invoice');
    const settleModal = settleModalEl ? new bootstrap.Modal(settleModalEl) : null;
    let currentSettleContext = null;
    const baseAmount = {{ (float) $invoice->amount }};

    if (btnSettle && settleModal) {
        btnSettle.addEventListener('click', async () => {
            document.getElementById('radio-mode-single').checked = true;
            document.getElementById('settle-paid-at').value = new Date().toISOString().split('T')[0];
            document.getElementById('settle-notes').value = '';

            const section = document.getElementById('settle-installment-section');
            section.classList.add('d-none');
            document.getElementById('container-mode-antecipated').classList.add('d-none');
            document.getElementById('settle-custom-installments-list').classList.add('d-none');
            document.getElementById('settle-custom-installments-list').innerHTML = '';

            updateSettleModeUI();
            settleModal.show();

            try {
                const resp = await fetch(`{{ route('admin.billing.invoices.installment-context', $invoice->id) }}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                if (resp.ok && data.ok && data.context) {
                    currentSettleContext = data.context;

                    if (data.context.is_installment && data.context.open_count > 0) {
                        section.classList.remove('d-none');

                        const instBadge = document.getElementById('settle-badge-installments');
                        if (data.context.installment_number && data.context.total_installments) {
                            instBadge.textContent = `Parcela ${data.context.installment_number} de ${data.context.total_installments}`;
                        } else {
                            instBadge.textContent = `${data.context.open_count + 1} parcelas vinculadas`;
                        }

                        if (data.context.antecipated_count > 0) {
                            const antContainer = document.getElementById('container-mode-antecipated');
                            antContainer.classList.remove('d-none');
                            document.getElementById('settle-antecipated-count').textContent = data.context.antecipated_count;
                            const combinedAnt = baseAmount + data.context.total_antecipated_amount;
                            document.getElementById('settle-antecipated-total').textContent = 'R$ ' + combinedAnt.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }

                        document.getElementById('settle-all-count').textContent = data.context.open_count + 1;
                        const combinedAll = baseAmount + data.context.total_open_amount;
                        document.getElementById('settle-all-total').textContent = 'R$ ' + combinedAll.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        const listEl = document.getElementById('settle-custom-installments-list');
                        listEl.innerHTML = '';
                        data.context.open_installments.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'form-check py-1 border-bottom';
                            div.innerHTML = `
                                <input class="form-check-input settle-custom-cb" type="checkbox" value="${item.id}" id="cb-inst-${item.id}" data-amount="${item.amount}" onchange="updateSettleModeUI()">
                                <label class="form-check-label d-flex justify-content-between align-items-center w-100 pe-2" for="cb-inst-${item.id}">
                                    <span>
                                        <strong>#${item.number}</strong>
                                        ${item.installment ? `<span class="badge bg-secondary-subtle text-secondary small ms-1">${item.installment}ª</span>` : ''}
                                        ${item.is_antecipated ? `<span class="badge bg-info-subtle text-info small ms-1"><i class="bi bi-lightning-charge"></i> Antecipada</span>` : ''}
                                        <span class="text-muted ms-1">(Venc: ${item.formatted_due_date || item.due_date})</span>
                                    </span>
                                    <span class="fw-bold text-primary">R$ ${parseFloat(item.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </label>
                            `;
                            listEl.appendChild(div);
                        });
                    }
                }
            } catch (e) {
                console.warn('Erro ao carregar contexto de parcelas:', e);
            }
        });
    }

    window.updateSettleModeUI = function () {
        const radios = document.getElementsByName('settle_radio_mode');
        let selectedMode = 'single';
        for (const r of radios) {
            if (r.checked) {
                selectedMode = r.value;
                break;
            }
        }
        document.getElementById('settle-mode').value = selectedMode;

        const customList = document.getElementById('settle-custom-installments-list');
        if (selectedMode === 'selected') {
            customList.classList.remove('d-none');
        } else {
            customList.classList.add('d-none');
        }

        let finalAmount = baseAmount;
        if (currentSettleContext) {
            if (selectedMode === 'antecipated') {
                finalAmount += currentSettleContext.total_antecipated_amount || 0;
            } else if (selectedMode === 'all_open') {
                finalAmount += currentSettleContext.total_open_amount || 0;
            } else if (selectedMode === 'selected') {
                const checkedBoxes = document.querySelectorAll('.settle-custom-cb:checked');
                checkedBoxes.forEach(cb => {
                    finalAmount += parseFloat(cb.dataset.amount || 0);
                });
            }
        }

        document.getElementById('settle-final-amount').textContent = 'R$ ' + finalAmount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const frmSettle = document.getElementById('frm-settle-invoice');
    if (frmSettle) {
        frmSettle.addEventListener('submit', async (e) => {
            e.preventDefault();

            const settleMode = document.getElementById('settle-mode').value;
            const gateway = document.getElementById('settle-gateway').value;
            const paidAt = document.getElementById('settle-paid-at').value;
            const notes = document.getElementById('settle-notes').value;
            const notifyWhatsApp = document.getElementById('settle-notify-whatsapp').checked;

            let selectedIds = [];
            if (settleMode === 'selected') {
                const checked = document.querySelectorAll('.settle-custom-cb:checked');
                selectedIds = Array.from(checked).map(c => parseInt(c.value));
                if (selectedIds.length === 0) {
                    Swal.fire('Atenção', 'Selecione ao menos uma parcela na lista personalizada.', 'warning');
                    return;
                }
            }

            const confirm = await Swal.fire({
                icon: 'question',
                title: 'Confirmar Baixa Manual?',
                html: `Deseja dar baixa e marcar como <strong>Paga</strong> esta fatura? <br><small class="text-muted">Valor a liquidar: ${document.getElementById('settle-final-amount').textContent}</small>`,
                showCancelButton: true,
                confirmButtonText: 'Sim, confirmar baixa',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d'
            });

            if (!confirm.isConfirmed) return;

            const btn = document.getElementById('btn-confirm-settle');
            const btnText = document.getElementById('btn-settle-text');
            btn.disabled = true;
            btnText.textContent = 'Processando...';

            try {
                const resp = await fetch(`{{ route('admin.billing.invoices.settle', $invoice->id) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        gateway: gateway,
                        paid_at: paidAt,
                        notes: notes,
                        notify_customer: notifyWhatsApp,
                        settle_mode: settleMode,
                        selected_ids: selectedIds
                    })
                });

                const data = await resp.json();
                if (resp.ok && data.ok) {
                    settleModal.hide();
                    if (window.toast) {
                        window.toast.success(data.message || 'Baixa realizada com sucesso!');
                    }
                    await Swal.fire({
                        icon: 'success',
                        title: 'Baixa Confirmada!',
                        text: data.message || 'Fatura baixada com sucesso.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    location.reload();
                } else {
                    Swal.fire('Aviso', data.error || data.message || 'Não foi possível concluir a baixa.', 'warning');
                }
            } catch (err) {
                Swal.fire('Erro', 'Falha ao conectar ao servidor.', 'error');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'Confirmar Baixa';
            }
        });
    }
})();
</script>
@endpush
