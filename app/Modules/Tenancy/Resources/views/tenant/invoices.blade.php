@extends('tenancy::tenant.layout')
@section('title', 'Faturas & Cobranças')

@push('head')
<style>
.bulk-floating-bar {
    position: sticky;
    bottom: 20px;
    z-index: 1040;
    transition: all 0.3s ease;
}
.invoice-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.table-hover tbody tr:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.04);
}
.card-body.p-0 {
    overflow: visible !important;
}
.table-responsive {
    overflow-x: auto;
    overflow-y: visible !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    {{-- Topo & Cabeçalho --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Faturas & Cobranças</h3>
            <p class="text-muted mb-0 small">Gerencie as faturas dos seus clientes, antecipe, cancele, mescle e acompanhe recebimentos.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-create-standalone-invoice" onclick="openCreateStandaloneModal()">
                <i class="bi bi-plus-circle me-1"></i> Nova Fatura Avulsa
            </button>
            <a href="{{ route('tenant.calendar') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar3 me-1"></i> Calendário
            </a>
            <a href="{{ route('tenant.billing') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear me-1"></i> Configurações
            </a>
        </div>
    </div>

    {{-- KPIs Resumo --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Total de Faturas</div>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</div>
                    <small class="text-muted"><i class="bi bi-receipt me-1"></i>Cobranças de clientes</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Total Recebido</div>
                    <div class="fs-4 fw-bold text-success mt-1">R$ {{ number_format($stats['paid'] ?? 0, 2, ',', '.') }}</div>
                    <small class="text-success"><i class="bi bi-check-circle me-1"></i>Faturas quitadas</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">A Receber (Pendente)</div>
                    <div class="fs-4 fw-bold text-warning mt-1">R$ {{ number_format($stats['pending'] ?? 0, 2, ',', '.') }}</div>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>Dentro do prazo</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-danger border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Vencidas</div>
                    <div class="fs-4 fw-bold text-danger mt-1">R$ {{ number_format($stats['overdue'] ?? 0, 2, ',', '.') }}</div>
                    <small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Aguardando pagamento</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Abas de Navegação --}}
    <ul class="nav nav-pills mb-3" id="invoicesTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="{{ route('tenant.invoices') }}" class="nav-link {{ request('tipo') !== 'avulsa' ? 'active' : '' }} fw-semibold">
                <i class="bi bi-people me-1"></i> Todas as Faturas
                <span class="badge bg-primary ms-1">{{ $stats['total'] ?? 0 }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{ route('tenant.invoices') }}?tipo=carne{{ request('view_mode') ? '&view_mode='.request('view_mode') : '' }}" class="nav-link {{ request('tipo') === 'carne' ? 'active' : '' }} fw-semibold">
                <i class="bi bi-collection me-1"></i> Carnês & Parcelamentos
                <span class="badge bg-purple ms-1" style="background:#8b5cf6;">{{ $stats['carnes'] ?? 0 }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{ route('tenant.invoices') }}?tipo=avulsa{{ request('view_mode') ? '&view_mode='.request('view_mode') : '' }}" class="nav-link {{ request('tipo') === 'avulsa' ? 'active' : '' }} fw-semibold">
                <i class="bi bi-file-earmark-plus me-1"></i> Cobranças Avulsas
                <span class="badge bg-info ms-1">{{ $stats['standalone'] ?? 0 }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="plan-invoices-tab" data-bs-toggle="pill" data-bs-target="#plan-invoices" type="button" role="tab">
                <i class="bi bi-shield-check me-1"></i> Minha Assinatura (Plano)
                <span class="badge bg-secondary ms-1">{{ $subscriptionInvoices->total() }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="invoicesTabContent">
        {{-- ======================================================== --}}
        {{-- ABA 1: COBRANÇAS DE CLIENTES --}}
        {{-- ======================================================== --}}
        <div class="tab-pane fade show active" id="clients-invoices" role="tabpanel">
            {{-- Filtros de Busca --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('tenant.invoices') }}" class="row g-2 align-items-center">
                        @if(request('tipo'))
                            <input type="hidden" name="tipo" value="{{ request('tipo') }}">
                        @endif
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Buscar por cliente, número ou WhatsApp..." value="{{ request('search') }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos os status</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendente</option>
                                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Pago</option>
                                <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Vencida</option>
                                <option value="canceled" {{ request('status') === 'canceled' ? 'selected' : '' }}>Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="tipo" class="form-select form-select-sm">
                                <option value="">Todos os tipos</option>
                                <option value="carne" {{ request('tipo') === 'carne' ? 'selected' : '' }}>Apenas Carnês / Parcelados</option>
                                <option value="avulsa" {{ request('tipo') === 'avulsa' ? 'selected' : '' }}>Somente Avulsas</option>
                                <option value="recorrente" {{ request('tipo') === 'recorrente' ? 'selected' : '' }}>Somente Recorrentes</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="start_date" class="form-control form-control-sm" title="Vencimento a partir de" value="{{ request('start_date') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="end_date" class="form-control form-control-sm" title="Vencimento até" value="{{ request('end_date') }}">
                        </div>
                        <div class="col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100" title="Filtrar"><i class="bi bi-funnel"></i></button>
                            @if(request()->hasAny(['search', 'status', 'tipo', 'start_date', 'end_date']))
                                <a href="{{ route('tenant.invoices') }}" class="btn btn-sm btn-outline-secondary" title="Limpar Filtros"><i class="bi bi-x-lg"></i></a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabela de Faturas --}}
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    @if($invoices->count())

                    {{-- Barra de Ferramentas & Modo de Visualização --}}
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark"><i class="bi bi-receipt-cutoff me-1 text-primary"></i>Cobranças</span>
                            <span class="badge bg-secondary-subtle text-secondary small">{{ $invoices->total() }} registros</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if(($viewMode ?? 'grouped') === 'grouped')
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="btn-toggle-all-carnes" title="Expandir ou recolher todos os carnês da página">
                                    <i class="bi bi-arrows-expand me-1"></i><span id="txt-toggle-all">Expandir Todos</span>
                                </button>
                            @endif
                            <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="Modo de visualização">
                                <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'grouped']) }}" 
                                   class="btn {{ ($viewMode ?? 'grouped') === 'grouped' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                                   title="Agrupar parcelas de mesmo carnê para visualização limpa">
                                    <i class="bi bi-collection me-1"></i>Modo Carnês
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'flat']) }}" 
                                   class="btn {{ ($viewMode ?? 'grouped') === 'flat' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                                   title="Exibir todas as faturas individuais soltas">
                                    <i class="bi bi-list-ul me-1"></i>Modo Individual
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0" id="table-tenant-invoices" style="width: 100% !important;">
                            <thead class="table-light">
                                <tr>
                                    <th width="40" class="text-center">
                                        <input type="checkbox" class="form-check-input invoice-checkbox" id="select-all-invoices" title="Selecionar todas desta página">
                                    </th>
                                    <th>Número / Carnê</th>
                                    <th>Cliente</th>
                                    <th>Descrição & Progresso</th>
                                    <th class="fw-semibold">Valor</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                    <th class="text-end" style="width: 180px; min-width: 170px; white-space: nowrap;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $statusMap = [
                                        'paid' => ['bg' => 'success', 'label' => 'Pago', 'icon' => 'bi-check-circle'],
                                        'pending' => ['bg' => 'warning', 'label' => 'Pendente', 'icon' => 'bi-clock'],
                                        'sent' => ['bg' => 'warning', 'label' => 'Pendente', 'icon' => 'bi-clock'],
                                        'overdue' => ['bg' => 'danger', 'label' => 'Vencida', 'icon' => 'bi-exclamation-circle'],
                                        'canceled' => ['bg' => 'secondary', 'label' => 'Cancelada', 'icon' => 'bi-x-circle'],
                                        'cancelled' => ['bg' => 'secondary', 'label' => 'Cancelada', 'icon' => 'bi-x-circle'],
                                    ];
                                @endphp

                                @foreach($displayRows as $row)
                                    @if($row->type === 'carne')
                                        {{-- LINHA MESTRA DO CARNÊ AGRUPADO --}}
                                        @php
                                            $customerName = $row->customer?->name ?? 'Sem cliente';
                                            $customerPhone = $row->customer?->whatsapp;
                                        @endphp
                                        <tr class="carne-group-row table-primary-subtle" id="carne-row-{{ $row->batch_id }}" style="background: rgba(37, 99, 235, 0.04);">
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       class="form-check-input carne-master-checkbox" 
                                                       data-batch-id="{{ $row->batch_id }}"
                                                       title="Marcar todas as parcelas pendentes deste carnê">
                                            </td>
                                            <td>
                                                <span class="badge border fw-bold text-dark" style="background: #ede9fe; color: #6d28d9 !important; border-color: #ddd6fe !important;">
                                                    <i class="bi bi-collection me-1"></i>Carnê {{ $row->total_installments }}x
                                                </span>
                                                <div class="small text-muted font-monospace mt-1" title="Lote: {{ $row->batch_id }}">
                                                    Lote #{{ strtoupper(substr($row->batch_id, 0, 8)) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $customerName }}</div>
                                                @if($customerPhone)
                                                    <small class="text-muted text-nowrap"><i class="bi bi-whatsapp text-success me-1"></i>{{ \App\Support\Format::phone($customerPhone) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 280px;" title="{{ $row->description }}">{{ $row->description }}</div>
                                                <div class="progress mt-1 shadow-xs" style="height: 6px; max-width: 220px;" title="{{ $row->paid_count }} de {{ $row->total_installments }} parcelas pagas">
                                                    <div class="progress-bar bg-success" style="width: {{ $row->progress_percent }}%;"></div>
                                                </div>
                                                <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">
                                                    <strong>{{ $row->paid_count }}/{{ $row->total_installments }}</strong> pagas ({{ $row->progress_percent }}% quitado)
                                                </small>
                                            </td>
                                            <td class="fw-bold text-nowrap">
                                                <div class="text-dark fs-6">R$ {{ number_format($row->total_amount, 2, ',', '.') }}</div>
                                                <small class="text-muted fw-normal" style="font-size: 0.75rem;">{{ $row->total_installments }}x de R$ {{ number_format($row->installment_amount, 2, ',', '.') }}</small>
                                            </td>
                                            <td>
                                                @if($row->next_due_invoice)
                                                    <div class="small fw-semibold {{ $row->next_due_invoice->status === 'overdue' ? 'text-danger' : 'text-dark' }}">
                                                        Próx: {{ $row->next_due_invoice->due_date?->format('d/m/Y') ?? '-' }}
                                                    </div>
                                                    <small class="text-muted d-block" style="font-size: 0.75rem;">Parcela {{ $row->next_due_invoice->meta['installment'] ?? '1' }}/{{ $row->total_installments }}</small>
                                                    @if($row->next_due_invoice->status === 'overdue' && $row->next_due_invoice->due_date)
                                                        <small class="text-danger d-block fw-bold" style="font-size: 0.72rem;">{{ $row->next_due_invoice->due_date->diffForHumans() }}</small>
                                                    @endif
                                                @else
                                                    <span class="badge bg-success-subtle text-success small"><i class="bi bi-check-all me-1"></i>Carnê Quitado</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    @if($row->overdue_count > 0)
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle small text-start">
                                                            <i class="bi bi-exclamation-circle me-1"></i>{{ $row->overdue_count }} Vencida(s)
                                                        </span>
                                                    @endif
                                                    @if($row->pending_count > 0)
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle small text-start">
                                                            <i class="bi bi-clock me-1"></i>{{ $row->pending_count }} Pendente(s)
                                                        </span>
                                                    @endif
                                                    @if($row->paid_count > 0)
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle small text-start">
                                                            <i class="bi bi-check-circle me-1"></i>{{ $row->paid_count }} Paga(s)
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-primary py-1 px-2 btn-toggle-carne shadow-sm" data-batch-id="{{ $row->batch_id }}" title="Abrir e ver todas as parcelas deste carnê">
                                                    <i class="bi bi-chevron-down me-1" id="chevron-{{ $row->batch_id }}"></i><span id="btn-text-{{ $row->batch_id }}">Ver {{ $row->total_installments }} Parcelas</span>
                                                </button>
                                            </td>
                                        </tr>

                                        {{-- GAVETA EXPANSÍVEL COM AS PARCELAS DO CARNÊ --}}
                                        <tr class="carne-details-row" id="carne-details-{{ $row->batch_id }}" style="display: none; background: #f8fafc;">
                                            <td colspan="8" class="p-3">
                                                <div class="card border border-light-subtle shadow-sm">
                                                    <div class="card-header bg-body-tertiary py-2 px-3 d-flex align-items-center justify-content-between">
                                                        <div class="small fw-bold text-dark">
                                                            <i class="bi bi-calendar3 me-1 text-primary"></i>Parcelas do Carnê ({{ $row->description }})
                                                        </div>
                                                        <span class="badge bg-light text-dark border small">Total: {{ $row->total_installments }} parcelas</span>
                                                    </div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0 align-middle">
                                                            <thead class="table-light small">
                                                                <tr>
                                                                    <th width="35" class="text-center">#</th>
                                                                    <th>Parcela</th>
                                                                    <th>Número</th>
                                                                    <th>Vencimento</th>
                                                                    <th>Valor</th>
                                                                    <th>Status</th>
                                                                    <th class="text-end">Ações da Parcela</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($row->items as $item)
                                                                    @php
                                                                        $stItem = $statusMap[$item->status] ?? ['bg' => 'secondary', 'label' => ucfirst($item->status), 'icon' => 'bi-question-circle'];
                                                                        $isItemPaid = $item->status === 'paid';
                                                                        $itemNumber = $item->number ?? '#' . $item->id;
                                                                    @endphp
                                                                    <tr id="inv-row-{{ $item->id }}" class="{{ $item->status === 'overdue' ? 'table-danger-subtle' : '' }}">
                                                                        <td class="text-center">
                                                                            <input type="checkbox" 
                                                                                   class="form-check-input invoice-checkbox item-checkbox carne-item-cb-{{ $row->batch_id }}" 
                                                                                   value="{{ $item->id }}"
                                                                                   data-id="{{ $item->id }}"
                                                                                   data-number="{{ $itemNumber }}"
                                                                                   data-amount="{{ (float) $item->amount }}"
                                                                                   data-status="{{ $item->status }}"
                                                                                   data-customer-id="{{ $item->customer_id ?? 0 }}"
                                                                                   data-customer-name="{{ $customerName }}">
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge bg-light text-dark border small">
                                                                                Parcela {{ $item->meta['installment'] ?? $loop->iteration }}/{{ $row->total_installments }}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <code class="fw-bold">{{ $itemNumber }}</code>
                                                                        </td>
                                                                        <td>
                                                                            <span class="small fw-semibold {{ $item->status === 'overdue' ? 'text-danger' : 'text-dark' }}">
                                                                                {{ $item->due_date?->format('d/m/Y') ?? '-' }}
                                                                            </span>
                                                                            @if($item->status === 'overdue' && $item->due_date)
                                                                                <small class="text-danger d-block" style="font-size: 0.72rem;">{{ $item->due_date->diffForHumans() }}</small>
                                                                            @endif
                                                                        </td>
                                                                        <td class="fw-bold">
                                                                            R$ {{ number_format($item->amount ?? 0, 2, ',', '.') }}
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge bg-{{ $stItem['bg'] }}-subtle text-{{ $stItem['bg'] }} border border-{{ $stItem['bg'] }}-subtle small">
                                                                                <i class="bi {{ $stItem['icon'] }} me-1"></i>{{ $stItem['label'] }}
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-end">
                                                                            <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                                                                @if(!$isItemPaid && $item->payment_url && !in_array($item->status, ['canceled', 'cancelled']))
                                                                                    <a href="{{ $item->payment_url }}" target="_blank" class="btn btn-xs btn-outline-success p-1 px-2" title="Abrir link de pagamento da parcela">
                                                                                        <i class="bi bi-credit-card"></i>
                                                                                    </a>
                                                                                @endif

                                                                                @if(!in_array($item->status, ['paid', 'canceled', 'cancelled']))
                                                                                    <button type="button" class="btn btn-xs btn-outline-info p-1 px-2" onclick="resendSingleInvoice({{ $item->id }}, '{{ $item->number }}', '{{ addslashes($customerName) }}', '{{ $customerPhone }}')" title="Reenviar esta parcela via WhatsApp">
                                                                                        <i class="bi bi-send"></i>
                                                                                    </button>
                                                                                @endif

                                                                                @if(!$isItemPaid && !in_array($item->status, ['canceled', 'cancelled']))
                                                                                    <button type="button" class="btn btn-xs btn-outline-success p-1 px-2" onclick="openSettleModal({{ $item->id }}, '{{ $item->number }}', {{ (float) $item->amount }}, '{{ addslashes($customerName) }}')" title="Dar baixa manual nesta parcela">
                                                                                        <i class="bi bi-check2-circle"></i>
                                                                                    </button>
                                                                                @endif

                                                                                <button type="button" class="btn btn-xs btn-outline-primary p-1 px-2" onclick="openInvoiceDetails({{ $item->id }})" title="Ver detalhes e QR Code Pix">
                                                                                    <i class="bi bi-eye"></i>
                                                                                </button>

                                                                                @if(!in_array($item->status, ['paid', 'canceled', 'cancelled']))
                                                                                    <button type="button" class="btn btn-xs btn-outline-warning p-1 px-2" onclick="cancelSingleInvoice({{ $item->id }}, '{{ $item->number }}')" title="Cancelar Parcela">
                                                                                        <i class="bi bi-slash-circle"></i>
                                                                                    </button>
                                                                                @endif

                                                                                @if(!$isItemPaid)
                                                                                    <button type="button" class="btn btn-xs btn-outline-danger p-1 px-2" onclick="deleteSingleInvoice({{ $item->id }}, '{{ $item->number }}')" title="Excluir Parcela">
                                                                                        <i class="bi bi-trash"></i>
                                                                                    </button>
                                                                                @endif
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
                                            $inv = $row->invoice;
                                            $st = $statusMap[$inv->status] ?? ['bg' => 'secondary', 'label' => ucfirst($inv->status), 'icon' => 'bi-question-circle'];
                                            $isPaid = $inv->status === 'paid';
                                            $customerName = $inv->customer?->name ?? 'Sem cliente';
                                            $customerPhone = $inv->customer?->whatsapp;
                                        @endphp
                                        <tr id="inv-row-{{ $inv->id }}">
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       class="form-check-input invoice-checkbox item-checkbox" 
                                                       value="{{ $inv->id }}"
                                                       data-id="{{ $inv->id }}"
                                                       data-number="{{ $inv->number ?? '#' . $inv->id }}"
                                                       data-amount="{{ (float) $inv->amount }}"
                                                       data-status="{{ $inv->status }}"
                                                       data-customer-id="{{ $inv->customer_id ?? 0 }}"
                                                       data-customer-name="{{ $customerName }}">
                                            </td>
                                            <td>
                                                <code class="fw-bold">{{ $inv->number ?? '#' . $inv->id }}</code>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $customerName }}</div>
                                                @if($customerPhone)
                                                    <small class="text-muted text-nowrap"><i class="bi bi-whatsapp text-success me-1"></i>{{ \App\Support\Format::phone($customerPhone) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="small text-muted">{{ Str::limit($inv->description ?: 'Cobrança', 35) }}</span>
                                                @if(isset($inv->meta['merged_from_numbers']))
                                                    <span class="badge bg-light text-dark border small ms-1" title="Mesclada"><i class="bi bi-intersect"></i> Unificada</span>
                                                @endif
                                                @if(!empty($inv->meta['is_standalone']))
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small ms-1" title="Cobrança Avulsa">
                                                        <i class="bi bi-file-earmark-plus me-1"></i>Avulsa
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="fw-bold text-nowrap">
                                                R$ {{ number_format($inv->amount ?? 0, 2, ',', '.') }}
                                            </td>
                                            <td>
                                                <div class="small fw-semibold {{ $inv->status === 'overdue' ? 'text-danger' : 'text-dark' }}">
                                                    {{ $inv->due_date?->format('d/m/Y') ?? '-' }}
                                                </div>
                                                @if($inv->status === 'overdue' && $inv->due_date)
                                                    <small class="text-danger d-block">{{ $inv->due_date->diffForHumans() }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $st['bg'] }}-subtle text-{{ $st['bg'] }} border border-{{ $st['bg'] }}-subtle small">
                                                    <i class="bi {{ $st['icon'] }} me-1"></i>{{ $st['label'] }}
                                                </span>
                                            </td>
                                            <td class="text-end" style="width: 180px; min-width: 170px; max-width: 190px; white-space: nowrap;">
                                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                                    @if(!$isPaid && $inv->payment_url && !in_array($inv->status, ['canceled', 'cancelled']))
                                                        <a href="{{ $inv->payment_url }}" target="_blank" class="btn btn-sm btn-outline-success p-1 px-2" title="Abrir link de pagamento">
                                                            <i class="bi bi-credit-card"></i>
                                                        </a>
                                                    @endif

                                                    @if(!in_array($inv->status, ['paid', 'canceled', 'cancelled']))
                                                        <button type="button" class="btn btn-sm btn-outline-info p-1 px-2" onclick="resendSingleInvoice({{ $inv->id }}, '{{ $inv->number }}', '{{ addslashes($customerName) }}', '{{ $customerPhone }}')" title="Reenviar cobrança manual (WhatsApp)">
                                                            <i class="bi bi-send"></i>
                                                        </button>
                                                    @endif

                                                    @if(!$isPaid && !in_array($inv->status, ['canceled', 'cancelled']))
                                                        <button type="button" class="btn btn-sm btn-outline-success p-1 px-2" onclick="openSettleModal({{ $inv->id }}, '{{ $inv->number }}', {{ (float) $inv->amount }}, '{{ addslashes($customerName) }}')" title="Dar baixa manual">
                                                            <i class="bi bi-check2-circle"></i>
                                                        </button>
                                                    @endif

                                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2" onclick="openInvoiceDetails({{ $inv->id }})" title="Ver e Editar">
                                                        <i class="bi bi-eye"></i>
                                                    </button>

                                                    @if(!in_array($inv->status, ['paid', 'canceled', 'cancelled']))
                                                        <button type="button" class="btn btn-sm btn-outline-warning p-1 px-2" onclick="cancelSingleInvoice({{ $inv->id }}, '{{ $inv->number }}')" title="Cancelar Fatura">
                                                            <i class="bi bi-slash-circle"></i>
                                                        </button>
                                                    @endif

                                                    @if(!$isPaid)
                                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="deleteSingleInvoice({{ $inv->id }}, '{{ $inv->number }}')" title="Excluir Fatura">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginação --}}
                    @if($invoices->hasPages())
                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                        <small class="text-muted">
                            Mostrando {{ $invoices->firstItem() }} a {{ $invoices->lastItem() }} de {{ $invoices->total() }} faturas
                        </small>
                        <div>
                            {{ $invoices->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                    @endif

                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-receipt display-4 text-secondary"></i>
                        <h5 class="mt-3">Nenhuma fatura encontrada</h5>
                        <p class="small text-muted mb-3">Não há cobranças cadastradas com os filtros selecionados.</p>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-create-standalone-invoice" onclick="openCreateStandaloneModal()">
                            <i class="bi bi-plus-circle me-1"></i> Criar Fatura Avulsa
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- ABA 2: MINHA ASSINATURA (PLANO) --}}
        {{-- ======================================================== --}}
        <div class="tab-pane fade" id="plan-invoices" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-shield-check me-2 text-primary"></i>Mensalidades do seu Plano</strong>
                </div>
                <div class="card-body p-0">
                    @if($subscriptionInvoices->count())
                    <div>
                        <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-tenant-sub-invoices" data-export-title="Faturas de Assinatura" style="width: 100% !important;">
                            <thead class="table-light">
                                <tr>
                                    <th class="all" data-priority="1">Número</th>
                                    <th class="desktop">Descrição</th>
                                    <th class="all text-end" data-priority="1">Valor</th>
                                    <th class="min-tablet" data-priority="2">Status</th>
                                    <th class="min-tablet">Vencimento</th>
                                    <th class="desktop">Pago em</th>
                                    <th class="all text-end no-export" data-priority="1" style="width: 130px; min-width: 120px; max-width: 140px; white-space: nowrap;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subscriptionInvoices as $subInv)
                                <tr>
                                    <td><code>{{ $subInv->number ?? '#' . $subInv->id }}</code></td>
                                    <td><strong>{{ $subInv->description ?: 'Assinatura Plataforma' }}</strong></td>
                                    <td class="fw-bold text-end">R$ {{ number_format($subInv->amount ?? 0, 2, ',', '.') }}</td>
                                    <td>
                                        @if($subInv->status === 'paid')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Pago</span>
                                        @elseif($subInv->status === 'overdue')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-exclamation-circle me-1"></i>Vencida</span>
                                        @elseif($subInv->status === 'canceled' || $subInv->status === 'cancelled')
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-x-circle me-1"></i>Cancelada</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-clock me-1"></i>Pendente</span>
                                        @endif
                                    </td>
                                    <td>{{ $subInv->due_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $subInv->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="all text-end no-export" style="width: 130px; min-width: 120px; max-width: 140px; white-space: nowrap;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                            @if($subInv->status !== 'paid' && $subInv->status !== 'canceled' && $subInv->status !== 'cancelled')
                                                <button type="button" class="btn btn-sm btn-outline-success p-1 px-2" onclick="payPlanInvoice({{ $subInv->id }})" title="Pagar Agora">
                                                    <i class="bi bi-credit-card"></i>
                                                </button>
                                            @endif

                                            <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2" onclick="viewPlanInvoice({{ $subInv->id }})" title="Visualizar Fatura">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            @if($subInv->status !== 'paid' && $subInv->status !== 'canceled' && $subInv->status !== 'cancelled')
                                                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="cancelPlanInvoice({{ $subInv->id }}, '{{ $subInv->number ?? '#' . $subInv->id }}')" title="Solicitar Cancelamento">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-shield-check display-4 text-secondary"></i>
                        <h5 class="mt-3">Nenhuma fatura de assinatura</h5>
                        <p class="small text-muted">Suas mensalidades da plataforma aparecerão aqui quando forem faturadas.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- BARRA FLUTUANTE DE AÇÕES EM LOTE (STICKY BULK BAR) --}}
{{-- ======================================================== --}}
<div id="bulk-action-bar" class="bulk-floating-bar d-none">
    <div class="card shadow-lg border border-primary">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary fs-6 px-3 py-2" id="bulk-selected-badge">
                    <span id="bulk-count">0</span> selecionada(s)
                </span>
                <span class="fw-bold text-dark fs-6" id="bulk-total-amount">R$ 0,00</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-success btn-sm fw-semibold shadow-sm" onclick="openBulkSettleModal()">
                    <i class="bi bi-check2-circle me-1"></i> Dar Baixa Manual
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" onclick="openBulkAnticipateModal()">
                    <i class="bi bi-calendar-event me-1"></i> Antecipar Vencimento
                </button>
                <button type="button" class="btn btn-outline-info btn-sm fw-semibold" id="btn-bulk-merge" onclick="openMergeModal()">
                    <i class="bi bi-intersect me-1"></i> Mesclar Faturas
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm fw-semibold" onclick="executeBulkAction('cancel')">
                    <i class="bi bi-x-circle me-1"></i> Cancelar Faturas
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" onclick="executeBulkAction('delete')">
                    <i class="bi bi-trash me-1"></i> Excluir Faturas
                </button>
                <button type="button" class="btn btn-light btn-sm text-secondary" onclick="deselectAllInvoices()" title="Desmarcar todas">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAIS --}}
{{-- ======================================================== --}}

