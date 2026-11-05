@extends('admin.layouts.app')
@section('title', 'Programa de Afiliados')

@push('styles')
<style>
.kpi-card-affiliate {
    border-radius: 14px;
    transition: transform .2s ease, box-shadow .2s ease;
}
.kpi-card-affiliate:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.kpi-icon-aff {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.nav-tabs-affiliates {
    border-bottom: 2px solid var(--bs-border-color);
    gap: .5rem;
}
.nav-tabs-affiliates .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--bs-secondary-color);
    font-weight: 600;
    padding: .75rem 1.25rem;
    border-radius: 0;
    transition: all .2s;
    background: transparent;
}
.nav-tabs-affiliates .nav-link:hover {
    color: var(--bs-primary);
    border-bottom-color: rgba(var(--bs-primary-rgb), 0.4);
}
.nav-tabs-affiliates .nav-link.active {
    color: var(--bs-primary);
    border-bottom-color: var(--bs-primary);
    background: transparent;
}
.chart-container-aff {
    position: relative;
    min-height: 250px;
}
</style>
@endpush

@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Programa de Afiliados"
        subtitle="Gerencie regras globais de comissões, descontos para novos clientes, taxas de recorrência, ranking de divulgação e métricas interativas de cliques e conversões."
        eyebrow="Superadmin"
        class="mb-4"
    />

    {{-- ========== TOP KPIS GERAIS ========== --}}
    <div class="row g-3 mb-4">
        {{-- Total Afiliados --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-affiliate border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-aff bg-primary-subtle text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Afiliados Cadastrados</div>
                        <div class="fs-4 fw-bold text-truncate">{{ number_format($kpis['total_affiliates'], 0, ',', '.') }}</div>
                        <div class="small text-muted mt-1">
                            <span class="text-success fw-semibold">{{ $kpis['active_affiliates'] }} ativos</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cliques & Visitas --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-affiliate border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-aff bg-info-subtle text-info">
                        <i class="bi bi-cursor-fill"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Cliques & Visitas</div>
                        <div class="fs-4 fw-bold text-truncate">{{ number_format($kpis['total_clicks'], 0, ',', '.') }}</div>
                        <div class="small text-muted mt-1">
                            <span>Taxa Média de Conversão: <strong class="text-info">{{ $kpis['conversion_rate'] }}%</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conversões / Vendas --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-affiliate border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-aff bg-success-subtle text-success">
                        <i class="bi bi-bag-check-fill"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Vendas / Conversões</div>
                        <div class="fs-4 fw-bold text-truncate text-success">{{ number_format($kpis['total_conversions'], 0, ',', '.') }}</div>
                        <div class="small text-muted mt-1">
                            <span>Gerados: <strong class="text-success">R$ {{ number_format($kpis['total_earned'], 2, ',', '.') }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Comissões Pagas & Saques --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 kpi-card-affiliate border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon-aff bg-warning-subtle text-warning">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="text-uppercase small text-muted fw-semibold">Total Pago</div>
                        <div class="fs-4 fw-bold text-truncate">R$ {{ number_format($kpis['total_paid'], 2, ',', '.') }}</div>
                        <div class="small text-muted mt-1">
                            @if($kpis['pending_withdrawals'] > 0)
                                <span class="text-danger fw-semibold">R$ {{ number_format($kpis['pending_withdrawals'], 2, ',', '.') }} em saques pendentes</span>
                            @else
                                <span class="text-success">Sem saques pendentes</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== NAVEGAÇÃO EM ABAS ========== --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent p-0 border-0">
            <ul class="nav nav-tabs nav-tabs-affiliates px-3 pt-2" id="affiliateTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-dash-btn" data-bs-toggle="tab" data-bs-target="#tab-dash" type="button" role="tab">
                        <i class="bi bi-graph-up-arrow me-1"></i> Dashboard & Gráficos KPIs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-settings-btn" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab">
                        <i class="bi bi-sliders me-1"></i> Configurações Globais do Programa
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-list-btn" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">
                        <i class="bi bi-people me-1"></i> Afiliados & Divulgadores ({{ $kpis['total_affiliates'] }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-clicks-btn" data-bs-toggle="tab" data-bs-target="#tab-clicks" type="button" role="tab">
                        <i class="bi bi-cursor me-1"></i> Registro de Cliques & Visitas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-commissions-btn" data-bs-toggle="tab" data-bs-target="#tab-commissions" type="button" role="tab">
                        <i class="bi bi-receipt-cutoff me-1"></i> Comissões & Repasses
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="affiliateTabsContent">

                {{-- ==================== ABA 1: DASHBOARD & GRÁFICOS ==================== --}}
                <div class="tab-pane fade show active" id="tab-dash" role="tabpanel">
                    
                    {{-- Gráficos Interativos --}}
                    <div class="row g-4 mb-4">
                        {{-- Gráfico 1: Cliques x Conversões (Últimos 30 dias) --}}
                        <div class="col-lg-7">
                            <div class="card border h-100">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><i class="bi bi-activity me-1 text-primary"></i> Evolução de Cliques & Conversões (30 Dias)</span>
                                    <span class="badge bg-primary-subtle text-primary">Interativo</span>
                                </div>
                                <div class="card-body chart-container-aff">
                                    <canvas id="chartClicksConversions" height="230"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- Gráfico 2: Comissões Geradas por Dia --}}
                        <div class="col-lg-5">
                            <div class="card border h-100">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><i class="bi bi-cash-stack me-1 text-success"></i> Comissões Geradas (R$ / Dia)</span>
                                    <span class="badge bg-success-subtle text-success">R$ {{ number_format($kpis['total_earned'], 2, ',', '.') }} Total</span>
                                </div>
                                <div class="card-body chart-container-aff">
                                    <canvas id="chartDailyCommissions" height="230"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Top 10 e Saques Pendentes --}}
                    <div class="row g-4">
                        {{-- Top 10 Afiliados --}}
                        <div class="col-lg-7">
                            <div class="card border">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><i class="bi bi-trophy text-warning me-1"></i> Top 10 Afiliados com Maior Ganho</span>
                                    <small class="text-muted">Ranking em tempo real</small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle m-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 45px;">#</th>
                                                    <th>Afiliado / Empresa</th>
                                                    <th class="text-center">Vendas</th>
                                                    <th class="text-end">Ganho Total</th>
                                                    <th class="text-end">Saldo Atual</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($top as $idx => $a)
                                                @php
                                                    $pos = $idx + 1;
                                                    $medalColor = $pos === 1 ? 'warning' : ($pos === 2 ? 'secondary' : ($pos === 3 ? 'danger' : 'light'));
                                                @endphp
                                                <tr>
                                                    <td>
                                                        @if($pos <= 3)
                                                            <span class="badge bg-{{ $medalColor }} {{ $pos === 4 ? 'text-dark' : '' }}"><i class="bi bi-trophy-fill"></i></span>
                                                        @else
                                                            <span class="text-muted small">#{{ $pos }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold">{{ $a->user->name ?? 'Afiliado #' . $a->id }}</div>
                                                        <small class="text-muted d-block">{{ $a->tenant->company_name ?? $a->user->email }}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success-subtle text-success">{{ $a->conversions }}</span>
                                                    </td>
                                                    <td class="text-end fw-bold text-success">
                                                        R$ {{ number_format($a->total_earned, 2, ',', '.') }}
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                            R$ {{ number_format($a->balance, 2, ',', '.') }}
                                                        </span>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">Nenhum afiliado cadastrado.</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Saques Pendentes --}}
                        <div class="col-lg-5">
                            <div class="card border">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><i class="bi bi-hourglass-split text-warning me-1"></i> Saques Pendentes</span>
                                    <span class="badge bg-warning text-dark">{{ count($pendingWithdrawals) }}</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle m-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Afiliado</th>
                                                    <th class="text-end">Valor</th>
                                                    <th class="text-end">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($pendingWithdrawals as $w)
                                                <tr id="row-withdrawal-{{ $w->id }}">
                                                    <td>
                                                        <strong class="d-block">{{ $w->affiliate->user->name ?? 'Afiliado #' . $w->affiliate_id }}</strong>
                                                        <small class="text-muted">
                                                            Chave PIX: {{ is_array($w->payment_info) ? ($w->payment_info['pix_key'] ?? '-') : '-' }}
                                                        </small>
                                                    </td>
                                                    <td class="text-end fw-bold text-warning">
                                                        R$ {{ number_format($w->amount, 2, ',', '.') }}
                                                    </td>
                                                    <td class="text-end">
                                                        <button class="btn btn-sm btn-outline-success btn-approve-wd" data-id="{{ $w->id }}" title="Aprovar Pagamento">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger btn-reject-wd" data-id="{{ $w->id }}" title="Rejeitar Saque">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">
                                                        <i class="bi bi-check2-circle fs-3 text-success d-block mb-1"></i>
                                                        Nenhuma solicitação de saque pendente.
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== ABA 2: CONFIGURAÇÕES GLOBAIS ==================== --}}
                <div class="tab-pane fade" id="tab-settings" role="tabpanel">
                    <form id="form-global-affiliate-settings">
                        <div class="row g-4">
                            {{-- Ganhos do Divulgador --}}
                            <div class="col-lg-6">
                                <div class="card border h-100">
                                    <div class="card-header bg-transparent fw-semibold">
                                        <i class="bi bi-cash-coin me-1 text-primary"></i> 1. Ganhos do Divulgador (Comissão Padrão)
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Modo de Cálculo da Comissão</label>
                                            <select name="commission_mode" id="global_commission_mode" class="form-select">
                                                <option value="percentage" @selected(($globalSettings['commission_mode'] ?? 'percentage') === 'percentage')>Porcentagem (%) sobre o valor da fatura</option>
                                                <option value="fixed" @selected(($globalSettings['commission_mode'] ?? '') === 'fixed')>Valor Fixo em Reais (R$) por venda realizada</option>
                                            </select>
                                        </div>

                                        <div class="mb-3" id="global_box_commission_pct">
                                            <label class="form-label fw-semibold">Porcentagem Padrão de Comissão (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" name="default_commission_pct" class="form-control" value="{{ $globalSettings['default_commission_pct'] ?? 20.00 }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">Ex: 20% sobre cada fatura paga pelo cliente indicado.</small>
                                        </div>

                                        <div class="mb-3" id="global_box_commission_fixed">
                                            <label class="form-label fw-semibold">Valor Fixo Padrão por Venda (R$)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="default_commission_fixed" class="form-control mask-money" value="{{ number_format($globalSettings['default_commission_fixed'] ?? 0, 2, ',', '.') }}">
                                            </div>
                                            <small class="text-muted">Ex: R$ 50,00 fixos creditados ao divulgador por fatura/assinatura.</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Tipo de Recorrência da Comissão</label>
                                            <select name="default_commission_type" class="form-select">
                                                <option value="recurring" @selected(($globalSettings['default_commission_type'] ?? 'recurring') === 'recurring')>Recorrente (Ganha em todas as mensalidades pagas)</option>
                                                <option value="first_payment" @selected(($globalSettings['default_commission_type'] ?? '') === 'first_payment')>Única Vez (Ganha comissão apenas no 1º pagamento)</option>
                                                <option value="lifetime" @selected(($globalSettings['default_commission_type'] ?? '') === 'lifetime')>Vitalícia (Enquanto o cliente mantiver a conta)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Descontos para Compradores --}}
                            <div class="col-lg-6">
                                <div class="card border h-100">
                                    <div class="card-header bg-transparent fw-semibold">
                                        <i class="bi bi-tag-fill me-1 text-success"></i> 2. Descontos para Novos Compradores (Incentivo ao Cliente)
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="buyer_discount_enabled" value="1" id="buyer_discount_enabled" @checked($globalSettings['buyer_discount_enabled'])>
                                            <label class="form-check-label fw-semibold" for="buyer_discount_enabled">
                                                Oferecer desconto automático para quem assinar via link/cupom do afiliado
                                            </label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Modo de Cálculo do Desconto</label>
                                            <select name="buyer_discount_mode" id="global_discount_mode" class="form-select">
                                                <option value="percentage" @selected(($globalSettings['buyer_discount_mode'] ?? 'percentage') === 'percentage')>Porcentagem (%) de desconto</option>
                                                <option value="fixed" @selected(($globalSettings['buyer_discount_mode'] ?? '') === 'fixed')>Valor Fixo de desconto em Reais (R$)</option>
                                            </select>
                                        </div>

                                        <div class="mb-3" id="global_box_discount_pct">
                                            <label class="form-label fw-semibold">Porcentagem de Desconto ao Comprador (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" name="buyer_discount_pct" class="form-control" value="{{ $globalSettings['buyer_discount_pct'] ?? 10.00 }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">Ex: 10% de desconto abatido no valor da fatura do assinante.</small>
                                        </div>

                                        <div class="mb-3" id="global_box_discount_fixed">
                                            <label class="form-label fw-semibold">Valor Fixo de Desconto ao Comprador (R$)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="buyer_discount_fixed" class="form-control mask-money" value="{{ number_format($globalSettings['buyer_discount_fixed'] ?? 0, 2, ',', '.') }}">
                                            </div>
                                            <small class="text-muted">Ex: R$ 25,00 de desconto no boleto/PIX do assinante.</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Aplicação do Desconto</label>
                                            <select name="buyer_discount_type" class="form-select">
                                                <option value="first_payment" @selected(($globalSettings['buyer_discount_type'] ?? 'first_payment') === 'first_payment')>Apenas na 1ª Mensalidade (Boas-vindas)</option>
                                                <option value="recurring" @selected(($globalSettings['buyer_discount_type'] ?? '') === 'recurring')>Recorrente (Em todas as mensalidades enquanto ativo)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Regras Operacionais --}}
                            <div class="col-12">
                                <div class="card border">
                                    <div class="card-header bg-transparent fw-semibold">
                                        <i class="bi bi-clock-history me-1 text-info"></i> 3. Regras Operacionais, Cookies e Saques
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Validade do Cookie de Rastreamento (Dias)</label>
                                                <div class="input-group">
                                                    <input type="number" name="cookie_days" class="form-control" value="{{ $globalSettings['cookie_days'] ?? 60 }}" min="1" max="365">
                                                    <span class="input-group-text">dias</span>
                                                </div>
                                                <small class="text-muted">Tempo que o visitante permanece vinculado ao afiliado.</small>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Valor Mínimo para Solicitar Saque (R$)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" name="min_withdrawal" class="form-control mask-money" value="{{ number_format($globalSettings['min_withdrawal'] ?? 50.00, 2, ',', '.') }}">
                                                </div>
                                                <small class="text-muted">Saldo mínimo acumulado exigido para abrir solicitação de saque PIX.</small>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Aprovação Automática de Comissões (Dias)</label>
                                                <div class="input-group">
                                                    <input type="number" name="auto_approve_days" class="form-control" value="{{ $globalSettings['auto_approve_days'] ?? 7 }}" min="0" max="90">
                                                    <span class="input-group-text">dias</span>
                                                </div>
                                                <small class="text-muted">Aprova automaticamente comissões pendentes após X dias (0 = manual).</small>
                                            </div>

                                            <div class="col-md-3 d-flex flex-column justify-content-center">
                                                <div class="form-check form-switch pt-2">
                                                    <input class="form-check-input" type="checkbox" name="enabled" value="1" id="affiliates_enabled" @checked($globalSettings['enabled'])>
                                                    <label class="form-check-label fw-bold" for="affiliates_enabled">
                                                        Programa de Afiliados Ativo no Sistema
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-end mt-4">
                                            <button type="submit" class="btn btn-primary px-4" id="btn-save-global-settings">
                                                <i class="bi bi-check-lg me-1"></i> Salvar Configurações Globais
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- ==================== ABA 3: LISTA DE AFILIADOS ==================== --}}
                <div class="tab-pane fade" id="tab-list" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <form method="GET" action="{{ route('admin.affiliates.index') }}" class="d-flex gap-2 align-items-center flex-grow-1" style="max-width: 550px;">
                            <input type="text" name="q" class="form-control" placeholder="Buscar por nome, e-mail, slug, cupom ou empresa..." value="{{ request('q') }}">
                            <select name="status" class="form-select" style="max-width: 170px;">
                                <option value="">Todos os Status</option>
                                <option value="pending" @selected(request('status') === 'pending')>⏳ Pendentes</option>
                                <option value="active" @selected(request('status') === 'active')>✅ Ativos</option>
                                <option value="rejected" @selected(request('status') === 'rejected')>❌ Rejeitados</option>
                                <option value="suspended" @selected(request('status') === 'suspended')>⛔ Suspensos</option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
                        </form>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-create-affiliate">
                            <i class="bi bi-person-plus-fill me-1"></i> Novo Afiliado / Vincular Tenant
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0 border">
                            <thead class="table-light">
                                <tr>
                                    <th>Afiliado / Empresa</th>
                                    <th>Link / Código</th>
                                    <th>Ganhos (Comissão)</th>
                                    <th>Descontos (Comprador)</th>
                                    <th class="text-center">Cliques</th>
                                    <th class="text-center">Vendas</th>
                                    <th class="text-end">Saldo Disponível</th>
                                    <th class="text-end">Total Ganho</th>
                                    <th>Status & Split</th>
                                    <th class="text-end" style="min-width: 140px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($affiliates as $a)
                                <tr>
                                    <td>
                                        <strong class="d-block text-truncate">{{ $a->user->name ?? 'Afiliado #' . $a->id }}</strong>
                                        <small class="text-muted d-block">{{ $a->user->email }}</small>
                                        @if($a->tenant)
                                            <a href="{{ route('admin.tenancy.tenants.show', $a->tenant->id) }}" class="badge bg-primary-subtle text-primary border text-decoration-none mt-1">
                                                <i class="bi bi-building me-1"></i> {{ $a->tenant->company_name }}
                                            </a>
                                        @endif
                                        @if($a->promotion_channel)
                                            <small class="text-info d-block mt-1"><i class="bi bi-megaphone me-1"></i> {{ $a->promotion_channel }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <code class="d-block text-truncate" style="max-width: 160px;">/a/{{ $a->slug }}</code>
                                        <span class="badge bg-secondary-subtle text-secondary border mt-1">Cupom: {{ $a->referral_code }}</span>
                                    </td>
                                    <td>
                                        <strong class="text-primary">{{ $a->commission_formatted }}</strong>
                                        <small class="text-muted d-block">{{ $a->commission_type_label }}</small>
                                    </td>
                                    <td>
                                        <strong class="text-success">{{ $a->discount_formatted }}</strong>
                                        <small class="text-muted d-block">{{ $a->discount_type_label }}</small>
                                    </td>
                                    <td class="text-center fw-semibold">{{ number_format($a->clicks, 0, ',', '.') }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success fs-6">{{ $a->conversions }}</span>
                                        <small class="text-muted d-block">{{ $a->conversion_rate }}% CR</small>
                                    </td>
                                    <td class="text-end fw-bold text-info">
                                        R$ {{ number_format($a->balance, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        R$ {{ number_format($a->total_earned, 2, ',', '.') }}
                                    </td>
                                    <td>
                                        @if($a->status === 'active')
                                            <span class="badge bg-success text-uppercase">Ativo</span>
                                        @elseif($a->status === 'pending')
                                            <span class="badge bg-warning text-dark text-uppercase"><i class="bi bi-hourglass-split me-1"></i> Pendente</span>
                                        @elseif($a->status === 'rejected')
                                            <span class="badge bg-danger text-uppercase"><i class="bi bi-x-circle me-1"></i> Rejeitado</span>
                                        @else
                                            <span class="badge bg-secondary text-uppercase">{{ $a->status }}</span>
                                        @endif

                                        @if($a->split_enabled)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle d-block mt-1 text-truncate" style="max-width: 130px;" title="Collector ID: {{ $a->mp_collector_id }}">
                                                <i class="bi bi-lightning-charge-fill"></i> Split MP ({{ $a->split_pct ?? $a->commission_pct }}%)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if(in_array($a->status, ['pending', 'rejected']))
                                                <button type="button" class="btn btn-success" onclick="approveAffiliateAction({{ $a->id }}, '{{ addslashes($a->user->name ?? 'Afiliado') }}')" title="Aprovar Afiliação">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning text-dark" onclick="pendAffiliateAction({{ $a->id }})" title="Marcar Pendência">
                                                    <i class="bi bi-hourglass-bottom"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger" onclick="rejectAffiliateAction({{ $a->id }})" title="Rejeitar Inscrição">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-primary btn-edit-affiliate"
                                                data-id="{{ $a->id }}"
                                                data-name="{{ $a->user->name ?? 'Afiliado' }}"
                                                data-slug="{{ $a->slug }}"
                                                data-code="{{ $a->referral_code }}"
                                                data-status="{{ $a->status }}"
                                                data-comm-mode="{{ $a->commission_mode ?? 'percentage' }}"
                                                data-comm-pct="{{ $a->commission_pct }}"
                                                data-comm-fixed="{{ $a->commission_fixed }}"
                                                data-comm-type="{{ $a->commission_type }}"
                                                data-disc-mode="{{ $a->discount_mode ?? 'percentage' }}"
                                                data-disc-pct="{{ $a->discount_pct }}"
                                                data-disc-fixed="{{ $a->discount_fixed }}"
                                                data-disc-type="{{ $a->discount_type }}"
                                                data-min-wd="{{ $a->min_withdrawal }}"
                                                data-split-enabled="{{ $a->split_enabled ? '1' : '0' }}"
                                                data-split-pct="{{ $a->split_pct }}"
                                                data-mp-collector-id="{{ $a->mp_collector_id }}"
                                                data-rejection-reason="{{ $a->rejection_reason }}"
                                                data-admin-notes="{{ $a->admin_notes }}"
                                                title="Editar Regras">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">
                                        <i class="bi bi-people display-6 d-block mb-2"></i>
                                        Nenhum afiliado encontrado com os filtros aplicados.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($affiliates->hasPages())
                        <div class="mt-3">
                            {{ $affiliates->links() }}
                        </div>
                    @endif
                </div>

                {{-- ==================== ABA 4: CLIQUES & VISITAS ==================== --}}
                <div class="tab-pane fade" id="tab-clicks" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0 border">
                            <thead class="table-light">
                                <tr>
                                    <th>Data / Hora</th>
                                    <th>Afiliado Promotor</th>
                                    <th>IP do Visitante</th>
                                    <th>Referer / Origem</th>
                                    <th>Navegador / Dispositivo</th>
                                    <th class="text-center">Converteu em Venda?</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentClicks as $c)
                                <tr>
                                    <td>{{ $c->created_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                                    <td>
                                        <strong>{{ $c->affiliate->user->name ?? 'Afiliado #' . $c->affiliate_id }}</strong>
                                        <code class="d-block">/a/{{ $c->affiliate->slug ?? '-' }}</code>
                                    </td>
                                    <td><code>{{ $c->ip ?? '-' }}</code></td>
                                    <td><small class="text-truncate d-block" style="max-width: 250px;">{{ $c->referer ?: 'Acesso Direto' }}</small></td>
                                    <td><small class="text-truncate d-block" style="max-width: 250px;">{{ $c->user_agent ?: '-' }}</small></td>
                                    <td class="text-center">
                                        @if($c->converted)
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Sim, Converteu</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">Visita</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">Nenhum clique registrado recentemente.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ==================== ABA 5: COMISSÕES ==================== --}}
                <div class="tab-pane fade" id="tab-commissions" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0 border">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Afiliado Promotor</th>
                                    <th>Tenant Comprador</th>
                                    <th>Fatura</th>
                                    <th class="text-end">Valor Base</th>
                                    <th class="text-end">Comissão</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCommissions as $comm)
                                <tr id="row-comm-{{ $comm->id }}">
                                    <td>{{ $comm->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>
                                        <strong>{{ $comm->affiliate->user->name ?? 'Afiliado #' . $comm->affiliate_id }}</strong>
                                        <small class="text-muted d-block">{{ $comm->affiliate->user->email ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $comm->referral->tenant->company_name ?? ($comm->referral->user->name ?? 'Novo Cliente') }}</strong>
                                    </td>
                                    <td>
                                        @if($comm->tenant_invoice_id)
                                            <span class="badge bg-body-tertiary text-body border">#{{ $comm->tenant_invoice_id }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                        <small class="text-muted d-block">{{ $comm->notes }}</small>
                                    </td>
                                    <td class="text-end">R$ {{ number_format($comm->base_amount, 2, ',', '.') }}</td>
                                    <td class="text-end fw-bold text-success">R$ {{ number_format($comm->commission_amount, 2, ',', '.') }}</td>
                                    <td>
                                        @php
                                            $commBadge = [
                                                'pending'  => 'bg-warning text-dark',
                                                'approved' => 'bg-success',
                                                'paid'     => 'bg-info',
                                                'canceled' => 'bg-danger',
                                            ];
                                        @endphp
                                        <span class="badge {{ $commBadge[$comm->status] ?? 'bg-secondary' }} text-uppercase">
                                            {{ $comm->status }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($comm->status === 'pending')
                                            <button class="btn btn-sm btn-outline-success btn-approve-comm" data-id="{{ $comm->id }}" title="Aprovar e Creditar Saldo">
                                                <i class="bi bi-check-lg"></i> Aprovar
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-reject-comm" data-id="{{ $comm->id }}" title="Rejeitar">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        @else
                                            <span class="text-muted small">Processada</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">Nenhuma comissão registrada recentemente.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- ========== MODAL: NOVO AFILIADO / VINCULAR TENANT ========== --}}
<div class="modal fade" id="modal-create-affiliate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-1 text-primary"></i> Cadastrar Novo Afiliado / Promotor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-create-affiliate">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Vincular a um Tenant Existente</label>
                            <select name="tenant_id" class="form-select" required>
                                <option value="">-- Selecione o Tenant / Empresa --</option>
                                @foreach($availableTenants as $t)
                                    <option value="{{ $t->id }}">
                                        {{ $t->company_name }} ({{ $t->user->email ?? 'sem email' }}) - Tenant #{{ $t->id }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">O afiliado será vinculado ao responsável da conta da empresa selecionada.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Slug URL Personalizado (/a/...)</label>
                            <input type="text" name="slug" class="form-control" placeholder="ex: parceiro-oficial">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status Inicial</label>
                            <select name="status" class="form-select">
                                <option value="active">Ativo (Pronto para divulgar)</option>
                                <option value="pending">Pendente (Aguardando aprovação)</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-primary">Regras de Ganho (Comissão)</strong></div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Modo de Ganho</label>
                            <select name="commission_mode" id="modal_create_comm_mode" class="form-select">
                                <option value="percentage">Porcentagem (%)</option>
                                <option value="fixed">Valor Fixo (R$)</option>
                            </select>
                        </div>

                        <div class="col-md-4" id="modal_create_box_comm_pct">
                            <label class="form-label small fw-bold">Comissão (%)</label>
                            <input type="number" step="0.01" name="commission_pct" class="form-control" value="20.00">
                        </div>

                        <div class="col-md-4" id="modal_create_box_comm_fixed" style="display: none;">
                            <label class="form-label small fw-bold">Comissão Fixa (R$)</label>
                            <input type="text" name="commission_fixed" class="form-control mask-money" value="0,00">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Frequência</label>
                            <select name="commission_type" class="form-select">
                                <option value="recurring">Recorrente</option>
                                <option value="first_payment">Única Vez</option>
                                <option value="lifetime">Vitalícia</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-success">Regras de Desconto ao Comprador</strong></div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Modo de Desconto</label>
                            <select name="discount_mode" id="modal_create_disc_mode" class="form-select">
                                <option value="percentage">Porcentagem (%)</option>
                                <option value="fixed">Valor Fixo (R$)</option>
                            </select>
                        </div>

                        <div class="col-md-4" id="modal_create_box_disc_pct">
                            <label class="form-label small fw-bold">Desconto (%)</label>
                            <input type="number" step="0.01" name="discount_pct" class="form-control" value="10.00">
                        </div>

                        <div class="col-md-4" id="modal_create_box_disc_fixed" style="display: none;">
                            <label class="form-label small fw-bold">Desconto Fixo (R$)</label>
                            <input type="text" name="discount_fixed" class="form-control mask-money" value="0,00">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Aplicação do Desconto</label>
                            <select name="discount_type" class="form-select">
                                <option value="first_payment">Apenas na 1ª Mensalidade</option>
                                <option value="recurring">Recorrente</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn-submit-create-affiliate">Cadastrar Afiliado</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========== MODAL: EDITAR AFILIADO ========== --}}
<div class="modal fade" id="modal-edit-affiliate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Editar Regras do Afiliado <span id="modal-edit-aff-name" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-edit-affiliate">
                <input type="hidden" name="affiliate_id" id="modal-edit-id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" id="modal-edit-status" class="form-select">
                                <option value="active">Ativo (Aprovado)</option>
                                <option value="pending">Pendente de Aprovação</option>
                                <option value="rejected">Rejeitado / Pendência</option>
                                <option value="suspended">Suspenso</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Slug URL (/a/...)</label>
                            <input type="text" name="slug" id="modal-edit-slug" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Cupom / Código</label>
                            <input type="text" name="referral_code" id="modal-edit-code" class="form-control text-uppercase">
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-primary">Comissão do Divulgador</strong></div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Modo de Ganho</label>
                            <select name="commission_mode" id="modal-edit-comm-mode" class="form-select">
                                <option value="percentage">Porcentagem (%)</option>
                                <option value="fixed">Valor Fixo (R$)</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="modal-edit-box-comm-pct">
                            <label class="form-label small fw-bold">Porcentagem (%)</label>
                            <input type="number" step="0.01" name="commission_pct" id="modal-edit-comm-pct" class="form-control">
                        </div>
                        <div class="col-md-4" id="modal-edit-box-comm-fixed" style="display: none;">
                            <label class="form-label small fw-bold">Valor Fixo (R$)</label>
                            <input type="text" name="commission_fixed" id="modal-edit-comm-fixed" class="form-control mask-money">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Recorrência</label>
                            <select name="commission_type" id="modal-edit-comm-type" class="form-select">
                                <option value="recurring">Recorrente</option>
                                <option value="first_payment">Única Vez</option>
                                <option value="lifetime">Vitalícia</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-success">Desconto ao Comprador</strong></div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Modo de Desconto</label>
                            <select name="discount_mode" id="modal-edit-disc-mode" class="form-select">
                                <option value="percentage">Porcentagem (%)</option>
                                <option value="fixed">Valor Fixo (R$)</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="modal-edit-box-disc-pct">
                            <label class="form-label small fw-bold">Desconto (%)</label>
                            <input type="number" step="0.01" name="discount_pct" id="modal-edit-disc-pct" class="form-control">
                        </div>
                        <div class="col-md-4" id="modal-edit-box-disc-fixed" style="display: none;">
                            <label class="form-label small fw-bold">Desconto Fixo (R$)</label>
                            <input type="text" name="discount_fixed" id="modal-edit-disc-fixed" class="form-control mask-money">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Aplicação</label>
                            <select name="discount_type" id="modal-edit-disc-type" class="form-select">
                                <option value="first_payment">Apenas na 1ª Mensalidade</option>
                                <option value="recurring">Recorrente</option>
                            </select>
                        </div>

                        {{-- SEÇÃO SPLIT MERCADO PAGO --}}
                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-info"><i class="bi bi-lightning-charge-fill me-1"></i> Split Automático Mercado Pago</strong></div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="split_enabled" value="1" id="modal-edit-split-enabled">
                                <label class="form-check-label small fw-bold" for="modal-edit-split-enabled">Ativar Split Direto MP</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Percentual Split (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="split_pct" id="modal-edit-split-pct" class="form-control" placeholder="Ex: 20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Mercado Pago Collector ID</label>
                            <input type="text" name="mp_collector_id" id="modal-edit-collector-id" class="form-control" placeholder="ID da conta MP do afiliado">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Motivo da Rejeição / Instruções de Pendência (Visível ao Cliente)</label>
                            <input type="text" name="rejection_reason" id="modal-edit-rejection-reason" class="form-control" placeholder="Caso rejeitado ou pendente">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-affiliate">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    // 1. Gráfico de Cliques x Conversões (Últimos 30 Dias)
    const ctxCC = document.getElementById('chartClicksConversions');
    if (ctxCC && typeof Chart !== 'undefined') {
        const labels = @json($kpis['chart']['labels'] ?? []);
        const clicks = @json($kpis['chart']['clicks'] ?? []);
        const conversions = @json($kpis['chart']['conversions'] ?? []);

        const gradClicks = ctxCC.getContext('2d').createLinearGradient(0, 0, 0, 220);
        gradClicks.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
        gradClicks.addColorStop(1, 'rgba(59, 130, 246, 0.02)');

        new Chart(ctxCC, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Cliques / Visitas',
                        data: clicks,
                        borderColor: '#3b82f6',
                        backgroundColor: gradClicks,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#3b82f6',
                        pointRadius: 3,
                    },
                    {
                        label: 'Vendas / Conversões',
                        data: conversions,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        fill: false,
                        tension: 0.35,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { color: textColor } },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 12 } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } }
                }
            }
        });
    }

    // 2. Gráfico de Comissões Diárias (R$)
    const ctxDC = document.getElementById('chartDailyCommissions');
    if (ctxDC && typeof Chart !== 'undefined') {
        const labels = @json($kpis['chart']['labels'] ?? []);
        const comms = @json($kpis['chart']['commissions'] ?? []);

        new Chart(ctxDC, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Comissões (R$)',
                    data: comms,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' Comissão: R$ ' + ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 8 } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            callback: function (val) { return 'R$ ' + val; }
                        }
                    }
                }
            }
        });
    }

    // Toggle de exibição % vs fixo nas configurações globais
    function toggleGlobalFields() {
        const commMode = document.getElementById('global_commission_mode')?.value;
        const boxCommPct = document.getElementById('global_box_commission_pct');
        const boxCommFixed = document.getElementById('global_box_commission_fixed');
        if (boxCommPct && boxCommFixed) {
            boxCommPct.style.display = commMode === 'fixed' ? 'none' : 'block';
            boxCommFixed.style.display = commMode === 'fixed' ? 'block' : 'none';
        }

        const discMode = document.getElementById('global_discount_mode')?.value;
        const boxDiscPct = document.getElementById('global_box_discount_pct');
        const boxDiscFixed = document.getElementById('global_box_discount_fixed');
        if (boxDiscPct && boxDiscFixed) {
            boxDiscPct.style.display = discMode === 'fixed' ? 'none' : 'block';
            boxDiscFixed.style.display = discMode === 'fixed' ? 'block' : 'none';
        }
    }
    document.getElementById('global_commission_mode')?.addEventListener('change', toggleGlobalFields);
    document.getElementById('global_discount_mode')?.addEventListener('change', toggleGlobalFields);
    toggleGlobalFields();

    // Salvar Configurações Globais via AJAX
    document.getElementById('form-global-affiliate-settings')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-global-settings');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';

        const formData = new FormData(this);
        const json = Object.fromEntries(formData.entries());
        json.buyer_discount_enabled = document.getElementById('buyer_discount_enabled')?.checked ? 1 : 0;
        json.enabled = document.getElementById('affiliates_enabled')?.checked ? 1 : 0;

        try {
            const res = await fetch('{{ route("admin.affiliates.settings.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(json)
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') {
                    Toastify({ text: data.message, duration: 3500, gravity: "top", position: "right", style: { background: "linear-gradient(to right, #00b09b, #96c93d)" } }).showToast();
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Erro ao salvar', text: data.error || 'Verifique os campos informados.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de conexão', text: err.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Salvar Configurações Globais';
        }
    });

    // Abrir Modal de Edição de Afiliado
    document.querySelectorAll('.btn-edit-affiliate').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modal-edit-id').value = this.dataset.id;
            document.getElementById('modal-edit-aff-name').textContent = '#' + this.dataset.id + ' (' + this.dataset.name + ')';
            document.getElementById('modal-edit-status').value = this.dataset.status;
            document.getElementById('modal-edit-slug').value = this.dataset.slug;
            document.getElementById('modal-edit-code').value = this.dataset.code;

            document.getElementById('modal-edit-comm-mode').value = this.dataset.commMode || 'percentage';
            document.getElementById('modal-edit-comm-pct').value = this.dataset.commPct || '';
            document.getElementById('modal-edit-comm-fixed').value = this.dataset.commFixed || '0,00';
            document.getElementById('modal-edit-comm-type').value = this.dataset.commType || 'recurring';

            document.getElementById('modal-edit-disc-mode').value = this.dataset.discMode || 'percentage';
            document.getElementById('modal-edit-disc-pct').value = this.dataset.discPct || '';
            document.getElementById('modal-edit-disc-fixed').value = this.dataset.discFixed || '0,00';
            document.getElementById('modal-edit-disc-type').value = this.dataset.discType || 'first_payment';

            const splitChk = document.getElementById('modal-edit-split-enabled');
            if (splitChk) splitChk.checked = this.dataset.splitEnabled === '1';
            const splitPctInput = document.getElementById('modal-edit-split-pct');
            if (splitPctInput) splitPctInput.value = this.dataset.splitPct || '';
            const collectorInput = document.getElementById('modal-edit-collector-id');
            if (collectorInput) collectorInput.value = this.dataset.mpCollectorId || '';
            const rejInput = document.getElementById('modal-edit-rejection-reason');
            if (rejInput) rejInput.value = this.dataset.rejectionReason || '';

            toggleEditModalFields();
            const modal = new bootstrap.Modal(document.getElementById('modal-edit-affiliate'));
            modal.show();
        });
    });

    // Funções de Moderação Rápida de Afiliados (Aprovar / Rejeitar / Pendência)
    window.approveAffiliateAction = async function (id, name) {
        const { isConfirmed } = await Swal.fire({
            title: 'Aprovar Afiliado?',
            text: `Deseja aprovar o cadastro de ${name}? O cliente receberá imediatamente confirmação no WhatsApp e E-mail com seus links liberados.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle-fill me-1"></i> Sim, Aprovar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        });

        if (!isConfirmed) return;

        try {
            const res = await fetch(`{{ url('/admin/affiliates') }}/${id}/approve`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 4000, style: { background: "#198754" } }).showToast();
                Swal.fire({ icon: 'success', title: 'Aprovado!', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Não foi possível aprovar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: e.message });
        }
    };

    window.rejectAffiliateAction = async function (id) {
        const { value: reason } = await Swal.fire({
            title: 'Rejeitar Inscrição de Afiliado',
            text: 'Digite o motivo da não aprovação (será enviado ao cliente por WhatsApp e E-mail):',
            input: 'textarea',
            inputPlaceholder: 'Informe o motivo detalhado...',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Rejeição',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            inputValidator: (val) => {
                if (!val || val.trim().length < 5) return 'Informe o motivo da rejeição.';
            }
        });

        if (!reason) return;

        try {
            const res = await fetch(`{{ url('/admin/affiliates') }}/${id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ reason: reason.trim() })
            });
            const data = await res.json();
            if (data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 4000, style: { background: "#dc3545" } }).showToast();
                Swal.fire({ icon: 'success', title: 'Rejeitado', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Não foi possível rejeitar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: e.message });
        }
    };

    window.pendAffiliateAction = async function (id) {
        const { value: instructions } = await Swal.fire({
            title: 'Marcar Pendência de Informações',
            text: 'Digite as instruções para o cliente ajustar seus dados:',
            input: 'textarea',
            inputPlaceholder: 'Orientações para o cliente...',
            showCancelButton: true,
            confirmButtonText: 'Enviar Pendência',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
            inputValidator: (val) => {
                if (!val || val.trim().length < 5) return 'Informe as orientações de pendência.';
            }
        });

        if (!instructions) return;

        try {
            const res = await fetch(`{{ url('/admin/affiliates') }}/${id}/pend`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ instructions: instructions.trim() })
            });
            const data = await res.json();
            if (data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 4000, style: { background: "#e0a800" } }).showToast();
                Swal.fire({ icon: 'success', title: 'Pendência Registrada', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Não foi possível registrar pendência.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: e.message });
        }
    };

    const parseMoneyInput = (val) => {
        if (val === null || val === undefined || val === '') return 0;
        if (typeof val === 'number') return val;
        const clean = String(val).replace(/[^\d,.-]/g, '').replace(',', '.');
        return parseFloat(clean) || 0;
    };

    function toggleCreateModalFields() {
        const commMode = document.getElementById('modal_create_comm_mode')?.value;
        const boxCommPct = document.getElementById('modal_create_box_comm_pct');
        const boxCommFixed = document.getElementById('modal_create_box_comm_fixed');
        if (boxCommPct && boxCommFixed) {
            boxCommPct.style.display = commMode === 'fixed' ? 'none' : 'block';
            boxCommFixed.style.display = commMode === 'fixed' ? 'block' : 'none';
        }

        const discMode = document.getElementById('modal_create_disc_mode')?.value;
        const boxDiscPct = document.getElementById('modal_create_box_disc_pct');
        const boxDiscFixed = document.getElementById('modal_create_box_disc_fixed');
        if (boxDiscPct && boxDiscFixed) {
            boxDiscPct.style.display = discMode === 'fixed' ? 'none' : 'block';
            boxDiscFixed.style.display = discMode === 'fixed' ? 'block' : 'none';
        }
    }
    document.getElementById('modal_create_comm_mode')?.addEventListener('change', toggleCreateModalFields);
    document.getElementById('modal_create_disc_mode')?.addEventListener('change', toggleCreateModalFields);
    toggleCreateModalFields();

    function toggleEditModalFields() {
        const commMode = document.getElementById('modal-edit-comm-mode')?.value;
        const boxCommPct = document.getElementById('modal-edit-box-comm-pct');
        const boxCommFixed = document.getElementById('modal-edit-box-comm-fixed');
        if (boxCommPct && boxCommFixed) {
            boxCommPct.style.display = commMode === 'fixed' ? 'none' : 'block';
            boxCommFixed.style.display = commMode === 'fixed' ? 'block' : 'none';
        }

        const discMode = document.getElementById('modal-edit-disc-mode')?.value;
        const boxDiscPct = document.getElementById('modal-edit-box-disc-pct');
        const boxDiscFixed = document.getElementById('modal-edit-box-disc-fixed');
        if (boxDiscPct && boxDiscFixed) {
            boxDiscPct.style.display = discMode === 'fixed' ? 'none' : 'block';
            boxDiscFixed.style.display = discMode === 'fixed' ? 'block' : 'none';
        }
    }
    document.getElementById('modal-edit-comm-mode')?.addEventListener('change', toggleEditModalFields);
    document.getElementById('modal-edit-disc-mode')?.addEventListener('change', toggleEditModalFields);

    // Salvar Edição do Afiliado
    document.getElementById('form-edit-affiliate')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('modal-edit-id').value;
        const btn = document.getElementById('btn-submit-edit-affiliate');
        btn.disabled = true;

        const formData = new FormData(this);
        const json = Object.fromEntries(formData.entries());
        json['split_enabled'] = document.getElementById('modal-edit-split-enabled')?.checked ? 1 : 0;

        json.commission_pct = parseMoneyInput(json.commission_pct);
        json.commission_fixed = parseMoneyInput(json.commission_fixed);
        json.discount_pct = parseMoneyInput(json.discount_pct);
        json.discount_fixed = parseMoneyInput(json.discount_fixed);
        if (json.min_withdrawal) json.min_withdrawal = parseMoneyInput(json.min_withdrawal);

        try {
            const res = await fetch(`{{ url('/admin/affiliates') }}/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(json)
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 3000, style: { background: "#10b981" } }).showToast();
                setTimeout(() => location.reload(), 800);
            } else {
                const errorMsg = data.error || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Falha ao salvar.');
                Swal.fire({ icon: 'error', title: 'Erro ao atualizar', text: errorMsg });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de conexão', text: err.message });
        } finally {
            btn.disabled = false;
        }
    });

    // Salvar Novo Afiliado
    document.getElementById('form-create-affiliate')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-submit-create-affiliate');
        btn.disabled = true;

        const formData = new FormData(this);
        const json = Object.fromEntries(formData.entries());

        json.commission_pct = parseMoneyInput(json.commission_pct);
        json.commission_fixed = parseMoneyInput(json.commission_fixed);
        json.discount_pct = parseMoneyInput(json.discount_pct);
        json.discount_fixed = parseMoneyInput(json.discount_fixed);

        // Garante coerência entre modo e valor
        if (json.commission_mode === 'percentage') {
            json.commission_fixed = 0;
        } else {
            json.commission_pct = 0;
        }

        if (json.discount_mode === 'percentage') {
            json.discount_fixed = 0;
        } else {
            json.discount_pct = 0;
        }

        try {
            const res = await fetch('{{ route("admin.affiliates.store") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(json)
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 3000, style: { background: "#10b981" } }).showToast();
                setTimeout(() => location.reload(), 800);
            } else {
                const errorMsg = data.error || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Falha ao cadastrar.');
                Swal.fire({ icon: 'error', title: 'Erro ao cadastrar', text: errorMsg });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Erro de conexão', text: err.message });
        } finally {
            btn.disabled = false;
        }
    });

    // Aprovar Saque
    document.querySelectorAll('.btn-approve-wd').forEach(btn => {
        btn.addEventListener('click', async () => {
            const { value: note } = await Swal.fire({
                title: 'Aprovar Saque?',
                text: 'Insira o comprovante de pagamento PIX ou observação (opcional):',
                input: 'text',
                inputPlaceholder: 'ex: ID Transação PIX E123456...',
                showCancelButton: true,
                confirmButtonText: 'Confirmar Pagamento',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
            });
            if (note === undefined) return;

            const res = await fetch(`{{ url('/admin/affiliates/withdrawals') }}/${btn.dataset.id}/approve`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ note })
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 3000, style: { background: "#10b981" } }).showToast();
                document.getElementById(`row-withdrawal-${btn.dataset.id}`)?.remove();
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Falha ao processar.' });
            }
        });
    });

    // Rejeitar Saque
    document.querySelectorAll('.btn-reject-wd').forEach(btn => {
        btn.addEventListener('click', async () => {
            const { value: note } = await Swal.fire({
                title: 'Rejeitar Saque?',
                text: 'Informe o motivo da rejeição (o valor será estornado ao saldo do afiliado):',
                input: 'text',
                inputPlaceholder: 'ex: Chave PIX inválida',
                showCancelButton: true,
                confirmButtonText: 'Rejeitar e Estornar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (note === undefined) return;

            const res = await fetch(`{{ url('/admin/affiliates/withdrawals') }}/${btn.dataset.id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ note })
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 3000, style: { background: "#ef4444" } }).showToast();
                document.getElementById(`row-withdrawal-${btn.dataset.id}`)?.remove();
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Falha ao rejeitar.' });
            }
        });
    });

    // Aprovar Comissão
    document.querySelectorAll('.btn-approve-comm').forEach(btn => {
        btn.addEventListener('click', async () => {
            const ok = await Swal.fire({
                title: 'Aprovar comissão?',
                text: 'O valor da comissão será creditado imediatamente no saldo do afiliado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, aprovar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
            });
            if (!ok.isConfirmed) return;

            const res = await fetch(`{{ url('/admin/affiliates/commissions') }}/${btn.dataset.id}/approve`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 3000, style: { background: "#10b981" } }).showToast();
                setTimeout(() => location.reload(), 700);
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Falha ao aprovar.' });
            }
        });
    });

    // Rejeitar Comissão
    document.querySelectorAll('.btn-reject-comm').forEach(btn => {
        btn.addEventListener('click', async () => {
            const { value: note } = await Swal.fire({
                title: 'Rejeitar comissão?',
                text: 'Motivo do cancelamento desta comissão:',
                input: 'text',
                inputPlaceholder: 'ex: Fatura estornada ou chargeback',
                showCancelButton: true,
                confirmButtonText: 'Sim, rejeitar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (note === undefined) return;

            const res = await fetch(`{{ url('/admin/affiliates/commissions') }}/${btn.dataset.id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ note })
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                if (typeof Toastify === 'function') Toastify({ text: data.message, duration: 3000, style: { background: "#ef4444" } }).showToast();
                setTimeout(() => location.reload(), 700);
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: data.error || 'Falha ao rejeitar.' });
            }
        });
    });
});
</script>
@endpush
