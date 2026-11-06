@extends('admin.layouts.app')

@section('title', 'WhatsApp - Mensagens')

@push('head')
<style>
/* ==========================================================================
   ESTILOS CONSISTENTES DA CENTRAL DE MENSAGENS WHATSAPP
   ========================================================================== */
.wa-messages-page {
    --wa-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --wa-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.wa-messages-filter .form-control,
.wa-messages-filter .form-select {
    min-height: 42px;
}
.wa-messages-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    vertical-align: middle;
}
.wa-messages-table tbody td {
    vertical-align: middle;
}
.wa-messages-snippet {
    max-width: 420px;
}
.wa-messages-message {
    white-space: pre-wrap;
    word-break: break-word;
    font-size: .88rem;
    line-height: 1.45;
}
.wa-contact-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 60%, #000));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    font-weight: 700;
    flex-shrink: 0;
}

/* ==========================================================================
   SIMULADOR DE SMARTPHONE WHATSAPP ULTRA-REALISTA (PADRÃO OFICIAL)
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
    height: 580px;
    max-height: 80vh;
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
    max-width: 92%;
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
    font-size: 0.68rem;
    color: #25d366;
    margin-top: 4px;
    font-weight: 600;
}
.wa-chat-footer {
    background: #1f2c34;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 10;
}
.wa-input-pill {
    flex-grow: 1;
    background: #2a3942;
    border-radius: 20px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #8696a0;
    font-size: 0.85rem;
}
.wa-input-text {
    flex-grow: 1;
    font-size: 0.82rem;
}
.wa-mic-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #00a884;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: transform .15s ease;
}
.wa-mic-btn:hover {
    transform: scale(1.05);
}
.wa-phone-home-bar {
    height: 16px;
    background: #000000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wa-phone-home-bar::after {
    content: '';
    width: 90px;
    height: 4px;
    background: #ffffff;
    border-radius: 9999px;
    opacity: 0.4;
}

/* Modal Detalhes Customizado */
.wa-detail-modal-tabs .nav-link {
    font-weight: 600;
    font-size: .88rem;
    padding: .5rem 1rem;
    border-radius: 8px;
    color: var(--bs-secondary-color);
}
.wa-detail-modal-tabs .nav-link.active {
    background: var(--bs-primary);
    color: #fff;
}
</style>
@endpush

