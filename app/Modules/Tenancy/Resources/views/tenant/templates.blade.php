@extends('tenancy::tenant.layout')
@section('title', 'Templates WhatsApp - White Label')

@section('content')
@php
    $categories = \App\Modules\Templates\Models\WhatsAppTemplate::categories();
    $offsets = \App\Modules\Templates\Models\WhatsAppTemplate::scheduleOffsets();
    $varGroups = \App\Modules\Templates\Models\WhatsAppTemplate::availableVariables();

    $customizedCount = $templates->where('is_customized', true)->count();
    $sharedCount = $templates->where('is_customized', false)->count();
    $activeCount = $templates->where('active', true)->count();

    $templatePayload = $templates->map(fn ($tpl) => [
        'id' => $tpl->id,
        'system_id' => $tpl->system_id ?? null,
        'tenant_id' => $tpl->tenant_id,
        'parent_id' => $tpl->parent_id ?? null,
        'name' => $tpl->name,
        'slug' => $tpl->slug,
        'category' => $tpl->category,
        'category_label' => $tpl->category_label ?? $tpl->category,
        'schedule_offset' => $tpl->schedule_offset,
        'content_html' => $tpl->content_html,
        'content_text' => $tpl->content_text,
        'active' => (bool) $tpl->active,
        'is_system' => (bool) $tpl->is_system,
        'is_customized' => (bool) $tpl->is_customized,
        'is_preferred' => (bool) ($tpl->is_preferred ?? false),
    ])->values();
@endphp