{{-- Modal 1: Antecipar Vencimento (Individual e em Lote) --}}
<div class="modal fade" id="modal-anticipate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form id="frm-anticipate">
                @csrf
                <input type="hidden" id="anticipate-mode" value="bulk"> {{-- 'single' ou 'bulk' --}}
                <input type="hidden" id="anticipate-invoice-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title fs-6"><i class="bi bi-calendar-event me-2 text-primary"></i>Antecipar Vencimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="anticipate-desc">Selecione a nova data de vencimento para as faturas selecionadas.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nova Data de Vencimento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="anticipate-date" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAnticipateDate(0)">Hoje</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAnticipateDate(1)">Amanhã</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAnticipateDate(3)">+3 dias</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAnticipateDate(7)">+7 dias</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i>Salvar Vencimento</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Mesclar Faturas --}}
<div class="modal fade" id="modal-merge" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-merge">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fs-6"><i class="bi bi-intersect me-2 text-primary"></i>Mesclar Faturas Selecionadas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small border-0 mb-3">
                        <i class="bi bi-info-circle me-1"></i> As faturas selecionadas serão agrupadas em uma única fatura e canceladas individualmente.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Cliente</label>
                        <div class="fw-bold text-dark fs-6" id="merge-customer-name">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Faturas a serem mescladas</label>
                        <div class="list-group list-group-flush border rounded-2 p-2 bg-light small" id="merge-invoices-list" style="max-height: 150px; overflow-y: auto;">
                            {{-- Preenchido via JS --}}
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Valor Total Unificado</label>
                            <div class="fs-5 fw-bold text-primary" id="merge-total-amount">R$ 0,00</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Novo Vencimento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="merge-due-date" required value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Descrição da Fatura Unificada</label>
                        <input type="text" class="form-control form-control-sm" id="merge-description" placeholder="Ex.: Fatura unificada mensal">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-intersect me-1"></i>Confirmar Mesclagem</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 3: Detalhes & Edição da Fatura --}}
