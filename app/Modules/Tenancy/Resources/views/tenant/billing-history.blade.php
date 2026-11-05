@extends('tenancy::tenant.layout')

@section('title', 'Histórico de Cobrança')

@push('head')
<style>
/* ==========================================================================
   ESTILOS GERAIS DA PÁGINA DE HISTÓRICO DE COBRANÇA
   ========================================================================== */
.billing-history-page .form-control,
.billing-history-page .form-select {
    min-height: 42px;
}
.billing-history-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
}
.billing-msg-row {
    transition: background-color .15s ease;
}
.billing-msg-row:hover {
    background-color: rgba(var(--bs-primary-rgb), .03) !important;
}

/* ==========================================================================
   SIMULADOR DE SMARTPHONE WHATSAPP ULTRA-REALISTA
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
.wa-phone-status-bar {
    height: 32px;
    padding: 6px 18px 0 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #e9edef;
    font-size: 0.74rem;
    font-weight: 600;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    z-index: 10;
    background: #1f2c34;
}
.wa-status-icons {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
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
    background: #6b7c85;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.95rem;
    position: relative;
    flex-shrink: 0;
}
.wa-verified-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #00a884;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    border: 1.5px solid #1f2c34;
}
.wa-header-info {
    flex-grow: 1;
    min-width: 0;
}
.wa-header-name {
    color: #e9edef;
    font-size: 0.88rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.wa-header-status {
    color: #8696a0;
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 4px;
}
.badge-online-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #00a884;
    display: inline-block;
}
.wa-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #aebac1;
    font-size: 1rem;
}
.wa-chat-body {
    flex-grow: 1;
    background-color: #0b141a;
    background-image: 
        radial-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        radial-gradient(rgba(255,255,255,0.015) 1px, #0b141a 1px);
    background-size: 24px 24px;
    padding: 12px 14px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.wa-date-pill {
    align-self: center;
    background: #182229;
    color: #8696a0;
    font-size: 0.68rem;
    font-weight: 500;
    padding: 4px 12px;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.wa-encryption-pill {
    align-self: center;
    max-width: 90%;
    background: #182229;
    color: #ffd279;
    font-size: 0.64rem;
    padding: 6px 10px;
    border-radius: 8px;
    text-align: center;
    line-height: 1.35;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
}
.wa-bubble-sent {
    align-self: flex-end;
    background: #005c4b;
    color: #e9edef;
    padding: 8px 10px 6px 10px;
    border-radius: 8px 0 8px 8px;
    max-width: 88%;
    font-size: 0.84rem;
    line-height: 1.38;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.35);
    word-break: break-word;
}
.wa-bubble-sent::before {
    content: '';
    position: absolute;
    top: 0;
    right: -8px;
    width: 0;
    height: 0;
    border-top: 9px solid #005c4b;
    border-right: 9px solid transparent;
}
.wa-bubble-sent strong {
    font-weight: 700;
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
    font-size: 0.78rem;
    display: block;
    margin: 4px 0;
    white-space: pre-wrap;
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
.wa-media-card {
    background: #0b141a;
    border-radius: 8px;
    overflow: hidden;
    margin: 4px 0 8px 0;
    border: 1px solid rgba(255,255,255,0.08);
    text-align: center;
    padding: 8px;
}
.wa-media-card img {
    max-width: 180px;
    width: 100%;
    height: auto;
    border-radius: 6px;
    display: block;
    margin: 0 auto;
    background: #fff;
    padding: 6px;
}
.wa-media-card .wa-media-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    color: #00a884;
    margin-top: 6px;
    font-weight: 600;
}
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
.wa-phone-home-bar {
    width: 120px;
    height: 4px;
    background: rgba(255, 255, 255, 0.45);
    border-radius: 4px;
    margin: 4px auto 2px auto;
    flex-shrink: 0;
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-history-page py-3">
    <x-ui.page-header
        title="Histórico Interativo de Cobrança"
        subtitle="Auditoria completa de todas as mensagens e cobranças disparadas para clientes, com simulador autêntico de celular."
        eyebrow="Comunicação de cobrança">
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total de mensagens</span>
                <span class="dashboard-note__value">{{ number_format($stats['total'], 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Falhas</span>
                <span class="dashboard-note__value text-danger">{{ number_format($stats['failed'], 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    {{-- Cards de Resumo e Estatísticas --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total de mensagens" :value="number_format($stats['total'], 0, ',', '.')" icon="bi-chat-dots" color="primary">
                <x-slot:meta>Todos os registros de comunicação de cobrança.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="WhatsApp" :value="number_format($stats['whatsapp'], 0, ',', '.')" icon="bi-whatsapp" color="success">
                <x-slot:meta>Disparos via WhatsApp oficial e instâncias.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="E-mail" :value="number_format($stats['email'], 0, ',', '.')" icon="bi-envelope" color="info">
                <x-slot:meta>E-mails transacionais e notificações.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Falhas / Bloqueios" :value="number_format($stats['failed'], 0, ',', '.')" icon="bi-x-circle" color="danger">
                <x-slot:meta>Mensagens não entregues ou com erro.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    {{-- Filtros de Histórico --}}
    <x-ui.premium-card title="Filtros de histórico" subtitle="Pesquise por cliente, fatura, canal ou status." class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label small fw-semibold">Buscar</label>
                <input type="search" name="q" class="form-control" placeholder="Cliente, WhatsApp, e-mail, fatura..." value="{{ request('q') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Canal</label>
                <select name="channel" class="form-select">
                    <option value="">Todos os canais</option>
                    <option value="whatsapp" @selected(request('channel') === 'whatsapp')>WhatsApp</option>
                    <option value="email" @selected(request('channel') === 'email')>E-mail</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>Agendado</option>
                    <option value="sent" @selected(request('status') === 'sent')>Enviado</option>
                    <option value="delivered" @selected(request('status') === 'delivered')>Entregue</option>
                    <option value="read" @selected(request('status') === 'read')>Lido</option>
                    <option value="failed" @selected(request('status') === 'failed')>Falhou</option>
                    <option value="canceled" @selected(request('status') === 'canceled')>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                @if(request()->hasAny(['q','channel','status']))
                    <a href="{{ route('tenant.billing.history') }}" class="btn btn-outline-secondary" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    @php
        $statusMap = [
            'scheduled' => ['tone' => 'secondary', 'label' => 'Agendado', 'icon' => 'bi-clock'],
            'sent'      => ['tone' => 'info', 'label' => 'Enviado', 'icon' => 'bi-check'],
            'delivered' => ['tone' => 'success', 'label' => 'Entregue', 'icon' => 'bi-check2-all'],
            'read'      => ['tone' => 'primary', 'label' => 'Lido', 'icon' => 'bi-check2-all text-info'],
            'opened'    => ['tone' => 'primary', 'label' => 'Aberto', 'icon' => 'bi-envelope-open'],
            'clicked'   => ['tone' => 'success', 'label' => 'Clicado', 'icon' => 'bi-cursor-fill'],
            'failed'    => ['tone' => 'danger', 'label' => 'Falhou', 'icon' => 'bi-exclamation-circle'],
            'bounced'   => ['tone' => 'danger', 'label' => 'Rejeitado', 'icon' => 'bi-x-circle'],
            'canceled'  => ['tone' => 'secondary', 'label' => 'Cancelado', 'icon' => 'bi-slash-circle'],
        ];
        $byCustomer = $messages->getCollection()->groupBy(fn($m) => $m->customer_name ?? $m->customer_whatsapp ?? 'sem-cliente');
    @endphp

    {{-- Abas de Navegação: Lista Interativa x Por Cliente --}}
    <ul class="nav nav-pills mb-3 gap-2" id="historyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-semibold" id="tab-list-btn" data-bs-toggle="pill" data-bs-target="#tab-list-content" type="button" role="tab" aria-selected="true">
                <i class="bi bi-list-check me-2"></i>Lista Cronológica de Disparos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-semibold" id="tab-customers-btn" data-bs-toggle="pill" data-bs-target="#tab-customers-content" type="button" role="tab" aria-selected="false">
                <i class="bi bi-people me-2"></i>Agrupado por Cliente ({{ $byCustomer->count() }})
            </button>
        </li>
    </ul>

    <div class="tab-content" id="historyTabsContent">
        {{-- =========================================================================
             ABA 1: LISTA CRONOLÓGICA INTERATIVA
             ========================================================================= --}}
        <div class="tab-pane fade show active" id="tab-list-content" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-body p-0">
                    @if($messages->isEmpty())
                        <x-ui.empty-state icon="bi-chat-dots" title="Nenhuma mensagem encontrada" message="O histórico de cobrança aparecerá aqui assim que os disparos forem efetuados." />
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable mb-0 align-middle" id="table-billing-history" style="width: 100% !important;" data-export-title="Histórico de Cobrança">
                                <thead class="table-light">
                                    <tr>
                                        <th class="all" style="width: 50px;">Canal</th>
                                        <th class="all" data-priority="1">Cliente / Destinatário</th>
                                        <th class="desktop">Fatura Vinculada</th>
                                        <th class="desktop">Conteúdo Disparado (Clique p/ celular)</th>
                                        <th class="min-tablet">Status</th>
                                        <th class="min-tablet text-nowrap">Data / Hora</th>
                                        <th class="all text-end no-export" data-priority="1" data-orderable="false" style="width: 130px; min-width: 120px; white-space: nowrap;">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($messages as $m)
                                        @php
                                            $stInfo = $statusMap[$m->status] ?? ['tone' => 'secondary', 'label' => ucfirst((string)$m->status), 'icon' => 'bi-clock'];
                                            $customerName = $m->customer_name ?: 'Cliente não identificado';
                                            $customerPhone = $m->customer_whatsapp ?: $m->recipient;
                                            $invNumber = $m->invoice_number ?: '-';
                                            $invAmount = $m->invoice_amount ? 'R$ ' . number_format((float)$m->invoice_amount, 2, ',', '.') : '';
                                            $invDueDate = $m->invoice_due_date ? \Carbon\Carbon::parse($m->invoice_due_date)->format('d/m/Y') : '';
                                            
                                            // Extração de QR Code da fatura ou da mensagem
                                            $invMeta = is_string($m->invoice_meta) ? json_decode($m->invoice_meta, true) : (array) ($m->invoice_meta ?? []);
                                            $msgMeta = is_string($m->meta) ? json_decode($m->meta, true) : (array) ($m->meta ?? []);
                                            
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

                                            $rawBody = $m->body ?: ($m->subject ?: 'Cobrança');
                                            $formattedTime = $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('H:i') : '12:00';
                                            $formattedDate = $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('d/m/Y') : 'HOJE';
                                            $isWpp = $m->channel === 'whatsapp';
                                        @endphp
                                        <tr class="billing-msg-row">
                                            <td class="text-center">
                                                @if($isWpp)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle p-2 rounded-circle" title="WhatsApp">
                                                        <i class="bi bi-whatsapp fs-6"></i>
                                                    </span>
                                                @else
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle p-2 rounded-circle" title="E-mail">
                                                        <i class="bi bi-envelope fs-6"></i>
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $customerName }}</div>
                                                <div class="small text-muted text-nowrap">
                                                    @if($isWpp)
                                                        <i class="bi bi-whatsapp text-success me-1"></i>{{ \App\Support\Format::phone($customerPhone) }}
                                                    @else
                                                        <i class="bi bi-envelope text-info me-1"></i>{{ $m->recipient }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($m->invoice_number)
                                                    <div>
                                                        <a href="{{ route('tenant.invoices') }}?search={{ $m->invoice_number }}" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none fw-semibold">
                                                            <i class="bi bi-receipt me-1"></i>#{{ $m->invoice_number }}
                                                        </a>
                                                    </div>
                                                    @if($invAmount)
                                                        <small class="fw-semibold text-dark">{{ $invAmount }}</small>
                                                        @if($invDueDate)
                                                            <small class="text-muted d-block" style="font-size: 0.725rem;">Venc: {{ $invDueDate }}</small>
                                                        @endif
                                                    @endif
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>
                                            @if($isWpp)
                                                <td class="js-open-phone-viewer" role="button"
                                                    data-channel="whatsapp"
                                                    data-customer="{{ $customerName }}"
                                                    data-phone="{{ \App\Support\Format::phone($customerPhone) }}"
                                                    data-recipient="{{ $m->recipient }}"
                                                    data-subject="{{ $m->subject }}"
                                                    data-message="{{ $rawBody }}"
                                                    data-status="{{ $m->status }}"
                                                    data-time="{{ $formattedTime }}"
                                                    data-date="{{ $formattedDate }}"
                                                    data-invoice="{{ $invNumber }}"
                                                    data-amount="{{ $invAmount }}"
                                                    data-due-date="{{ $invDueDate }}"
                                                    data-qr-url="{{ $qrCodeUrl }}"
                                                    title="Clique para simular exatamente no WhatsApp do cliente">
                                                    <div class="text-truncate d-flex align-items-center gap-1" style="max-width: 320px;">
                                                        <i class="bi bi-whatsapp text-success small me-1"></i>
                                                        <span class="text-body">{{ Str::limit(strip_tags($rawBody), 80) }}</span>
                                                    </div>
                                                    @if(!empty($qrCodeUrl))
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 0.65rem;">
                                                            <i class="bi bi-qr-code me-1"></i>Com Imagem QR Code
                                                        </span>
                                                    @endif
                                                </td>
                                            @else
                                                <td class="js-open-email-viewer" role="button"
                                                    data-channel="email"
                                                    data-customer="{{ $customerName }}"
                                                    data-recipient="{{ $m->recipient }}"
                                                    data-subject="{{ $m->subject }}"
                                                    data-message="{{ $rawBody }}"
                                                    data-status="{{ $m->status }}"
                                                    data-time="{{ $formattedTime }}"
                                                    data-date="{{ $formattedDate }}"
                                                    data-invoice="{{ $invNumber }}"
                                                    title="Clique para visualizar o E-mail enviado">
                                                    <div class="text-truncate d-flex align-items-center gap-1" style="max-width: 320px;">
                                                        <i class="bi bi-envelope text-info small me-1"></i>
                                                        <span class="text-body fw-semibold">{{ $m->subject ?: 'Cobrança por E-mail' }}</span>
                                                    </div>
                                                    <small class="text-muted d-block text-truncate" style="font-size: 0.725rem; max-width: 320px;">
                                                        {{ Str::limit(strip_tags($rawBody), 60) }}
                                                    </small>
                                                </td>
                                            @endif
                                            <td>
                                                <span class="badge bg-{{ $stInfo['tone'] }}-subtle text-{{ $stInfo['tone'] }} border border-{{ $stInfo['tone'] }}-subtle px-2 py-1">
                                                    <i class="bi {{ $stInfo['icon'] }} me-1"></i>{{ $stInfo['label'] }}
                                                </span>
                                            </td>
                                            <td class="small text-nowrap text-muted">
                                                <div>{{ $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') : '-' }}</div>
                                                <small style="font-size: 0.725rem;">{{ $m->created_at ? \Carbon\Carbon::parse($m->created_at)->diffForHumans() : '' }}</small>
                                            </td>
                                            <td class="all text-end no-export" style="width: 130px; min-width: 120px; white-space: nowrap;">
                                                @if($isWpp)
                                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-1 js-open-phone-viewer"
                                                            data-channel="whatsapp"
                                                            data-customer="{{ $customerName }}"
                                                            data-phone="{{ \App\Support\Format::phone($customerPhone) }}"
                                                            data-recipient="{{ $m->recipient }}"
                                                            data-subject="{{ $m->subject }}"
                                                            data-message="{{ $rawBody }}"
                                                            data-status="{{ $m->status }}"
                                                            data-time="{{ $formattedTime }}"
                                                            data-date="{{ $formattedDate }}"
                                                            data-invoice="{{ $invNumber }}"
                                                            data-amount="{{ $invAmount }}"
                                                            data-due-date="{{ $invDueDate }}"
                                                            data-qr-url="{{ $qrCodeUrl }}"
                                                            title="Visualizar no WhatsApp">
                                                        <i class="bi bi-whatsapp me-1"></i>Ver WhatsApp
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2 py-1 js-open-email-viewer"
                                                            data-channel="email"
                                                            data-customer="{{ $customerName }}"
                                                            data-recipient="{{ $m->recipient }}"
                                                            data-subject="{{ $m->subject }}"
                                                            data-message="{{ $rawBody }}"
                                                            data-status="{{ $m->status }}"
                                                            data-time="{{ $formattedTime }}"
                                                            data-date="{{ $formattedDate }}"
                                                            data-invoice="{{ $invNumber }}"
                                                            title="Visualizar E-mail enviado">
                                                        <i class="bi bi-envelope-open me-1"></i>Ver E-mail
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($messages->hasPages())
                            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 bg-light">
                                <small class="text-muted">Mostrando {{ $messages->firstItem() }} a {{ $messages->lastItem() }} de {{ $messages->total() }} mensagens</small>
                                <div>{{ $messages->links() }}</div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- =========================================================================
             ABA 2: MENSAGENS POR CLIENTE (CARDS AGRUPADOS)
             ========================================================================= --}}
        <div class="tab-pane fade" id="tab-customers-content" role="tabpanel">
            <x-ui.premium-card title="Mensagens por cliente" :subtitle="$messages->total() . ' registro(s) em ' . $byCustomer->count() . ' cliente(s).'">
                <div class="row g-3">
                    @forelse($byCustomer as $customerKey => $items)
                        @php
                            $first = $items->first();
                            $whatsapp = $items->pluck('customer_whatsapp')->filter()->first();
                            $whatsappMsgs = $items->where('channel', 'whatsapp')->count();
                            $emailMsgs = $items->where('channel', 'email')->count();
                            $failedMsgs = $items->whereIn('status', ['failed', 'bounced'])->count();
                            $total = $items->count();
                        @endphp
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 border-0 shadow-sm cursor-pointer js-open-history-modal" role="button"
                                 data-customer="{{ $first->customer_name ?? 'Cliente não informado' }}"
                                 data-whatsapp="{{ \App\Support\Format::phone($whatsapp) ?? 'Sem WhatsApp' }}"
                                 data-messages="{{ $items->map(fn($m) => [
                                     'datetime' => \App\Support\Format::datetime($m->created_at) . ' (' . \Illuminate\Support\Carbon::parse($m->created_at)->diffForHumans() . ')',
                                     'channel' => $m->channel === 'whatsapp' ? 'WhatsApp' : ($m->channel === 'email' ? 'E-mail' : ucfirst($m->channel)),
                                     'channel_icon' => $m->channel === 'whatsapp' ? 'bi-whatsapp text-success' : ($m->channel === 'email' ? 'bi-envelope text-info' : 'bi-chat'),
                                     'invoice_number' => $m->invoice_number ?? '-',
                                     'content' => $m->subject ?: ($m->body ? \Illuminate\Support\Str::limit(strip_tags($m->body), 80) : '-'),
                                     'raw_message' => $m->body ?: ($m->subject ?: ''),
                                     'time' => $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('H:i') : '12:00',
                                     'date' => $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('d/m/Y') : 'HOJE',
                                     'status' => $m->status,
                                     'status_tone' => ($statusMap[$m->status] ?? ['tone' => 'secondary'])['tone'],
                                     'status_label' => ($statusMap[$m->status] ?? ['label' => ucfirst((string)$m->status)])['label'],
                                 ])->values()->toJson() }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-0 fw-bold">{{ $first->customer_name ?? 'Cliente não informado' }}</h6>
                                            <small class="text-muted">{{ \App\Support\Format::phone($whatsapp) ?? 'Sem WhatsApp' }}</small>
                                        </div>
                                        <span class="badge bg-primary">{{ $total }}</span>
                                    </div>
                                    <div class="d-flex gap-2 small">
                                        @if($whatsappMsgs > 0)
                                            <span class="text-success"><i class="bi bi-whatsapp me-1"></i>{{ $whatsappMsgs }} WA</span>
                                        @endif
                                        @if($emailMsgs > 0)
                                            <span class="text-info"><i class="bi bi-envelope me-1"></i>{{ $emailMsgs }} E-mail</span>
                                        @endif
                                        @if($failedMsgs > 0)
                                            <span class="text-danger"><i class="bi bi-x-circle me-1"></i>{{ $failedMsgs }} falha(s)</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <x-ui.empty-state icon="bi-chat-dots" title="Nenhuma mensagem registrada" message="O histórico de mensagens de cobrança aparecerá aqui quando as comunicações forem enviadas." />
                        </div>
                    @endforelse
                </div>
            </x-ui.premium-card>
        </div>
    </div>
</div>

{{-- ==========================================================================
     MODAL DE SMARTPHONE ULTRA-REALISTA DO CLIENTE
     ========================================================================== --}}
<div class="modal fade" id="modalBillingPhonePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 bg-transparent shadow-none">
            <div class="wa-phone-wrapper">
                <div class="wa-phone-screen">
                    {{-- Notch / Dynamic Island --}}
                    <div class="wa-phone-notch">
                        <div class="wa-notch-cam"></div>
                        <div class="wa-notch-sensor"></div>
                    </div>

                    {{-- Barra de Status do Smartphone --}}
                    <div class="wa-phone-status-bar">
                        <span id="bp-phone-status-time">12:00</span>
                        <div class="wa-status-icons">
                            <i class="bi bi-reception-4"></i>
                            <i class="bi bi-wifi"></i>
                            <i class="bi bi-battery-full"></i>
                        </div>
                    </div>

                    {{-- Header do Chat WhatsApp --}}
                    <div class="wa-chat-header">
                        <i class="bi bi-arrow-left wa-header-back-btn" data-bs-dismiss="modal" title="Voltar"></i>
                        <div class="wa-header-avatar" id="bp-phone-avatar">
                            <span id="bp-phone-avatar-text">C</span>
                            <span class="wa-verified-badge"><i class="bi bi-check"></i></span>
                        </div>
                        <div class="wa-header-info">
                            <div class="wa-header-name" id="bp-phone-contact-name">Cliente</div>
                            <div class="wa-header-status">
                                <span class="badge-online-dot"></span> <span id="bp-phone-status-text">online</span>
                            </div>
                        </div>
                        <div class="wa-header-actions">
                            <i class="bi bi-camera-video" title="Chamada de vídeo"></i>
                            <i class="bi bi-telephone" title="Chamada de voz"></i>
                            <i class="bi bi-three-dots-vertical" title="Mais opções"></i>
                            <button type="button" class="btn-close btn-close-white ms-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                    </div>

                    {{-- Área de Conversa com Wallpaper Oficial do WhatsApp --}}
                    <div class="wa-chat-body" id="bp-phone-chat-body">
                        {{-- Pílula de Data --}}
                        <div class="wa-date-pill" id="bp-phone-date-pill">HOJE</div>

                        {{-- Selo de Criptografia de Ponta a Ponta --}}
                        <div class="wa-encryption-pill">
                            <i class="bi bi-lock-fill me-1 text-warning"></i> As mensagens e chamadas são protegidas com a criptografia de ponta a ponta. Ninguém fora desta conversa pode ler ou ouvir o conteúdo.
                        </div>

                        {{-- Balão de Mensagem Enviada --}}
                        <div class="wa-bubble-sent" id="bp-phone-bubble">
                            {{-- Área de Imagem / QR Code no Topo --}}
                            <div id="bp-phone-media-container" class="d-none">
                                <div class="wa-media-card">
                                    <img id="bp-phone-media-img" src="" alt="QR Code Pix Cobrança" />
                                    <div class="wa-media-badge"><i class="bi bi-qr-code me-1"></i>QR Code Pix (Aponte a câmera)</div>
                                </div>
                            </div>

                            {{-- Corpo da Mensagem Formatado --}}
                            <div id="bp-phone-message-body"></div>

                            {{-- Horário e Ticks de Entrega --}}
                            <div class="wa-bubble-meta">
                                <span id="bp-phone-bubble-time">12:00</span>
                                <span class="wa-status-check" id="bp-phone-status-check">
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
                        <div class="wa-mic-btn" title="Copiar texto da mensagem" id="bp-phone-copy-btn">
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

<!-- Modal Visualização de E-mail Transacional -->
<div class="modal fade" id="modalBillingEmailPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-info text-white p-2 rounded-circle"><i class="bi bi-envelope fs-6"></i></span>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold" id="email-preview-subject">Assunto do E-mail</h6>
                        <small class="text-white-50" id="email-preview-meta">Para: cliente@email.com</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0 bg-light" style="min-height: 480px;">
                <div id="email-preview-container" class="p-3 d-flex justify-content-center">
                    <iframe id="email-preview-frame" style="width: 100%; min-height: 540px; border: 0; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);"></iframe>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 border-top">
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes do Cliente (Cards agrupados) -->
<div class="modal fade" id="modal-history-details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-person me-2 text-primary"></i><span id="mh-customer"></span></h5>
                    <small class="text-muted" id="mh-whatsapp"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Canal</th>
                                <th>Fatura</th>
                                <th>Conteúdo</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="mh-body"></tbody>
                    </table>
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
// Formatador nativo de WhatsApp
function formatWhatsAppText(raw) {
    if (!raw) return '';
    let text = String(raw);

    // Se o conteúdo tiver estrutura HTML completa (ex: e-mail), extrai o texto legível
    if (text.includes('<div') || text.includes('<table') || text.includes('<p>') || text.includes('<html')) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        text = doc.body.innerText || doc.body.textContent || text;
    }

    let t = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Markdown oficial WhatsApp
    return t
        .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
        .replace(/_(.*?)_/g, '<em>$1</em>')
        .replace(/~(.*?)~/g, '<del>$1</del>')
        .replace(/```(.*?)```/gs, '<code>$1</code>')
        .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
        .replace(/\n/g, '<br>');
}

function getStatusTicks(status) {
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
        case 'scheduled':
        default:
            return '<i class="bi bi-clock text-secondary" style="font-size: 0.8rem;" title="Na Fila / Agendada"></i>';
    }
}

const phoneModalEl = document.getElementById('modalBillingPhonePreview');
const phoneModal = phoneModalEl ? new bootstrap.Modal(phoneModalEl) : null;

const emailModalEl = document.getElementById('modalBillingEmailPreview');
const emailModal = emailModalEl ? new bootstrap.Modal(emailModalEl) : null;

function openPhoneMockup(data) {
    const customer = data.customer || 'Cliente';
    const phone = data.phone || data.recipient || '';
    const message = data.message || '';
    const status = data.status || 'sent';
    const time = data.time || '12:00';
    const date = data.date || 'HOJE';
    const qrUrl = data.qrUrl || '';

    // Status Bar & Header
    document.getElementById('bp-phone-status-time').textContent = time;
    document.getElementById('bp-phone-avatar-text').textContent = customer.trim().charAt(0).toUpperCase() || 'C';
    document.getElementById('bp-phone-contact-name').textContent = customer;
    document.getElementById('bp-phone-status-text').textContent = phone ? `online • ${phone}` : 'online';
    document.getElementById('bp-phone-date-pill').textContent = date;

    // Mídia / QR Code no topo do balão
    const mediaContainer = document.getElementById('bp-phone-media-container');
    const mediaImg = document.getElementById('bp-phone-media-img');
    if (qrUrl && qrUrl.trim() !== '') {
        mediaImg.src = qrUrl;
        mediaContainer.classList.remove('d-none');
    } else {
        mediaContainer.classList.add('d-none');
        mediaImg.src = '';
    }

    // Corpo da Mensagem Formatado
    document.getElementById('bp-phone-message-body').innerHTML = formatWhatsAppText(message);
    document.getElementById('bp-phone-bubble-time').textContent = time;
    document.getElementById('bp-phone-status-check').innerHTML = getStatusTicks(status);

    if (phoneModal) phoneModal.show();
}

const tenantBranding = {
    logoUrl: @json($tenantBranding['logo_url'] ?? ''),
    brandName: @json($tenantBranding['brand_name'] ?? ($tenant->company_name ?? '')),
    primaryColor: @json($tenantBranding['primary_color'] ?? '#0f172a'),
    accentColor: @json($tenantBranding['accent_color'] ?? '#0d6efd')
};

function openEmailMockup(data) {
    const subject = data.subject || 'Sem Assunto';
    const recipient = data.recipient || '';
    const customer = data.customer || '';
    const date = data.date || '';
    const time = data.time || '';
    let bodyHtml = data.message || '';

    // Aplica personalização dinâmica de branding caso o HTML legado tenha cores fixas ou falte logo
    if (tenantBranding.primaryColor && tenantBranding.primaryColor.toLowerCase() !== '#0f172a') {
        bodyHtml = bodyHtml.replace(/background:\s*#0f172a\b/gi, 'background:' + tenantBranding.primaryColor);
        bodyHtml = bodyHtml.replace(/background:\s*#0d6efd\b/gi, 'background:' + tenantBranding.primaryColor);
    }
    if (tenantBranding.accentColor && tenantBranding.accentColor.toLowerCase() !== '#93c5fd') {
        bodyHtml = bodyHtml.replace(/color:\s*#93c5fd\b/gi, 'color:' + tenantBranding.accentColor);
    }
    if (tenantBranding.logoUrl && !bodyHtml.includes(tenantBranding.logoUrl)) {
        const logoTag = `<div style="margin:0 0 16px 0;text-align:left;"><img src="${tenantBranding.logoUrl}" alt="${tenantBranding.brandName}" style="max-height:56px;max-width:220px;height:auto;display:inline-block;border:0;outline:none;object-fit:contain;vertical-align:middle;"></div>`;
        bodyHtml = bodyHtml.replace(/(<td[^>]*padding:\s*22px 26px[^>]*>)/i, `$1${logoTag}`);
    }

    const subjectEl = document.getElementById('email-preview-subject');
    const metaEl = document.getElementById('email-preview-meta');
    const iframe = document.getElementById('email-preview-frame');

    if (subjectEl) subjectEl.textContent = subject;
    if (metaEl) metaEl.textContent = `Para: ${customer ? customer + ' <' + recipient + '>' : recipient} • ${date} ${time}`;
    if (iframe) iframe.srcdoc = bodyHtml;

    if (emailModal) emailModal.show();
}

// Clique nos botões de WhatsApp
document.querySelectorAll('.js-open-phone-viewer').forEach(el => {
    el.addEventListener('click', function(e) {
        e.stopPropagation();
        openPhoneMockup({
            customer: this.dataset.customer,
            phone: this.dataset.phone,
            recipient: this.dataset.recipient,
            message: this.dataset.message,
            status: this.dataset.status,
            time: this.dataset.time,
            date: this.dataset.date,
            qrUrl: this.dataset.qrUrl
        });
    });
});

// Clique nos botões de E-mail
document.querySelectorAll('.js-open-email-viewer').forEach(el => {
    el.addEventListener('click', function(e) {
        e.stopPropagation();
        openEmailMockup({
            customer: this.dataset.customer,
            recipient: this.dataset.recipient,
            subject: this.dataset.subject,
            message: this.dataset.message,
            time: this.dataset.time,
            date: this.dataset.date
        });
    });
});

// Copiar mensagem para clipboard
const copyBtn = document.getElementById('bp-phone-copy-btn');
if (copyBtn) {
    copyBtn.addEventListener('click', () => {
        const text = document.getElementById('bp-phone-message-body')?.innerText;
        if (text) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: "Texto da mensagem copiado!",
                        duration: 2500,
                        gravity: "top",
                        position: "right",
                        style: { background: "linear-gradient(to right, #00b09b, #96c93d)", borderRadius: "8px" }
                    }).showToast();
                }
            });
        }
    });
}

// Modal Detalhes Agrupados por Cliente
const mhModal = new bootstrap.Modal(document.getElementById('modal-history-details'));
const toneBadge = {
    active: 'bg-success',
    inactive: 'bg-info',
    sandbox: 'bg-warning text-dark',
    overdue: 'bg-danger',
    secondary: 'bg-secondary',
};

document.querySelectorAll('.js-open-history-modal').forEach(card => {
    card.addEventListener('click', () => {
        document.getElementById('mh-customer').textContent = card.dataset.customer;
        document.getElementById('mh-whatsapp').textContent = card.dataset.whatsapp;
        const items = JSON.parse(card.dataset.messages || '[]');
        const tbody = document.getElementById('mh-body');
        tbody.innerHTML = items.map((item, idx) => `
            <tr>
                <td>${item.datetime}</td>
                <td><i class="bi ${item.channel_icon} me-1"></i>${item.channel}</td>
                <td><span class="badge text-bg-light border">${item.invoice_number}</span></td>
                <td><div class="text-truncate" style="max-width:220px;">${item.content}</div></td>
                <td><span class="badge ${toneBadge[item.status_tone] || 'bg-secondary'}">${item.status_label}</span></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0" onclick="openPhoneMockupFromClientModal(${idx})">
                        <i class="bi bi-phone me-1"></i>Ver
                    </button>
                </td>
            </tr>
        `).join('');

        window.__clientItems = items;
        window.__clientName = card.dataset.customer;
        window.__clientPhone = card.dataset.whatsapp;

        mhModal.show();
    });
});

window.openPhoneMockupFromClientModal = function(idx) {
    const item = window.__clientItems ? window.__clientItems[idx] : null;
    if (!item) return;

    if (item.channel === 'email' || item.channel === 'E-mail') {
        openEmailMockup({
            customer: window.__clientName || 'Cliente',
            recipient: item.recipient || window.__clientEmail || '',
            subject: item.subject || item.content || 'E-mail de Cobrança',
            message: item.raw_message || item.content,
            time: item.time || '12:00',
            date: item.date || 'HOJE'
        });
    } else {
        openPhoneMockup({
            customer: window.__clientName || 'Cliente',
            phone: window.__clientPhone || '',
            message: item.raw_message || item.content,
            status: item.status || 'sent',
            time: item.time || '12:00',
            date: item.date || 'HOJE',
            qrUrl: ''
        });
    }
};
</script>
@endpush
