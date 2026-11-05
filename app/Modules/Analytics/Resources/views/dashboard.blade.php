@extends('admin.layouts.app')
@section('title', 'Analytics & Tráfego em Tempo Real')

@push('head')
<style>
.analytics-page {
    --analytics-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --analytics-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.analytics-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.analytics-toolbar__meta {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.analytics-filter {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.analytics-filter .form-select {
    min-width: 190px;
}
.analytics-hero-note {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
}
.analytics-note-card {
    border: 1px solid var(--analytics-border-soft);
    border-radius: 16px;
    padding: 1rem;
    background: var(--analytics-surface-soft);
}
.analytics-note-card__label {
    display: block;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .35rem;
}
.analytics-note-card__value {
    display: block;
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.15;
}

/* KPIs de Visitas e Faturamento */
.analytics-kpi {
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.analytics-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.analytics-kpi .card-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.analytics-kpi__label {
    display: block;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .45rem;
}
.analytics-kpi__value {
    display: block;
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.05;
}
.analytics-kpi__hint {
    display: block;
    margin-top: .5rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
}
.analytics-panel {
    height: 100%;
}
.analytics-panel .card-header {
    padding: 1rem 1rem .9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.analytics-panel .card-body {
    padding: 1rem;
}
.analytics-panel__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}
.analytics-panel__subtitle {
    display: block;
    margin-top: .15rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
    font-weight: 500;
}
.analytics-chart-box {
    position: relative;
    min-height: 320px;
}
.analytics-chart-box--short {
    min-height: 280px;
}
.analytics-insights {
    display: grid;
    gap: .75rem;
}
.analytics-insight {
    border: 1px solid var(--analytics-border-soft);
    border-radius: 14px;
    padding: .9rem 1rem;
    background: var(--analytics-surface-soft);
}
.analytics-insight__label {
    display: block;
    color: var(--bs-secondary-color);
    font-size: .78rem;
    margin-bottom: .2rem;
}
.analytics-insight__value {
    font-size: 1rem;
    font-weight: 700;
}
.analytics-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    vertical-align: middle;
}
.analytics-table tbody td {
    vertical-align: middle;
}
.analytics-table__rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 999px;
    background: var(--bs-tertiary-bg);
    color: var(--bs-body-color);
    font-size: .74rem;
    font-weight: 700;
    margin-right: .5rem;
}
.analytics-empty {
    display: grid;
    place-items: center;
    min-height: 220px;
    color: var(--bs-secondary-color);
    text-align: center;
}
.analytics-loading {
    display: grid;
    place-items: center;
    min-height: 180px;
    color: var(--bs-secondary-color);
}
.analytics-last-update {
    color: var(--bs-secondary-color);
    font-size: .82rem;
}

/* Indicador de tempo real com pulso verde */
.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 3px 10px;
    border-radius: 20px;
    background: rgba(25, 135, 84, 0.15);
    color: #198754;
    border: 1px solid rgba(25, 135, 84, 0.25);
}
.live-dot {
    width: 8px;
    height: 8px;
    background-color: #198754;
    border-radius: 50%;
    position: relative;
    display: inline-block;
}
.live-dot::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: 50%;
    border: 2px solid #198754;
    animation: live-pulse 1.6s cubic-bezier(0.25, 0.1, 0.25, 1) infinite;
}
@keyframes live-pulse {
    0% { transform: scale(0.9); opacity: 1; }
    70% { transform: scale(2.2); opacity: 0; }
    100% { transform: scale(2.2); opacity: 0; }
}

