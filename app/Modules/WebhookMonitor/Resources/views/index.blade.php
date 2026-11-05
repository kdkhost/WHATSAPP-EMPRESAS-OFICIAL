@extends('admin.layouts.app')
@section('title', 'Monitor de Webhooks')

@push('head')
<style>
.webhook-monitor-page {
    --wm-healthy: #198754;
    --wm-warning: #ffc107;
    --wm-critical: #dc3545;
}

/* Status Dot Pulsante */
.wm-status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    position: relative;
    flex-shrink: 0;
}
.wm-status-dot.healthy {
    background-color: var(--wm-healthy);
    box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7);
    animation: wm-pulse-green 2s infinite;
}
.wm-status-dot.warning {
    background-color: var(--wm-warning);
    box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
    animation: wm-pulse-yellow 2s infinite;
}
.wm-status-dot.critical {
    background-color: var(--wm-critical);
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    animation: wm-pulse-red 2s infinite;
}
.wm-status-dot.no_data {
    background-color: var(--bs-secondary);
}

@keyframes wm-pulse-green {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
}
@keyframes wm-pulse-yellow {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}
@keyframes wm-pulse-red {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}

/* Gateway Cards Harmonizados com Dark/Light */
.wm-gateway-card {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: var(--app-radius-premium);
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    background: var(--app-surface-bg);
}
.wm-gateway-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--app-shadow-premium);
    border-color: color-mix(in srgb, var(--bs-primary) 35%, var(--bs-border-color));
}

.wm-gateway-mini-stats {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
}
.wm-gateway-mini-stats .border-start,
.wm-gateway-mini-stats .border-end {
    border-color: var(--bs-border-color) !important;
}