<div class="tenant-premium-hero mb-4">
    <div>
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-shield-lock me-2"></i>White Label & Personalização por Perfil</span>
        <h3 class="tenant-premium-hero__title">Templates de WhatsApp</h3>
        <p class="tenant-premium-hero__text">
            Os modelos globais são compartilhados com todos os usuários como base homologada. Quando você edita qualquer modelo, a personalização é aplicada <strong>exclusivamente à sua empresa</strong>, mantendo o padrão intacto para os outros usuários e garantindo total integridade da sua marca.
        </p>
    </div>
    <div class="row g-3">
        <div class="col-4">
            <div class="tenant-premium-metric">
                <div class="text-muted small text-uppercase fw-semibold">Padrão Compartilhado</div>
                <div class="fs-4 fw-bold text-secondary">{{ $sharedCount }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="tenant-premium-metric">
                <div class="text-muted small text-uppercase fw-semibold">Personalizados (White Label)</div>
                <div class="fs-4 fw-bold text-success">{{ $customizedCount }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="tenant-premium-metric">
                <div class="text-muted small text-uppercase fw-semibold">Ativos para Disparo</div>
                <div class="fs-4 fw-bold text-primary">{{ $activeCount }}</div>
            </div>
        </div>
    </div>
</div>

<div class="tenant-premium-panel">
    <div class="tenant-premium-panel__header flex-wrap gap-3">
        <div>
            <span class="tenant-premium-kicker">Gerenciamento Dinâmico</span>
            <h5 class="tenant-premium-title">Catálogo de Mensagens ({{ $templates->count() }})</h5>
            <p class="tenant-premium-subtitle">Você pode personalizar livremente o texto de qualquer modelo ou criar mensagens avulsas adicionais.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTemplate">
                <i class="bi bi-plus-lg me-1"></i> Criar Mensagem Avulsa
            </button>
        </div>
    </div>

    {{-- Filtro Rápido de Categorias --}}
    <div class="px-4 py-3 bg-body-tertiary border-bottom d-flex align-items-center gap-2 flex-wrap">
        <span class="small fw-semibold text-muted me-1"><i class="bi bi-funnel me-1"></i>Filtrar:</span>
        <button type="button" class="btn btn-sm btn-outline-secondary active js-filter-cat" data-cat="all">Todos ({{ $templates->count() }})</button>
        <button type="button" class="btn btn-sm btn-outline-success js-filter-cat" data-cat="customized_only">Personalizados ({{ $customizedCount }})</button>
        <button type="button" class="btn btn-sm btn-outline-secondary js-filter-cat" data-cat="shared_only">Compartilhados ({{ $sharedCount }})</button>
        <span class="text-muted opacity-50 mx-1">|</span>
        @foreach($categories as $catKey => $catName)
            @php $countInCat = $templates->where('category', $catKey)->count(); @endphp
            @if($countInCat > 0)
                <button type="button" class="btn btn-sm btn-outline-secondary js-filter-cat" data-cat="{{ $catKey }}">
                    {{ $catName }} <span class="badge bg-secondary-subtle text-body ms-1">{{ $countInCat }}</span>
                </button>
            @endif
        @endforeach
    </div>

    <div class="tenant-premium-panel__body p-0">
        <div>
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-templates" data-export-title="Templates de Mensagem" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Modelo / Finalidade</th>
                        <th class="desktop">White Label & Origem</th>
                        <th class="min-tablet">Categoria</th>
                        <th class="desktop">Agendamento</th>
                        <th class="min-tablet" data-priority="2">Status</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 150px; min-width: 140px; max-width: 170px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tpl)
                        @php
                            $isCustomized = (bool) $tpl->is_customized;
                            $isPreferred = (bool) ($tpl->is_preferred ?? false);
                            $badgeClass = $tpl->category_badge ?? 'secondary';
                        @endphp
                        <tr id="tpl-row-{{ $tpl->id }}" data-category="{{ $tpl->category }}" data-customized="{{ $isCustomized ? '1' : '0' }}">
                            <td>
                                <div class="fw-semibold text-dark">{{ $tpl->name }}</div>
                                <small class="text-muted font-monospace">{{ $tpl->slug }}</small>
                            </td>
                            <td>
                                @if($isCustomized)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-brush me-1"></i>Personalizado (White Label)
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        <i class="bi bi-globe me-1"></i>Padrão Compartilhado
                                    </span>
                                @endif

                                @if($isPreferred)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" title="Modelo ativo preferencial da categoria">
                                        <i class="bi bi-star-fill me-1"></i>Padrão
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} border border-{{ $badgeClass }}-subtle">
                                    {{ $tpl->category_label }}
                                </span>
                            </td>
                            <td>
                                @if($tpl->schedule_offset)
                                    <span class="badge bg-body-tertiary text-body border font-monospace">{{ $tpl->schedule_offset }}</span>
                                @else
                                    <span class="badge bg-light text-muted border">Disparo Direto</span>
                                @endif
                            </td>
                            <td>
                                @if($isCustomized)
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input toggle-active" type="checkbox" data-id="{{ $tpl->id }}" {{ $tpl->active ? 'checked' : '' }} title="Ativar ou desativar mensagem">
                                    </div>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Ativo</span>
                                @endif
                            </td>
                            <td class="all text-end no-export" style="width: 150px; min-width: 140px; max-width: 170px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    {{-- Visualizar / Preview estilo WhatsApp --}}
                                    <button type="button" class="btn btn-sm btn-outline-info p-1 px-2 btn-preview-tpl" data-id="{{ $tpl->id }}" title="Visualizar no WhatsApp">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    {{-- Editar / Personalizar --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2 btn-edit-tpl" data-id="{{ $tpl->id }}" title="{{ $isCustomized ? 'Editar sua personalização' : 'Personalizar este modelo' }}">
                                        <i class="bi bi-pencil{{ $isCustomized ? '' : '-square' }}"></i>
                                    </button>

                                    {{-- Restaurar Padrão Global do Sistema (se for personalizado) --}}
                                    @if($isCustomized && !empty($tpl->parent_id))
                                        <button type="button" class="btn btn-sm btn-outline-warning p-1 px-2 btn-reset-tpl" data-id="{{ $tpl->id }}" title="Restaurar modelo compartilhado original">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    @endif

                                    {{-- Definir como Padrão --}}
                                    <button type="button" class="btn btn-sm {{ $isPreferred ? 'btn-warning' : 'btn-outline-warning' }} p-1 px-2 btn-default-tpl"
                                            data-id="{{ $tpl->id }}" {{ $isPreferred ? 'disabled' : '' }}
                                            title="Definir como preferido para a categoria">
                                        <i class="bi bi-star{{ $isPreferred ? '-fill' : '' }}"></i>
                                    </button>

                                    {{-- Excluir (somente avulsos sem parent_id) --}}
                                    @if($isCustomized && empty($tpl->parent_id))
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 btn-delete-tpl" data-id="{{ $tpl->id }}" title="Excluir mensagem avulsa">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-chat-square-text display-5 d-block mb-2"></i>
                                Nenhum template cadastrado no momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL EDITAR / PERSONALIZAR TEMPLATE --}}
