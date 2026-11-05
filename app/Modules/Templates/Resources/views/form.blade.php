@extends('admin.layouts.app')

@section('title', $template->exists ? 'Editar template' : 'Novo template')

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
.template-form-page .form-control,
.template-form-page .form-select {
    min-height: 46px;
}
/* ==========================================================================
   SIMULADOR DE SMARTPHONE WHATSAPP ULTRA-REALISTA
   ========================================================================== */
.template-preview-device {
    position: sticky;
    top: 90px;
}
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
    height: 650px;
    max-height: 84vh;
}
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
.wa-phone-home-bar {
    width: 120px;
    height: 4px;
    background: rgba(255, 255, 255, 0.45);
    border-radius: 4px;
    margin: 4px auto 2px auto;
    flex-shrink: 0;
}
.var-pill-btn {
    font-size: 0.8rem;
    padding: 3px 8px;
    border-radius: 8px;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    color: var(--bs-body-color);
    transition: all 0.15s ease;
}
.var-pill-btn:hover {
    background: var(--bs-primary-subtle);
    border-color: var(--bs-primary);
    color: var(--bs-primary);
}
.wa-format-toolbar button {
    font-size: 0.85rem;
}

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
    min-height: 250px;
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
.note-editor .note-input {
    background: var(--bs-body-bg) !important;
    border: 1px solid var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
    border-radius: 8px !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid template-form-page">
    <x-ui.page-header
        :title="$template->exists ? 'Editar template WhatsApp' : 'Novo template WhatsApp'"
        :subtitle="$template->exists ? 'Ajuste nome, categoria, régua de disparo e formatação do modelo selecionado.' : 'Crie um novo modelo para garantir que todas as mensagens disparadas sejam padronizadas e auditáveis.'"
        eyebrow="Mensageria WhatsApp">
        <x-slot:actions>
            <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar à lista
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Modo</span>
                <span class="dashboard-note__value">{{ $template->exists ? 'Edição' : 'Criação' }}</span>
            </div>
            @if($template->slug)
                <div class="dashboard-note">
                    <span class="dashboard-note__label">Slug</span>
                    <span class="dashboard-note__value font-monospace small">{{ $template->slug }}</span>
                </div>
            @endif
        </x-slot:aside>
    </x-ui.page-header>

    <form id="frm-template" data-id="{{ $template->id ?? '' }}" class="row g-4">
        @csrf
        @if($template->exists)
            @method('PUT')
        @endif

        {{-- Coluna 1: Edição e Variáveis --}}
        <div class="col-lg-7">
            <x-ui.premium-card title="Definições do Template" subtitle="Identificação, categorização e régua do disparo.">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nome do template <span class="text-danger">*</span></label>
                        <input name="name" class="form-control" required maxlength="120" placeholder="Ex: Lembrete 3 dias antes" value="{{ old('name', $template->name) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Categoria <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            @foreach ($categories as $k => $v)
                                <option value="{{ $k }}" @selected(old('category', $template->category) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Régua / Disparo</label>
                        <select name="schedule_offset" class="form-select">
                            @foreach ($scheduleOffsets as $k => $v)
                                <option value="{{ $k }}" @selected(old('schedule_offset', $template->schedule_offset) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Toolbar de Formatação Rápida WhatsApp --}}
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label fw-semibold mb-0">Conteúdo da Mensagem <span class="text-danger">*</span></label>
                    <div class="wa-format-toolbar btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-wa-tag="bold" title="Negrito (*texto*)"><i class="bi bi-type-bold"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-wa-tag="italic" title="Itálico (_texto_)"><i class="bi bi-type-italic"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-wa-tag="strike" title="Tachado (~texto~)"><i class="bi bi-type-strikethrough"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-wa-tag="code" title="Código (```texto```)"><i class="bi bi-code-slash"></i></button>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea name="content_html" id="editor">{!! old('content_html', $template->content_html) !!}</textarea>
                </div>

                <div class="row align-items-center pt-2 border-top">
                    <div class="col-md-6">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', $template->active ?? true))>
                            <label class="form-check-label fw-semibold" for="active">Template ativo e disponível para disparos</label>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary" id="btn-save-template">
                                <i class="bi bi-check2-circle me-1"></i> Salvar Template
                            </button>
                        </div>
                    </div>
                </div>
            </x-ui.premium-card>

            {{-- Variáveis Disponíveis com Inserção Dinâmica --}}
            <x-ui.premium-card title="Variáveis Dinâmicas do Sistema" subtitle="Clique em qualquer variável para inseri-la diretamente no cursor do editor.">
                <div class="accordion" id="accordionVariables">
                    {{-- Grupo 1: Cliente --}}
                    <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 bg-body-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseClient">
                                <i class="bi bi-person-circle text-primary me-2"></i><strong>Dados do Cliente</strong>
                            </button>
                        </h2>
                        <div id="collapseClient" class="accordion-collapse collapse show">
                            <div class="accordion-body p-3 d-flex flex-wrap gap-2">
                                @foreach(['{nome}' => 'Nome completo', '{primeiro_nome}' => 'Primeiro nome', '{email}' => 'E-mail', '{telefone}' => 'WhatsApp/Telefone', '{documento}' => 'CPF/CNPJ'] as $vKey => $vDesc)
                                    <button type="button" class="var-pill-btn js-insert-var" data-var="{{ $vKey }}" title="{{ $vDesc }}">
                                        <strong>{{ $vKey }}</strong> <span class="text-muted small">({{ $vDesc }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Grupo 2: Fatura e Cobrança --}}
                    <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 bg-body-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBilling">
                                <i class="bi bi-receipt text-success me-2"></i><strong>Fatura, Valores e Pagamento</strong>
                            </button>
                        </h2>
                        <div id="collapseBilling" class="accordion-collapse collapse">
                            <div class="accordion-body p-3 d-flex flex-wrap gap-2">
                                @foreach([
                                    '{numero_fatura}' => 'Número da fatura',
                                    '{valor}' => 'Valor total',
                                    '{valor_original}' => 'Valor original',
                                    '{valor_atualizado}' => 'Valor com atraso',
                                    '{vencimento}' => 'Data vencimento',
                                    '{dias_atraso}' => 'Dias atraso',
                                    '{link_pagamento}' => 'Link checkout',
                                    '{boleto_url}' => 'Link do boleto',
                                    '{linha_digitavel}' => 'Linha digitável',
                                    '{forma_pagamento}' => 'Forma pagamento',
                                    '{data_pagamento}' => 'Data de confirmação',
                                    '{descricao}' => 'Descrição da fatura',
                                ] as $vKey => $vDesc)
                                    <button type="button" class="var-pill-btn js-insert-var" data-var="{{ $vKey }}" title="{{ $vDesc }}">
                                        <strong>{{ $vKey }}</strong> <span class="text-muted small">({{ $vDesc }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Grupo 3: Pix --}}
                    <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 bg-body-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePix">
                                <i class="bi bi-qr-code text-teal me-2"></i><strong>PIX e QR Code</strong>
                            </button>
                        </h2>
                        <div id="collapsePix" class="accordion-collapse collapse">
                            <div class="accordion-body p-3 d-flex flex-wrap gap-2">
                                @foreach([
                                    '{pix_copia_cola}'   => 'Código Pix Copia e Cola',
                                    '{qrcode_img}'       => 'Tag HTML com imagem do QR Code',
                                    '{qrcode_url}'       => 'URL/DataURI da imagem QR Code',
                                    '{qrcode_base64}'    => 'Base64 puro da imagem',
                                    '{qrcode_pix}'       => 'Link/Imagem do QR Code Pix',
                                    '{qrcode_instrucao}' => 'Instrução do QR Code',
                                ] as $vKey => $vDesc)
                                    <button type="button" class="var-pill-btn js-insert-var" data-var="{{ $vKey }}" title="{{ $vDesc }}">
                                        <strong>{{ $vKey }}</strong> <span class="text-muted small">({{ $vDesc }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Grupo 4: Serviços e Pacotes --}}
                    <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 bg-body-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePackages">
                                <i class="bi bi-box-seam text-info me-2"></i><strong>Serviços, Pacotes e Parcelas</strong>
                            </button>
                        </h2>
                        <div id="collapsePackages" class="accordion-collapse collapse">
                            <div class="accordion-body p-3 d-flex flex-wrap gap-2">
                                @foreach([
                                    '{nome_servico}' => 'Nome do serviço',
                                    '{servico_principal}' => 'Serviço principal',
                                    '{parcela_atual}' => 'Nº da parcela',
                                    '{parcelas_total}' => 'Total parcelas',
                                    '{resumo_parcelas}' => 'Resumo (ex: 2/12)',
                                    '{proximo_vencimento}' => 'Próximo vencimento',
                                    '{status_servico}' => 'Status do serviço',
                                ] as $vKey => $vDesc)
                                    <button type="button" class="var-pill-btn js-insert-var" data-var="{{ $vKey }}" title="{{ $vDesc }}">
                                        <strong>{{ $vKey }}</strong> <span class="text-muted small">({{ $vDesc }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Grupo 5: Contratos --}}
                    <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 bg-body-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseContracts">
                                <i class="bi bi-file-earmark-text text-warning me-2"></i><strong>Contratos e Assinaturas</strong>
                            </button>
                        </h2>
                        <div id="collapseContracts" class="accordion-collapse collapse">
                            <div class="accordion-body p-3 d-flex flex-wrap gap-2">
                                @foreach([
                                    '{contrato_numero}' => 'Número do contrato',
                                    '{contrato_titulo}' => 'Título do contrato',
                                    '{link_assinatura}' => 'Link para assinar',
                                    '{link_contrato}' => 'Link da via do contrato',
                                    '{data_assinatura}' => 'Data da assinatura',
                                ] as $vKey => $vDesc)
                                    <button type="button" class="var-pill-btn js-insert-var" data-var="{{ $vKey }}" title="{{ $vDesc }}">
                                        <strong>{{ $vKey }}</strong> <span class="text-muted small">({{ $vDesc }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Grupo 6: Empresa e Suporte --}}
                    <div class="accordion-item border-0 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3 bg-body-tertiary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCompany">
                                <i class="bi bi-building text-secondary me-2"></i><strong>Empresa, Avisos e Suporte</strong>
                            </button>
                        </h2>
                        <div id="collapseCompany" class="accordion-collapse collapse">
                            <div class="accordion-body p-3 d-flex flex-wrap gap-2">
                                @foreach([
                                    '{empresa}' => 'Nome da empresa',
                                    '{whatsapp_suporte}' => 'WhatsApp do suporte',
                                    '{email_suporte}' => 'E-mail do suporte',
                                    '{telefone_suporte}' => 'Telefone do suporte',
                                    '{titulo}' => 'Título de aviso',
                                    '{mensagem}' => 'Mensagem de aviso',
                                ] as $vKey => $vDesc)
                                    <button type="button" class="var-pill-btn js-insert-var" data-var="{{ $vKey }}" title="{{ $vDesc }}">
                                        <strong>{{ $vKey }}</strong> <span class="text-muted small">({{ $vDesc }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.premium-card>
        </div>

        {{-- Coluna 2: Live Preview no Smartphone WhatsApp --}}
        <div class="col-lg-5">
            <div class="template-preview-device">
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
                            <i class="bi bi-arrow-left wa-header-back-btn" title="Voltar"></i>
                            <div class="wa-header-avatar">
                                <i class="bi bi-whatsapp"></i>
                                <span class="wa-verified-badge"><i class="bi bi-check"></i></span>
                            </div>
                            <div class="wa-header-info">
                                <div class="wa-header-name" id="preview-title">{{ $template->name ?: 'Template WhatsApp' }}</div>
                                <div class="wa-header-status">
                                    <span class="badge-online-dot"></span> online
                                </div>
                            </div>
                            <div class="wa-header-actions">
                                <i class="bi bi-camera-video" title="Chamada de vídeo"></i>
                                <i class="bi bi-telephone" title="Chamada de voz"></i>
                                <i class="bi bi-three-dots-vertical" title="Mais opções"></i>
                            </div>
                        </div>

                        <!-- Área de Conversa com Wallpaper Oficial do WhatsApp -->
                        <div class="wa-chat-body">
                            <!-- Pílula de Data -->
                            <div class="wa-date-pill">HOJE</div>

                            <!-- Selo de Criptografia de Ponta a Ponta -->
                            <div class="wa-encryption-pill">
                                <i class="bi bi-lock-fill me-1 text-warning"></i> As mensagens são protegidas com a criptografia de ponta a ponta.
                            </div>

                            <!-- Balão de Mensagem Recebida do WhatsApp -->
                            <div class="wa-bubble-received">
                                <div id="preview-content">
                                    <em class="text-muted">Comece a digitar no editor para visualizar a mensagem renderizada...</em>
                                </div>
                                <div class="wa-bubble-meta">
                                    <span id="wa-live-bubble-time">{{ now()->format('H:i') }}</span>
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

                <div class="pt-2 text-center text-muted small">
                    <i class="bi bi-info-circle me-1"></i> Prévia em tempo real com dados de demonstração.
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
{{-- Carregamento garantido do jQuery antes do Summernote Lite --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-pt-BR.min.js"></script>
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '';
    const form = document.getElementById('frm-template');
    const isEdit = Boolean(form && form.dataset.id);
    const templateId = form ? form.dataset.id : null;
    const saveUrl = isEdit ? `{{ url('/admin/templates') }}/${templateId}` : `{{ route('admin.templates.store') }}`;

    // Dicionário de variáveis de exemplo para preview instantâneo client-side (0ms)
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
        '{vencimento}': '21/10/2026',
        '{dias_atraso}': '3',
        '{descricao}': 'Hospedagem Cloud Dedicada + Backup Diário',
        '{link_pagamento}': 'https://pay.exemplo.com/c/inv-2026-0891',
        '{boleto_url}': 'https://pay.exemplo.com/boleto/20260891.pdf',
        '{linha_digitavel}': '34191.79001 01043.510047 91020.150008 8 98450000028990',
        '{metodo_pagamento}': 'PIX Dinâmico / Boleto',
        '{forma_pagamento}': 'PIX',
        '{data_pagamento}': '19/10/2026 10:15',
        '{pix_copia_cola}': '00020101021226580014br.gov.bcb.pix2536pix.exemplo.com/qr/v2/99c89e5204000053039865802BR5925MINHA EMPRESA TECNOLOGIA6009SAO PAULO62070503***6304E8A2',
        '{qrcode_url}': 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data=00020101021226580014br.gov.bcb.pix',
        '{qrcode_pix}': 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data=00020101021226580014br.gov.bcb.pix',
        '{qrcode_base64}': 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        '{qrcode_img}': '<img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=00020101021226580014br.gov.bcb.pix" alt="QR Code Pix" style="max-width:200px;width:100%;height:auto;display:block;margin:12px auto;border-radius:10px;border:1px solid #e2e8f0;padding:6px;background:#ffffff;">',
        '{qrcode_instrucao}': 'Também enviamos a imagem do QR Code Pix para agilizar o pagamento.',
        '{nome_servico}': 'Plano Corporativo Plus',
        '{servico_principal}': 'Plano Corporativo Plus',
        '{servicos}': 'Plano Corporativo Plus, IP Dedicado',
        '{servicos_ativos}': 'Plano Corporativo Plus',
        '{status_servico}': 'Ativo',
        '{parcela_atual}': '2',
        '{parcelas_total}': '12',
        '{resumo_parcelas}': '2/12',
        '{proximo_vencimento}': '19/11/2026',
        '{contrato_numero}': 'CTR-2026/044',
        '{contrato_titulo}': 'Prestação de Serviços de TI',
        '{link_contrato}': 'https://sistema.exemplo.com/contratos/ctr-2026-044',
        '{link_assinatura}': 'https://sistema.exemplo.com/assinar/ctr-2026-044?token=abc123xyz',
        '{data_assinatura}': '19/10/2026 10:15',
        '{titulo}': 'Manutenção Programada na Infraestrutura',
        '{mensagem}': 'Informamos que realizaremos melhorias no datacenter no próximo domingo.',
        '{empresa}': 'CW Solutions',
        '{whatsapp_suporte}': '(11) 98765-4321',
        '{email_suporte}': 'suporte@exemplo.com.br',
        '{telefone_suporte}': '(11) 3344-5566',
        '{whmcs_cliente_id}': '1042',
        '{whmcs_fatura_id}': '8910',
    };

    // Converte HTML do editor para texto formatado estilo WhatsApp
    function htmlToWhatsAppText(html) {
        if (!html) return '';
        let text = html;

        // Trata tags de imagem (converte QR Code para indicador amigável)
        text = text.replace(/<img\s+[^>]*>/gi, function (tag) {
            if (/(qrcode|qr-code|pix|\{qrcode)/i.test(tag)) {
                return '\n\n📱 *[QR Code Pix enviado em anexo]*\n\n';
            }
            const altMatch = tag.match(/alt=["']([^"']+)["']/i);
            if (altMatch && altMatch[1]) {
                return '\n\n[' + altMatch[1].trim() + ']\n\n';
            }
            return '';
        });

        // Trata tags básicas
        text = text.replace(/<strong[^>]*>(.*?)<\/strong>/gi, '*$1*')
                   .replace(/<b[^>]*>(.*?)<\/b>/gi, '*$1*')
                   .replace(/<em[^>]*>(.*?)<\/em>/gi, '_$1_')
                   .replace(/<i[^>]*>(.*?)<\/i>/gi, '_$1_')
                   .replace(/<strike[^>]*>(.*?)<\/strike>/gi, '~$1~')
                   .replace(/<del[^>]*>(.*?)<\/del>/gi, '~$1~')
                   .replace(/<s[^>]*>(.*?)<\/s>/gi, '~$1~')
                   .replace(/<code[^>]*>(.*?)<\/code>/gi, '```$1```')
                   .replace(/<pre[^>]*>(.*?)<\/pre>/gi, '```$1```')
                   .replace(/<a[^>]*href=["']([^"']*)["'][^>]*>(.*?)<\/a>/gi, '$2 ($1)')
                   .replace(/<br\s*[\/]?>/gi, '\n')
                   .replace(/<\/p>/gi, '\n')
                   .replace(/<p[^>]*>/gi, '')
                   .replace(/<\/div>/gi, '\n')
                   .replace(/<div[^>]*>/gi, '')
                   .replace(/<li[^>]*>/gi, '• ')
                   .replace(/<\/li>/gi, '\n')
                   .replace(/<[^>]+>/g, '');

        // Decodifica entidades HTML comuns
        const doc = new DOMParser().parseFromString(text, 'text/html');
        return doc.body.textContent || '';
    }

    // Renderiza o texto no balão simulador do WhatsApp
    function formatWhatsAppBubble(text) {
        if (!text) return '';
        let t = text;

        // Substituição local de variáveis
        for (const [key, val] of Object.entries(sampleContext)) {
            t = t.split(key).join(val);
        }

        // Se não houver código Pix no texto, remove bloco Pix vazio
        t = t.replace(/\*Pix Copia e Cola:?\*[\r\n]+(```[\r\n]*```)?[\r\n]*/gi, '');

        // Simulação visual de anexo de mídia no balão do WhatsApp
        t = t.replace(/(?:📱\s*)?\*\[QR Code Pix enviado em anexo\]\*/gi, 
            '<div class="wa-media-card">' +
            '<img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=00020101021226580014br.gov.bcb.pix" alt="QR Code Pix Anexo" />' +
            '<div class="wa-media-badge"><i class="bi bi-paperclip me-1"></i>Imagem QR Code Pix (Mídia Anexa)</div>' +
            '</div>'
        );

        // Atualiza horário em tempo real no smartphone e no balão
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const phoneTimeEl = document.getElementById('wa-phone-status-time');
        if (phoneTimeEl) phoneTimeEl.textContent = timeStr;
        const bubbleTimeEl = document.getElementById('wa-live-bubble-time');
        if (bubbleTimeEl) bubbleTimeEl.textContent = timeStr;

        // Formatação visual para o balão
        return t
            .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
            .replace(/_(.*?)_/g, '<em>$1</em>')
            .replace(/~(.*?)~/g, '<del>$1</del>')
            .replace(/```(.*?)```/gs, '<code>$1</code>')
            .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
            .replace(/\n/g, '<br>');
    }

    // Obtém o conteúdo atual do editor ou do textarea como fallback
    function getEditorContent() {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            try {
                return jQuery('#editor').summernote('code');
            } catch (_) {}
        }
        const el = document.getElementById('editor');
        return el ? el.value : '';
    }

    // Renderização instantânea client-side no mesmo instante da digitação (0ms)
    function renderClientPreview() {
        const previewEl = document.getElementById('preview-content');
        if (!previewEl) return;

        const contentHtml = getEditorContent();
        if (!contentHtml || contentHtml.trim() === '' || contentHtml === '<p><br></p>') {
            previewEl.innerHTML = '<em class="text-muted">Comece a digitar no editor para visualizar a mensagem renderizada...</em>';
            return;
        }

        const rawText = htmlToWhatsAppText(contentHtml);
        const formatted = formatWhatsAppBubble(rawText);
        previewEl.innerHTML = formatted;
    }

    // Debounce do Preview no servidor
    let previewTimer = null;
    function triggerPreview() {
        // 1. Renderiza no cliente imediatamente durante a digitação
        renderClientPreview();

        // 2. Consulta o servidor em background para consolidação com parser oficial
        clearTimeout(previewTimer);
        previewTimer = setTimeout(fetchLivePreview, 160);
    }

    async function fetchLivePreview() {
        const contentHtml = getEditorContent();
        const previewEl = document.getElementById('preview-content');
        if (!previewEl) return;

        if (!contentHtml || contentHtml.trim() === '' || contentHtml === '<p><br></p>') {
            previewEl.innerHTML = '<em class="text-muted">Comece a digitar no editor para visualizar a mensagem renderizada...</em>';
            return;
        }

        try {
            const res = await fetch(`{{ route('admin.templates.preview') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ content_html: contentHtml }),
            });
            const data = await res.json();
            if (data && data.text) {
                previewEl.innerHTML = formatWhatsAppBubble(data.text);
            }
        } catch (e) {
            // Em caso de falha de conexão, a renderização client-side já está visível
        }
    }

    // Inicialização resiliente do Summernote
    function initSummernote(retryCount = 0) {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.summernote === 'undefined') {
            if (retryCount < 60) {
                setTimeout(() => initSummernote(retryCount + 1), 50);
            } else {
                console.warn('Aviso: Dependências do Summernote não foram carregadas a tempo.');
            }
            return;
        }

        const $editor = jQuery('#editor');
        if ($editor.length === 0) return;

        // Evita re-inicialização duplicada
        if ($editor.next('.note-editor').length > 0) {
            triggerPreview();
            return;
        }

        $editor.summernote({
            lang: 'pt-BR',
            height: 270,
            placeholder: 'Digite a mensagem do WhatsApp aqui...',
            dialogsInBody: true,
            toolbar: [
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['codeview', 'help']]
            ],
            callbacks: {
                onChange: function () {
                    triggerPreview();
                },
                onKeyup: function () {
                    triggerPreview();
                },
                onPaste: function () {
                    setTimeout(triggerPreview, 50);
                }
            }
        });

        // Conecta ouvintes diretos na div editável do Summernote para digitação fluida em tempo real
        const $noteEditable = $editor.next('.note-editor').find('.note-editable');
        if ($noteEditable.length > 0) {
            $noteEditable.on('input keyup paste cut compositionend', function () {
                triggerPreview();
            });
        }

        // Dispara renderização inicial imediatamente
        triggerPreview();
    }

    // Ouvinte no textarea de fallback
    const rawTextarea = document.getElementById('editor');
    if (rawTextarea) {
        rawTextarea.addEventListener('input', triggerPreview);
    }

    // Inicializa assim que o DOM ou script estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initSummernote());
    } else {
        initSummernote();
    }

    // Inserção de variáveis dinâmicas no cursor do editor
    document.querySelectorAll('.js-insert-var').forEach(btn => {
        btn.addEventListener('click', function () {
            const variable = this.dataset.var;
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
                jQuery('#editor').summernote('insertText', variable);
            } else {
                const textarea = document.getElementById('editor');
                if (textarea) {
                    const start = textarea.selectionStart || 0;
                    const end = textarea.selectionEnd || 0;
                    textarea.value = textarea.value.substring(0, start) + variable + textarea.value.substring(end);
                }
            }
            triggerPreview();
        });
    });

    // Toolbar rápida com atalhos de formatação WhatsApp
    document.querySelectorAll('[data-wa-tag]').forEach(btn => {
        btn.addEventListener('click', function () {
            const tag = this.dataset.waTag;
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.summernote === 'undefined') return;

            const $editor = jQuery('#editor');
            if (tag === 'bold') $editor.summernote('bold');
            else if (tag === 'italic') $editor.summernote('italic');
            else if (tag === 'strike') $editor.summernote('strikethrough');
            else if (tag === 'code') {
                const text = window.getSelection().toString() || 'codigo';
                $editor.summernote('insertText', '```' + text + '```');
            }
            triggerPreview();
        });
    });

    // Atualização em tempo real do título do chat no preview
    const nameInput = document.querySelector('input[name=name]');
    if (nameInput) {
        nameInput.addEventListener('input', function () {
            const previewTitle = document.getElementById('preview-title');
            if (previewTitle) {
                previewTitle.textContent = this.value.trim() || 'Template WhatsApp';
            }
        });
    }

    // Envio do formulário via AJAX com confirmações SweetAlert2 e Toastify
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btnSave = document.getElementById('btn-save-template');
            if (btnSave) {
                btnSave.disabled = true;
                btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
            }

            const formData = new FormData(form);
            // Garante que o conteúdo mais recente do Summernote seja incluído
            formData.set('content_html', getEditorContent());

            try {
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const result = await response.json();

                if (response.ok && result.ok) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Template salvo!',
                            text: result.message || 'Template gravado com sucesso.',
                            timer: 1600,
                            showConfirmButton: false,
                        });
                    } else if (typeof Toastify !== 'undefined') {
                        Toastify({
                            text: result.message || 'Template gravado com sucesso!',
                            duration: 3000,
                            gravity: 'top',
                            position: 'right',
                            style: { background: '#198754' }
                        }).showToast();
                    }

                    if (result.redirect && !isEdit) {
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1200);
                    }
                } else {
                    let errorMsg = result.message || result.error || 'Erro ao salvar template.';
                    if (result.errors) {
                        errorMsg = Object.values(result.errors).flat().join('<br>');
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Atenção',
                            html: errorMsg,
                        });
                    }
                }
            } catch (err) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de comunicação',
                        text: 'Não foi possível salvar os dados devido a uma falha de conexão.',
                    });
                }
            } finally {
                if (btnSave) {
                    btnSave.disabled = false;
                    btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Salvar Template';
                }
            }
        });
    }
})();
</script>
@endpush
