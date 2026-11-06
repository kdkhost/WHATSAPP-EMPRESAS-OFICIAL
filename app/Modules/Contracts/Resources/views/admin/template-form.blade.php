@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', $mode === 'edit' ? 'Editar template de contrato' : 'Novo template de contrato')

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<style>
/* ── Summernote overrides ── */
#frm-template .note-editor.note-frame           { border-color: var(--bs-border-color) !important; border-radius: 16px !important; overflow: hidden; background: #fff !important; }
#frm-template .note-editor .note-toolbar        { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; border-radius: 16px 16px 0 0 !important; padding: 6px 8px !important; }
#frm-template .note-editor .note-statusbar      { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; border-radius: 0 0 16px 16px !important; }
#frm-template .note-editor .note-editing-area   { background: #fff !important; }
#frm-template .note-editor .note-editable       { min-height: 520px; background: #fff !important; color: #000 !important; font-size: 15px; line-height: 1.7; padding: 32px !important; }
#frm-template .note-editor .note-editable *     { color: #000 !important; }
#frm-template .note-editor .note-editing-area .note-editable *,
.note-editor .note-editing-area .note-editable * { color: #000 !important; }
#frm-template .note-editor .note-btn            { color: var(--bs-body-color) !important; background: var(--bs-body-bg) !important; border-color: var(--bs-border-color) !important; border-radius: 8px !important; padding: 6px 10px !important; font-size: 13px !important; }
#frm-template .note-editor .note-btn:hover      { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-primary) !important; }
#frm-template .note-editor .note-dropdown-menu  { background: var(--bs-body-bg) !important; border: 1px solid var(--bs-border-color) !important; border-radius: 12px !important; box-shadow: 0 12px 30px rgba(0,0,0,.12) !important; padding: 6px !important; }
#frm-template .note-editor .note-dropdown-item  { color: var(--bs-body-color) !important; padding: 7px 12px !important; border-radius: 8px !important; }
#frm-template .note-editor .note-dropdown-item:hover { background: var(--bs-tertiary-bg) !important; }
#frm-template .note-editor .note-modal-content  { background: var(--bs-body-bg) !important; border: 1px solid var(--bs-border-color) !important; border-radius: 16px !important; }
#frm-template .note-editor .note-modal-header,
#frm-template .note-editor .note-modal-footer   { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color) !important; }
#frm-template .note-editor .note-form-label     { color: var(--bs-body-color) !important; font-weight: 600 !important; }
#frm-template .note-editor .note-input          { background: var(--bs-body-bg) !important; border: 1px solid var(--bs-border-color) !important; border-radius: 8px !important; color: var(--bs-body-color) !important; }
#frm-template .note-editor .note-btn-primary    { background: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; }
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
.var-row           { display: flex; justify-content: space-between; align-items: center; padding: 7px 10px; border-radius: 8px; border: 1px solid var(--bs-border-color); font-size: 12.5px; }
.var-row code      { font-size: 11.5px; }
</style>
@endpush

@section('content')
@php
    $layout = $template->layout_json ?: app(\App\Modules\Contracts\Services\ContractService::class)->defaultLayout();
    $contractRoutePrefix = $contractRoutePrefix ?? (request()->is('painel*') ? 'tenant.contracts.' : 'admin.contracts.');
    $contractUploadUrl = $contractUploadUrl ?? route(request()->is('painel*') ? 'tenant.uploads.store' : 'admin.uploads.store', 'contracts');
    $validationAttributes = trans('validation.attributes');
    $validation_messages = [
        'validation.required' => trans('validation.required'),
        'validation.exists'   => trans('validation.exists'),
    ];
@endphp

<div class="container-fluid">

    {{-- HERO --}}
    <div class="contract-premium-hero d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="padding:8px 0 16px;">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route($contractRoutePrefix . 'templates') }}" class="btn btn-outline-secondary btn-sm" style="border-radius:10px;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <span class="cte-eyebrow">Contratos digitais</span>
                <h4 class="mb-0 fw-bold">{{ $mode === 'edit' ? 'Editar template' : 'Novo template' }}</h4>
                <p class="text-muted mb-0 small">Monte o conteúdo, configure o timbrado e posicione cada bloco visualmente.</p>
            </div>
        </div>
        <button type="button" class="btn btn-primary" id="btn-save-template" style="border-radius:12px;padding:10px 24px;font-weight:600;">
            <i class="bi bi-check2-circle me-2"></i>Salvar template
        </button>
    </div>

    <form id="frm-template">
        @csrf
        <input type="hidden" name="layout_json" id="layout_json">

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
                        <div class="cte-card">
                            <div class="cte-card-header"><strong><i class="bi bi-info-circle me-1"></i>Dados do template</strong></div>
                            <div class="cte-card-body">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label fw-semibold">Nome do template <span class="text-danger">*</span></label>
                                        <input name="name" class="form-control" required maxlength="120" value="{{ $template->name }}" placeholder="Ex.: Tráfego pago mensal" style="border-radius:10px;">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">Categoria</label>
                                        <input name="category" class="form-control" maxlength="80" value="{{ $template->category }}" placeholder="Marketing, Serviços…" style="border-radius:10px;">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Descrição interna</label>
                                        <textarea name="description" class="form-control" rows="3" maxlength="1000" placeholder="Resumo do uso deste modelo…" style="border-radius:10px;">{{ $template->description }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" name="require_witness" value="1" id="require_witness" @checked($template->require_witness)>
                                            <label class="form-check-label" for="require_witness">Exigir testemunhas</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked($template->active ?? true)>
                                            <label class="form-check-label" for="active">Disponibilizar para novos contratos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="cte-card">
                            <div class="cte-card-header"><strong><i class="bi bi-images me-1"></i>Papel timbrado</strong></div>
                            <div class="cte-card-body">
                                <div class="cte-upload-zone contract-letterhead-upload mb-3"
                                     data-upload
                                     data-url="{{ $contractUploadUrl }}"
                                     data-max-mb="20"
                                     data-owner-type="contract_template"
                                     data-owner-id="{{ $template->id ?? 0 }}">
                                    <div data-upload-trigger>
                                        <i class="bi bi-cloud-arrow-up cte-upload-icon"></i>
                                        <strong class="d-block mb-1">Arraste ou clique para enviar</strong>
                                        <span class="text-muted small">PNG, JPG, WebP, SVG ou PDF · máx 20 MB</span>
                                    </div>
                                    <input type="file" hidden accept="image/*,.pdf">
                                    <div data-upload-progress></div>
                                </div>
                                <label class="form-label fw-semibold small">URL do arquivo</label>
                                <div class="input-group mb-2">
                                    <input name="background_path" id="background_path" class="form-control form-control-sm"
                                           value="{{ $template->background_path }}"
                                           placeholder="/storage/uploads/contracts/timbrado.png"
                                           style="border-radius:8px 0 0 8px;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-clear-bg" title="Limpar">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <p class="text-muted small mb-3">PNG oferece resultados mais previsíveis no PDF final.</p>
                                <div class="cte-bg-preview" id="bg-preview-thumb">
                                    <span class="text-muted small">Nenhum timbrado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ABA: CONTEÚDO --}}
            <div class="tab-pane fade" id="tab-content">
                <div class="cte-card">
                    <div class="cte-card-header">
                        <strong><i class="bi bi-file-earmark-text me-1"></i>Conteúdo do contrato</strong>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($variables as $token => $label)
                                <button type="button" class="btn btn-sm btn-outline-secondary var-chip" data-var="{{ $token }}" title="{{ $label }}">
                                    <code class="text-reset">{{ $token }}</code>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="cte-card-body">
                        <textarea id="tpl-editor">{!! $template->content_html !!}</textarea>
                    </div>
                </div>
            </div>

            {{-- ABA: LAYOUT NO-CODE --}}
            <div class="tab-pane fade" id="tab-layout">
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="cte-card">
                            <div class="cte-card-body d-flex align-items-center p-3 gap-3">
                                <div>
                                    <strong class="d-block mb-1">Modo de Renderização</strong>
                                    <span class="text-muted small">Escolha a mecânica de geração do documento PDF final.</span>
                                </div>
                                <div class="ms-auto bg-light rounded p-1 d-flex gap-1" style="border: 1px solid var(--bs-border-color);">
                                    <input type="radio" class="btn-check" name="renderMode" id="mode-fixed" value="fixed" autocomplete="off" checked>
                                    <label class="btn btn-sm btn-outline-secondary border-0" for="mode-fixed" style="border-radius:6px;width:140px;">Fixo (Arrastar)</label>
                                    <input type="radio" class="btn-check" name="renderMode" id="mode-fluid" value="fluid" autocomplete="off">
                                    <label class="btn btn-sm btn-outline-secondary border-0" for="mode-fluid" style="border-radius:6px;width:140px;">Fluido (Múltiplas Pág.)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="cte-card mb-4">
                            <div class="cte-card-header">
                                <strong><i class="bi bi-bounding-box me-1"></i>Editor visual</strong>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis small">A4 &middot; 794&times;1123 px</span>
                            </div>
                            <div class="cte-card-body">
                                <p class="text-muted small mb-3 stage-controls">Arraste os blocos ou ajuste os valores nos campos. O contrato final usa estas coordenadas com precisão cirúrgica.</p>
                                <div class="alert alert-info fluid-msg p-3 mb-0" style="border-radius:12px;">
                                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>Modo Fluido Ativo</h6>
                                    <p class="mb-0 small">O editor de arrastar e soltar não é necessário neste modo. O sistema vai construir o documento empilhando as informações, quebrando páginas sempre que o texto preencher a folha, e no final inserirá os blocos habilitados (Assinatura, Signatário e Validação).</p>
                                </div>
                                <div class="fluid-margins-ctrl mt-3 row g-2">
                                    <div class="col-12"><strong class="small text-muted text-uppercase mb-1 d-block">Distanciamento / Margens Livres da Página (px)</strong></div>
                                    <div class="col-3">
                                        <label class="form-label small mb-1">Topo</label>
                                        <input type="number" id="f-margin-t" class="form-control form-control-sm" min="0" value="{{ $layout['fluidMargins']['top'] ?? 40 }}" style="border-radius:8px;">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label small mb-1">Fundo</label>
                                        <input type="number" id="f-margin-b" class="form-control form-control-sm" min="0" value="{{ $layout['fluidMargins']['bottom'] ?? 40 }}" style="border-radius:8px;">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label small mb-1">Esquerda</label>
                                        <input type="number" id="f-margin-l" class="form-control form-control-sm" min="0" value="{{ $layout['fluidMargins']['left'] ?? 40 }}" style="border-radius:8px;">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label small mb-1">Direita</label>
                                        <input type="number" id="f-margin-r" class="form-control form-control-sm" min="0" value="{{ $layout['fluidMargins']['right'] ?? 40 }}" style="border-radius:8px;">
                                    </div>
                                </div>
                                <div class="cte-stage-wrap" id="stage-wrap">
                                    <div class="cte-stage" id="contract-stage">
                                        <div class="cte-stage-bg" id="contract-stage-bg"></div>
                                        <div class="layout-box" data-box="content">Conteúdo</div>
                                        <div class="layout-box" data-box="signature">Assinatura</div>
                                        <div class="layout-box" data-box="signer">Signatário</div>
                                        <div class="layout-box" data-box="metadata">Validação</div>
                                    </div>
                                </div>
                                <div class="stage-controls" style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:10px 0 0;">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="snap-toggle">
                                        <label class="form-check-label small" for="snap-toggle">Snap</label>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="grid-toggle">
                                        <label class="form-check-label small" for="grid-toggle">Grade</label>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label small mb-0 text-nowrap">Opacidade fundo</label>
                                        <input type="range" class="form-range" id="bg-opacity" min="0" max="1" step="0.05" value="{{ $layout['page']['opacity'] ?? 1 }}" style="width:80px;">
                                    </div>
                                    <div class="d-flex align-items-center gap-2 ms-auto">
                                        <label class="form-label small mb-0 text-nowrap">Tam. grade</label>
                                        <input type="number" id="grid-size" class="form-control form-control-sm" value="10" min="1" step="1" style="width:70px;border-radius:8px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="cte-card mb-4">
                            <div class="cte-card-header">
                                <strong><i class="bi bi-eye me-1"></i>Preview em tempo real</strong>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-preview" style="border-radius:8px;"><i class="bi bi-arrow-clockwise"></i></button>
                            </div>
                            <div class="cte-card-body p-2">
                                <div class="cte-preview-wrap">
                                    <div class="cte-preview-stage" id="cte-preview-stage">
                                        <div class="cte-preview-bg" id="cte-preview-bg"></div>
                                        <div class="cte-preview-content" id="cte-preview-content"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="cte-card">
                            <div class="cte-card-header"><strong><i class="bi bi-sliders me-1"></i>Posicionamento dos blocos</strong></div>
                            <div class="cte-card-body"><div class="row g-3" id="layout-controls"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ABA: VARIÁVEIS --}}
            <div class="tab-pane fade" id="tab-vars">
                <div class="cte-card">
                    <div class="cte-card-header"><strong><i class="bi bi-code-square me-1"></i>Variáveis automáticas</strong></div>
                    <div class="cte-card-body">
                        <p class="text-muted small mb-3">Insira estes tokens no conteúdo do contrato. Serão substituídos pelos dados reais de cada cliente ao gerar o documento.</p>
                        <div class="row g-2">
                            @foreach($variables as $token => $label)
                                <div class="col-md-6 col-lg-4">
                                    <div class="var-row"><code>{{ $token }}</code><span class="text-muted ms-2 small">{{ $label }}</span></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
