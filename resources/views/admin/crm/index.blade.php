@extends('admin.layouts.app')
@section('title', 'CRM')

@php
    $paymentRate = $totals['invoices'] > 0 ? round(($totals['paid'] / $totals['invoices']) * 100, 1) : 0;
    $averageTicket = $totals['paid'] > 0 ? ($totals['revenue_30d'] / max(1, $totals['paid'])) : 0;
    $inactiveCustomers = max(0, $totals['customers'] - $totals['customers_active']);
@endphp

@push('head')
<style>
.crm-page {
    --crm-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --crm-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.crm-hero-notes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
}
.crm-note {
    border: 1px solid var(--crm-border-soft);
    border-radius: 16px;
    padding: 1rem;
    background: var(--crm-surface-soft);
}
.crm-note__label {
    display: block;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .35rem;
}
.crm-note__value {
    display: block;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.15;
}
.crm-actions {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .9rem;
}
.crm-action-tile {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    gap: .9rem;
    height: 100%;
    min-height: 136px;
    padding: 1rem;
    border-radius: 16px;
    border: 1px solid var(--crm-border-soft);
    background: var(--bs-body-bg);
    color: inherit;
    text-decoration: none;
    transition: var(--app-transition);
}
.crm-action-tile:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--bs-primary) 35%, var(--crm-border-soft));
    box-shadow: var(--app-shadow-premium);
    color: inherit;
}
.crm-action-tile__title {
    margin: 0;
    font-size: .98rem;
    font-weight: 700;
}
.crm-action-tile__text {
    margin: 0;
    color: var(--bs-secondary-color);
    font-size: .82rem;
    line-height: 1.45;
}
.crm-kpi {
    height: 100%;
}
.crm-kpi .card-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.crm-kpi__label {
    display: block;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .45rem;
}
.crm-kpi__value {
    display: block;
    font-size: 1.55rem;
    font-weight: 700;
    line-height: 1.05;
}
.crm-kpi__hint {
    display: block;
    margin-top: .5rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
}
.crm-panel {
    height: 100%;
}
.crm-panel .card-header {
    padding: 1rem 1rem .9rem;
}
.crm-panel .card-body {
    padding: 1rem;
}
.crm-panel__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}
.crm-panel__subtitle {
    display: block;
    margin-top: .15rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
    font-weight: 500;
}
.crm-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    vertical-align: middle;
}
.crm-table tbody td {
    vertical-align: middle;
}
.crm-customer-name {
    display: flex;
    align-items: center;
    gap: .7rem;
    min-width: 0;
}
.crm-customer-avatar {
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
    font-weight: 700;
    flex: 0 0 auto;
}
.crm-summary-list {
    display: grid;
    gap: .8rem;
}
.crm-summary-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .9rem 1rem;
    border: 1px solid var(--crm-border-soft);
    border-radius: 14px;
    background: var(--crm-surface-soft);
}
.crm-summary-item__label {
    color: var(--bs-secondary-color);
    font-size: .84rem;
}
.crm-summary-item__value {
    font-size: 1rem;
    font-weight: 700;
}
.crm-empty {
    display: grid;
    place-items: center;
    min-height: 220px;
    color: var(--bs-secondary-color);
    text-align: center;
}
@media (max-width: 1199.98px) {
    .crm-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 991.98px) {
    .crm-hero-notes {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767.98px) {
    .crm-actions {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid crm-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Relacionamento e cobranca</span>
            <h1 class="admin-premium-hero__title">CRM com leitura mais objetiva para clientes, receita e follow-up.</h1>
            <p class="admin-premium-hero__text">
                A visao geral agora separa operacao comercial, carteira e cobrancas em blocos mais claros, sem misturar atalhos, tabelas e numeros no mesmo plano visual.
            </p>
        </div>
        <div class="crm-hero-notes">
            <div class="crm-note">
                <span class="crm-note__label">Receita dos ultimos 30 dias</span>
                <span class="crm-note__value">R$ {{ number_format($totals['revenue_30d'], 2, ',', '.') }}</span>
            </div>
            <div class="crm-note">
                <span class="crm-note__label">Taxa de pagamento</span>
                <span class="crm-note__value">{{ number_format($paymentRate, 1, ',', '.') }}%</span>
            </div>
            <div class="crm-note">
                <span class="crm-note__label">Novos clientes em 30 dias</span>
                <span class="crm-note__value">{{ number_format($totals['new_customers_30d'], 0, ',', '.') }}</span>
            </div>
            <div class="crm-note">
                <span class="crm-note__label">Clientes inativos</span>
                <span class="crm-note__value">{{ number_format($inactiveCustomers, 0, ',', '.') }}</span>
            </div>
        </div>
    </section>

    <section class="premium-panel mb-4">
        <div class="premium-panel__header">
            <div>
                <span class="premium-panel__kicker">Acoes principais</span>
                <h2 class="premium-panel__title">Atalhos operacionais</h2>
                <p class="premium-panel__subtitle mb-0">Acesso direto para cadastro, cobranca, agenda e funil comercial.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.billing.customers.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Novo cliente
                </a>
                <a href="{{ route('admin.invoice-editor.create') }}" class="btn btn-outline-primary">
                    <i class="bi bi-receipt-cutoff me-1"></i> Nova fatura
                </a>
            </div>
        </div>
        <div class="premium-panel__body">
            <div class="crm-actions">
                <a href="{{ route('admin.billing.customers.index') }}" class="crm-action-tile">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                    <div>
                        <h3 class="crm-action-tile__title">Clientes</h3>
                        <p class="crm-action-tile__text">Gerencie cadastro, status, documentos e historico da base.</p>
                    </div>
                </a>
                <a href="{{ route('admin.invoice-editor.index') }}" class="crm-action-tile">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-receipt"></i></div>
                    <div>
                        <h3 class="crm-action-tile__title">Faturas</h3>
                        <p class="crm-action-tile__text">Emita cobrancas, acompanhe pagamentos e revise pendencias.</p>
                    </div>
                </a>
                <a href="{{ route('admin.billing.calendar') }}" class="crm-action-tile">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-calendar3"></i></div>
                    <div>
                        <h3 class="crm-action-tile__title">Calendario</h3>
                        <p class="crm-action-tile__text">Veja vencimentos, mova cobrancas e acompanhe dias bloqueados.</p>
                    </div>
                </a>
                <a href="{{ route('admin.crm.funnel') }}" class="crm-action-tile">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-funnel"></i></div>
                    <div>
                        <h3 class="crm-action-tile__title">Funil de vendas</h3>
                        <p class="crm-action-tile__text">Avance leads por etapa e acompanhe negociacoes em andamento.</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Clientes ativos', $totals['customers_active'], 'Carteira ativa', 'primary', 'bi-people'],
                ['Receita 30d', 'R$ ' . number_format($totals['revenue_30d'], 2, ',', '.'), 'Pagamentos confirmados', 'success', 'bi-cash-stack'],
                ['Faturas pendentes', $totals['pending'], 'Aguardando pagamento', 'warning', 'bi-hourglass-split'],
                ['Faturas vencidas', $totals['overdue'], 'Em atraso agora', 'danger', 'bi-exclamation-triangle'],
                ['Novos clientes', $totals['new_customers_30d'], 'Entradas nos ultimos 30 dias', 'info', 'bi-person-plus'],
                ['Total de faturas', $totals['invoices'], 'Base geral de cobrancas', 'secondary', 'bi-receipt-cutoff'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $hint, $tone, $icon])
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card crm-kpi">
                    <div class="card-body">
                        <div>
                            <span class="crm-kpi__label">{{ $label }}</span>
                            <span class="crm-kpi__value text-{{ $tone }}">{{ $value }}</span>
                            <span class="crm-kpi__hint">{{ $hint }}</span>
                        </div>
                        <div class="stat-icon bg-{{ $tone }}-subtle text-{{ $tone }}">
                            <i class="bi {{ $icon }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card crm-panel">
                <div class="card-header">
                    <div>
                        <h3 class="crm-panel__title">Ultimos clientes</h3>
                        <span class="crm-panel__subtitle">Entradas recentes da carteira com foco em status e contato.</span>
                    </div>
                    <a href="{{ route('admin.billing.customers.index') }}" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover crm-table mb-0">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>WhatsApp</th>
                                    <th>Documento</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCustomers as $customer)
                                    <tr>
                                        <td>
                                            <div class="crm-customer-name">
                                                <span class="crm-customer-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                                                <div class="min-w-0">
                                                    <div class="fw-semibold text-truncate">{{ $customer->name }}</div>
                                                    <small class="text-muted">Cliente #{{ $customer->id }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $customer->whatsapp ?: '-' }}</td>
                                        <td>{{ $customer->document ?: '-' }}</td>
                                        <td class="text-end">
                                            <span class="badge bg-{{ $customer->active ? 'success' : 'secondary' }}">
                                                {{ $customer->active ? 'Ativo' : 'Inativo' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-0">
                                            <div class="crm-empty">
                                                <div>
                                                    <i class="bi bi-people opacity-50 d-block mb-2 fs-3"></i>
                                                    Nenhum cliente cadastrado ainda.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card crm-panel">
                <div class="card-header">
                    <div>
                        <h3 class="crm-panel__title">Ultimas faturas</h3>
                        <span class="crm-panel__subtitle">Leitura rapida de cobrancas mais recentes e seus status.</span>
                    </div>
                    <a href="{{ route('admin.invoice-editor.index') }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover crm-table mb-0">
                            <thead>
                                <tr>
                                    <th>Fatura</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $statusColors = ['pending' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'sent' => 'info', 'canceled' => 'secondary'];
                                    $statusLabels = ['pending' => 'Pendente', 'paid' => 'Paga', 'overdue' => 'Vencida', 'sent' => 'Enviada', 'canceled' => 'Cancelada'];
                                @endphp
                                @forelse($recentInvoices as $invoice)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $invoice->number }}</div>
                                            <small class="text-muted">Cobranca #{{ $invoice->id }}</small>
                                        </td>
                                        <td>{{ $invoice->customer_name }}</td>
                                        <td class="text-end fw-semibold">R$ {{ number_format($invoice->amount, 2, ',', '.') }}</td>
                                        <td class="text-end">
                                            <span class="badge bg-{{ $statusColors[$invoice->status] ?? 'secondary' }}">
                                                {{ $statusLabels[$invoice->status] ?? ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-0">
                                            <div class="crm-empty">
                                                <div>
                                                    <i class="bi bi-receipt opacity-50 d-block mb-2 fs-3"></i>
                                                    Nenhuma fatura cadastrada.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card crm-panel">
                <div class="card-header">
                    <div>
                        <h3 class="crm-panel__title">Resumo comercial</h3>
                        <span class="crm-panel__subtitle">Indicadores sinteticos da carteira e cobranca.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="crm-summary-list">
                        <div class="crm-summary-item">
                            <span class="crm-summary-item__label">Total de clientes</span>
                            <span class="crm-summary-item__value">{{ number_format($totals['customers'], 0, ',', '.') }}</span>
                        </div>
                        <div class="crm-summary-item">
                            <span class="crm-summary-item__label">Clientes ativos</span>
                            <span class="crm-summary-item__value text-success">{{ number_format($totals['customers_active'], 0, ',', '.') }}</span>
                        </div>
                        <div class="crm-summary-item">
                            <span class="crm-summary-item__label">Faturas pagas</span>
                            <span class="crm-summary-item__value text-success">{{ number_format($totals['paid'], 0, ',', '.') }}</span>
                        </div>
                        <div class="crm-summary-item">
                            <span class="crm-summary-item__label">Taxa de pagamento</span>
                            <span class="crm-summary-item__value">{{ number_format($paymentRate, 1, ',', '.') }}%</span>
                        </div>
                        <div class="crm-summary-item">
                            <span class="crm-summary-item__label">Ticket medio 30d</span>
                            <span class="crm-summary-item__value">R$ {{ number_format($averageTicket, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card crm-panel">
                <div class="card-header">
                    <div>
                        <h3 class="crm-panel__title">Proximos passos sugeridos</h3>
                        <span class="crm-panel__subtitle">Ordem rapida para operar o CRM sem perder contexto.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="crm-actions">
                        <a href="{{ route('admin.billing.customers.create') }}" class="crm-action-tile">
                            <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-person-vcard"></i></div>
                            <div>
                                <h3 class="crm-action-tile__title">Cadastrar contato</h3>
                                <p class="crm-action-tile__text">Adicionar novo cliente ou lead para iniciar relacionamento.</p>
                            </div>
                        </a>
                        <a href="{{ route('admin.invoice-editor.create') }}" class="crm-action-tile">
                            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></div>
                            <div>
                                <h3 class="crm-action-tile__title">Emitir cobranca</h3>
                                <p class="crm-action-tile__text">Criar fatura com gateway, descricao e envio operacional.</p>
                            </div>
                        </a>
                        <a href="{{ route('admin.billing.calendar') }}" class="crm-action-tile">
                            <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-calendar-check"></i></div>
                            <div>
                                <h3 class="crm-action-tile__title">Revisar agenda</h3>
                                <p class="crm-action-tile__text">Ajustar vencimentos e checar bloqueios por feriado.</p>
                            </div>
                        </a>
                        <a href="{{ route('admin.crm.funnel') }}" class="crm-action-tile">
                            <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-kanban"></i></div>
                            <div>
                                <h3 class="crm-action-tile__title">Atualizar funil</h3>
                                <p class="crm-action-tile__text">Mover oportunidades e priorizar negociacoes abertas.</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
