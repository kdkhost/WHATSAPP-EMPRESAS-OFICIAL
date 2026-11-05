@extends('tenancy::tenant.layout')
@section('title', 'Pacotes')

@section('content')
@php
    $activePackages = $packages->where('active', true)->count();
    $linkedCustomers = $subscriptions->pluck('customer_id')->unique()->count();
    $pendingTotal = $subscriptions->flatMap->installments->whereIn('status', ['pending', 'overdue'])->sum('amount');
@endphp

<div class="tenant-premium-hero mb-4">
    <div>
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-box-seam me-2"></i>Pacotes comerciais</span>
        <h3 class="tenant-premium-hero__title">Pacotes e parcelas dos clientes</h3>
        <p class="tenant-premium-hero__text">Crie um pacote com valor integral, configure pagamento a vista ou parcelado, repasse opcional de taxas e gere as faturas do cliente automaticamente.</p>
    </div>
    <div class="row g-3">
        <div class="col-sm-4">
            <div class="tenant-premium-metric">
                <div class="text-muted small text-uppercase fw-semibold">Pacotes ativos</div>
                <div class="fs-4 fw-bold">{{ $activePackages }}</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="tenant-premium-metric">
                <div class="text-muted small text-uppercase fw-semibold">Clientes vinculados</div>
                <div class="fs-4 fw-bold text-primary">{{ $linkedCustomers }}</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="tenant-premium-metric">
                <div class="text-muted small text-uppercase fw-semibold">Em aberto</div>
                <div class="fs-4 fw-bold text-warning">{{ \App\Support\Format::money($pendingTotal) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="tenant-premium-panel">
            <div class="tenant-premium-panel__header">
                <div>
                    <span class="tenant-premium-kicker">Novo produto</span>
                    <h5 class="tenant-premium-title">Criar pacote</h5>
                </div>
            </div>
            <div class="tenant-premium-panel__body">
                <form id="frm-package" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nome do pacote</label>
                        <input name="name" class="form-control" required maxlength="160" placeholder="Ex.: Marketing 360">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descricao do servico</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="20000" placeholder="Descricao clara do escopo contratado"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valor integral</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input name="total_amount" class="form-control" data-mask="decimal" inputmode="decimal" required placeholder="97,90">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Max. parcelas</label>
                        <input name="max_installments" type="number" min="1" max="24" class="form-control" value="12" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vencimento</label>
                        <select name="billing_interval_days" class="form-select" required>
                            <option value="30" selected>Mensal (30d)</option>
                            <option value="7">Semanal (7d)</option>
                            <option value="15">Quinzenal (15d)</option>
                            <option value="60">Bimestral (60d)</option>
                            <option value="90">Trimestral (90d)</option>
                            <option value="180">Semestral (180d)</option>
                            <option value="365">Anual (365d)</option>
                            <option value="730">Bienal (730d)</option>
                            <option value="1095">Trienal (1095d)</option>
                            <option value="0">Vitalício (Pagamento único)</option>
                            <option value="-1">Permuta (Parceria / Cortesia)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="active" class="form-select">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_cash" value="1" id="allow-cash" checked>
                                <label class="form-check-label" for="allow-cash">A vista</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_installments" value="1" id="allow-installments" checked>
                                <label class="form-check-label" for="allow-installments">Parcelado</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Taxa do gateway</label>
                        <select name="gateway_fee_mode" class="form-select">
                            <option value="tenant">Absorver</option>
                            <option value="customer">Repassar</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">% taxa</label>
                        <input name="gateway_fee_percent" class="form-control" data-mask="decimal" inputmode="decimal" value="0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Taxa fixa</label>
                        <input name="gateway_fixed_fee" class="form-control" data-mask="decimal" inputmode="decimal" value="0,00">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Juros por parcela (%)</label>
                        <input name="installment_interest_percent" class="form-control" data-mask="decimal" inputmode="decimal" value="0,00">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Salvar pacote</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="tenant-premium-panel">
            <div class="tenant-premium-panel__header">
                <div>
                    <span class="tenant-premium-kicker">Contratacao</span>
                    <h5 class="tenant-premium-title">Vincular pacote ao cliente</h5>
                </div>
            </div>
            <div class="tenant-premium-panel__body">
                <form id="frm-assign-package" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} @if($customer->whatsapp)- {{ \App\Support\Format::phone($customer->whatsapp) }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pacote</label>
                        <select name="tenant_package_id" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach($packages->where('active', true) as $package)
                                <option value="{{ $package->id }}">{{ $package->name }} - {{ \App\Support\Format::money($package->total_amount) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Forma</label>
                        <select name="payment_mode" class="form-select">
                            <option value="cash">A vista</option>
                            <option value="installment">Parcelado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Parcelas</label>
                        <input name="installments_count" type="number" min="1" max="24" class="form-control" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">1o vencimento</label>
                        <input name="first_due_date" type="date" class="form-control" value="{{ now('America/Sao_Paulo')->addDays(3)->toDateString() }}" required>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-success" type="submit"><i class="bi bi-link-45deg me-1"></i>Vincular e gerar faturas</button>
                    </div>
                </form>

                <hr class="my-3 opacity-25">

                <div class="row g-2 text-center">
                    <div class="col-3">
                        <div class="p-2 rounded bg-primary-subtle">
                            <div class="small text-muted">Ativas</div>
                            <div class="fw-bold fs-5 text-primary">{{ $subscriptions->where('status', 'active')->count() }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded bg-success-subtle">
                            <div class="small text-muted">Quitadas</div>
                            <div class="fw-bold fs-5 text-success">{{ $subscriptions->where('status', 'completed')->count() }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded bg-secondary-subtle">
                            <div class="small text-muted">Canceladas</div>
                            <div class="fw-bold fs-5 text-secondary">{{ $subscriptions->where('status', 'cancelled')->count() }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded bg-danger-subtle">
                            <div class="small text-muted">Vencidas</div>
                            <div class="fw-bold fs-5 text-danger">{{ $subscriptions->where('status', 'overdue')->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="tenant-premium-panel mb-4">
    <div class="tenant-premium-panel__header">
        <div>
            <span class="tenant-premium-kicker">Historico recente</span>
            <h5 class="tenant-premium-title">Contratacoes</h5>
        </div>
        <span class="badge bg-primary-subtle text-primary border">{{ $subscriptions->count() }} total</span>
    </div>
    <div class="tenant-premium-panel__body p-0">
        <div>
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-tenant-package-subs" data-export-title="Contratações de Pacotes" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Cliente</th>
                        <th class="all" data-priority="1">Pacote</th>
                        <th class="min-tablet text-end" data-priority="2">Valor</th>
                        <th class="desktop">Pagamento</th>
                        <th class="desktop">Próx. vencimento</th>
                        <th class="min-tablet">Status</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 130px; min-width: 120px; max-width: 140px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $sub)
                        @php
                            $statusMap = [
                                'active' => ['bg' => 'primary', 'text' => 'Ativo'],
                                'completed' => ['bg' => 'success', 'text' => 'Quitado'],
                                'cancelled' => ['bg' => 'secondary', 'text' => 'Cancelado'],
                                'overdue' => ['bg' => 'danger', 'text' => 'Vencido'],
                            ];
                            $statusInfo = $statusMap[$sub->status] ?? ['bg' => 'primary', 'text' => $sub->status];
                            $editsRemaining = max(0, 2 - ($sub->edits_count ?? 0));
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $sub->customer?->name }}</div>
                                <small class="text-muted">{{ $sub->customer?->whatsapp ? \App\Support\Format::phone($sub->customer->whatsapp) : '' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $sub->package?->name }}</div>
                                <small class="text-muted">{{ $sub->installments_count }}x {{ \App\Support\Format::money($sub->total_amount / max($sub->installments_count, 1)) }}</small>
                            </td>
                            <td class="text-end fw-semibold">{{ \App\Support\Format::money($sub->final_amount) }}</td>
                            <td>
                                @if($sub->payment_mode === 'cash')
                                    <span class="badge bg-success-subtle text-success border">A vista</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border">{{ $sub->installments_count }}x</span>
                                @endif
                            </td>
                            <td>{{ $sub->next_due_date ? \App\Support\Format::date($sub->next_due_date) : '-' }}</td>
                            <td>
                                <span class="badge bg-{{ $statusInfo['bg'] }}-subtle text-{{ $statusInfo['bg'] }} border">{{ $statusInfo['text'] }}</span>
                                <small class="text-muted d-block mt-1">{{ $editsRemaining }} edicoes restantes</small>
                            </td>
                            <td class="all text-end no-export" style="width: 130px; min-width: 120px; max-width: 140px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    @if($editsRemaining > 0 && $sub->status !== 'cancelled' && $sub->status !== 'completed')
                                        <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2 js-edit-sub" title="Editar contratação" data-id="{{ $sub->id }}"><i class="bi bi-pencil"></i></button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2 text-muted" title="Edição indisponível" disabled style="cursor:not-allowed;opacity:.5;"><i class="bi bi-pencil"></i></button>
                                    @endif
                                    @if($sub->status !== 'cancelled' && $sub->status !== 'completed')
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 js-cancel-sub" title="Cancelar contratação" data-id="{{ $sub->id }}" data-customer="{{ $sub->customer?->name }}"><i class="bi bi-x-circle"></i></button>
                                    @endif
                                    <a href="{{ route('tenant.invoices') }}?customer={{ $sub->customer_id }}" class="btn btn-sm btn-outline-info p-1 px-2" title="Ver faturas do cliente">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pacote vinculado ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tenant-premium-panel">
    <div class="tenant-premium-panel__header">
        <div>
            <span class="tenant-premium-kicker">Tabela de pacotes</span>
            <h5 class="tenant-premium-title">Pacotes cadastrados</h5>
        </div>
        <span class="badge bg-success-subtle text-success border">{{ $packages->count() }} total</span>
    </div>
    <div class="tenant-premium-panel__body p-0">
        <div>
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-tenant-packages-list" data-export-title="Pacotes Cadastrados" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Pacote</th>
                        <th class="min-tablet text-end" data-priority="2">Valor</th>
                        <th class="desktop">Opções</th>
                        <th class="desktop">Taxas</th>
                        <th class="min-tablet">Status</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 100px; min-width: 90px; max-width: 110px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($packages as $package)
                        <tr data-pkg-id="{{ $package->id }}">
                            <td>
                                <div class="fw-semibold">{{ $package->name }}</div>
                                <small class="text-muted">{{ $package->subscriptions_count }} contratacao(oes)</small>
                            </td>
                            <td class="text-end fw-semibold">{{ \App\Support\Format::money($package->total_amount) }}</td>
                            <td>
                                @if($package->allow_cash)<span class="badge bg-success-subtle text-success border">A vista</span>@endif
                                @if($package->allow_installments)<span class="badge bg-primary-subtle text-primary border">Ate {{ $package->max_installments }}x</span>@endif
                                @php
                                    $intervalDays = (int) ($package->settings['billing_interval_days'] ?? 30);
                                    $intervalLabels = [
                                        -1 => 'Permuta',
                                        0 => 'Vitalício',
                                        7 => 'Semanal (7d)',
                                        15 => 'Quinzenal (15d)',
                                        30 => 'Mensal (30d)',
                                        60 => 'Bimestral (60d)',
                                        90 => 'Trimestral (90d)',
                                        180 => 'Semestral (180d)',
                                        365 => 'Anual (365d)',
                                        730 => 'Bienal (730d)',
                                        1095 => 'Trienal (1095d)',
                                    ];
                                    $intervalLabel = $intervalLabels[$intervalDays] ?? 'Mensal (30d)';
                                    $intervalBadgeClass = match($intervalDays) {
                                        0 => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                        -1 => 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle',
                                        default => 'bg-info-subtle text-info border'
                                    };
                                @endphp
                                <span class="badge {{ $intervalBadgeClass }}">{{ $intervalLabel }}</span>
                            </td>
                            <td>
                                <small class="text-muted d-block">{{ $package->gateway_fee_mode === 'customer' ? 'Repasse ao cliente' : 'Absorvida pelo tenant' }}</small>
                                <small>{{ number_format((float) $package->gateway_fee_percent, 2, ',', '.') }}% + {{ \App\Support\Format::money($package->gateway_fixed_fee) }}</small>
                            </td>
                            <td><span class="badge bg-{{ $package->active ? 'success' : 'secondary' }}-subtle text-{{ $package->active ? 'success' : 'secondary' }} border">{{ $package->active ? 'Ativo' : 'Inativo' }}</span></td>
                            <td class="all text-end no-export" style="width: 100px; min-width: 90px; max-width: 110px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2 js-edit-pkg" title="Editar" data-id="{{ $package->id }}"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 js-del-pkg" title="Excluir" data-id="{{ $package->id }}"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Crie o primeiro pacote para vender servicos aos seus clientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Editar Pacote -->
<div class="modal fade" id="modal-edit-pkg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar pacote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="frm-edit-package" class="row g-3">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="col-12">
                        <label class="form-label">Nome do pacote</label>
                        <input name="name" class="form-control" required maxlength="160">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descricao do servico</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="20000"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valor integral</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input name="total_amount" class="form-control" data-mask="decimal" inputmode="decimal" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Maximo de parcelas</label>
                        <input name="max_installments" type="number" min="1" max="24" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Intervalo de vencimento</label>
                        <select name="billing_interval_days" class="form-select" required>
                            <option value="30">Mensal (30 dias)</option>
                            <option value="7">Semanal (7 dias)</option>
                            <option value="15">Quinzenal (15 dias)</option>
                            <option value="60">Bimestral (60 dias)</option>
                            <option value="90">Trimestral (90 dias)</option>
                            <option value="180">Semestral (180 dias)</option>
                            <option value="365">Anual (365 dias)</option>
                            <option value="730">Bienal (730 dias)</option>
                            <option value="1095">Trienal (1095 dias)</option>
                            <option value="0">Vitalício (Pagamento único)</option>
                            <option value="-1">Permuta (Parceria / Cortesia)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="allow_cash" value="1" id="edit-allow-cash">
                            <label class="form-check-label" for="edit-allow-cash">A vista</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="allow_installments" value="1" id="edit-allow-installments">
                            <label class="form-check-label" for="edit-allow-installments">Parcelado</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Taxa do gateway</label>
                        <select name="gateway_fee_mode" class="form-select">
                            <option value="tenant">Absorver no tenant</option>
                            <option value="customer">Repassar ao cliente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">% taxa</label>
                        <input name="gateway_fee_percent" class="form-control" data-mask="decimal" inputmode="decimal" value="0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Taxa fixa</label>
                        <input name="gateway_fixed_fee" class="form-control" data-mask="decimal" inputmode="decimal" value="0,00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Juros por parcela (%)</label>
                        <input name="installment_interest_percent" class="form-control" data-mask="decimal" inputmode="decimal" value="0,00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="active" class="form-select">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-save-edit">Salvar alteracoes</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Contratacao -->
<div class="modal fade" id="modal-edit-sub" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar contratacao</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="frm-edit-sub" class="row g-3">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active">Ativo</option>
                            <option value="completed">Quitado</option>
                            <option value="cancelled">Cancelado</option>
                            <option value="overdue">Vencido</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Proximo vencimento</label>
                        <input name="next_due_date" type="date" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Quantidade de parcelas</label>
                        <input name="installments_count" type="number" min="1" max="24" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-save-sub">Salvar alteracoes</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelar Contratacao -->
<div class="modal fade" id="modal-cancel-sub" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar cancelamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Deseja cancelar a contratacao de <strong id="cancel-customer-name"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i> Esta acao ira cancelar todas as faturas pendentes vinculadas.
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notify-customer" checked>
                    <label class="form-check-label" for="notify-customer">Notificar cliente via WhatsApp sobre o cancelamento</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-cancel">Confirmar cancelamento</button>
            </div>
        </div>
    </div>
</div>
@endsection

@php
    $packagesJson = $packages->keyBy('id')->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'total_amount' => number_format((float) $p->total_amount, 2, ',', '.'),
            'allow_cash' => $p->allow_cash,
            'allow_installments' => $p->allow_installments,
            'max_installments' => $p->max_installments,
            'gateway_fee_mode' => $p->gateway_fee_mode,
            'gateway_fee_percent' => number_format((float) $p->gateway_fee_percent, 2, ',', '.'),
            'gateway_fixed_fee' => number_format((float) $p->gateway_fixed_fee, 2, ',', '.'),
            'installment_interest_percent' => number_format((float) $p->installment_interest_percent, 2, ',', '.'),
            'active' => $p->active,
            'billing_interval_days' => $p->settings['billing_interval_days'] ?? 30,
        ];
    });
@endphp
@push('scripts')
<script>
const packagesData = @json($packagesJson);

const editModal = new bootstrap.Modal(document.getElementById('modal-edit-pkg'));
let editingId = null;

document.querySelectorAll('.js-edit-pkg').forEach(btn => {
    btn.addEventListener('click', () => {
        editingId = btn.dataset.id;
        const pkg = packagesData[editingId];
        if (!pkg) return;
        const form = document.getElementById('frm-edit-package');
        form.querySelector('[name="name"]').value = pkg.name;
        form.querySelector('[name="description"]').value = pkg.description || '';
        form.querySelector('[name="total_amount"]').value = pkg.total_amount;
        form.querySelector('[name="allow_cash"]').checked = pkg.allow_cash;
        form.querySelector('[name="allow_installments"]').checked = pkg.allow_installments;
        form.querySelector('[name="max_installments"]').value = pkg.max_installments;
        form.querySelector('[name="gateway_fee_mode"]').value = pkg.gateway_fee_mode;
        form.querySelector('[name="gateway_fee_percent"]').value = pkg.gateway_fee_percent;
        form.querySelector('[name="gateway_fixed_fee"]').value = pkg.gateway_fixed_fee;
        form.querySelector('[name="installment_interest_percent"]').value = pkg.installment_interest_percent;
        form.querySelector('[name="active"]').value = pkg.active ? '1' : '0';
        form.querySelector('[name="billing_interval_days"]').value = pkg.billing_interval_days;
        editModal.show();
    });
});

document.querySelectorAll('.js-del-pkg').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const result = await Swal.fire({
            title: 'Excluir pacote?',
            text: 'Esta acao nao pode ser desfeita.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
        });
        if (!result.isConfirmed) return;
        const r = await fetch('{{ url("/painel/pacotes") }}/' + id, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ _method: 'DELETE' })
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            window.toast.success('Pacote excluido.');
            setTimeout(() => location.reload(), 600);
        } else {
            window.toast.error(d.error || d.message || 'Erro ao excluir pacote.');
        }
    });
});

