@extends('admin.layouts.app')
@section('title', 'Dashboard')

@push('head')
<style>
.admin-dashboard-page {
    --dashboard-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --dashboard-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.dashboard-kpi {
    height: 100%;
}
.dashboard-kpi .card-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.dashboard-kpi__label {
    display: block;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .45rem;
}
.dashboard-kpi__value {
    display: block;
    font-size: 1.55rem;
    font-weight: 700;
    line-height: 1.05;
}
.dashboard-kpi__hint {
    display: block;
    margin-top: .5rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
}
.dashboard-panel {
    height: 100%;
}
.dashboard-panel .card-header {
    padding: 1rem 1rem .9rem;
}
.dashboard-panel .card-body {
    padding: 1rem;
}
.dashboard-panel__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}
.dashboard-panel__subtitle {
    display: block;
    margin-top: .15rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
}
.dashboard-stack {
    display: grid;
    gap: .85rem;
}
.dashboard-stack__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .9rem 1rem;
    border: 1px solid var(--dashboard-border-soft);
    border-radius: 14px;
    background: var(--dashboard-surface-soft);
}
.dashboard-stack__label {
    color: var(--bs-secondary-color);
    font-size: .84rem;
}
.dashboard-stack__value {
    font-size: 1rem;
    font-weight: 700;
}
.dashboard-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    vertical-align: middle;
}
.dashboard-table tbody td {
    vertical-align: middle;
}
.dashboard-empty {
    display: grid;
    place-items: center;
    min-height: 180px;
    color: var(--bs-secondary-color);
    text-align: center;
}
</style>
@endpush

