@extends('admin.layouts.app')

@section('title', 'Histórico de mensagens de cobrança')

@push('head')
<style>
/* ==========================================================================
   PAGE THEME & LAYOUT VARIABLES
   ========================================================================== */
.billing-history-page {
    --bh-surface: var(--bs-body-bg);
    --bh-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --bh-border: var(--bs-border-color);
    --bh-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
    --bh-wa-green: #25D366;
    --bh-wa-dark-green: #005c4b;
    --bh-wa-light-green: #d9fdd3;
    --bh-mail-blue: #0ea5e9;
}

/* KPI Cards com micro-interações */
.bh-kpi-card {
    border: 1px solid var(--bh-border);
    border-radius: 18px;
    background: var(--bh-surface);
    padding: 1.25rem;
    position: relative;
    overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
}
.bh-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
    border-color: color-mix(in srgb, var(--bs-primary) 40%, var(--bh-border));
}
.bh-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.85;
}
.bh-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    flex-shrink: 0;
}

/* Quick Filter Pills */
.bh-filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
}
.bh-pill-btn {
    border: 1px solid var(--bh-border);
    border-radius: 50rem;
    background: var(--bh-surface);
    color: var(--bs-secondary-color);
    padding: .45rem 1rem;
    font-size: .84rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    text-decoration: none;
    transition: all .18s ease;
}
.bh-pill-btn:hover {
    color: var(--bs-body-color);
    border-color: var(--bs-primary);
    background: var(--bh-surface-soft);
}
.bh-pill-btn.active {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff !important;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(var(--bs-primary-rgb), 0.35);
}
.bh-pill-btn .badge {
    font-size: .72rem;
    font-weight: 600;
    border-radius: 50rem;
    padding: .25em .6em;
}

/* Tabela de Histórico */
.bh-table-wrapper {
    background: var(--bh-surface);
    border: 1px solid var(--bh-border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}
.bh-table {
    margin-bottom: 0;
}
.bh-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .9rem 1rem;
    border-bottom: 1px solid var(--bh-border);
    white-space: nowrap;
}
.bh-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--bh-border-soft);
    font-size: .88rem;
}
.bh-table tbody tr:last-child td {
    border-bottom: none;
}
.bh-table tbody tr:hover td {
    background: var(--bh-surface-soft);
}

/* Avatar de Cliente */
.bh-client-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 85%, #000), color-mix(in srgb, var(--bs-primary) 40%, #000));
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    font-weight: 700;
    flex-shrink: 0;
}

/* Mensagem Snippet & Copy */
.bh-message-snippet {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.45;
    font-size: .84rem;
    color: var(--bs-body-color);
}
.bh-copy-btn {
    opacity: 0.6;
    transition: opacity .15s, transform .15s;
    cursor: pointer;
}
.bh-copy-btn:hover {
    opacity: 1;
    transform: scale(1.12);
}

/* Badges de Canal */
.bh-badge-channel {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .75rem;
    font-weight: 600;
    line-height: 1;
}
.bh-badge-channel--whatsapp {
    background: rgba(37, 211, 102, 0.12);
    color: #128C7E;
    border: 1px solid rgba(37, 211, 102, 0.3);
}
[data-bs-theme="dark"] .bh-badge-channel--whatsapp {
    background: rgba(37, 211, 102, 0.18);
    color: #25D366;
    border-color: rgba(37, 211, 102, 0.35);
}
.bh-badge-channel--email {
    background: rgba(14, 165, 233, 0.12);
    color: #0284c7;
    border: 1px solid rgba(14, 165, 233, 0.3);
}
[data-bs-theme="dark"] .bh-badge-channel--email {
    background: rgba(14, 165, 233, 0.18);
    color: #38bdf8;
    border-color: rgba(14, 165, 233, 0.35);
}

/* Badges de Status de Envio */
.bh-status-pill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .3rem .65rem;
    border-radius: 50rem;
    font-size: .75rem;
    font-weight: 600;
    white-space: nowrap;
}
.bh-status-pill--sent {
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.25);
}
.bh-status-pill--delivered {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.bh-status-pill--read {
    background: rgba(37, 211, 102, 0.15);
    color: #059669;
    border: 1px solid rgba(37, 211, 102, 0.35);
}
[data-bs-theme="dark"] .bh-status-pill--read {
    color: #25D366;
}
.bh-status-pill--queued {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.25);
}
.bh-status-pill--failed {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

/* ==========================================================================
   SMARTPHONE WHATSAPP ULTRA-REALISTA (MODAL PREVIEW)
   ========================================================================== */
.wa-modal-phone {
    max-width: 380px;
    width: 100%;
    margin: 0 auto;
    background: #181d22;
    border-radius: 46px;
    padding: 10px;
    box-shadow: 
        0 25px 60px -15px rgba(0, 0, 0, 0.85),
        0 0 0 1px rgba(255, 255, 255, 0.08),
        inset 0 0 0 2px #0a0e12;
    position: relative;
}
.wa-modal-screen {
    background: #0b141a;
    border-radius: 38px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    height: 580px;
}
.wa-phone-notch {
    position: absolute;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    width: 90px;
    height: 18px;
    background: #000;
    border-radius: 20px;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 8px;
}
.wa-notch-cam {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #0a1128;
    border: 1px solid #14213d;
}
.wa-phone-status-bar {
    height: 32px;
    padding: 6px 16px 0 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #e9edef;
    font-size: 0.72rem;
    font-weight: 600;
    z-index: 10;
    background: #1f2c34;
}
.wa-chat-header {
    background: #1f2c34;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid rgba(134, 150, 160, 0.15);
    z-index: 10;
}
.wa-chat-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background-color: #0b141a;
    background-image: radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
    background-size: 16px 16px;
}
.wa-bubble-sent {
    align-self: flex-end;
    background: #005c4b;
    color: #e9edef;
    border-radius: 10px 0 10px 10px;
    padding: 8px 12px 6px 12px;
    max-width: 88%;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    font-size: 0.86rem;
    line-height: 1.45;
    word-break: break-word;
    white-space: pre-wrap;
}
.wa-bubble-sent::before {
    content: '';
    position: absolute;
    top: 0;
    right: -8px;
    width: 0;
    height: 0;
    border-top: 10px solid #005c4b;
    border-right: 8px solid transparent;
}
.wa-bubble-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    font-size: 0.66rem;
    color: #8696a0;
    margin-top: 4px;
}
.wa-double-check {
    color: #53bdeb;
    font-size: 0.9rem;
    line-height: 1;
}

