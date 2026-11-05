@extends('admin.layouts.app')

@section('title', 'Faturas')

@push('head')
<style>
.billing-invoices-page .form-control,
.billing-invoices-page .form-select {
    min-height: 46px;
}
.billing-invoices-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
}
.billing-invoices-table tbody td {
    vertical-align: middle;
}
.billing-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .76rem;
    font-weight: 700;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--bs-primary) 15%, var(--bs-body-bg));
    color: var(--bs-primary);
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-invoices-page">
    <x-ui.page-header
        title="Faturas"
        subtitle="Controle financeiro central com busca rápida, disparo manual por WhatsApp e leitura operacional do status de cobrança."
        eyebrow="Cobrança e faturamento">
        <x-slot:actions>
            <a href="{{ route('admin.billing.invoices.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nova fatura
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Receita recebida</span>
                <span class="dashboard-note__value text-success">{{ \App\Support\Format::money($stats->revenue ?? 0) }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Registros</span>
                <span class="dashboard-note__value">{{ number_format($invoices->total(), 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total de faturas" :value="number_format($stats->total ?? 0, 0, ',', '.')" icon="bi-receipt" color="primary">
                <x-slot:meta>Base consolidada de cobranças emitidas.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Pagas" :value="number_format($stats->paid ?? 0, 0, ',', '.')" icon="bi-check-circle" color="success">
                <x-slot:meta>Faturas liquidadas com confirmação registrada.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Pendentes" :value="number_format($stats->pending ?? 0, 0, ',', '.')" icon="bi-hourglass-split" color="warning">
                <x-slot:meta>Cobranças aguardando pagamento ou envio.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Vencidas" :value="number_format($stats->overdue ?? 0, 0, ',', '.')" icon="bi-exclamation-triangle" color="danger">
                <x-slot:meta>Títulos que exigem ação comercial ou reenvio.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card title="Filtros de faturamento" subtitle="Busque por número, cliente, status ou gateway." class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Buscar</label>
                <input type="search" name="q" class="form-control" placeholder="Número da fatura ou nome do cliente" value="{{ request('q') }}">
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pendente</option>
                    <option value="sent" @selected(request('status') === 'sent')>Enviada</option>
                    <option value="viewed" @selected(request('status') === 'viewed')>Visualizada</option>
                    <option value="paid" @selected(request('status') === 'paid')>Paga</option>
                    <option value="overdue" @selected(request('status') === 'overdue')>Vencida</option>
                    <option value="canceled" @selected(request('status') === 'canceled')>Cancelada</option>
                    <option value="refunded" @selected(request('status') === 'refunded')>Reembolsada</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Gateway</label>
                <select name="gateway" class="form-select">
                    <option value="">Todos os gateways</option>
                    <option value="mercadopago" @selected(request('gateway') === 'mercadopago')>Mercado Pago</option>
                    <option value="efipro" @selected(request('gateway') === 'efipro')>Efi Pro</option>
                    <option value="sumup" @selected(request('gateway') === 'sumup')>SumUp</option>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                @if(request()->hasAny(['q', 'status', 'gateway']))
                    <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-outline-secondary" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    <x-ui.premium-card title="Lista de faturas" :subtitle="$invoices->total() . ' registro(s) encontrados.'">
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if(($viewMode ?? 'grouped') === 'grouped')
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-toggle-all-carnes" title="Expandir ou recolher todas as parcelas">
                        <i class="bi bi-arrows-expand me-1"></i><span id="btn-toggle-all-text">Expandir Carnês</span>
                    </button>
                @endif
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'grouped']) }}" 
                       class="btn {{ ($viewMode ?? 'grouped') === 'grouped' ? 'btn-primary' : 'btn-outline-secondary' }}"
                       title="Agrupar faturas de mesmo cliente/carnê">
                        <i class="bi bi-collection me-1"></i> Modo Carnês
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'individual']) }}" 
                       class="btn {{ ($viewMode ?? 'grouped') === 'individual' ? 'btn-primary' : 'btn-outline-secondary' }}"
                       title="Visualizar todas as parcelas avulsas">
                        <i class="bi bi-list-ul me-1"></i> Modo Individual
                    </a>
                </div>
            </div>
        </x-slot:actions>
        <div class="billing-invoices-table p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-invoices" data-export-title="Faturas de Clientes" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Número / Lote</th>
                        <th class="all" data-priority="1">Cliente</th>
                        <th class="desktop">Descrição & Progresso</th>
                        <th class="min-tablet text-end" data-priority="2">Valor</th>
                        <th class="min-tablet">Vencimento</th>
                        <th class="desktop">Gateway</th>
                        <th class="min-tablet" data-priority="2">Status</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 140px; min-width: 130px; max-width: 160px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $statusMap = [
                            'pending' => ['tone' => 'pending', 'icon' => 'bi-hourglass-split', 'label' => 'Pendente'],
                            'sent' => ['tone' => 'production', 'icon' => 'bi-send', 'label' => 'Enviada'],
                            'viewed' => ['tone' => 'sandbox', 'icon' => 'bi-eye', 'label' => 'Visualizada'],
                            'paid' => ['tone' => 'active', 'icon' => 'bi-check-circle', 'label' => 'Paga'],
                            'overdue' => ['tone' => 'overdue', 'icon' => 'bi-exclamation-triangle', 'label' => 'Vencida'],
                            'canceled' => ['tone' => 'inactive', 'icon' => 'bi-x-circle', 'label' => 'Cancelada'],
                            'refunded' => ['tone' => 'secondary', 'icon' => 'bi-arrow-counterclockwise', 'label' => 'Reembolsada'],
                        ];
                        $gatewayMap = [
                            'mercadopago' => ['label' => 'Mercado Pago', 'tone' => 'production'],
                            'efipro' => ['label' => 'Efi Pro', 'tone' => 'sandbox'],
                            'sumup' => ['label' => 'SumUp', 'tone' => 'active'],
                        ];
                        $rowsToRender = isset($displayRows) && count($displayRows) > 0 ? $displayRows : $invoices->map(fn($item) => (object)['type' => 'single', 'invoice' => $item]);
                    @endphp

                    @forelse($rowsToRender as $row)
                        @if($row->type === 'carne')
                            {{-- LINHA MESTRA DO CARNÊ AGRUPADO --}}
                            @php
                                $cust = $row->customer;
                                $custName = $cust?->name ?? 'Cliente não informado';
                                $custInitials = collect(explode(' ', $custName))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
                            @endphp
                            <tr class="carne-group-row table-primary-subtle" id="carne-row-{{ $row->batch_id }}" style="background: rgba(13, 110, 253, 0.05);">
                                <td>
                                    <span class="badge border fw-bold text-dark" style="background: #ede9fe; color: #6d28d9 !important; border-color: #ddd6fe !important;">
                                        <i class="bi bi-collection me-1"></i>Carnê {{ $row->total_installments }}x
                                    </span>
                                    <div class="small text-muted font-monospace mt-1" title="Lote: {{ $row->batch_id }}">
                                        Lote #{{ strtoupper(substr($row->batch_id, 0, 8)) }}
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="billing-avatar" style="background: rgba(109, 40, 217, 0.15); color: #6d28d9;">
                                            {{ strtoupper($custInitials ?: '?') }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate" style="max-width: 180px;">{{ $custName }}</div>
                                            <div class="small text-muted">{{ \App\Support\Format::phone($cust?->whatsapp) ?: 'Sem WhatsApp' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 240px;" title="{{ $row->description }}">{{ $row->description }}</div>
                                    <div class="progress mt-1" style="height: 6px; max-width: 180px;" title="{{ $row->paid_count }} de {{ $row->total_installments }} parcelas pagas">
                                        <div class="progress-bar bg-success" style="width: {{ $row->progress_percent }}%;"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">
                                        <strong>{{ $row->paid_count }}/{{ $row->total_installments }}</strong> pagas ({{ $row->progress_percent }}%)
                                    </small>
                                </td>
                                <td class="text-end fw-bold">
                                    <div class="text-dark">{{ \App\Support\Format::money($row->total_amount) }}</div>
                                    <small class="text-muted fw-normal d-block" style="font-size: 0.75rem;">{{ $row->total_installments }}x de {{ \App\Support\Format::money($row->installment_amount) }}</small>
                                </td>
                                <td>
                                    @if($row->next_due_invoice)
                                        <div class="small fw-semibold {{ $row->next_due_invoice->status === 'overdue' ? 'text-danger' : 'text-dark' }}">
                                            Próx: {{ \App\Support\Format::date($row->next_due_invoice->due_date) }}
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 0.74rem;">Parcela {{ $row->next_due_invoice->meta['installment'] ?? '1' }}/{{ $row->total_installments }}</small>
                                    @else
                                        <span class="badge bg-success-subtle text-success small"><i class="bi bi-check-all me-1"></i>Quitado</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-body border small">Carnê</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if($row->overdue_count > 0)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle small text-start">
                                                <i class="bi bi-exclamation-triangle me-1"></i>{{ $row->overdue_count }} Vencida(s)
                                            </span>
                                        @endif
                                        @if($row->pending_count > 0)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle small text-start">
                                                <i class="bi bi-hourglass-split me-1"></i>{{ $row->pending_count }} Pendente(s)
                                            </span>
                                        @endif
                                        @if($row->paid_count > 0)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle small text-start">
                                                <i class="bi bi-check-circle me-1"></i>{{ $row->paid_count }} Paga(s)
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="all text-end no-export" style="width: 140px; min-width: 130px; max-width: 160px; white-space: nowrap;">
                                    <button type="button" class="btn btn-sm btn-primary py-1 px-2 btn-toggle-admin-carne shadow-sm" data-batch-id="{{ $row->batch_id }}" title="Abrir e ver todas as parcelas deste carnê">
                                        <i class="bi bi-chevron-down me-1" id="chevron-{{ $row->batch_id }}"></i><span id="btn-text-{{ $row->batch_id }}">Ver {{ $row->total_installments }}x</span>
                                    </button>
                                </td>
                            </tr>

                            {{-- GAVETA EXPANSÍVEL COM AS PARCELAS DO CARNÊ --}}
                            <tr class="carne-details-row" id="carne-details-{{ $row->batch_id }}" style="display: none; background: #f8fafc;">
                                <td colspan="8" class="p-3">
                                    <div class="card border shadow-sm">
                                        <div class="card-header bg-body-tertiary py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="small fw-bold text-dark">
                                                <i class="bi bi-collection me-1 text-primary"></i>Parcelas do Carnê ({{ $row->description }})
                                            </div>
                                            <span class="badge bg-light text-dark border small">Total: {{ $row->total_installments }} parcelas</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0 align-middle">
                                                <thead class="table-light small">
                                                    <tr>
                                                        <th>Parcela</th>
                                                        <th>Número</th>
                                                        <th>Vencimento</th>
                                                        <th class="text-end">Valor</th>
                                                        <th>Gateway</th>
                                                        <th>Status</th>
                                                        <th class="text-end">Ações da Parcela</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($row->items as $item)
                                                        @php
                                                            $stItem = $statusMap[$item->status] ?? ['tone' => 'secondary', 'icon' => 'bi-circle', 'label' => ucfirst((string) $item->status)];
                                                            $gwItem = $gatewayMap[$item->gateway] ?? null;
                                                        @endphp
                                                        <tr class="{{ $item->status === 'overdue' ? 'table-danger-subtle' : '' }}">
                                                            <td>
                                                                <span class="badge bg-light text-dark border">
                                                                    {{ $item->meta['installment'] ?? '1' }}/{{ $row->total_installments }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="{{ route('admin.billing.invoices.show', $item->id) }}" class="fw-semibold text-decoration-none">
                                                                    {{ $item->number }}
                                                                </a>
                                                            </td>
                                                            <td>{{ \App\Support\Format::date($item->due_date) }}</td>
                                                            <td class="text-end fw-semibold">{{ \App\Support\Format::money($item->amount) }}</td>
                                                            <td>
                                                                @if($gwItem)
                                                                    <x-ui.status-badge :status="$gwItem['tone']" :label="$gwItem['label']" />
                                                                @else
                                                                    <span class="text-muted small">Padrão</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <x-ui.status-badge :status="$stItem['tone']" :label="$stItem['label']" />
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                                                    @if(in_array($item->status, ['pending', 'sent', 'overdue']))
                                                                        <button class="btn btn-sm btn-outline-success p-1 px-2" data-settle="{{ $item->id }}" data-number="{{ $item->number }}" data-amount="{{ (float)$item->amount }}" data-customer="{{ addslashes($custName) }}" title="Dar baixa manual">
                                                                            <i class="bi bi-check2-circle"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-outline-success p-1 px-2" data-send="{{ $item->id }}" title="Enviar cobrança via WhatsApp">
                                                                            <i class="bi bi-whatsapp"></i>
                                                                        </button>
                                                                    @endif
                                                                    <a href="{{ route('admin.billing.invoices.show', $item->id) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Ver detalhes">
                                                                        <i class="bi bi-eye"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @else
                            {{-- LINHA NORMAL DE FATURA INDIVIDUAL --}}
                            @php
                                $i = $row->invoice;
                                $st = $statusMap[$i->status] ?? ['tone' => 'secondary', 'icon' => 'bi-circle', 'label' => ucfirst((string) $i->status)];
                                $gw = $gatewayMap[$i->gateway] ?? null;
                                $descriptionPreview = trim((string) \Illuminate\Support\Str::of(html_entity_decode(strip_tags((string) $i->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish());
                                $name = $i->customer?->name ?? 'Cliente não informado';
                                $initials = collect(explode(' ', $name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.billing.invoices.show', $i->id) }}" class="fw-semibold text-decoration-none">
                                        {{ $i->number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="billing-avatar">{{ strtoupper($initials ?: '?') }}</span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate" style="max-width: 180px;">{{ $name }}</div>
                                            <div class="small text-muted">{{ \App\Support\Format::phone($i->customer?->whatsapp) ?: 'Sem WhatsApp' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="small d-inline-block text-truncate" style="max-width: 240px;" title="{{ $descriptionPreview }}">
                                        {{ \Illuminate\Support\Str::limit($descriptionPreview, 52) ?: 'Sem descrição' }}
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">{{ \App\Support\Format::money($i->amount) }}</td>
                                <td>
                                    @if($i->due_date)
                                        <div>{{ \App\Support\Format::date($i->due_date) }}</div>
                                    @else
                                        <span class="text-muted">Não definido</span>
                                    @endif
                                </td>
                                <td>
                                    @if($gw)
                                        <x-ui.status-badge :status="$gw['tone']" :label="$gw['label']" />
                                    @else
                                        <span class="text-muted">Padrão</span>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.status-badge :status="$st['tone']" :label="$st['label']" />
                                </td>
                                <td class="all text-end no-export" style="width: 140px; min-width: 130px; max-width: 150px; white-space: nowrap;">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                        <button class="btn btn-sm btn-outline-info p-1 px-2 btn-copy-public-link" data-link="{{ url('/fatura/' . ($i->token ?: ($i->webhook_token ?: $i->id))) }}" title="Copiar link da fatura pública">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>
                                        @if(in_array($i->status, ['pending', 'sent', 'overdue']))
                                            <button class="btn btn-sm btn-outline-success p-1 px-2" data-settle="{{ $i->id }}" data-number="{{ $i->number }}" data-amount="{{ (float)$i->amount }}" data-customer="{{ addslashes($name) }}" title="Dar baixa manual">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success p-1 px-2" data-send="{{ $i->id }}" title="Enviar cobrança via WhatsApp (Meta Oficial)">
                                                <i class="bi bi-whatsapp"></i>
                                            </button>
                                        @elseif($i->status === 'paid' && filled($i->gateway_charge_id))
                                            <button class="btn btn-sm btn-outline-danger p-1 px-2 btn-refund-invoice" data-id="{{ $i->id }}" data-number="{{ $i->number }}" data-charge-id="{{ $i->gateway_charge_id }}" data-amount="{{ (float)$i->amount }}" title="Estornar no Mercado Pago">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.billing.invoices.show', $i->id) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Ver detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
                                Nenhuma fatura encontrada.
                                <div class="mt-3">
                                    <a href="{{ route('admin.billing.invoices.create') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-lg me-1"></i> Criar primeira fatura
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $invoices->hasPages())
            <div class="card-footer py-2 mt-3 bg-transparent border-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">Mostrando {{ $invoices->firstItem() }} a {{ $invoices->lastItem() }} de {{ $invoices->total() }} registros</small>
                    {{ $invoices->links() }}
                </div>
            </div>
        @endif
    </x-ui.premium-card>

    {{-- Modal de Baixa Manual (Admin) --}}
    <div class="modal fade" id="modal-settle-invoice" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg border-0">
                <form id="frm-settle-invoice">
                    @csrf
                    <input type="hidden" id="settle-invoice-id" value="">
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
                                <div class="fs-5 fw-bold text-dark" id="settle-summary-number">-</div>
                                <div class="small text-muted" id="settle-summary-customer">-</div>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small text-uppercase fw-bold">Valor da Parcela/Fatura</span>
                                <div class="fs-4 fw-bold text-success" id="settle-summary-amount">R$ 0,00</div>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle" id="settle-summary-due">Vencimento: -</span>
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
                                                Baixar apenas esta parcela (<span id="settle-single-amount-label">R$ 0,00</span>)
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
                            <span class="fs-5 fw-bold text-success ms-1" id="settle-final-amount">R$ 0,00</span>
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

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

document.querySelectorAll('[data-send]').forEach((btn) => {
    btn.onclick = async () => {
        const ok = await Swal.fire({
            icon: 'question',
            title: 'Enviar cobrança via WhatsApp?',
            text: 'A mensagem será enviada imediatamente para o cliente.',
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        });

        if (!ok.isConfirmed) {
            return;
        }

        const id = btn.dataset.send;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const r = await fetch(`{{ url('/admin/billing/invoices') }}/${id}/send`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            const d = await r.json().catch(() => ({}));

            if (r.ok && d.ok) {
                window.toast.success(d.message || 'Cobranca enviada com sucesso.');
            } else {
                window.toast.error(d.error || 'Falha no envio.');
            }
        } catch (err) {
            window.toast.error('Erro de rede.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    };
});

// Copiar Link Público da Fatura com Toastify
document.querySelectorAll('.btn-copy-public-link').forEach((btn) => {
    btn.onclick = async () => {
        const link = btn.dataset.link;
        if (!link) return;
        try {
            await navigator.clipboard.writeText(link);
            if (window.toast) window.toast.success('Link da fatura pública copiado!');
            else alert2('Sucesso', 'Link copiado!', 'success');
        } catch(e) {
            prompt('Copie o link abaixo:', link);
        }
    };
});

// Estornar Fatura no Mercado Pago com Confirmação SweetAlert2
document.querySelectorAll('.btn-refund-invoice').forEach((btn) => {
    btn.onclick = async () => {
        const id = btn.dataset.id;
        const number = btn.dataset.number;
        const amount = parseFloat(btn.dataset.amount || 0);

        const { value: refundAmount } = await Swal.fire({
            title: `Estornar Fatura #${number}?`,
            text: `Valor original: R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}. Informe o valor do estorno (ou deixe vazio para estorno integral):`,
            input: 'text',
            inputPlaceholder: amount.toFixed(2),
            showCancelButton: true,
            confirmButtonText: 'Confirmar Estorno',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            inputValidator: (val) => {
                if (val && (isNaN(parseFloat(val)) || parseFloat(val) <= 0 || parseFloat(val) > amount)) {
                    return 'Informe um valor válido menor ou igual ao total da fatura!';
                }
            }
        });

        if (refundAmount === undefined) return;

        btn.disabled = true;
        try {
            const resp = await fetch(`{{ url('/admin/billing/invoices') }}/${id}/refund`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ amount: refundAmount ? parseFloat(refundAmount) : amount })
            });

            const data = await resp.json().catch(() => ({}));
            if (resp.ok && data.ok) {
                await Swal.fire('Estornado!', data.message || 'Estorno realizado com sucesso no Mercado Pago.', 'success');
                location.reload();
            } else {
                Swal.fire('Falha no Estorno', data.error || 'Não foi possível estornar.', 'error');
            }
        } catch(e) {
            Swal.fire('Erro', 'Falha de comunicação com o servidor.', 'error');
        } finally {
            btn.disabled = false;
        }
    };
});

// Modal de Baixa Manual (Admin)
const settleModalEl = document.getElementById('modal-settle-invoice');
const settleModal = settleModalEl ? new bootstrap.Modal(settleModalEl) : null;
let currentSettleContext = null;
let currentSettleBaseAmount = 0;

document.querySelectorAll('[data-settle]').forEach((btn) => {
    btn.onclick = async () => {
        const id = btn.dataset.settle;
        const number = btn.dataset.number;
        const amount = parseFloat(btn.dataset.amount || 0);
        const customer = btn.dataset.customer;
        openAdminSettleModal(id, number, amount, customer);
    };
});

async function openAdminSettleModal(id, number, amount, customerName) {
    if (!settleModal) return;
    document.getElementById('settle-invoice-id').value = id;
    document.getElementById('settle-summary-number').textContent = number ? `#${number}` : `#${id}`;
    document.getElementById('settle-summary-customer').textContent = customerName || 'Cliente';
    document.getElementById('settle-summary-amount').textContent = 'R$ ' + parseFloat(amount).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('settle-single-amount-label').textContent = 'R$ ' + parseFloat(amount).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('settle-paid-at').value = new Date().toISOString().split('T')[0];
    document.getElementById('settle-notes').value = '';
    document.getElementById('radio-mode-single').checked = true;

    currentSettleBaseAmount = parseFloat(amount) || 0;
    currentSettleContext = null;

    const section = document.getElementById('settle-installment-section');
    section.classList.add('d-none');
    document.getElementById('container-mode-antecipated').classList.add('d-none');
    document.getElementById('settle-custom-installments-list').classList.add('d-none');
    document.getElementById('settle-custom-installments-list').innerHTML = '';

    updateSettleModeUI();
    settleModal.show();

    try {
        const resp = await fetch(`{{ url('/admin/billing/invoices') }}/${id}/installment-context`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await resp.json();
        if (resp.ok && data.ok && data.context) {
            currentSettleContext = data.context;

            if (data.context.this_invoice && data.context.this_invoice.formatted_due_date) {
                document.getElementById('settle-summary-due').textContent = 'Vencimento: ' + data.context.this_invoice.formatted_due_date;
            }

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
                    const combinedAnt = currentSettleBaseAmount + data.context.total_antecipated_amount;
                    document.getElementById('settle-antecipated-total').textContent = 'R$ ' + combinedAnt.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                document.getElementById('settle-all-count').textContent = data.context.open_count + 1;
                const combinedAll = currentSettleBaseAmount + data.context.total_open_amount;
                document.getElementById('settle-all-total').textContent = 'R$ ' + combinedAll.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const listEl = document.getElementById('settle-custom-installments-list');
                listEl.innerHTML = '';
                data.context.open_installments.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'form-check py-1 border-bottom';
                    div.innerHTML = `
                        <input class="form-check-input settle-custom-cb" type="checkbox" value="${item.id}" id="cb-admin-inst-${item.id}" data-amount="${item.amount}" onchange="updateSettleModeUI()">
                        <label class="form-check-label d-flex justify-content-between align-items-center w-100 pe-2" for="cb-admin-inst-${item.id}">
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
}

function updateSettleModeUI() {
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

    let finalAmount = currentSettleBaseAmount;
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
}

document.getElementById('frm-settle-invoice').addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = document.getElementById('settle-invoice-id').value;
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
        const resp = await fetch(`{{ url('/admin/billing/invoices') }}/${id}/settle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
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

// =========================================================================
// CONTROLE DE EXPANSÃO DE CARNÊS E PARCELAS (MODO AGRUPADO ADMIN)
// =========================================================================
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-toggle-admin-carne');
    if (!btn) return;

    e.preventDefault();
    const batchId = btn.dataset.batchId;
    const detailsRow = document.getElementById(`carne-details-${batchId}`);
    const chevron = document.getElementById(`chevron-${batchId}`);

    if (!detailsRow) return;

    const isHidden = detailsRow.style.display === 'none' || detailsRow.style.display === '';

    if (isHidden) {
        detailsRow.style.display = 'table-row';
        if (chevron) {
            chevron.classList.remove('bi-chevron-down');
            chevron.classList.add('bi-chevron-up');
        }
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
    } else {
        detailsRow.style.display = 'none';
        if (chevron) {
            chevron.classList.remove('bi-chevron-up');
            chevron.classList.add('bi-chevron-down');
        }
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
    }
});

// Botão Expandir / Recolher Todos os Carnês
const btnToggleAll = document.getElementById('btn-toggle-all-carnes');
if (btnToggleAll) {
    let allExpanded = false;
    btnToggleAll.addEventListener('click', function () {
        allExpanded = !allExpanded;
        const allRows = document.querySelectorAll('.carne-details-row');
        const allChevrons = document.querySelectorAll('.btn-toggle-admin-carne i.bi');
        const allBtns = document.querySelectorAll('.btn-toggle-admin-carne');
        const label = document.getElementById('btn-toggle-all-text');

        allRows.forEach(row => {
            row.style.display = allExpanded ? 'table-row' : 'none';
        });

        allChevrons.forEach(ch => {
            ch.classList.toggle('bi-chevron-down', !allExpanded);
            ch.classList.toggle('bi-chevron-up', allExpanded);
        });

        allBtns.forEach(b => {
            b.classList.toggle('btn-primary', !allExpanded);
            b.classList.toggle('btn-secondary', allExpanded);
        });

        if (label) {
            label.textContent = allExpanded ? 'Recolher Carnês' : 'Expandir Carnês';
        }
    });
}
</script>
@endpush