@section('content')
<div class="container-fluid wa-messages-page">
    <x-ui.page-header
        title="Mensagens WhatsApp"
        subtitle="Histórico operacional de envios e recebimentos, com busca, reenvio e envio manual para soluções rápidas."
        eyebrow="Operação WhatsApp">
        <x-slot:actions>
            <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                <a href="javascript:void(0)" class="js-meta-help-trigger text-muted p-2 text-decoration-none d-flex align-items-center" data-help-topic="usage-delivery-tracking" title="Instruções sobre Rastreamento, Templates e Status">
                    <i class="bi bi-question-circle fs-5"></i>
                </a>
                <button class="btn btn-success" id="btn-manual-send">
                    <i class="bi bi-send-fill me-1"></i> Nova mensagem manual
                </button>
                <button class="btn btn-outline-secondary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                </button>
            </div>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total registrado</span>
                <span class="dashboard-note__value">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Falhas nas últimas 24h</span>
                <span class="dashboard-note__value text-danger">{{ number_format($stats['failed'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    @php
        $cards = [
            ['label' => 'Enviadas nas últimas 24h', 'value' => $stats['sent'] ?? 0, 'icon' => 'bi-send-check', 'color' => 'success', 'meta' => 'Mensagens com envio concluído nas últimas 24 horas.'],
            ['label' => 'Falhas nas últimas 24h', 'value' => $stats['failed'] ?? 0, 'icon' => 'bi-exclamation-circle', 'color' => 'danger', 'meta' => 'Mensagens que retornaram erro ou bloqueio.'],
            ['label' => 'Entregues nas últimas 24h', 'value' => $stats['delivered'] ?? 0, 'icon' => 'bi-check2-all', 'color' => 'info', 'meta' => 'Mensagens com confirmação de entrega da plataforma.'],
            ['label' => 'Total geral', 'value' => $stats['total'] ?? 0, 'icon' => 'bi-chat-dots', 'color' => 'primary', 'meta' => 'Histórico consolidado salvo no banco.'],
        ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach($cards as $card)
            <div class="col-12 col-md-6 col-xl-3">
                <x-ui.stats-card :label="$card['label']" :value="number_format($card['value'], 0, ',', '.')" :icon="$card['icon']" :color="$card['color']">
                    <x-slot:meta>{{ $card['meta'] }}</x-slot:meta>
                </x-ui.stats-card>
            </div>
        @endforeach
    </div>

    {{-- Filtros de Pesquisa Padronizados --}}
    <x-ui.premium-card title="Filtros de pesquisa" subtitle="Refine o histórico por número, conteúdo, status, direção e período." class="mb-4">
        <form class="row g-3 wa-messages-filter" method="GET" action="{{ url('/admin/whatsapp/messages') }}">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label small fw-bold">Buscar</label>
                <input type="search" name="q" class="form-control" placeholder="Número, texto ou ID Meta (WAMID)" value="{{ request('q') }}">
            </div>
            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach(['sent' => 'Enviada', 'failed' => 'Falha', 'pending' => 'Pendente', 'delivered' => 'Entregue', 'read' => 'Lida'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-bold">Direção</label>
                <select name="direction" class="form-select">
                    <option value="">Todas</option>
                    <option value="out" @selected(request('direction') === 'out')>Saída</option>
                    <option value="in" @selected(request('direction') === 'in')>Entrada</option>
                </select>
            </div>
            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-bold">Data inicial</label>
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-md-2 col-xl-2">
                <label class="form-label small fw-bold">Data final</label>
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-12 col-xl-1 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary w-100" title="Filtrar">
                    <i class="bi bi-search"></i>
                </button>
                @if(request()->anyFilled(['q', 'status', 'direction', 'from', 'to']))
                    <a href="{{ url('/admin/whatsapp/messages') }}" class="btn btn-outline-secondary" title="Limpar">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>

        {{-- Pílulas de Atalhos Rápidos --}}
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
            <span class="small text-secondary fw-semibold"><i class="bi bi-lightning-charge me-1"></i>Filtros rápidos:</span>
            <a href="{{ url('/admin/whatsapp/messages') }}" class="badge rounded-pill text-decoration-none {{ !request()->filled('status') && !request()->filled('from') ? 'bg-primary' : 'bg-secondary-subtle text-body border' }} p-2 px-3">
                Todas as Mensagens
            </a>
            <a href="{{ url('/admin/whatsapp/messages?status=failed') }}" class="badge rounded-pill text-decoration-none {{ request('status') === 'failed' ? 'bg-danger' : 'bg-danger-subtle text-danger border border-danger-subtle' }} p-2 px-3">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Apenas Falhas ({{ $stats['failed'] ?? 0 }})
            </a>
            <a href="{{ url('/admin/whatsapp/messages?status=delivered') }}" class="badge rounded-pill text-decoration-none {{ request('status') === 'delivered' ? 'bg-success' : 'bg-success-subtle text-success border border-success-subtle' }} p-2 px-3">
                <i class="bi bi-check-circle-fill me-1"></i> Entregues
            </a>
            <a href="{{ url('/admin/whatsapp/messages?from='.now()->format('Y-m-d').'&to='.now()->format('Y-m-d')) }}" class="badge rounded-pill text-decoration-none {{ request('from') === now()->format('Y-m-d') ? 'bg-info text-white' : 'bg-secondary-subtle text-body border' }} p-2 px-3">
                <i class="bi bi-calendar-event me-1"></i> Disparadas Hoje
            </a>
        </div>
    </x-ui.premium-card>

    {{-- Tabela de Mensagens em Largura Total --}}
    <x-ui.premium-card title="Histórico de mensagens" subtitle="Timeline operacional dos registros salvos pela plataforma.">
        <div class="table-responsive p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 wa-messages-table app-datatable mb-0" id="table-whatsapp-messages" data-export-title="Mensagens de WhatsApp" style="width: 100% !important;">
                <thead>
                    <tr>
                        <th class="all" style="width:70px">#</th>
                        <th class="all" data-priority="1">Destino</th>
                        <th class="desktop">Mensagem</th>
                        <th class="desktop">Tipo</th>
                        <th class="desktop">Direção</th>
                        <th class="min-tablet" data-priority="2">Status</th>
                        <th class="min-tablet">Registrada em</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 90px; min-width: 80px; max-width: 100px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $msg)
                        @php
                            $response = json_decode($msg->response ?? '{}', true);
                            $type = $response['type'] ?? ($msg->media_type ?? 'text');
                            $typeIcons = ['text' => 'bi-chat-text', 'audio' => 'bi-mic', 'image' => 'bi-image', 'document' => 'bi-file-earmark'];
                            $statusColors = ['sent' => 'active', 'delivered' => 'production', 'read' => 'sandbox', 'failed' => 'overdue', 'pending' => 'pending'];

                            // Telefone formatado
                            $rawPhone = preg_replace('/\D+/', '', (string) $msg->to);
                            $formattedPhone = $msg->to;
                            if (strlen($rawPhone) === 13 && str_starts_with($rawPhone, '55')) {
                                $formattedPhone = '+55 (' . substr($rawPhone, 2, 2) . ') ' . substr($rawPhone, 4, 5) . '-' . substr($rawPhone, 9);
                            } elseif (strlen($rawPhone) === 12 && str_starts_with($rawPhone, '55')) {
                                $formattedPhone = '+55 (' . substr($rawPhone, 2, 2) . ') ' . substr($rawPhone, 4, 4) . '-' . substr($rawPhone, 8);
                            }
                        @endphp
                        <tr>
                            <td class="text-muted font-monospace">{{ $msg->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="wa-contact-avatar">
                                        <i class="bi bi-whatsapp"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-body font-monospace">{{ $formattedPhone }}</div>
                                        <div class="small text-muted font-monospace" style="font-size: .75rem;" title="{{ $msg->meta_message_id ?? $msg->external_id }}">
                                            {{ ($msg->meta_message_id ?? $msg->external_id) ? \Illuminate\Support\Str::limit($msg->meta_message_id ?? $msg->external_id, 18) : 'Sem ID' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="wa-messages-snippet">
                                <div class="wa-messages-message">{{ \Illuminate\Support\Str::limit($msg->message, 120) }}</div>
                            </td>
                            <td>
                                <span class="d-inline-flex align-items-center gap-1 badge bg-body-tertiary text-secondary border">
                                    <i class="bi {{ $typeIcons[$type] ?? 'bi-chat' }}"></i>
                                    <span>{{ ucfirst($type) }}</span>
                                </span>
                            </td>
                            <td>
                                <x-ui.status-badge :status="$msg->direction === 'out' ? 'production' : 'sandbox'" :label="$msg->direction === 'out' ? 'Saída' : 'Entrada'" />
                            </td>
                            <td>
                                <x-ui.status-badge :status="$statusColors[$msg->status] ?? 'secondary'" :label="ucfirst($msg->status)" />
                            </td>
                            <td>
                                <div>{{ \Carbon\Carbon::parse($msg->created_at)->format('d/m/Y') }}</div>
                                <div class="small text-muted">{{ \Carbon\Carbon::parse($msg->created_at)->format('H:i') }} ({{ \Carbon\Carbon::parse($msg->created_at)->diffForHumans() }})</div>
                            </td>
                            <td class="all text-end no-export" style="width: 90px; min-width: 80px; max-width: 100px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2 btn-view-message" 
                                        data-id="{{ $msg->id }}"
                                        data-to="{{ $formattedPhone }}"
                                        data-status="{{ $msg->status }}"
                                        data-meta-id="{{ $msg->meta_message_id ?? $msg->external_id ?? '' }}"
                                        data-date="{{ \Carbon\Carbon::parse($msg->created_at)->format('d/m/Y H:i') }}"
                                        data-time="{{ \Carbon\Carbon::parse($msg->created_at)->format('H:i') }}"
                                        data-content="{{ base64_encode($msg->message ?? '') }}"
                                        data-response="{{ base64_encode($msg->response ?? '{}') }}"
                                        title="Visualizar detalhes">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success p-1 px-2 btn-resend-message" 
                                        data-id="{{ $msg->id }}" 
                                        title="Reenviar mensagem">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-chat-dots fs-1 d-block mb-2 opacity-50"></i>
                                Nenhuma mensagem encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($messages, 'links'))
            <div class="mt-3 p-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">
                    Exibindo {{ $messages->firstItem() ?? 0 }} a {{ $messages->lastItem() ?? 0 }} de {{ $messages->total() }} mensagens
                </div>
                <div>
                    {{ $messages->links() }}
                </div>
            </div>
        @endif
    </x-ui.premium-card>
</div>

{{-- ==========================================================================
     MODAL DE VISUALIZAÇÃO COM SMARTPHONE WHATSAPP & DIAGNÓSTICO
     ========================================================================== --}}
<div class="modal fade" id="modal-detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success p-2 rounded-circle"><i class="bi bi-whatsapp fs-6"></i></span>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold" id="modal-detail-title">Visualização de Mensagem WhatsApp</h6>
                        <small class="text-white-50" id="modal-detail-subtitle">Histórico e diagnóstico operacional</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body p-0">
                {{-- Abas de Navegação --}}
                <div class="bg-body-tertiary border-bottom px-4 pt-2">
                    <ul class="nav nav-pills wa-detail-modal-tabs gap-2 pb-2" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-wa-phone" type="button">
                                <i class="bi bi-phone me-1"></i> Visualização WhatsApp
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-wa-diag" type="button">
                                <i class="bi bi-code-slash me-1"></i> Dados Técnicos & Diagnóstico
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content p-4">
                    {{-- ABA 1: Simulador Realista de Smartphone --}}
                    <div class="tab-pane fade show active" id="tab-wa-phone">
                        <div class="wa-phone-wrapper">
                            <div class="wa-phone-screen">
                                <div class="wa-phone-notch">
                                    <div class="wa-notch-cam"></div>
                                    <div class="wa-notch-sensor"></div>
                                </div>
                                <div class="wa-phone-status-bar">
                                    <span id="bp-phone-status-time">12:00</span>
                                    <div class="wa-status-icons">
                                        <i class="bi bi-reception-4"></i>
                                        <i class="bi bi-wifi"></i>
                                        <i class="bi bi-battery-full"></i>
                                    </div>
                                </div>
                                <div class="wa-chat-header">
                                    <div class="wa-header-avatar" id="bp-phone-avatar">
                                        <span id="bp-phone-avatar-text">W</span>
                                        <span class="wa-verified-badge"><i class="bi bi-check"></i></span>
                                    </div>
                                    <div class="wa-header-info">
                                        <div class="wa-header-name" id="bp-phone-contact-name">Destinatário</div>
                                        <div class="wa-header-status">
                                            <span class="badge-online-dot"></span> <span id="bp-phone-status-text">online</span>
                                        </div>
                                    </div>
                                    <div class="wa-header-actions">
                                        <i class="bi bi-camera-video"></i>
                                        <i class="bi bi-telephone"></i>
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </div>
                                </div>
                                <div class="wa-chat-body" id="bp-phone-chat-body">
                                    <div class="wa-date-pill" id="bp-phone-date-pill">HOJE</div>
                                    <div class="wa-encryption-pill">
                                        <i class="bi bi-lock-fill me-1 text-warning"></i> Mensagem protegida com criptografia de ponta a ponta.
                                    </div>

                                    {{-- Balão de Mensagem --}}
                                    <div class="wa-bubble-sent" id="bp-phone-bubble">
                                        <div id="bp-phone-media-container" class="d-none">
                                            <div class="wa-media-card">
                                                <img id="bp-phone-media-img" src="" alt="Mídia Anexa" />
                                                <div class="wa-media-badge"><i class="bi bi-qr-code me-1"></i>QR Code Pix Anexo</div>
                                            </div>
                                        </div>
                                        <div id="bp-phone-message-body" style="white-space: pre-wrap; word-break: break-word;"></div>
                                        <div class="wa-bubble-meta">
                                            <span id="bp-phone-bubble-time">12:00</span>
                                            <span class="wa-status-check" id="bp-phone-status-check">
                                                <i class="bi bi-check2-all text-info"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="wa-chat-footer">
                                    <div class="wa-input-pill">
                                        <i class="bi bi-emoji-smile"></i>
                                        <span class="wa-input-text">Mensagem</span>
                                        <i class="bi bi-paperclip"></i>
                                        <i class="bi bi-camera"></i>
                                    </div>
                                    <div class="wa-mic-btn" title="Copiar texto da mensagem" id="bp-phone-copy-btn">
                                        <i class="bi bi-clipboard-check"></i>
                                    </div>
                                </div>
                                <div class="wa-phone-home-bar"></div>
                            </div>
                        </div>
                    </div>

                    {{-- ABA 2: Dados Técnicos e Diagnóstico --}}
                    <div class="tab-pane fade" id="tab-wa-diag">
                        {{-- Card de Erro se Houver Falha --}}
                        <div class="alert alert-danger d-none mb-3" id="det-diag-failure-alert">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
                                <div>
                                    <div class="fw-bold">Falha Detectada no Disparo:</div>
                                    <div class="small mt-1" id="det-diag-failure-text">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered">
                                <tr>
                                    <td class="text-muted fw-bold bg-body-tertiary" style="width:30%">ID do Registro</td>
                                    <td id="det-id" class="font-monospace"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-bold bg-body-tertiary">Destino</td>
                                    <td id="det-to" class="font-monospace"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-bold bg-body-tertiary">Meta Message ID (WAMID)</td>
                                    <td id="det-meta-id" class="font-monospace small"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-bold bg-body-tertiary">Status</td>
                                    <td id="det-status"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-bold bg-body-tertiary">Data do Registro</td>
                                    <td id="det-date"></td>
                                </tr>
                            </table>
                        </div>

                        <label class="form-label fw-bold small text-muted text-uppercase">Resposta da Integração (JSON)</label>
                        <pre id="det-response" class="bg-body-tertiary border rounded-3 p-3 font-monospace small" style="max-height: 240px; overflow-y: auto;"></pre>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top bg-body-tertiary px-4 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success fw-bold" id="btn-modal-resend-direct">
                    <i class="bi bi-arrow-repeat me-1"></i> Reenviar Mensagem Agora
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Envio Manual Oficial --}}
<x-ui.premium-modal id="modal-manual-send" title="Envio Manual de Mensagem WhatsApp" size="xl">
    <x-slot:subtitle>Solução rápida e operacional: selecione o cliente e template para autopreenchimento inteligente, ou digite o número e a mensagem livremente.</x-slot:subtitle>
    <div class="row g-4">
        <div class="col-lg-7">
            <form id="frm-manual-msg" class="d-grid gap-3" onsubmit="return false;">
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
                            <div id="customer-search-results" class="position-absolute w-100 bg-body rounded-3 shadow-lg border p-1 mt-1 d-none" style="z-index: 1060; max-height: 240px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-bold mb-1">
                            <i class="bi bi-file-earmark-text me-1 text-info"></i> Template <span class="text-danger">*</span>
                        </label>
                        <select name="template_id" id="template-select" class="form-select" required>
                            <option value="">Selecione um template obrigatório...</option>
                            @if(isset($templates))
                                @foreach($templates as $tpl)
                                    <option value="{{ $tpl->id }}" data-slug="{{ $tpl->slug }}" data-name="{{ $tpl->name }}">
                                        [{{ ucfirst($tpl->category) }}] {{ $tpl->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>

                <div id="customer-selected-card" class="card border border-primary-subtle bg-primary-subtle bg-opacity-10 rounded-3 p-2 d-none">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-check-fill text-primary fs-4"></i>
                            <div>
                                <div class="fw-bold text-primary" id="card-cust-name">-</div>
                                <div class="small text-muted" id="card-cust-meta">-</div>
                            </div>
                        </div>
                        <div id="card-cust-invoice-badge"></div>
                    </div>
                </div>

                <div>
                    <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1">
                        <span><i class="bi bi-telephone-fill me-1 text-success"></i> Número do Destinatário</span>
                        <span class="badge bg-secondary-subtle text-muted fw-normal">DDI + DDD + Número</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-success bg-opacity-10 text-success"><i class="bi bi-whatsapp"></i></span>
                        <input name="to" id="manual-to" class="form-control font-monospace" placeholder="+55 (11) 99999-8888" inputmode="tel" required>
                    </div>
                    <div class="form-text text-muted">Preenchido automaticamente ao selecionar o cliente, ou você pode digitar/editar diretamente qualquer número.</div>
                </div>

                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label fw-bold mb-0"><i class="bi bi-chat-text-fill me-1 text-warning"></i> Mensagem WhatsApp</label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
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

                    <div class="d-flex align-items-center gap-1 mb-2" id="wa-toolbar">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="bold" title="Negrito (*texto*)"><i class="bi bi-type-bold"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="italic" title="Itálico (_texto_)"><i class="bi bi-type-italic"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="strike" title="Riscado (~texto~)"><i class="bi bi-type-strikethrough"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="mono" title="Monoespaçado (```texto```)"><i class="bi bi-code"></i></button>
                        <span class="border-end mx-1" style="height: 20px;"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="list" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="bullet" title="Lista com marcadores"><i class="bi bi-list-ul"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="indent" title="Citação"><i class="bi bi-text-indent-left"></i></button>
                    </div>
                    <textarea name="message" id="wa-editor" class="form-control font-monospace" rows="8" placeholder="Digite a mensagem diretamente ou selecione um template acima."></textarea>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <label class="form-label fw-bold"><i class="bi bi-phone me-1"></i> Preview ao Vivo</label>
            <div class="border rounded-4 p-3 bg-body-tertiary d-flex flex-column justify-content-between" style="min-height: 420px;">
                <div>
                    <div class="d-flex align-items-center gap-2 p-2 mb-3 rounded-3 bg-body border">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div class="flex-grow-1 text-truncate">
                            <div class="fw-bold small text-truncate" id="preview-contact-name">Destinatário</div>
                            <div class="text-muted" style="font-size: 0.75rem;" id="preview-contact-number">Aguardando número...</div>
                        </div>
                        <i class="bi bi-whatsapp text-success"></i>
                    </div>

                    <div id="msg-preview" class="p-3 rounded-3 border bg-body" style="font-size: .88rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">
                        <em class="text-muted">A mensagem aparecerá aqui em tempo real...</em>
                    </div>
                </div>

                <div class="pt-3 border-top mt-3 d-flex justify-content-between align-items-center small text-muted">
                    <span id="msg-char-count">0 caracteres</span>
                    <span class="text-success"><i class="bi bi-shield-check me-1"></i> Pronto para envio</span>
                </div>
            </div>
        </div>
    </div>
    <x-slot:footer>
        <button type="button" class="btn btn-outline-danger me-auto" id="btn-reset-form">
            <i class="bi bi-trash me-1"></i> Limpar tudo
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-success px-4" id="btn-confirm-send-manual">
            <i class="bi bi-whatsapp me-1"></i> Enviar Mensagem
        </button>
    </x-slot:footer>
</x-ui.premium-modal>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const detailModalEl = document.getElementById('modal-detail');
    const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
    let activeMsgId = null;

    // Parser seguro de markdown para o balão do WhatsApp
    function renderWhatsAppMarkdown(rawText) {
        if (!rawText) return '<em class="text-muted">Sem conteúdo</em>';
        return rawText
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\*(.+?)\*/g, '<strong>$1</strong>')
            .replace(/_(.+?)_/g, '<em>$1</em>')
            .replace(/~(.+?)~/g, '<del>$1</del>')
            .replace(/```(.+?)```/gs, '<code>$1</code>')
            .replace(/^> (.+)$/gm, '<div style="border-left: 3px solid #00a884; padding-left: 8px; opacity: 0.9;">$1</div>')
            .replace(/\n/g, '<br>');
    }

    // Helper para decodificar base64 seguro em UTF-8
    function decodeBase64Utf8(b64) {
        try {
            return decodeURIComponent(escape(atob(b64)));
        } catch (e) {
            try {
                return atob(b64);
            } catch (err) {
                return '';
            }
        }
    }

    // =========================================================================
    // VISUALIZAR DETALHES DA MENSAGEM (ZERO ERROS / INSTANTÂNEO)
    // =========================================================================
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-message');
        if (!btn) return;

        e.preventDefault();
        const id = btn.dataset.id;
        const to = btn.dataset.to || '-';
        const status = btn.dataset.status || 'sent';
        const metaId = btn.dataset.metaId || btn.dataset.evoId || '-';
        const date = btn.dataset.date || '-';
        const time = btn.dataset.time || '12:00';
        const content = decodeBase64Utf8(btn.dataset.content || '');
        const responseRaw = decodeBase64Utf8(btn.dataset.response || '{}');

        activeMsgId = id;

        // Limpa mídia da mensagem anterior para não exibir imagem errada
        document.getElementById('bp-phone-media-container')?.classList.add('d-none');
        const prevMedia = document.getElementById('bp-phone-media-img');
        if (prevMedia) prevMedia.removeAttribute('src');

        // Preenche imediatamente a tela do smartphone WhatsApp (sem depender de rede!)
        document.getElementById('bp-phone-contact-name').textContent = to;
        document.getElementById('bp-phone-avatar-text').textContent = (to.replace(/\D+/g, '').slice(-1) || 'W').toUpperCase();
        document.getElementById('bp-phone-message-body').innerHTML = renderWhatsAppMarkdown(content);
        document.getElementById('bp-phone-bubble-time').textContent = time;
        document.getElementById('bp-phone-status-time').textContent = time;

        // Ticks de status
        const tickWrap = document.getElementById('bp-phone-status-check');
        if (status === 'read') {
            tickWrap.innerHTML = '<i class="bi bi-check2-all text-info" title="Lida"></i>';
        } else if (status === 'delivered') {
            tickWrap.innerHTML = '<i class="bi bi-check2-all" style="color: #53bdeb;" title="Entregue"></i>';
        } else if (status === 'sent') {
            tickWrap.innerHTML = '<i class="bi bi-check2" title="Enviada"></i>';
        } else if (status === 'failed') {
            tickWrap.innerHTML = '<i class="bi bi-exclamation-circle text-danger" title="Falha"></i>';
        } else {
            tickWrap.innerHTML = '<i class="bi bi-clock text-muted" title="Pendente"></i>';
        }

        // Preenche a aba técnica
        document.getElementById('det-id').textContent = `#${id}`;
        document.getElementById('det-to').textContent = to;
        document.getElementById('det-meta-id').textContent = metaId || 'Não registrado';
        document.getElementById('det-status').innerHTML = `<span class="badge bg-secondary-subtle text-body border text-uppercase">${status}</span>`;
        document.getElementById('det-date').textContent = date;

        try {
            const parsedJson = JSON.parse(responseRaw);
            document.getElementById('det-response').textContent = JSON.stringify(parsedJson, null, 2);

            // Diagnóstico de falha
            if (status === 'failed') {
                const failureMsg = parsedJson.response?.message || parsedJson.message || parsedJson.error || 'Falha no envio da mensagem pela API.';
                document.getElementById('det-diag-failure-alert').classList.remove('d-none');
                document.getElementById('det-diag-failure-text').textContent = failureMsg;
            } else {
                document.getElementById('det-diag-failure-alert').classList.add('d-none');
            }
        } catch (err) {
            document.getElementById('det-response').textContent = responseRaw || '-';
            document.getElementById('det-diag-failure-alert').classList.add('d-none');
        }

        // Abre o modal de imediato!
        if (detailModal) {
            detailModal.show();
        }

        // Tenta sincronizar metadados enriquecidos em background (nome de cliente, etc.)
        fetch(`{{ url('/admin/whatsapp/messages') }}/${id}/detail`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        }).then(res => res.json()).then(data => {
            if (data.ok && data.message) {
                const m = data.message;
                if (m.customer_name) {
                    document.getElementById('bp-phone-contact-name').textContent = m.customer_name;
                    document.getElementById('bp-phone-avatar-text').textContent = m.customer_name.charAt(0).toUpperCase();
                }
                if (m.media_url) {
                    document.getElementById('bp-phone-media-img').src = m.media_url;
                    document.getElementById('bp-phone-media-container').classList.remove('d-none');
                }
                if (m.failure_reason && status === 'failed') {
                    document.getElementById('det-diag-failure-alert').classList.remove('d-none');
                    document.getElementById('det-diag-failure-text').textContent = m.failure_reason;
                }
            }
        }).catch(() => {});
    });

    // Copiar Texto da Mensagem no Smartphone
    document.getElementById('bp-phone-copy-btn')?.addEventListener('click', function () {
        const text = document.getElementById('bp-phone-message-body')?.innerText || '';
        if (text) {
            navigator.clipboard.writeText(text).then(() => {
                if (window.toast) {
                    window.toast.success('Texto copiado com sucesso!');
                } else {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Texto copiado!', showConfirmButton: false, timer: 1800 });
                }
            });
        }
    });

    // =========================================================================
    // REENVIAR MENSAGEM (GLOBAL COM SWEETALERT2)
    // =========================================================================
    async function triggerResend(msgId) {
        if (!msgId) return;

        const result = await Swal.fire({
            title: 'Reenviar mensagem?',
            html: `Deseja realmente reenviar a mensagem <strong>#${msgId}</strong> para a fila de disparo?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Sim, reenviar agora',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
        });

        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`{{ url('/admin/whatsapp/messages') }}/${msgId}/resend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });

            const data = await resp.json().catch(() => ({}));

            if (resp.ok && data.ok) {
                if (detailModal) detailModal.hide();
                await Swal.fire({
                    title: 'Reenvio Solicitado!',
                    text: 'A mensagem foi reenviada para processamento com sucesso.',
                    icon: 'success',
                    confirmButtonColor: '#198754',
                });
                location.reload();
            } else {
                Swal.fire({
                    title: 'Falha no Reenvio',
                    text: data.message || 'Não foi possível reenviar esta mensagem.',
                    icon: 'error',
                });
            }
        } catch (err) {
            Swal.fire({
                title: 'Erro de Conexão',
                text: 'Falha de comunicação ao tentar reenviar a mensagem.',
                icon: 'error',
            });
        }
    }

    document.addEventListener('click', function (e) {
        const resendBtn = e.target.closest('.btn-resend-message');
        if (resendBtn) {
            e.preventDefault();
            triggerResend(resendBtn.dataset.id);
        }
    });

    document.getElementById('btn-modal-resend-direct')?.addEventListener('click', function () {
        if (activeMsgId) triggerResend(activeMsgId);
    });

    // =========================================================================
    // MODAL DE ENVIO MANUAL E TEMPLATES
    // =========================================================================
    const manualModalEl = document.getElementById('modal-manual-send');
    const manualModal = manualModalEl ? new bootstrap.Modal(manualModalEl) : null;
    const editor = document.getElementById('wa-editor');
    const preview = document.getElementById('msg-preview');
    const counter = document.getElementById('msg-char-count');
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
    const previewContactNumber = document.getElementById('preview-contact-number');

    function formatPhoneInput(value) {
        const digits = value.replace(/\D+/g, '').slice(0, 13);
        if (!digits) return '';
        if (digits.length <= 2) return `+${digits}`;
        if (digits.length <= 4) return `+${digits.slice(0, 2)} (${digits.slice(2)}`;
        if (digits.length <= 9) return `+${digits.slice(0, 2)} (${digits.slice(2, 4)}) ${digits.slice(4)}`;
        return `+${digits.slice(0, 2)} (${digits.slice(2, 4)}) ${digits.slice(4, 9)}-${digits.slice(9)}`;
    }

    function insertAtCursor(text) {
        if (!editor) return;
        const start = editor.selectionStart;
        const val = editor.value;
        editor.value = val.substring(0, start) + text + val.substring(editor.selectionEnd);
        editor.selectionStart = editor.selectionEnd = start + text.length;
        editor.focus();
        updatePreview();
    }

    function wrapSelection(before, after) {
        if (!editor) return;
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

    document.querySelectorAll('[data-insert-var]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            insertAtCursor(el.dataset.insertVar);
        });
    });

    document.querySelectorAll('[data-wa-format]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const fmt = btn.dataset.waFormat;
            switch (fmt) {
                case 'bold': wrapSelection('*', '*'); break;
                case 'italic': wrapSelection('_', '_'); break;
                case 'strike': wrapSelection('~', '~'); break;
                case 'mono': wrapSelection('```', '```'); break;
                case 'indent': insertAtCursor('> '); break;
                case 'list': insertAtCursor('1. '); break;
                case 'bullet': insertAtCursor('- '); break;
            }
        });
    });

    manualTo?.addEventListener('input', () => {
        manualTo.value = formatPhoneInput(manualTo.value);
        if (previewContactNumber) previewContactNumber.textContent = manualTo.value || 'Aguardando número...';
    });

    function updatePreview() {
        if (!editor || !preview) return;
        const text = editor.value;
        preview.innerHTML = text.trim().length > 0 ? renderWhatsAppMarkdown(text) : '<em class="text-muted">A mensagem aparecerá aqui em tempo real...</em>';
        if (counter) counter.textContent = `${text.length} caracteres`;
    }

    editor?.addEventListener('input', updatePreview);

    let searchTimer = null;
    custSearchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = custSearchInput.value.trim();

        searchTimer = setTimeout(async () => {
            custSearchSpinner?.classList.remove('d-none');
            try {
                const resp = await fetch(`{{ route('admin.whatsapp.customers.search') }}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                });
                const data = await resp.json();
                const customers = data.customers || [];

                if (customers.length === 0) {
                    custSearchResults.innerHTML = '<div class="p-3 text-muted text-center small">Nenhum cliente encontrado</div>';
                } else {
                    custSearchResults.innerHTML = customers.map((c) => {
                        const phone = c.formatted_whatsapp || c.whatsapp || 'Sem telefone';
                        const inv = c.invoice ? `<span class="badge bg-primary-subtle text-primary border">${c.invoice.number} (${c.invoice.formatted_amount})</span>` : '';
                        return `
                            <div class="p-2 border-bottom wa-cust-item" data-customer='${JSON.stringify(c).replace(/'/g, "&#39;")}' style="cursor:pointer;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-bold text-truncate" style="max-width: 240px;">${c.name}</div>
                                    <div class="small font-monospace text-muted">${phone}</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted">${c.document || c.email || ''}</small>
                                    ${inv}
                                </div>
                            </div>
                        `;
                    }).join('');
                }
                custSearchResults.classList.remove('d-none');
            } catch (err) {
                console.error(err);
            } finally {
                custSearchSpinner?.classList.add('d-none');
            }
        }, 250);
    });

    custSearchResults?.addEventListener('click', (e) => {
        const item = e.target.closest('.wa-cust-item');
        if (!item) return;

        const c = JSON.parse(item.dataset.customer);
        selectedCustId.value = c.id;
        custSearchInput.value = c.name;
        custClearBtn?.classList.remove('d-none');

        custCardName.textContent = c.name;
        custCardMeta.textContent = `${c.formatted_whatsapp || c.whatsapp || 'Sem WhatsApp'} • ${c.document || c.email || ''}`;

        if (c.invoice) {
            custCardInvoice.innerHTML = `
                <span class="badge bg-warning-subtle text-dark border">
                    <i class="bi bi-receipt me-1"></i> Fatura #${c.invoice.number} • ${c.invoice.formatted_amount}
                </span>
            `;
        } else {
            custCardInvoice.innerHTML = '<span class="badge bg-secondary-subtle text-muted">Sem fatura pendente</span>';
        }

        custCard?.classList.remove('d-none');

        if (c.whatsapp) {
            manualTo.value = formatPhoneInput(c.whatsapp);
            if (previewContactNumber) previewContactNumber.textContent = manualTo.value;
        }
        if (previewContactName) previewContactName.textContent = c.name;

        if (templateSelect?.value) {
            renderCurrentTemplate();
        }

        custSearchResults.classList.add('d-none');
    });

    custClearBtn?.addEventListener('click', () => {
        selectedCustId.value = '';
        custSearchInput.value = '';
        custClearBtn.classList.add('d-none');
        custCard.classList.add('d-none');
        if (previewContactName) previewContactName.textContent = 'Destinatário';
        if (templateSelect?.value) renderCurrentTemplate();
    });

    async function renderCurrentTemplate() {
        const tplId = templateSelect?.value;
        if (!tplId) return;

        const custId = selectedCustId?.value || null;

        try {
            const resp = await fetch('{{ route("admin.whatsapp.render-template") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ template_id: tplId, customer_id: custId }),
            });
            const data = await resp.json();
            if (data.success && data.rendered_text && editor) {
                editor.value = data.rendered_text;
                updatePreview();
            }
        } catch (err) {
            console.error(err);
        }
    }

    templateSelect?.addEventListener('change', renderCurrentTemplate);

    document.getElementById('btn-manual-send')?.addEventListener('click', () => {
        if (manualModal) manualModal.show();
    });

    document.getElementById('btn-reset-form')?.addEventListener('click', () => {
        if (selectedCustId) selectedCustId.value = '';
        if (custSearchInput) custSearchInput.value = '';
        custClearBtn?.classList.add('d-none');
        custCard?.classList.add('d-none');
        if (templateSelect) templateSelect.value = '';
        if (manualTo) manualTo.value = '';
        if (editor) editor.value = '';
        if (previewContactNumber) previewContactNumber.textContent = 'Aguardando número...';
        updatePreview();
    });

    document.getElementById('btn-confirm-send-manual')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-confirm-send-manual');
        const rawTo = manualTo?.value.trim() || '';
        const cleanTo = rawTo.replace(/\D+/g, '');
        const message = editor?.value.trim() || '';
        const custId = selectedCustId?.value ? parseInt(selectedCustId.value) : null;
        const tplId = templateSelect?.value ? parseInt(templateSelect.value) : null;

        if (!cleanTo || cleanTo.length < 10) {
            window.toast ? window.toast.error('Informe um número de telefone com DDD válido.') : alert('Número inválido');
            manualTo?.focus();
            return;
        }

        if (!tplId) {
            window.toast ? window.toast.error('Selecione um template de mensagem obrigatório.') : alert('Selecione um template');
            templateSelect?.focus();
            return;
        }

        if (!message) {
            window.toast ? window.toast.error('O corpo da mensagem não pode ficar vazio.') : alert('Mensagem vazia');
            editor?.focus();
            return;
        }

        const confirmSend = await Swal.fire({
            title: 'Confirmar envio manual?',
            html: `Enviar mensagem via WhatsApp para o número <strong>${rawTo}</strong>?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-whatsapp me-1"></i> Sim, enviar agora',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
        });

        if (!confirmSend.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

        try {
            const resp = await fetch('{{ route("admin.whatsapp.send-manual") }}', {
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
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-whatsapp me-1"></i> Enviar Mensagem';

            if (resp.ok && data.ok) {
                if (manualModal) manualModal.hide();
                await Swal.fire({
                    title: 'Mensagem Enviada!',
                    text: data.message || 'Disparo concluído com sucesso.',
                    icon: 'success',
                    confirmButtonColor: '#198754',
                });
                location.reload();
            } else {
                Swal.fire({
                    title: 'Não foi possível enviar',
                    text: data.message || 'Erro ao enviar mensagem WhatsApp.',
                    icon: 'error',
                });
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-whatsapp me-1"></i> Enviar Mensagem';
            Swal.fire({
                title: 'Erro de Conexão',
                text: 'Falha ao se comunicar com o servidor.',
                icon: 'error',
            });
        }
    });

    updatePreview();
});
</script>
@endpush