document.getElementById('btn-save-edit').addEventListener('click', async () => {
    if (!editingId) return;
    const form = document.getElementById('frm-edit-package');
    const fd = new FormData(form);
    ['allow_cash', 'allow_installments'].forEach(key => {
        if (!fd.has(key)) fd.set(key, '0');
    });
    const r = await fetch('{{ url("/painel/pacotes") }}/' + editingId, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd
    });
    const d = await r.json();
    if (r.ok && d.ok) {
        window.toast.success('Pacote atualizado.');
        editModal.hide();
        setTimeout(() => location.reload(), 600);
    } else {
        window.toast.error(d.error || d.message || 'Erro ao atualizar pacote.');
    }
});

// --- Editar contratacao ---
const subModal = new bootstrap.Modal(document.getElementById('modal-edit-sub'));
let editingSubId = null;

document.querySelectorAll('.js-edit-sub').forEach(btn => {
    btn.addEventListener('click', () => {
        editingSubId = btn.dataset.id;
        const row = btn.closest('tr');
        const statusText = row.querySelector('td:nth-child(6) .badge').textContent.trim();
        const statusMap = { 'Ativo': 'active', 'Quitado': 'completed', 'Cancelado': 'cancelled', 'Vencido': 'overdue' };
        const venc = row.querySelector('td:nth-child(5)').textContent.trim();
        const parcText = row.querySelector('td:nth-child(4)').textContent.trim();
        const parcMatch = parcText.match(/(\d+)x/);

        document.getElementById('frm-edit-sub').querySelector('[name="status"]').value = statusMap[statusText] || 'active';
        document.getElementById('frm-edit-sub').querySelector('[name="next_due_date"]').value = venc === '-' ? '' : venc.split('/').reverse().join('-');
        document.getElementById('frm-edit-sub').querySelector('[name="installments_count"]').value = parcMatch ? parcMatch[1] : '';
        subModal.show();
    });
});