/* ==========================================================================
   WEBMAIL CLIENT PREVIEW (MODAL E-MAIL)
   ========================================================================== */
.mail-preview-window {
    background: var(--bh-surface);
    border: 1px solid var(--bh-border);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.08);
}
.mail-header-bar {
    background: var(--bs-tertiary-bg);
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--bh-border);
}
.mail-body-content {
    padding: 1.5rem;
    max-height: 480px;
    overflow-y: auto;
    background: var(--bh-surface);
    color: var(--bs-body-color);
    line-height: 1.6;
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-history-page py-3">

    {{-- CABEÇALHO DA PÁGINA --}}
    <x-ui.page-header
        title="Histórico de mensagens de cobrança"
        subtitle="Auditoria operacional e monitoramento em tempo real dos disparos realizados via WhatsApp e E-mail nos últimos {{ $retentionDays }} dias."
        eyebrow="Auditoria e Faturamento">
        <x-slot:actions>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('admin.billing.dispatches.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
                    <i class="bi bi-send me-1"></i> Envios agendados
                </a>
                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#retentionModal">
                    <i class="bi bi-shield-check me-1"></i> Governança & Retenção
                </button>
                <button type="button" class="btn btn-primary d-inline-flex align-items-center" id="btnRefreshHistory">
                    <i class="bi bi-arrow-clockwise me-1" id="iconRefresh"></i> Atualizar
                </button>
            </div>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Retenção</span>
                <span class="dashboard-note__value">{{ $retentionDays }} dias</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Desde</span>
                <span class="dashboard-note__value">{{ \App\Support\Format::date($cutoff) }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Taxa Sucesso</span>
                <span class="dashboard-note__value text-success font-monospace">{{ $stats['success_rate'] }}%</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    {{-- 4 KPIS MODERNOS --}}
    <div class="row g-3 mb-4">
        {{-- Total --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bh-kpi-card text-primary">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">Total Disparado</span>
                    <div class="bh-kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-send-check"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                        100% registros
                    </span>
                </div>
                <div class="text-muted small">Preservados para conferência operacional.</div>
            </div>
        </div>

        {{-- WhatsApp --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bh-kpi-card text-success">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">WhatsApp (Evolution)</span>
                    <div class="bh-kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['whatsapp'] ?? 0, 0, ',', '.') }}</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                        {{ $stats['total'] > 0 ? round(($stats['whatsapp'] / $stats['total']) * 100, 1) : 0 }}% do volume
                    </span>
                </div>
                <div class="text-muted small">Mensagens automáticas via Evolution API.</div>
            </div>
        </div>

        {{-- E-mail --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bh-kpi-card text-info">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">E-mail (SMTP)</span>
                    <div class="bh-kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-envelope-at"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['email'] ?? 0, 0, ',', '.') }}</span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                        {{ $stats['total'] > 0 ? round(($stats['email'] / $stats['total']) * 100, 1) : 0 }}% do volume
                    </span>
                </div>
                <div class="text-muted small">Mensagens enviadas por servidor SMTP.</div>
            </div>
        </div>

        {{-- Falhas --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bh-kpi-card {{ ($stats['failed'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">Falhas de Envio</span>
                    <div class="bh-kpi-icon {{ ($stats['failed'] ?? 0) > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                        <i class="bi {{ ($stats['failed'] ?? 0) > 0 ? 'bi-exclamation-triangle' : 'bi-check2-circle' }}"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['failed'] ?? 0, 0, ',', '.') }}</span>
                    @if(($stats['failed'] ?? 0) > 0)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                            Requer atenção
                        </span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                            100% Sucesso
                        </span>
                    @endif
                </div>
                <div class="text-muted small">
                    {{ ($stats['failed'] ?? 0) > 0 ? 'Disparos que retornaram erro de entrega.' : 'Nenhum erro registrado no período.' }}
                </div>
            </div>
        </div>
    </div>

    {{-- BARRA DE FILTROS E ABAS RÁPIDAS --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            
            {{-- Abas Rápidas (Pills) --}}
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="bh-filter-pills">
                    @php
                        $curChannel = request('channel');
                        $curStatus  = request('status');
                        $isFailedTab = $curStatus === 'failed';
                    @endphp
                    <a href="{{ route('admin.billing.message-history.index', array_merge(request()->except(['channel', 'status', 'page']))) }}"
                       class="bh-pill-btn {{ empty($curChannel) && !$isFailedTab ? 'active' : '' }}">
                        <i class="bi bi-grid-fill"></i>
                        <span>Todas as mensagens</span>
                        <span class="badge {{ empty($curChannel) && !$isFailedTab ? 'bg-white text-dark' : 'bg-secondary' }}">
                            {{ $stats['total'] }}
                        </span>
                    </a>

                    <a href="{{ route('admin.billing.message-history.index', array_merge(request()->except(['page']), ['channel' => 'whatsapp', 'status' => ''])) }}"
                       class="bh-pill-btn {{ $curChannel === 'whatsapp' && !$isFailedTab ? 'active' : '' }}">
                        <i class="bi bi-whatsapp text-success"></i>
                        <span>WhatsApp</span>
                        <span class="badge {{ $curChannel === 'whatsapp' && !$isFailedTab ? 'bg-white text-dark' : 'bg-success-subtle text-success' }}">
                            {{ $stats['whatsapp'] }}
                        </span>
                    </a>

                    <a href="{{ route('admin.billing.message-history.index', array_merge(request()->except(['page']), ['channel' => 'email', 'status' => ''])) }}"
                       class="bh-pill-btn {{ $curChannel === 'email' && !$isFailedTab ? 'active' : '' }}">
                        <i class="bi bi-envelope-fill text-info"></i>
                        <span>E-mail</span>
                        <span class="badge {{ $curChannel === 'email' && !$isFailedTab ? 'bg-white text-dark' : 'bg-info-subtle text-info' }}">
                            {{ $stats['email'] }}
                        </span>
                    </a>

                    <a href="{{ route('admin.billing.message-history.index', array_merge(request()->except(['page']), ['status' => 'failed'])) }}"
                       class="bh-pill-btn {{ $isFailedTab ? 'active' : '' }}">
                        <i class="bi bi-exclamation-octagon text-danger"></i>
                        <span>Falhas</span>
                        <span class="badge {{ $isFailedTab ? 'bg-white text-dark' : 'bg-danger-subtle text-danger' }}">
                            {{ $stats['failed'] }}
                        </span>
                    </a>
                </div>

                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Mostrando <strong>{{ $messages->firstItem() ?? 0 }}-{{ $messages->lastItem() ?? 0 }}</strong> de <strong>{{ $messages->total() }}</strong> mensagens
                </div>
            </div>

            {{-- Formulário de Filtros Avançados --}}
            <form method="GET" action="{{ route('admin.billing.message-history.index') }}" class="row g-2 align-items-end" id="filterForm">
                @if(request('channel'))
                    <input type="hidden" name="channel" value="{{ request('channel') }}">
                @endif

                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" name="q" class="form-control border-start-0" placeholder="Cliente, fatura, número, ID..." value="{{ request('q') }}">
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        @foreach([
                            'sent'      => 'Enviado',
                            'delivered' => 'Entregue',
                            'read'      => 'Lido / Aberto',
                            'queued'    => 'Na fila',
                            'failed'    => 'Falhou',
                            'bounced'   => 'Rejeitado'
                        ] as $val => $lbl)
                            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Data inicial</label>
                    <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Data final</label>
                    <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                </div>

                <div class="col-12 col-lg-3 d-flex gap-2 justify-content-end">
                    @if(request()->hasAny(['q', 'channel', 'status', 'from', 'to']))
                        <a href="{{ route('admin.billing.message-history.index') }}" class="btn btn-outline-secondary w-50" title="Limpar todos os filtros">
                            <i class="bi bi-x-lg me-1"></i> Limpar
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary {{ request()->hasAny(['q', 'channel', 'status', 'from', 'to']) ? 'w-50' : 'w-100' }}">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- TABELA DE HISTÓRICO COMPLETA (LARGURA TOTAL) --}}
    <div class="bh-table-wrapper mb-4">
        <div class="table-responsive">
            <table class="table bh-table align-middle">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Destinatário / Cliente</th>
                        <th style="min-width: 170px;">Fatura Vinculada</th>
                        <th style="min-width: 140px;">Canal / Rota</th>
                        <th style="min-width: 280px;">Mensagem Disparada</th>
                        <th style="min-width: 120px;">Status</th>
                        <th style="min-width: 140px;">Data de Envio</th>
                        <th style="min-width: 130px;" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $item)
                        @php
                            $rawBody = (string) $item->body;
                            $cleanBody = html_entity_decode(strip_tags($rawBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $cleanBody = trim(preg_replace('/[ \t]+/', ' ', $cleanBody) ?: '');
                            $preview = \Illuminate\Support\Str::limit($cleanBody, 150);
                            
                            $isWhatsApp = $item->channel === 'whatsapp';
                            $channelLabel = $isWhatsApp ? 'WhatsApp' : 'E-mail';
                            $channelIcon = $isWhatsApp ? 'bi-whatsapp' : 'bi-envelope';
                            
                            $statusMap = [
                                'sent'      => ['label' => 'Enviado', 'icon' => 'bi-check', 'class' => 'bh-status-pill--sent'],
                                'delivered' => ['label' => 'Entregue', 'icon' => 'bi-check-all', 'class' => 'bh-status-pill--delivered'],
                                'read'      => ['label' => 'Lido', 'icon' => 'bi-check-all', 'class' => 'bh-status-pill--read'],
                                'opened'    => ['label' => 'Aberto', 'icon' => 'bi-eye', 'class' => 'bh-status-pill--read'],
                                'clicked'   => ['label' => 'Clicado', 'icon' => 'bi-cursor', 'class' => 'bh-status-pill--read'],
                                'queued'    => ['label' => 'Na fila', 'icon' => 'bi-hourglass-split', 'class' => 'bh-status-pill--queued'],
                                'failed'    => ['label' => 'Falhou', 'icon' => 'bi-exclamation-circle', 'class' => 'bh-status-pill--failed'],
                                'bounced'   => ['label' => 'Rejeitado', 'icon' => 'bi-arrow-return-left', 'class' => 'bh-status-pill--failed'],
                            ];
                            $st = $statusMap[$item->status] ?? ['label' => ucfirst((string) $item->status), 'icon' => 'bi-info-circle', 'class' => 'bg-secondary-subtle text-secondary'];
                            
                            $invoiceStatusMap = [
                                'paid'     => ['label' => 'Paga', 'class' => 'bg-success-subtle text-success border border-success-subtle'],
                                'pending'  => ['label' => 'Pendente', 'class' => 'bg-warning-subtle text-warning border border-warning-subtle'],
                                'overdue'  => ['label' => 'Vencida', 'class' => 'bg-danger-subtle text-danger border border-danger-subtle'],
                                'canceled' => ['label' => 'Cancelada', 'class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
                            ];
                            $invSt = $invoiceStatusMap[$item->invoice_status ?? ''] ?? null;
                            
                            $initial = mb_strtoupper(mb_substr($item->customer_name ?? 'C', 0, 1));
                        @endphp
                        <tr>
                            {{-- 1. Destinatário / Cliente --}}
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bh-client-avatar">
                                        {{ $initial }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-body">
                                            {{ $item->customer_name }}
                                        </div>
                                        <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                                            @if($isWhatsApp)
                                                <i class="bi bi-whatsapp text-success"></i>
                                                <span>{{ \App\Support\Format::phone($item->recipient ?: $item->customer_whatsapp) }}</span>
                                            @else
                                                <i class="bi bi-envelope text-info"></i>
                                                <span>{{ $item->recipient ?: $item->customer_email }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- 2. Fatura --}}
                            <td>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <a href="{{ route('admin.billing.invoices.show', $item->invoice_id) }}" class="fw-bold font-monospace text-decoration-none">
                                        {{ $item->invoice_number }}
                                    </a>
                                    @if($invSt)
                                        <span class="badge {{ $invSt['class'] }} rounded-pill" style="font-size: .68rem;">
                                            {{ $invSt['label'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="fw-semibold text-body small">
                                    {{ \App\Support\Format::money($item->invoice_amount) }}
                                </div>
                                <div class="text-muted small" style="font-size: .75rem;">
                                    Venc. {{ \App\Support\Format::date($item->invoice_due_date) }}
                                </div>
                            </td>

                            {{-- 3. Canal & Rota --}}
                            <td>
                                <div class="mb-1">
                                    <span class="bh-badge-channel {{ $isWhatsApp ? 'bh-badge-channel--whatsapp' : 'bh-badge-channel--email' }}">
                                        <i class="bi {{ $channelIcon }}"></i>
                                        <span>{{ $channelLabel }}</span>
                                    </span>
                                </div>
                                @if($item->instance_name)
                                    <div class="small text-muted text-truncate" style="max-width: 140px;" title="Instância: {{ $item->instance_name }}">
                                        <i class="bi bi-phone me-1"></i>{{ $item->instance_name }}
                                    </div>
                                @endif
                                <div class="text-muted" style="font-size: .72rem;">
                                    Offset: <span class="badge bg-body-secondary text-body border">{{ $item->schedule_offset ?: 'manual' }}</span>
                                </div>
                            </td>

                            {{-- 4. Mensagem & Preview --}}
                            <td>
                                @if($item->subject)
                                    <div class="fw-bold small text-body mb-1 text-truncate" style="max-width: 320px;" title="{{ $item->subject }}">
                                        <i class="bi bi-envelope-open me-1 text-primary"></i>{{ $item->subject }}
                                    </div>
                                @endif
                                <div class="bh-message-snippet text-muted">
                                    {{ $preview ?: 'Conteúdo não textual ou vazio.' }}
                                </div>
                                
                                @if($item->last_error)
                                    <div class="mt-1 d-inline-flex align-items-center gap-1 text-danger small p-1 px-2 rounded bg-danger-subtle border border-danger-subtle" style="font-size: .72rem;">
                                        <i class="bi bi-exclamation-circle-fill"></i>
                                        <span class="text-truncate" style="max-width: 260px;" title="{{ $item->last_error }}">{{ $item->last_error }}</span>
                                    </div>
                                @endif

                                @if($item->provider_id)
                                    <div class="mt-1 text-muted small font-monospace d-flex align-items-center gap-1" style="font-size: .70rem;">
                                        <span>ID: {{ $item->provider_id }}</span>
                                        <i class="bi bi-copy bh-copy-btn" title="Copiar ID" onclick="copyToClipboard('{{ $item->provider_id }}', 'ID copiado!')"></i>
                                    </div>
                                @endif
                            </td>

                            {{-- 5. Status --}}
                            <td>
                                <div class="mb-1">
                                    <span class="bh-status-pill {{ $st['class'] }}">
                                        <i class="bi {{ $st['icon'] }}"></i>
                                        <span>{{ $st['label'] }}</span>
                                    </span>
                                </div>
                                @if($item->read_at)
                                    <div class="text-muted small" style="font-size: .72rem;" title="Lido/Aberto em {{ \App\Support\Format::datetime($item->read_at) }}">
                                        <i class="bi bi-eye text-primary me-1"></i>{{ \App\Support\Format::time($item->read_at) }}
                                    </div>
                                @elseif($item->delivered_at)
                                    <div class="text-muted small" style="font-size: .72rem;" title="Entregue em {{ \App\Support\Format::datetime($item->delivered_at) }}">
                                        <i class="bi bi-check2 me-1"></i>{{ \App\Support\Format::time($item->delivered_at) }}
                                    </div>
                                @endif
                            </td>

                            {{-- 6. Data de Envio --}}
                            <td>
                                <div class="fw-semibold text-body small">
                                    {{ \App\Support\Format::datetime($item->sent_at ?: $item->created_at) }}
                                </div>
                                <div class="text-muted small" style="font-size: .72rem;">
                                    {{ \Carbon\Carbon::parse($item->sent_at ?: $item->created_at)->diffForHumans() }}
                                </div>
                            </td>

                            {{-- 7. Ações Rápidas --}}
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    {{-- Botão de Visualização Autêntica (Modal Smartphone / Webmail) --}}
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 btn-open-preview"
                                            data-channel="{{ $item->channel }}"
                                            data-customer="{{ $item->customer_name }}"
                                            data-phone="{{ $item->customer_whatsapp ?: $item->recipient }}"
                                            data-recipient="{{ $item->recipient }}"
                                            data-subject="{{ $item->subject }}"
                                            data-body="{{ base64_encode((string) $item->body) }}"
                                            data-status="{{ $item->status }}"
                                            data-date="{{ \App\Support\Format::datetime($item->sent_at ?: $item->created_at) }}"
                                            data-time="{{ \App\Support\Format::time($item->sent_at ?: $item->created_at) }}"
                                            data-instance="{{ $item->instance_name ?: 'Evolution API' }}"
                                            title="Visualizar mensagem como o cliente recebeu">
                                        <i class="bi {{ $isWhatsApp ? 'bi-phone' : 'bi-eye' }} me-1"></i> Ver
                                    </button>

                                    {{-- Botão de Copiar Texto --}}
                                    <button type="button" 
                                            class="btn btn-sm btn-light border rounded-pill p-1 px-2"
                                            onclick="copyMessageText(this)"
                                            data-text="{{ base64_encode($cleanBody) }}"
                                            title="Copiar texto da mensagem">
                                        <i class="bi bi-clipboard"></i>
                                    </button>

                                    {{-- Botão de Diagnóstico / JSON --}}
                                    <button type="button"
                                            class="btn btn-sm btn-light border rounded-pill p-1 px-2 btn-open-diag"
                                            data-source-id="{{ $item->source_id }}"
                                            data-channel="{{ $item->channel }}"
                                            data-provider-id="{{ $item->provider_id }}"
                                            data-dispatch-id="{{ $item->dispatch_id }}"
                                            data-instance="{{ $item->instance_name }}"
                                            data-error="{{ $item->last_error }}"
                                            data-response="{{ base64_encode(is_string($item->response) ? $item->response : json_encode($item->response)) }}"
                                            data-meta="{{ base64_encode(is_string($item->meta) ? $item->meta : json_encode($item->meta)) }}"
                                            title="Auditoria técnica e payload">
                                        <i class="bi bi-code-slash text-muted"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center">
                                <div class="py-4">
                                    <div class="display-6 text-muted mb-3">
                                        <i class="bi bi-chat-square-dots"></i>
                                    </div>
                                    <h5 class="fw-bold text-body">Nenhuma mensagem de cobrança encontrada</h5>
                                    <p class="text-muted small mb-3">
                                        Quando uma cobrança for enviada por WhatsApp ou e-mail, ela aparecerá aqui durante o período de retenção de {{ $retentionDays }} dias.
                                    </p>
                                    @if(request()->hasAny(['q', 'channel', 'status', 'from', 'to']))
                                        <a href="{{ route('admin.billing.message-history.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar filtros ativos
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        @if($messages->hasPages())
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Página {{ $messages->currentPage() }} de {{ $messages->lastPage() }}
                </div>
                <div>
                    {{ $messages->links() }}
                </div>
            </div>
        @endif
    </div>

</div>

{{-- ==========================================================================
     MODAL 1: PREVIEW AUTÊNTICO (SMARTPHONE WHATSAPP OU LEITOR DE E-MAIL)
     ========================================================================== --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-body-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <span id="previewModalIcon" class="fs-5"></span>
                    <div>
                        <h6 class="modal-title fw-bold text-body mb-0" id="previewModalLabel">Visualização da Mensagem</h6>
                        <small class="text-muted" id="previewModalSubtitle">Simulação exata do que foi entregue</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="btnCopyModalBody">
                        <i class="bi bi-clipboard me-1"></i> Copiar Mensagem
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4 bg-body-secondary d-flex justify-content-center">

                {{-- Preview WhatsApp (Smartphone Ultra-Realista) --}}
                <div id="waPreviewContainer" class="w-100" style="display: none;">
                    <div class="wa-modal-phone">
                        <div class="wa-phone-notch">
                            <div class="wa-notch-cam"></div>
                            <div style="width: 5px; height: 5px; border-radius: 50%; background: #111;"></div>
                        </div>

                        <div class="wa-modal-screen">
                            {{-- Barra de status --}}
                            <div class="wa-phone-status-bar">
                                <span id="waModalTimeTop">12:00</span>
                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-wifi" style="font-size: .8rem;"></i>
                                    <i class="bi bi-reception-4" style="font-size: .8rem;"></i>
                                    <i class="bi bi-battery-full" style="font-size: .9rem;"></i>
                                </div>
                            </div>

                            {{-- Header do Chat WhatsApp --}}
                            <div class="wa-chat-header">
                                <i class="bi bi-arrow-left text-muted me-1"></i>
                                <div style="width: 34px; height: 34px; border-radius: 50%; background: #00a884; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: .9rem;" id="waModalAvatar">
                                    C
                                </div>
                                <div class="flex-grow-1 min-w-0" style="line-height: 1.2;">
                                    <div class="text-white fw-bold small text-truncate" id="waModalContactName">Cliente</div>
                                    <div style="color: #00a884; font-size: .70rem;">online • via <span id="waModalInstanceName">Evolution API</span></div>
                                </div>
                                <div class="d-flex align-items-center gap-3 text-muted">
                                    <i class="bi bi-camera-video"></i>
                                    <i class="bi bi-telephone"></i>
                                    <i class="bi bi-three-dots-vertical"></i>
                                </div>
                            </div>

                            {{-- Corpo da Conversa --}}
                            <div class="wa-chat-body" id="waChatBody">
                                <div class="align-self-center px-3 py-1 rounded-3 text-muted small" style="background: #182229; font-size: .68rem;">
                                    MENSAGEM DE COBRANÇA
                                </div>

                                {{-- Balão de Mensagem Enviada --}}
                                <div class="wa-bubble-sent">
                                    <div id="waModalMessageBody"></div>
                                    <div class="wa-bubble-meta">
                                        <span id="waModalMessageTime">12:00</span>
                                        <span class="wa-double-check" id="waModalStatusCheck">✓✓</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Preview E-mail (Webmail Moderno) --}}
                <div id="mailPreviewContainer" class="w-100" style="display: none;">
                    <div class="mail-preview-window">
                        <div class="mail-header-bar">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                    <i class="bi bi-envelope me-1"></i> Disparo de E-mail
                                </span>
                                <span class="text-muted small" id="mailModalDate">19/10/2026 12:00</span>
                            </div>
                            <h5 class="fw-bold text-body mb-2" id="mailModalSubject">Sem assunto</h5>
                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                <div><strong>Para:</strong> <span id="mailModalRecipient">cliente@exemplo.com</span></div>
                                <div><strong>Cliente:</strong> <span id="mailModalCustomer">Nome do Cliente</span></div>
                            </div>
                        </div>
                        <div class="mail-body-content" id="mailModalBody">
                            {{-- Conteúdo do e-mail --}}
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer py-2 px-4 bg-body-tertiary">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

{{-- ==========================================================================
     MODAL 2: AUDITORIA TÉCNICA E DIAGNÓSTICO (JSON / HEADERS / ERROS)
     ========================================================================== --}}
<div class="modal fade" id="diagModal" tabindex="-1" aria-labelledby="diagModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-body-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-terminal-split fs-5 text-primary"></i>
                    <div>
                        <h6 class="modal-title fw-bold text-body mb-0" id="diagModalLabel">Diagnóstico e Auditoria Técnica</h6>
                        <small class="text-muted">Metadados, IDs de rastreio e resposta bruta da API</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="p-2 border rounded-3 bg-body-tertiary">
                            <span class="d-block text-muted small">Canal</span>
                            <span class="fw-bold" id="diagChannel">-</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded-3 bg-body-tertiary">
                            <span class="d-block text-muted small">ID Provedor / Mensagem</span>
                            <span class="fw-bold font-monospace small" id="diagProviderId">-</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded-3 bg-body-tertiary">
                            <span class="d-block text-muted small">ID Despacho</span>
                            <span class="fw-bold font-monospace small" id="diagDispatchId">-</span>
                        </div>
                    </div>
                </div>

                {{-- Bloco de Erro se houver --}}
                <div id="diagErrorBlock" class="mb-3" style="display: none;">
                    <div class="p-3 border border-danger-subtle bg-danger-subtle text-danger rounded-3">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Erro retornado pelo disparo:</div>
                        <div class="small font-monospace text-break" id="diagErrorText"></div>
                    </div>
                </div>

                <ul class="nav nav-pills mb-3" id="diagTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1 px-3 small rounded-pill" id="tab-response-btn" data-bs-toggle="pill" data-bs-target="#tab-response" type="button" role="tab">
                            Resposta da API (Evolution / SMTP)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-3 small rounded-pill" id="tab-meta-btn" data-bs-toggle="pill" data-bs-target="#tab-meta" type="button" role="tab">
                            Metadados / Headers
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="diagTabsContent">
                    <div class="tab-pane fade show active" id="tab-response" role="tabpanel">
                        <div class="position-relative">
                            <pre class="bg-dark text-light p-3 rounded-3 small font-monospace overflow-auto mb-0" style="max-height: 280px;" id="diagResponseJson">Nenhuma resposta registrada.</pre>
                            <button type="button" class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyDiagResponse()">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-meta" role="tabpanel">
                        <pre class="bg-dark text-light p-3 rounded-3 small font-monospace overflow-auto mb-0" style="max-height: 280px;" id="diagMetaJson">Nenhum metadado registrado.</pre>
                    </div>
                </div>

            </div>
            <div class="modal-footer py-2 px-4 bg-body-tertiary">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

{{-- ==========================================================================
     MODAL 3: GOVERNANÇA, RETENÇÃO & POLÍTICA DE ARMAZENAMENTO
     ========================================================================== --}}
<div class="modal fade" id="retentionModal" tabindex="-1" aria-labelledby="retentionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-body-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check fs-5 text-success"></i>
                    <div>
                        <h6 class="modal-title fw-bold text-body mb-0" id="retentionModalLabel">Governança & Retenção do Histórico</h6>
                        <small class="text-muted">Como a integridade e privacidade são garantidas</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex gap-3">
                        <div class="text-primary fs-4"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <div class="fw-bold text-body">Janela de Retenção de {{ $retentionDays }} Dias</div>
                            <div class="text-muted small">
                                As mensagens de WhatsApp e e-mail vinculadas às faturas são preservadas por {{ $retentionDays }} dias após o envio para conferência, auditoria de atendimento e prevenção de contestações.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="text-success fs-4"><i class="bi bi-arrow-repeat"></i></div>
                        <div>
                            <div class="fw-bold text-body">Expurgo Automático via Cron Diário</div>
                            <div class="text-muted small">
                                O comando agendado <code>billing.message_history.cleanup</code> roda diariamente às madrugadas eliminando com segurança registros mais antigos que o período de retenção, mantendo o banco de dados leve e rápido.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="text-info fs-4"><i class="bi bi-database-check"></i></div>
                        <div>
                            <div class="fw-bold text-body">Proteção Total a Faturas e Clientes</div>
                            <div class="text-muted small">
                                A limpeza nunca apaga faturas, lançamentos financeiros, clientes ou agendamentos. Apenas os textos transitórios das mensagens enviadas são reciclados.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="text-secondary fs-4"><i class="bi bi-file-earmark-lock2"></i></div>
                        <div>
                            <div class="fw-bold text-body">Conformidade e Sigilo Multi-Tenant</div>
                            <div class="text-muted small">
                                Cada tenant visualiza estritamente os seus próprios registros. Administradores globais têm visão integral com auditoria por tenant.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-4 bg-body-tertiary">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // 1. Botão de Atualizar com Efeito de Giro
    const btnRefresh = document.getElementById('btnRefreshHistory');
    const iconRefresh = document.getElementById('iconRefresh');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', function () {
            if (iconRefresh) {
                iconRefresh.classList.add('spin-animation');
            }
            btnRefresh.disabled = true;
            window.location.reload();
        });
    }

    // 2. Helper Global de Copiar para Clipboard com Toastify
    window.copyToClipboard = function (text, successMsg) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
            if (typeof Toastify === 'function') {
                Toastify({
                    text: successMsg || 'Copiado para a área de transferência!',
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    style: {
                        background: "linear-gradient(to right, #00b09b, #96c93d)",
                        borderRadius: "10px",
                        boxShadow: "0 4px 12px rgba(0,0,0,0.15)"
                    }
                }).showToast();
            }
        }).catch(function (err) {
            console.error('Falha ao copiar:', err);
        });
    };

    // Copiar mensagem da linha
    window.copyMessageText = function (btn) {
        const encoded = btn.getAttribute('data-text');
        if (!encoded) return;
        try {
            const decoded = decodeURIComponent(escape(atob(encoded)));
            window.copyToClipboard(decoded, 'Mensagem copiada!');
        } catch (e) {
            console.error(e);
        }
    };

    // 3. Modal de Visualização Autêntica (WhatsApp / Webmail)
    const previewModalEl = document.getElementById('previewModal');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
    let currentModalRawText = '';

    document.querySelectorAll('.btn-open-preview').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const channel   = this.getAttribute('data-channel');
            const customer  = this.getAttribute('data-customer');
            const phone     = this.getAttribute('data-phone');
            const recipient = this.getAttribute('data-recipient');
            const subject   = this.getAttribute('data-subject');
            const status    = this.getAttribute('data-status');
            const dateStr   = this.getAttribute('data-date');
            const timeStr   = this.getAttribute('data-time') || '12:00';
            const instance  = this.getAttribute('data-instance') || 'Evolution API';
            const bodyEnc   = this.getAttribute('data-body') || '';

            let bodyContent = '';
            try {
                bodyContent = decodeURIComponent(escape(atob(bodyEnc)));
            } catch (e) {
                bodyContent = atob(bodyEnc);
            }
            currentModalRawText = bodyContent;

            const waContainer   = document.getElementById('waPreviewContainer');
            const mailContainer = document.getElementById('mailPreviewContainer');
            const modalIcon     = document.getElementById('previewModalIcon');
            const modalTitle    = document.getElementById('previewModalLabel');
            const modalSub      = document.getElementById('previewModalSubtitle');

            if (channel === 'whatsapp') {
                modalIcon.innerHTML = '<i class="bi bi-whatsapp text-success"></i>';
                modalTitle.textContent = 'WhatsApp do Cliente';
                modalSub.textContent = 'Simulação no dispositivo de destino (' + (phone || recipient) + ')';

                waContainer.style.display = 'block';
                mailContainer.style.display = 'none';

                document.getElementById('waModalTimeTop').textContent = timeStr;
                document.getElementById('waModalMessageTime').textContent = timeStr;
                document.getElementById('waModalContactName').textContent = customer || 'Cliente';
                document.getElementById('waModalInstanceName').textContent = instance;
                document.getElementById('waModalAvatar').textContent = (customer || 'C').charAt(0).toUpperCase();

                // Status ticks
                const checkEl = document.getElementById('waModalStatusCheck');
                if (status === 'read' || status === 'opened') {
                    checkEl.textContent = '✓✓';
                    checkEl.style.color = '#53bdeb';
                } else if (status === 'delivered') {
                    checkEl.textContent = '✓✓';
                    checkEl.style.color = '#8696a0';
                } else if (status === 'failed') {
                    checkEl.textContent = '⚠️';
                    checkEl.style.color = '#ff6b6b';
                } else {
                    checkEl.textContent = '✓';
                    checkEl.style.color = '#8696a0';
                }

                // Renderiza quebras de linha e formatação básica WhatsApp
                const formatted = bodyContent
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
                    .replace(/_(.*?)_/g, '<em>$1</em>')
                    .replace(/~(.*?)~/g, '<del>$1</del>')
                    .replace(/\n/g, '<br>');

                document.getElementById('waModalMessageBody').innerHTML = formatted;

            } else {
                modalIcon.innerHTML = '<i class="bi bi-envelope-at text-info"></i>';
                modalTitle.textContent = 'Visualização de E-mail';
                modalSub.textContent = 'Corpo da mensagem e cabeçalhos enviados ao cliente';

                waContainer.style.display = 'none';
                mailContainer.style.display = 'block';

                document.getElementById('mailModalDate').textContent = dateStr;
                document.getElementById('mailModalSubject').textContent = subject || 'Sem assunto';
                document.getElementById('mailModalRecipient').textContent = recipient || '-';
                document.getElementById('mailModalCustomer').textContent = customer || '-';

                // Se tiver tags HTML, injeta com proteção, caso contrário formata com quebras
                const hasHtml = /<[a-z][\s\S]*>/i.test(bodyContent);
                if (hasHtml) {
                    document.getElementById('mailModalBody').innerHTML = bodyContent;
                } else {
                    document.getElementById('mailModalBody').innerHTML = '<div style="white-space: pre-wrap;">' + bodyContent + '</div>';
                }
            }

            if (previewModal) previewModal.show();
        });
    });

    // Copiar do modal
    const btnCopyModal = document.getElementById('btnCopyModalBody');
    if (btnCopyModal) {
        btnCopyModal.addEventListener('click', function () {
            window.copyToClipboard(currentModalRawText, 'Mensagem copiada para a área de transferência!');
        });
    }

    // 4. Modal de Diagnóstico / Auditoria Técnica
    const diagModalEl = document.getElementById('diagModal');
    const diagModal   = diagModalEl ? new bootstrap.Modal(diagModalEl) : null;
    let currentDiagResponseRaw = '';

    document.querySelectorAll('.btn-open-diag').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const channel     = this.getAttribute('data-channel');
            const providerId  = this.getAttribute('data-provider-id') || '-';
            const dispatchId  = this.getAttribute('data-dispatch-id') || '-';
            const error       = this.getAttribute('data-error');
            const responseEnc = this.getAttribute('data-response') || '';
            const metaEnc     = this.getAttribute('data-meta') || '';

            document.getElementById('diagChannel').textContent = channel === 'whatsapp' ? 'WhatsApp (Evolution API)' : 'E-mail (SMTP)';
            document.getElementById('diagProviderId').textContent = providerId;
            document.getElementById('diagDispatchId').textContent = dispatchId;

            // Bloco de Erro
            const errBlock = document.getElementById('diagErrorBlock');
            const errText  = document.getElementById('diagErrorText');
            if (error && error.trim() !== '') {
                errBlock.style.display = 'block';
                errText.textContent = error;
            } else {
                errBlock.style.display = 'none';
            }

            // Resposta JSON
            let responseStr = 'Nenhum registro bruto salvo.';
            if (responseEnc) {
                try {
                    const dec = decodeURIComponent(escape(atob(responseEnc)));
                    const parsed = JSON.parse(dec);
                    responseStr = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    try {
                        responseStr = atob(responseEnc);
                    } catch (e2) {
                        responseStr = responseEnc;
                    }
                }
            }
            currentDiagResponseRaw = responseStr;
            document.getElementById('diagResponseJson').textContent = responseStr;

            // Metadados
            let metaStr = 'Nenhum metadado registrado.';
            if (metaEnc) {
                try {
                    const decMeta = decodeURIComponent(escape(atob(metaEnc)));
                    const parsedMeta = JSON.parse(decMeta);
                    metaStr = JSON.stringify(parsedMeta, null, 2);
                } catch (e) {
                    try {
                        metaStr = atob(metaEnc);
                    } catch (e2) {
                        metaStr = metaEnc;
                    }
                }
            }
            document.getElementById('diagMetaJson').textContent = metaStr;

            if (diagModal) diagModal.show();
        });
    });

    window.copyDiagResponse = function () {
        window.copyToClipboard(currentDiagResponseRaw, 'Payload JSON copiado com sucesso!');
    };

});
</script>
<style>
.spin-animation {
    animation: bhSpin .7s linear infinite;
}
@keyframes bhSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endpush
@endsection
