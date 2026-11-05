@extends('tenancy::tenant.layout')

@section('title', 'Envios agendados')

@push('head')
<style>
.billing-dispatches-page .form-control,
.billing-dispatches-page .form-select {
    min-height: 46px;
}
.billing-dispatches-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-dispatches-page">
    <x-ui.page-header
        title="Envios agendados"
        subtitle="Histórico e fila de disparos automáticos de cobrança via WhatsApp conforme o cronograma definido para cada fatura."
        eyebrow="Automação de cobrança">
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total de registros</span>
                <span class="dashboard-note__value">{{ number_format($dispatches->total(), 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Falhas</span>
                <span class="dashboard-note__value text-danger">{{ number_format($stats->failed ?? 0, 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total de envios" :value="number_format($stats->total ?? 0, 0, ',', '.')" icon="bi-send" color="primary">
                <x-slot:meta>Todos os registros de disparo gerados pela cobrança.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Enviados" :value="number_format($stats->sent ?? 0, 0, ',', '.')" icon="bi-check-circle" color="success">
                <x-slot:meta>Disparos concluídos com sucesso.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Agendados" :value="number_format($stats->scheduled ?? 0, 0, ',', '.')" icon="bi-clock" color="info">
                <x-slot:meta>Itens ainda aguardando a janela de execução.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Falhas" :value="number_format($stats->failed ?? 0, 0, ',', '.')" icon="bi-x-circle" color="danger">
                <x-slot:meta>Eventos que precisam de revisão operacional.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card title="Filtros de histórico" subtitle="Pesquise por cliente, fatura ou status." class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label">Buscar</label>
                <input type="search" name="q" class="form-control" placeholder="Número da fatura ou nome do cliente" value="{{ request('q') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>Agendado</option>
                    <option value="sending" @selected(request('status') === 'sending')>Enviando</option>
                    <option value="sent" @selected(request('status') === 'sent')>Enviado</option>
                    <option value="failed" @selected(request('status') === 'failed')>Falhou</option>
                    <option value="canceled" @selected(request('status') === 'canceled')>Cancelado</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                @if(request()->hasAny(['q','status']))
                    <a href="{{ route('tenant.dispatches') }}" class="btn btn-outline-secondary" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

@php
        $byCustomer = $dispatches->getCollection()->groupBy(fn($d) => $d->invoice?->customer_id ?? 'sem-cliente');
        $statusMap = [
            'scheduled' => ['tone' => 'inactive', 'label' => 'Agendado'],
            'sending' => ['tone' => 'sandbox', 'label' => 'Enviando'],
            'sent' => ['tone' => 'active', 'label' => 'Enviado'],
            'failed' => ['tone' => 'overdue', 'label' => 'Falhou'],
            'canceled' => ['tone' => 'secondary', 'label' => 'Cancelado'],
        ];
    @endphp

    <x-ui.premium-card title="Envios por cliente" :subtitle="$dispatches->total() . ' registro(s) em ' . $byCustomer->count() . ' cliente(s).'">
        <div class="row g-3">
            @forelse($byCustomer as $customerId => $items)
                @php
                    $first = $items->first();
                    $customer = $first->invoice?->customer;
                    $scheduled = $items->where('status', 'scheduled')->count();
                    $sent = $items->where('status', 'sent')->count();
                    $failed = $items->where('status', 'failed')->count();
                    $total = $items->count();
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm cursor-pointer js-open-dispatch-modal" role="button"
                         data-customer="{{ $customer?->name ?? 'Cliente não informado' }}"
                         data-whatsapp="{{ \App\Support\Format::phone($customer?->whatsapp) ?? 'Sem WhatsApp' }}"
                         data-dispatches="{{ $items->map(fn($d) => [
                             'number' => $d->invoice?->number ?? '#'.$d->invoice_id,
                             'invoice_id' => $d->invoice_id,
                             'offset' => $d->schedule_offset ?: 'Manual',
                             'scheduled_at' => $d->scheduled_at ? \App\Support\Format::datetime($d->scheduled_at) : 'Não agendado',
                             'status_tone' => ($statusMap[$d->status] ?? ['tone' => 'secondary'])['tone'],
                             'status_label' => ($statusMap[$d->status] ?? ['label' => ucfirst((string)$d->status)])['label'],
                             'attempts' => $d->attempts,
                         ])->values()->toJson() }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ $customer?->name ?? 'Cliente não informado' }}</h6>
                                    <small class="text-muted">{{ \App\Support\Format::phone($customer?->whatsapp) ?? 'Sem WhatsApp' }}</small>
                                </div>
                                <span class="badge bg-primary">{{ $total }}</span>
                            </div>
                            <div class="d-flex gap-2 small">
                                @if($sent > 0)
                                    <span class="text-success"><i class="bi bi-check-circle me-1"></i>{{ $sent }} enviado(s)</span>
                                @endif
                                @if($scheduled > 0)
                                    <span class="text-info"><i class="bi bi-clock me-1"></i>{{ $scheduled }} agendado(s)</span>
                                @endif
                                @if($failed > 0)
                                    <span class="text-danger"><i class="bi bi-x-circle me-1"></i>{{ $failed }} falha(s)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <x-ui.empty-state icon="bi-send" title="Nenhum envio agendado" message="Quando as faturas gerarem disparos automáticos, o histórico aparecerá aqui." />
                </div>
            @endforelse
        </div>

        @if($dispatches->hasPages())
            <div class="mt-3">{{ $dispatches->links() }}</div>
        @endif
    </x-ui.premium-card>
</div>

<!-- Modal Detalhes do Cliente -->
<div class="modal fade" id="modal-dispatch-details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-person me-2 text-primary"></i><span id="md-customer"></span></h5>
                    <small class="text-muted" id="md-whatsapp"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div>
                    <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" data-export-title="Disparos">
                        <thead class="table-light">
                            <tr>
                                <th>Fatura</th>
                                <th>Tipo</th>
                                <th>Agendado para</th>
                                <th>Status</th>
                                <th class="text-center">Tent.</th>
                            </tr>
                        </thead>
                        <tbody id="md-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const mdModal = new bootstrap.Modal(document.getElementById('modal-dispatch-details'));
const toneBadge = {
    active: 'bg-success',
    inactive: 'bg-info',
    sandbox: 'bg-warning text-dark',
    overdue: 'bg-danger',
    secondary: 'bg-secondary',
};

document.querySelectorAll('.js-open-dispatch-modal').forEach(card => {
    card.addEventListener('click', () => {
        document.getElementById('md-customer').textContent = card.dataset.customer;
        document.getElementById('md-whatsapp').textContent = card.dataset.whatsapp;
        const items = JSON.parse(card.dataset.dispatches || '[]');
        const tbody = document.getElementById('md-body');
        tbody.innerHTML = items.map(item => `
            <tr>
                <td><a href="{{ route('tenant.invoices') }}?id=${item.invoice_id}" class="fw-semibold text-decoration-none">${item.number}</a></td>
                <td><span class="badge bg-secondary">${item.offset}</span></td>
                <td>${item.scheduled_at}</td>
                <td><span class="badge ${toneBadge[item.status_tone] || 'bg-secondary'}">${item.status_label}</span></td>
                <td class="text-center"><span class="badge text-bg-light border">${item.attempts}</span></td>
            </tr>
        `).join('');
        mdModal.show();
    });
});
</script>
@endpush
