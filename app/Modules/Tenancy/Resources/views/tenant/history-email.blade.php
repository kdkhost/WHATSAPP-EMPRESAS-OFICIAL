@extends('tenancy::tenant.layout')

@section('title', 'Histórico de E-mails')

@push('head')
<style>
/* ==========================================================================
   ESTILOS: HISTÓRICO DE E-MAILS & SIMULADOR DE CLIENTE DE E-MAIL
   ========================================================================== */

/* Cards de estatísticas superiores */
.stat-card-email {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}
.stat-card-email:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}
.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}

/* Tabela um por linha */
.table-email-history {
    margin-bottom: 0;
}
.table-email-history th {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    color: #6c757d;
    background-color: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color);
    padding: 12px 16px;
    white-space: nowrap;
}
.table-email-history td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--bs-border-color);
    white-space: nowrap;
}
.table-email-row {
    transition: background-color 0.15s ease;
}
.table-email-row:hover {
    background-color: var(--bs-secondary-bg);
}

.email-subject-clickable {
    cursor: pointer;
    transition: color 0.15s ease;
}
.email-subject-clickable:hover {
    color: var(--bs-primary) !important;
}

/* ==========================================================================
   SIMULADOR DE CLIENTE DE E-MAIL (MODAL MODERNO)
   ========================================================================== */
.email-client-window {
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.12);
}