<div class="modal fade" id="modal-invoice-detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6"><i class="bi bi-receipt me-2 text-primary"></i>Fatura <span id="detail-inv-number"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-detail-inv" class="row g-3">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="col-12">
                        <label class="form-label text-muted small mb-0">Cliente</label>
                        <div class="fw-semibold text-dark fs-6" id="detail-customer-name"></div>
                        <small class="text-muted" id="detail-customer-phone"></small>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small mb-0">Descrição</label>
                        <div class="fw-semibold" id="detail-desc"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-0">Valor</label>
                        <div class="fw-bold fs-5 text-primary" id="detail-amount"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-0">Data de Pagamento</label>
                        <div class="fw-semibold" id="detail-paid-at">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm" id="detail-status">
                            <option value="pending">Pendente</option>
                            <option value="paid">Pago</option>
                            <option value="overdue">Vencida</option>
                            <option value="canceled">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Vencimento</label>
                        <input name="due_date" type="date" class="form-control form-control-sm" id="detail-due-date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-sm btn-success d-none" id="detail-settle-btn">
                    <i class="bi bi-check2-circle me-1"></i>Dar Baixa Manual
                </button>
                <a href="#" target="_blank" class="btn btn-sm btn-outline-success d-none" id="detail-payment-btn">
                    <i class="bi bi-credit-card me-1"></i>Link de Pagamento
                </a>
                <button type="button" class="btn btn-sm btn-outline-info d-none" id="detail-resend-btn">
                    <i class="bi bi-send me-1"></i>Reenviar WhatsApp
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark d-none" id="detail-cancel-btn">
                    <i class="bi bi-slash-circle me-1"></i>Cancelar Fatura
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-save-detail"><i class="bi bi-save me-1"></i>Salvar Alterações</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal 4: Nova Fatura Avulsa (À Vista e Parcelada) --}}
<div class="modal fade" id="modal-create-standalone-invoice" tabindex="-1" aria-labelledby="modalCreateStandaloneTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <form id="frm-create-standalone-invoice" autocomplete="off">
                @csrf
                <div class="modal-header bg-light py-3 border-bottom">
                    <div>
                        <h5 class="modal-title fs-5 fw-bold text-dark mb-0" id="modalCreateStandaloneTitle">
                            <i class="bi bi-file-earmark-plus me-2 text-primary"></i>Nova Fatura Avulsa
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Gere cobranças pontuais ou parceladas (carnê) para seus clientes.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Seleção do Cliente --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1 mb-0">
                                <i class="bi bi-person me-1 text-primary"></i>Cliente Destinatário <span class="text-danger">*</span>
                            </label>
                            <a href="{{ route('tenant.customers') }}" target="_blank" class="small text-primary text-decoration-none">
                                <i class="bi bi-plus-lg me-1"></i>Novo Cliente
                            </a>
                        </div>
                        <select name="customer_id" id="standalone_customer_id" class="form-select form-select-md fw-semibold" required>
                            <option value="">Selecione o cliente que receberá a cobrança...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-whatsapp="{{ $c->whatsapp }}" data-email="{{ $c->email }}" data-doc="{{ $c->document }}">
                                    {{ $c->name }} {{ $c->document ? "({$c->document})" : '' }} {{ $c->whatsapp ? "• " . \App\Support\Format::phone($c->whatsapp) : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted" id="standalone-customer-info">A fatura e o histórico de cobrança ficarão vinculados a este cliente.</small>
                        </div>
                    </div>

                    {{-- Descrição do Serviço / Produto --}}
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                            <i class="bi bi-tag me-1 text-primary"></i>Descrição / Referência do Serviço <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="description" id="standalone_description" class="form-control" placeholder="Ex: Prestação de Serviços, Manutenção Mensal, Venda Avulsa..." required maxlength="255">
                    </div>

                    {{-- Linha de Valores, Parcelas e Juros --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                                <i class="bi bi-cash me-1 text-success"></i>Valor Total (R$) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">R$</span>
                                <input type="text" name="amount" id="standalone_amount" class="form-control fw-bold fs-6 text-primary" placeholder="0,00" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                                <i class="bi bi-calendar-range me-1 text-primary"></i>Parcelamento
                            </label>
                            <select name="installments" id="standalone_installments" class="form-select fw-semibold">
                                <option value="1">1x (À vista)</option>
                                @for($i = 2; $i <= 24; $i++)
                                    <option value="{{ $i }}">{{ $i }}x (Carnê de {{ $i }} parcelas)</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                                <i class="bi bi-percent me-1 text-primary"></i>Juros ao Mês (%)
                            </label>
                            <input type="number" name="interest_rate" id="standalone_interest_rate" class="form-control fw-semibold" value="0" min="0" step="0.1">
                        </div>
                    </div>

                    {{-- Linha de 1º Vencimento, Gateway e Envio WhatsApp --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                                <i class="bi bi-calendar-check me-1 text-primary"></i>1º Vencimento <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="first_due_date" id="standalone_first_due_date" class="form-control" value="{{ date('Y-m-d', strtotime('+1 day')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                                <i class="bi bi-credit-card me-1 text-primary"></i>Gateway de Pagamento
                            </label>
                            <select name="gateway" id="standalone_gateway" class="form-select">
                                <option value="">(Padrão do Sistema)</option>
                                @foreach($gateways as $gw)
                                    <option value="{{ $gw->gateway }}" {{ $gw->is_default ? 'selected' : '' }}>
                                        {{ ucfirst($gw->gateway) }} {{ $gw->is_default ? '★ (Padrão)' : '' }}
                                    </option>
                                @endforeach
                                @if($gateways->isEmpty())
                                    <option value="mercadopago">Mercado Pago</option>
                                    <option value="efipro">Efí Pro</option>
                                    <option value="sumup">SumUp</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center mt-md-4">
                            <div class="form-check form-switch p-0 ps-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="standalone_send_now" name="send_now" value="1" checked style="cursor: pointer;">
                                <label class="form-check-label small fw-semibold text-dark ms-1" for="standalone_send_now" style="cursor: pointer;">
                                    <i class="bi bi-whatsapp text-success me-1"></i>Enviar WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Live Preview / Simulação Dinâmica do Carnê --}}
                    <div class="card border rounded-3 bg-light-subtle p-3 mb-0" id="standalone-preview-box">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                            <span class="fw-bold text-primary small text-uppercase letter-spacing-1">
                                <i class="bi bi-magic me-1"></i>Resumo da Cobrança
                            </span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="standalone-preview-badge">1x À vista</span>
                        </div>
                        <div class="row align-items-center g-3">
                            <div class="col-sm-5 border-end-sm">
                                <div class="text-muted small">Valor Final Total</div>
                                <div class="fs-4 fw-bold text-dark mt-1" id="standalone-preview-total">R$ 0,00</div>
                                <div class="small text-muted" id="standalone-preview-interest-detail">Sem acréscimo de juros.</div>
                            </div>
                            <div class="col-sm-7 ps-sm-3">
                                <div class="text-muted small mb-1 fw-semibold">Demonstrativo das Parcelas:</div>
                                <div id="standalone-preview-list" style="max-height: 125px; overflow-y: auto;" class="small pe-1">
                                    <div class="text-muted fst-italic">Informe o valor acima para ver as parcelas...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btn-submit-standalone">
                        <i class="bi bi-check2-circle me-1"></i><span id="btn-submit-text">Gerar Fatura(s)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 5: Visualizar Fatura do Plano --}}
<div class="modal fade" id="modal-view-plan-invoice" tabindex="-1" aria-labelledby="modalViewPlanTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3 border-bottom">
                <div>
                    <h5 class="modal-title fs-5 fw-bold text-dark mb-0" id="modalViewPlanTitle">
                        <i class="bi bi-file-text me-2 text-primary"></i>Fatura <span id="view-plan-number" class="text-primary"></span>
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Detalhes e discriminativo da cobrança da plataforma.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light-subtle rounded-3 border">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold letter-spacing-1">Valor Total</span>
                        <div class="fs-3 fw-bold text-primary mt-1" id="view-plan-amount">R$ 0,00</div>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small text-uppercase fw-semibold letter-spacing-1">Status</span>
                        <div class="mt-1" id="view-plan-status-badge"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Descrição do Serviço</label>
                    <div class="p-2 border rounded-2 bg-light text-dark fw-semibold" id="view-plan-desc">-</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Vencimento</label>
                        <div class="fw-semibold text-dark" id="view-plan-due-date">-</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Pago em</label>
                        <div class="fw-semibold text-dark" id="view-plan-paid-at">-</div>
                    </div>
                </div>

                <div class="mb-2" id="view-plan-cancel-info" style="display: none;">
                    <div class="alert alert-secondary py-2 px-3 small border-0 mb-0">
                        <i class="bi bi-info-circle me-1"></i> <span id="view-plan-cancel-reason"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 border-top">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-sm btn-success" id="btn-view-plan-pay" style="display: none;">
                    <i class="bi bi-credit-card me-1"></i> Pagar Agora
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal 6: Pagar Fatura do Plano (PIX Copia e Cola / QR Code / Checkout) --}}
<div class="modal fade" id="modal-pay-plan-invoice" tabindex="-1" aria-labelledby="modalPayPlanTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3 border-bottom">
                <div>
                    <h5 class="modal-title fs-5 fw-bold text-dark mb-0" id="modalPayPlanTitle">
                        <i class="bi bi-qr-code me-2 text-success"></i>Pagar Mensalidade / Fatura
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Fatura <span id="pay-plan-number" class="fw-bold"></span></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4 text-center">
                {{-- Valor Destaque --}}
                <div class="mb-3">
                    <span class="text-muted small text-uppercase fw-semibold letter-spacing-1">Valor a Pagar</span>
                    <div class="fs-2 fw-bold text-success mt-1" id="pay-plan-amount">R$ 0,00</div>
                    <small class="text-muted" id="pay-plan-due">Vencimento: -</small>
                </div>

                {{-- QR Code PIX --}}
                <div id="pay-plan-qr-container" class="mb-3 p-3 bg-light rounded-3 border d-inline-block shadow-sm" style="display: none;">
                    <img id="pay-plan-qrcode-img" src="" alt="QR Code PIX" class="img-fluid" style="max-height: 180px;">
                    <div class="small text-muted mt-2"><i class="bi bi-phone me-1"></i>Escaneie com o app do seu banco</div>
                </div>

                {{-- Chave / Código PIX Copia e Cola --}}
                <div id="pay-plan-pix-container" class="mb-3 text-start" style="display: none;">
                    <label class="form-label small fw-bold text-uppercase text-muted letter-spacing-1">
                        <i class="bi bi-copy me-1 text-primary"></i>PIX Copia e Cola
                    </label>
                    <div class="input-group">
                        <input type="text" id="pay-plan-pix-code" class="form-control form-control-sm font-monospace" readonly>
                        <button class="btn btn-sm btn-primary" type="button" onclick="copyPlanPixCode()">
                            <i class="bi bi-clipboard me-1"></i>Copiar
                        </button>
                    </div>
                </div>

                {{-- Link Externo de Pagamento (Cartão / Boleto / Mercado Pago) --}}
                <div id="pay-plan-link-container" class="mt-3" style="display: none;">
                    <a href="#" id="pay-plan-checkout-btn" target="_blank" class="btn btn-primary w-100 shadow-sm py-2">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir Link de Pagamento (Cartão / Boleto)
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="location.reload()">
                    <i class="bi bi-check2-circle me-1"></i> Já efetuei o pagamento
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal 7: Dar Baixa Manual na Fatura (Individual / Parcelas Antecipadas / Carnê) --}}
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
                    {{-- Resumo da Fatura Selecionada --}}
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

                    {{-- Bloco Dinâmico de Parcelamento / Parcelas Antecipadas --}}
                    <div id="settle-installment-section" class="d-none mb-3">
                        <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <i class="bi bi-layers-fill text-primary fs-5"></i>
                                    <strong class="text-primary">Fatura com Parcelamento / Carnê Vinculado</strong>
                                    <span class="badge bg-primary ms-auto" id="settle-badge-installments">Parcela 1 de 6</span>
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

                                {{-- Lista com checkboxes para seleção personalizada --}}
                                <div id="settle-custom-installments-list" class="d-none mt-2 p-2 bg-body-tertiary rounded border small" style="max-height: 180px; overflow-y: auto;">
                                    {{-- Preenchido via JS com as parcelas abertas --}}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Dados do Recebimento --}}
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