<div class="modal fade" id="modalEditTemplate" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="edit-modal-title">
                        <i class="bi bi-brush me-2 text-primary"></i>Personalizar Modelo de WhatsApp
                    </h5>
                    <div class="small text-muted" id="edit-modal-subtitle">Isolamento White Label por Empresa</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Banner Informativo White Label --}}
                <div class="alert alert-info d-flex align-items-center mb-3 py-2" id="edit-whitelabel-banner">
                    <i class="bi bi-info-circle-fill fs-4 me-2"></i>
                    <div class="small" id="edit-whitelabel-text">
                        Você está personalizando este modelo. Ao salvar, uma cópia exclusiva será aplicada às suas mensagens, sem modificar os modelos dos outros usuários.
                    </div>
                </div>

                <form id="frm-edit-template" class="row g-3">
                    <input type="hidden" name="id" id="edit-tpl-id">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nome do Modelo <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit-tpl-name" class="form-control" required maxlength="120">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Categoria <span class="text-danger">*</span></label>
                        <select name="category" id="edit-tpl-category" class="form-select" required>
                            @foreach($categories as $catKey => $catLabel)
                                <option value="{{ $catKey }}">{{ $catLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Agendamento (Offset)</label>
                        <select name="schedule_offset" id="edit-tpl-offset" class="form-select">
                            <option value="">Sem agendamento (Disparo manual/avulso)</option>
                            @foreach($offsets as $offKey => $offLabel)
                                <option value="{{ $offKey }}">{{ $offKey }} - {{ $offLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Variáveis Disponíveis --}}
                    <div class="col-12">
                        <div class="card bg-body-tertiary border-0 p-2">
                            <div class="small fw-bold text-muted mb-1"><i class="bi bi-braces me-1"></i>Clique na variável para inseri-la no cursor:</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($varGroups as $gKey => $gVal)
                                    @if(is_array($gVal) || is_iterable($gVal))
                                        @foreach($gVal as $vKey => $vDesc)
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-edit-tag" data-tag="{{ $vKey }}" title="{{ $vDesc }}">
                                                {{ $vKey }}
                                            </button>
                                        @endforeach
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-edit-tag" data-tag="{{ $gKey }}" title="{{ $gVal }}">
                                            {{ $gKey }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Conteúdo da Mensagem (WhatsApp) <span class="text-danger">*</span></label>
                        <textarea name="content_html" id="edit-tpl-html" class="form-control font-monospace" rows="8" required></textarea>
                        <div class="form-text">
                            Sintaxe nativa WhatsApp: <code>*negrito*</code>, <code>_itálico_</code>, <code>~tachado~</code>, <code>```monoespaçado```</code>.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Texto Puro / Fallback (Opcional)</label>
                        <textarea name="content_text" id="edit-tpl-text" class="form-control font-monospace" rows="3" placeholder="Se deixar vazio, o sistema converterá automaticamente o conteúdo acima."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-save-template">
                    <i class="bi bi-check2-circle me-1"></i>Salvar Personalização
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL CRIAR MENSAGEM AVULSA --}}
<div class="modal fade" id="modalCreateTemplate" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Nova Mensagem Avulsa de WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-create-template" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nome do Modelo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="120" placeholder="Ex: Promoção de Renovação Anual">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Categoria <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            @foreach($categories as $catKey => $catLabel)
                                <option value="{{ $catKey }}">{{ $catLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Agendamento (Offset)</label>
                        <select name="schedule_offset" class="form-select">
                            <option value="">Sem agendamento (Disparo manual/avulso)</option>
                            @foreach($offsets as $offKey => $offLabel)
                                <option value="{{ $offKey }}">{{ $offKey }} - {{ $offLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Variáveis Disponíveis --}}
                    <div class="col-12">
                        <div class="card bg-body-tertiary border-0 p-2">
                            <div class="small fw-bold text-muted mb-1"><i class="bi bi-braces me-1"></i>Clique em uma variável para inseri-la no texto:</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($varGroups as $gKey => $gVal)
                                    @if(is_array($gVal) || is_iterable($gVal))
                                        @foreach($gVal as $vKey => $vDesc)
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-create-tag" data-tag="{{ $vKey }}" title="{{ $vDesc }}">
                                                {{ $vKey }}
                                            </button>
                                        @endforeach
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-create-tag" data-tag="{{ $gKey }}" title="{{ $gVal }}">
                                            {{ $gKey }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Conteúdo da Mensagem (WhatsApp) <span class="text-danger">*</span></label>
                        <textarea name="content_html" id="create-content-html" class="form-control font-monospace" rows="8" required placeholder="Olá *{primeiro_nome}*, tudo bem?&#10;&#10;Sua mensagem personalizada aqui..."></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Texto Puro / Fallback (Opcional)</label>
                        <textarea name="content_text" id="create-content-text" class="form-control font-monospace" rows="3" placeholder="Se deixar vazio, o sistema converterá automaticamente mantendo negritos e quebras de linha."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-create-template"><i class="bi bi-check2-circle me-1"></i>Criar Mensagem</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL PREVIEW ESTILO WHATSAPP SMARTPHONE --}}
<div class="modal fade" id="modalPreviewTemplate" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 bg-transparent">
            <div class="modal-body p-0">
                <div class="wa-phone-container shadow-lg">
                    <div class="wa-chat-header">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="wa-avatar">C</div>
                            <div class="min-w-0">
                                <div class="wa-contact-name text-truncate">Carlos Alberto Silva</div>
                                <div class="wa-status"><span class="wa-status-dot"></span> online</div>
                            </div>
                        </div>
                        <div class="wa-header-icons d-flex align-items-center gap-3">
                            <i class="bi bi-camera-video-fill"></i>
                            <i class="bi bi-telephone-fill"></i>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                    </div>

                    <div class="wa-chat-body" style="min-height: 380px;">
                        <div class="text-center my-2">
                            <span class="wa-date-pill">HOJE</span>
                        </div>
                        <div class="wa-bubble-wrapper">
                            <div class="wa-bubble">
                                <div class="wa-bubble-text" id="wa-preview-body">
                                    <span class="text-muted fst-italic">Carregando visualização...</span>
                                </div>
                                <div class="wa-bubble-meta">
                                    <span class="wa-bubble-time" id="wa-preview-clock">10:55</span>
                                    <span class="wa-bubble-ticks">✓✓</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wa-chat-footer">
                        <div class="wa-footer-icon"><i class="bi bi-emoji-smile"></i></div>
                        <div class="wa-input-fake">Mensagem</div>
                        <div class="wa-footer-icon"><i class="bi bi-paperclip"></i></div>
                        <div class="wa-mic-btn"><i class="bi bi-mic-fill"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
/* Summernote Gourmet - Integração com tema escuro e claro */
.note-editor.note-frame {
    border: 1px solid var(--bs-border-color) !important;
    border-radius: 14px !important;
    overflow: hidden !important;
    background: var(--bs-body-bg) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.note-editor .note-toolbar {
    background: var(--bs-tertiary-bg) !important;
    border-bottom: 1px solid var(--bs-border-color) !important;
    padding: 6px 10px !important;
}
.note-editor .note-statusbar {
    background: var(--bs-tertiary-bg) !important;
    border-top: 1px solid var(--bs-border-color) !important;
}
.note-editor .note-btn {
    background: var(--bs-body-bg) !important;
    border: 1px solid var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
    border-radius: 8px !important;
    padding: 5px 9px !important;
    font-size: 0.84rem !important;
}
.note-editor .note-btn:hover,
.note-editor .note-btn:focus {
    background: var(--bs-primary-subtle) !important;
    border-color: var(--bs-primary) !important;
    color: var(--bs-primary) !important;
}
.note-editor .note-btn.active {
    background: var(--bs-primary) !important;
    color: #fff !important;
    border-color: var(--bs-primary) !important;
}
.note-editor .note-editing-area {
    background: var(--bs-body-bg) !important;
}
.note-editor .note-editable {
    background: var(--bs-body-bg) !important;
    color: var(--bs-body-color) !important;
    min-height: 220px;
    font-size: 0.95rem;
    line-height: 1.6;
    padding: 16px !important;
}
.note-editor .note-editable p {
    margin-bottom: 0.5rem;
}
.note-editor .note-placeholder {
    padding: 16px !important;
    color: var(--bs-secondary-color) !important;
}
.note-editor .dropdown-menu {
    background: var(--bs-body-bg) !important;
    border: 1px solid var(--bs-border-color) !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2) !important;
    color: var(--bs-body-color) !important;
    padding: 6px !important;
}
.note-editor .dropdown-item {
    color: var(--bs-body-color) !important;
    border-radius: 6px;
    padding: 6px 10px;
}
.note-editor .dropdown-item:hover {
    background: var(--bs-tertiary-bg) !important;
    color: var(--bs-primary) !important;
}
.note-modal {
    z-index: 1065 !important;
}
.note-modal-backdrop {
    z-index: 1060 !important;
}
.note-dropdown-menu {
    z-index: 1070 !important;
}
.note-editor .note-modal-content {
    background: var(--bs-body-bg) !important;
    border: 1px solid var(--bs-border-color) !important;
    border-radius: 16px !important;
    color: var(--bs-body-color) !important;
}
.note-editor .note-modal-header,
.note-editor .note-modal-footer {
    background: var(--bs-tertiary-bg) !important;
    border-color: var(--bs-border-color) !important;
}
.note-editor .note-form-label {
    color: var(--bs-body-color) !important;
}

.wa-phone-container {
    border-radius: 20px;
    overflow: hidden;
    background-color: #efeae2;
    display: flex;
    flex-direction: column;
}
[data-bs-theme="dark"] .wa-phone-container {
    background-color: #0b141a;
}
.wa-chat-header {
    background: #005c4b;
    color: #ffffff;
    padding: .75rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
[data-bs-theme="dark"] .wa-chat-header {
    background: #1f2c34;
}
.wa-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #25d366;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.wa-contact-name {
    font-size: .9rem;
    font-weight: 600;
    line-height: 1.2;
}
.wa-status {
    font-size: .72rem;
    color: #aebac1;
    display: flex;
    align-items: center;
    gap: 4px;
}
.wa-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #25d366;
}
.wa-header-icons {
    color: #e9edef;
    font-size: .95rem;
}
.wa-chat-body {
    padding: 1rem;
    background-color: #efeae2;
    background-image: radial-gradient(circle, rgba(0,0,0,0.035) 1px, transparent 1px);
    background-size: 16px 16px;
    display: flex;
    flex-direction: column;
}
[data-bs-theme="dark"] .wa-chat-body {
    background-color: #0b141a;
    background-image: radial-gradient(circle, rgba(255,255,255,0.025) 1px, transparent 1px);
}
.wa-date-pill {
    background: rgba(255, 255, 255, 0.85);
    color: #54656f;
    font-size: .7rem;
    font-weight: 600;
    padding: .2rem .6rem;
    border-radius: 6px;
}
[data-bs-theme="dark"] .wa-date-pill {
    background: #182229;
    color: #8696a0;
}
.wa-bubble-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: .5rem;
    margin-bottom: .5rem;
}
.wa-bubble {
    position: relative;
    max-width: 90%;
    background: #d9fdd3;
    color: #111b21;
    border-radius: 8px;
    border-top-right-radius: 0;
    padding: .5rem .65rem .35rem .65rem;
    box-shadow: 0 1px .5px rgba(11,20,26,.13);
    word-break: break-word;
}
[data-bs-theme="dark"] .wa-bubble {
    background: #005c4b;
    color: #e9edef;
}
.wa-bubble::after {
    content: '';
    position: absolute;
    top: 0;
    right: -8px;
    width: 0;
    height: 0;
    border-top: 10px solid #d9fdd3;
    border-right: 8px solid transparent;
}
[data-bs-theme="dark"] .wa-bubble::after {
    border-top-color: #005c4b;
}
.wa-bubble-text {
    font-size: .84rem;
    line-height: 1.42;
    white-space: pre-wrap;
}
.wa-bubble-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
    margin-left: 20px;
    float: right;
}
.wa-bubble-time {
    font-size: .65rem;
    color: #667781;
}
[data-bs-theme="dark"] .wa-bubble-time {
    color: #8696a0;
}
.wa-bubble-ticks {
    font-size: .75rem;
    color: #53bdeb;
    font-weight: 700;
    letter-spacing: -2px;
}
.wa-chat-footer {
    background: #f0f2f5;
    padding: .5rem .75rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
[data-bs-theme="dark"] .wa-chat-footer {
    background: #202c33;
}
.wa-footer-icon {
    color: #54656f;
    font-size: 1.1rem;
}
[data-bs-theme="dark"] .wa-footer-icon {
    color: #8696a0;
}
.wa-input-fake {
    flex: 1;
    background: #ffffff;
    border-radius: 8px;
    padding: .4rem .75rem;
    font-size: .8rem;
    color: #8696a0;
}
[data-bs-theme="dark"] .wa-input-fake {
    background: #2a3942;
}
.wa-mic-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #00a884;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
}
</style>
@endpush

@push('scripts')
{{-- Dependências do Summernote Lite e jQuery --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-pt-BR.min.js"></script>

<script>
const templateData = @json($templatePayload);
const templateMap = new Map(templateData.map(item => [String(item.id), item]));
const csrfToken = '{{ csrf_token() }}';

// Filtros rápidos
document.querySelectorAll('.js-filter-cat').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.js-filter-cat').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.cat;
        document.querySelectorAll('#table-templates tbody tr').forEach(row => {
            if (cat === 'all') {
                row.style.display = '';
            } else if (cat === 'customized_only') {
                row.style.display = row.dataset.customized === '1' ? '' : 'none';
            } else if (cat === 'shared_only') {
                row.style.display = row.dataset.customized === '0' ? '' : 'none';
            } else {
                row.style.display = row.dataset.category === cat ? '' : 'none';
            }
        });
    });
});