/* Top bar simulando sistema operacional / cliente de e-mail */
.email-window-topbar {
    background: #f1f3f5;
    padding: 10px 16px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.email-window-controls {
    display: flex;
    align-items: center;
    gap: 7px;
}
.email-window-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    display: inline-block;
}
.email-window-dot--red { background: #ff5f56; border: 1px solid #e0443e; }
.email-window-dot--yellow { background: #ffbd2e; border: 1px solid #dea123; }
.email-window-dot--green { background: #27c93f; border: 1px solid #1aab29; }

.email-window-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Cabeçalho do e-mail com remetente, destinatário, assunto */
.email-envelope-header {
    background: #f8f9fa;
    padding: 18px 24px;
    border-bottom: 1px solid #e9ecef;
}
.email-subject-heading {
    font-size: 1.25rem;
    font-weight: 700;
    color: #212529;
    margin-bottom: 14px;
    line-height: 1.3;
}
.email-meta-grid {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.email-sender-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d6efd, #0a58ca);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
}
.email-meta-details {
    flex-grow: 1;
    min-width: 0;
}
.email-meta-line {
    font-size: 0.86rem;
    color: #495057;
    margin-bottom: 3px;
    line-height: 1.4;
}
.email-meta-label {
    font-weight: 600;
    color: #6c757d;
    min-width: 48px;
    display: inline-block;
}

/* Viewport do conteúdo HTML */
.email-viewport-container {
    padding: 20px 24px;
    background: #ffffff;
    min-height: 420px;
}
.email-iframe-viewer {
    width: 100%;
    height: 520px;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.03);
}

/* Modo escuro (quando a aplicação estiver em dark mode) */
[data-bs-theme="dark"] .email-client-window {
    background: #1e2227;
    border-color: rgba(255, 255, 255, 0.12);
}
[data-bs-theme="dark"] .email-window-topbar {
    background: #171a1e;
    border-bottom-color: rgba(255, 255, 255, 0.08);
}
[data-bs-theme="dark"] .email-window-title {
    color: #adb5bd;
}
[data-bs-theme="dark"] .email-envelope-header {
    background: #22262c;
    border-bottom-color: rgba(255, 255, 255, 0.08);
}
[data-bs-theme="dark"] .email-subject-heading {
    color: #f8f9fa;
}
[data-bs-theme="dark"] .email-meta-line {
    color: #ced4da;
}
[data-bs-theme="dark"] .email-meta-label {
    color: #adb5bd;
}
[data-bs-theme="dark"] .email-viewport-container {
    background: #1e2227;
}

/* Ícone de rotação suave no atualizar */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spin-anim {
    animation: spin 0.8s linear infinite;
}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

    {{-- CABEÇALHO DA PÁGINA --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
                    <i class="bi bi-envelope-check me-1"></i> Auditoria de Envios
                </span>
                <span class="text-muted small">• E-mail Billing</span>
            </div>
            <h4 class="fw-bold mb-1">Histórico de Disparos de E-mail</h4>
            <p class="text-muted mb-0">Rastreamento linha a linha de todas as cobranças e notificações enviadas aos seus clientes por e-mail.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3" id="btnRefreshHistory">
                <i class="bi bi-arrow-clockwise me-1" id="iconRefresh"></i> Atualizar
            </button>
            <a href="{{ route('tenant.dispatches') }}" class="btn btn-primary rounded-pill px-3">
                <i class="bi bi-send me-1"></i> Envios Agendados
            </a>
        </div>
    </div>

    {{-- CARDS DE ESTATÍSTICAS SUPERIORES --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card-email shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="stat-card-icon bg-primary-subtle text-primary">
                        <i class="bi bi-envelope-paper"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-body mb-0">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</div>
                        <div class="text-muted small">Total de Disparos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card-email shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="stat-card-icon bg-success-subtle text-success">
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-body mb-0">{{ number_format($stats['sent'] ?? 0, 0, ',', '.') }}</div>
                        <div class="text-muted small">Entregues com Sucesso</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card-email shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="stat-card-icon bg-warning-subtle text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-body mb-0">{{ number_format($stats['queued'] ?? 0, 0, ',', '.') }}</div>
                        <div class="text-muted small">Aguardando na Fila</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card-email shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="stat-card-icon bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-body mb-0">{{ number_format($stats['failed'] ?? 0, 0, ',', '.') }}</div>
                        <div class="text-muted small">Falhas ou Rejeições</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CARD DA TABELA DE HISTÓRICO COM FILTROS --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        {{-- Barra de Filtros --}}
        <div class="card-header bg-body-tertiary border-bottom py-3 px-4">
            <form method="GET" action="{{ route('tenant.history.email') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Status da Mensagem</label>
                    <select name="status" class="form-select form-select-sm rounded-3">
                        <option value="">Todos os status</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Enviado</option>
                        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Entregue</option>
                        <option value="opened" {{ request('status') === 'opened' ? 'selected' : '' }}>Aberto / Lido</option>
                        <option value="clicked" {{ request('status') === 'clicked' ? 'selected' : '' }}>Link Clicado</option>
                        <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Na fila</option>
                        <option value="bounced" {{ request('status') === 'bounced' ? 'selected' : '' }}>Rejeitado (Bounce)</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Falha no envio</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Data Inicial</label>
                    <input type="date" name="date_from" class="form-control form-control-sm rounded-3" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Data Final</label>
                    <input type="date" name="date_to" class="form-control form-control-sm rounded-3" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Buscar Destinatário / Assunto</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body rounded-start-3"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm rounded-end-3" placeholder="E-mail ou assunto..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                    @if(request()->hasAny(['status', 'date_from', 'date_to', 'search']))
                        <a href="{{ route('tenant.history.email') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Limpar filtros">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Corpo da Tabela: Um por Linha com Botão do Olho --}}
        <div class="card-body p-0">
            @if($messages->isEmpty())
                <div class="text-center py-5">
                    <div class="display-6 text-muted mb-3">
                        <i class="bi bi-envelope-x"></i>
                    </div>
                    <h5 class="fw-bold text-body">Nenhum e-mail registrado</h5>
                    <p class="text-muted small mb-3">Nenhum disparo de e-mail corresponde aos filtros selecionados ou ainda não há envios registrados.</p>
                    @if(request()->hasAny(['status', 'date_from', 'date_to', 'search']))
                        <a href="{{ route('tenant.history.email') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar filtros ativos
                        </a>
                    @endif
                </div>
            @else
                <div>
                    <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-history-email" style="width: 100% !important;" data-export-title="Histórico de E-mails">
                        <thead>
                            <tr>
                                <th class="all" style="width: 70px;">#ID</th>
                                <th class="all">Destinatário / Cliente</th>
                                <th class="desktop">Assunto do E-mail</th>
                                <th class="desktop">Origem / Fatura</th>
                                <th class="min-tablet">Status</th>
                                <th class="min-tablet">Data de Envio</th>
                                <th class="all text-end no-export" data-priority="1" data-orderable="false" style="width: 80px; min-width: 70px; max-width: 90px; white-space: nowrap;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($messages as $msg)
                                @php
                                    $inv = $msg->dispatch?->invoice;
                                    $cust = $inv?->customer;
                                    $custName = $cust?->name;
                                    
                                    // Mapeamento de status amigável
                                    $statusMap = [
                                        'queued'    => ['warning', 'Na fila', 'bi-clock-history'],
                                        'sent'      => ['info', 'Enviado', 'bi-send-check'],
                                        'delivered' => ['success', 'Entregue', 'bi-check2-all'],
                                        'opened'    => ['primary', 'Aberto / Lido', 'bi-envelope-open'],
                                        'clicked'   => ['primary', 'Link Clicado', 'bi-cursor-fill'],
                                        'bounced'   => ['danger', 'Rejeitado', 'bi-exclamation-triangle'],
                                        'failed'    => ['danger', 'Falha', 'bi-x-circle'],
                                    ];
                                    list($badgeColor, $badgeLabel, $badgeIcon) = $statusMap[$msg->status] ?? ['secondary', ucfirst($msg->status), 'bi-envelope'];

                                    // Nome do remetente
                                    $senderName = $tenant->emailConfig->from_name ?? $tenant->name ?? 'Minha Empresa';
                                    $senderEmail = $tenant->emailConfig->from_email ?? 'financeiro@' . request()->getHost();
                                @endphp
                                <tr class="table-email-row">
                                    {{-- ID --}}
                                    <td class="text-muted small font-monospace">
                                        #{{ $msg->id }}
                                    </td>

                                    {{-- Destinatário / Cliente --}}
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-body-secondary d-flex align-items-center justify-content-center text-secondary small fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">
                                                {{ strtoupper(substr($custName ?: $msg->to, 0, 1)) }}
                                            </div>
                                            <div class="min-w-0" style="line-height: 1.25;">
                                                @if($custName)
                                                    <div class="fw-bold text-body text-truncate" style="max-width: 220px;">{{ $custName }}</div>
                                                    <div class="text-muted small font-monospace text-truncate" style="max-width: 220px; font-size: 0.78rem;">
                                                        <i class="bi bi-envelope-at me-1"></i>{{ $msg->to }}
                                                    </div>
                                                @else
                                                    <div class="fw-semibold text-body font-monospace text-truncate" style="max-width: 240px;">
                                                        <i class="bi bi-envelope-at text-muted me-1"></i>{{ $msg->to }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Assunto --}}
                                    <td class="email-subject-clickable js-trigger-email-preview"
                                        data-id="{{ $msg->id }}"
                                        data-to="{{ $msg->to }}"
                                        data-customer="{{ $custName ?? 'Cliente' }}"
                                        data-sender-name="{{ $senderName }}"
                                        data-sender-email="{{ $senderEmail }}"
                                        data-subject="{{ $msg->subject }}"
                                        data-status="{{ $msg->status }}"
                                        data-status-label="{{ $badgeLabel }}"
                                        data-status-color="{{ $badgeColor }}"
                                        data-error="{{ $msg->error }}"
                                        data-date="{{ $msg->created_at->format('d/m/Y H:i:s') }}"
                                        data-date-human="{{ $msg->created_at->diffForHumans() }}"
                                        data-invoice="{{ $inv ? 'Fatura #' . $inv->number : '' }}"
                                        data-body="{{ base64_encode($msg->body_html ?? '') }}"
                                        title="Clique para visualizar o e-mail completo">
                                        <div class="d-flex align-items-center gap-1 text-truncate" style="max-width: 340px;">
                                            <i class="bi bi-envelope text-primary small me-1"></i>
                                            <span class="fw-semibold text-body text-truncate">{{ $msg->subject ?: '(Sem assunto)' }}</span>
                                        </div>
                                    </td>

                                    {{-- Fatura / Origem --}}
                                    <td>
                                        @if($inv)
                                            <a href="{{ route('tenant.invoices') }}?search={{ $inv->number }}" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none" title="Ver detalhes da Fatura #{{ $inv->number }}">
                                                <i class="bi bi-receipt me-1"></i>Fatura #{{ $inv->number }}
                                            </a>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <i class="bi bi-send me-1"></i>Envio Direto
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        <span class="badge text-bg-{{ $badgeColor }} d-inline-flex align-items-center gap-1">
                                            <i class="bi {{ $badgeIcon }}"></i> {{ $badgeLabel }}
                                        </span>
                                        @if($msg->status === 'failed' && $msg->error)
                                            <i class="bi bi-info-circle text-danger ms-1" 
                                               role="button" 
                                               tabindex="0"
                                               data-bs-toggle="tooltip" 
                                               data-bs-title="{{ Str::limit($msg->error, 120) }}"></i>
                                        @endif
                                    </td>

                                    {{-- Data --}}
                                    <td class="text-muted small">
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="bi bi-calendar3 text-muted"></i>
                                            <span>{{ $msg->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                    </td>

                                    {{-- Ação: Botão do Olho --}}
                                    <td class="all text-end no-export" style="width: 80px; min-width: 70px; max-width: 90px; white-space: nowrap;">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary p-1 px-2 js-trigger-email-preview"
                                                    data-id="{{ $msg->id }}"
                                                    data-to="{{ $msg->to }}"
                                                    data-customer="{{ $custName ?? 'Cliente' }}"
                                                    data-sender-name="{{ $senderName }}"
                                                    data-sender-email="{{ $senderEmail }}"
                                                    data-subject="{{ $msg->subject }}"
                                                    data-status="{{ $msg->status }}"
                                                    data-status-label="{{ $badgeLabel }}"
                                                    data-status-color="{{ $badgeColor }}"
                                                    data-error="{{ $msg->error }}"
                                                    data-date="{{ $msg->created_at->format('d/m/Y H:i:s') }}"
                                                    data-date-human="{{ $msg->created_at->diffForHumans() }}"
                                                    data-invoice="{{ $inv ? 'Fatura #' . $inv->number : '' }}"
                                                    data-body="{{ base64_encode($msg->body_html ?? '') }}"
                                                    title="Visualizar e-mail">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Rodapé da Paginação --}}
                <div class="card-footer bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center py-3 px-4 gap-2">
                    <span class="small text-muted">
                        Exibindo {{ $messages->count() }} de {{ $messages->total() }} e-mails registrados
                    </span>
                    <div>
                        {{ $messages->withQueryString()->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ==========================================================================
     MODAL: SIMULADOR DE CLIENTE DE E-MAIL (PREVIEW REALISTA DA MENSAGEM)
     ========================================================================== --}}
<div class="modal fade" id="modalEmailSimulator" tabindex="-1" aria-labelledby="modalEmailSimulatorLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 bg-transparent shadow-none">
            <div class="email-client-window">

                {{-- Barra Superior da Janela de E-mail (Window Chrome) --}}
                <div class="email-window-topbar">
                    <div class="email-window-controls">
                        <span class="email-window-dot email-window-dot--red" data-bs-dismiss="modal" role="button" title="Fechar"></span>
                        <span class="email-window-dot email-window-dot--yellow" title="Minimizar"></span>
                        <span class="email-window-dot email-window-dot--green" id="btnToggleFullscreen" role="button" title="Expandir visualização"></span>
                    </div>
                    <div class="email-window-title">
                        <i class="bi bi-envelope-at-fill text-primary"></i>
                        <span>Cliente de E-mail — Visualização Fiel da Mensagem Entregue</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" id="btnCopyEmailContent" title="Copiar conteúdo">
                            <i class="bi bi-clipboard me-1"></i> Copiar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" id="btnPrintEmail" title="Imprimir e-mail">
                            <i class="bi bi-printer me-1"></i> Imprimir
                        </button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                </div>

                {{-- Envelope / Cabeçalho da Mensagem --}}
                <div class="email-envelope-header">
                    {{-- Assunto --}}
                    <div class="email-subject-heading" id="modalEmailSubject">
                        Cobrança Fatura #0000
                    </div>

                    {{-- Metadados: De, Para, Data, Status --}}
                    <div class="email-meta-grid">
                        <div class="email-sender-avatar" id="modalSenderAvatar">
                            M
                        </div>
                        <div class="email-meta-details">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div class="email-meta-line">
                                    <span class="email-meta-label">De:</span>
                                    <strong class="text-body" id="modalSenderName">Minha Empresa</strong>
                                    <span class="text-muted font-monospace small" id="modalSenderEmail">&lt;financeiro@empresa.com&gt;</span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-2 rounded-pill small" title="Assinado digitalmente">
                                        <i class="bi bi-shield-fill-check me-1"></i> Autenticado TLS / SPF / DKIM
                                    </span>
                                </div>
                                <div id="modalStatusBadge">
                                    <span class="badge text-bg-success">Entregue</span>
                                </div>
                            </div>

                            <div class="email-meta-line">
                                <span class="email-meta-label">Para:</span>
                                <span class="fw-semibold text-body" id="modalRecipientCustomer">Nome do Cliente</span>
                                <span class="text-muted font-monospace small" id="modalRecipientEmail">&lt;cliente@email.com&gt;</span>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-1">
                                <div class="email-meta-line mb-0">
                                    <span class="email-meta-label">Data:</span>
                                    <span class="text-muted" id="modalEmailDate">19/10/2026 12:00:00</span>
                                    <span class="text-muted small ms-1" id="modalEmailDateHuman">(há alguns instantes)</span>
                                </div>
                                <div id="modalInvoiceBadgeContainer"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Alerta de Falha (Se houver erro SMTP registrado) --}}
                    <div id="modalErrorBox" class="alert alert-danger d-flex align-items-start gap-2 mt-3 mb-0 py-2 px-3 rounded-3 d-none">
                        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
                        <div>
                            <div class="fw-bold">Falha no Envio pelo Servidor SMTP</div>
                            <div class="small font-monospace" id="modalErrorText">Erro detalhado</div>
                        </div>
                    </div>
                </div>

                {{-- Viewport do Conteúdo: Renderizado em Iframe Isolado --}}
                <div class="email-viewport-container">
                    <iframe id="emailIframeViewport" 
                            class="email-iframe-viewer" 
                            title="Visualização da Mensagem de E-mail"
                            sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin">
                    </iframe>
                </div>

                {{-- Rodapé do Cliente de E-mail --}}
                <div class="p-3 bg-body-tertiary border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i> Visualização exata renderizada com os mesmos estilos recebidos pelo cliente final.
                    </div>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Botão de Atualizar com Animação
    const btnRefresh = document.getElementById('btnRefreshHistory');
    const iconRefresh = document.getElementById('iconRefresh');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            if (iconRefresh) iconRefresh.classList.add('spin-anim');
            btnRefresh.disabled = true;
            window.location.reload();
        });
    }

    // 2. Helper de Copiar Conteúdo com Toastify Global
    function showToast(message, isError = false) {
        if (typeof Toastify === 'function') {
            Toastify({
                text: message,
                duration: 3500,
                gravity: "top",
                position: "right",
                style: {
                    background: isError 
                        ? "#dc3545" 
                        : "linear-gradient(to right, #00b09b, #96c93d)",
                    borderRadius: "10px",
                    boxShadow: "0 6px 16px rgba(0,0,0,0.15)"
                }
            }).showToast();
        }
    }

    // 3. Modal de Simulação de E-mail
    const modalEl = document.getElementById('modalEmailSimulator');
    const emailModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    let currentRawHtml = '';

    // Disparadores de abertura (botão do olho ou clique no assunto)
    document.querySelectorAll('.js-trigger-email-preview').forEach(trigger => {
        trigger.addEventListener('click', function () {
            const id          = this.getAttribute('data-id') || '';
            const to          = this.getAttribute('data-to') || '';
            const customer    = this.getAttribute('data-customer') || 'Cliente';
            const senderName  = this.getAttribute('data-sender-name') || 'Minha Empresa';
            const senderEmail = this.getAttribute('data-sender-email') || '';
            const subject     = this.getAttribute('data-subject') || '(Sem assunto)';
            const status      = this.getAttribute('data-status') || 'sent';
            const statusLabel = this.getAttribute('data-status-label') || 'Enviado';
            const statusColor = this.getAttribute('data-status-color') || 'info';
            const error       = this.getAttribute('data-error') || '';
            const dateStr     = this.getAttribute('data-date') || '';
            const dateHuman   = this.getAttribute('data-date-human') || '';
            const invoice     = this.getAttribute('data-invoice') || '';
            const bodyEnc     = this.getAttribute('data-body') || '';

            // Decodifica corpo HTML
            let bodyHtml = '';
            try {
                bodyHtml = decodeURIComponent(escape(atob(bodyEnc)));
            } catch (e) {
                try {
                    bodyHtml = atob(bodyEnc);
                } catch (e2) {
                    bodyHtml = bodyEnc;
                }
            }

            const tenantBranding = {
                logoUrl: @json($tenantBranding['logo_url'] ?? ''),
                brandName: @json($tenantBranding['brand_name'] ?? ($tenant->company_name ?? '')),
                primaryColor: @json($tenantBranding['primary_color'] ?? '#0f172a'),
                accentColor: @json($tenantBranding['accent_color'] ?? '#0d6efd')
            };

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

            currentRawHtml = bodyHtml;

            // Preenche Assunto
            const subjectEl = document.getElementById('modalEmailSubject');
            if (subjectEl) subjectEl.textContent = subject;

            // Preenche Remetente
            const senderAvatarEl = document.getElementById('modalSenderAvatar');
            if (senderAvatarEl) {
                senderAvatarEl.textContent = senderName.trim().charAt(0).toUpperCase() || 'M';
            }
            const senderNameEl = document.getElementById('modalSenderName');
            if (senderNameEl) senderNameEl.textContent = senderName;

            const senderEmailEl = document.getElementById('modalSenderEmail');
            if (senderEmailEl) senderEmailEl.textContent = senderEmail ? `<${senderEmail}>` : '';

            // Preenche Destinatário
            const recipientCustEl = document.getElementById('modalRecipientCustomer');
            if (recipientCustEl) recipientCustEl.textContent = customer;

            const recipientEmailEl = document.getElementById('modalRecipientEmail');
            if (recipientEmailEl) recipientEmailEl.textContent = to ? `<${to}>` : '';

            // Preenche Data
            const dateEl = document.getElementById('modalEmailDate');
            if (dateEl) dateEl.textContent = dateStr;

            const dateHumanEl = document.getElementById('modalEmailDateHuman');
            if (dateHumanEl) dateHumanEl.textContent = dateHuman ? `(${dateHuman})` : '';

            // Preenche Status Badge
            const statusBadgeEl = document.getElementById('modalStatusBadge');
            if (statusBadgeEl) {
                statusBadgeEl.innerHTML = `<span class="badge text-bg-${statusColor} px-3 py-2 fs-6 rounded-pill">${statusLabel}</span>`;
            }

            // Badge de Fatura
            const invoiceContainer = document.getElementById('modalInvoiceBadgeContainer');
            if (invoiceContainer) {
                if (invoice) {
                    invoiceContainer.innerHTML = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill"><i class="bi bi-receipt me-1"></i>${invoice}</span>`;
                } else {
                    invoiceContainer.innerHTML = '';
                }
            }

            // Exibição de Erro
            const errorBox = document.getElementById('modalErrorBox');
            const errorText = document.getElementById('modalErrorText');
            if (errorBox && errorText) {
                if (status === 'failed' && error) {
                    errorText.textContent = error;
                    errorBox.classList.remove('d-none');
                } else {
                    errorBox.classList.add('d-none');
                }
            }

            // Injeção do HTML no iframe sandboxed
            const iframe = document.getElementById('emailIframeViewport');
            if (iframe) {
                // Se o corpo não tiver tags HTML, envelopa com estrutura básica limpa
                const hasHtml = /<[a-z][\s\S]*>/i.test(bodyHtml);
                let finalDoc = '';
                if (hasHtml) {
                    finalDoc = bodyHtml;
                } else {
                    finalDoc = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="utf-8">
                            <style>
                                body {
                                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                                    line-height: 1.6;
                                    color: #212529;
                                    padding: 24px;
                                    margin: 0;
                                    white-space: pre-wrap;
                                }
                            </style>
                        </head>
                        <body>${bodyHtml || '<em style="color: #6c757d;">Conteúdo do e-mail não disponível.</em>'}</body>
                        </html>
                    `;
                }

                iframe.srcdoc = finalDoc;
            }

            if (emailModal) {
                emailModal.show();
            }
        });
    });

    // 4. Copiar Conteúdo do E-mail
    const btnCopy = document.getElementById('btnCopyEmailContent');
    if (btnCopy) {
        btnCopy.addEventListener('click', () => {
            if (!currentRawHtml) {
                showToast('Nenhum conteúdo para copiar.', true);
                return;
            }
            // Copia texto sem tags se possível ou o html
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = currentRawHtml;
            const plainText = tempDiv.textContent || tempDiv.innerText || currentRawHtml;

            navigator.clipboard.writeText(plainText).then(() => {
                showToast('Conteúdo do e-mail copiado com sucesso!');
            }).catch(() => {
                showToast('Falha ao copiar para a área de transferência.', true);
            });
        });
    }

    // 5. Imprimir E-mail
    const btnPrint = document.getElementById('btnPrintEmail');
    if (btnPrint) {
        btnPrint.addEventListener('click', () => {
            const iframe = document.getElementById('emailIframeViewport');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } else {
                window.print();
            }
        });
    }

    // 6. Alternar Tela Cheia no Modal
    const btnFullscreen = document.getElementById('btnToggleFullscreen');
    if (btnFullscreen && modalEl) {
        btnFullscreen.addEventListener('click', () => {
            const dialog = modalEl.querySelector('.modal-dialog');
            if (dialog) {
                dialog.classList.toggle('modal-fullscreen');
            }
        });
    }

    // 7. Inicializa tooltips do Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
