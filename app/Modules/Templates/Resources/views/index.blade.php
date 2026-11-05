@extends('admin.layouts.app')

@section('title', 'Templates de Mensagem WhatsApp')

@push('head')
<style>
.templates-index-page .templates-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
}
.templates-filter-bar {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 16px;
    padding: 1.25rem;
}
.templates-table th.col-actions,
.templates-table td.col-actions {
    width: 140px !important;
    min-width: 135px !important;
    max-width: 150px !important;
    padding-left: 4px !important;
    padding-right: 4px !important;
    text-align: right !important;
    white-space: nowrap !important;
}
.templates-table th.col-template {
    min-width: 160px;
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

/* Tela interna do smartphone */
.wa-phone-screen {
    background: #0b141a;
    border-radius: 38px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    height: 650px;
    max-height: 84vh;
}

/* Dynamic Island / Notch no topo */
.wa-phone-notch {
    position: absolute;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 20px;
    background: #000000;
    border-radius: 20px;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 8px;
}
.wa-notch-cam {
    width: 9px;
    height: 9px;
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
    font-size: 1.1rem;
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

/* Balão de Mensagem Recebida do WhatsApp */
.wa-bubble-received {
    align-self: flex-start;
    background: #202c33;
    color: #e9edef;
    border-radius: 0 10px 10px 10px;
    padding: 8px 12px 6px 12px;
    max-width: 88%;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    font-size: 0.86rem;
    line-height: 1.45;
    word-break: break-word;
}
/* Rabicho clássico do WhatsApp */
.wa-bubble-received::before {
    content: '';
    position: absolute;
    top: 0;
    left: -8px;
    width: 0;
    height: 0;
    border-top: 10px solid #202c33;
    border-left: 8px solid transparent;
}

.wa-bubble-received p {
    margin-bottom: 0.4rem;
}
.wa-bubble-received p:last-child {
    margin-bottom: 0;
}
.wa-bubble-received strong {
    font-weight: 600;
    color: #ffffff;
}
.wa-bubble-received em {
    font-style: italic;
    color: #dfe5e7;
}
.wa-bubble-received a {
    color: #53bdeb;
    text-decoration: underline;
}
.wa-bubble-received code {
    background: #111b21;
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
    color: #53bdeb;
    font-size: 0.85rem;
}

/* Anexo de Mídia (QR Code) */
.wa-media-card {
    background: #111b21;
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
</style>
@endpush

@section('content')
<div class="container-fluid templates-index-page">
    <x-ui.page-header
        title="Templates de Mensagem WhatsApp"
        subtitle="Todos os modelos disparados pelo sistema (cobranças, confirmações, pix, contratos e notificações). Todas as mensagens do sistema devem possuir um template válido."
        eyebrow="Mensageria e Automação">
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.templates.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Novo template
                </a>
            </div>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Ativos</span>
                <span class="dashboard-note__value text-success">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total listado</span>
                <span class="dashboard-note__value">{{ $templates->count() }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total de templates" :value="$stats['total'] ?? 0" icon="bi-chat-square-text" color="primary">
                <x-slot:meta>Biblioteca global de mensagens.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Templates Ativos" :value="$stats['active'] ?? 0" icon="bi-check-circle" color="success">
                <x-slot:meta>Liberados para envio automático e manual.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Régua de Cobrança" :value="$stats['scheduled'] ?? 0" icon="bi-clock-history" color="info">
                <x-slot:meta>Vinculados a janelas de antecedência e atraso.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Padrão do Sistema" :value="$stats['system'] ?? 0" icon="bi-shield-check" color="secondary">
                <x-slot:meta>Modelos oficiais mantidos pelo sistema.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    {{-- Barra de Filtros e Busca --}}
    <div class="templates-filter-bar mb-4 shadow-sm">
        <form method="GET" action="{{ route('admin.templates.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-muted">Buscar por nome, slug ou termo</label>
                <div class="input-group">
                    <span class="input-group-text bg-body-tertiary"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Ex: lembrete, atraso, contrato, pix..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">Categoria</label>
                <select name="category" class="form-select">
                    <option value="all">Todas as categorias</option>
                    @foreach($allCategories as $catKey => $catLabel)
                        <option value="{{ $catKey }}" @selected(request('category') === $catKey)>{{ $catLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted">Disparo</label>
                <select name="schedule" class="form-select">
                    <option value="all">Todos os tipos</option>
                    <option value="scheduled" @selected(request('schedule') === 'scheduled')>Agendado (Régua)</option>
                    <option value="manual" @selected(request('schedule') === 'manual')>Manual / Evento</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="all">Todos</option>
                    <option value="active" @selected(request('status') === 'active')>Ativos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
                </select>
            </div>
            <div class="col-6 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" title="Filtrar">
                    <i class="bi bi-funnel"></i>
                </button>
                @if(request()->hasAny(['search', 'category', 'schedule', 'status']))
                    <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <x-ui.premium-card title="Biblioteca de templates" :subtitle="$templates->count() . ' template(s) encontrado(s).'">
        <div class="templates-table p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-templates" data-export-title="Templates de WhatsApp" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all col-template" data-priority="1">Template</th>
                        <th class="min-tablet" style="width: 140px;">Categoria</th>
                        <th class="desktop" style="width: 130px;">Agendamento / Régua</th>
                        <th class="min-tablet" data-priority="2" style="width: 90px;">Status</th>
                        <th class="desktop" style="width: 125px;">Atualizado em</th>
                        <th class="all text-end no-export col-actions" data-priority="1">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                        @php
                            $badgeClass = $t->categoryBadgeClass();
                            $catLabel = $t->categoryLabel();
                        @endphp
                        <tr id="row-{{ $t->id }}">
                            <td>
                                <div class="d-flex flex-column min-w-0" style="max-width: 100%;">
                                    <div class="fw-semibold text-body text-truncate" title="{{ $t->name }}">
                                        {{ $t->name }}
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                        <span class="small font-monospace text-muted text-truncate" style="font-size: 0.72rem;">{{ $t->slug }}</span>
                                        @if($t->is_system)
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0 px-1 flex-shrink-0" style="font-size: 0.65rem;" title="Template mantido pelo sistema">
                                                <i class="bi bi-shield-lock me-1"></i>Sistema
                                            </span>
                                        @endif
                                        @if($t->is_default)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1 flex-shrink-0" style="font-size: 0.65rem;" title="Padrão ativo">
                                                <i class="bi bi-star-fill me-1"></i>Padrão
                                            </span>
                                        @endif
                                        <span class="d-inline d-md-none badge bg-{{ $t->active ? 'success' : 'secondary' }}-subtle text-{{ $t->active ? 'success' : 'secondary' }} border border-{{ $t->active ? 'success' : 'secondary' }}-subtle py-0 px-1 flex-shrink-0" style="font-size: 0.62rem;">
                                            <i class="bi {{ $t->active ? 'bi-check-circle-fill' : 'bi-dash-circle' }} me-1"></i>{{ $t->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} border border-{{ $badgeClass }}-subtle">
                                    {{ $catLabel }}
                                </span>
                            </td>
                            <td>
                                @if($t->schedule_offset)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace">
                                        <i class="bi bi-alarm me-1"></i>{{ $t->schedule_offset }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">Manual / Evento</span>
                                @endif
                            </td>
                            <td>
                                <x-ui.status-badge :status="$t->active ? 'active' : 'inactive'" :label="$t->active ? 'Ativo' : 'Inativo'" />
                            </td>
                            <td>{{ $t->updated_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end no-export col-actions" style="width: 140px; min-width: 135px; max-width: 150px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-info p-1 px-2 js-quick-preview"
                                            data-name="{{ $t->name }}"
                                            data-category="{{ $catLabel }}"
                                            data-content="{{ $t->content_text ?: strip_tags($t->content_html) }}"
                                            data-html="{{ $t->content_html }}"
                                            title="Visualizar no WhatsApp">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="{{ route('admin.templates.edit', $t->id) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Editar template">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2 js-clone-template" data-id="{{ $t->id }}" title="Duplicar template">
                                        <i class="bi bi-copy"></i>
                                    </button>
                                    @if(! $t->locked)
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 js-delete-template" data-id="{{ $t->id }}" data-name="{{ $t->name }}" title="Excluir template">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" disabled title="Template bloqueado contra exclusão">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-ui.empty-state icon="bi-chat-square-text" title="Nenhum template encontrado" message="Crie novos modelos para garantir que nenhuma mensagem seja enviada sem um template válido.">
                                    <x-slot:actions>
                                        <a href="{{ route('admin.templates.create') }}" class="btn btn-primary">
                                            <i class="bi bi-plus-lg me-1"></i> Criar template
                                        </a>
                                    </x-slot:actions>
                                </x-ui.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.premium-card>
</div>

{{-- Modal de Preview Rápido Estilo Smartphone WhatsApp --}}
<div class="modal fade" id="modalQuickPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 bg-transparent shadow-none">
            <div class="wa-phone-wrapper">
                <div class="wa-phone-screen">
                    <!-- Dynamic Island / Notch no topo -->
                    <div class="wa-phone-notch">
                        <div class="wa-notch-cam"></div>
                        <div class="wa-notch-sensor"></div>
                    </div>

                    <!-- Barra de Status do Sistema -->
                    <div class="wa-phone-status-bar">
                        <span id="wa-phone-status-time">{{ now()->format('H:i') }}</span>
                        <div class="wa-status-icons">
                            <i class="bi bi-reception-4"></i>
                            <i class="bi bi-wifi"></i>
                            <i class="bi bi-battery-full"></i>
                        </div>
                    </div>

                    <!-- Header do WhatsApp -->
                    <div class="wa-chat-header">
                        <i class="bi bi-arrow-left wa-header-back-btn" data-bs-dismiss="modal" title="Voltar"></i>
                        <div class="wa-header-avatar">
                            <i class="bi bi-whatsapp"></i>
                            <span class="wa-verified-badge"><i class="bi bi-check"></i></span>
                        </div>
                        <div class="wa-header-info">
                            <div class="wa-header-name" id="wa-modal-name">Nome do Template</div>
                            <div class="wa-header-status">
                                <span class="badge-online-dot"></span> online
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

                    <!-- Área de Conversa com Wallpaper Oficial do WhatsApp -->
                    <div class="wa-chat-body">
                        <!-- Pílula de Data -->
                        <div class="wa-date-pill">HOJE</div>

                        <!-- Selo de Criptografia de Ponta a Ponta -->
                        <div class="wa-encryption-pill">
                            <i class="bi bi-lock-fill me-1 text-warning"></i> As mensagens são protegidas com a criptografia de ponta a ponta. Ninguém fora desta conversa pode ler ou ouvir o conteúdo.
                        </div>

                        <!-- Balão de Mensagem Recebida do WhatsApp -->
                        <div class="wa-bubble-received">
                            <div id="wa-modal-body">Texto da mensagem renderizado aqui...</div>
                            <div class="wa-bubble-meta">
                                <span id="wa-modal-time">{{ now()->format('H:i') }}</span>
                                <i class="bi bi-check2-all"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Barra Inferior de Digitação do WhatsApp -->
                    <div class="wa-chat-footer">
                        <div class="wa-input-pill">
                            <i class="bi bi-emoji-smile" title="Emojis"></i>
                            <span class="wa-input-text">Mensagem</span>
                            <i class="bi bi-paperclip" title="Anexar"></i>
                            <i class="bi bi-camera" title="Câmera"></i>
                        </div>
                        <div class="wa-mic-btn" title="Mensagem de voz">
                            <i class="bi bi-mic-fill"></i>
                        </div>
                    </div>

                    <!-- Barra Home do Smartphone -->
                    <div class="wa-phone-home-bar"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '';
    const previewModalEl = document.getElementById('modalQuickPreview');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;

    // Dicionário de variáveis de exemplo para simulação fidedigna com dados realistas
    const sampleContext = {
        '{nome}': 'João Carlos da Silva',
        '{primeiro_nome}': 'João',
        '{email}': 'joao.silva@exemplo.com.br',
        '{telefone}': '(11) 98765-4321',
        '{documento}': '123.456.789-00',
        '{numero_fatura}': 'INV-2026-0891',
        '{valor}': 'R$ 289,90',
        '{valor_original}': 'R$ 289,90',
        '{valor_atualizado}': 'R$ 295,40',
        '{vencimento}': '{{ now()->addDays(2)->format('d/m/Y') }}',
        '{dias_atraso}': '3',
        '{descricao}': 'Hospedagem Cloud Dedicada + Backup Diário',
        '{link_pagamento}': 'https://pay.exemplo.com/c/inv-2026-0891',
        '{boleto_url}': 'https://pay.exemplo.com/boleto/20260891.pdf',
        '{linha_digitavel}': '34191.79001 01043.510047 91020.150008 8 98450000028990',
        '{metodo_pagamento}': 'PIX Dinâmico / Boleto',
        '{forma_pagamento}': 'PIX',
        '{data_pagamento}': '{{ now()->format('d/m/Y H:i') }}',
        '{pix_copia_cola}': '00020101021226580014br.gov.bcb.pix2536pix.exemplo.com/qr/v2/99c89e5204000053039865802BR5925MINHA EMPRESA TECNOLOGIA6009SAO PAULO62070503***6304E8A2',
        '{qrcode_url}': 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data=00020101021226580014br.gov.bcb.pix',
        '{qrcode_pix}': 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data=00020101021226580014br.gov.bcb.pix',
        '{qrcode_base64}': 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        '{qrcode_img}': '<div class="wa-media-card"><img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=00020101021226580014br.gov.bcb.pix" alt="QR Code Pix" /><div class="wa-media-badge"><i class="bi bi-qr-code me-1"></i>QR Code Pix (Aponte a câmera)</div></div>',
        '{qrcode_instrucao}': 'Também enviamos a imagem do QR Code Pix para agilizar o pagamento.',
        '{nome_servico}': 'Plano Corporativo Plus',
        '{servico_principal}': 'Plano Corporativo Plus',
        '{servicos}': 'Plano Corporativo Plus, IP Dedicado',
        '{servicos_ativos}': 'Plano Corporativo Plus',
        '{status_servico}': 'Ativo',
        '{parcela_atual}': '2',
        '{parcelas_total}': '12',
        '{resumo_parcelas}': '2/12',
        '{proximo_vencimento}': '{{ now()->addMonth()->format('d/m/Y') }}',
        '{contrato_numero}': 'CTR-2026/044',
        '{contrato_titulo}': 'Prestação de Serviços de TI',
        '{link_contrato}': 'https://sistema.exemplo.com/contratos/ctr-2026-044',
        '{link_assinatura}': 'https://sistema.exemplo.com/assinar/ctr-2026-044?token=abc123xyz',
        '{data_assinatura}': '{{ now()->format('d/m/Y H:i') }}',
        '{titulo}': 'Manutenção Programada na Infraestrutura',
        '{mensagem}': 'Informamos que realizaremos melhorias no datacenter no próximo domingo.',
        '{empresa}': '{{ config('app.name', 'CW Solutions') }}',
        '{whatsapp_suporte}': '(11) 98765-4321',
        '{email_suporte}': 'suporte@exemplo.com.br',
        '{telefone_suporte}': '(11) 3344-5566',
        '{whmcs_cliente_id}': '1042',
        '{whmcs_fatura_id}': '8910',
    };

    function formatWhatsAppBubble(text) {
        if (!text) return '';
        let t = text;

        // Substituição de variáveis
        for (const [key, val] of Object.entries(sampleContext)) {
            t = t.split(key).join(val);
        }

        // Se não houver código Pix no texto, remove bloco Pix vazio
        t = t.replace(/\*Pix Copia e Cola:?\*[\r\n]+(```[\r\n]*```)?[\r\n]*/gi, '');

        // Simulação visual de anexo de mídia do QR Code no WhatsApp
        t = t.replace(/(?:📱\s*)?\*\[QR Code Pix enviado em anexo\]\*/gi, 
            '<div class="wa-media-card">' +
            '<img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=00020101021226580014br.gov.bcb.pix" alt="QR Code Pix Anexo" />' +
            '<div class="wa-media-badge"><i class="bi bi-paperclip me-1"></i>Imagem QR Code Pix (Mídia Anexa)</div>' +
            '</div>'
        );

        // Formatação do WhatsApp: negrito, itálico, tachado, código e links
        return t
            .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
            .replace(/_(.*?)_/g, '<em>$1</em>')
            .replace(/~(.*?)~/g, '<del>$1</del>')
            .replace(/```(.*?)```/gs, '<code>$1</code>')
            .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
            .replace(/\n/g, '<br>');
    }

    // Preview Rápido via delegação (funciona em todas as páginas e gavetas do DataTables)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-quick-preview');
        if (!btn) return;

        const name = btn.dataset.name || 'Template WhatsApp';
        const rawContent = btn.dataset.content || '';
        const htmlContent = btn.dataset.html || '';

        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const phoneTimeEl = document.getElementById('wa-phone-status-time');
        if (phoneTimeEl) phoneTimeEl.textContent = timeStr;

        const bubbleTimeEl = document.getElementById('wa-modal-time');
        if (bubbleTimeEl) bubbleTimeEl.textContent = timeStr;

        document.getElementById('wa-modal-name').textContent = name;
        document.getElementById('wa-modal-body').innerHTML = formatWhatsAppBubble(rawContent || htmlContent);

        if (previewModal) {
            try {
                previewModal.show();
                return;
            } catch (err) {
                console.warn('[Modal Preview fallback]', err);
            }
        }
        if (previewModalEl) {
            previewModalEl.classList.add('show');
            previewModalEl.style.display = 'block';
            document.body.classList.add('modal-open');
            if (!document.querySelector('.modal-backdrop')) {
                const bd = document.createElement('div');
                bd.className = 'modal-backdrop fade show';
                document.body.appendChild(bd);
            }
        }
    });

    // Fechar modal de preview com segurança
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-bs-dismiss="modal"]') || e.target.classList.contains('modal-backdrop') || e.target === previewModalEl) {
            if (previewModal) {
                try { previewModal.hide(); } catch (err) {}
            }
            if (previewModalEl) {
                previewModalEl.classList.remove('show');
                previewModalEl.style.display = 'none';
                document.body.classList.remove('modal-open');
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            }
        }
    });

    // Duplicar Template via AJAX com SweetAlert2 via delegação
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-clone-template');
        if (!btn) return;

        const id = btn.dataset.id;
        const res = await Swal.fire({
            icon: 'question',
            title: 'Duplicar template?',
            text: 'Será criada uma cópia deste template pronta para edição.',
            showCancelButton: true,
            confirmButtonText: 'Sim, duplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
        });

        if (!res.isConfirmed) return;

        try {
            const resp = await fetch(`{{ url('/admin/templates') }}/${id}/clonar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
            });
            const data = await resp.json();

            if (data.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Template duplicado!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false,
                });
                setTimeout(() => {
                    window.location.href = data.redirect || window.location.href;
                }, 1200);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao duplicar',
                    text: data.error || 'Não foi possível duplicar o template.',
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Falha de comunicação',
                text: 'Ocorreu um erro ao processar a requisição.',
            });
        }
    });

    // Exclusão com SweetAlert2 e sincronização DataTables via delegação
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-delete-template');
        if (!btn) return;

        const id = btn.dataset.id;
        const name = btn.dataset.name || 'este template';

        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Excluir template?',
            html: `Você está prestes a remover o template <strong>${name}</strong>.<br><small class="text-muted">Avisos e disparos futuros que utilizam este modelo não serão enviados sem template válido.</small>`,
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });

        if (!confirm.isConfirmed) return;

        try {
            const r = await fetch(`{{ url('/admin/templates') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
            });
            const res = await r.json();

            if (res.ok) {
                if (window.jQuery && window.jQuery.fn.DataTable && window.jQuery.fn.DataTable.isDataTable('#table-admin-templates')) {
                    window.jQuery('#table-admin-templates').DataTable().row('#row-' + id).remove().draw(false);
                } else {
                    const row = document.getElementById(`row-${id}`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Excluído!',
                    text: res.message || 'Template excluído com sucesso.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Não foi possível excluir',
                    text: res.error || 'Erro ao excluir o template.',
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Erro de conexão',
                text: 'Não foi possível se comunicar com o servidor.',
            });
        }
    });
});
</script>
@endpush
