@extends('tenancy::tenant.layout')

@section('title', 'Histórico WhatsApp')

@push('head')
<style>
/* ==========================================================================
   SIMULADOR DE SMARTPHONE WHATSAPP ULTRA-REALISTA (HISTÓRICO & PREVIEW)
   ========================================================================== */
.wa-phone-wrapper {
    position: relative;
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
}

/* Botões físicos nas laterais do aparelho */
.wa-phone-wrapper::before {
    content: '';
    position: absolute;
    left: -7px;
    top: 95px;
    width: 4px;
    height: 42px;
    background: #2a313a;
    border-radius: 3px 0 0 3px;
    box-shadow: 0 54px 0 #2a313a;
}
.wa-phone-wrapper::after {
    content: '';
    position: absolute;
    right: -7px;
    top: 110px;
    width: 4px;
    height: 54px;
    background: #2a313a;
    border-radius: 0 3px 3px 0;
}

/* Versão compacta para colunas laterais */
.wa-phone-wrapper--compact {
    max-width: 340px;
    border-radius: 40px;
    padding: 8px;
}
.wa-phone-wrapper--compact .wa-phone-screen {
    height: 540px;
    border-radius: 32px;
}

/* Tela interna do smartphone */
.wa-phone-screen {
    background: #0b141a;
    border-radius: 38px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    height: 620px;
    max-height: 85vh;
}

/* Dynamic Island / Notch no topo */
.wa-phone-notch {
    position: absolute;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    width: 96px;
    height: 18px;
    background: #000000;
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
.wa-notch-sensor {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #111;
}

/* Barra de Status do Sistema */
.wa-phone-status-bar {
    height: 32px;
    padding: 6px 18px 0 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #e9edef;
    font-size: 0.74rem;
    font-weight: 600;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif;
    z-index: 10;
    background: #1f2c34;
}
.wa-status-icons {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
}

/* Header do WhatsApp */
.wa-chat-header {
    background: #1f2c34;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid rgba(134, 150, 160, 0.15);
    z-index: 10;
}
.wa-header-back-btn {
    color: #aebac1;
    font-size: 1.15rem;
    cursor: pointer;
    line-height: 1;
}
.wa-header-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00a884, #005c4b);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    font-weight: bold;
    position: relative;
    flex-shrink: 0;
}
.wa-header-avatar .wa-verified-badge {
    position: absolute;
    bottom: -1px;
    right: -1px;
    background: #00a884;
    color: #fff;
    border-radius: 50%;
    font-size: 0.55rem;
    width: 14px;
    height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #1f2c34;
}
.wa-header-info {
    flex-grow: 1;
    min-width: 0;
}
.wa-header-name {
    color: #e9edef;
    font-size: 0.90rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.wa-header-status {
    color: #00a884;
    font-size: 0.70rem;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 1px;
}
.badge-online-dot {
    width: 6px;
    height: 6px;
    background-color: #00a884;
    border-radius: 50%;
    display: inline-block;
}
.wa-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #aebac1;
    font-size: 1rem;
}
.wa-header-actions i {
    cursor: pointer;
    transition: color 0.15s;
}
.wa-header-actions i:hover {
    color: #e9edef;
}
.wa-header-close-btn {
    background: transparent;
    border: none;
    color: #aebac1;
    font-size: 1.1rem;
    padding: 0;
    cursor: pointer;
    line-height: 1;
}
.wa-header-close-btn:hover {
    color: #ffffff;
}

/* Área de Conversa com Wallpaper Oficial do WhatsApp */
.wa-chat-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    background-color: #0b141a;
    background-image: radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
    background-size: 16px 16px;
}

/* Pílula de Data */
.wa-date-pill {
    align-self: center;
    background: #182229;
    color: #8696a0;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 8px;
    text-transform: uppercase;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    margin: 2px 0;
}

/* Selo de Criptografia */
.wa-encryption-pill {
    align-self: center;
    background: #182229;
    color: #ffd279;
    font-size: 0.65rem;
    line-height: 1.35;
    padding: 5px 10px;
    border-radius: 8px;
    text-align: center;
    max-width: 92%;
    margin-bottom: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Balão de Mensagem Enviada (Verde WhatsApp Oficial) */
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
/* Rabicho clássico do WhatsApp para mensagens enviadas */
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

.wa-bubble-sent p {
    margin-bottom: 0.4rem;
}
.wa-bubble-sent p:last-child {
    margin-bottom: 0;
}
.wa-bubble-sent strong {
    font-weight: 600;
    color: #ffffff;
}
.wa-bubble-sent em {
    font-style: italic;
    color: #dfe5e7;
}
.wa-bubble-sent a {
    color: #53bdeb;
    text-decoration: underline;
}
.wa-bubble-sent code {
    background: #0b141a;
    color: #25d366;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 0.80rem;
    display: block;
    margin: 4px 0;
    white-space: pre-wrap;
}

/* Meta com horário e duplo tique azul */
.wa-bubble-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    font-size: 0.66rem;
    color: #8696a0;
    margin-top: 4px;
}
.wa-bubble-meta i {
    font-size: 0.88rem;
    line-height: 1;
}

/* Anexo de Mídia (QR Code) */
.wa-media-card {
    background: #0b141a;
    border-radius: 8px;
    overflow: hidden;
    margin: 6px 0;
    border: 1px solid rgba(255,255,255,0.06);
    text-align: center;
    padding: 8px;
}
.wa-media-card img {
    max-width: 170px;
    width: 100%;
    height: auto;
    border-radius: 6px;
    display: block;
    margin: 0 auto;
    background: #fff;
    padding: 4px;
}
.wa-media-card .wa-media-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    color: #00a884;
    margin-top: 5px;
}

/* Barra Inferior do WhatsApp */
.wa-chat-footer {
    padding: 6px 8px 8px 8px;
    background: #1f2c34;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 10;
}
.wa-input-pill {
    flex-grow: 1;
    background: #2a3942;
    border-radius: 20px;
    height: 38px;
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 8px;
    color: #8696a0;
    font-size: 0.86rem;
}
.wa-input-pill i {
    font-size: 1.05rem;
    color: #8696a0;
    cursor: pointer;
}
.wa-input-pill .wa-input-text {
    flex-grow: 1;
    color: #8696a0;
    font-size: 0.82rem;
    user-select: none;
}
.wa-mic-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #00a884;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    transition: transform 0.15s, background-color 0.15s;
}
.wa-mic-btn:hover {
    background: #029474;
    transform: scale(1.05);
}

/* Barra Home do Smartphone */
.wa-phone-home-bar {
    width: 120px;
    height: 4px;
    background: rgba(255, 255, 255, 0.45);
    border-radius: 4px;
    margin: 4px auto 2px auto;
    flex-shrink: 0;
}

