@extends('admin.layouts.app')

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
                    <a href="{{ route('admin.billing.dispatches.index') }}" class="btn btn-outline-secondary" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    <x-ui.premium-card title="Histórico de envios" :subtitle="$dispatches->total() . ' registro(s) encontrados.'">
        <div class="billing-dispatches-table p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-dispatches" data-export-title="Disparos e Envios" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Cliente</th>
                        <th class="all" data-priority="1">Fatura</th>
                        <th class="desktop">Tipo de disparo</th>
                        <th class="min-tablet">Agendado para</th>
                        <th class="min-tablet" data-priority="2">Status</th>
                        <th class="desktop text-center">Tentativas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dispatches as $d)
                        @php
                            $statusMap = [
                                'scheduled' => ['tone' => 'inactive', 'label' => 'Agendado'],
                                'sending' => ['tone' => 'sandbox', 'label' => 'Enviando'],
                                'sent' => ['tone' => 'active', 'label' => 'Enviado'],
                                'failed' => ['tone' => 'overdue', 'label' => 'Falhou'],
                                'canceled' => ['tone' => 'secondary', 'label' => 'Cancelado'],
                            ];
                            $st = $statusMap[$d->status] ?? ['tone' => 'secondary', 'label' => ucfirst((string) $d->status)];
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $d->invoice?->customer?->name ?? 'Cliente não informado' }}</div>
                                <div class="small text-muted">{{ \App\Support\Format::phone($d->invoice?->customer?->whatsapp) ?: 'Sem WhatsApp' }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.billing.invoices.show', $d->invoice_id) }}" class="text-decoration-none fw-semibold">
                                    {{ $d->invoice?->number ?? '#'.$d->invoice_id }}
                                </a>
                            </td>
                            <td>
                                <x-ui.status-badge status="sandbox" :label="$d->schedule_offset ?: 'Manual'" />
                            </td>
                            <td>
                                @if($d->scheduled_at)
                                    <div class="fw-semibold">{{ \App\Support\Format::datetime($d->scheduled_at) }}</div>
                                    <div class="small text-muted">{{ \Illuminate\Support\Carbon::parse($d->scheduled_at)->diffForHumans() }}</div>
                                @else
                                    <span class="text-muted">Não agendado</span>
                                @endif
                            </td>
                            <td>
                                <x-ui.status-badge :status="$st['tone']" :label="$st['label']" />
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border fs-6">{{ $d->attempts }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-send fs-1 d-block mb-2 opacity-50"></i>
                                Nenhum envio agendado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($dispatches instanceof \Illuminate\Pagination\LengthAwarePaginator && $dispatches->hasPages())
            <div class="card-footer py-2 mt-3 bg-transparent border-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">Mostrando {{ $dispatches->firstItem() }} a {{ $dispatches->lastItem() }} de {{ $dispatches->total() }} registros</small>
                    {{ $dispatches->links() }}
                </div>
            </div>
        @endif
    </x-ui.premium-card>
</div>
@endsection
