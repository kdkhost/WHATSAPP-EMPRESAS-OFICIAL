@extends('tenancy::tenant.layout')

@section('title', 'Relatorios de Cobrancas')

@push('head')
<style>
.reports-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 150px);
    overflow: hidden;
}
.reports-hero { flex-shrink: 0; }
.reports-filters { flex-shrink: 0; }
.reports-kpis { flex-shrink: 0; }
.reports-data {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding-right: .25rem;
}
.reports-data::-webkit-scrollbar { width: 6px; }
.reports-data::-webkit-scrollbar-thumb {
    background: rgba(148,163,184,.35);
    border-radius: 4px;
}
.kpi-card {
    border: 1px solid var(--app-border-soft, rgba(148,163,184,.18));
    border-radius: 16px;
    padding: .875rem;
    background: linear-gradient(135deg, rgba(13,110,253,.05), rgba(255,255,255,0));
    transition: transform .15s ease, box-shadow .15s ease;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.kpi-card__value {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -.02em;
}
.kpi-card__label {
    font-size: .78rem;
    color: var(--bs-secondary-color, #64748b);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-top: .35rem;
}
.kpi-card--whatsapp { border-left: 4px solid #20c997; }
.kpi-card--whatsapp-fail { border-left: 4px solid #dc3545; }
.kpi-card--email { border-left: 4px solid #0dcaf0; }
.kpi-card--email-fail { border-left: 4px solid #fd7e14; }
.kpi-card--disparos { border-left: 4px solid #6f42c1; }
.kpi-card--cobrancas { border-left: 4px solid #0d6efd; }
.filter-bar {
    border: 1px solid var(--app-border-soft, rgba(148,163,184,.18));
    border-radius: 14px;
    padding: .6rem .9rem;
    background: var(--bs-body-bg, #fff);
}
.customer-card {
    border: 1px solid var(--app-border-soft, rgba(148,163,184,.18));
    border-radius: 14px;
    padding: .875rem 1rem;
    background: var(--bs-body-bg, #fff);
    cursor: pointer;
    transition: all .12s ease;
}
.customer-card:hover {
    border-color: rgba(13,110,253,.35);
    box-shadow: 0 4px 16px rgba(13,110,253,.08);
}
.customer-card__meta {
    font-size: .82rem;
    color: var(--bs-secondary-color, #64748b);
}
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 260px;
    color: var(--bs-secondary-color, #64748b);
}
@media (max-width: 768px) {
    .reports-page { height: calc(100vh - 130px); }
    .kpi-card__value { font-size: 1.2rem; }
    .kpi-card__label { font-size: .7rem; }
}
</style>
@endpush

@section('content')
@php
    use App\Support\Format;
    $statuses = ['pending', 'sent', 'viewed', 'paid', 'overdue', 'canceled', 'refunded'];
@endphp

<div class="container-fluid py-2 reports-page">
    <div class="reports-hero mb-2">
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-graph-up me-2"></i>Analise de performance</span>
        <h1 class="tenant-premium-hero__title mb-1">Relatorio de Cobrancas</h1>
        <p class="text-muted mb-0 small">Acompanhe faturas, disparos e taxas de entrega em tempo real.</p>
    </div>

    <div class="reports-kpis row g-2 mb-2">
        <div class="col-4 col-md-2">
            <div class="kpi-card kpi-card--cobrancas h-100">
                <div class="kpi-card__value text-primary">{{ $stats['total_invoices'] }}</div>
                <div class="kpi-card__label">Cobrancas</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-card kpi-card--disparos h-100">
                <div class="kpi-card__value text-info">{{ $stats['total_dispatches'] }}</div>
                <div class="kpi-card__label">Disparos</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-card kpi-card--whatsapp h-100">
                <div class="kpi-card__value text-success">{{ $stats['whatsapp_sent'] }}</div>
                <div class="kpi-card__label">WhatsApp OK</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-card kpi-card--whatsapp-fail h-100">
                <div class="kpi-card__value text-danger">{{ $stats['whatsapp_failed'] }}</div>
                <div class="kpi-card__label">WhatsApp Falha</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-card kpi-card--email h-100">
                <div class="kpi-card__value text-info">{{ $stats['email_sent'] }}</div>
                <div class="kpi-card__label">Email OK</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-card kpi-card--email-fail h-100">
                <div class="kpi-card__value text-warning">{{ $stats['email_failed'] }}</div>
                <div class="kpi-card__label">Email Falha</div>
            </div>
        </div>
    </div>

    <div class="reports-filters filter-bar mb-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1 text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1 text-muted">De</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1 text-muted">Ate</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-6 col-md-auto flex-grow-1">
                <label class="form-label small mb-1 text-muted">Buscar</label>
                <input type="text" name="search" class="form-control form-select-sm" placeholder="Cliente, numero..." value="{{ request('search') }}">
            </div>
            <div class="col-12 col-md-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="{{ route('tenant.reports') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Limpar</a>
            </div>
        </form>
    </div>

    <div class="reports-data">
        @if($invoices->isEmpty())
            <div class="empty-state">
                <i class="bi bi-inbox display-4 mb-3"></i>
                <p class="mb-0">Nenhuma cobranca encontrada.</p>
            </div>
        @else
            @php
                $statusMap = [
                    'pending' => ['bg' => 'warning', 'label' => 'Pendente'],
                    'sent' => ['bg' => 'info', 'label' => 'Enviada'],
                    'viewed' => ['bg' => 'primary', 'label' => 'Visualizada'],
                    'paid' => ['bg' => 'success', 'label' => 'Paga'],
                    'overdue' => ['bg' => 'danger', 'label' => 'Vencida'],
                    'canceled' => ['bg' => 'secondary', 'label' => 'Cancelada'],
                    'refunded' => ['bg' => 'dark', 'label' => 'Estornada'],
                ];
                $byCustomer = $invoices->getCollection()->groupBy(fn($i) => $i->customer_id ?? 'sem-cliente');
            @endphp
            <div class="row g-2">
                @foreach($byCustomer as $customerId => $items)
                    @php
                        $first = $items->first();
                        $customer = $first->customer;
                        $total = $items->count();
                        $paid = $items->where('status', 'paid')->count();
                        $pending = $items->whereIn('status', ['pending', 'sent', 'viewed'])->count();
                        $overdue = $items->where('status', 'overdue')->count();
                        $totalAmount = $items->sum('amount');
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="customer-card h-100 js-open-report-modal"
                             data-customer="{{ $customer?->name ?? 'Cliente nao informado' }}"
                             data-whatsapp="{{ \App\Support\Format::phone($customer?->whatsapp) ?? 'Sem WhatsApp' }}"
                             data-invoices="{{ $items->map(function($inv) use ($statusMap, $messageStats) {
                                 $ms = $messageStats[$inv->id] ?? ['wa_sent'=>0,'wa_failed'=>0,'email_sent'=>0,'email_failed'=>0];
                                 $st = $statusMap[$inv->status] ?? ['bg' => 'secondary', 'label' => ucfirst($inv->status)];
                                 return [
                                     'number' => $inv->number,
                                     'amount' => 'R$ ' . number_format($inv->amount, 2, ',', '.'),
                                     'due_date' => $inv->due_date?->format('d/m/Y') ?? '-',
                                     'status_bg' => $st['bg'],
                                     'status_label' => $st['label'],
                                     'wa_sent' => (int) $ms['wa_sent'],
                                     'wa_failed' => (int) $ms['wa_failed'],
                                     'email_sent' => (int) $ms['email_sent'],
                                     'email_failed' => (int) $ms['email_failed'],
                                     'date' => $inv->created_at->format('d/m/Y H:i'),
                                 ];
                             })->values()->toJson() }}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="min-w-0">
                                    <h6 class="mb-0 fw-bold text-truncate">{{ $customer?->name ?? 'Cliente nao informado' }}</h6>
                                    <div class="customer-card__meta text-truncate">{{ \App\Support\Format::phone($customer?->whatsapp) ?? 'Sem WhatsApp' }}</div>
                                </div>
                                <span class="badge bg-primary rounded-pill">{{ $total }}</span>
                            </div>
                            <div class="d-flex gap-2 small mb-2 flex-wrap">
                                @if($paid > 0)
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>{{ $paid }} paga(s)</span>
                                @endif
                                @if($pending > 0)
                                    <span class="badge bg-warning-subtle text-warning"><i class="bi bi-clock me-1"></i>{{ $pending }} pend.</span>
                                @endif
                                @if($overdue > 0)
                                    <span class="badge bg-danger-subtle text-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ $overdue }} venc.</span>
                                @endif
                            </div>
                            <div class="fw-bold text-primary small">R$ {{ number_format($totalAmount, 2, ',', '.') }} total</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 d-flex justify-content-center">
                {{ $invoices->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="modal-report-details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-person me-2 text-primary"></i><span id="mr-customer"></span></h5>
                    <small class="text-muted" id="mr-whatsapp"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fatura</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>WhatsApp</th>
                                <th>Email</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody id="mr-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const mrModal = new bootstrap.Modal(document.getElementById('modal-report-details'));

document.querySelectorAll('.js-open-report-modal').forEach(card => {
    card.addEventListener('click', () => {
        document.getElementById('mr-customer').textContent = card.dataset.customer;
        document.getElementById('mr-whatsapp').textContent = card.dataset.whatsapp;
        const items = JSON.parse(card.dataset.invoices || '[]');
        const tbody = document.getElementById('mr-body');
        tbody.innerHTML = items.map(item => {
            let wa = item.wa_sent > 0 ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' + item.wa_sent + '</span>' : '';
            if (item.wa_failed > 0) wa += ' <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>' + item.wa_failed + '</span>';
            let em = item.email_sent > 0 ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' + item.email_sent + '</span>' : '';
            if (item.email_failed > 0) em += ' <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>' + item.email_failed + '</span>';
            return '<tr>' +
                '<td class="text-muted small">' + item.number + '</td>' +
                '<td class="fw-semibold small">' + item.amount + '</td>' +
                '<td class="small">' + item.due_date + '</td>' +
                '<td><span class="badge text-bg-' + item.status_bg + '">' + item.status_label + '</span></td>' +
                '<td class="small">' + (wa || '<span class="text-muted">-</span>') + '</td>' +
                '<td class="small">' + (em || '<span class="text-muted">-</span>') + '</td>' +
                '<td class="small text-muted">' + item.date + '</td>' +
            '</tr>';
        }).join('');
        mrModal.show();
    });
});
</script>
@endpush