document.getElementById('btn-save-sub').addEventListener('click', async () => {
    if (!editingSubId) return;
    const form = document.getElementById('frm-edit-sub');
    const fd = new FormData(form);
    const r = await fetch('{{ url("/painel/contratacoes") }}/' + editingSubId, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd
    });
    const d = await r.json();
    if (r.ok && d.ok) {
        window.toast.success('Contratacao atualizada.');
        subModal.hide();
        setTimeout(() => location.reload(), 600);
    } else {
        window.toast.error(d.error || d.message || 'Erro ao atualizar contratacao.');
    }
});

// --- Cancelar contratacao ---
const cancelModal = new bootstrap.Modal(document.getElementById('modal-cancel-sub'));
let cancellingSubId = null;

document.querySelectorAll('.js-cancel-sub').forEach(btn => {
    btn.addEventListener('click', () => {
        cancellingSubId = btn.dataset.id;
        document.getElementById('cancel-customer-name').textContent = btn.dataset.customer || 'este cliente';
        document.getElementById('notify-customer').checked = true;
        cancelModal.show();
    });
});

document.getElementById('btn-confirm-cancel').addEventListener('click', async (e) => {
    if (!cancellingSubId) return;
    const btn = e.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Cancelando...';
    btn.disabled = true;

    try {
        const notify = document.getElementById('notify-customer').checked;
        const r = await fetch('{{ url("/painel/contratacoes") }}/' + cancellingSubId, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ _method: 'DELETE', notify_customer: notify })
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            window.toast.success('Contratacao cancelada' + (notify ? ' e cliente notificado.' : '.'));
            cancelModal.hide();
            setTimeout(() => location.reload(), 600);
        } else {
            window.toast.error(d.error || d.message || 'Erro ao cancelar contratacao.');
        }
    } catch (err) {
        window.toast.error('Erro de conexao. Tente novamente.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});

document.getElementById('frm-package').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    const fd = new FormData(form);
    ['allow_cash', 'allow_installments'].forEach(key => {
        if (!fd.has(key)) fd.set(key, '0');
    });

    const r = await fetch('{{ route("tenant.packages.store") }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd
    });
    const d = await r.json();
    if (r.ok && d.ok) {
        window.toast.success('Pacote criado.');
        setTimeout(() => location.reload(), 600);
    } else {
        window.toast.error(d.error || d.message || 'Erro ao salvar pacote.');
    }
});

document.getElementById('frm-assign-package').addEventListener('submit', async event => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const r = await fetch('{{ route("tenant.packages.assign") }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd
    });
    const d = await r.json();
    if (r.ok && d.ok) {
        window.toast.success('Pacote vinculado e faturas geradas.');
        setTimeout(() => location.reload(), 600);
    } else {
        window.toast.error(d.error || d.message || 'Erro ao vincular pacote.');
    }
});
</script>
@endpush