(function () {
    'use strict';
    /* ── Constantes e refs DOM ── */
    const csrf       = document.querySelector('meta[name=csrf-token]').content;
    const layout     = @json($layout);
    const srvMsg     = @json($validation_messages);
    const srvAttr    = @json($validationAttributes);
    const routeUrl   = @json($mode === 'edit' ? route($contractRoutePrefix . 'templates.update', $template->id) : route($contractRoutePrefix . 'templates.store'));
    const httpMethod = @json($mode === 'edit' ? 'PUT' : 'POST');
    const PAGE_W     = Number(layout.page?.width  || 794);
    const PAGE_H     = Number(layout.page?.height || 1123);

    const wrap     = document.getElementById('stage-wrap');
    const stage    = document.getElementById('contract-stage');
    const stageBg  = document.getElementById('contract-stage-bg');
    const bgInput  = document.getElementById('background_path');
    const bgThumb  = document.getElementById('bg-preview-thumb');
    const controls = document.getElementById('layout-controls');
    const pvStage  = document.getElementById('cte-preview-stage');
    const pvBg     = document.getElementById('cte-preview-bg');
    const pvCnt    = document.getElementById('cte-preview-content');

    const boxes = {
        content:   stage.querySelector('[data-box="content"]'),
        signature: stage.querySelector('[data-box="signature"]'),
        signer:    stage.querySelector('[data-box="signer"]'),
        metadata:  stage.querySelector('[data-box="metadata"]'),
    };
    const initBox = (obj) => ({
        x: obj?.x||0, y: obj?.y||0, w: obj?.w||100, h: obj?.h||50, enabled: obj?.enabled!==false,
        fontFamily: obj?.fontFamily || 'Arial, sans-serif',
        fontSize: obj?.fontSize || 12,
        color: obj?.color || '#000000'
    });

    const state = {
        mode:         layout.mode || 'fixed',
        fluidMargins: layout.fluidMargins || { top: 40, right: 40, bottom: 40, left: 40 },
        content:      initBox(layout.content),
        signature:    initBox(layout.signature),
        signer:       initBox(layout.signer),
        metadata:     initBox(layout.metadata),
    };

    let _drag = false, _pvTimer = null, _pdfTok = 0, _sel = null;
    let GRID = 10, SNAP = false;
    window._cteOpacity = layout.page?.opacity ?? 1;

    // Alternar modo
    const tabLayout = document.getElementById('tab-layout');
    const updateModeView = () => {
        const mode = document.querySelector('input[name="renderMode"]:checked').value;
        state.mode = mode;
        if(mode === 'fluid') tabLayout.classList.add('mode-fluid');
        else tabLayout.classList.remove('mode-fluid');
        renderAll();
    };
    document.querySelectorAll('input[name="renderMode"]').forEach(r => {
        if(r.value === state.mode) r.checked = true;
        r.addEventListener('change', updateModeView);
    });

    /* ── Helpers ── */
    const prec = (v) => Math.round(Number(v||0)*100)/100;
    function clamp(name, n) {
        const w=Math.max(1,prec(n.w)), h=Math.max(1,prec(n.h));
        return { x:prec(Math.max(0,Math.min(PAGE_W-w,n.x))), y:prec(Math.max(0,Math.min(PAGE_H-h,n.y))), w:prec(Math.min(w,PAGE_W)), h:prec(Math.min(h,PAGE_H)) };
    }
    function snap(c) {
        const g=Math.max(0.1,parseFloat(window._cteGrid||1)||1);
        return {x:prec(Math.round(c.x/g)*g),y:prec(Math.round(c.y/g)*g),w:prec(Math.round(c.w/g)*g),h:prec(Math.round(c.h/g)*g)};
    }
    const scale = () => { 
        if(!wrap) return 1; 
        const maxW = (wrap.clientWidth - 32) / PAGE_W;
        const maxH = (window.innerHeight * 0.70) / PAGE_H; 
        return Math.max(0.3, Math.min(1.0, maxW, maxH)); // No máximo 1.0 (tamanho real), ou até caber em 70% da tela
    };
    const transErr = (k,a) => (srvMsg[k]||k).replace(':attribute',srvAttr[a]||a);
    function eff() {
        const e=JSON.parse(JSON.stringify(state));
        if(window._cteSnap) Object.keys(e).forEach((k)=>{ e[k]={...clamp(k,snap(e[k])),enabled:e[k].enabled}; });
        return e;
    }
    function schedPv(d) { clearTimeout(_pvTimer); _pvTimer=setTimeout(updatePreview,d!=null?d:120); }

    /* ── Summernote ── */
    jQuery('#tpl-editor').summernote({
        height:520, placeholder:'Escreva o contrato, insira cláusulas e use os tokens de variáveis.',
        toolbar:[['style',['style','bold','italic','underline','clear']],['font',['fontsize','color']],['para',['ul','ol','paragraph','height']],['table',['table']],['insert',['link','hr']],['view',['fullscreen','codeview']]],
    });
    function normSN(){
        const ed=document.querySelector('.note-editor .note-editable'); if(!ed) return;
        ed.style.setProperty('background-color','#fff','important'); ed.style.setProperty('color','#111','important');
        ed.querySelectorAll('*').forEach((n)=>{if(n.style){n.style.setProperty('color','#111','important');if(n.style.backgroundColor&&n.style.backgroundColor!=='transparent')n.style.backgroundColor='transparent';}});
    }
    normSN(); setTimeout(normSN,400);
    jQuery('#tpl-editor').on('summernote.change summernote.paste summernote.keyup',()=>{normSN();schedPv(300);});
    document.querySelectorAll('.var-chip').forEach((b)=>b.addEventListener('click',()=>jQuery('#tpl-editor').summernote('insertText',b.dataset.var)));

    /* ── renderAll ── */
    function renderAll() {
        const e=eff();
        Object.keys(boxes).forEach((n)=>{
            const c=clamp(n,e[n]), b=boxes[n];
            if(e[n].enabled===false){b.style.display='none';return;}
            b.style.display='flex'; b.style.left=c.x+'px'; b.style.top=c.y+'px'; b.style.width=c.w+'px'; b.style.height=c.h+'px';
        });
        const e2=eff();
        const getBox = (k) => ({
            ...clamp(k, e2[k]), enabled: e2[k].enabled !== false, 
            fontFamily: e2[k].fontFamily, fontSize: e2[k].fontSize, color: e2[k].color
        });
        document.getElementById('layout_json').value=JSON.stringify({
            mode: state.mode,
            fluidMargins: state.fluidMargins,
            page:{width:PAGE_W,height:PAGE_H,padding:layout.page?.padding||48,opacity:window._cteOpacity},
            content:   getBox('content'),
            signature: getBox('signature'),
            signer:    getBox('signer'),
            metadata:  getBox('metadata'),
        });
        if(!_drag) schedPv();
    }

    /* ── buildControls ── */
    function buildControls() {
        const LBL={content:'Conteúdo',signature:'Assinatura',signer:'Signatário',metadata:'Validação'};
        const CLR={content:'primary',signature:'success',signer:'warning',metadata:'danger'};
        const fonts = [
            {v:'Arial, sans-serif', l:'Arial'}, {v:'"Times New Roman", Times, serif', l:'Times New Roman'},
            {v:'"Courier New", Courier, monospace', l:'Courier'}, {v:'"DejaVu Sans", sans-serif', l:'DejaVu Sans'}, {v:'Helvetica, sans-serif', l:'Helvetica'}
        ];
        controls.innerHTML=Object.keys(state).map((k)=>{
            const opts = fonts.map(f => `<option value='${f.v}' ${state[k].fontFamily===f.v?'selected':''}>${f.l}</option>`).join('');
            return `
            <div class="col-12"><div class="block-ctrl" id="ctrl-${k}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-${CLR[k]}-subtle text-${CLR[k]}-emphasis" style="border-radius:6px;font-size:11px;">${LBL[k]}</span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input box-enable" type="checkbox" data-box="${k}" ${state[k].enabled!==false?'checked':''} id="en-${k}">
                        <label class="form-check-label small" for="en-${k}">Ativo</label>
                    </div>
                </div>
                <div class="row g-2 mb-2 layout-input-row">
                    ${['x','y','w','h'].map((f)=>`<div class="col-3"><label class="form-label small text-uppercase fw-bold text-muted mb-1">${f}</label><input type="text" inputmode="decimal" class="form-control form-control-sm layout-input" data-box="${k}" data-field="${f}" value="${state[k][f]}" style="border-radius:8px;text-align:center;"></div>`).join('')}
                </div>
                <div class="row g-2 pt-2 border-top">
                    <div class="col-5">
                        <label class="form-label small text-muted mb-1" style="font-size:11px;">Fonte</label>
                        <select class="form-select form-select-sm layout-style" data-box="${k}" data-field="fontFamily" style="border-radius:8px;font-size:11px;">${opts}</select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1" style="font-size:11px;">Tam. (px)</label>
                        <input type="text" inputmode="decimal" class="form-control form-control-sm layout-style" data-box="${k}" data-field="fontSize" value="${state[k].fontSize}" style="border-radius:8px;text-align:center;font-size:11px;">
                    </div>
                    <div class="col-3">
                        <label class="form-label small text-muted mb-1" style="font-size:11px;">Cor</label>
                        <input type="color" class="form-control form-control-color form-control-sm layout-style w-100" data-box="${k}" data-field="color" value="${state[k].color}" style="border-radius:8px;padding:2px;height:26px;">
                    </div>
                </div>
            </div></div>`;
        }).join('');
        controls.querySelectorAll('.layout-input').forEach((i)=>i.addEventListener('input',()=>{
            state[i.dataset.box][i.dataset.field]=Math.max(0,prec(parseFloat(String(i.value||'0').replace(',', '.'))));
            state[i.dataset.box]=clamp(i.dataset.box,state[i.dataset.box]); renderAll();
        }));
        controls.querySelectorAll('.layout-style').forEach((i)=>i.addEventListener('input',()=>{
            state[i.dataset.box][i.dataset.field]=i.value; renderAll();
        }));
        controls.querySelectorAll('.box-enable').forEach((c)=>c.addEventListener('change',()=>{state[c.dataset.box].enabled=c.checked;renderAll();}));
    }

    function syncCtrl(n){
        controls.querySelectorAll(`.layout-input[data-box="${n}"]`).forEach((i)=>{i.value=state[n][i.dataset.field];});
        controls.querySelectorAll(`.layout-style[data-box="${n}"]`).forEach((i)=>{i.value=state[n][i.dataset.field];});
    }

    /* ── Seleção ── */
    function selBox(n){
        _sel=n;
        Object.keys(boxes).forEach((k)=>{
            boxes[k].classList.toggle('selected',k===n);
            const c=document.getElementById('ctrl-'+k); if(c)c.classList.toggle('is-selected',k===n);
        });
        controls.querySelectorAll('.layout-input').forEach((i)=>{i.disabled=!!n&&i.dataset.box!==n;});
    }
    Object.keys(boxes).forEach((n)=>boxes[n].addEventListener('click',(e)=>{selBox(n);e.stopPropagation();}));
    document.addEventListener('click',()=>selBox(null));
    document.addEventListener('keydown',(e)=>{
        if(!_sel)return; const s=e.shiftKey?10:1;
        const map={ArrowLeft:{x:-s},ArrowRight:{x:s},ArrowUp:{y:-s},ArrowDown:{y:s}};
        if(!map[e.key])return; e.preventDefault();
        state[_sel]=clamp(_sel,{...state[_sel],...{x:state[_sel].x+(map[e.key].x||0),y:state[_sel].y+(map[e.key].y||0)}});
        syncCtrl(_sel); renderAll();
    });

    /* ── Drag ── */
    function setupDrag(bn){
        const el=boxes[bn]; let pid=null,sx=0,sy=0,bx=0,by=0,fr=null;
        el.addEventListener('pointerdown',(ev)=>{
            if(ev.target.classList.contains('rsz-handle'))return;
            _drag=true;pid=ev.pointerId;sx=ev.clientX;sy=ev.clientY;bx=state[bn].x;by=state[bn].y;
            selBox(bn);el.setPointerCapture(ev.pointerId);ev.preventDefault();
        });
        el.addEventListener('pointermove',(ev)=>{
            if(ev.pointerId!==pid)return;
            const sc=scale(); let nx=clamp(bn,{...state[bn],x:bx+(ev.clientX-sx)/sc,y:by+(ev.clientY-sy)/sc});
            if(window._cteSnap)nx=clamp(bn,snap(nx));
            state[bn]=nx;syncCtrl(bn);
            if(fr)cancelAnimationFrame(fr); fr=requestAnimationFrame(renderAll);
        });
        ['pointerup','pointercancel','lostpointercapture'].forEach((ev)=>el.addEventListener(ev,()=>{
            if(pid===null)return; pid=null;_drag=false;
            if(fr){cancelAnimationFrame(fr);fr=null;} renderAll();schedPv(60);
        }));
    }

    /* ── Resize ── */
    function setupResize(bn){
        const el=boxes[bn]; const h=document.createElement('div');h.className='rsz-handle';el.appendChild(h);
        let pid=null,sx=0,sy=0,w0=0,h0=0,fr=null;
        h.addEventListener('pointerdown',(ev)=>{
            ev.stopPropagation();_drag=true;pid=ev.pointerId;sx=ev.clientX;sy=ev.clientY;w0=state[bn].w;h0=state[bn].h;
            selBox(bn);h.setPointerCapture(ev.pointerId);ev.preventDefault();
        });
        h.addEventListener('pointermove',(ev)=>{
            if(pid===null||ev.pointerId!==pid)return;
            const sc=scale(); 
            let nx = clamp(bn,{...state[bn],w:Math.max(20,w0+(ev.clientX-sx)/sc),h:Math.max(20,h0+(ev.clientY-sy)/sc)});
            if(window._cteSnap) nx = clamp(bn, snap(nx));
            state[bn] = nx;
            syncCtrl(bn); if(fr)cancelAnimationFrame(fr);fr=requestAnimationFrame(renderAll);
        });
        ['pointerup','pointercancel','lostpointercapture'].forEach((ev)=>h.addEventListener(ev,()=>{
            if(pid===null)return;pid=null;_drag=false;if(fr){cancelAnimationFrame(fr);fr=null;}renderAll();schedPv(60);
        }));
    }

    /* ── Background ── */
    async function pdfTo(url,tgt,maxW){
        const pj=window.pdfjsLib;if(!pj)return;
        pj.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
        try{
            const pg=(await (await pj.getDocument(url).promise).getPage(1));
            const v0=pg.getViewport({scale:1}),sc=maxW/v0.width,vp=pg.getViewport({scale:sc});
            const c=document.createElement('canvas');c.width=vp.width;c.height=vp.height;
            await pg.render({canvasContext:c.getContext('2d'),viewport:vp}).promise;
            tgt.innerHTML='';tgt.appendChild(c);
        }catch(_){}
    }
    function renderBg(){
        const val=bgInput.value.trim();const tok=++_pdfTok;
        if(!val){bgThumb.innerHTML='<span class="text-muted small">Nenhum timbrado</span>';stageBg.innerHTML='';return;}
        if(val.toLowerCase().includes('.pdf')){
            bgThumb.innerHTML='<span class="text-muted small">Carregando PDF…</span>';
            pdfTo(val,bgThumb,bgThumb.clientWidth||300).then(()=>{if(tok!==_pdfTok)bgThumb.innerHTML='';});
            pdfTo(val,stageBg,PAGE_W).then(()=>{if(tok!==_pdfTok)stageBg.innerHTML='';});
        }else{
            bgThumb.innerHTML=`<img src="${val}" alt="Timbrado">`;
            stageBg.innerHTML=`<img src="${val}" alt="" style="width:100%;height:100%;object-fit:fill;display:block;">`;
        }
    }
    bgInput.addEventListener('input',()=>{renderBg();schedPv(100);});
    document.getElementById('btn-clear-bg').addEventListener('click',()=>{bgInput.value='';renderBg();schedPv(50);});
    document.querySelector('[data-upload]')?.addEventListener('upload:done',(ev)=>{
        let r=ev.detail?.response||{};if(typeof r==='string'){try{r=JSON.parse(r);}catch(_){r={};}}
        if(r.url){bgInput.value=r.url;renderBg();schedPv(100);}
    });

    /* ── Preview ── */
    function updatePreview(){
        if(!pvCnt||!pvBg||!pvStage)return;
        pvBg.style.opacity = window._cteOpacity;
        const rect=pvStage.getBoundingClientRect();
        const sc=rect.width>0?rect.width/PAGE_W:1;
        pvBg.style.transform=`scale(${sc})`;pvCnt.style.transform=`scale(${sc})`;
        const bg=bgInput.value.trim();
        if(bg&&bg.toLowerCase().includes('.pdf')){
            if(!pvBg.querySelector('canvas[data-pv]')){
                pvBg.innerHTML='<canvas data-pv style="display:block;"></canvas>';
                const c=pvBg.querySelector('canvas'),pj=window.pdfjsLib;
                if(pj&&c){pj.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
                pj.getDocument(bg).promise.then((d)=>d.getPage(1)).then((p)=>{
                    const v0=p.getViewport({scale:1}),vp=p.getViewport({scale:PAGE_W/v0.width});
                    c.width=vp.width;c.height=vp.height;p.render({canvasContext:c.getContext('2d'),viewport:vp});
                }).catch(()=>{});}
            }
        }else if(bg){
            const cur=pvBg.querySelector('img');
            if(!cur||cur.getAttribute('src')!==bg)pvBg.innerHTML=`<img src="${bg}" style="width:794px;height:1123px;object-fit:fill;display:block;" alt="">`;
        }else{pvBg.innerHTML='';}
        const e=eff();
        const html=(()=>{try{return jQuery('#tpl-editor').summernote('code');}catch(_){return '';}})();
        
        if(state.mode === 'fluid') {
            pvCnt.style.boxSizing = 'border-box';
            pvCnt.style.padding = `${state.fluidMargins.top||0}px ${state.fluidMargins.right||0}px ${state.fluidMargins.bottom||0}px ${state.fluidMargins.left||0}px`;
        } else {
            pvCnt.style.padding = '0';
        }

        const blk=(k,inner)=>{
            if(e[k].enabled===false)return '';
            if(state.mode === 'fluid') {
                const style = `font-family:${e[k].fontFamily}; font-size:${e[k].fontSize}px; color:${e[k].color}; margin-top:20px;`;
                return `<div style="${style}">${inner}</div>`;
            } else {
                const c=clamp(k,e[k]);
                const style = `font-family:${e[k].fontFamily}; font-size:${e[k].fontSize}px; color:${e[k].color};`;
                return `<div style="position:absolute;left:${c.x}px;top:${c.y}px;width:${c.w}px;height:${c.h}px;overflow:hidden;box-sizing:border-box;${style}">${inner}</div>`;
            }
        };
        pvCnt.innerHTML=
            blk('content',  `<div style="line-height:1.7;">${html}</div>`)+
            blk('signature',`<div style="font-size:0.9em;font-weight:800;text-transform:uppercase;letter-spacing:.08em;opacity:0.7;margin-bottom:4px;">Assinatura</div><div style="border:1.5px dashed currentColor;border-radius:8px;height:calc(100% - 20px);display:flex;align-items:center;justify-content:center;opacity:0.5;">Campo de assinatura</div>`)+
            blk('signer',   `<div style="font-size:0.9em;font-weight:800;text-transform:uppercase;letter-spacing:.08em;opacity:0.7;margin-bottom:4px;">Signatário</div><div>Nome · CPF · E-mail</div>`)+
            blk('metadata', `<div style="font-size:0.9em;font-weight:800;text-transform:uppercase;letter-spacing:.08em;opacity:0.7;margin-bottom:4px;">Validação</div><div>Status · Data/Hora · IP · Token</div>`);
    }
    document.getElementById('btn-refresh-preview')?.addEventListener('click',updatePreview);

    /* ── Toolbar ── */
    window._cteSnap=false;window._cteGrid=10; window._cteOpacity=parseFloat(document.getElementById('bg-opacity')?.value||'1');
    document.getElementById('snap-toggle')?.addEventListener('change',(ev)=>{window._cteSnap=ev.target.checked;renderAll();});
    const applyGrid=()=>{const g=Math.max(5,Math.min(200,parseFloat(window._cteGrid||10)||10)); stage.style.setProperty('--grid-size', `${g}px`);};
    document.getElementById('grid-toggle')?.addEventListener('change',(ev)=>{stage.classList.toggle('show-grid',ev.target.checked);applyGrid();});
    document.getElementById('bg-opacity')?.addEventListener('input',(ev)=>{
        window._cteOpacity = parseFloat(ev.target.value);
        if(stageBg) stageBg.style.opacity = window._cteOpacity;
        renderAll();
    });
    document.getElementById('grid-size')?.addEventListener('input',(ev)=>{window._cteGrid=Math.max(0.1,parseFloat(ev.target.value||'10')||10);applyGrid();renderAll();});
    
    // Bind margins
    const bindMargin = (id, key) => {
        document.getElementById(id)?.addEventListener('input', (ev) => {
            state.fluidMargins[key] = parseFloat(ev.target.value || 0);
            updatePreview();
        });
    };
    bindMargin('f-margin-t', 'top');
    bindMargin('f-margin-b', 'bottom');
    bindMargin('f-margin-l', 'left');
    bindMargin('f-margin-r', 'right');

    // Inicializar visualização do modo selecionado
    updateModeView();

    /* ── Salvar ── */
    document.getElementById('btn-save-template').addEventListener('click',async()=>{
        const btn=document.getElementById('btn-save-template');const ori=btn.innerHTML;
        btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Salvando…';
        renderAll();
        const fd=new FormData(document.getElementById('frm-template'));
        fd.set('content_html',(()=>{try{return jQuery('#tpl-editor').summernote('code');}catch(_){return '';}})()); 
        if(!fd.has('require_witness'))fd.set('require_witness','0');
        if(!fd.has('active'))fd.set('active','0');
        if(httpMethod==='PUT')fd.set('_method','PUT');
        try{
            const res=await fetch(routeUrl,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
            let data=null;try{data=await res.json();}catch(_){data={ok:false,error:'invalid_json'};}
            if(res.ok&&data.ok){window.toast.success('Template salvo com sucesso!');if(data.redirect){window.location=data.redirect;return;}}
            else if(res.status===422&&data?.errors){const f=Object.keys(data.errors)[0];const r=Array.isArray(data.errors[f])?data.errors[f][0]:data.errors[f];window.toast.error(r?.includes('validation.')?transErr(r,f):(r||'Erro de validação'));}
            else{const m=data?.message||data?.error||'Não foi possível salvar.';window.toast.error(m.includes('validation.')?transErr(m,'name'):m);}
        }catch(_){window.toast.error('Erro de conexão. Tente novamente.');}
        finally{btn.disabled=false;btn.innerHTML=ori;}
    });

    /* ── INIT ── */
    // Resize observer para escala fluida do stage
    const updateStageScale = () => {
        if(!wrap)return;
        const sc = scale();
        stage.style.width = PAGE_W + 'px';
        stage.style.height = PAGE_H + 'px';
        stage.style.margin = '0 auto';
        stage.style.transform = `scale(${sc})`;
        stage.style.transformOrigin = 'top center';
        wrap.style.height = (PAGE_H * sc + 16) + 'px';
    };
    if(window.ResizeObserver) { new ResizeObserver(updateStageScale).observe(wrap); }
    updateStageScale();
    if(stageBg) stageBg.style.opacity = window._cteOpacity;

    buildControls();
    renderBg();
    renderAll();
    Object.keys(boxes).forEach(setupDrag);
    Object.keys(boxes).forEach(setupResize);

    // Preview: aguarda a aba Layout ser visível para calcular escala correta
    const layoutTab = document.querySelector('[data-bs-target="#tab-layout"]');
    if (layoutTab) {
        layoutTab.addEventListener('shown.bs.tab', () => { updatePreview(); }, { once: false });
    }
    // Primeira render do preview (pode estar na aba Layout por padrão)
    setTimeout(updatePreview, 300);

    // Bind de upload via adminUI global
    if (window.adminUI?.bindUpload) {
        window.adminUI.bindUpload(document.getElementById('frm-template'));
    }
})();
</script>
@endpush