// Configuração e inicialização resiliente do Summernote
function getSummernoteConfig(placeholder) {
    return {
        lang: 'pt-BR',
        height: 230,
        placeholder: placeholder || 'Digite a mensagem do WhatsApp...',
        dialogsInBody: true,
        toolbar: [
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link']],
            ['view', ['codeview', 'help']]
        ]
    };
}

function initSummernoteEditors(retry = 0) {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.summernote === 'undefined') {
        if (retry < 50) {
            setTimeout(() => initSummernoteEditors(retry + 1), 60);
        }
        return;
    }

    const $edit = jQuery('#edit-tpl-html');
    if ($edit.length && !$edit.next('.note-editor').length) {
        $edit.summernote(getSummernoteConfig('Digite o conteúdo da mensagem do WhatsApp...'));
    }

    const $create = jQuery('#create-content-html');
    if ($create.length && !$create.next('.note-editor').length) {
        $create.summernote(getSummernoteConfig('Olá *{primeiro_nome}*, tudo bem?\n\nSua mensagem personalizada aqui...'));
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initSummernoteEditors());
} else {
    initSummernoteEditors();
}

// Inserção de tags no cursor (compatível com Summernote e Fallback Textarea)
function insertTag(textarea, tag) {
    if (!textarea) return;
    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || 0;
    const val = textarea.value || '';
    textarea.value = val.substring(0, start) + tag + val.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
}