{{-- Modal 8: Dar Baixa Manual em Lote (Múltiplas Faturas Selecionadas) --}}
<div class="modal fade" id="modal-bulk-settle-invoice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg border-0">
            <form id="frm-bulk-settle">
                @csrf
                <div class="modal-header bg-success text-white py-3">
                    <div>
                        <h5 class="modal-title fs-5 fw-bold mb-0">
                            <i class="bi bi-check2-all me-2"></i>Dar Baixa em Lote
                        </h5>
                        <p class="small mb-0 text-white-50">Liquide simultaneamente as faturas e parcelas selecionadas.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-success bg-success-subtle border-0 py-2 px-3 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Você está dando baixa em <strong id="bulk-settle-modal-count">0</strong> fatura(s) selecionada(s). Faturas já quitadas serão ignoradas.
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border mb-3">
                        <span class="text-muted small text-uppercase fw-bold">Valor Total Selecionado</span>
                        <span class="fs-4 fw-bold text-success" id="bulk-settle-modal-total">R$ 0,00</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Forma de Pagamento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="bulk-settle-gateway" required>
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
                            <input type="date" class="form-control form-control-sm" id="bulk-settle-paid-at" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Observações (opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="bulk-settle-notes" placeholder="Ex.: Quitação de parcelas antecipadas">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="bulk-settle-notify-whatsapp" checked>
                                <label class="form-check-label small fw-semibold" for="bulk-settle-notify-whatsapp">
                                    <i class="bi bi-whatsapp text-success me-1"></i>Enviar mensagem de confirmação via WhatsApp aos clientes
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success fw-semibold" id="btn-confirm-bulk-settle">
                        <i class="bi bi-check2-all me-1"></i>Confirmar Baixa em Lote
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

const anticipateModal = new bootstrap.Modal(document.getElementById('modal-anticipate'));
const mergeModal = new bootstrap.Modal(document.getElementById('modal-merge'));
const detailModal = new bootstrap.Modal(document.getElementById('modal-invoice-detail'));
const viewPlanModal = new bootstrap.Modal(document.getElementById('modal-view-plan-invoice'));
const payPlanModal = new bootstrap.Modal(document.getElementById('modal-pay-plan-invoice'));
const settleModal = new bootstrap.Modal(document.getElementById('modal-settle-invoice'));
const bulkSettleModal = new bootstrap.Modal(document.getElementById('modal-bulk-settle-invoice'));

// Seleção de Checkboxes e Barra de Ações em Lote
const selectAllCheckbox = document.getElementById('select-all-invoices');
const itemCheckboxes = document.querySelectorAll('.item-checkbox');
const bulkActionBar = document.getElementById('bulk-action-bar');
const bulkCountSpan = document.getElementById('bulk-count');
const bulkTotalSpan = document.getElementById('bulk-total-amount');
const btnBulkMerge = document.getElementById('btn-bulk-merge');

function updateBulkState() {
    const checkedBoxes = Array.from(document.querySelectorAll('.item-checkbox:checked'));
    const count = checkedBoxes.length;

    if (count > 0) {
        bulkActionBar.classList.remove('d-none');
        bulkCountSpan.textContent = count;

        let total = 0;
        let customerIds = new Set();

        checkedBoxes.forEach(cb => {
            total += parseFloat(cb.dataset.amount || 0);
            customerIds.add(cb.dataset.customerId);
        });

        bulkTotalSpan.textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Mesclar só fica disponível se >= 2 faturas
        if (count >= 2) {
            btnBulkMerge.removeAttribute('disabled');
        } else {
            btnBulkMerge.setAttribute('disabled', 'true');
        }
    } else {
        bulkActionBar.classList.add('d-none');
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.checked = (count === itemCheckboxes.length && itemCheckboxes.length > 0);
    }
}

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', () => {
        itemCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
        updateBulkState();
    });
}

itemCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateBulkState);
});

function deselectAllInvoices() {
    itemCheckboxes.forEach(cb => cb.checked = false);
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    updateBulkState();
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => parseInt(cb.value));
}

// -------------------------------------------------------------
// AÇÕES EM LOTE (Cancelar, Deletar, Antecipar)
// -------------------------------------------------------------
async function executeBulkAction(action) {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Swal.fire('Aviso', 'Selecione ao menos uma fatura.', 'warning');
        return;
    }

    let title = '';
    let text = '';
    let confirmBtn = '';
    let btnColor = '#0d6efd';

    if (action === 'cancel') {
        title = `Cancelar ${ids.length} fatura(s)?`;
        text = 'As faturas selecionadas terão seu status alterado para Cancelada.';
        confirmBtn = 'Sim, cancelar';
        btnColor = '#ffc107';
    } else if (action === 'delete') {
        title = `Excluir ${ids.length} fatura(s)?`;
        text = 'Esta ação é permanente. Faturas já pagas serão preservadas por segurança.';
        confirmBtn = 'Sim, excluir';
        btnColor = '#dc3545';
    }

    const confirm = await Swal.fire({
        icon: action === 'delete' ? 'warning' : 'question',
        title: title,
        text: text,
        showCancelButton: true,
        confirmButtonText: confirmBtn,
        cancelButtonText: 'Voltar',
        confirmButtonColor: btnColor,
    });

    if (!confirm.isConfirmed) return;

    try {
        const r = await fetch('{{ route("tenant.invoices.bulk") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, ids: ids })
        });
        const d = await r.json();

        if (r.ok && d.ok) {
            if (window.toast) window.toast.success(d.message);
            else Swal.fire('Sucesso', d.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            Swal.fire('Aviso', d.error || 'Não foi possível concluir a ação em lote.', 'warning');
        }
    } catch (e) {
        Swal.fire('Erro', 'Erro de rede ou conexão.', 'error');
    }
}

// -------------------------------------------------------------
// ANTECIPAR VENCIMENTO
// -------------------------------------------------------------
function setAnticipateDate(daysToAdd) {
    const d = new Date();
    d.setDate(d.getDate() + daysToAdd);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    document.getElementById('anticipate-date').value = `${yyyy}-${mm}-${dd}`;
}

function openBulkAnticipateModal() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    document.getElementById('anticipate-mode').value = 'bulk';
    document.getElementById('anticipate-desc').textContent = `Defina a nova data de vencimento para as ${ids.length} faturas selecionadas.`;
    setAnticipateDate(0);
    anticipateModal.show();
}

function openSingleAnticipate(id, number, currentDue) {
    document.getElementById('anticipate-mode').value = 'single';
    document.getElementById('anticipate-invoice-id').value = id;
    document.getElementById('anticipate-desc').textContent = `Alterar vencimento da fatura #${number}.`;
    if (currentDue) {
        document.getElementById('anticipate-date').value = currentDue;
    } else {
        setAnticipateDate(0);
    }
    anticipateModal.show();
}

document.getElementById('frm-anticipate').addEventListener('submit', async (e) => {
    e.preventDefault();
    const mode = document.getElementById('anticipate-mode').value;
    const dueDate = document.getElementById('anticipate-date').value;

    if (!dueDate) return;

    if (mode === 'bulk') {
        const ids = getSelectedIds();
        try {
            const r = await fetch('{{ route("tenant.invoices.bulk") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'anticipate', ids: ids, due_date: dueDate })
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                anticipateModal.hide();
                if (window.toast) window.toast.success(d.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                Swal.fire('Aviso', d.error || 'Erro ao antecipar faturas.', 'warning');
            }
        } catch (err) {
            Swal.fire('Erro', 'Erro de rede.', 'error');
        }
    } else {
        const id = document.getElementById('anticipate-invoice-id').value;
        try {
            const r = await fetch(`{{ url('/painel/faturas') }}/${id}/antecipar`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ due_date: dueDate })
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                anticipateModal.hide();
                if (window.toast) window.toast.success(d.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                Swal.fire('Aviso', d.error || 'Erro ao antecipar fatura.', 'warning');
            }
        } catch (err) {
            Swal.fire('Erro', 'Erro de rede.', 'error');
        }
    }
});

// -------------------------------------------------------------
// MESCLAR FATURAS
// -------------------------------------------------------------
function openMergeModal() {
    const checkedBoxes = Array.from(document.querySelectorAll('.item-checkbox:checked'));
    if (checkedBoxes.length < 2) {
        Swal.fire('Aviso', 'Selecione ao menos 2 faturas para mesclar.', 'warning');
        return;
    }

    // Validar se todas pertencem ao mesmo cliente
    const customerIds = new Set(checkedBoxes.map(cb => cb.dataset.customerId));
    if (customerIds.size > 1) {
        Swal.fire('Não permitido', 'Todas as faturas selecionadas devem pertencer ao mesmo cliente para serem mescladas.', 'error');
        return;
    }

    // Validar se há alguma paga
    const hasPaid = checkedBoxes.some(cb => cb.dataset.status === 'paid');
    if (hasPaid) {
        Swal.fire('Aviso', 'Faturas já quitadas não podem ser mescladas.', 'warning');
        return;
    }

    const customerName = checkedBoxes[0].dataset.customerName;
    document.getElementById('merge-customer-name').textContent = customerName;

    let total = 0;
    const listEl = document.getElementById('merge-invoices-list');
    listEl.innerHTML = '';

    checkedBoxes.forEach(cb => {
        const val = parseFloat(cb.dataset.amount || 0);
        total += val;
        const item = document.createElement('div');
        item.className = 'd-flex justify-content-between py-1 border-bottom';
        item.innerHTML = `<span><strong>${cb.dataset.number}</strong></span><span class="text-primary fw-bold">R$ ${val.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>`;
        listEl.appendChild(item);
    });

    document.getElementById('merge-total-amount').textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    setAnticipateDate(0);
    document.getElementById('merge-due-date').value = document.getElementById('anticipate-date').value;
    document.getElementById('merge-description').value = `Fatura unificada (${checkedBoxes.length} cobranças mescladas)`;

    mergeModal.show();
}