/* Linhas da Tabela Clicáveis com Efeito */
.wa-table-row {
    transition: background-color .15s ease;
}
.wa-message-clickable {
    cursor: pointer;
    transition: color .15s ease;
}
.wa-message-clickable:hover {
    color: var(--bs-success) !important;
}

/* Toolbars e Emojis do Envio Manual */
.wa-toolbar {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
}
.wa-toolbar__separator {
    width: 1px;
    height: 24px;
    background: var(--bs-border-color);
}
.wa-emoji-picker {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    padding: .65rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    background: var(--bs-card-bg);
}
.wa-emoji-picker button {
    border: 0;
    background: transparent;
    font-size: 1.25rem;
    line-height: 1;
    padding: .25rem;
    border-radius: 6px;
    transition: background-color .15s;
}
.wa-emoji-picker button:hover {
    background: var(--bs-tertiary-bg);
}
.wa-cust-item {
    cursor: pointer;
    transition: background-color .15s ease-in-out;
}
.wa-cust-item:hover {
    background-color: var(--bs-tertiary-bg);
}

/* Responsividade Mobile para o Smartphone */
@media (max-width: 576px) {
    .wa-phone-wrapper {
        border-radius: 32px;
        padding: 6px;
    }
    .wa-phone-screen {
        border-radius: 26px !important;
        height: 520px !important;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-1 fw-bold text-body">
                <i class="bi bi-whatsapp text-success me-2"></i>Histórico de Disparos WhatsApp
            </h4>
            <p class="text-muted mb-0">Auditoria operacional em tempo real: todas as mensagens enviadas para seus clientes com simulação autêntica no WhatsApp.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="javascript:void(0)" class="js-meta-help-trigger text-muted p-1 text-decoration-none" data-help-topic="usage-delivery-tracking" title="Instruções sobre Rastreamento e Status de Entrega">
                <i class="bi bi-question-circle fs-5"></i>
            </a>
            <button class="btn btn-success rounded-pill px-3 shadow-sm" id="btn-manual-send" data-has-instance="{{ $hasConnectedInstance ? '1' : '0' }}">
                <i class="bi bi-send-plus me-1"></i> Nova mensagem manual
            </button>
        </div>
    </div>

    @if(!$hasConnectedInstance)
        <div class="alert alert-warning d-flex align-items-center justify-content-between gap-3 mb-4 rounded-3 shadow-sm border-warning-subtle">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                <div>
                    <strong>Instância de WhatsApp desconectada:</strong> Conecte sua instância para poder enviar mensagens manuais e automáticas aos seus clientes.
                </div>
            </div>
            <a href="{{ route('tenant.instances') }}" class="btn btn-sm btn-warning text-dark text-nowrap rounded-pill px-3">
                <i class="bi bi-qr-code me-1"></i> Minhas Instâncias
            </a>
        </div>
    @endif

    {{-- Cards de Resumo e Estatísticas --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card bg-primary text-white h-100 shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</div>
                    <small class="text-white-50">Total de mensagens</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-success text-white h-100 shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ number_format($stats['sent'] ?? 0, 0, ',', '.') }}</div>
                    <small class="text-white-50">Enviadas / Entregues</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-warning text-dark h-100 shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ number_format($stats['queued'] ?? 0, 0, ',', '.') }}</div>
                    <small class="text-black-50">Aguardando na Fila</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-danger text-white h-100 shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">{{ number_format($stats['failed'] ?? 0, 0, ',', '.') }}</div>
                    <small class="text-white-50">Falhas / Erros</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela de Histórico com Filtros --}}
    <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
        <div class="card-header bg-body-tertiary border-bottom py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos os status</option>
                        <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Na fila</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Enviada</option>
                        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Entregue</option>
                        <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Lida</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Falha</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Data De</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Data Até</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Busca rápida</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cliente, telefone, fatura..." value="{{ request('search') }}">
                </div>
                <div class="col-12 mt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-search me-1"></i>Filtrar</button>
                    <a href="{{ route('tenant.history.whatsapp') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-x-circle me-1"></i>Limpar filtros</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            @if($messages->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-whatsapp display-4 d-block mb-3 text-secondary"></i>
                    <h5>Nenhuma mensagem encontrada</h5>
                    <p class="text-muted mb-3">Você ainda não enviou mensagens ou nenhum registro atende aos filtros aplicados.</p>
                    <button type="button" class="btn btn-sm btn-success rounded-pill px-4" onclick="document.getElementById('btn-manual-send').click()">
                        <i class="bi bi-send me-1"></i> Enviar Primeira Mensagem Manual
                    </button>
                </div>
            @else
                <div>
                    <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-history-whatsapp" style="width: 100% !important;" data-export-title="Histórico de WhatsApp">
                        <thead class="table-light">
                            <tr>
                                <th class="all" style="width: 70px;">#</th>
                                <th class="all">Destinatário / Cliente</th>
                                <th class="min-tablet">Telefone</th>
                                <th class="desktop">Origem / Fatura</th>
                                <th class="desktop">Mensagem (Clique para Simulação)</th>
                                <th class="min-tablet">Status</th>
                                <th class="min-tablet text-nowrap">Data / Hora</th>
                                <th class="all text-end no-export" data-priority="1" data-orderable="false" style="width: 80px; min-width: 70px; max-width: 90px; white-space: nowrap;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($messages as $msg)
                                    @php
                                        $inv = $msg->dispatch?->invoice;
                                        $customer = $inv?->customer;
                                        $custName = $customer?->name ?? ($msg->meta['customer_name'] ?? null);
                                        $statusMap = [
                                            'queued' => ['secondary', 'Na fila', 'bi-clock'],
                                            'sent' => ['info', 'Enviada', 'bi-check'],
                                            'delivered' => ['success', 'Entregue', 'bi-check2-all'],
                                            'read' => ['primary', 'Lida', 'bi-check2-all'],
                                            'failed' => ['danger', 'Falha', 'bi-exclamation-circle'],
                                        ];
                                        list($badgeColor, $badgeLabel, $badgeIcon) = $statusMap[$msg->status] ?? ['secondary', $msg->status, 'bi-clock'];

                                        $invMeta = is_array($inv?->meta) ? $inv->meta : (json_decode($inv?->meta ?? '[]', true) ?: []);
                                        $msgMeta = is_array($msg->meta) ? $msg->meta : (json_decode($msg->meta ?? '[]', true) ?: []);
                                        $qrCodeUrl = data_get($invMeta, 'gateway_payment.qrcode_url')
                                            ?: data_get($invMeta, 'qrcode_url')
                                            ?: data_get($msgMeta, 'qrcode_url')
                                            ?: data_get($msgMeta, 'media_url')
                                            ?: '';
                                        $pixCode = data_get($invMeta, 'gateway_payment.pix_copia_cola')
                                            ?: data_get($invMeta, 'pix_copia_cola')
                                            ?: data_get($invMeta, 'pix_qr_code')
                                            ?: data_get($msgMeta, 'pix_copia_cola')
                                            ?: '';
                                        if (empty($qrCodeUrl) && !empty($pixCode)) {
                                            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=' . urlencode($pixCode);
                                        }
                                    @endphp
                                    <tr class="wa-table-row">
                                        <td class="text-muted small">#{{ $msg->id }}</td>
                                        <td>
                                            @if($custName)
                                                <div class="fw-semibold text-body">{{ $custName }}</div>
                                            @else
                                                <span class="text-muted fst-italic">Envio avulso</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-nowrap font-monospace small"><i class="bi bi-telephone text-muted me-1"></i>{{ \App\Support\Format::phone($msg->to) }}</span>
                                        </td>
                                        <td>
                                            @if($inv)
                                                <a href="{{ route('tenant.invoices') }}?search={{ $inv->number }}" class="badge bg-primary-subtle text-primary border text-decoration-none" title="Ver Fatura #{{ $inv->number }}">
                                                    <i class="bi bi-file-earmark-text me-1"></i>Fatura #{{ $inv->number }}
                                                </a>
                                            @elseif(!empty($msg->meta['origin']) && str_contains($msg->meta['origin'], 'manual'))
                                                <span class="badge bg-success-subtle text-success border">
                                                    <i class="bi bi-send-check me-1"></i>Envio Manual
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border">Direto</span>
                                            @endif
                                        </td>
                                        <td class="wa-message-clickable js-open-wa-modal"
                                            data-id="{{ $msg->id }}"
                                            data-customer="{{ $custName ?? 'Cliente' }}"
                                            data-to="{{ \App\Support\Format::phone($msg->to) }}"
                                            data-raw-to="{{ $msg->to }}"
                                            data-message="{{ $msg->message }}"
                                            data-status="{{ $msg->status }}"
                                            data-time="{{ $msg->created_at->format('H:i') }}"
                                            data-date="{{ $msg->created_at->format('d/m/Y') }}"
                                            data-origin="{{ $inv ? 'Fatura #' . $inv->number : 'WhatsApp' }}"
                                            data-qr-url="{{ $qrCodeUrl }}"
                                            title="Clique para abrir simulação exata no celular">
                                            <div class="text-truncate d-flex align-items-center gap-1" style="max-width: 320px;">
                                                <i class="bi bi-phone text-success small me-1"></i>
                                                <span>{{ Str::limit($msg->message, 80) }}</span>
                                            </div>
                                            @if(!empty($qrCodeUrl))
                                                <span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 0.65rem;">
                                                    <i class="bi bi-qr-code me-1"></i>Com QR Code
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $badgeColor }} d-inline-flex align-items-center gap-1">
                                                <i class="bi {{ $badgeIcon }}"></i> {{ $badgeLabel }}
                                            </span>
                                        </td>
                                        <td class="text-muted small text-nowrap">
                                            {{ $msg->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="all text-end no-export" style="width: 80px; min-width: 70px; max-width: 90px; white-space: nowrap;">
                                            <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success p-1 px-2 js-open-wa-modal"
                                                        data-id="{{ $msg->id }}"
                                                        data-customer="{{ $custName ?? 'Cliente' }}"
                                                        data-to="{{ \App\Support\Format::phone($msg->to) }}"
                                                        data-raw-to="{{ $msg->to }}"
                                                        data-message="{{ $msg->message }}"
                                                        data-status="{{ $msg->status }}"
                                                        data-time="{{ $msg->created_at->format('H:i') }}"
                                                        data-date="{{ $msg->created_at->format('d/m/Y') }}"
                                                        data-origin="{{ $inv ? 'Fatura #' . $inv->number : 'WhatsApp' }}"
                                                        data-qr-url="{{ $qrCodeUrl }}"
                                                        title="Visualizar no WhatsApp">
                                                    <i class="bi bi-phone"></i>
                                                </button>
                                            </div>
                                        </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center py-2">
                    <span class="small text-muted">Exibindo {{ $messages->count() }} de {{ $messages->total() }} mensagens</span>
                    <div>
                        {{ $messages->withQueryString()->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ==========================================================================
     MODAL 1: SIMULADOR ULTRA-REALISTA DE SMARTPHONE WHATSAPP (HISTÓRICO)
     ========================================================================== --}}
<div class="modal fade" id="modalWaPhonePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 bg-transparent shadow-none">
            <div class="wa-phone-wrapper">
                <div class="wa-phone-screen">
                    {{-- Notch / Dynamic Island --}}
                    <div class="wa-phone-notch">
                        <div class="wa-notch-cam"></div>
                        <div class="wa-notch-sensor"></div>
                    </div>

                    {{-- Barra de Status do Sistema --}}
                    <div class="wa-phone-status-bar">
                        <span id="wa-phone-status-time">12:00</span>
                        <div class="wa-status-icons">
                            <i class="bi bi-reception-4"></i>
                            <i class="bi bi-wifi"></i>
                            <i class="bi bi-battery-full"></i>
                        </div>
                    </div>

                    {{-- Header do Chat WhatsApp --}}
                    <div class="wa-chat-header">
                        <i class="bi bi-arrow-left wa-header-back-btn" data-bs-dismiss="modal" title="Voltar"></i>
                        <div class="wa-header-avatar" id="wa-phone-avatar">
                            <span id="wa-phone-avatar-text">C</span>
                            <span class="wa-verified-badge"><i class="bi bi-check"></i></span>
                        </div>
                        <div class="wa-header-info">
                            <div class="wa-header-name" id="wa-phone-contact-name">Cliente</div>
                            <div class="wa-header-status">
                                <span class="badge-online-dot"></span> <span id="wa-phone-status-text">online</span>
                            </div>
                        </div>
                        <div class="wa-header-actions">
                            <i class="bi bi-camera-video" title="Chamada de vídeo"></i>
                            <i class="bi bi-telephone" title="Chamada de voz"></i>
                            <i class="bi bi-three-dots-vertical" title="Mais opções"></i>
                            <button type="button" class="wa-header-close-btn ms-1" data-bs-dismiss="modal" title="Fechar visualização">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Área de Conversa com Wallpaper Oficial do WhatsApp --}}
                    <div class="wa-chat-body" id="wa-phone-chat-body">
                        {{-- Pílula de Data --}}
                        <div class="wa-date-pill" id="wa-phone-date-pill">HOJE</div>

                        {{-- Selo de Criptografia de Ponta a Ponta --}}
                        <div class="wa-encryption-pill">
                            <i class="bi bi-lock-fill me-1 text-warning"></i> As mensagens são protegidas com a criptografia de ponta a ponta. Ninguém fora desta conversa pode ler ou ouvir o conteúdo.
                        </div>

                        {{-- Balão de Mensagem Enviada --}}
                        <div class="wa-bubble-sent" id="wa-phone-bubble">
                            {{-- Área de Imagem / QR Code no Topo --}}
                            <div id="wa-phone-media-container" class="d-none">
                                <div class="wa-media-card">
                                    <img id="wa-phone-media-img" src="" alt="QR Code Pix Cobrança" />
                                    <div class="wa-media-badge"><i class="bi bi-qr-code me-1"></i>QR Code Pix (Aponte a câmera)</div>
                                </div>
                            </div>

                            <div id="wa-phone-message-body"></div>
                            <div class="wa-bubble-meta">
                                <span id="wa-phone-bubble-time">12:00</span>
                                <span class="wa-status-check" id="wa-phone-status-check">
                                    <i class="bi bi-check2-all text-info"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Barra Inferior de Digitação do WhatsApp --}}
                    <div class="wa-chat-footer">
                        <div class="wa-input-pill">
                            <i class="bi bi-emoji-smile" title="Emojis"></i>
                            <span class="wa-input-text">Mensagem</span>
                            <i class="bi bi-paperclip" title="Anexar"></i>
                            <i class="bi bi-camera" title="Câmera"></i>
                        </div>
                        <div class="wa-mic-btn" title="Copiar texto da mensagem" id="wa-phone-copy-btn">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>

                    {{-- Barra Home do Smartphone --}}
                    <div class="wa-phone-home-bar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ==========================================================================
     MODAL 2: ENVIO MANUAL DE MENSAGEM WHATSAPP COM LIVE PREVIEW SMARTPHONE
     ========================================================================== --}}