function insertIntoEditor(selector, tag) {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
        const $el = jQuery(selector);
        if ($el.next('.note-editor').length) {
            $el.summernote('insertText', tag);
            return;
        }
    }
    insertTag(document.querySelector(selector), tag);
}

document.querySelectorAll('.js-insert-create-tag').forEach(btn => {
    btn.addEventListener('click', () => insertIntoEditor('#create-content-html', btn.dataset.tag));
});

document.querySelectorAll('.js-insert-edit-tag').forEach(btn => {
    btn.addEventListener('click', () => insertIntoEditor('#edit-tpl-html', btn.dataset.tag));
});

// Formatação visual do WhatsApp
function formatWhatsAppHtml(text) {
    if (!text) return '';
    let escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    escaped = escaped.replace(/\*([^\*\n]+)\*/g, '<strong>$1</strong>');
    escaped = escaped.replace(/_([^_\n]+)_/g, '<em>$1</em>');
    escaped = escaped.replace(/~([^~\n]+)~/g, '<del>$1</del>');
    escaped = escaped.replace(/```([^`]+)```/g, '<code class="p-1 rounded bg-black bg-opacity-10">$1</code>');
    escaped = escaped.replace(/^> (.+)$/gm, '<span class="d-block ps-2 my-1 border-start border-3 border-success opacity-75">$1</span>');
    escaped = escaped.replace(/\n/g, '<br>');
    return escaped;
}

// Preview Modal
const previewModal = new bootstrap.Modal(document.getElementById('modalPreviewTemplate'));
document.querySelectorAll('.btn-preview-tpl').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const now = new Date();
        document.getElementById('wa-preview-clock').textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        document.getElementById('wa-preview-body').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
        previewModal.show();

        try {
            const r = await fetch(`{{ url('/painel/templates') }}/${id}/preview`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d.ok && d.rendered_text) {
                document.getElementById('wa-preview-body').innerHTML = formatWhatsAppHtml(d.rendered_text);
            } else {
                document.getElementById('wa-preview-body').textContent = 'Não foi possível carregar a prévia.';
            }
        } catch (e) {
            document.getElementById('wa-preview-body').textContent = 'Erro de comunicação ao obter preview.';
        }
    });
});

// Abertura do Modal de Edição / Personalização White Label
document.querySelectorAll('.btn-edit-tpl').forEach(btn => {
    btn.addEventListener('click', () => {
        const tpl = templateMap.get(String(btn.dataset.id));
        if (!tpl) return;

        document.getElementById('edit-tpl-id').value = tpl.id;
        document.getElementById('edit-tpl-name').value = tpl.name || '';
        document.getElementById('edit-tpl-category').value = tpl.category || 'custom';
        document.getElementById('edit-tpl-offset').value = tpl.schedule_offset || '';

        const contentHtml = tpl.content_html || '';
        document.getElementById('edit-tpl-html').value = contentHtml;
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            const $edit = jQuery('#edit-tpl-html');
            if ($edit.next('.note-editor').length) {
                $edit.summernote('code', contentHtml);
            }
        }
        document.getElementById('edit-tpl-text').value = tpl.content_text || '';

        const titleEl = document.getElementById('edit-modal-title');
        const bannerTextEl = document.getElementById('edit-whitelabel-text');

        if (tpl.is_customized) {
            titleEl.innerHTML = '<i class="bi bi-brush me-2 text-success"></i>Editar Personalização White Label';
            bannerTextEl.innerHTML = 'Você já possui uma versão exclusiva desta mensagem configurada para sua empresa. Suas alterações continuarão ativas apenas para os seus clientes.';
        } else {
            titleEl.innerHTML = '<i class="bi bi-shield-check me-2 text-primary"></i>Personalizar Modelo para sua Empresa';
            bannerTextEl.innerHTML = '<strong>Cópia Exclusiva White Label:</strong> Este modelo atualmente é compartilhado globalmente. Ao salvar, uma cópia exclusiva será criada apenas para o seu perfil comercial, mantendo o modelo original intacto para os demais usuários.';
        }

        const editModal = new bootstrap.Modal(document.getElementById('modalEditTemplate'));
        editModal.show();

        // Garante sincronia do código no Summernote mesmo se terminou renderização logo após show
        setTimeout(() => {
            initSummernoteEditors();
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
                const $edit = jQuery('#edit-tpl-html');
                if ($edit.next('.note-editor').length) {
                    $edit.summernote('code', contentHtml);
                }
            }
        }, 120);
    });
});

// Salvar Personalização White Label via AJAX
document.getElementById('btn-save-template').addEventListener('click', async () => {
    const form = document.getElementById('frm-edit-template');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const id = document.getElementById('edit-tpl-id').value;
    const data = Object.fromEntries(new FormData(form).entries());

    // Obter conteúdo formatado do Summernote
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
        const $edit = jQuery('#edit-tpl-html');
        if ($edit.next('.note-editor').length) {
            data.content_html = $edit.summernote('code');
        }
    }

    const btn = document.getElementById('btn-save-template');
    btn.disabled = true;

    try {
        const r = await fetch(`{{ url('/painel/templates') }}/${id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            Swal.fire({
                icon: 'success',
                title: d.is_fork ? 'White Label Criado!' : 'Personalização Salva!',
                text: d.message || 'Seu modelo exclusivo está pronto e ativo.',
                timer: 1600,
                showConfirmButton: false
            });
            setTimeout(() => location.reload(), 1100);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Falha na Personalização',
                text: d.error || d.message || 'Verifique os dados informados.',
            });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: 'Não foi possível salvar.' });
    }
    btn.disabled = false;
});