document.getElementById('frm-merge').addEventListener('submit', async (e) => {
    e.preventDefault();
    const ids = getSelectedIds();
    const dueDate = document.getElementById('merge-due-date').value;
    const description = document.getElementById('merge-description').value;

    try {
        const r = await fetch('{{ route("tenant.invoices.merge") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids, due_date: dueDate, description: description })
        });
        const d = await r.json();

        if (r.ok && d.ok) {
            mergeModal.hide();
            await Swal.fire('Sucesso!', d.message, 'success');
            location.reload();
        } else {
            Swal.fire('Aviso', d.error || 'Erro ao mesclar faturas.', 'warning');
        }
    } catch (err) {
        Swal.fire('Erro', 'Erro de conexão.', 'error');
    }
});

// -------------------------------------------------------------
// AÇÕES INDIVIDUAIS (Reenviar, Cancelar, Deletar, Detalhes)
// -------------------------------------------------------------
async function resendSingleInvoice(id, number, customerName, phone) {
    if (!phone) {
        Swal.fire({
            icon: 'warning',
            title: 'Cliente sem WhatsApp',
            text: `O cliente ${customerName} não possui número de WhatsApp cadastrado para receber a cobrança.`,
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const confirm = await Swal.fire({
        icon: 'question',
        title: `Reenviar cobrança da fatura #${number}?`,
        html: `Deseja reenviar a cobrança agora para <strong>${customerName}</strong> via WhatsApp?<br><small class="text-muted"><i class="bi bi-whatsapp text-success me-1"></i>${phone}</small>`,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-send me-1"></i> Sim, reenviar agora',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
    });
    if (!confirm.isConfirmed) return;

    if (window.toast) window.toast.info('Enviando cobrança para o WhatsApp...');

    try {
        const r = await fetch(`{{ url('/painel/faturas') }}/${id}/reenviar`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            if (window.toast) window.toast.success(d.message || 'Cobrança reenviada com sucesso!');
            else Swal.fire('Sucesso!', d.message, 'success');
        } else {
            Swal.fire('Aviso', d.error || d.message || 'Erro ao reenviar cobrança.', 'warning');
        }
    } catch (e) {
        Swal.fire('Erro', 'Falha ao conectar ao servidor para reenvio.', 'error');
    }
}

async function cancelSingleInvoice(id, number) {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: `Cancelar fatura #${number}?`,
        text: 'A fatura será marcada como Cancelada e seus envios pausados.',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#ffc107',
    });
    if (!confirm.isConfirmed) return;

    try {
        const r = await fetch(`{{ url('/painel/faturas') }}/${id}/cancelar`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            if (window.toast) window.toast.success(d.message);
            setTimeout(() => location.reload(), 800);
        } else {
            Swal.fire('Aviso', d.error || 'Erro ao cancelar fatura.', 'warning');
        }
    } catch (e) {
        Swal.fire('Erro', 'Erro de conexão.', 'error');
    }
}

async function deleteSingleInvoice(id, number) {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: `Excluir fatura #${number}?`,
        text: 'Esta ação não poderá ser desfeita.',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#dc3545',
    });
    if (!confirm.isConfirmed) return;

    try {
        const r = await fetch(`{{ url('/painel/faturas') }}/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            document.getElementById(`inv-row-${id}`)?.remove();
            if (window.toast) window.toast.success(d.message);
            else Swal.fire('Sucesso', d.message, 'success');
        } else {
            Swal.fire('Aviso', d.error || 'Erro ao excluir fatura.', 'warning');
        }
    } catch (e) {
        Swal.fire('Erro', 'Erro de conexão.', 'error');
    }
}

function formatPhoneBR(value) {
    if (!value) return '';
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('55') && digits.length > 11) {
        digits = digits.slice(2);
    }
    if (digits.length <= 10) {
        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/^(\(\d{2}\) \d{4})(\d)/, '$1-$2')
            .slice(0, 14);
    }
    return digits
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/^(\(\d{2}\) \d{5})(\d)/, '$1-$2')
        .slice(0, 15);
}

async function openInvoiceDetails(id) {
    try {
        const r = await fetch(`{{ url('/painel/faturas') }}/${id}`, {
            headers: { 'Accept': 'application/json' }
        });
        const d = await r.json();

        document.getElementById('detail-inv-number').textContent = d.number || '#' + d.id;
        document.getElementById('detail-customer-name').textContent = d.customer_name;
        document.getElementById('detail-customer-phone').textContent = d.customer_whatsapp ? 'WhatsApp: ' + formatPhoneBR(d.customer_whatsapp) : '';
        document.getElementById('detail-desc').textContent = d.description || 'Fatura #' + d.id;
        document.getElementById('detail-amount').textContent = 'R$ ' + d.amount.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('detail-status').value = d.status;
        document.getElementById('detail-due-date').value = d.due_date;
        document.getElementById('detail-paid-at').textContent = d.paid_at || '-';

        const payBtn = document.getElementById('detail-payment-btn');
        if (d.payment_url && d.status !== 'paid' && d.status !== 'canceled') {
            payBtn.href = d.payment_url;
            payBtn.classList.remove('d-none');
        } else {
            payBtn.classList.add('d-none');
        }

        const settleBtn = document.getElementById('detail-settle-btn');
        if (settleBtn) {
            if (d.status !== 'paid' && d.status !== 'canceled') {
                settleBtn.classList.remove('d-none');
                settleBtn.onclick = () => {
                    detailModal.hide();
                    openSettleModal(d.id, d.number, d.amount, d.customer_name);
                };
            } else {
                settleBtn.classList.add('d-none');
            }
        }

        const resendBtn = document.getElementById('detail-resend-btn');
        if (resendBtn) {
            if (d.status !== 'paid' && d.status !== 'canceled') {
                resendBtn.classList.remove('d-none');
                resendBtn.onclick = () => {
                    resendSingleInvoice(d.id, d.number, d.customer_name, d.customer_whatsapp);
                };
            } else {
                resendBtn.classList.add('d-none');
            }
        }

        const cancelBtn = document.getElementById('detail-cancel-btn');
        if (cancelBtn) {
            if (d.status !== 'paid' && d.status !== 'canceled') {
                cancelBtn.classList.remove('d-none');
                cancelBtn.onclick = () => {
                    detailModal.hide();
                    cancelSingleInvoice(d.id, d.number);
                };
            } else {
                cancelBtn.classList.add('d-none');
            }
        }

        document.getElementById('frm-detail-inv').dataset.id = d.id;
        detailModal.show();
    } catch (e) {
        Swal.fire('Erro', 'Erro ao carregar fatura.', 'error');
    }
}

document.getElementById('btn-save-detail').addEventListener('click', async () => {
    const id = document.getElementById('frm-detail-inv').dataset.id;
    const fd = new FormData(document.getElementById('frm-detail-inv'));
    
    try {
        const r = await fetch(`{{ url('/painel/faturas') }}/${id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            detailModal.hide();
            if (window.toast) window.toast.success('Fatura atualizada com sucesso.');
            setTimeout(() => location.reload(), 800);
        } else {
            Swal.fire('Aviso', d.error || d.message || 'Erro ao salvar alterações.', 'warning');
        }
    } catch (err) {
        Swal.fire('Erro', 'Erro de rede.', 'error');
    }
});

// ========================================================
// CONTROLE DO MODAL DE FATURA AVULSA (À VISTA & PARCELADO)
// ========================================================
let standaloneModal = null;
const standaloneModalEl = document.getElementById('modal-create-standalone-invoice');
if (standaloneModalEl) {
    standaloneModal = bootstrap.Modal.getInstance(standaloneModalEl) || new bootstrap.Modal(standaloneModalEl);
}

function openCreateStandaloneModal(customerId = null) {
    if (customerId && standaloneCustomer) {
        standaloneCustomer.value = customerId;
        standaloneCustomer.dispatchEvent(new Event('change'));
    }
    if (!standaloneModal && standaloneModalEl) {
        standaloneModal = bootstrap.Modal.getInstance(standaloneModalEl) || new bootstrap.Modal(standaloneModalEl);
    }
    if (standaloneModal) {
        standaloneModal.show();
    }
}
window.openCreateStandaloneModal = openCreateStandaloneModal;

const standaloneForm = document.getElementById('frm-create-standalone-invoice');
const standaloneAmount = document.getElementById('standalone_amount');
const standaloneInstallments = document.getElementById('standalone_installments');
const standaloneInterest = document.getElementById('standalone_interest_rate');
const standaloneDueDate = document.getElementById('standalone_first_due_date');
const standaloneCustomer = document.getElementById('standalone_customer_id');
const standaloneCustomerInfo = document.getElementById('standalone-customer-info');

// Máscara monetária BRL (R$ 0,00)
function formatCurrencyInput(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (!value) {
        e.target.value = '';
        updateStandalonePreview();
        return;
    }
    let floatVal = (parseInt(value, 10) / 100).toFixed(2);
    let parts = floatVal.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    e.target.value = parts.join(',');
    updateStandalonePreview();
}

if (standaloneAmount) {
    standaloneAmount.addEventListener('input', formatCurrencyInput);
}

// Atualização de dados do cliente selecionado
if (standaloneCustomer) {
    standaloneCustomer.addEventListener('change', () => {
        const selected = standaloneCustomer.options[standaloneCustomer.selectedIndex];
        if (!selected || !selected.value) {
            standaloneCustomerInfo.innerHTML = 'A fatura e o histórico de cobrança ficarão vinculados a este cliente.';
            return;
        }
        const wpp = selected.dataset.whatsapp;
        const email = selected.dataset.email;
        const doc = selected.dataset.doc;
        
        let details = [];
        if (wpp) details.push(`<i class="bi bi-whatsapp text-success me-1"></i>WhatsApp: <strong>${formatPhoneBR(wpp)}</strong>`);
        if (email) details.push(`<i class="bi bi-envelope text-secondary me-1"></i>${email}`);
        if (doc) details.push(`<i class="bi bi-card-text text-muted me-1"></i>Doc: ${doc}`);
        
        standaloneCustomerInfo.innerHTML = details.length > 0 
            ? details.join(' &nbsp;•&nbsp; ') 
            : 'Cliente sem WhatsApp cadastrado (o link pode ser gerado e enviado manualmente).';
    });
}

