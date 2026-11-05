@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Editar contrato ' . $contract->number)

@push('head')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<style>
/* ── Summernote overrides ── */
#frm-contract .note-editor.note-frame           { border-color: var(--bs-border-color) !important; border-radius: 16px !important; overflow: hidden; background: #fff !important; }
#frm-contract .note-editor .note-toolbar        { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; border-radius: 16px 16px 0 0 !important; padding: 6px 8px !important; }
#frm-contract .note-editor .note-statusbar      { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; border-radius: 0 0 16px 16px !important; }
#frm-contract .note-editor .note-editing-area   { background: #fff !important; }
#frm-contract .note-editor .note-editable       { min-height: 520px; background: #fff !important; color: #000 !important; font-size: 15px; line-height: 1.7; padding: 32px !important; }
#frm-contract .note-editor .note-editable *     { color: #000 !important; }
#frm-contract .note-editor .note-btn            { color: var(--bs-body-color) !important; background: var(--bs-body-bg) !important; border-color: var(--bs-border-color) !important; border-radius: 8px !important; padding: 6px 10px !important; font-size: 13px !important; }
#frm-contract .note-editor .note-btn:hover      { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-primary) !important; }
#frm-contract .note-editor .note-dropdown-menu  { background: var(--bs-body-bg) !important; border: 1px solid var(--bs-border-color) !important; border-radius: 12px !important; box-shadow: 0 12px 30px rgba(0,0,0,.12) !important; padding: 6px !important; }
#frm-contract .note-editor .note-dropdown-item  { color: var(--bs-body-color) !important; padding: 7px 12px !important; border-radius: 8px !important; }
#frm-contract .note-editor .note-dropdown-item:hover { background: var(--bs-tertiary-bg) !important; }
#frm-contract .note-editor .note-modal-content  { background: var(--bs-body-bg) !important; border: 1px solid var(--bs-border-color) !important; border-radius: 16px !important; }
#frm-contract .note-editor .note-modal-header,
#frm-contract .note-editor .note-modal-footer   { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; }
#frm-contract .note-editor .note-form-label     { color: var(--bs-body-color) !important; font-weight: 600 !important; }
#frm-contract .note-editor .note-input          { background: var(--bs-body-bg) !important; border: 1px solid var(--bs-border-color) !important; border-radius: 8px !important; color: var(--bs-body-color) !important; }
#frm-contract .note-editor .note-btn-primary    { background: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; }