@section('content')
<div class="container-fluid admin-dashboard-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Painel operacional</span>
            <h1 class="admin-premium-hero__title">Financeiro, WhatsApp, tenancy e sistema em uma leitura mais organizada.</h1>
            <p class="admin-premium-hero__text">
                O dashboard agora separa melhor as frentes do negocio, reduz o ruido dos indicadores e deixa o acompanhamento rapido mais consistente com o restante do administrativo.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Online agora</span>
                <span class="dashboard-note__value text-success" id="kpi-online-now">0</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Visitas hoje</span>
                <span class="dashboard-note__value text-primary" id="kpi-visits-today">0</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Receita hoje</span>
                <span class="dashboard-note__value text-success" id="kpi-revenue-today">R$ 0</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Receita 30 dias</span>
                <span class="dashboard-note__value" id="kpi-revenue-30d">R$ 0</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Última atualização</span>
                <span class="dashboard-note__value"><span id="kpi-time">--:--:--</span></span>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        {{-- Card 1: Visitantes Online Agora --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Online Agora (Tempo Real)</span>
                        <span class="dashboard-kpi__value text-success" id="kpi-online-now-card">0</span>
                        <span class="dashboard-kpi__hint" id="kpi-online-sub">0 logados • 0 anônimos</span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 2: Visitas Hoje --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Visitas Hoje</span>
                        <span class="dashboard-kpi__value text-primary" id="kpi-visits-today-card">0</span>
                        <span class="dashboard-kpi__hint" id="kpi-visits-today-sub">0 únicos hoje</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-eye-fill"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 3: Receita Hoje --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Receita hoje</span>
                        <span class="dashboard-kpi__value text-success" id="kpi-revenue-today-copy">R$ 0</span>
                        <span class="dashboard-kpi__hint">Faturamento confirmado no dia atual.</span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 4: Faturas Pendentes --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Faturas pendentes</span>
                        <span class="dashboard-kpi__value text-warning" id="kpi-invoices-pending">0</span>
                        <span class="dashboard-kpi__hint">Aguardando pagamento ou ação de follow-up.</span>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 5: Mensagens Enviadas Hoje --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Mensagens enviadas hoje</span>
                        <span class="dashboard-kpi__value text-info" id="kpi-msg-sent">0</span>
                        <span class="dashboard-kpi__hint">WhatsApp com envio concluído.</span>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-whatsapp"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 6: Clientes Ativos --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Clientes ativos</span>
                        <span class="dashboard-kpi__value" id="kpi-customers">0</span>
                        <span class="dashboard-kpi__hint">Base operacional ativa.</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 7: Tenants Ativos --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">Tenants ativos</span>
                        <span class="dashboard-kpi__value text-primary" id="kpi-tenants-active">0</span>
                        <span class="dashboard-kpi__hint">Cadastrados: <span id="kpi-tenants-total">0</span></span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-building"></i></div>
                </div>
            </div>
        </div>

        {{-- Card 8: Instâncias WhatsApp Conectadas --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="dashboard-kpi__label">WhatsApp Conectados</span>
                        <span class="dashboard-kpi__value text-success" id="kpi-instances-connected">0</span>
                        <span class="dashboard-kpi__hint">Instâncias totais: <span id="kpi-instances-total">0</span></span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card dashboard-panel">
                <div class="card-header">
                    <div>
                        <h3 class="dashboard-panel__title">Saude financeira</h3>
                        <span class="dashboard-panel__subtitle">Cobrancas, atraso e ticket medio.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dashboard-stack">
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Receita 30 dias</span>
                            <span class="dashboard-stack__value" id="kpi-revenue-30d-stack">R$ 0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Em atraso</span>
                            <span class="dashboard-stack__value text-danger" id="kpi-invoices-overdue">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Ticket medio</span>
                            <span class="dashboard-stack__value" id="kpi-ticket-medio">R$ 0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dashboard-panel">
                <div class="card-header">
                    <div>
                        <h3 class="dashboard-panel__title">Operacao WhatsApp</h3>
                        <span class="dashboard-panel__subtitle">Entrega, falhas e fila operacional.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dashboard-stack">
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Falhas hoje</span>
                            <span class="dashboard-stack__value text-danger" id="kpi-msg-failed">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Taxa de entrega</span>
                            <span class="dashboard-stack__value text-success" id="kpi-taxa-entrega">100%</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Cobrancas na fila</span>
                            <span class="dashboard-stack__value text-warning" id="kpi-dispatches-pending-stack">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dashboard-panel">
                <div class="card-header">
                    <div>
                        <h3 class="dashboard-panel__title">Infraestrutura</h3>
                        <span class="dashboard-panel__subtitle">Tenancy, jobs e atividade do sistema.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dashboard-stack">
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Tenants ativos</span>
                            <span class="dashboard-stack__value" id="kpi-tenants-active-stack">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Instancias conectadas</span>
                            <span class="dashboard-stack__value text-success" id="kpi-instances-connected-stack">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Sessoes ativas</span>
                            <span class="dashboard-stack__value" id="kpi-sessions">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card dashboard-panel">
                <div class="card-header">
                    <div>
                        <h3 class="dashboard-panel__title">Seguranca em 24 horas</h3>
                        <span class="dashboard-panel__subtitle">WAF, tentativas de login e sinais de falha.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dashboard-stack">
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Bloqueios WAF</span>
                            <span class="dashboard-stack__value text-danger" id="kpi-blocks">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Tentativas de login</span>
                            <span class="dashboard-stack__value" id="kpi-login-attempts">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Logins falhados</span>
                            <span class="dashboard-stack__value text-warning" id="kpi-login-failed">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card dashboard-panel">
                <div class="card-header">
                    <div>
                        <h3 class="dashboard-panel__title">Processamento interno</h3>
                        <span class="dashboard-panel__subtitle">Jobs, armazenamento e volume total da base tenancy.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dashboard-stack">
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Jobs na fila</span>
                            <span class="dashboard-stack__value" id="kpi-jobs-pending">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Jobs falhados</span>
                            <span class="dashboard-stack__value text-danger" id="kpi-jobs-failed">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Armazenamento</span>
                            <span class="dashboard-stack__value" id="kpi-storage">0 MB</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Total de tenants</span>
                            <span class="dashboard-stack__value" id="kpi-tenants-total">0</span>
                        </div>
                        <div class="dashboard-stack__item">
                            <span class="dashboard-stack__label">Total de instancias</span>
                            <span class="dashboard-stack__value" id="kpi-instances-total">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card dashboard-panel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="dashboard-panel__title">Ultimas faturas</h3>
                        <span class="dashboard-panel__subtitle">Cobrancas mais recentes para leitura rapida do fluxo.</span>
                    </div>
                    <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Fatura</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-end">Status</th>
                                    <th class="text-end">Data</th>
                                    <th class="text-end" style="width: 80px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbl-invoices">
                                <tr>
                                    <td colspan="6" class="p-0">
                                        <div class="dashboard-empty">Carregando faturas...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card dashboard-panel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="dashboard-panel__title">Ultimas mensagens</h3>
                        <span class="dashboard-panel__subtitle">Saida recente do canal WhatsApp para conferencia.</span>
                    </div>
                    <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Destino</th>
                                    <th class="text-end">Status</th>
                                    <th class="text-end">Data</th>
                                    <th class="text-end" style="width: 80px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbl-messages">
                                <tr>
                                    <td colspan="4" class="p-0">
                                        <div class="dashboard-empty">Carregando mensagens...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Visualização Rápida de Fatura --}}
    <div class="modal fade" id="modal-invoice-quickview" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header border-bottom py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-receipt fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fs-6 fw-bold mb-0">Fatura <span id="qv-inv-number"></span></h5>
                            <small class="text-muted" id="qv-inv-tenant"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: var(--bs-tertiary-bg);">
                        <div>
                            <small class="text-muted d-block">Valor Total</small>
                            <span class="fs-4 fw-bold text-primary" id="qv-inv-amount">R$ 0,00</span>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Status</small>
                            <span id="qv-inv-status-badge"></span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="text-muted small mb-1">Cliente</label>
                            <div class="fw-semibold text-body fs-6" id="qv-inv-customer"></div>
                            <div class="small text-muted d-flex gap-3 mt-1">
                                <span id="qv-inv-phone" class="d-none"><i class="bi bi-whatsapp text-success me-1"></i><span></span></span>
                                <span id="qv-inv-email" class="d-none"><i class="bi bi-envelope text-info me-1"></i><span></span></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small mb-1">Vencimento</label>
                            <div class="fw-semibold" id="qv-inv-due-date">-</div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small mb-1">Data de Criação</label>
                            <div class="fw-semibold" id="qv-inv-created-at">-</div>
                        </div>
                        <div class="col-12 d-none" id="qv-inv-paid-row">
                            <label class="text-muted small mb-1">Pago em</label>
                            <div class="fw-semibold text-success" id="qv-inv-paid-at">-</div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small mb-1">Descrição</label>
                            <div class="p-2 rounded bg-body-tertiary text-muted small" id="qv-inv-desc" style="white-space: pre-wrap; word-break: break-word;">-</div>
                        </div>
                    </div>

                    <div class="d-none" id="qv-inv-payment-box">
                        <label class="text-muted small mb-1">Link de Pagamento Público</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="qv-inv-payment-url" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="qv-inv-copy-link-btn" title="Copiar link">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <a href="#" target="_blank" class="btn btn-outline-primary" id="qv-inv-open-link-btn" title="Abrir link">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a href="#" class="btn btn-sm btn-primary" id="qv-inv-full-btn">
                        <i class="bi bi-eye me-1"></i>Ver Fatura Completa
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Visualização Rápida de Mensagem --}}
    <div class="modal fade" id="modal-message-quickview" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header border-bottom py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-whatsapp fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fs-6 fw-bold mb-0">Mensagem WhatsApp</h5>
                            <small class="text-muted" id="qv-msg-to"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span id="qv-msg-status-badge"></span>
                        <small class="text-muted" id="qv-msg-date"></small>
                    </div>

                    <label class="text-muted small mb-1">Conteúdo Disparado</label>
                    <div class="p-3 rounded-3 mb-3 border" style="background: rgba(16, 185, 129, 0.05); font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap; word-break: break-word;" id="qv-msg-body">
                        -
                    </div>

                    <div class="d-none" id="qv-msg-error-box">
                        <div class="alert alert-danger py-2 px-3 small mb-0">
                            <strong class="d-block"><i class="bi bi-exclamation-triangle-fill me-1"></i>Motivo da Falha / Resposta:</strong>
                            <span id="qv-msg-error-text"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-whatsapp me-1"></i>Ver no WhatsApp Admin
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const fmt = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    const statusBadge = status => {
        const map = { pending: 'warning', overdue: 'danger', paid: 'success', sent: 'info', failed: 'danger', delivered: 'primary', read: 'secondary' };
        return `<span class="badge text-bg-${map[status] || 'secondary'}">${status}</span>`;
    };

    const setElemText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val !== undefined && val !== null ? val : '';
    };

    async function loadKpis() {
        try {
            const response = await fetch('{{ route("admin.dashboard.kpis") }}', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                console.warn('Dashboard KPIs response not OK:', response.status);
                return;
            }

            const data = await response.json();
            if (!data) return;

            const fin = data.financial || {};
            setElemText('kpi-revenue-today', fmt(fin.revenue_today));
            setElemText('kpi-revenue-today-copy', fmt(fin.revenue_today));
            setElemText('kpi-revenue-30d', fmt(fin.revenue_30d));
            setElemText('kpi-revenue-30d-stack', fmt(fin.revenue_30d));
            setElemText('kpi-invoices-pending', fin.invoices_pending ?? 0);
            setElemText('kpi-invoices-overdue', fin.invoices_overdue ?? 0);
            setElemText('kpi-customers', fin.customers_active ?? 0);
            setElemText('kpi-ticket-medio', fmt(fin.ticket_medio));

            const wa = data.whatsapp || {};
            setElemText('kpi-msg-sent', wa.sent_today ?? 0);
            setElemText('kpi-msg-failed', wa.failed_today ?? 0);
            setElemText('kpi-taxa-entrega', (wa.taxa_entrega ?? 100) + '%');
            setElemText('kpi-dispatches-pending', wa.dispatches_pending ?? 0);
            setElemText('kpi-dispatches-pending-stack', wa.dispatches_pending ?? 0);

            const ten = data.tenancy || {};
            setElemText('kpi-tenants-active', ten.tenants_active ?? 0);
            setElemText('kpi-tenants-active-stack', ten.tenants_active ?? 0);
            setElemText('kpi-tenants-total', ten.tenants_total ?? 0);
            setElemText('kpi-instances-connected', ten.instances_connected ?? 0);
            setElemText('kpi-instances-connected-stack', ten.instances_connected ?? 0);
            setElemText('kpi-instances-total', ten.instances_total ?? 0);

            const sec = data.security || {};
            setElemText('kpi-blocks', sec.blocks_24h ?? 0);
            setElemText('kpi-login-attempts', sec.login_attempts_24h ?? 0);
            setElemText('kpi-login-failed', sec.login_failed_24h ?? 0);

            const sys = data.system || {};
            setElemText('kpi-sessions', sys.sessions_active ?? 0);
            setElemText('kpi-jobs-pending', sys.jobs_pending ?? 0);
            setElemText('kpi-jobs-failed', sys.jobs_failed ?? 0);
            setElemText('kpi-storage', (sys.uploads_mb ?? 0) + ' MB');

            if (data.visits) {
                setElemText('kpi-online-now', data.visits.online_now ?? 0);
                setElemText('kpi-online-now-card', data.visits.online_now ?? 0);
                setElemText('kpi-online-sub', `${data.visits.online_authenticated ?? 0} logados • ${data.visits.online_anonymous ?? 0} anônimos`);
                setElemText('kpi-visits-today', data.visits.visits_today ?? 0);
                setElemText('kpi-visits-today-card', data.visits.visits_today ?? 0);
                setElemText('kpi-visits-today-sub', `${data.visits.unique_today ?? 0} únicos hoje`);
            }

            setElemText('kpi-time', data.timestamp || new Date().toLocaleTimeString('pt-BR'));

            const tblInvoices = document.getElementById('tbl-invoices');
            if (tblInvoices) {
                const invoices = data.recent_invoices || [];
                window.__recentInvoices = invoices;
                tblInvoices.innerHTML = invoices.length
                    ? invoices.map((invoice, idx) => `<tr>
                        <td><strong>${invoice.number || '-'}</strong></td>
                        <td>${invoice.customer || 'Cliente'}</td>
                        <td class="text-end fw-semibold">${fmt(invoice.amount)}</td>
                        <td class="text-end">${statusBadge(invoice.status)}</td>
                        <td class="text-end"><small class="text-muted">${invoice.created_at ? new Date(invoice.created_at).toLocaleDateString('pt-BR') : '-'}</small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" onclick="openInvoiceQuickView(${idx})" title="Visualização rápida da fatura">
                                <i class="bi bi-eye me-1"></i>Ver
                            </button>
                        </td>
                    </tr>`).join('')
                    : '<tr><td colspan="6" class="p-0"><div class="dashboard-empty">Nenhuma fatura recente.</div></td></tr>';
            }

            const tblMessages = document.getElementById('tbl-messages');
            if (tblMessages) {
                const messages = data.recent_messages || [];
                window.__recentMessages = messages;
                tblMessages.innerHTML = messages.length
                    ? messages.map((message, idx) => `<tr>
                        <td><strong>${message.to || '-'}</strong></td>
                        <td class="text-end">${statusBadge(message.status)}</td>
                        <td class="text-end"><small class="text-muted">${message.created_at ? new Date(message.created_at).toLocaleDateString('pt-BR') + ' ' + new Date(message.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '-'}</small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 rounded-pill" onclick="openMessageQuickView(${idx})" title="Visualização rápida da mensagem">
                                <i class="bi bi-chat-text me-1"></i>Ver
                            </button>
                        </td>
                    </tr>`).join('')
                    : '<tr><td colspan="4" class="p-0"><div class="dashboard-empty">Nenhuma mensagem recente.</div></td></tr>';
            }
        } catch (error) {
            console.error('KPI load error:', error);
        }
    }

    // Modal de Visualização Rápida de Fatura
    const invoiceModalEl = document.getElementById('modal-invoice-quickview');
    const invoiceModal = invoiceModalEl ? new bootstrap.Modal(invoiceModalEl) : null;

    window.openInvoiceQuickView = function(idx) {
        const inv = window.__recentInvoices ? window.__recentInvoices[idx] : null;
        if (!inv || !invoiceModal) return;

        document.getElementById('qv-inv-number').textContent = inv.number || '#' + (inv.id || '');
        document.getElementById('qv-inv-tenant').textContent = inv.tenant_name ? 'Empresa: ' + inv.tenant_name : '';
        document.getElementById('qv-inv-amount').textContent = fmt(inv.amount);
        document.getElementById('qv-inv-status-badge').innerHTML = statusBadge(inv.status);
        document.getElementById('qv-inv-customer').textContent = inv.customer || 'Cliente Avulso';

        const phoneEl = document.getElementById('qv-inv-phone');
        if (phoneEl) {
            if (inv.customer_whatsapp) {
                phoneEl.classList.remove('d-none');
                phoneEl.querySelector('span').textContent = inv.customer_whatsapp;
            } else {
                phoneEl.classList.add('d-none');
            }
        }

        const emailEl = document.getElementById('qv-inv-email');
        if (emailEl) {
            if (inv.customer_email) {
                emailEl.classList.remove('d-none');
                emailEl.querySelector('span').textContent = inv.customer_email;
            } else {
                emailEl.classList.add('d-none');
            }
        }

        const dueStr = inv.due_date ? new Date(inv.due_date + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
        document.getElementById('qv-inv-due-date').textContent = dueStr;

        const createdStr = inv.created_at ? new Date(inv.created_at).toLocaleDateString('pt-BR') : '-';
        document.getElementById('qv-inv-created-at').textContent = createdStr;

        const paidRow = document.getElementById('qv-inv-paid-row');
        if (paidRow) {
            if (inv.paid_at) {
                paidRow.classList.remove('d-none');
                document.getElementById('qv-inv-paid-at').textContent = new Date(inv.paid_at).toLocaleDateString('pt-BR');
            } else {
                paidRow.classList.add('d-none');
            }
        }

        document.getElementById('qv-inv-desc').textContent = inv.description || 'Cobrança padrão do sistema';

        const payBox = document.getElementById('qv-inv-payment-box');
        const payUrlInput = document.getElementById('qv-inv-payment-url');
        const payOpenBtn = document.getElementById('qv-inv-open-link-btn');
        if (payBox && payUrlInput && payOpenBtn) {
            if (inv.payment_url) {
                payBox.classList.remove('d-none');
                payUrlInput.value = inv.payment_url;
                payOpenBtn.href = inv.payment_url;
            } else {
                payBox.classList.add('d-none');
            }
        }

        const fullBtn = document.getElementById('qv-inv-full-btn');
        if (fullBtn) {
            fullBtn.href = inv.id ? `{{ url('/admin/billing/invoices') }}/${inv.id}` : '{{ route("admin.billing.invoices.index") }}';
        }

        invoiceModal.show();
    };

    // Copiar link de pagamento
    const copyLinkBtn = document.getElementById('qv-inv-copy-link-btn');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', () => {
            const val = document.getElementById('qv-inv-payment-url')?.value;
            if (val) {
                navigator.clipboard.writeText(val).then(() => {
                    if (typeof Toastify === 'function') {
                        Toastify({
                            text: "Link de pagamento copiado com sucesso!",
                            duration: 2500,
                            gravity: "top",
                            position: "right",
                            style: { background: "linear-gradient(to right, #00b09b, #96c93d)", borderRadius: "8px" }
                        }).showToast();
                    } else if (typeof Swal === 'function') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Link copiado!',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                });
            }
        });
    }

    // Modal de Visualização Rápida de Mensagem
    const messageModalEl = document.getElementById('modal-message-quickview');
    const messageModal = messageModalEl ? new bootstrap.Modal(messageModalEl) : null;

    window.openMessageQuickView = function(idx) {
        const msg = window.__recentMessages ? window.__recentMessages[idx] : null;
        if (!msg || !messageModal) return;

        document.getElementById('qv-msg-to').textContent = msg.to ? 'Destinatário: ' + msg.to : '';
        document.getElementById('qv-msg-status-badge').innerHTML = statusBadge(msg.status);

        const dateStr = msg.created_at ? new Date(msg.created_at).toLocaleDateString('pt-BR') + ' às ' + new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '-';
        document.getElementById('qv-msg-date').textContent = dateStr;

        document.getElementById('qv-msg-body').textContent = msg.message || '(Mensagem sem corpo textual)';

        const errBox = document.getElementById('qv-msg-error-box');
        const errText = document.getElementById('qv-msg-error-text');
        if (errBox && errText) {
            if (msg.status === 'failed' && msg.response) {
                errBox.classList.remove('d-none');
                errText.textContent = msg.response;
            } else {
                errBox.classList.add('d-none');
            }
        }

        messageModal.show();
    };

    loadKpis();
    setInterval(loadKpis, 30000);
})();
</script>
@endpush