<div class="modal fade" id="modal-manual-send" tabindex="-1" aria-labelledby="modalManualSendLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-body-tertiary border-bottom py-3">
                <div>
                    <h5 class="modal-title fw-bold" id="modalManualSendLabel">
                        <i class="bi bi-whatsapp text-success me-2"></i>Envio Manual de Mensagem WhatsApp
                    </h5>
                    <p class="text-muted small mb-0">Solução operacional com autopreenchimento de cliente e simulador de smartphone em tempo real.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-start">
                    <!-- Coluna Esquerda: Formulário e Editor -->
                    <div class="col-lg-7">
                        <form id="frm-manual-msg" class="d-grid gap-3" onsubmit="return false;">
                            <!-- Seleção de Cliente e Seleção de Template -->
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
                                        <span><i class="bi bi-person-circle me-1 text-primary"></i> Cliente (opcional)</span>
                                        <span id="customer-clear-btn" class="badge bg-secondary-subtle text-secondary d-none" style="cursor: pointer;" title="Desvincular cliente selecionado">
                                            <i class="bi bi-x-circle me-1"></i> Desvincular
                                        </span>
                                    </label>
                                    <div class="position-relative">
                                        <div class="input-group">
                                            <span class="input-group-text bg-body-tertiary"><i class="bi bi-search"></i></span>
                                            <input type="text" id="customer-search-input" class="form-control" placeholder="Buscar por nome, WhatsApp, CPF..." autocomplete="off">
                                            <button type="button" class="btn btn-outline-secondary d-none" id="customer-search-spinner" disabled>
                                                <span class="spinner-border spinner-border-sm"></span>
                                            </button>
                                        </div>
                                        <input type="hidden" id="selected-customer-id" value="">
                                        <!-- Dropdown flutuante de resultados da busca -->
                                        <div id="customer-search-results" class="position-absolute w-100 bg-body rounded-3 shadow-lg border p-1 mt-1 d-none" style="z-index: 1060; max-height: 240px; overflow-y: auto;"></div>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label fw-bold mb-1">
                                        <i class="bi bi-file-earmark-text me-1 text-info"></i> Template (opcional)
                                    </label>
                                    <select name="template_id" id="template-select" class="form-select">
                                        <option value="">-- Mensagem livre (sem template) --</option>
                                        @if(isset($templates))
                                            @foreach($templates as $tpl)
                                                <option value="{{ $tpl->id }}" data-slug="{{ $tpl->slug }}" data-name="{{ $tpl->name }}">
                                                    {{ $tpl->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <!-- Card de Resumo do Cliente Selecionado -->
                            <div id="customer-selected-card" class="card border border-primary-subtle bg-primary-subtle bg-opacity-10 rounded-3 p-2 d-none">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-person-check-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-primary" id="card-cust-name">-</div>
                                            <div class="small text-muted" id="card-cust-meta">-</div>
                                        </div>
                                    </div>
                                    <div id="card-cust-invoice-badge">
                                        <!-- Badge da fatura vinculada se houver -->
                                    </div>
                                </div>
                            </div>

                            <!-- Destinatário -->
                            <div>
                                <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="bi bi-telephone-fill me-1 text-success"></i> Número do Destinatário</span>
                                    <span class="badge bg-secondary-subtle text-muted fw-normal">DDI + DDD + Número</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-success bg-opacity-10 text-success"><i class="bi bi-whatsapp"></i></span>
                                    <input name="to" id="manual-to" class="form-control font-monospace" placeholder="+55 (11) 99999-8888" inputmode="tel" required>
                                </div>
                                <div class="form-text text-muted">Preenchido automaticamente ao selecionar o cliente, ou você pode digitar diretamente.</div>
                            </div>

                            <!-- Editor de Mensagem e Barra de Ferramentas -->
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fw-bold mb-0"><i class="bi bi-chat-text-fill me-1 text-warning"></i> Mensagem WhatsApp</label>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-braces me-1"></i> Inserir Variável
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                            <li><h6 class="dropdown-header">Dados do Cliente</h6></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{nome}"><i class="bi bi-person me-1"></i> {nome}</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{primeiro_nome}"><i class="bi bi-person me-1"></i> {primeiro_nome}</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header">Dados da Cobrança / Fatura</h6></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{valor}"><i class="bi bi-currency-dollar me-1"></i> {valor}</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{vencimento}"><i class="bi bi-calendar-event me-1"></i> {vencimento}</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{link_pagamento}"><i class="bi bi-link-45deg me-1"></i> {link_pagamento}</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{pix_copia_cola}"><i class="bi bi-qr-code me-1"></i> {pix_copia_cola}</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{linha_digitavel}"><i class="bi bi-upc me-1"></i> {linha_digitavel}</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{numero_fatura}"><i class="bi bi-hash me-1"></i> {numero_fatura}</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" data-insert-var="{empresa}"><i class="bi bi-building me-1"></i> {empresa}</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="wa-toolbar mb-2" id="wa-toolbar">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="bold" title="Negrito (*texto*)"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="italic" title="Itálico (_texto_)"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="strike" title="Riscado (~texto~)"><i class="bi bi-type-strikethrough"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="mono" title="Monoespaçado (```texto```)"><i class="bi bi-code"></i></button>
                                    <span class="wa-toolbar__separator"></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="list" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="bullet" title="Lista com marcadores"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="emoji" title="Emojis"><i class="bi bi-emoji-smile"></i></button>
                                </div>
                                <div id="emoji-picker" class="wa-emoji-picker d-none mb-2"></div>
                                <textarea name="message" id="wa-editor" class="form-control" rows="8" placeholder="Digite a mensagem diretamente ou selecione um template acima. O celular ao lado renderizará o WhatsApp em tempo real." style="font-family: monospace;"></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Coluna Direita: Live Preview Smartphone com WhatsApp Aberto -->
                    <div class="col-lg-5">
                        <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-2">
                            <span><i class="bi bi-phone me-1 text-success"></i> Simulador WhatsApp (Tempo Real)</span>
                            <span class="badge bg-success-subtle text-success small rounded-pill">100% Autêntico</span>
                        </label>
                        <div class="wa-phone-wrapper wa-phone-wrapper--compact">
                            <div class="wa-phone-screen">
                                {{-- Notch --}}
                                <div class="wa-phone-notch">
                                    <div class="wa-notch-cam"></div>
                                    <div class="wa-notch-sensor"></div>
                                </div>

                                {{-- Barra de Status --}}
                                <div class="wa-phone-status-bar">
                                    <span id="preview-live-time">12:00</span>
                                    <div class="wa-status-icons">
                                        <i class="bi bi-reception-4"></i>
                                        <i class="bi bi-wifi"></i>
                                        <i class="bi bi-battery-full"></i>
                                    </div>
                                </div>

                                {{-- Header do WhatsApp --}}
                                <div class="wa-chat-header">
                                    <i class="bi bi-arrow-left wa-header-back-btn"></i>
                                    <div class="wa-header-avatar" style="width: 32px; height: 32px; font-size: 0.95rem;">
                                        <span id="preview-contact-avatar">D</span>
                                        <span class="wa-verified-badge" style="width: 12px; height: 12px; font-size: 0.5rem;"><i class="bi bi-check"></i></span>
                                    </div>
                                    <div class="wa-header-info">
                                        <div class="wa-header-name" id="preview-contact-name" style="font-size: 0.84rem;">Destinatário</div>
                                        <div class="wa-header-status" style="font-size: 0.66rem;">
                                            <span class="badge-online-dot"></span> online
                                        </div>
                                    </div>
                                    <div class="wa-header-actions" style="font-size: 0.9rem; gap: 8px;">
                                        <i class="bi bi-camera-video"></i>
                                        <i class="bi bi-telephone"></i>
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </div>
                                </div>

                                {{-- Área do Chat --}}
                                <div class="wa-chat-body">
                                    <div class="wa-date-pill">HOJE</div>
                                    <div class="wa-encryption-pill">
                                        <i class="bi bi-lock-fill me-1 text-warning"></i> Criptografia de ponta a ponta ativa.
                                    </div>
                                    <div class="wa-bubble-sent">
                                        <div id="msg-preview"><em class="text-muted">A mensagem aparecerá aqui durante a digitação...</em></div>
                                        <div class="wa-bubble-meta">
                                            <span id="preview-bubble-time">12:00</span>
                                            <i class="bi bi-check2-all text-info"></i>
                                        </div>
                                    </div>
                                </div>

                                {{-- Rodapé do WhatsApp --}}
                                <div class="wa-chat-footer">
                                    <div class="wa-input-pill">
                                        <i class="bi bi-emoji-smile"></i>
                                        <span class="wa-input-text">Mensagem</span>
                                        <i class="bi bi-paperclip"></i>
                                    </div>
                                    <div class="wa-mic-btn" style="width: 34px; height: 34px;">
                                        <i class="bi bi-mic-fill" style="font-size: 0.9rem;"></i>
                                    </div>
                                </div>

                                {{-- Home Bar --}}
                                <div class="wa-phone-home-bar"></div>
                            </div>
                        </div>

                        {{-- Contador de caracteres e Anti-Ban --}}
                        <div class="d-flex justify-content-between align-items-center small text-muted mt-2 px-2">
                            <span id="msg-char-count">0 caracteres</span>
                            <span class="text-success"><i class="bi bi-shield-check me-1"></i> Proteção Anti-Ban</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary border-top py-3">
                <button type="button" class="btn btn-outline-danger me-auto rounded-pill px-3" id="btn-reset-form">
                    <i class="bi bi-trash me-1"></i> Limpar tudo
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success rounded-pill px-4" id="btn-confirm-send-manual">
                    <i class="bi bi-whatsapp me-1"></i> Enviar Mensagem
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

    // =========================================================================
    // 1. FORMATADOR AUTÊNTICO DE MENSAGEM WHATSAPP
    // =========================================================================
    function formatWhatsAppMessage(text) {
        if (!text) return '<em class="text-white-50">Mensagem vazia</em>';
        let t = text;

        // Suporte visual a QR Code Pix em anexo
        t = t.replace(/(?:📱\s*)?\*\[QR Code Pix enviado em anexo\]\*/gi, 
            '<div class="wa-media-card">' +
            '<img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=00020101021226580014br.gov.bcb.pix" alt="QR Code Pix Anexo" />' +
            '<div class="wa-media-badge"><i class="bi bi-paperclip me-1"></i>Imagem QR Code Pix (Mídia Anexa)</div>' +
            '</div>'
        );

        // Tags de imagem de qrcode {qrcode_img}
        t = t.replace(/\{qrcode_img\}/gi,
            '<div class="wa-media-card">' +
            '<img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=00020101021226580014br.gov.bcb.pix" alt="QR Code Pix" />' +
            '<div class="wa-media-badge"><i class="bi bi-qr-code me-1"></i>QR Code Pix (Aponte a câmera)</div>' +
            '</div>'
        );

        // Formatação nativa do WhatsApp (*negrito*, _itálico_, ~tachado~, ```código```)
        return t
            .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
            .replace(/_(.*?)_/g, '<em>$1</em>')
            .replace(/~(.*?)~/g, '<del>$1</del>')
            .replace(/```(.*?)```/gs, '<code>$1</code>')
            .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
            .replace(/\n/g, '<br>');
    }

    function getStatusCheckHtml(status) {
        switch (status) {
            case 'read':
                return '<i class="bi bi-check2-all" style="color: #53bdeb; font-size: 0.95rem;" title="Mensagem Lida"></i>';
            case 'delivered':
                return '<i class="bi bi-check2-all" style="color: #8696a0; font-size: 0.95rem;" title="Mensagem Entregue"></i>';
            case 'sent':
                return '<i class="bi bi-check" style="color: #8696a0; font-size: 0.95rem;" title="Mensagem Enviada"></i>';
            case 'failed':
                return '<i class="bi bi-exclamation-circle text-danger" style="font-size: 0.85rem;" title="Falha no Envio"></i>';
            case 'queued':
            default:
                return '<i class="bi bi-clock text-secondary" style="font-size: 0.8rem;" title="Na Fila"></i>';
        }
    }

    // =========================================================================
    // 2. MODAL DE SMARTPHONE WHATSAPP AO CLICAR NO HISTÓRICO
    // =========================================================================
    const phoneModalEl = document.getElementById('modalWaPhonePreview');
    const phoneModal = phoneModalEl ? new bootstrap.Modal(phoneModalEl) : null;

    document.querySelectorAll('.js-open-wa-modal').forEach(trigger => {
        trigger.addEventListener('click', function () {
            const customer = this.getAttribute('data-customer') || 'Cliente';
            const phone = this.getAttribute('data-to') || '';
            const message = this.getAttribute('data-message') || '';
            const status = this.getAttribute('data-status') || 'sent';
            const time = this.getAttribute('data-time') || '12:00';
            const date = this.getAttribute('data-date') || 'HOJE';

            // Atualiza Topo e Status Bar
            const statusTime = document.getElementById('wa-phone-status-time');
            if (statusTime) statusTime.textContent = time;

            // Avatar & Nome
            const avatarText = document.getElementById('wa-phone-avatar-text');
            if (avatarText) {
                const initial = customer.trim().charAt(0).toUpperCase() || 'C';
                avatarText.textContent = initial;
            }

            const contactName = document.getElementById('wa-phone-contact-name');
            if (contactName) {
                contactName.textContent = customer;
                contactName.title = `${customer} (${phone})`;
            }

            const statusText = document.getElementById('wa-phone-status-text');
            if (statusText) {
                statusText.textContent = phone ? `online • ${phone}` : 'online';
            }

            // Data Pill & Mensagem
            const datePill = document.getElementById('wa-phone-date-pill');
            if (datePill) datePill.textContent = date;

            // Imagem de QR Code Pix no Topo do Balão
            const qrUrl = this.getAttribute('data-qr-url') || '';
            const mediaContainer = document.getElementById('wa-phone-media-container');
            const mediaImg = document.getElementById('wa-phone-media-img');
            if (mediaContainer && mediaImg) {
                if (qrUrl && qrUrl.trim() !== '') {
                    mediaImg.src = qrUrl;
                    mediaContainer.classList.remove('d-none');
                } else {
                    mediaContainer.classList.add('d-none');
                    mediaImg.src = '';
                }
            }

            const messageBody = document.getElementById('wa-phone-message-body');
            if (messageBody) {
                messageBody.innerHTML = formatWhatsAppMessage(message);
            }

            const bubbleTime = document.getElementById('wa-phone-bubble-time');
            if (bubbleTime) bubbleTime.textContent = time;

            const statusCheck = document.getElementById('wa-phone-status-check');
            if (statusCheck) {
                statusCheck.innerHTML = getStatusCheckHtml(status);
            }

            if (phoneModal) {
                phoneModal.show();
            }
        });
    });

    // Copiar conteúdo da mensagem do smartphone
    const waCopyBtn = document.getElementById('wa-phone-copy-btn');
    if (waCopyBtn) {
        waCopyBtn.addEventListener('click', () => {
            const bodyEl = document.getElementById('wa-phone-message-body');
            const text = bodyEl ? bodyEl.innerText : '';
            if (text) {
                navigator.clipboard.writeText(text).then(() => {
                    if (typeof Toastify === 'function') {
                        Toastify({
                            text: "Texto da mensagem copiado com sucesso!",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            style: { background: "linear-gradient(to right, #00b09b, #96c93d)", borderRadius: "10px" }
                        }).showToast();
                    }
                });
            }
        });
    }

    // =========================================================================
    // 3. MODAL DE ENVIO MANUAL COM LIVE PREVIEW SMARTPHONE
    // =========================================================================
    const manualModalEl = document.getElementById('modal-manual-send');
    if (!manualModalEl) return;

    const manualModal = new bootstrap.Modal(manualModalEl);
    const editor = document.getElementById('wa-editor');
    const preview = document.getElementById('msg-preview');
    const counter = document.getElementById('msg-char-count');
    const emojiPicker = document.getElementById('emoji-picker');
    const manualTo = document.getElementById('manual-to');
    const templateSelect = document.getElementById('template-select');
    const custSearchInput = document.getElementById('customer-search-input');
    const custSearchResults = document.getElementById('customer-search-results');
    const custSearchSpinner = document.getElementById('customer-search-spinner');
    const selectedCustId = document.getElementById('selected-customer-id');
    const custCard = document.getElementById('customer-selected-card');
    const custCardName = document.getElementById('card-cust-name');
    const custCardMeta = document.getElementById('card-cust-meta');
    const custCardInvoice = document.getElementById('card-cust-invoice-badge');
    const custClearBtn = document.getElementById('customer-clear-btn');
    const previewContactName = document.getElementById('preview-contact-name');
    const previewContactAvatar = document.getElementById('preview-contact-avatar');
    const previewLiveTime = document.getElementById('preview-live-time');
    const previewBubbleTime = document.getElementById('preview-bubble-time');

    const emojiList = ['😀', '👍', '✅', '❌', '📄', '💰', '🔔', '⚠️', '📞', '📧', '🏠', '🎉', '⏰', '📅', '💳', '🔗'];
    if (emojiPicker) {
        emojiPicker.innerHTML = emojiList.map((emoji) => `<button type="button" data-emoji="${emoji}" aria-label="${emoji}">${emoji}</button>`).join('');
    }

    function updateLiveClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        if (previewLiveTime) previewLiveTime.textContent = timeStr;
        if (previewBubbleTime) previewBubbleTime.textContent = timeStr;
    }
    updateLiveClock();

    function formatPhoneInput(value) {
        const digits = value.replace(/\D+/g, '').slice(0, 13);
        if (!digits) return '';
        if (digits.length <= 2) return `+${digits}`;
        if (digits.length <= 4) return `+${digits.slice(0, 2)} (${digits.slice(2)}`;
        if (digits.length <= 9) return `+${digits.slice(0, 2)} (${digits.slice(2, 4)}) ${digits.slice(4)}`;
        return `+${digits.slice(0, 2)} (${digits.slice(2, 4)}) ${digits.slice(4, 9)}-${digits.slice(9)}`;
    }

    function wrapSelection(before, after) {
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        const text = editor.value;
        const selected = text.substring(start, end);

        if (selected) {
            editor.value = text.substring(0, start) + before + selected + after + text.substring(end);
            editor.selectionStart = start + before.length;
            editor.selectionEnd = end + before.length;
        } else {
            editor.value = text.substring(0, start) + before + after + text.substring(end);
            editor.selectionStart = start + before.length;
            editor.selectionEnd = start + before.length;
        }

        editor.focus();
        updatePreview();
    }

    function insertAtCursor(text) {
        const start = editor.selectionStart;
        const val = editor.value;
        editor.value = val.substring(0, start) + text + val.substring(editor.selectionEnd);
        editor.selectionStart = editor.selectionEnd = start + text.length;
        editor.focus();
        updatePreview();
    }

    // Inserção de variáveis rápidas
    document.querySelectorAll('[data-insert-var]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            insertAtCursor(el.dataset.insertVar);
        });
    });

    // Barra de formatação do WhatsApp
    document.querySelectorAll('[data-wa-format]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const fmt = btn.dataset.waFormat;

            switch (fmt) {
                case 'bold':
                    wrapSelection('*', '*');
                    break;
                case 'italic':
                    wrapSelection('_', '_');
                    break;
                case 'strike':
                    wrapSelection('~', '~');
                    break;
                case 'mono':
                    wrapSelection('```', '```');
                    break;
                case 'list': {
                    const start = editor.selectionStart;
                    const end = editor.selectionEnd;
                    const selected = editor.value.substring(start, end);
                    const lines = selected ? selected.split('\n') : [''];
                    const numbered = lines.map((line, index) => `${index + 1}. ${line}`.trimEnd()).join('\n');

                    if (selected) {
                        editor.value = editor.value.substring(0, start) + numbered + editor.value.substring(end);
                        editor.selectionStart = start;
                        editor.selectionEnd = start + numbered.length;
                    } else {
                        insertAtCursor('1. ');
                    }

                    updatePreview();
                    break;
                }
                case 'bullet': {
                    const start = editor.selectionStart;
                    const end = editor.selectionEnd;
                    const selected = editor.value.substring(start, end);
                    const lines = selected ? selected.split('\n') : [''];
                    const bulleted = lines.map((line) => `- ${line}`.trimEnd()).join('\n');

                    if (selected) {
                        editor.value = editor.value.substring(0, start) + bulleted + editor.value.substring(end);
                        editor.selectionStart = start;
                        editor.selectionEnd = start + bulleted.length;
                    } else {
                        insertAtCursor('- ');
                    }

                    updatePreview();
                    break;
                }
                case 'emoji':
                    emojiPicker.classList.toggle('d-none');
                    break;
            }
        });
    });

    // Inserção de Emojis
    if (emojiPicker) {
        emojiPicker.addEventListener('click', (event) => {
            const target = event.target.closest('button[data-emoji]');
            if (target) {
                insertAtCursor(target.dataset.emoji);
            }
        });
    }

    // Renderização do Live Preview no Smartphone da Direita
    function updatePreview() {
        const raw = editor.value;
        const len = raw.length;
        if (counter) counter.textContent = `${len} ${len === 1 ? 'caractere' : 'caracteres'}`;

        if (!raw.trim()) {
            if (preview) preview.innerHTML = '<em class="text-white-50">A mensagem aparecerá aqui durante a digitação...</em>';
            return;
        }

        if (preview) {
            preview.innerHTML = formatWhatsAppMessage(raw);
        }
    }

    if (editor) {
        editor.addEventListener('input', updatePreview);
        editor.addEventListener('keyup', updatePreview);
    }

    if (manualTo) {
        manualTo.addEventListener('input', (e) => {
            const cursor = e.target.selectionStart;
            e.target.value = formatPhoneInput(e.target.value);
            e.target.setSelectionRange(cursor, cursor);
        });
    }

    // Busca de clientes em tempo real com debounce
    let searchTimeout = null;
    if (custSearchInput) {
        custSearchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const query = custSearchInput.value.trim();

            if (query.length < 2) {
                custSearchResults.classList.add('d-none');
                custSearchResults.innerHTML = '';
                return;
            }

            custSearchSpinner.classList.remove('d-none');

            searchTimeout = setTimeout(async () => {
                try {
                    const resp = await fetch(`{{ route('tenant.whatsapp.customers.search') }}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    const data = await resp.json();

                    custSearchSpinner.classList.add('d-none');
                    custSearchResults.innerHTML = '';

                    if (!data.customers || data.customers.length === 0) {
                        custSearchResults.innerHTML = '<div class="p-2 text-muted small text-center">Nenhum cliente encontrado.</div>';
                        custSearchResults.classList.remove('d-none');
                        return;
                    }

                    data.customers.forEach((c) => {
                        const item = document.createElement('div');
                        item.className = 'p-2 wa-cust-item rounded-2 d-flex justify-content-between align-items-center';
                        item.innerHTML = `
                            <div>
                                <div class="fw-bold small text-body">${c.name}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    ${c.phone ? '<i class="bi bi-whatsapp text-success me-1"></i>' + c.phone : 'Sem WhatsApp'} 
                                    ${c.document ? ' • ' + c.document : ''}
                                </div>
                            </div>
                            ${c.open_invoices_count > 0 ? `<span class="badge bg-warning text-dark small">${c.open_invoices_count} fatura(s) pendente(s)</span>` : ''}
                        `;

                        item.addEventListener('click', () => {
                            selectCustomer(c);
                        });

                        custSearchResults.appendChild(item);
                    });

                    custSearchResults.classList.remove('d-none');
                } catch (err) {
                    custSearchSpinner.classList.add('d-none');
                    console.error('Erro ao buscar clientes:', err);
                }
            }, 300);
        });
    }

    // Selecionar cliente
    function selectCustomer(cust) {
        selectedCustId.value = cust.id;
        custSearchInput.value = cust.name;
        custSearchResults.classList.add('d-none');

        // Atualiza Card de Seleção
        custCardName.textContent = cust.name;
        custCardMeta.textContent = `${cust.phone || 'Sem telefone'} • ${cust.document || 'Sem documento'}`;
        custCard.classList.remove('d-none');
        custClearBtn.classList.remove('d-none');

        // Atualiza Preview do Smartphone
        if (previewContactName) previewContactName.textContent = cust.name;
        if (previewContactAvatar) {
            previewContactAvatar.textContent = cust.name.trim().charAt(0).toUpperCase() || 'C';
        }

        // Se tem telefone cadastrado, preenche o destinatário
        if (cust.phone) {
            manualTo.value = formatPhoneInput(cust.phone);
        }

        // Exibe badge de fatura se houver
        if (cust.latest_invoice) {
            custCardInvoice.innerHTML = `
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="bi bi-exclamation-circle me-1"></i>Fatura #${cust.latest_invoice.number} (${cust.latest_invoice.amount_formatted})
                </span>
            `;
        } else {
            custCardInvoice.innerHTML = '';
        }

        // Se tiver template selecionado, recarrega o conteúdo interpolado
        if (templateSelect && templateSelect.value) {
            loadTemplate(templateSelect.value, cust.id);
        }
    }

    // Desvincular cliente
    if (custClearBtn) {
        custClearBtn.addEventListener('click', () => {
            selectedCustId.value = '';
            custSearchInput.value = '';
            custCard.classList.add('d-none');
            custClearBtn.classList.add('d-none');
            if (previewContactName) previewContactName.textContent = 'Destinatário';
            if (previewContactAvatar) previewContactAvatar.textContent = 'D';
        });
    }

    // Carregar template selecionado
    async function loadTemplate(templateId, customerId = null) {
        if (!templateId) return;

        try {
            const url = `{{ url('/painel/templates') }}/${templateId}/json${customerId ? '?customer_id=' + customerId : ''}`;
            const resp = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const data = await resp.json();

            if (data && data.content) {
                editor.value = data.content;
                updatePreview();
            }
        } catch (err) {
            console.error('Erro ao carregar template:', err);
        }
    }

    if (templateSelect) {
        templateSelect.addEventListener('change', () => {
            if (templateSelect.value) {
                loadTemplate(templateSelect.value, selectedCustId.value || null);
            }
        });
    }

    // Resetar formulário
    const btnReset = document.getElementById('btn-reset-form');
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            editor.value = '';
            manualTo.value = '';
            templateSelect.value = '';
            if (custClearBtn) custClearBtn.click();
            updatePreview();
        });
    }

    // Botão abrir modal manual
    const btnManualSend = document.getElementById('btn-manual-send');
    if (btnManualSend) {
        btnManualSend.addEventListener('click', () => {
            const hasInstance = btnManualSend.dataset.hasInstance === '1';
            if (!hasInstance) {
                Swal.fire({
                    title: 'Instância Desconectada',
                    text: 'Conecte sua instância do WhatsApp para realizar envios.',
                    icon: 'warning',
                    confirmButtonText: 'Ver Instâncias',
                    showCancelButton: true,
                    cancelButtonText: 'Fechar',
                }).then((r) => {
                    if (r.isConfirmed) {
                        window.location.href = '{{ route("tenant.instances") }}';
                    }
                });
                return;
            }
            updateLiveClock();
            updatePreview();
            manualModal.show();
        });
    }

    // Confirmação e Envio da Mensagem Manual
    const btnSendManual = document.getElementById('btn-confirm-send-manual');
    if (btnSendManual) {
        btnSendManual.addEventListener('click', async () => {
            const rawTo = manualTo.value.trim();
            const cleanTo = rawTo.replace(/\D+/g, '');
            const message = editor.value.trim();
            const custId = selectedCustId.value ? parseInt(selectedCustId.value) : null;
            const tplId = templateSelect.value ? parseInt(templateSelect.value) : null;

            if (!cleanTo || cleanTo.length < 10) {
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: 'Informe um número de telefone válido com DDD (mínimo 10 dígitos).',
                        duration: 3500,
                        gravity: "top",
                        position: "right",
                        style: { background: "#dc3545" }
                    }).showToast();
                }
                manualTo.focus();
                return;
            }

            if (!message) {
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: 'O corpo da mensagem não pode ficar vazio.',
                        duration: 3500,
                        gravity: "top",
                        position: "right",
                        style: { background: "#dc3545" }
                    }).showToast();
                }
                editor.focus();
                return;
            }

            // Confirmação via SweetAlert2 (conforme regra global)
            const result = await Swal.fire({
                title: 'Confirmar envio da mensagem?',
                html: `Deseja realmente enviar esta mensagem para o número <strong>${rawTo}</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-whatsapp me-1"></i> Sim, enviar agora',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
            });

            if (!result.isConfirmed) return;

            btnSendManual.disabled = true;
            btnSendManual.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

            try {
                const resp = await fetch('{{ route("tenant.whatsapp.send-manual") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        to: cleanTo,
                        message: message,
                        customer_id: custId,
                        template_id: tplId,
                    }),
                });

                const data = await resp.json().catch(() => ({}));

                btnSendManual.disabled = false;
                btnSendManual.innerHTML = '<i class="bi bi-whatsapp me-1"></i> Enviar Mensagem';

                if (resp.ok && data.ok) {
                    if (typeof Toastify === 'function') {
                        Toastify({
                            text: data.message || 'Mensagem enviada com sucesso!',
                            duration: 3500,
                            gravity: "top",
                            position: "right",
                            style: { background: "linear-gradient(to right, #00b09b, #96c93d)" }
                        }).showToast();
                    }
                    manualModal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    const errorMsg = data.message || 'Erro ao enviar mensagem WhatsApp.';
                    if (data.blocked) {
                        Swal.fire({
                            title: 'Envio Bloqueado pela Segurança Anti-Ban',
                            text: errorMsg,
                            icon: 'warning',
                            confirmButtonText: 'Entendido',
                        });
                    } else {
                        if (typeof Toastify === 'function') {
                            Toastify({
                                text: errorMsg,
                                duration: 3500,
                                gravity: "top",
                                position: "right",
                                style: { background: "#dc3545" }
                            }).showToast();
                        }
                    }
                }
            } catch (err) {
                btnSendManual.disabled = false;
                btnSendManual.innerHTML = '<i class="bi bi-whatsapp me-1"></i> Enviar Mensagem';
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: 'Erro de conexão ao enviar mensagem.',
                        duration: 3500,
                        gravity: "top",
                        position: "right",
                        style: { background: "#dc3545" }
                    }).showToast();
                }
                console.error(err);
            }
        });
    }
});
</script>
@endpush