@media (max-width: 991.98px) {
    .analytics-hero-note {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 767.98px) {
    .analytics-hero-note {
        grid-template-columns: 1fr;
    }
    .analytics-filter {
        width: 100%;
    }
    .analytics-filter .form-select,
    .analytics-filter .btn {
        width: 100%;
    }
    .analytics-chart-box,
    .analytics-chart-box--short {
        min-height: 260px;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid analytics-page py-3">

    {{-- HERO HEADER --}}
    <section class="admin-premium-hero mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="admin-premium-hero__eyebrow">Painel Analítico & Tráfego</span>
                <span class="live-indicator"><span class="live-dot"></span> TEMPO REAL</span>
            </div>
            <h1 class="admin-premium-hero__title">Tráfego de visitas, clientes online e métricas financeiras.</h1>
            <p class="admin-premium-hero__text">
                Monitore visitantes em tempo real no sistema, divididos entre usuários autenticados e visitantes anônimos, além de histórico de conversão e faturamento.
            </p>
        </div>
        <div class="analytics-hero-note">
            <div class="analytics-note-card">
                <span class="analytics-note-card__label">Online Agora (Tempo Real)</span>
                <span class="analytics-note-card__value text-success" id="hero-online-now">--</span>
                <small class="text-muted d-block mt-1" id="hero-online-sub" style="font-size: 0.75rem;">Aguardando sinal...</small>
            </div>
            <div class="analytics-note-card">
                <span class="analytics-note-card__label">Visitas Hoje</span>
                <span class="analytics-note-card__value text-primary" id="hero-visits-today">--</span>
                <small class="text-muted d-block mt-1" id="hero-unique-today" style="font-size: 0.75rem;">Únicos: --</small>
            </div>
            <div class="analytics-note-card">
                <span class="analytics-note-card__label">Receita Paga (Recorte)</span>
                <span class="analytics-note-card__value text-body" id="hero-revenue">R$ 0,00</span>
                <small class="text-muted d-block mt-1" id="hero-conversion" style="font-size: 0.75rem;">Conversão: 0%</small>
            </div>
            <div class="analytics-note-card">
                <span class="analytics-note-card__label">Última Sincronização</span>
                <span class="analytics-note-card__value text-secondary" id="hero-last-update">--:--:--</span>
                <small class="text-muted d-block mt-1" id="hero-period-label" style="font-size: 0.75rem;">Últimos 30 dias</small>
            </div>
        </div>
    </section>

    {{-- BARRA DE FILTROS & REFRESH --}}
    <section class="premium-panel mb-4">
        <div class="premium-panel__header analytics-toolbar">
            <div>
                <span class="premium-panel__kicker">Controle de visualização</span>
                <h2 class="premium-panel__title">Período de Análise & Monitoramento</h2>
                <p class="premium-panel__subtitle mb-0">Selecione o horizonte de datas para gráficos e KPIs consolidados.</p>
            </div>
            <div class="analytics-toolbar__meta">
                <div class="analytics-last-update" id="last-update">Atualizado: --</div>
                <div class="analytics-filter">
                    <select id="period-days" class="form-select">
                        <option value="7">Últimos 7 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                        <option value="180">Últimos 6 meses</option>
                        <option value="365">Último ano</option>
                    </select>
                    <button class="btn btn-primary" id="btn-refresh" type="button">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- SEÇÃO 1: KPIS DE VISITAS EM TEMPO REAL & FINANCEIRO --}}
    <div class="row g-3 mb-4" id="kpis-row">
        {{-- Card 1: Visitantes Online Agora --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card analytics-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="analytics-kpi__label mb-0">Online Agora</span>
                            <span class="live-indicator py-0 px-2" style="font-size: 0.65rem;"><span class="live-dot" style="width: 6px; height: 6px;"></span> AO VIVO</span>
                        </div>
                        <span class="analytics-kpi__value text-success" id="kpi-online-total">0</span>
                        <span class="analytics-kpi__hint" id="kpi-online-desc">
                            <strong class="text-primary" id="kpi-online-auth">0</strong> logados • <strong class="text-secondary" id="kpi-online-anon">0</strong> anônimos
                        </span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Visitas Hoje --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card analytics-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="analytics-kpi__label">Visitas Hoje</span>
                        <span class="analytics-kpi__value text-primary" id="kpi-visits-today">0</span>
                        <span class="analytics-kpi__hint" id="kpi-visits-today-desc">
                            <span id="kpi-unique-today">0</span> visitantes únicos
                        </span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Visitas no Período --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card analytics-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="analytics-kpi__label">Total Visitas (Recorte)</span>
                        <span class="analytics-kpi__value text-info" id="kpi-visits-period">0</span>
                        <span class="analytics-kpi__hint" id="kpi-visits-period-desc">
                            <span id="kpi-unique-period">0</span> únicos no período
                        </span>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Taxa de Logados --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card analytics-kpi shadow-sm">
                <div class="card-body">
                    <div>
                        <span class="analytics-kpi__label">Taxa Usuários Logados</span>
                        <span class="analytics-kpi__value text-warning" id="kpi-auth-rate">0%</span>
                        <span class="analytics-kpi__hint">Visitas autenticadas vs públicas</span>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEÇÃO 2: GRÁFICO DE TRÁFEGO DE VISITAS & DISPOSITIVOS --}}
    <div class="row g-4 mb-4">
        {{-- Gráfico de Tráfego de Visitas --}}
        <div class="col-xl-8">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-activity text-primary me-2"></i>Evolução de Tráfego & Visitas
                        </h3>
                        <span class="analytics-panel__subtitle">Visitas totais e visitantes únicos por dia no sistema.</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="traffic-chart-total">0 acessos</span>
                </div>
                <div class="card-body">
                    <div class="analytics-chart-box">
                        <canvas id="chart-traffic"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Distribuição de Dispositivos & Navegadores --}}
        <div class="col-xl-4">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-phone text-info me-2"></i>Aparelhos & Plataformas
                        </h3>
                        <span class="analytics-panel__subtitle">Divisão entre Desktop, Mobile e Tablet.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="analytics-chart-box analytics-chart-box--short">
                        <canvas id="chart-devices"></canvas>
                    </div>
                    <div class="analytics-insights mt-3" id="device-insights"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEÇÃO 3: FEED EM TEMPO REAL & TOP PÁGINAS ACESSADAS --}}
    <div class="row g-4 mb-4">
        {{-- Feed ao Vivo de Visitantes --}}
        <div class="col-xl-7">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
                        <div class="d-flex align-items-center gap-2">
                            <span class="live-indicator"><span class="live-dot"></span> AO VIVO</span>
                            <div>
                                <h3 class="analytics-panel__title mb-0">Visitantes Ativos em Tempo Real</h3>
                                <span class="analytics-panel__subtitle">Feed contínuo com usuários logados e visitantes navegando agora.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="form-check form-switch m-0 d-flex align-items-center gap-2" title="Quando desativado (Modo Sessão Única), novo login desconecta qualquer outra máquina automaticamente.">
                                <input class="form-check-input" type="checkbox" role="switch" id="toggle-concurrent-sessions" {{ ($concurrentAllowed ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label small text-muted user-select-none" for="toggle-concurrent-sessions">Multi-login</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" id="btn-terminate-others" title="Desconectar todas as outras sessões ativas do sistema imediatamente">
                                <i class="bi bi-shield-x me-1"></i>Derrubar Outras
                            </button>
                            <span class="badge bg-success-subtle text-success small" id="live-feed-counter">Atualizando...</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover analytics-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Status / Usuário</th>
                                    <th>Página Acessada</th>
                                    <th>Dispositivo / Browser</th>
                                    <th>Horário</th>
                                    <th class="text-end">Sessão / Ação</th>
                                </tr>
                            </thead>
                            <tbody id="live-feed-body">
                                <tr>
                                    <td colspan="4" class="text-center text-muted p-4">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Carregando visitantes em tempo real...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top Páginas Mais Acessadas --}}
        <div class="col-xl-5">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-compass text-primary me-2"></i>Páginas Mais Acessadas
                        </h3>
                        <span class="analytics-panel__subtitle">Rotas mais visitadas do sistema no período selecionado.</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Top Rotas</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover analytics-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Rota / Caminho</th>
                                    <th class="text-end">Acessos</th>
                                    <th class="text-end">Logados</th>
                                </tr>
                            </thead>
                            <tbody id="top-paths-body">
                                <tr>
                                    <td colspan="3" class="text-center text-muted p-4">Carregando páginas...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEÇÃO 4: RECEITA FINANCEIRA & DISPAROS WHATSAPP --}}
    <div class="row g-4 mb-4">
        {{-- Receita por Dia --}}
        <div class="col-xl-8">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-cash-stack text-success me-2"></i>Receita Paga por Dia
                        </h3>
                        <span class="analytics-panel__subtitle">Evolução do faturamento quitado no período.</span>
                    </div>
                    <span class="badge bg-success-subtle text-success fs-6" id="revenue-total">R$ 0,00</span>
                </div>
                <div class="card-body">
                    <div class="analytics-chart-box">
                        <canvas id="chart-revenue"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Distribuição de Faturas --}}
        <div class="col-xl-4">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-pie-chart text-warning me-2"></i>Status das Faturas
                        </h3>
                        <span class="analytics-panel__subtitle">Separação por status de pagamento.</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="analytics-chart-box analytics-chart-box--short">
                        <canvas id="chart-invoices"></canvas>
                    </div>
                    <div class="analytics-insights mt-3" id="invoice-insights"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEÇÃO 5: DISPAROS WHATSAPP & TOP CLIENTES --}}
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-whatsapp text-success me-2"></i>Envios WhatsApp por Dia
                        </h3>
                        <span class="analytics-panel__subtitle">Volume operacional agrupado por status de envio.</span>
                    </div>
                    <span class="badge bg-info-subtle text-info" id="dispatches-total">0 envios</span>
                </div>
                <div class="card-body">
                    <div class="analytics-chart-box analytics-chart-box--short">
                        <canvas id="chart-dispatches"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card analytics-panel shadow-sm">
                <div class="card-header bg-body-tertiary">
                    <div>
                        <h3 class="analytics-panel__title">
                            <i class="bi bi-trophy text-warning me-2"></i>Top Clientes
                        </h3>
                        <span class="analytics-panel__subtitle">Quem mais gerou receita paga no período.</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Top 10</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover analytics-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th class="text-end">Receita</th>
                                    <th class="text-end">Faturas</th>
                                </tr>
                            </thead>
                            <tbody id="top-clients-body">
                                <tr>
                                    <td colspan="3" class="text-center text-muted p-4">Carregando dados...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(function () {
    const chartNodes = {
        traffic: document.getElementById('chart-traffic'),
        devices: document.getElementById('chart-devices'),
        revenue: document.getElementById('chart-revenue'),
        invoices: document.getElementById('chart-invoices'),
        dispatches: document.getElementById('chart-dispatches'),
    };
    const charts = {};
    let currentDays = Number(document.getElementById('period-days').value || 30);
    let cachedPayload = null;
    let refreshTimer = null;
    let livePollingTimer = null;

    const moneyFormatter = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
    });

    function themeTokens() {
        const style = getComputedStyle(document.documentElement);
        const bodyColor = style.getPropertyValue('--bs-body-color').trim() || '#212529';
        const secondaryColor = style.getPropertyValue('--bs-secondary-color').trim() || '#6c757d';
        const borderColor = style.getPropertyValue('--bs-border-color').trim() || 'rgba(0,0,0,.08)';

        // Paleta Analítica Profissional com Alto Contraste (Sem Monocromia)
        const blue = '#2563eb';      // Visitas Totais / Desktop (Azul Royal)
        const purple = '#8b5cf6';    // Visitantes Únicos / Tablets (Roxo / Violeta)
        const emerald = '#10b981';   // Usuários Logados / Receita Paga (Verde Esmeralda)
        const amber = '#f59e0b';     // Faturas Pendentes / Tablets (Âmbar / Laranja)
        const red = '#ef4444';       // Faturas Vencidas / Alertas (Vermelho)
        const cyan = '#06b6d4';      // Faturas Enviadas / Tráfego (Ciano)
        const slate = '#64748b';     // Faturas Canceladas (Cinza Slate)
        const greenWa = '#25d366';   // Disparos WhatsApp (Verde Oficial WhatsApp)

        return {
            bodyColor,
            secondaryColor,
            borderColor,
            blue,
            purple,
            emerald,
            amber,
            red,
            cyan,
            slate,
            greenWa,
            primary: blue,
            success: emerald,
            danger: red,
            warning: amber,
            info: cyan,
            secondary: slate,
            chartPalette: [blue, purple, emerald, amber, cyan, red, slate, '#ec4899', '#14b8a6'],
        };
    }

    function applyChartDefaults() {
        const tokens = themeTokens();
        Chart.defaults.color = tokens.secondaryColor;
        Chart.defaults.borderColor = tokens.borderColor;
        return tokens;
    }

    // =========================================================================
    // 1. CARREGAMENTO DOS DADOS COMPLETOS (ANALYTICS & VISITAS)
    // =========================================================================
    async function load(days, options = {}) {
        const { silent = false } = options;
        currentDays = Number(days);

        if (!silent) {
            toggleRefreshState(true);
        }

        try {
            const response = await fetch(`{{ route('admin.analytics.data') }}?days=${currentDays}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) throw new Error('Falha ao obter dados analíticos');

            const payload = await response.json();
            cachedPayload = payload;
            render(payload);
        } catch (error) {
            console.error('Erro ao carregar dados do Analytics:', error);
            if (!silent && typeof Toastify === 'function') {
                Toastify({
                    text: 'Erro ao carregar métricas analíticas.',
                    style: { background: '#dc3545' },
                    duration: 3500,
                }).showToast();
            }
        } finally {
            if (!silent) {
                toggleRefreshState(false);
            }
            scheduleRefresh();
        }
    }

    // =========================================================================
    // 2. POLLING RÁPIDO DE VISITANTES EM TEMPO REAL (8 SEGUNDOS)
    // =========================================================================
    async function pollLiveVisitors() {
        try {
            const resp = await fetch('{{ route('admin.analytics.live-visitors') }}', {
                headers: { 'Accept': 'application/json' },
            });
            if (resp.ok) {
                const liveData = await resp.json();
                updateOnlineDom(liveData.online, liveData.visits_today);
                renderLiveFeed(liveData.live_feed);
            }
        } catch (e) {
            // Silencioso em caso de erro momentâneo
        } finally {
            livePollingTimer = setTimeout(pollLiveVisitors, 8000);
        }
    }

    function updateOnlineDom(online, visitsToday) {
        if (!online) return;

        // Hero Header
        const heroOnline = document.getElementById('hero-online-now');
        if (heroOnline) heroOnline.textContent = numberValue(online.online_total);

        const heroSub = document.getElementById('hero-online-sub');
        if (heroSub) {
            heroSub.textContent = `${online.online_authenticated} logados • ${online.online_anonymous} anônimos`;
        }

        // Card Online Agora
        const kpiTotal = document.getElementById('kpi-online-total');
        if (kpiTotal) kpiTotal.textContent = numberValue(online.online_total);

        const kpiAuth = document.getElementById('kpi-online-auth');
        if (kpiAuth) kpiAuth.textContent = numberValue(online.online_authenticated);

        const kpiAnon = document.getElementById('kpi-online-anon');
        if (kpiAnon) kpiAnon.textContent = numberValue(online.online_anonymous);

        // Se visitsToday estiver presente
        if (visitsToday) {
            const heroVisitsToday = document.getElementById('hero-visits-today');
            if (heroVisitsToday) heroVisitsToday.textContent = numberValue(visitsToday.visits_today);

            const heroUniqueToday = document.getElementById('hero-unique-today');
            if (heroUniqueToday) heroUniqueToday.textContent = `Únicos: ${numberValue(visitsToday.unique_today)}`;

            const kpiVisitsToday = document.getElementById('kpi-visits-today');
            if (kpiVisitsToday) kpiVisitsToday.textContent = numberValue(visitsToday.visits_today);

            const kpiUniqueToday = document.getElementById('kpi-unique-today');
            if (kpiUniqueToday) kpiUniqueToday.textContent = numberValue(visitsToday.unique_today);
        }

        const feedCounter = document.getElementById('live-feed-counter');
        if (feedCounter) feedCounter.textContent = `${online.online_total} online agora`;
    }

    // =========================================================================
    // 3. RENDERIZAÇÃO GERAL DO PAINEL
    // =========================================================================
    function render(payload) {
        const tokens = applyChartDefaults();
        const periodLabel = periodText(payload.period.days);

        // Atualização do Hero
        document.getElementById('hero-period-label').textContent = periodLabel;
        document.getElementById('hero-last-update').textContent = new Date().toLocaleTimeString('pt-BR');
        document.getElementById('last-update').textContent = `Atualizado: ${new Date().toLocaleTimeString('pt-BR')}`;
        document.getElementById('hero-revenue').textContent = formatMoney(payload.kpis.total_revenue);
        document.getElementById('hero-conversion').textContent = `Conversão: ${formatPercent(payload.kpis.conversion_rate)}`;

        // Atualização de Visitas
        if (payload.online_stats) {
            updateOnlineDom(payload.online_stats, payload.visits_kpis);
        }

        if (payload.visits_kpis) {
            const vk = payload.visits_kpis;
            document.getElementById('kpi-visits-today').textContent = numberValue(vk.visits_today);
            document.getElementById('kpi-unique-today').textContent = numberValue(vk.unique_today);
            document.getElementById('kpi-visits-period').textContent = numberValue(vk.visits_period);
            document.getElementById('kpi-unique-period').textContent = numberValue(vk.unique_period);
            document.getElementById('kpi-auth-rate').textContent = `${vk.auth_rate_percentage}%`;
        }

        // Renderização dos Gráficos e Tabelas
        renderTrafficChart(payload.visits_traffic || [], tokens);
        renderDevicesChart(payload.visits_devices || [], tokens);
        renderLiveFeed(payload.live_feed || []);
        renderTopPaths(payload.top_paths || []);

        renderRevenue(payload.revenue || [], payload.kpis || {}, tokens);
        renderInvoices(payload.invoices || [], tokens);
        renderDispatches(payload.dispatches || [], tokens);
        renderTopClients(payload.top_clients || []);
    }

    // =========================================================================
    // 4. GRÁFICO DE TRÁFEGO DE VISITAS
    // =========================================================================
    function renderTrafficChart(rows, tokens) {
        const totalAcessos = rows.reduce((acc, curr) => acc + Number(curr.total_visits || 0), 0);
        document.getElementById('traffic-chart-total').textContent = `${numberValue(totalAcessos)} acessos no recorte`;

        const labels = rows.map(r => shortDate(r.date));
        const totalVisits = rows.map(r => Number(r.total_visits || 0));
        const uniqueVisits = rows.map(r => Number(r.unique_visits || 0));
        const authVisits = rows.map(r => Number(r.auth_visits || 0));

        rebuildChart('traffic', {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Visitas Totais',
                        data: totalVisits,
                        borderColor: tokens.blue,
                        backgroundColor: alpha(tokens.blue, 0.15),
                        fill: true,
                        tension: 0.35,
                        pointRadius: totalVisits.length > 1 ? 3 : 5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: tokens.blue,
                        borderWidth: 2.5,
                    },
                    {
                        label: 'Visitantes Únicos',
                        data: uniqueVisits,
                        borderColor: tokens.purple,
                        backgroundColor: 'transparent',
                        borderDash: [5, 4],
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: tokens.purple,
                        borderWidth: 2.5,
                    },
                    {
                        label: 'Usuários Logados',
                        data: authVisits,
                        borderColor: tokens.emerald,
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: tokens.emerald,
                        borderWidth: 2.5,
                    }
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 14,
                            boxHeight: 14,
                            borderRadius: 4,
                            useBorderRadius: true,
                            padding: 16,
                            color: tokens.secondaryColor,
                            font: { weight: '600', size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.dataset.label}: ${numberValue(ctx.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, callback: val => numberValue(val) },
                    },
                },
            },
        });
    }

    // =========================================================================
    // 5. GRÁFICO DE DISPOSITIVOS & APARELHOS
    // =========================================================================
    function renderDevicesChart(rows, tokens) {
        const deviceMap = {
            desktop: { label: 'Desktop / Computador', color: tokens.blue, icon: 'bi-laptop' },
            mobile:  { label: 'Smartphone / Mobile', color: tokens.emerald, icon: 'bi-phone' },
            tablet:  { label: 'Tablet / iPad', color: tokens.amber, icon: 'bi-tablet' },
        };

        const total = rows.reduce((acc, curr) => acc + Number(curr.total || 0), 0);
        const labels = rows.map(r => deviceMap[r.device_type]?.label || r.device_type);
        const values = rows.map(r => Number(r.total || 0));
        const colors = rows.map(r => deviceMap[r.device_type]?.color || tokens.secondary);

        rebuildChart('devices', {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                },
            },
        });

        // Insights de dispositivos
        const insightsContainer = document.getElementById('device-insights');
        if (rows.length === 0) {
            insightsContainer.innerHTML = '<div class="text-center text-muted small py-2">Sem dados de dispositivos registrados.</div>';
            return;
        }

        insightsContainer.innerHTML = rows.map(r => {
            const meta = deviceMap[r.device_type] || { label: r.device_type, color: tokens.secondary, icon: 'bi-display' };
            const count = Number(r.total || 0);
            const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            return `
                <div class="analytics-insight d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi ${meta.icon}" style="color: ${meta.color}; font-size: 1.1rem;"></i>
                        <span class="analytics-insight__label mb-0 fw-semibold text-body">${meta.label}</span>
                    </div>
                    <div class="text-end">
                        <span class="analytics-insight__value">${numberValue(count)}</span>
                        <small class="text-muted ms-1">(${pct}%)</small>
                    </div>
                </div>
            `;
        }).join('');
    }

    // =========================================================================
    // 6. TABELA DE FEED DE VISITANTES EM TEMPO REAL
    // =========================================================================
    function renderLiveFeed(feed) {
        const body = document.getElementById('live-feed-body');
        if (!body) return;

        if (!feed || feed.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted p-4">
                        <i class="bi bi-clock-history d-block mb-1 fs-4"></i>
                        Nenhuma visita recente registrada ainda.
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = feed.map(item => {
            const isOnlineBadge = item.is_online_now
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-1"><span class="live-dot" style="width: 5px; height: 5px;"></span> Online</span>'
                : `<span class="badge bg-secondary-subtle text-secondary rounded-pill">${escapeHtml(item.time_ago)}</span>`;

            let userHtml = '';
            if (item.is_authenticated && item.user_name) {
                const isAdmin = item.role === 'admin';
                const avatarBg = isAdmin ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary';
                const badgeClass = isAdmin ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-primary-subtle text-primary';
                const badgeLabel = item.role_label || (isAdmin ? 'Administrador' : 'Tenant');

                userHtml = `
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle ${avatarBg} fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 28px; height: 28px; font-size: 0.75rem; flex-shrink: 0;">
                            ${escapeHtml(item.user_name.charAt(0).toUpperCase())}
                        </div>
                        <div class="min-w-0" style="line-height: 1.2;">
                            <div class="fw-bold text-body text-truncate" style="max-width: 150px;">${escapeHtml(item.user_name)}</div>
                            <span class="badge ${badgeClass}" style="font-size: 0.65rem;">${badgeLabel}</span>
                        </div>
                    </div>
                `;
            } else {
                userHtml = `
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-body-secondary text-secondary d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem; flex-shrink: 0;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="min-w-0" style="line-height: 1.2;">
                            <div class="text-muted text-truncate" style="max-width: 150px; font-size: 0.8rem;">Visitante Anônimo</div>
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">Apenas visitando</span>
                        </div>
                    </div>
                `;
            }

            const deviceIcon = item.device === 'mobile' ? 'bi-phone' : (item.device === 'tablet' ? 'bi-tablet' : 'bi-laptop');

            let actionHtml = '';
            if (item.is_current_session) {
                actionHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle" title="Você está navegando nesta sessão agora"><i class="bi bi-laptop me-1"></i>Esta Sessão</span>';
            } else if (item.session_id) {
                actionHtml = `
                    <button type="button" class="btn btn-xs btn-outline-danger btn-disconnect-session py-1 px-2" data-session-id="${escapeHtml(item.session_id)}" data-user-name="${escapeHtml(item.user_name || 'Visitante')}" title="Desconectar esta máquina / encerrar sessão ativa">
                        <i class="bi bi-box-arrow-right me-1"></i>Desconectar
                    </button>
                `;
            } else {
                actionHtml = '<span class="text-muted small">-</span>';
            }

            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            ${isOnlineBadge}
                            ${userHtml}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-body-secondary text-body font-monospace text-truncate" style="max-width: 170px;" title="${escapeHtml(item.path)}">
                            ${escapeHtml(item.path)}
                        </span>
                    </td>
                    <td>
                        <div class="small text-muted d-flex align-items-center gap-1">
                            <i class="bi ${deviceIcon}"></i>
                            <span>${escapeHtml(item.browser || 'Web')} • ${escapeHtml(item.platform || 'OS')}</span>
                        </div>
                    </td>
                    <td class="text-muted small font-monospace">
                        ${escapeHtml(item.time_formatted || item.time_ago)}
                    </td>
                    <td class="text-end">
                        ${actionHtml}
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Handlers para desconexão de sessões
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-disconnect-session');
        if (!btn) return;

        const sessionId = btn.getAttribute('data-session-id');
        const userName = btn.getAttribute('data-user-name');
        if (!sessionId) return;

        const result = await Swal.fire({
            title: 'Desconectar Sessão?',
            html: `Tem certeza que deseja desconectar o login de <strong>${escapeHtml(userName)}</strong>?<br><small class="text-muted">A máquina/dispositivo será deslogada imediatamente.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Sim, Desconectar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger me-2',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false
        });

        if (!result.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const resp = await fetch('/admin/analytics/sessions/terminate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ session_id: sessionId })
            });
            const data = await resp.json();

            if (data.success) {
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: data.message || 'Sessão desconectada com sucesso!',
                        duration: 3500,
                        gravity: 'top',
                        position: 'right',
                        backgroundColor: '#10b981'
                    }).showToast();
                }
                pollLiveVisitors();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Atenção',
                    text: data.message || 'Não foi possível desconectar a sessão.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-box-arrow-right me-1"></i>Desconectar';
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Falha de comunicação ao tentar desconectar a sessão.',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-box-arrow-right me-1"></i>Desconectar';
        }
    });

    const btnTerminateOthers = document.getElementById('btn-terminate-others');
    if (btnTerminateOthers) {
        btnTerminateOthers.addEventListener('click', async function () {
            const result = await Swal.fire({
                title: 'Derrubar Outras Sessões?',
                html: 'Deseja desconectar todas as outras sessões ativas no sistema?<br><small class="text-muted">Apenas a sua máquina/sessão atual continuará conectada.</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-shield-x me-1"></i> Sim, Derrubar Outras',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger me-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            });

            if (!result.isConfirmed) return;

            btnTerminateOthers.disabled = true;
            btnTerminateOthers.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Derrubando...';

            try {
                const resp = await fetch('/admin/analytics/sessions/terminate-others', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                const data = await resp.json();

                if (data.success) {
                    if (typeof Toastify === 'function') {
                        Toastify({
                            text: data.message || 'Sessões externas encerradas!',
                            duration: 4000,
                            gravity: 'top',
                            position: 'right',
                            backgroundColor: '#10b981'
                        }).showToast();
                    }
                    pollLiveVisitors();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Atenção',
                        text: data.message || 'Erro ao tentar derrubar sessões.',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Falha de comunicação com o servidor.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
            } finally {
                btnTerminateOthers.disabled = false;
                btnTerminateOthers.innerHTML = '<i class="bi bi-shield-x me-1"></i>Derrubar Outras';
            }
        });
    }

    const toggleConcurrent = document.getElementById('toggle-concurrent-sessions');
    if (toggleConcurrent) {
        toggleConcurrent.addEventListener('change', async function () {
            const allowed = this.checked;
            try {
                const resp = await fetch('/admin/analytics/sessions/toggle-concurrent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ allowed: allowed })
                });
                const data = await resp.json();
                if (data.success) {
                    if (typeof Toastify === 'function') {
                        Toastify({
                            text: data.message,
                            duration: 3500,
                            gravity: 'top',
                            position: 'right',
                            backgroundColor: allowed ? '#2563eb' : '#f59e0b'
                        }).showToast();
                    }
                    pollLiveVisitors();
                }
            } catch (err) {
                this.checked = !allowed;
            }
        });
    }

    function renderTopPaths(paths) {
        const body = document.getElementById('top-paths-body');
        if (!body) return;

        if (!paths || paths.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted p-4">
                        Nenhum registro de páginas ainda.
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = paths.map((p, idx) => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="analytics-table__rank">${idx + 1}</span>
                        <span class="font-monospace fw-semibold text-body text-truncate" style="max-width: 220px;" title="${escapeHtml(p.path)}">
                            ${escapeHtml(p.path)}
                        </span>
                    </div>
                </td>
                <td class="text-end fw-bold text-primary">
                    ${numberValue(p.total)}
                </td>
                <td class="text-end">
                    <span class="badge bg-success-subtle text-success small">${numberValue(p.auth_count || 0)} logados</span>
                </td>
            </tr>
        `).join('');
    }

    // =========================================================================
    // 8. GRÁFICOS FINANCEIROS E TOP CLIENTES
    // =========================================================================
    function renderRevenue(rows, kpis, tokens) {
        document.getElementById('revenue-total').textContent = formatMoney(kpis.total_revenue);

        const labels = rows.map(row => shortDate(row.d));
        const values = rows.map(row => Number(row.total));

        rebuildChart('revenue', {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Receita paga',
                    data: values,
                    borderColor: tokens.success,
                    backgroundColor: alpha(tokens.success, 0.14),
                    fill: true,
                    tension: 0.35,
                    pointRadius: values.length > 1 ? 2 : 4,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => compactMoney(value) },
                    },
                },
            },
        });
    }

    function renderInvoices(rows, tokens) {
        const statusMap = {
            pending: { label: 'Pendentes', color: tokens.warning },
            paid: { label: 'Pagas', color: tokens.success },
            overdue: { label: 'Vencidas', color: tokens.danger },
            sent: { label: 'Enviadas', color: tokens.info },
            canceled: { label: 'Canceladas', color: tokens.secondary },
        };

        const labels = rows.map(r => statusMap[r.status]?.label || r.status);
        const values = rows.map(r => Number(r.total));
        const colors = rows.map(r => statusMap[r.status]?.color || tokens.secondary);

        rebuildChart('invoices', {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } },
            },
        });

        const insights = document.getElementById('invoice-insights');
        insights.innerHTML = rows.map(r => {
            const meta = statusMap[r.status] || { label: r.status, color: tokens.secondary };
            return `
                <div class="analytics-insight d-flex justify-content-between align-items-center">
                    <span class="analytics-insight__label mb-0" style="color: ${meta.color}; font-weight: 600;">● ${meta.label}</span>
                    <span class="analytics-insight__value">${numberValue(r.total)}</span>
                </div>
            `;
        }).join('');
    }

    function renderDispatches(rows, tokens) {
        const total = rows.reduce((acc, curr) => acc + Number(curr.total || 0), 0);
        document.getElementById('dispatches-total').textContent = `${numberValue(total)} envios`;

        const grouped = {};
        rows.forEach(r => {
            const date = shortDate(r.d);
            grouped[date] = (grouped[date] || 0) + Number(r.total || 0);
        });

        rebuildChart('dispatches', {
            type: 'bar',
            data: {
                labels: Object.keys(grouped),
                datasets: [{
                    label: 'Disparos',
                    data: Object.values(grouped),
                    backgroundColor: tokens.greenWa,
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                },
            },
        });
    }

    function renderTopClients(rows) {
        const body = document.getElementById('top-clients-body');
        if (rows.length === 0) {
            body.innerHTML = `<tr><td colspan="3" class="text-center text-muted p-4">Sem faturamento no período.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map((r, i) => `
            <tr>
                <td><span class="analytics-table__rank">${i + 1}</span> ${escapeHtml(r.name)}</td>
                <td class="text-end fw-semibold">${formatMoney(r.total)}</td>
                <td class="text-end"><span class="badge bg-primary-subtle text-primary">${numberValue(r.invoices)}</span></td>
            </tr>
        `).join('');
    }

    function rebuildChart(key, config) {
        if (charts[key]) {
            charts[key].destroy();
        }
        if (chartNodes[key]) {
            charts[key] = new Chart(chartNodes[key], config);
        }
    }

    function toggleRefreshState(loading) {
        const button = document.getElementById('btn-refresh');
        if (button) {
            button.disabled = loading;
            button.innerHTML = loading
                ? '<span class="spinner-border spinner-border-sm me-2"></span>Atualizando...'
                : '<i class="bi bi-arrow-clockwise me-1"></i> Atualizar';
        }
    }

    function scheduleRefresh() {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => load(currentDays, { silent: true }), 60000);
    }

    function periodText(days) {
        const map = { 7: 'Últimos 7 dias', 30: 'Últimos 30 dias', 90: 'Últimos 90 dias', 180: 'Últimos 6 meses', 365: 'Último ano' };
        return map[days] || `Últimos ${days} dias`;
    }

    function formatMoney(val) { return moneyFormatter.format(Number(val || 0)); }
    function compactMoney(val) {
        const n = Number(val || 0);
        return Math.abs(n) >= 1000 ? 'R$ ' + (n / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : 'R$ ' + n.toLocaleString('pt-BR');
    }
    function formatPercent(val) { return Number(val || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%'; }
    function numberValue(val) { return Number(val || 0).toLocaleString('pt-BR'); }
    function shortDate(val) {
        const d = new Date(val + 'T12:00:00');
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    }
    function alpha(hex, op) {
        const c = hex.replace('#', '');
        return c.length === 6 ? `rgba(${parseInt(c.slice(0,2),16)}, ${parseInt(c.slice(2,4),16)}, ${parseInt(c.slice(4,6),16)}, ${op})` : hex;
    }
    function escapeHtml(val) {
        return String(val ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);
    }

    document.getElementById('period-days').addEventListener('change', e => load(e.target.value));
    document.getElementById('btn-refresh').addEventListener('click', () => load(currentDays));

    window.addEventListener('admin:theme-change', () => {
        if (cachedPayload) render(cachedPayload);
    });

    // Carga inicial e início do polling de visitantes online
    load(currentDays);
    pollLiveVisitors();
})();
</script>
@endpush