// Restaurar Padrão Compartilhado Global
document.querySelectorAll('.btn-reset-tpl').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Restaurar Padrão Global?',
            text: 'Sua personalização exclusiva será removida e sua empresa voltará a utilizar o modelo compartilhado padrão do sistema.',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Sim, restaurar padrão',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d'
        });
        if (!confirm.isConfirmed) return;

        btn.disabled = true;
        try {
            const r = await fetch(`{{ url('/painel/templates') }}/${id}/restaurar`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Padrão Restaurado!',
                    text: d.message || 'Seu perfil voltou a usar o modelo compartilhado.',
                    timer: 1500,
                    showConfirmButton: false
                });
                setTimeout(() => location.reload(), 1000);
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: d.message || 'Não foi possível restaurar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: 'Falha ao restaurar.' });
        }
        btn.disabled = false;
    });
});

// Limpar Summernote ao abrir modal de criação
const modalCreateEl = document.getElementById('modalCreateTemplate');
if (modalCreateEl) {
    modalCreateEl.addEventListener('show.bs.modal', () => {
        setTimeout(() => {
            initSummernoteEditors();
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
                const $create = jQuery('#create-content-html');
                if ($create.next('.note-editor').length) {
                    $create.summernote('code', '');
                }
            }
        }, 120);
    });
}