// Cálculo e Atualização Dinâmica da Simulação do Carnê
function updateStandalonePreview() {
    const rawVal = (standaloneAmount?.value || '').replace(/\./g, '').replace(',', '.');
    const baseAmount = parseFloat(rawVal) || 0;
    const inst = parseInt(standaloneInstallments?.value || '1', 10) || 1;
    const rate = parseFloat(standaloneInterest?.value || '0') || 0;

    const previewTotal = document.getElementById('standalone-preview-total');
    const previewBadge = document.getElementById('standalone-preview-badge');
    const previewInterestDetail = document.getElementById('standalone-preview-interest-detail');
    const previewList = document.getElementById('standalone-preview-list');

    if (baseAmount <= 0) {
        if (previewTotal) previewTotal.textContent = 'R$ 0,00';
        if (previewBadge) previewBadge.textContent = inst > 1 ? `${inst}x parcelas` : '1x À vista';
        if (previewInterestDetail) previewInterestDetail.textContent = 'Sem acréscimo de juros.';
        if (previewList) previewList.innerHTML = '<div class="text-muted fst-italic">Informe o valor acima para ver as parcelas...</div>';
        return;
    }

    // Fórmula de juros simples mensal
    const interestAmount = baseAmount * (rate / 100) * inst;
    const totalAmount = baseAmount + interestAmount;
    const portionAmount = totalAmount / inst;

    if (previewTotal) {
        previewTotal.textContent = totalAmount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    if (previewBadge) {
        if (inst === 1) {
            previewBadge.textContent = '1x À vista';
            previewBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        } else {
            previewBadge.textContent = `${inst}x de ` + portionAmount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            previewBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle';
        }
    }

    if (previewInterestDetail) {
        if (rate > 0) {
            previewInterestDetail.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i>Juros: +${interestAmount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })} (${rate}% a.m.)</span>`;
        } else {
            previewInterestDetail.textContent = 'Sem acréscimo de juros.';
        }
    }

    // Geração do preview das datas de vencimento de cada parcela
    let firstDueDateVal = standaloneDueDate?.value;
    let baseDate = firstDueDateVal ? new Date(firstDueDateVal + 'T12:00:00') : new Date();

    let listHtml = '';
    for (let i = 1; i <= inst; i++) {
        let parcelDate = new Date(baseDate);
        parcelDate.setMonth(parcelDate.getMonth() + (i - 1));
        
        const dateStr = parcelDate.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const valFormatted = portionAmount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        listHtml += `
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">
                <span class="text-dark fw-semibold">${i}ª Parcela</span>
                <span class="text-muted"><i class="bi bi-calendar3 me-1"></i>${dateStr}</span>
                <span class="fw-bold text-primary">${valFormatted}</span>
            </div>
        `;
    }

    if (previewList) {
        previewList.innerHTML = listHtml;
    }
}

// Gatilhos de recalculo
[standaloneInstallments, standaloneInterest, standaloneDueDate].forEach(el => {
    if (el) el.addEventListener('input', updateStandalonePreview);
    if (el) el.addEventListener('change', updateStandalonePreview);
});

// Auto-abertura via URL (?nova_avulsa=1 ou ?create_standalone=1)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('nova_avulsa') === '1' || urlParams.get('create_standalone') === '1') {
    const custId = urlParams.get('customer_id');
    if (custId && standaloneCustomer) {
        standaloneCustomer.value = custId;
        standaloneCustomer.dispatchEvent(new Event('change'));
    }
    standaloneModal.show();
}

// Submissão do Formulário de Fatura Avulsa via AJAX com SweetAlert2 & Toastify
if (standaloneForm) {
    standaloneForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const rawVal = (standaloneAmount?.value || '').replace(/\./g, '').replace(',', '.');
        const baseAmount = parseFloat(rawVal) || 0;
        if (baseAmount <= 0) {
            Swal.fire('Valor Inválido', 'Por favor, informe um valor maior que zero para a fatura.', 'warning');
            return;
        }

        const customerSelect = document.getElementById('standalone_customer_id');
        const selectedCustomerText = customerSelect?.options[customerSelect.selectedIndex]?.text || 'Cliente';
        const inst = parseInt(standaloneInstallments?.value || '1', 10) || 1;
        const submitBtn = document.getElementById('btn-submit-standalone');
        const submitText = document.getElementById('btn-submit-text');

        // Confirmação via SweetAlert2 (respeitando regra estrita: sem alert() nativo)
        const confirmResult = await Swal.fire({
            title: inst > 1 ? `Gerar Carnê de ${inst} parcelas?` : 'Gerar Fatura Avulsa à Vista?',
            html: `
                <div class="text-start small text-muted">
                    <p class="mb-1"><strong>Destinatário:</strong> ${selectedCustomerText}</p>
                    <p class="mb-1"><strong>Condição:</strong> ${inst > 1 ? `${inst} parcelas mensais` : 'À vista (1x)'}</p>
                    <p class="mb-0"><strong>Total da Cobrança:</strong> ${document.getElementById('standalone-preview-total')?.textContent || 'R$ 0,00'}</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check2 me-1"></i> Sim, Gerar Cobrança',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d'
        });

        if (!confirmResult.isConfirmed) {
            return;
        }

        // Estado de carregamento
        if (submitBtn) submitBtn.disabled = true;
        if (submitText) submitText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Gerando...';

        const fd = new FormData(standaloneForm);

        try {
            const resp = await fetch('{{ route("tenant.invoices.standalone.store") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await resp.json();

            if (resp.ok && data.ok) {
                standaloneModal.hide();
                standaloneForm.reset();

                if (window.toast) {
                    window.toast.success(data.message || 'Fatura(s) gerada(s) com sucesso!');
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Cobrança Gerada!',
                    text: data.message || 'A cobrança foi criada com sucesso e já está disponível.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#0d6efd',
                    timer: 2500,
                    timerProgressBar: true
                });

                // Recarrega a listagem para exibir a(s) nova(s) fatura(s)
                location.reload();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Não foi possível gerar',
                    text: data.error || data.message || 'Ocorreu um erro ao criar a cobrança.',
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro de Conexão',
                text: 'Não foi possível se comunicar com o servidor. Tente novamente em instantes.',
                confirmButtonColor: '#0d6efd'
            });
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (submitText) submitText.textContent = 'Gerar Fatura(s)';
        }
    });
}

// Funções para Faturas de Plano (Minha Assinatura)
async function viewPlanInvoice(id) {
    try {
        const resp = await fetch(`/painel/faturas/plano/${id}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await resp.json();
        if (!resp.ok || !data.ok) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.error || 'Não foi possível carregar os detalhes da fatura.',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        const inv = data.invoice || data;
        document.getElementById('view-plan-number').textContent = inv.number || '';
        document.getElementById('view-plan-amount').textContent = inv.amount_formatted || inv.formatted_amount || '';
        document.getElementById('view-plan-desc').textContent = inv.description || '';
        document.getElementById('view-plan-due-date').textContent = inv.due_date || '-';
        document.getElementById('view-plan-paid-at').textContent = inv.paid_at || '-';

        let statusBadge = '<span class="badge bg-secondary">Pendente</span>';
        if (inv.status === 'paid') statusBadge = '<span class="badge bg-success">Pago</span>';
        else if (inv.status === 'canceled') statusBadge = '<span class="badge bg-danger">Cancelado</span>';
        else if (inv.status === 'overdue') statusBadge = '<span class="badge bg-warning">Vencida</span>';
        document.getElementById('view-plan-status-badge').innerHTML = inv.status_badge || statusBadge;

        const cancelInfo = document.getElementById('view-plan-cancel-info');
        const cancelReason = inv.cancel_reason || inv.cancellation_reason || inv.meta?.cancellation_reason;
        if (inv.status === 'canceled' && cancelReason) {
            cancelInfo.style.display = 'block';
            document.getElementById('view-plan-cancel-reason').textContent = 'Motivo do cancelamento: ' + cancelReason;
        } else {
            cancelInfo.style.display = 'none';
        }

        const btnPay = document.getElementById('btn-view-plan-pay');
        if (inv.status === 'pending') {
            btnPay.style.display = 'inline-block';
            btnPay.onclick = () => {
                viewPlanModal.hide();
                payPlanInvoice(inv.id);
            };
        } else {
            btnPay.style.display = 'none';
        }

        viewPlanModal.show();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Falha ao buscar fatura. Tente novamente.',
            confirmButtonColor: '#0d6efd'
        });
    }
}

async function payPlanInvoice(id) {
    Swal.fire({
        title: 'Gerando Pagamento...',
        text: 'Obtendo dados de PIX e Checkout...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const resp = await fetch(`/painel/faturas/plano/${id}/pagar`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        });
        const data = await resp.json();
        Swal.close();

        if (!resp.ok || !data.ok) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: data.error || 'Não foi possível gerar a cobrança. Contate o suporte.',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        document.getElementById('pay-plan-number').textContent = data.number || '';
        document.getElementById('pay-plan-amount').textContent = data.amount_formatted || data.formatted_amount || '';
        document.getElementById('pay-plan-due').textContent = 'Vencimento: ' + (data.due_date || '-');

        const qrContainer = document.getElementById('pay-plan-qr-container');
        const qrImg = document.getElementById('pay-plan-qrcode-img');
        const qrSrc = data.pix_qr_code || data.qrcode_url;
        if (qrSrc) {
            qrImg.src = qrSrc.startsWith('data:') || qrSrc.startsWith('http') ? qrSrc : 'data:image/png;base64,' + qrSrc;
            qrContainer.style.display = 'inline-block';
        } else {
            qrContainer.style.display = 'none';
        }

        const pixContainer = document.getElementById('pay-plan-pix-container');
        const pixCode = document.getElementById('pay-plan-pix-code');
        const pixVal = data.pix_code || data.pix_copia_cola;
        if (pixVal) {
            pixCode.value = pixVal;
            pixContainer.style.display = 'block';
        } else {
            pixContainer.style.display = 'none';
        }

        const linkContainer = document.getElementById('pay-plan-link-container');
        const checkoutBtn = document.getElementById('pay-plan-checkout-btn');
        const payUrl = data.checkout_url || data.payment_url;
        if (payUrl) {
            checkoutBtn.href = payUrl;
            linkContainer.style.display = 'block';
        } else {
            linkContainer.style.display = 'none';
        }

        payPlanModal.show();
    } catch (err) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Não foi possível processar o pagamento no momento.',
            confirmButtonColor: '#0d6efd'
        });
    }
}

function copyPlanPixCode() {
    const input = document.getElementById('pay-plan-pix-code');
    if (!input || !input.value) return;
    navigator.clipboard.writeText(input.value).then(() => {
        if (window.toast) {
            window.toast.success('Código PIX copiado com sucesso!');
        } else {
            Swal.fire({
                icon: 'success',
                title: 'Copiado!',
                text: 'Código PIX Copia e Cola copiado para a área de transferência.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }).catch(() => {
        input.select();
        document.execCommand('copy');
        if (window.toast) {
            window.toast.success('Código PIX copiado com sucesso!');
        }
    });
}

async function cancelPlanInvoice(id, number) {
    const result = await Swal.fire({
        title: 'Solicitar Cancelamento?',
        text: `Deseja realmente solicitar o cancelamento da fatura ${number}?`,
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Informe o motivo do cancelamento (opcional)...',
        inputAttributes: {
            'aria-label': 'Motivo do cancelamento'
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Confirmar Cancelamento',
        cancelButtonText: 'Voltar',
        showLoaderOnConfirm: true,
        preConfirm: async (reason) => {
            try {
                const resp = await fetch(`/painel/faturas/plano/${id}/cancelar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ reason: reason || 'Cancelamento solicitado pelo usuário' })
                });
                const data = await resp.json();
                if (!resp.ok || !data.ok) {
                    Swal.showValidationMessage(data.error || 'Não foi possível cancelar a fatura.');
                    return false;
                }
                return data;
            } catch (err) {
                Swal.showValidationMessage('Falha ao comunicar com o servidor.');
                return false;
            }
        },
        allowOutsideClick: () => !Swal.isLoading()
    });

    if (result.isConfirmed && result.value) {
        if (window.toast) {
            window.toast.success(result.value.message || 'Fatura cancelada com sucesso!');
        }
        await Swal.fire({
            icon: 'success',
            title: 'Fatura Cancelada',
            text: result.value.message || 'A fatura foi cancelada com sucesso.',
            timer: 2000,
            showConfirmButton: false
        });
        location.reload();
    }
}