[data-bs-theme="dark"] .wm-gateway-card {
    background: var(--admin-dark-surface, #1e293b) !important;
    border-color: var(--admin-dark-border, #334155) !important;
}
[data-bs-theme="dark"] .wm-gateway-card .border-top {
    border-color: var(--admin-dark-border, #334155) !important;
}
[data-bs-theme="dark"] .wm-gateway-mini-stats {
    background: var(--admin-dark-field, #0f172a);
    border-color: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] .wm-gateway-mini-stats .border-start,
[data-bs-theme="dark"] .wm-gateway-mini-stats .border-end {
    border-color: var(--admin-dark-border, #334155) !important;
}

/* Event Code Badge Harmonizado com Tema */
.wm-event-code {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    background: color-mix(in srgb, var(--bs-primary) 8%, var(--app-surface-bg));
    border: 1px solid color-mix(in srgb, var(--bs-primary) 22%, transparent);
    color: var(--bs-primary);
    border-radius: 6px;
    font-weight: 500;
}
[data-bs-theme="dark"] .wm-event-code {
    background: rgba(13, 110, 253, 0.15);
    border-color: rgba(13, 110, 253, 0.3);
    color: #8bb9fe;
}

/* Modal Metadata Boxes */
.wm-modal-meta-box {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
}
[data-bs-theme="dark"] .wm-modal-meta-box {
    background: var(--admin-dark-field, #0f172a);
    border-color: var(--admin-dark-border, #334155);
}

/* Code Payload Viewer com suporte Light e Dark */
.wm-code-viewer-container {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
}
.wm-code-viewer-header {
    background: var(--bs-tertiary-bg);
    padding: 0.55rem 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--bs-border-color);
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
}
.wm-code-viewer {
    max-height: 480px;
    overflow-y: auto;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.82rem;
    line-height: 1.55;
    margin: 0;
    padding: 1rem;
    border: none;
}
.wm-code-viewer::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.wm-code-viewer::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

[data-bs-theme="dark"] .wm-code-viewer-container {
    border-color: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] .wm-code-viewer-header {
    background: var(--admin-dark-surface, #1e293b);
    border-bottom-color: var(--admin-dark-border, #334155);
    color: #94a3b8;
}
[data-bs-theme="dark"] .wm-code-viewer {
    background: var(--admin-dark-field, #0f172a);
    color: #e2e8f0;
}
[data-bs-theme="dark"] .wm-code-viewer::-webkit-scrollbar-thumb {
    background: #334155;
}
</style>
@endpush

@section('content')
<div class="container-fluid webhook-monitor-page px-0">

    {{-- ========== HEADER ========== --}}
    <x-ui.page-header
        title="Monitor de Webhooks"
        subtitle="Observabilidade em tempo real dos webhooks recebidos dos gateways de pagamento, métricas de integridade e auditoria de payloads."
        eyebrow="Infraestrutura & Gateways"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <button type="button" class="btn btn-outline-secondary" id="btn-toggle-auto" title="Pausar / Retomar Auto-refresh">
                    <i class="bi bi-clock-history me-1 text-success" id="auto-refresh-icon"></i>
                    <span id="auto-refresh-label">Auto-refresh (15s)</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-refresh-manual" title="Atualizar agora">
                    <i class="bi bi-arrow-clockwise me-1" id="manual-refresh-icon"></i>
                    <span>Atualizar</span>
                </button>

                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear me-1"></i> Configurar Gateways
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.gateways.mercadopago') }}">
                                <i class="bi bi-wallet2 text-primary"></i> Mercado Pago
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.gateways.efipro') }}">
                                <i class="bi bi-lightning-charge text-warning"></i> Efí Pro (Gerencianet)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.gateways.sumup') }}">
                                <i class="bi bi-credit-card-2-front text-info"></i> SumUp
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </x-slot:actions>

        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Taxa 24h</span>
                <span class="dashboard-note__value {{ $overview['success_rate'] >= 90 ? 'text-success' : ($overview['success_rate'] >= 75 ? 'text-warning' : 'text-danger') }}">
                    {{ $overview['success_rate'] }}%
                </span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Eventos 24h</span>
                <span class="dashboard-note__value">{{ number_format($overview['total_24h'], 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    {{-- ========== KPIS / INDICADORES GLOBAIS ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Webhooks Recebidos (24h)"
                :value="number_format($overview['total_24h'], 0, ',', '.')"
                icon="bi-broadcast"
                color="primary"
                meta="Notificações nas últimas 24h"
                class="h-100"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Taxa Global de Sucesso"
                :value="$overview['success_rate'] . '%'"
                icon="bi-check2-circle"
                color="success"
                meta="Processados sem falha"
                class="h-100"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Falhas / Erros (24h)"
                :value="number_format($overview['errors_24h'], 0, ',', '.')"
                icon="bi-exclamation-octagon"
                :color="$overview['errors_24h'] > 0 ? 'danger' : 'secondary'"
                meta="Notificações com exceção"
                class="h-100"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-ui.stats-card
                label="Gateways Ativos"
                :value="count($stats) . ' Provedores'"
                icon="bi-shield-check"
                color="info"
                meta="Mercado Pago, Efí e SumUp"
                class="h-100"
            />
        </div>
    </div>

    {{-- ========== CARDS DE SAÚDE DOS GATEWAYS ========== --}}
    <div class="row g-3 mb-4">
        @foreach($stats as $key => $s)
            @php
                $statusMap = [
                    'healthy'  => ['label' => 'Saudável',   'class' => 'success',   'icon' => 'bi-check-circle-fill'],
                    'warning'  => ['label' => 'Atenção',    'class' => 'warning',   'icon' => 'bi-exclamation-triangle-fill'],
                    'critical' => ['label' => 'Crítico',    'class' => 'danger',    'icon' => 'bi-x-circle-fill'],
                    'no_data'  => ['label' => 'Sem dados',  'class' => 'secondary', 'icon' => 'bi-dash-circle'],
                ];
                $meta = $statusMap[$s['status']] ?? ['label' => 'Desconhecido', 'class' => 'secondary', 'icon' => 'bi-question-circle'];
            @endphp
            <div class="col-12 col-md-4">
                <div class="card h-100 wm-gateway-card shadow-sm">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="badge bg-{{ $s['badge'] }}-subtle text-{{ $s['badge'] }} p-2 rounded flex-shrink-0 d-flex align-items-center justify-content-center" style="width:40px;height:40px;font-size:1.15rem;">
                                        <i class="bi {{ $s['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <h6 class="m-0 fw-bold">{{ $s['name'] }}</h6>
                                        <small class="text-secondary d-block" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock me-1"></i>{{ $s['last_event_ago'] }}
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="wm-status-dot {{ $s['status'] }}" title="{{ $meta['label'] }}"></span>
                                    <span class="badge bg-{{ $meta['class'] }}-subtle text-{{ $meta['class'] }} border border-{{ $meta['class'] }}-subtle" style="font-size: 0.72rem;">
                                        <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                                    </span>
                                </div>
                            </div>

                            <div class="row g-2 text-center py-2 px-1 wm-gateway-mini-stats mb-3">
                                <div class="col-4">
                                    <span class="text-secondary d-block small" style="font-size: 0.7rem;">Eventos 24h</span>
                                    <strong class="fs-5">{{ number_format($s['total_24h'], 0, ',', '.') }}</strong>
                                </div>
                                <div class="col-4 border-start border-end">
                                    <span class="text-secondary d-block small" style="font-size: 0.7rem;">Sucesso</span>
                                    <strong class="fs-5 {{ $s['success_rate'] !== null && $s['success_rate'] >= 90 ? 'text-success' : ($s['success_rate'] !== null && $s['success_rate'] >= 75 ? 'text-warning' : 'text-danger') }}">
                                        {{ $s['success_rate'] !== null ? $s['success_rate'] . '%' : '—' }}
                                    </strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-secondary d-block small" style="font-size: 0.7rem;">Erros 24h</span>
                                    <strong class="fs-5 {{ $s['errors_24h'] > 0 ? 'text-danger' : 'text-secondary' }}">
                                        {{ number_format($s['errors_24h'], 0, ',', '.') }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            @if($s['errors_24h'] > 0)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2" style="font-size: 0.72rem;">
                                    <i class="bi bi-exclamation-triangle me-1"></i>{{ $s['errors_24h'] }} erro(s) pendente(s)
                                </span>
                            @else
                                <span class="text-success small fw-semibold" style="font-size: 0.75rem;">
                                    <i class="bi bi-shield-check me-1"></i>Operação estável
                                </span>
                            @endif

                            <a href="{{ route('admin.webhook-monitor.index', ['gateway' => $key]) }}" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.75rem;" title="Filtrar por este gateway">
                                <i class="bi bi-funnel me-1"></i> Filtrar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ========== FILTROS E BUSCA (PADRONIZADOS) ========== --}}
    <x-ui.premium-card title="Filtros e Diagnóstico" subtitle="Filtre por gateway, status de processamento ou pesquise por IP, evento e faturas." class="mb-4">
        <form method="GET" action="{{ route('admin.webhook-monitor.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted">Gateway</label>
                <select name="gateway" class="form-select">
                    <option value="">Todos os gateways</option>
                    @foreach($stats as $gKey => $gMeta)
                        <option value="{{ $gKey }}" @selected(request('gateway') === $gKey)>{{ $gMeta['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="success" @selected(request('status') === 'success')>Processados com sucesso</option>
                    <option value="error" @selected(request('status') === 'error')>Com erro / Falhas</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pendentes</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-muted">Busca textual</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Buscar por IP, evento, fatura, erro..." value="{{ request('q') }}">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1 d-inline-flex align-items-center justify-content-center" style="min-height: 44px;" title="Aplicar filtros">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                @if(request()->hasAny(['gateway', 'status', 'q']))
                    <a href="{{ route('admin.webhook-monitor.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center" style="min-height: 44px;" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    {{-- ========== TABELA DE WEBHOOKS (DATATABLES PADRONIZADO) ========== --}}
    <x-ui.premium-card title="Histórico de Notificações Recebidas" :subtitle="$webhooks->count() . ' webhook(s) listado(s).'">
        @if($webhooks->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-webhook-monitor" data-export-title="Monitor de Webhooks de Pagamento" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Data / Hora</th>
                            <th class="min-tablet" data-priority="2">Gateway</th>
                            <th class="all" data-priority="3">Evento</th>
                            <th class="desktop">Fatura</th>
                            <th class="min-tablet" data-priority="4">Status</th>
                            <th class="desktop">IP Origem</th>
                            <th class="all text-end no-export col-actions" data-priority="1" style="width: 95px; min-width: 90px; max-width: 110px; white-space: nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($webhooks as $w)
                        @php
                            $gatewayMeta = $stats[$w->gateway] ?? [
                                'name' => ucfirst($w->gateway),
                                'badge' => 'secondary',
                                'icon' => 'bi-credit-card'
                            ];
                            $createdAt = \Carbon\Carbon::parse($w->created_at);
                        @endphp
                        <tr id="wh-row-{{ $w->id }}">
                            <td class="all">
                                <div>
                                    <strong class="d-block small text-body">{{ $createdAt->format('d/m/Y H:i:s') }}</strong>
                                    <small class="text-secondary" style="font-size: 0.72rem;">{{ $createdAt->diffForHumans() }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $gatewayMeta['badge'] }}-subtle text-{{ $gatewayMeta['badge'] }} border border-{{ $gatewayMeta['badge'] }}-subtle">
                                    <i class="bi {{ $gatewayMeta['icon'] }} me-1"></i>{{ $gatewayMeta['name'] }}
                                </span>
                            </td>
                            <td>
                                <code class="wm-event-code">{{ $w->event ?: 'webhook_received' }}</code>
                            </td>
                            <td>
                                @if($w->invoice_id)
                                    <a href="{{ route('admin.billing.invoices.index', ['q' => $w->invoice_number ?: $w->invoice_id]) }}" class="badge bg-info-subtle text-info border border-info-subtle text-decoration-none" title="Ver fatura vinculada">
                                        <i class="bi bi-receipt me-1"></i>{{ $w->invoice_number ?: '#' . $w->invoice_id }}
                                    </a>
                                @else
                                    <span class="text-secondary small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($w->processed && ! $w->error)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle me-1"></i>Processado
                                    </span>
                                @elseif($w->error)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle cursor-pointer js-view-error" data-error="{{ $w->error }}" title="Clique para ver o erro">
                                        <i class="bi bi-x-circle me-1"></i>Erro
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-clock me-1"></i>Pendente
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($w->ip)
                                    <code class="small text-body"><i class="bi bi-globe me-1 text-secondary"></i>{{ $w->ip }}</code>
                                @else
                                    <span class="text-secondary small">—</span>
                                @endif
                            </td>
                            <td class="all text-end no-export col-actions" style="width: 95px; min-width: 90px; max-width: 110px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2 js-view-payload" data-id="{{ $w->id }}" title="Inspecionar Payload JSON">
                                        <i class="bi bi-code-slash"></i>
                                    </button>
                                    @if($w->error)
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 js-view-error" data-id="{{ $w->id }}" data-error="{{ $w->error }}" title="Ver mensagem de erro">
                                            <i class="bi bi-exclamation-triangle"></i>
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
            <x-ui.empty-state
                icon="bi-broadcast"
                title="Nenhum webhook encontrado"
                message="Nenhum evento registrado com os filtros selecionados ou nenhum disparo recebido até o momento."
            >
                <x-slot:actions>
                    <button type="button" class="btn btn-primary" id="btn-empty-refresh">
                        <i class="bi bi-arrow-clockwise me-1"></i> Recarregar dados
                    </button>
                </x-slot:actions>
            </x-ui.empty-state>
        @endif
    </x-ui.premium-card>

</div>

{{-- ========== MODAL DE INSPEÇÃO DE PAYLOAD (DARK & LIGHT COERENTE) ========== --}}
<div class="modal fade" id="modalWebhookPayload" tabindex="-1" aria-labelledby="modalWebhookPayloadLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0 fw-bold" id="modalWebhookPayloadLabel">
                        <i class="bi bi-code-slash me-2 text-primary"></i>Inspecionar Payload #<span id="m-wh-id">--</span>
                    </h5>
                    <small class="text-secondary" id="m-wh-subtitle">Carregando detalhes...</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3">
                <div id="m-wh-loader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-secondary mt-2">Carregando payload JSON do banco de dados...</div>
                </div>

                <div id="m-wh-content" class="d-none">
                    {{-- Bloco de Erro, se houver --}}
                    <div id="m-wh-error-box" class="alert alert-danger d-none border-danger mb-3">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Mensagem de Erro Registrada:</div>
                        <div id="m-wh-error-text" class="small font-monospace text-break"></div>
                    </div>

                    {{-- Metadados rápidos adaptados ao tema --}}
                    <div class="row g-2 mb-3 small">
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded wm-modal-meta-box">
                                <span class="text-secondary d-block" style="font-size:0.7rem;text-transform:uppercase;font-weight:600;">Gateway</span>
                                <strong id="m-wh-gateway" class="text-capitalize text-body">--</strong>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded wm-modal-meta-box">
                                <span class="text-secondary d-block" style="font-size:0.7rem;text-transform:uppercase;font-weight:600;">Evento</span>
                                <code id="m-wh-event" class="text-break">--</code>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded wm-modal-meta-box">
                                <span class="text-secondary d-block" style="font-size:0.7rem;text-transform:uppercase;font-weight:600;">IP Origem</span>
                                <code id="m-wh-ip">--</code>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded wm-modal-meta-box">
                                <span class="text-secondary d-block" style="font-size:0.7rem;text-transform:uppercase;font-weight:600;">Status</span>
                                <span id="m-wh-status">--</span>
                            </div>
                        </div>
                    </div>

                    {{-- Código JSON Formatado com suporte a Dark e Light --}}
                    <div class="wm-code-viewer-container">
                        <div class="wm-code-viewer-header">
                            <span><i class="bi bi-file-code me-1 text-primary"></i> payload.json</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn-copy-payload" style="font-size: 0.75rem;" title="Copiar JSON formatado">
                                <i class="bi bi-clipboard me-1"></i> <span id="btn-copy-text">Copiar JSON</span>
                            </button>
                        </div>
                        <pre class="wm-code-viewer"><code id="m-wh-code"></code></pre>
                    </div>
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
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modalWebhookPayload');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    let currentRawPayload = '';
    let autoRefreshActive = true;
    let refreshInterval = null;

    // Toast helper usando Toastify global
    function notify(text, type = 'info') {
        if (typeof window.toast !== 'undefined' && typeof window.toast[type] === 'function') {
            window.toast[type](text);
        } else if (typeof Toastify === 'function') {
            const colors = {
                success: 'linear-gradient(to right, #198754, #157347)',
                danger: 'linear-gradient(to right, #dc3545, #b02a37)',
                warning: 'linear-gradient(to right, #ffc107, #e0a800)',
                info: 'linear-gradient(to right, #0dcaf0, #0aa2c0)'
            };
            Toastify({
                text: text,
                duration: 3500,
                gravity: 'top',
                position: 'right',
                style: { background: colors[type] || colors.info },
                stopOnFocus: true
            }).showToast();
        }
    }

    // Modal de Inspeção de Payload
    async function openPayloadModal(id) {
        if (!modal) return;
        document.getElementById('m-wh-id').textContent = id;
        document.getElementById('m-wh-subtitle').textContent = 'Carregando detalhes...';
        document.getElementById('m-wh-loader').classList.remove('d-none');
        document.getElementById('m-wh-content').classList.add('d-none');
        modal.show();

        try {
            const resp = await fetch(`{{ url('/admin/webhook-monitor') }}/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const res = await resp.json();

            if (!res.success) {
                notify(res.message || 'Erro ao carregar dados do webhook.', 'danger');
                modal.hide();
                return;
            }

            const data = res.data;
            document.getElementById('m-wh-subtitle').textContent = `${data.created_at_fmt} (${data.relative_time})`;
            document.getElementById('m-wh-gateway').textContent = data.gateway;
            document.getElementById('m-wh-event').textContent = data.event;
            document.getElementById('m-wh-ip').textContent = data.ip;

            // Status Badge
            const statusEl = document.getElementById('m-wh-status');
            if (data.processed && !data.error) {
                statusEl.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle">Processado</span>';
            } else if (data.error) {
                statusEl.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Erro</span>';
            } else {
                statusEl.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pendente</span>';
            }

            // Bloco de Erro
            const errBox = document.getElementById('m-wh-error-box');
            const errText = document.getElementById('m-wh-error-text');
            if (data.error) {
                errBox.classList.remove('d-none');
                errText.textContent = data.error;
            } else {
                errBox.classList.add('d-none');
                errText.textContent = '';
            }

            // Payload Code
            currentRawPayload = data.payload_raw;
            let formattedJson = currentRawPayload;
            try {
                if (typeof data.payload === 'object' && data.payload !== null) {
                    formattedJson = JSON.stringify(data.payload, null, 2);
                } else if (typeof currentRawPayload === 'string') {
                    formattedJson = JSON.stringify(JSON.parse(currentRawPayload), null, 2);
                }
            } catch (e) {
                formattedJson = currentRawPayload;
            }

            document.getElementById('m-wh-code').textContent = formattedJson;

            document.getElementById('m-wh-loader').classList.add('d-none');
            document.getElementById('m-wh-content').classList.remove('d-none');
        } catch (err) {
            console.error('Erro ao abrir payload:', err);
            notify('Falha ao comunicar com o servidor.', 'danger');
            modal.hide();
        }
    }

    // Copiar Payload JSON com feedback
    document.getElementById('btn-copy-payload')?.addEventListener('click', function () {
        if (!currentRawPayload) return;
        const btnText = document.getElementById('btn-copy-text');
        navigator.clipboard.writeText(currentRawPayload).then(() => {
            if (btnText) btnText.textContent = 'Copiado!';
            notify('Payload JSON copiado com sucesso!', 'success');
            setTimeout(() => {
                if (btnText) btnText.textContent = 'Copiar JSON';
            }, 2000);
        }).catch(() => {
            notify('Não foi possível copiar o texto.', 'warning');
        });
    });

    // Eventos de clique para abrir modal de payload ou ver erro com SweetAlert2
    document.addEventListener('click', function (e) {
        const btnPayload = e.target.closest('.js-view-payload');
        if (btnPayload) {
            const id = btnPayload.getAttribute('data-id');
            if (id) openPayloadModal(id);
            return;
        }

        const btnError = e.target.closest('.js-view-error');
        if (btnError) {
            const err = btnError.getAttribute('data-error');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Falha no Webhook',
                    text: err,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
            } else {
                notify(err, 'danger');
            }
        }
    });

    // Atualização manual
    const refreshManual = () => {
        const icon = document.getElementById('manual-refresh-icon');
        if (icon) icon.classList.add('spin-animation');
        window.location.reload();
    };

    document.getElementById('btn-refresh-manual')?.addEventListener('click', refreshManual);
    document.getElementById('btn-empty-refresh')?.addEventListener('click', refreshManual);

    // Toggle Auto-refresh
    const toggleAutoRefresh = () => {
        autoRefreshActive = !autoRefreshActive;
        const icon = document.getElementById('auto-refresh-icon');
        const label = document.getElementById('auto-refresh-label');

        if (autoRefreshActive) {
            if (icon) icon.className = 'bi bi-clock-history me-1 text-success';
            if (label) label.textContent = 'Auto-refresh (15s)';
            notify('Auto-refresh ativado (15s)', 'info');
            startAutoRefresh();
        } else {
            if (icon) icon.className = 'bi bi-pause-circle me-1 text-warning';
            if (label) label.textContent = 'Auto-refresh Pausado';
            notify('Auto-refresh pausado', 'warning');
            clearInterval(refreshInterval);
        }
    };

    document.getElementById('btn-toggle-auto')?.addEventListener('click', toggleAutoRefresh);

    function startAutoRefresh() {
        clearInterval(refreshInterval);
        refreshInterval = setInterval(() => {
            // Se o usuário estiver com o modal aberto ou interagindo, não recarrega para não interromper
            if (modalEl && modalEl.classList.contains('show')) return;
            window.location.reload();
        }, 15000);
    }

    startAutoRefresh();
});
</script>
@endpush