// Criar Mensagem Avulsa via AJAX
document.getElementById('btn-create-template').addEventListener('click', async () => {
    const form = document.getElementById('frm-create-template');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const fd = new FormData(form);

    // Obter conteúdo formatado do Summernote
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
        const $create = jQuery('#create-content-html');
        if ($create.next('.note-editor').length) {
            fd.set('content_html', $create.summernote('code'));
        }
    }

    const btn = document.getElementById('btn-create-template');
    btn.disabled = true;

    try {
        const r = await fetch('{{ route("tenant.templates.store") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: fd
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            Swal.fire({
                icon: 'success',
                title: 'Mensagem Criada!',
                text: d.message || 'Template registrado com sucesso.',
                timer: 1500,
                showConfirmButton: false
            });
            setTimeout(() => location.reload(), 1000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro ao Criar',
                text: d.error || d.message || 'Verifique os campos informados.',
            });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: 'Não foi possível salvar o template.' });
    }
    btn.disabled = false;
});

// Toggle Ativar/Desativar
document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', async () => {
        const id = toggle.dataset.id;
        try {
            const r = await fetch(`{{ url('/painel/templates') }}/${id}`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ active: toggle.checked })
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                if (window.toast) {
                    window.toast.success(toggle.checked ? 'Mensagem ativada.' : 'Mensagem desativada.');
                }
            } else {
                toggle.checked = !toggle.checked;
                Swal.fire({ icon: 'error', title: 'Erro', text: d.error || d.message || 'Não foi possível alterar status.' });
            }
        } catch (e) {
            toggle.checked = !toggle.checked;
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: 'Não foi possível comunicar com o servidor.' });
        }
    });
});