// ========================================================
// CONTROLE DE BAIXA MANUAL (INDIVIDUAL, ANTECIPADAS E LOTE)
// ========================================================
let currentSettleContext = null;
let currentSettleBaseAmount = 0;

async function openSettleModal(id, number, amount, customerName) {
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

    // Reset seções
    const section = document.getElementById('settle-installment-section');
    section.classList.add('d-none');
    document.getElementById('container-mode-antecipated').classList.add('d-none');
    document.getElementById('settle-custom-installments-list').classList.add('d-none');
    document.getElementById('settle-custom-installments-list').innerHTML = '';
    
    updateSettleModeUI();
    settleModal.show();

    // Busca contexto de parcelamento / parcelas antecipadas via AJAX
    try {
        const resp = await fetch(`{{ url('/painel/faturas') }}/${id}/contexto-parcelamento`, {
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

                // Opção 2: Parcelas antecipadas
                if (data.context.antecipated_count > 0) {
                    const antContainer = document.getElementById('container-mode-antecipated');
                    antContainer.classList.remove('d-none');
                    document.getElementById('settle-antecipated-count').textContent = data.context.antecipated_count;
                    const combinedAnt = currentSettleBaseAmount + data.context.total_antecipated_amount;
                    document.getElementById('settle-antecipated-total').textContent = 'R$ ' + combinedAnt.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Opção 3: Todas as parcelas pendentes
                document.getElementById('settle-all-count').textContent = data.context.open_count + 1;
                const combinedAll = currentSettleBaseAmount + data.context.total_open_amount;
                document.getElementById('settle-all-total').textContent = 'R$ ' + combinedAll.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Opção 4: Checkboxes personalizados
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
        const resp = await fetch(`{{ url('/painel/faturas') }}/${id}/dar-baixa`, {
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

// Ações em Lote: Modal de Baixa Manual em Lote
function openBulkSettleModal() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Swal.fire('Aviso', 'Selecione ao menos uma fatura para dar baixa.', 'warning');
        return;
    }

    const checkedBoxes = Array.from(document.querySelectorAll('.item-checkbox:checked'));
    let total = 0;
    checkedBoxes.forEach(cb => {
        total += parseFloat(cb.dataset.amount || 0);
    });

    document.getElementById('bulk-settle-modal-count').textContent = ids.length;
    document.getElementById('bulk-settle-modal-total').textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('bulk-settle-paid-at').value = new Date().toISOString().split('T')[0];
    document.getElementById('bulk-settle-notes').value = '';

    bulkSettleModal.show();
}

document.getElementById('frm-bulk-settle').addEventListener('submit', async (e) => {
    e.preventDefault();
    const ids = getSelectedIds();
    if (ids.length === 0) return;

    const gateway = document.getElementById('bulk-settle-gateway').value;
    const paidAt = document.getElementById('bulk-settle-paid-at').value;
    const notes = document.getElementById('bulk-settle-notes').value;
    const notifyWhatsApp = document.getElementById('bulk-settle-notify-whatsapp').checked;

    const confirm = await Swal.fire({
        icon: 'question',
        title: `Dar baixa em ${ids.length} fatura(s)?`,
        text: 'Todas as faturas selecionadas serão marcadas como Pagas.',
        showCancelButton: true,
        confirmButtonText: 'Sim, confirmar baixa em lote',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d'
    });

    if (!confirm.isConfirmed) return;

    const btn = document.getElementById('btn-confirm-bulk-settle');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processando...';

    try {
        const resp = await fetch('{{ route("tenant.invoices.bulk") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'settle',
                ids: ids,
                gateway: gateway,
                paid_at: paidAt,
                notes: notes,
                notify_customer: notifyWhatsApp
            })
        });

        const data = await resp.json();
        if (resp.ok && data.ok) {
            bulkSettleModal.hide();
            if (window.toast) {
                window.toast.success(data.message || 'Baixa em lote realizada com sucesso!');
            }
            await Swal.fire({
                icon: 'success',
                title: 'Baixa em Lote Confirmada!',
                text: data.message || 'Faturas baixadas com sucesso.',
                timer: 2000,
                showConfirmButton: false
            });
            location.reload();
        } else {
            Swal.fire('Aviso', data.error || data.message || 'Não foi possível concluir a ação.', 'warning');
        }
    } catch (err) {
        Swal.fire('Erro', 'Falha ao comunicar com o servidor.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Confirmar Baixa em Lote';
    }
});

// ========================================================
// CONTROLE DE EXPANSÃO E AGRUPAMENTO DE CARNÊS
// ========================================================
document.addEventListener('DOMContentLoaded', function () {
    // Alternar exibição de um carnê individual
    document.querySelectorAll('.btn-toggle-carne').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const batchId = this.getAttribute('data-batch-id');
            const detailsRow = document.getElementById('carne-details-' + batchId);
            const chevron = document.getElementById('chevron-' + batchId);
            const btnText = document.getElementById('btn-text-' + batchId);

            if (!detailsRow) return;

            if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                detailsRow.style.display = 'table-row';
                if (chevron) {
                    chevron.classList.remove('bi-chevron-down');
                    chevron.classList.add('bi-chevron-up');
                }
                if (btnText) {
                    btnText.textContent = 'Ocultar Parcelas';
                }
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            } else {
                detailsRow.style.display = 'none';
                if (chevron) {
                    chevron.classList.remove('bi-chevron-up');
                    chevron.classList.add('bi-chevron-down');
                }
                if (btnText) {
                    const total = detailsRow.querySelectorAll('tbody tr').length;
                    btnText.textContent = 'Ver ' + total + ' Parcelas';
                }
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');
            }
        });
    });

    // Expandir / Recolher todos os carnês da página
    const btnToggleAll = document.getElementById('btn-toggle-all-carnes');
    if (btnToggleAll) {
        let allOpen = false;
        btnToggleAll.addEventListener('click', function () {
            allOpen = !allOpen;
            const detailsRows = document.querySelectorAll('.carne-details-row');
            detailsRows.forEach(row => {
                row.style.display = allOpen ? 'table-row' : 'none';
            });

            document.querySelectorAll('.btn-toggle-carne').forEach(btn => {
                const batchId = btn.getAttribute('data-batch-id');
                const chevron = document.getElementById('chevron-' + batchId);
                const btnText = document.getElementById('btn-text-' + batchId);
                const detailsRow = document.getElementById('carne-details-' + batchId);
                const total = detailsRow ? detailsRow.querySelectorAll('tbody tr').length : '';

                if (allOpen) {
                    if (chevron) {
                        chevron.classList.remove('bi-chevron-down');
                        chevron.classList.add('bi-chevron-up');
                    }
                    if (btnText) btnText.textContent = 'Ocultar Parcelas';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                } else {
                    if (chevron) {
                        chevron.classList.remove('bi-chevron-up');
                        chevron.classList.add('bi-chevron-down');
                    }
                    if (btnText) btnText.textContent = 'Ver ' + total + ' Parcelas';
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-primary');
                }
            });

            const txtAll = document.getElementById('txt-toggle-all');
            if (txtAll) {
                txtAll.textContent = allOpen ? 'Recolher Todos' : 'Expandir Todos';
            }
        });
    }

    // Checkbox Master do Carnê: marca/desmarca todas as parcelas do lote
    document.querySelectorAll('.carne-master-checkbox').forEach(masterCb => {
        masterCb.addEventListener('change', function () {
            const batchId = this.getAttribute('data-batch-id');
            const itemCbs = document.querySelectorAll('.carne-item-cb-' + batchId);
            itemCbs.forEach(cb => {
                cb.checked = masterCb.checked;
            });
            if (typeof updateBulkState === 'function') {
                updateBulkState();
            }
        });
    });

    // Ao selecionar parcelas individuais, atualiza o checkbox master do lote
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
            if (typeof updateBulkState === 'function') {
                updateBulkState();
            }
        });
    });
});
</script>
@endpush