/* ── Layout premium ── */
.cte-eyebrow       { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--bs-primary); display: block; margin-bottom: 2px; }
.cte-tabs          { border-bottom: 2px solid var(--bs-border-color); margin-bottom: 24px; }
.cte-tabs .nav-link { color: var(--bs-secondary-color); border: 0; border-bottom: 3px solid transparent; margin-bottom: -2px; padding: 10px 18px; font-weight: 600; font-size: 13.5px; border-radius: 0; transition: color .18s, border-color .18s; }
.cte-tabs .nav-link i { margin-right: 6px; }
.cte-tabs .nav-link:hover { color: var(--bs-body-color); }
.cte-tabs .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); background: transparent; }
.cte-card          { border-radius: 18px; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); box-shadow: 0 2px 16px rgba(15,23,42,.06); overflow: hidden; }
.cte-card-header   { padding: 16px 22px; border-bottom: 1px solid var(--bs-border-color); background: var(--bs-tertiary-bg); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.cte-card-body     { padding: 22px; }
.var-chip          { font-size: 11px; padding: 3px 8px; border-radius: 6px; font-family: monospace; cursor: pointer; transition: background .15s, transform .12s; }
.var-chip:hover    { transform: translateY(-1px); }
[data-upload-trigger] * { pointer-events: none; }

/* ── Layout Modes ── */
#tab-layout.mode-fluid .cte-stage-wrap, 
#tab-layout.mode-fluid .stage-controls { display: none !important; }
#tab-layout.mode-fluid .layout-input-row { display: none !important; }
#tab-layout.mode-fluid .fluid-msg { display: block !important; }
#tab-layout.mode-fluid .fluid-margins-ctrl { display: flex !important; }
.fluid-msg, .fluid-margins-ctrl { display: none; }

/* ── Stage no-code ── */
.cte-stage-wrap    { width: 100%; overflow: hidden; background: var(--bs-tertiary-bg); border-radius: 12px; border: 1px solid var(--bs-border-color); padding: 8px; }
.cte-stage         { position: relative; background: #fff; transform-origin: top center; cursor: default; }
.cte-stage.show-grid::after { content:''; position:absolute; inset:0; z-index:5; pointer-events:none; background-image: linear-gradient(rgba(100,116,139,.3) 1px, transparent 1px), linear-gradient(90deg, rgba(100,116,139,.3) 1px, transparent 1px); background-size: var(--grid-size, 10px) var(--grid-size, 10px); }
.cte-stage-bg      { position: absolute; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
.cte-stage-bg img, .cte-stage-bg canvas { width: 100%; height: 100%; object-fit: fill; display: block; }

/* ── Blocos ── */
.layout-box        { position: absolute; border: 2px solid rgba(99,102,241,.5); border-radius: 8px; background: rgba(99,102,241,.07); cursor: grab; user-select: none; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: rgba(99,102,241,.9); text-transform: uppercase; letter-spacing: .08em; z-index: 10; transition: border-color .15s, background .15s, box-shadow .15s; }
.layout-box:hover  { border-color: rgba(99,102,241,.9); background: rgba(99,102,241,.14); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
.layout-box.selected { border-color: #6366f1; background: rgba(99,102,241,.18); box-shadow: 0 0 0 4px rgba(99,102,241,.2); cursor: grabbing; }
.layout-box[data-box="signature"] { border-color: rgba(16,185,129,.5); background: rgba(16,185,129,.07); color: rgba(16,185,129,.9); }
.layout-box[data-box="signature"]:hover,.layout-box[data-box="signature"].selected { border-color: #10b981; background: rgba(16,185,129,.16); box-shadow: 0 0 0 4px rgba(16,185,129,.2); }
.layout-box[data-box="signer"]    { border-color: rgba(245,158,11,.5); background: rgba(245,158,11,.07); color: rgba(245,158,11,.9); }
.layout-box[data-box="signer"]:hover,.layout-box[data-box="signer"].selected { border-color: #f59e0b; background: rgba(245,158,11,.16); box-shadow: 0 0 0 4px rgba(245,158,11,.2); }
.layout-box[data-box="metadata"]  { border-color: rgba(239,68,68,.5); background: rgba(239,68,68,.07); color: rgba(239,68,68,.9); }
.layout-box[data-box="metadata"]:hover,.layout-box[data-box="metadata"].selected { border-color: #ef4444; background: rgba(239,68,68,.16); box-shadow: 0 0 0 4px rgba(239,68,68,.2); }

/* ── Resize handle ── */
.rsz-handle        { position: absolute; right: 0; bottom: 0; width: 16px; height: 16px; cursor: se-resize; z-index: 20; display: flex; align-items: flex-end; justify-content: flex-end; padding: 2px; }
.rsz-handle::after { content: ''; display: block; width: 8px; height: 8px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; border-radius: 0 0 3px 0; opacity: .7; }

/* ── Preview ── */
.cte-preview-wrap  { background: #e8ecf0; border-radius: 12px; padding: 12px; display: flex; align-items: flex-start; justify-content: center; min-height: 260px; }
.cte-preview-stage { position: relative; width: 100%; max-width: 380px; background: #fff; border-radius: 10px; box-shadow: 0 8px 32px rgba(15,23,42,.15); overflow: hidden; aspect-ratio: 794 / 1123; }
.cte-preview-bg    { position: absolute; top: 0; left: 0; width: 794px; height: 1123px; transform-origin: top left; z-index: 0; pointer-events: none; }
.cte-preview-bg img,.cte-preview-bg canvas { width: 100%; height: 100%; object-fit: fill; display: block; }
.cte-preview-content { position: absolute; top: 0; left: 0; width: 794px; height: 1123px; transform-origin: top left; z-index: 2; overflow: hidden; pointer-events: none; }

/* ── Controles blocos ── */
.block-ctrl        { border-radius: 14px; border: 1px solid var(--bs-border-color); padding: 14px; transition: border-color .2s, box-shadow .2s; }
.block-ctrl.is-selected { border-color: var(--bs-primary); box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb),.1); }
.block-ctrl input[type=number] { text-align: center; }

/* ── Upload zone ── */
.cte-upload-zone   { border: 2px dashed var(--bs-border-color); border-radius: 14px; padding: 22px; text-align: center; cursor: pointer; transition: border-color .2s, background .2s; }
.cte-upload-zone:hover,.cte-upload-zone.drag-over { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.04); }
.cte-upload-icon   { font-size: 28px; color: var(--bs-primary); margin-bottom: 8px; display: block; }

/* ── BG preview ── */
.cte-bg-preview    { min-height: 90px; border-radius: 12px; border: 1px dashed var(--bs-border-color); background: var(--bs-tertiary-bg); display: flex; align-items: center; justify-content: center; color: var(--bs-secondary-color); font-size: 13px; overflow: hidden; }
.cte-bg-preview img { width: 100%; display: block; border-radius: 12px; max-height:180px; object-fit:contain; }

/* ── Vars table ── */
.var-row           { display: flex; justify-content: space-between; align-items: center; padding: 7px 10px; border-radius: 8px; border: 1px solid var(--bs-border-color); font-size: 12.5px; cursor: pointer; transition: all .15s; }
.var-row:hover     { background: rgba(var(--bs-primary-rgb),.05); border-color: var(--bs-primary); }
.var-row code      { font-size: 11.5px; }

/* ── Autocomplete Cliente ── */
.customer-search-wrap { position: relative; }
.customer-results-box {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 1050;
    max-height: 240px; overflow-y: auto; background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color); border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,.15); margin-top: 4px; display: none;
}
.customer-result-item {
    padding: 10px 14px; border-bottom: 1px solid var(--bs-border-color);
    cursor: pointer; transition: background .15s;
}
.customer-result-item:last-child { border-bottom: none; }
.customer-result-item:hover { background: var(--bs-tertiary-bg); }
.customer-selected-card {
    border-radius: 12px; border: 1px solid rgba(16,185,129,.35);
    background: rgba(16,185,129,.05); padding: 12px 16px; display: flex;
}
</style>
@endpush

@section('content')
@php
    $selectedTemplate = $selectedTemplate ?? $contract->template;
    $selectedCustomer = $selectedCustomer ?? $contract->customer;
    $selectedInvoice = $selectedInvoice ?? $contract->invoice;
    $layout = $contract->layout_json ?: ($selectedTemplate?->layout_json ?: app(\App\Modules\Contracts\Services\ContractService::class)->defaultLayout());
    $contractRoutePrefix = $contractRoutePrefix ?? (request()->is('painel*') ? 'tenant.contracts.' : 'admin.contracts.');
    $contractBaseUrl = $contractBaseUrl ?? url(request()->is('painel*') ? '/painel/contratos' : '/admin/contracts');
    $contractUploadUrl = $contractUploadUrl ?? route(request()->is('painel*') ? 'tenant.uploads.store' : 'admin.uploads.store', 'contracts');
    $variables = $variables ?? collect();

    $statusConfig = [
        'draft'    => ['secondary','Rascunho'],
        'sent'     => ['primary','Enviado'],
        'viewed'   => ['info','Visualizado'],
        'signed'   => ['success','Assinado'],
        'rejected' => ['danger','Rejeitado'],
        'expired'  => ['dark','Expirado'],
    ];
    [$stColor, $stLabel] = $statusConfig[$contract->status] ?? ['secondary', $contract->status];
@endphp

<div class="container-fluid">

    {{-- HERO --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="padding:8px 0 16px;">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route($contractRoutePrefix . 'show', $contract->id) }}" class="btn btn-outline-secondary btn-sm" style="border-radius:10px;" title="Voltar aos detalhes">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <span class="cte-eyebrow">Edição de Contrato Digital &middot; <code>{{ $contract->number }}</code></span>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="mb-0 fw-bold">Editar contrato</h4>
                    <span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span>
                </div>
                <p class="text-muted mb-0 small">Ajuste os dados do cliente, cláusulas contratuais e posições de layout antes da assinatura.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route($contractRoutePrefix . 'show', $contract->id) }}" class="btn btn-outline-secondary" style="border-radius:12px;padding:10px 18px;">
                Cancelar
            </a>
            <button type="submit" form="frm-contract" class="btn btn-primary" id="btn-save" style="border-radius:12px;padding:10px 24px;font-weight:600;">
                <i class="bi bi-check2-circle me-2"></i>Salvar alterações
            </button>
        </div>
    </div>

    @if(in_array($contract->status, ['sent', 'viewed'], true))
    <div class="alert alert-warning d-flex align-items-center gap-3 py-2 px-3 mb-4 shadow-sm" style="border-radius:12px;">
        <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0 text-warning"></i>
        <div class="small">
            <strong>Atenção:</strong> Este contrato já possui link de assinatura público enviado/visualizado pelo cliente. Qualquer alteração salva será atualizada em tempo real na página de assinatura.
        </div>
    </div>
    @endif

    <form id="frm-contract" autocomplete="off" novalidate>
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="layout_json" id="layout_json">
        <input type="hidden" name="background_type" id="background_type" value="{{ $contract->background_type ?: ($selectedTemplate?->background_type ?: 'none') }}">

        {{-- TABS --}}
        <ul class="nav cte-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-info"    type="button"><i class="bi bi-card-text"></i>Informações</button></li>
            <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-content" type="button"><i class="bi bi-file-earmark-text"></i>Conteúdo</button></li>
            <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-layout"  type="button"><i class="bi bi-bounding-box"></i>Layout no-code</button></li>
            <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#tab-vars"    type="button"><i class="bi bi-code-square"></i>Variáveis</button></li>
        </ul>

        <div class="tab-content">

            {{-- ABA: INFORMAÇÕES --}}
            <div class="tab-pane fade show active" id="tab-info">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="cte-card mb-4">
                            <div class="cte-card-header">
                                <strong><i class="bi bi-person-badge me-1"></i>Cliente &amp; Fatura</strong>
                                <span class="badge bg-light text-dark border">Contrato {{ $contract->number }}</span>
                            </div>
                            <div class="cte-card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                                        <div class="customer-search-wrap">
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0" style="border-radius:10px 0 0 10px;"><i class="bi bi-search"></i></span>
                                                <input id="customer-search" class="form-control border-start-0" placeholder="Buscar cliente para alterar..." value="{{ $selectedCustomer?->name }}" style="border-radius:0 10px 10px 0;">
                                            </div>
                                            <input type="hidden" name="customer_id" id="customer_id" value="{{ $selectedCustomer?->id }}" required>
                                            <div id="customer-results" class="customer-results-box"></div>
                                        </div>

                                        <div id="customer-selected-card" class="customer-selected-card mt-2" style="{{ $selectedCustomer ? 'display:flex;' : 'display:none;' }}">
                                            <div class="d-flex align-items-center justify-content-between w-100">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                                    <div>
                                                        <strong id="cust-card-name" class="d-block">{{ $selectedCustomer?->name }}</strong>
                                                        <small id="cust-card-meta" class="text-muted">
                                                            {{ $selectedCustomer?->email }} &middot; {{ $selectedCustomer?->document ?: 'Sem documento' }} &middot; {{ $selectedCustomer?->whatsapp ?: $selectedCustomer?->phone }}
                                                        </small>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger" id="btn-change-customer" title="Trocar cliente" style="border-radius:8px;">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Vincular a uma fatura <span class="text-muted small fw-normal">(Opcional)</span></label>
                                        <select name="invoice_id" id="invoice-select" class="form-select" style="border-radius:10px;">
                                            <option value="">Nenhuma fatura vinculada</option>
                                            @if($selectedInvoice)
                                                <option value="{{ $selectedInvoice->id }}" selected>
                                                    #{{ $selectedInvoice->number }} &middot; R$ {{ number_format((float) $selectedInvoice->amount, 2, ',', '.') }} &middot; Venc: {{ $selectedInvoice->due_date ? \Carbon\Carbon::parse($selectedInvoice->due_date)->format('d/m/Y') : '—' }} ({{ $selectedInvoice->status }})
                                                </option>
                                            @endif
                                        </select>
                                        <div class="form-text small">As variáveis <code>{valor}</code>, <code>{vencimento}</code> e <code>{numero_fatura}</code> são vinculadas à fatura selecionada.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="cte-card">
                            <div class="cte-card-header"><strong><i class="bi bi-file-earmark-text me-1"></i>Dados do contrato</strong></div>
                            <div class="cte-card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Modelo base (Template)</label>
                                        <select name="template_id" id="template-select" class="form-select" style="border-radius:10px;">
                                            <option value="">Contrato em branco (Personalizado)</option>
                                            @foreach($templates as $tpl)
                                                <option value="{{ $tpl->id }}" @selected($contract->template_id === $tpl->id)>{{ $tpl->name }} ({{ $tpl->category ?: 'Geral' }})</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text small">Modelo de referência vinculado ao contrato.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Título do contrato / Motivo <span class="text-danger">*</span></label>
                                        <input type="text" name="title" id="title" class="form-control" required value="{{ old('title', $contract->title) }}" placeholder="Ex: Prestação de Serviços Digitais" style="border-radius:10px;">
                                        <div class="form-text small">Exibido com destaque no documento oficial e no topo do contrato.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Validade / Expiração da assinatura</label>
                                        <input type="date" name="expires_at" id="expires_at" class="form-control" value="{{ old('expires_at', $contract->expires_at?->format('Y-m-d')) }}" style="border-radius:10px;">
                                        <div class="form-text small">Data limite para o cliente assinar o contrato.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Link público de assinatura</label>
                                        <div class="input-group">
                                            <input type="text" readonly class="form-control bg-light" value="{{ $contract->public_url }}" style="border-radius:10px 0 0 10px; font-size:12px;">
                                            <button type="button" class="btn btn-outline-secondary" id="btn-copy-public-link" data-copy="{{ $contract->public_url }}" style="border-radius:0 10px 10px 0;" title="Copiar link">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <div class="form-text small">Link exclusivo para assinatura do cliente.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SIDEBAR INFORMAÇÕES --}}
                    <div class="col-lg-4">
                        <div class="cte-card mb-4">
                            <div class="cte-card-header"><strong><i class="bi bi-info-circle me-1"></i>Sobre a Edição</strong></div>
                            <div class="cte-card-body small text-muted">
                                <p class="mb-2">Você pode atualizar livremente todas as cláusulas, condições e posições do layout deste contrato.</p>
                                <div class="alert alert-light border py-2 px-3 mb-2">
                                    <strong class="text-dark d-block mb-1">Status Jurídico:</strong>
                                    Enquanto o cliente não assinar, o contrato está aberto para ajustes comerciais e correções. Assim que for assinado, ele será selado com criptografia e carimbo de data/hora imutável.
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                                    <i class="bi bi-shield-check text-success fs-5"></i>
                                    <span>Validade com carimbo de auditoria, IP e geolocalização.</span>
                                </div>
                            </div>
                        </div>

                        <div class="cte-card">
                            <div class="cte-card-header"><strong><i class="bi bi-lightning-charge me-1"></i>Tags Mais Usadas</strong></div>
                            <div class="cte-card-body">
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="var-chip bg-primary-subtle text-primary" data-insert="{nome_cliente}">{nome_cliente}</span>
                                    <span class="var-chip bg-primary-subtle text-primary" data-insert="{documento_cliente}">{documento_cliente}</span>
                                    <span class="var-chip bg-primary-subtle text-primary" data-insert="{email_cliente}">{email_cliente}</span>
                                    <span class="var-chip bg-primary-subtle text-primary" data-insert="{telefone_cliente}">{telefone_cliente}</span>
                                    <span class="var-chip bg-success-subtle text-success" data-insert="{valor}">{valor}</span>
                                    <span class="var-chip bg-success-subtle text-success" data-insert="{vencimento}">{vencimento}</span>
                                    <span class="var-chip bg-info-subtle text-info-emphasis" data-insert="{data_extenso}">{data_extenso}</span>
                                    <span class="var-chip bg-secondary-subtle text-secondary-emphasis" data-insert="{titulo_contrato}">{titulo_contrato}</span>
                                </div>
                                <div class="text-muted small mt-2">Clique para inserir na posição atual do editor de texto.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ABA: CONTEÚDO --}}
            <div class="tab-pane fade" id="tab-content">
                <div class="cte-card mb-4">
                    <div class="cte-card-header">
                        <div>
                            <strong><i class="bi bi-textarea-t me-1"></i>Cláusulas e Texto do Contrato</strong>
                            <span class="text-muted small ms-2">Edite o texto contratual com formatação rica</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-sync-preview-content">
                                <i class="bi bi-arrow-clockwise me-1"></i>Atualizar preview
                            </button>
                        </div>
                    </div>
                    <div class="cte-card-body p-0">
                        <textarea id="tpl-editor" name="content_html">{!! old('content_html', $contract->content_html) !!}</textarea>
                    </div>
                </div>
            </div>

            {{-- ABA: LAYOUT NO-CODE --}}
            <div class="tab-pane fade" id="tab-layout">
                <div class="row g-4">
                    {{-- PALETA E CONFIGS --}}
                    <div class="col-lg-4">
                        <div class="cte-card mb-4">
                            <div class="cte-card-header"><strong><i class="bi bi-sliders me-1"></i>Modo &amp; Timbrado</strong></div>
                            <div class="cte-card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Modo de Renderização</label>
                                    <div class="btn-group w-100" role="group" id="btn-group-mode">
                                        <input type="radio" class="btn-check" name="layout_mode_radio" id="mode_fixed" value="fixed" autocomplete="off" {{ ($layout['mode'] ?? 'fixed') === 'fixed' ? 'checked' : '' }}>
                                        <label class="btn btn-outline-primary" for="mode_fixed"><i class="bi bi-bounding-box me-1"></i>Fixo (Visual A4)</label>

                                        <input type="radio" class="btn-check" name="layout_mode_radio" id="mode_fluid" value="fluid" autocomplete="off" {{ ($layout['mode'] ?? 'fixed') === 'fluid' ? 'checked' : '' }}>
                                        <label class="btn btn-outline-primary" for="mode_fluid"><i class="bi bi-file-text me-1"></i>Fluido (Dinâmico)</label>
                                    </div>
                                    <div class="form-text small mt-1">
                                        <strong>Fixo:</strong> Posicionamento absoluto sobre papel timbrado A4.<br>
                                        <strong>Fluido:</strong> Texto corre naturalmente por várias páginas.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Papel Timbrado de Fundo</label>
                                    <div class="d-flex gap-2 mb-2">
                                        <input type="text" name="background_path" id="background_path" class="form-control form-control-sm" placeholder="URL da imagem (PNG/JPG) ou PDF" value="{{ $contract->background_path }}" style="border-radius:8px;">
                                    </div>

                                    <div class="cte-bg-preview mb-2" id="bg-preview-box">
                                        <span class="text-muted small">Nenhum papel timbrado carregado</span>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-upload-bg" data-upload-trigger="bg-file-input">
                                            <i class="bi bi-upload me-1"></i>Trocar timbrado
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-restore-default-bg" title="Restaurar timbrado padrão do sistema">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Padrão
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-bg">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <input type="file" id="bg-file-input" accept="image/*,application/pdf" style="display:none;">
                                </div>

                                <div class="fluid-margins-ctrl flex-column gap-2 border-top pt-3">
                                    <label class="form-label fw-semibold mb-0">Margens do Modo Fluido (px)</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="small text-muted">Topo</label>
                                            <input type="number" id="fluid-margin-top" class="form-control form-control-sm" value="{{ $layout['fluid_margins']['top'] ?? 90 }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">Base</label>
                                            <input type="number" id="fluid-margin-bottom" class="form-control form-control-sm" value="{{ $layout['fluid_margins']['bottom'] ?? 90 }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">Esquerda</label>
                                            <input type="number" id="fluid-margin-left" class="form-control form-control-sm" value="{{ $layout['fluid_margins']['left'] ?? 60 }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">Direita</label>
                                            <input type="number" id="fluid-margin-right" class="form-control form-control-sm" value="{{ $layout['fluid_margins']['right'] ?? 60 }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- CONTROLES DOS BLOCOS --}}
                        <div class="cte-card stage-controls">
                            <div class="cte-card-header"><strong><i class="bi bi-layers me-1"></i>Propriedades do Bloco</strong></div>
                            <div class="cte-card-body" id="block-controls-container">
                                <p class="text-muted small mb-0">Selecione um bloco no palco ao lado para editar suas coordenadas e formatação.</p>
                            </div>
                        </div>
                    </div>

                    {{-- PALCO / STAGE A4 --}}
                    <div class="col-lg-8">
                        <div class="cte-card">
                            <div class="cte-card-header">
                                <strong><i class="bi bi-display me-1"></i>Palco de Layout A4 (794 x 1123 px)</strong>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="chk-grid" checked>
                                        <label class="form-check-label small" for="chk-grid">Grade</label>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="chk-snap" checked>
                                        <label class="form-check-label small" for="chk-snap">Snap</label>
                                    </div>
                                </div>
                            </div>
                            <div class="cte-card-body p-3">
                                <div class="fluid-msg alert alert-info text-center py-4 mb-0">
                                    <i class="bi bi-file-earmark-richtext fs-1 d-block mb-2"></i>
                                    <h5>Modo Fluido Ativo</h5>
                                    <p class="small text-muted mb-0">No modo fluido o texto cresce livremente quebrando as páginas respeitando as margens configuradas.<br>O timbrado de fundo será replicado em todas as páginas do contrato e do PDF.</p>
                                </div>

                                <div class="cte-stage-wrap" id="stage-wrap">
                                    <div class="cte-stage show-grid" id="stage">
                                        <div class="cte-stage-bg" id="stage-bg"></div>
                                        <div class="layout-box" id="box-content" data-box="content">
                                            <span>Conteúdo Contratual</span>
                                            <div class="rsz-handle" data-rsz="content"></div>
                                        </div>
                                        <div class="layout-box" id="box-signature" data-box="signature">
                                            <span>Assinatura Digital</span>
                                            <div class="rsz-handle" data-rsz="signature"></div>
                                        </div>
                                        <div class="layout-box" id="box-signer" data-box="signer">
                                            <span>Nome / Dados Signatário</span>
                                            <div class="rsz-handle" data-rsz="signer"></div>
                                        </div>
                                        <div class="layout-box" id="box-metadata" data-box="metadata">
                                            <span>Metadados / Carimbo / IP</span>
                                            <div class="rsz-handle" data-rsz="metadata"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- MINI PREVIEW EM TEMPO REAL --}}
                        <div class="cte-card mt-4">
                            <div class="cte-card-header">
                                <strong><i class="bi bi-eye me-1"></i>Pré-visualização da Folha</strong>
                                <span class="badge bg-primary-subtle text-primary">Tempo Real</span>
                            </div>
                            <div class="cte-card-body p-3 cte-preview-wrap">
                                <div class="cte-preview-stage" id="preview-stage">
                                    <div class="cte-preview-bg" id="preview-bg"></div>
                                    <div class="cte-preview-content" id="preview-content"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ABA: VARIÁVEIS --}}
            <div class="tab-pane fade" id="tab-vars">
                <div class="cte-card">
                    <div class="cte-card-header">
                        <strong><i class="bi bi-code-slash me-1"></i>Dicionário de Variáveis Dinâmicas</strong>
                        <span class="text-muted small">Clique sobre qualquer tag para copiá-la para a área de transferência</span>
                    </div>
                    <div class="cte-card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:280px;">Tag</th>
                                        <th>Descrição</th>
                                        <th>Exemplo / Origem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($variables as $varKey => $varDesc)
                                    <tr class="var-row" data-copy="{{ $varKey }}">
                                        <td><code class="text-primary fw-bold">{{ $varKey }}</code></td>
                                        <td>{{ is_array($varDesc) ? ($varDesc['description'] ?? '') : $varDesc }}</td>
                                        <td class="text-muted small">{{ is_array($varDesc) ? ($varDesc['example'] ?? 'Dinâmico') : 'Dinâmico' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-pt-BR.min.js"></script>
<script>
(() => {
    'use strict';

    /* ── Configurações Iniciais ── */
    const updateUrl = '{{ route($contractRoutePrefix . 'update', $contract->id) }}';
    const showUrl   = '{{ route($contractRoutePrefix . 'show', $contract->id) }}';
    const baseUrl   = '{{ $contractBaseUrl }}';
    const uploadUrl = '{{ $contractUploadUrl }}';
    const searchUrl = '{{ route($contractRoutePrefix . 'customers.search') }}';
    const defaultBgUrl = @json($defaultBackground);
    const defaultBgType = @json($defaultBackgroundType);
    const csrf      = '{{ csrf_token() }}';

    const PAGE_W = 794;
    const PAGE_H = 1123;
    let selectedBox = null;
    let isDragging = false;
    let isResizing = false;
    let dragStartX = 0, dragStartY = 0;
    let origBoxX = 0, origBoxY = 0, origBoxW = 0, origBoxH = 0;

    /* ── Layout Data ── */
    const initialLayout = @json($layout);
    const layout = {
        mode: initialLayout.mode || 'fixed',
        fluid_margins: initialLayout.fluid_margins || { top: 90, bottom: 90, left: 60, right: 60 },
        boxes: initialLayout.boxes || {
            content:   { x: 48, y: 120, w: 698, h: 720, font_family: 'Arial', font_size: 14, color: '#1e293b' },
            signature: { x: 48, y: 860, w: 260, h: 90,  font_family: 'Arial', font_size: 12, color: '#1e293b' },
            signer:    { x: 48, y: 955, w: 260, h: 50,  font_family: 'Arial', font_size: 11, color: '#475569' },
            metadata:  { x: 48, y: 1010, w: 698, h: 45, font_family: 'Arial', font_size: 10, color: '#64748b' },
        }
    };

    const boxes = layout.boxes;

    /* ── Elementos do DOM ── */
    const stage     = document.getElementById('stage');
    const wrap      = document.getElementById('stage-wrap');
    const stageBg   = document.getElementById('stage-bg');
    const bgInput   = document.getElementById('background_path');
    const bgTypeIn  = document.getElementById('background_type');
    const chkGrid   = document.getElementById('chk-grid');
    const chkSnap   = document.getElementById('chk-snap');
    const prevBg    = document.getElementById('preview-bg');
    const prevCont  = document.getElementById('preview-content');
    const cId       = document.getElementById('customer_id');
    const sInput    = document.getElementById('customer-search');
    const sResults  = document.getElementById('customer-results');
    const cCard     = document.getElementById('customer-selected-card');
    const invSelect = document.getElementById('invoice-select');

    /* ── Toast Helper ── */
    window.toast = {
        success: (m) => typeof toastr !== 'undefined' ? toastr.success(m) : console.log(m),
        error:   (m) => typeof toastr !== 'undefined' ? toastr.error(m) : console.error(m),
        info:    (m) => typeof toastr !== 'undefined' ? toastr.info(m) : console.log(m),
    };

    /* ── Escala do Stage A4 ── */
    const scale = () => {
        if (!wrap) return 1;
        const w = wrap.clientWidth - 16;
        return Math.min(1, Math.max(0.3, w / PAGE_W));
    };

    /* ── Summernote Init ── */
    function normSN() {
        const ed = document.querySelector('.note-editor .note-editable');
        if (!ed) return;
        ed.style.setProperty('background-color', '#fff', 'important');
        ed.style.setProperty('color', '#111', 'important');
        ed.style.setProperty('min-height', '520px', 'important');
        ed.querySelectorAll('*').forEach((n) => {
            if (n.style) {
                n.style.setProperty('color', '#111', 'important');
                if (n.style.backgroundColor && n.style.backgroundColor !== 'transparent') {
                    n.style.backgroundColor = 'transparent';
                }
            }
        });
    }

    function initSummernote() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.summernote === 'undefined') {
            setTimeout(initSummernote, 100);
            return;
        }
        if (jQuery('#tpl-editor').next('.note-editor').length > 0) {
            normSN();
            return;
        }
        try {
            jQuery('#tpl-editor').summernote({
                lang: 'pt-BR',
                height: 520,
                placeholder: 'Escreva o contrato, insira cláusulas e use as variáveis.',
                toolbar: [
                    ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
                    ['font', ['fontsize', 'color']],
                    ['para', ['ul', 'ol', 'paragraph', 'height']],
                    ['table', ['table']],
                    ['insert', ['link', 'hr']],
                    ['view', ['fullscreen', 'codeview']]
                ],
                callbacks: {
                    onChange: () => { normSN(); updatePreview(); }
                }
            });
            normSN();
            setTimeout(normSN, 300);
        } catch (e) {
            console.warn('Summernote não carregado:', e);
        }
    }
    initSummernote();

    const tabContentBtn = document.querySelector('[data-bs-target="#tab-content"]');
    if (tabContentBtn) {
        tabContentBtn.addEventListener('shown.bs.tab', () => {
            initSummernote();
            normSN();
        });
    }

    /* ── Alternância Fixo vs Fluido ── */
    const tabLayout = document.getElementById('tab-layout');
    const updateModeView = () => {
        if (!tabLayout) return;
        if (layout.mode === 'fluid') {
            tabLayout.classList.add('mode-fluid');
        } else {
            tabLayout.classList.remove('mode-fluid');
        }
    };

    document.querySelectorAll('input[name="layout_mode_radio"]').forEach(r => {
        r.addEventListener('change', (e) => {
            layout.mode = e.target.value;
            updateModeView();
            updatePreview();
        });
    });

    ['top','bottom','left','right'].forEach(dir => {
        const inp = document.getElementById(`fluid-margin-${dir}`);
        if (inp) {
            inp.type = 'text';
            inp.inputMode = 'decimal';
            const handleMargin = (e) => {
                let raw = String(e.target.value ?? '').trim().replace(',', '.');
                let num = parseFloat(raw);
                layout.fluid_margins[dir] = isNaN(num) ? 0 : Math.round(num * 10) / 10;
                updatePreview();
            };
            inp.addEventListener('input', handleMargin);
            inp.addEventListener('change', handleMargin);
        }
    });

    /* ── Grade e Snap ── */
    chkGrid.addEventListener('change', () => {
        stage.classList.toggle('show-grid', chkGrid.checked);
    });

    /* ── Renderização das Caixas no Stage ── */
    const renderBox = (name) => {
        const el = document.getElementById(`box-${name}`);
        const b = boxes[name];
        if (!el || !b) return;
        el.style.left   = b.x + 'px';
        el.style.top    = b.y + 'px';
        el.style.width  = b.w + 'px';
        el.style.height = b.h + 'px';
    };

    const renderAll = () => {
        Object.keys(boxes).forEach(renderBox);
        document.getElementById('layout_json').value = JSON.stringify(layout);
        updatePreview();
    };

    /* ── Controles de Propriedades dos Blocos ── */
    const buildControls = () => {
        const c = document.getElementById('block-controls-container');
        if (!c) return;
        const labels = {
            content:   { name: 'Conteúdo Contratual', icon: 'file-text' },
            signature: { name: 'Assinatura Digital', icon: 'pen' },
            signer:    { name: 'Dados Signatário', icon: 'person-lines-fill' },
            metadata:  { name: 'Metadados e Carimbo', icon: 'shield-check' }
        };

        let html = '';
        Object.keys(boxes).forEach(name => {
            const b = boxes[name];
            const meta = labels[name] || { name, icon: 'square' };
            html += `
                <div class="block-ctrl mb-3 ${selectedBox === name ? 'is-selected' : ''}" data-ctrl="${name}">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <strong class="small text-primary"><i class="bi bi-${meta.icon} me-1"></i>${meta.name}</strong>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-3">
                            <label class="small text-muted d-block" style="font-size:10px;">X (px)</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-sm b-prop" data-prop="x" data-box="${name}" value="${b.x ?? 0}">
                        </div>
                        <div class="col-3">
                            <label class="small text-muted d-block" style="font-size:10px;">Y (px)</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-sm b-prop" data-prop="y" data-box="${name}" value="${b.y ?? 0}">
                        </div>
                        <div class="col-3">
                            <label class="small text-muted d-block" style="font-size:10px;">Largura</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-sm b-prop" data-prop="w" data-box="${name}" value="${b.w ?? 0}">
                        </div>
                        <div class="col-3">
                            <label class="small text-muted d-block" style="font-size:10px;">Altura</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-sm b-prop" data-prop="h" data-box="${name}" value="${b.h ?? 0}">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="small text-muted d-block" style="font-size:10px;">Fonte</label>
                            <select class="form-select form-select-sm b-prop" data-prop="font_family" data-box="${name}">
                                <option value="Arial" ${b.font_family === 'Arial' ? 'selected' : ''}>Arial</option>
                                <option value="Inter" ${b.font_family === 'Inter' ? 'selected' : ''}>Inter</option>
                                <option value="Times New Roman" ${b.font_family === 'Times New Roman' ? 'selected' : ''}>Times New Roman</option>
                                <option value="Georgia" ${b.font_family === 'Georgia' ? 'selected' : ''}>Georgia</option>
                                <option value="Courier New" ${b.font_family === 'Courier New' ? 'selected' : ''}>Courier New</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="small text-muted d-block" style="font-size:10px;">Tamanho</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-sm b-prop" data-prop="font_size" data-box="${name}" value="${b.font_size || 12}">
                        </div>
                    </div>
                </div>
            `;
        });

        c.innerHTML = html;

        c.querySelectorAll('.b-prop').forEach(inp => {
            const handleProp = (e) => {
                const bName = e.target.dataset.box;
                const prop  = e.target.dataset.prop;
                let rawVal  = String(e.target.value ?? '').trim().replace(',', '.');
                let val     = rawVal;

                if (['x','y','w','h','font_size'].includes(prop)) {
                    let num = parseFloat(rawVal);
                    val = isNaN(num) ? 0 : Math.round(num * 10) / 10;
                }

                if (boxes[bName]) {
                    boxes[bName][prop] = val;
                    renderBox(bName);
                    document.getElementById('layout_json').value = JSON.stringify(layout);
                    updatePreview();
                }
            };
            inp.addEventListener('input', handleProp);
            inp.addEventListener('change', handleProp);
        });

        c.querySelectorAll('.block-ctrl').forEach(item => {
            item.addEventListener('click', (e) => {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
                selectBox(item.dataset.ctrl);
            });
        });
    };

    const selectBox = (name) => {
        selectedBox = name;
        document.querySelectorAll('.layout-box').forEach(b => b.classList.remove('selected'));
        const el = document.getElementById(`box-${name}`);
        if (el) el.classList.add('selected');

        document.querySelectorAll('.block-ctrl').forEach(bc => {
            bc.classList.toggle('is-selected', bc.dataset.ctrl === name);
        });
    };

    /* ── Arrastar Blocos ── */
    const setupDrag = (name) => {
        const el = document.getElementById(`box-${name}`);
        if (!el) return;

        el.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('rsz-handle')) return;
            selectBox(name);
            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            origBoxX = boxes[name].x;
            origBoxY = boxes[name].y;
            document.body.style.userSelect = 'none';

            const onMove = (mv) => {
                if (!isDragging) return;
                const sc = scale();
                const dx = (mv.clientX - dragStartX) / sc;
                const dy = (mv.clientY - dragStartY) / sc;
                let nx = origBoxX + dx;
                let ny = origBoxY + dy;

                if (chkSnap.checked) {
                    nx = Math.round(nx / 10) * 10;
                    ny = Math.round(ny / 10) * 10;
                }

                nx = Math.max(0, Math.min(PAGE_W - boxes[name].w, nx));
                ny = Math.max(0, Math.min(PAGE_H - boxes[name].h, ny));

                boxes[name].x = nx;
                boxes[name].y = ny;
                renderBox(name);
            };

            const onUp = () => {
                isDragging = false;
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                buildControls();
                document.getElementById('layout_json').value = JSON.stringify(layout);
                updatePreview();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    };

    /* ── Redimensionar Blocos ── */
    const setupResize = (name) => {
        const hnd = document.querySelector(`.rsz-handle[data-rsz="${name}"]`);
        if (!hnd) return;

        hnd.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            selectBox(name);
            isResizing = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            origBoxW = boxes[name].w;
            origBoxH = boxes[name].h;
            document.body.style.userSelect = 'none';

            const onMove = (mv) => {
                if (!isResizing) return;
                const sc = scale();
                const dx = (mv.clientX - dragStartX) / sc;
                const dy = (mv.clientY - dragStartY) / sc;
                let nw = origBoxW + dx;
                let nh = origBoxH + dy;

                if (chkSnap.checked) {
                    nw = Math.round(nw / 10) * 10;
                    nh = Math.round(nh / 10) * 10;
                }

                nw = Math.max(60, Math.min(PAGE_W - boxes[name].x, nw));
                nh = Math.max(30, Math.min(PAGE_H - boxes[name].y, nh));

                boxes[name].w = nw;
                boxes[name].h = nh;
                renderBox(name);
            };

            const onUp = () => {
                isResizing = false;
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                buildControls();
                document.getElementById('layout_json').value = JSON.stringify(layout);
                updatePreview();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    };

    /* ── Renderização do Timbrado de Fundo (PDF / Imagem) ── */
    const renderBg = () => {
        const url = bgInput.value.trim();
        const bPreview = document.getElementById('bg-preview-box');

        if (!url) {
            stageBg.innerHTML = '';
            prevBg.innerHTML  = '';
            if (bPreview) bPreview.innerHTML = '<span class="text-muted small">Nenhum papel timbrado configurado</span>';
            bgTypeIn.value = 'none';
            return;
        }

        const isPdf = url.toLowerCase().endsWith('.pdf') || url.includes('.pdf?');
        bgTypeIn.value = isPdf ? 'pdf' : 'image';

        if (isPdf) {
            if (bPreview) bPreview.innerHTML = `<span class="badge bg-danger-subtle text-danger"><i class="bi bi-file-pdf me-1"></i>PDF Timbrado Vinculado</span>`;
            if (window.pdfjsLib) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
                pdfjsLib.getDocument(url).promise.then(pdf => pdf.getPage(1)).then(page => {
                    const canvasStage = document.createElement('canvas');
                    const canvasPrev  = document.createElement('canvas');
                    canvasStage.width = PAGE_W;
                    canvasStage.height = PAGE_H;
                    canvasPrev.width  = PAGE_W;
                    canvasPrev.height = PAGE_H;

                    const vp = page.getViewport({ scale: PAGE_W / page.getViewport({ scale: 1 }).width });
                    page.render({ canvasContext: canvasStage.getContext('2d'), viewport: vp });
                    page.render({ canvasContext: canvasPrev.getContext('2d'), viewport: vp });

                    stageBg.innerHTML = '';
                    stageBg.appendChild(canvasStage);
                    prevBg.innerHTML = '';
                    prevBg.appendChild(canvasPrev);
                }).catch(() => {
                    stageBg.innerHTML = `<div class="p-3 text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Erro ao renderizar PDF timbrado.</div>`;
                });
            }
        } else {
            stageBg.innerHTML = `<img src="${url}" alt="Papel timbrado" style="width:100%;height:100%;object-fit:fill;">`;
            prevBg.innerHTML  = `<img src="${url}" alt="Papel timbrado" style="width:100%;height:100%;object-fit:fill;">`;
            if (bPreview) bPreview.innerHTML = `<img src="${url}" alt="Preview timbrado">`;
        }
    };

    bgInput.addEventListener('change', renderBg);

    /* ── Upload de Timbrado ── */
    const fileIn = document.getElementById('bg-file-input');
    if (fileIn) {
        fileIn.addEventListener('change', async () => {
            if (!fileIn.files || !fileIn.files[0]) return;
            const file = fileIn.files[0];
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', csrf);

            window.toast.info('Enviando papel timbrado...');
            try {
                const res = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd
                });
                const data = await res.json();
                if (res.ok && data.url) {
                    bgInput.value = data.url;
                    renderBg();
                    window.toast.success('Papel timbrado atualizado!');
                } else {
                    window.toast.error(data.message || 'Falha ao enviar arquivo de timbrado.');
                }
            } catch (err) {
                window.toast.error('Erro de conexão ao enviar arquivo.');
            }
        });
    }

    document.getElementById('btn-upload-bg')?.addEventListener('click', () => fileIn?.click());
    document.getElementById('btn-clear-bg')?.addEventListener('click', () => {
        bgInput.value = '';
        renderBg();
        window.toast.info('Papel timbrado removido.');
    });
    document.getElementById('btn-restore-default-bg')?.addEventListener('click', () => {
        if (defaultBgUrl) {
            bgInput.value = defaultBgUrl;
            renderBg();
            window.toast.success('Papel timbrado padrão restaurado!');
        } else {
            window.toast.info('Nenhum timbrado padrão configurado.');
        }
    });

    /* ── Mini Preview ── */
    const updatePreview = () => {
        if (!prevCont) return;
        const html = (() => {
            try { return jQuery('#tpl-editor').summernote('code'); } catch (_) { return ''; }
        })();

        const sc = (document.getElementById('preview-stage')?.clientWidth || 380) / PAGE_W;
        prevCont.style.transform = `scale(${sc})`;

        if (layout.mode === 'fluid') {
            const m = layout.fluid_margins || { top: 90, bottom: 90, left: 60, right: 60 };
            prevCont.innerHTML = `
                <div style="position:absolute;left:${m.left}px;top:${m.top}px;width:${PAGE_W - m.left - m.right}px;height:${PAGE_H - m.top - m.bottom}px;overflow:hidden;font-size:13px;line-height:1.5;color:#1e293b;">
                    ${html}
                </div>
            `;
        } else {
            const b = boxes.content;
            prevCont.innerHTML = `
                <div style="position:absolute;left:${b.x}px;top:${b.y}px;width:${b.w}px;height:${b.h}px;overflow:hidden;font-family:${b.font_family};font-size:${b.font_size}px;color:${b.color};">
                    ${html}
                </div>
                <div style="position:absolute;left:${boxes.signature.x}px;top:${boxes.signature.y}px;width:${boxes.signature.w}px;height:${boxes.signature.h}px;border-bottom:1.5px solid #000;display:flex;align-items:flex-end;padding-bottom:4px;font-size:11px;color:#64748b;">
                    Assinatura do Cliente
                </div>
                <div style="position:absolute;left:${boxes.signer.x}px;top:${boxes.signer.y}px;width:${boxes.signer.w}px;height:${boxes.signer.h}px;font-size:10px;color:#64748b;">
                    Nome / Documento
                </div>
                <div style="position:absolute;left:${boxes.metadata.x}px;top:${boxes.metadata.y}px;width:${boxes.metadata.w}px;height:${boxes.metadata.h}px;font-size:9px;color:#94a3b8;">
                    Carimbo de autenticação digital &middot; Hash &middot; IP
                </div>
            `;
        }
    };

    document.getElementById('btn-sync-preview-content')?.addEventListener('click', updatePreview);

    /* ── Autocomplete e Seleção de Cliente ── */
    const selectCustomer = (c) => {
        cId.value = c.id;
        sInput.value = '';
        sResults.style.display = 'none';

        document.getElementById('cust-card-name').textContent = c.name;
        document.getElementById('cust-card-meta').textContent = `${c.email || 'Sem e-mail'} · ${c.document || 'Sem documento'} · ${c.whatsapp || c.phone || 'Sem telefone'}`;
        cCard.style.display = 'flex';

        loadCustomerInvoices(c.id);
    };

    document.getElementById('btn-change-customer')?.addEventListener('click', () => {
        cId.value = '';
        cCard.style.display = 'none';
        sInput.value = '';
        sInput.focus();
        invSelect.innerHTML = '<option value="">Nenhuma fatura vinculada</option>';
    });

    const loadCustomerInvoices = async (customerId, selectInvoiceId = null) => {
        try {
            const res = await fetch(`${baseUrl}/customers/${customerId}/invoices`, {
                headers: { 'Accept': 'application/json' }
            });
            const invoices = await res.json();
            invSelect.innerHTML = '<option value="">Nenhuma fatura vinculada</option>';
            if (Array.isArray(invoices) && invoices.length > 0) {
                invoices.forEach(inv => {
                    const opt = document.createElement('option');
                    opt.value = inv.id;
                    const val = parseFloat(inv.amount || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                    opt.textContent = `#${inv.number} · R$ ${val} · Venc: ${inv.due_date || '—'} (${inv.status})`;
                    if (selectInvoiceId && String(inv.id) === String(selectInvoiceId)) {
                        opt.selected = true;
                    }
                    invSelect.appendChild(opt);
                });
            }
        } catch (_) {}
    };

    let searchTimer = null;
    sInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = sInput.value.trim();
        if (q.length < 2) {
            sResults.style.display = 'none';
            return;
        }

        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const list = await res.json();
                if (!Array.isArray(list) || list.length === 0) {
                    sResults.innerHTML = '<div class="p-3 text-muted small text-center">Nenhum cliente encontrado.</div>';
                    sResults.style.display = 'block';
                    return;
                }

                sResults.innerHTML = list.map(c => `
                    <div class="customer-result-item" data-id="${c.id}">
                        <div class="fw-semibold text-dark">${c.name}</div>
                        <div class="small text-muted">
                            ${c.document ? `<span class="me-2">${c.document}</span>` : ''}
                            ${c.email ? `<span class="me-2">${c.email}</span>` : ''}
                            ${c.whatsapp || c.phone ? `<span>${c.whatsapp || c.phone}</span>` : ''}
                        </div>
                    </div>
                `).join('');
                sResults.style.display = 'block';
                sResults.querySelectorAll('.customer-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const target = list.find(x => String(x.id) === String(item.dataset.id));
                        if (target) selectCustomer(target);
                    });
                });
            } catch (_) { sResults.style.display = 'none'; }
        }, 300);
    });

    document.addEventListener('click', (ev) => {
        if (!sInput.contains(ev.target) && !sResults.contains(ev.target)) {
            sResults.style.display = 'none';
        }
    });

    // Se já tiver fatura do contrato selecionada inicialmente
    @if($contract->customer_id)
        loadCustomerInvoices({{ $contract->customer_id }}, {{ $contract->invoice_id ?: 'null' }});
    @endif

    /* ── Inserção de Tags no Summernote ── */
    document.querySelectorAll('[data-insert]').forEach(el => {
        el.addEventListener('click', () => {
            const tag = el.dataset.insert;
            try {
                jQuery('#tpl-editor').summernote('insertText', tag);
                window.toast.info(`Tag ${tag} inserida no texto!`);
            } catch (_) {
                navigator.clipboard.writeText(tag);
                window.toast.success(`Copiado: ${tag}`);
            }
        });
    });

    /* ── Clique para copiar variável ── */
    document.querySelectorAll('[data-copy]').forEach(el => {
        el.addEventListener('click', () => {
            const txt = el.dataset.copy;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(txt).then(() => {
                    window.toast.success(`Copiado: ${txt}`);
                });
            }
        });
    });

    /* ── Salvar Alterações (AJAX) ── */
    document.getElementById('frm-contract').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (!cId.value) {
            window.toast.error('Selecione um cliente para vincular ao contrato.');
            document.getElementById('customer-search').focus();
            return;
        }
        if (!document.getElementById('title').value.trim()) {
            window.toast.error('Informe o título do contrato.');
            document.getElementById('title').focus();
            return;
        }

        const btn = document.getElementById('btn-save');
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando…';

        renderAll();
        const fd = new FormData(document.getElementById('frm-contract'));
        fd.set('content_html', (() => { try { return jQuery('#tpl-editor').summernote('code'); } catch (_) { return ''; } })());

        try {
            const res = await fetch(updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd
            });
            let data = null;
            try { data = await res.json(); } catch (_) { data = { ok: false, error: 'invalid_json' }; }

            if (res.ok && data.ok) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Contrato atualizado com sucesso!',
                    text: 'As alterações nas cláusulas, dados e layout foram salvas.',
                    confirmButtonText: '<i class="bi bi-eye me-1"></i> Visualizar contrato',
                    confirmButtonColor: '#10b981',
                    showCancelButton: true,
                    cancelButtonText: 'Continuar editando',
                    cancelButtonColor: '#6366f1',
                }).then((r) => {
                    if (r.isConfirmed) {
                        window.location = showUrl;
                    }
                });
                return;
            } else if (res.status === 422 && data?.errors) {
                const f = Object.keys(data.errors)[0];
                const msg = Array.isArray(data.errors[f]) ? data.errors[f][0] : data.errors[f];
                window.toast.error(msg || 'Erro de validação.');
            } else {
                const m = data?.message || data?.error || 'Não foi possível salvar o contrato.';
                window.toast.error(m);
            }
        } catch (_) {
            window.toast.error('Erro de conexão ao servidor. Tente novamente.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    });

    /* ── INIT ── */
    const updateStageScale = () => {
        if (!wrap) return;
        const sc = scale();
        stage.style.width = PAGE_W + 'px';
        stage.style.height = PAGE_H + 'px';
        stage.style.margin = '0 auto';
        stage.style.transform = `scale(${sc})`;
        stage.style.transformOrigin = 'top center';
        wrap.style.height = (PAGE_H * sc + 16) + 'px';
    };
    if (window.ResizeObserver) { new ResizeObserver(updateStageScale).observe(wrap); }
    updateStageScale();

    updateModeView();
    buildControls();
    renderBg();
    renderAll();
    Object.keys(boxes).forEach(setupDrag);
    Object.keys(boxes).forEach(setupResize);

    const layoutTab = document.querySelector('[data-bs-target="#tab-layout"]');
    if (layoutTab) {
        layoutTab.addEventListener('shown.bs.tab', () => { updateStageScale(); updatePreview(); }, { once: false });
    }
    setTimeout(updatePreview, 300);

    if (window.adminUI?.bindUpload) {
        window.adminUI.bindUpload(document.getElementById('frm-contract'));
    }
})();
</script>
@endpush