// Definir como Padrão
document.querySelectorAll('.btn-default-tpl').forEach(btn => {
    btn.addEventListener('click', async () => {
        try {
            const r = await fetch(`{{ url('/painel/templates') }}/${btn.dataset.id}/padrao`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Padrão Definido!',
                    text: 'Este modelo agora é o preferencial da sua categoria.',
                    timer: 1400,
                    showConfirmButton: false
                });
                setTimeout(() => location.reload(), 1000);
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: d.error || d.message || 'Falha ao definir como padrão.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: 'Falha de comunicação.' });
        }
    });
});

// Excluir Template Avulso
document.querySelectorAll('.btn-delete-tpl').forEach(btn => {
    btn.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Excluir mensagem avulsa?',
            text: 'Esta ação não poderá ser desfeita.',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!ok.isConfirmed) return;

        try {
            const r = await fetch(`{{ url('/painel/templates') }}/${btn.dataset.id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                document.getElementById(`tpl-row-${btn.dataset.id}`)?.remove();
                Swal.fire({ icon: 'success', title: 'Excluído!', text: 'Mensagem removida com sucesso.', timer: 1200, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Erro ao Excluir', text: d.error || d.message || 'Falha ao excluir.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro de Conexão', text: 'Falha ao comunicar com o servidor.' });
        }
    });
});
</script>
@endpush
