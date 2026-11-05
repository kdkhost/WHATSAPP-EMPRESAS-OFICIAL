@extends('tenancy::tenant.layout')
@section('title', 'Envio Manual - WhatsApp')

@section('content')
<div class="tenant-premium-hero mb-4">
    <div>
        <span class="tenant-premium-hero__eyebrow"><i class="bi bi-chat-dots-fill me-2"></i>Comunicação WhatsApp</span>
        <h1 class="tenant-premium-hero__title">Envio Manual de Mensagem</h1>
        <p class="tenant-premium-hero__text">Envie mensagens avulsas ou de resolução rápida para clientes escolhendo templates ou digitando o texto diretamente, com pré-visualização realista no formato WhatsApp.</p>
    </div>
    <div class="tenant-premium-metric">
        <span class="tenant-premium-kicker">Status de Envio</span>
        @if($hasConnectedInstance)
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-check-circle-fill me-1"></i> WhatsApp Conectado
                </span>
            </div>
            <div class="small text-muted mt-2">
                Instância: <strong>{{ $connectedInstance->name ?? $connectedInstance->instance_name ?? 'Padrão' }}</strong>
                @if($connectedInstance->profile_number)
                    ({{ $connectedInstance->profile_number }})
                @endif
            </div>
        @else
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> WhatsApp Desconectado
                </span>
            </div>
            <div class="small text-muted mt-2">
                <a href="{{ route('tenant.instances') }}" class="btn btn-sm btn-outline-warning mt-1">
                    <i class="bi bi-qr-code me-1"></i> Conectar Instância
                </a>
            </div>
        @endif
    </div>
</div>

<div class="row g-4">
    {{-- Coluna 1: Formulário de Envio --}}
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Compor Mensagem</div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-form">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar Campos
                </button>
            </div>
            <div class="card-body p-4">
                <form id="frm-manual-whatsapp" autocomplete="off">
                    @csrf

                    {{-- 1. Seleção de Cliente com Busca AJAX --}}
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>1. Cliente (opcional para preenchimento de variáveis)</span>
                            <span class="text-muted small" id="customer-selected-badge" style="display:none;">
                                <i class="bi bi-check2 text-success me-1"></i><span id="customer-selected-name"></span>
                            </span>
                        </label>
                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-search"></i></span>
                                <input type="text" class="form-control" id="customer-search-input" placeholder="Digite o nome, WhatsApp, CPF ou e-mail do cliente...">
                                <button class="btn btn-outline-secondary" type="button" id="btn-clear-customer" title="Remover cliente selecionado" style="display:none;">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <input type="hidden" id="selected-customer-id" name="customer_id" value="">
                            
                            {{-- Dropdown de resultados da busca AJAX --}}
                            <div id="customer-search-results" class="list-group position-absolute w-100 shadow mt-1" style="display:none; z-index: 1050; max-height: 240px; overflow-y: auto;">
                            </div>
                        </div>

                        {{-- Atalhos para clientes recentes --}}
                        @if($recentCustomers->isNotEmpty())
                            <div class="mt-2">
                                <small class="text-muted d-block mb-1">Clientes recentes:</small>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($recentCustomers as $rc)
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill js-quick-customer"
                                                data-id="{{ $rc->id }}"
                                                data-name="{{ $rc->name }}"
                                                data-whatsapp="{{ $rc->whatsapp }}">
                                            <i class="bi bi-person me-1"></i>{{ Str::limit($rc->name, 18) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- 2. Seleção de Template --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold">2. Modelo de Mensagem (Template) <span class="text-danger">*</span></label>
                        <select class="form-select" id="template-select" name="template_id" required>
                            <option value="">Selecione um template obrigatório...</option>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}" data-category="{{ $tpl->category }}">
                                    [{{ ucfirst($tpl->category) }}] {{ $tpl->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Nenhuma mensagem pode ser enviada sem um template válido e ativo. Selecione o modelo para carregar a mensagem.</small>
                    </div>

                    {{-- 3. Número do Destinatário --}}
                    <div class="mb-3">
                        <label class="form-label">3. Número do WhatsApp do Destinatário <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-success-subtle text-success"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" class="form-control" id="target-phone" name="to" placeholder="Ex: (11) 98765-4321 ou 5511987654321" required>
                        </div>
                        <small class="text-muted">Informe DDD + número (com ou sem código 55 do país).</small>
                    </div>

                    {{-- 4. Mensagem --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">4. Mensagem <span class="text-danger">*</span></label>
                            <small class="text-muted" id="char-counter">0 caracteres</small>
                        </div>

                        {{-- Barra de Ferramentas do Editor WhatsApp --}}
                        <div class="wa-toolbar mb-2" id="wa-toolbar">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="bold" title="Negrito (*texto*)"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="italic" title="Itálico (_texto_)"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="strike" title="Riscado (~texto~)"><i class="bi bi-type-strikethrough"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="mono" title="Monoespaçado (```texto```)"><i class="bi bi-code"></i></button>
                            <span class="wa-toolbar__separator"></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="list" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="bullet" title="Lista com marcadores"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="indent" title="Citação"><i class="bi bi-text-indent-left"></i></button>
                            <span class="wa-toolbar__separator"></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-wa-format="emoji" title="Emojis"><i class="bi bi-emoji-smile"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="btn-clear-message" title="Limpar Mensagem"><i class="bi bi-eraser me-1"></i>Limpar</button>
                        </div>
                        <div id="emoji-picker" class="wa-emoji-picker d-none mb-2"></div>

                        <textarea class="form-control font-monospace" id="message-body" name="message" rows="7" placeholder="Digite o conteúdo da mensagem aqui..." required></textarea>
                        
                        {{-- Atalhos de variáveis --}}
                        <div class="mt-2">
                            <small class="text-muted d-block mb-1">Inserir variáveis na mensagem:</small>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{nome}">Nome</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{primeiro_nome}">1º Nome</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{valor}">Valor</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{vencimento}">Vencimento</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{numero_fatura}">Nº Fatura</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{link_pagamento}">Link Pagamento</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{pix_copia_cola}">Pix Copia/Cola</button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill js-insert-tag" data-tag="{empresa}">Empresa</button>
                            </div>
                        </div>
                    </div>

                    {{-- Botão de Envio --}}
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <div class="text-muted small">
                            <i class="bi bi-shield-check text-success me-1"></i> Disparo protegido via Evolution API
                        </div>
                        <button type="submit" class="btn btn-success px-4 py-2 fw-semibold" id="btn-submit-send">
                            <i class="bi bi-send-fill me-2"></i> Disparar Mensagem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Coluna 2: Preview Autêntico do WhatsApp --}}
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
            <div class="card-header bg-transparent border-0 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="tenant-premium-kicker mb-0">Visualização em Tempo Real</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">WhatsApp Style</span>
                </div>
            </div>
            <div class="card-body p-3">
                {{-- Mockup do Aparelho / Chat WhatsApp --}}
                <div class="wa-phone-container">
                    {{-- Barra Superior do Chat WhatsApp --}}
                    <div class="wa-chat-header">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="wa-avatar">
                                <span id="wa-preview-avatar">C</span>
                            </div>
                            <div class="min-w-0">
                                <div class="wa-contact-name text-truncate" id="wa-preview-name">Destinatário</div>
                                <div class="wa-status"><span class="wa-status-dot"></span> online</div>
                            </div>
                        </div>
                        <div class="wa-header-icons d-flex align-items-center gap-3">
                            <i class="bi bi-camera-video-fill"></i>
                            <i class="bi bi-telephone-fill"></i>
                            <i class="bi bi-three-dots-vertical"></i>
                        </div>
                    </div>

                    {{-- Corpo da Conversa com Papel de Parede --}}
                    <div class="wa-chat-body" id="wa-chat-body">
                        {{-- Data Badge --}}
                        <div class="text-center my-2">
                            <span class="wa-date-pill">HOJE</span>
                        </div>

                        {{-- Balão de Mensagem de Saída (Verde WhatsApp com Rabicho) --}}
                        <div class="wa-bubble-wrapper">
                            <div class="wa-bubble">
                                <div class="wa-bubble-text" id="wa-preview-text">
                                    <span class="text-muted fst-italic">Sua mensagem aparecerá aqui em tempo real...</span>
                                </div>
                                <div class="wa-bubble-meta">
                                    <span class="wa-bubble-time" id="wa-preview-time">10:55</span>
                                    <span class="wa-bubble-ticks" title="Entregue e lida">✓✓</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Barra Inferior Simulada de Digitação --}}
                    <div class="wa-chat-footer">
                        <div class="wa-footer-icon"><i class="bi bi-emoji-smile"></i></div>
                        <div class="wa-input-fake">Mensagem</div>
                        <div class="wa-footer-icon"><i class="bi bi-paperclip"></i></div>
                        <div class="wa-mic-btn"><i class="bi bi-mic-fill"></i></div>
                    </div>
                </div>

                <div class="text-center text-muted small mt-2">
                    <i class="bi bi-info-circle me-1"></i> Esta é a visualização exata de como a mensagem chegará ao WhatsApp do cliente.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Estilos do Mockup WhatsApp */
.wa-phone-container {
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.12);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    background-color: #efeae2;
    display: flex;
    flex-direction: column;
    max-width: 420px;
    margin: 0 auto;
}
[data-bs-theme="dark"] .wa-phone-container {
    border-color: rgba(255, 255, 255, 0.08);
    background-color: #0b141a;
}

/* Header WhatsApp */
.wa-chat-header {
    background: #005c4b;
    color: #ffffff;
    padding: .65rem .85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
[data-bs-theme="dark"] .wa-chat-header {
    background: #1f2c34;
}
.wa-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #25d366;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .95rem;
    flex-shrink: 0;
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
    display: inline-block;
}
.wa-header-icons {
    color: #e9edef;
    font-size: .95rem;
}

/* Corpo do Chat */
.wa-chat-body {
    min-height: 340px;
    max-height: 480px;
    overflow-y: auto;
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
    box-shadow: 0 1px .5px rgba(11,20,26,.13);
}
[data-bs-theme="dark"] .wa-date-pill {
    background: #182229;
    color: #8696a0;
}

/* Balão de Mensagem de Saída (Estilo Oficial WhatsApp com Rabicho) */
.wa-bubble-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: .5rem;
    margin-bottom: .5rem;
}
.wa-bubble {
    position: relative;
    max-width: 88%;
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
/* Rabicho / Tail */
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

/* Footer / Input WhatsApp */
.wa-chat-footer {
    background: #f0f2f5;
    padding: .5rem .75rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    border-top: 1px solid rgba(0,0,0,0.06);
}
[data-bs-theme="dark"] .wa-chat-footer {
    background: #202c33;
    border-top-color: rgba(255,255,255,0.06);
}
.wa-footer-icon {
    color: #54656f;
    font-size: 1.1rem;
    cursor: pointer;
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

/* Toolbar de Formatação do WhatsApp */
.wa-toolbar {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
    background: var(--bs-tertiary-bg, rgba(0, 0, 0, 0.03));
    padding: .45rem .65rem;
    border-radius: 8px;
    border: 1px solid var(--bs-border-color);
}
.wa-toolbar .btn {
    padding: .25rem .5rem;
    font-size: .85rem;
    line-height: 1.2;
}
.wa-toolbar__separator {
    width: 1px;
    height: 22px;
    background: var(--bs-border-color);
    margin: 0 .15rem;
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
    max-height: 180px;
    overflow-y: auto;
}
.wa-emoji-picker button {
    border: 0;
    background: transparent;
    font-size: 1.25rem;
    line-height: 1;
    padding: .25rem;
    border-radius: 6px;
    transition: background-color .15s;
    cursor: pointer;
}
.wa-emoji-picker button:hover {
    background: var(--bs-tertiary-bg);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customerSearchInput = document.getElementById('customer-search-input');
    const customerResults = document.getElementById('customer-search-results');
    const selectedCustomerId = document.getElementById('selected-customer-id');
    const targetPhone = document.getElementById('target-phone');
    const templateSelect = document.getElementById('template-select');
    const messageBody = document.getElementById('message-body');
    const charCounter = document.getElementById('char-counter');
    const customerBadge = document.getElementById('customer-selected-badge');
    const customerNameBadge = document.getElementById('customer-selected-name');
    const btnClearCustomer = document.getElementById('btn-clear-customer');
    const btnResetForm = document.getElementById('btn-reset-form');

    // Elementos do Preview
    const waPreviewText = document.getElementById('wa-preview-text');
    const waPreviewName = document.getElementById('wa-preview-name');
    const waPreviewAvatar = document.getElementById('wa-preview-avatar');
    const waPreviewTime = document.getElementById('wa-preview-time');

    let currentCustomerData = null;
    let searchTimeout = null;

    // Atualiza hora do preview
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        waPreviewTime.textContent = `${hours}:${minutes}`;
    }
    updateClock();

    // Renderiza formatação do WhatsApp no preview (*negrito*, _itálico_, quebras de linha)
    function formatWhatsAppMarkup(text) {
        if (!text || text.trim() === '') {
            return '<span class="text-muted fst-italic">Sua mensagem aparecerá aqui em tempo real...</span>';
        }
        let escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // *negrito*
        escaped = escaped.replace(/\*([^\*\n]+)\*/g, '<strong>$1</strong>');
        // _italico_
        escaped = escaped.replace(/_([^_\n]+)_/g, '<em>$1</em>');
        // ~tachado~
        escaped = escaped.replace(/~([^~\n]+)~/g, '<del>$1</del>');
        // ```mono```
        escaped = escaped.replace(/```([^`]+)```/g, '<code class="p-1 rounded bg-black bg-opacity-10">$1</code>');
        // > citacao
        escaped = escaped.replace(/^> (.+)$/gm, '<span class="d-block ps-2 my-1 border-start border-3 border-success opacity-75">$1</span>');
        // quebras de linha
        escaped = escaped.replace(/\n/g, '<br>');

        return escaped;
    }

    // Atualiza o preview de mensagem
    function updatePreview() {
        const text = messageBody.value;
        waPreviewText.innerHTML = formatWhatsAppMarkup(text);
        charCounter.textContent = `${text.length} caracteres`;

        if (currentCustomerData) {
            waPreviewName.textContent = currentCustomerData.name;
            waPreviewAvatar.textContent = currentCustomerData.name.charAt(0).toUpperCase();
        } else {
            const rawPhone = targetPhone.value.trim();
            waPreviewName.textContent = rawPhone ? rawPhone : 'Destinatário';
            waPreviewAvatar.textContent = rawPhone ? '#' : 'D';
        }
    }

    messageBody.addEventListener('input', updatePreview);
    targetPhone.addEventListener('input', updatePreview);

    // Funções auxiliares para manipulação do editor de texto
    function insertAtCursor(text) {
        const start = messageBody.selectionStart;
        const end = messageBody.selectionEnd;
        const val = messageBody.value;
        messageBody.value = val.substring(0, start) + text + val.substring(end);
        messageBody.focus();
        messageBody.selectionStart = messageBody.selectionEnd = start + text.length;
        updatePreview();
    }

    function wrapSelection(prefix, suffix) {
        const start = messageBody.selectionStart;
        const end = messageBody.selectionEnd;
        const val = messageBody.value;
        const selected = val.substring(start, end);
        const replacement = prefix + selected + suffix;
        messageBody.value = val.substring(0, start) + replacement + val.substring(end);
        messageBody.focus();
        if (selected.length > 0) {
            messageBody.selectionStart = start + prefix.length;
            messageBody.selectionEnd = end + prefix.length;
        } else {
            messageBody.selectionStart = messageBody.selectionEnd = start + prefix.length;
        }
        updatePreview();
    }

    // Configuração do Seletor de Emojis do WhatsApp
    const emojiPicker = document.getElementById('emoji-picker');
    const waEmojis = [
        '😀', '😃', '😄', '😁', '😅', '😂', '🤣', '🙂', '😉', '😊',
        '😇', '🥰', '😍', '🤩', '😘', '😗', '😋', '😜', '😎', '🤝',
        '👍', '👎', '👌', '✌️', '🤞', '👏', '🙌', '🙏', '💪', '🔥',
        '💰', '💳', '💵', '🧾', '📅', '⏰', '⏳', '⚠️', '🚨', '❌',
        '✅', '🚀', '💬', '📱', '📦', '🔔', '🎉', '✨', '⭐', '❤️'
    ];
    if (emojiPicker) {
        emojiPicker.innerHTML = waEmojis.map(e => `<button type="button" data-emoji="${e}">${e}</button>`).join('');
    }

    // Ações da Barra de Ferramentas (Toolbar)
    document.querySelectorAll('[data-wa-format]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
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
                    const start = messageBody.selectionStart;
                    const end = messageBody.selectionEnd;
                    const selected = messageBody.value.substring(start, end);
                    if (selected) {
                        const lines = selected.split('\n');
                        const numbered = lines.map((l, idx) => `${idx + 1}. ${l}`).join('\n');
                        messageBody.value = messageBody.value.substring(0, start) + numbered + messageBody.value.substring(end);
                        messageBody.selectionStart = start;
                        messageBody.selectionEnd = start + numbered.length;
                    } else {
                        insertAtCursor('1. ');
                    }
                    updatePreview();
                    break;
                }
                case 'bullet': {
                    const start = messageBody.selectionStart;
                    const end = messageBody.selectionEnd;
                    const selected = messageBody.value.substring(start, end);
                    if (selected) {
                        const lines = selected.split('\n');
                        const bulleted = lines.map(l => `- ${l}`).join('\n');
                        messageBody.value = messageBody.value.substring(0, start) + bulleted + messageBody.value.substring(end);
                        messageBody.selectionStart = start;
                        messageBody.selectionEnd = start + bulleted.length;
                    } else {
                        insertAtCursor('- ');
                    }
                    updatePreview();
                    break;
                }
                case 'indent':
                    insertAtCursor('> ');
                    break;
                case 'emoji':
                    if (emojiPicker) emojiPicker.classList.toggle('d-none');
                    break;
            }
        });
    });

    // Clique em Emoji
    document.addEventListener('click', (e) => {
        const emojiBtn = e.target.closest('[data-emoji]');
        if (emojiBtn) {
            insertAtCursor(emojiBtn.dataset.emoji);
            if (emojiPicker) emojiPicker.classList.add('d-none');
        }
    });

    // Botão Limpar Mensagem na Toolbar
    const btnClearMsg = document.getElementById('btn-clear-message');
    if (btnClearMsg) {
        btnClearMsg.addEventListener('click', () => {
            if (messageBody.value.trim().length > 0) {
                messageBody.value = '';
                messageBody.focus();
                updatePreview();
                if (window.toast) {
                    window.toast.info('Mensagem limpa.');
                }
            }
        });
    }

    // Inserção de variáveis no cursor do textarea
    document.querySelectorAll('.js-insert-tag').forEach(btn => {
        btn.addEventListener('click', () => {
            const tag = btn.dataset.tag;
            insertAtCursor(tag);
        });
    });

    // Busca de Clientes AJAX com debounce
    customerSearchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            customerResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`{{ route('tenant.whatsapp.customers.search') }}?q=${encodeURIComponent(query)}`);
                const data = await res.json();

                customerResults.innerHTML = '';
                if (data.success && data.customers.length > 0) {
                    data.customers.forEach(cust => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>${cust.name}</strong>
                                <span class="badge bg-light text-dark">${cust.whatsapp || cust.phone || 'Sem fone'}</span>
                            </div>
                            <small class="text-muted">${cust.email || ''} ${cust.document ? '• ' + cust.document : ''}</small>
                        `;
                        item.addEventListener('click', (e) => {
                            e.preventDefault();
                            selectCustomer(cust);
                        });
                        customerResults.appendChild(item);
                    });
                    customerResults.style.display = 'block';
                } else {
                    customerResults.innerHTML = '<div class="list-group-item text-muted small py-2">Nenhum cliente encontrado.</div>';
                    customerResults.style.display = 'block';
                }
            } catch (err) {
                console.error(err);
            }
        }, 300);
    });

    // Fecha o dropdown de busca ao clicar fora
    document.addEventListener('click', function (e) {
        if (!customerSearchInput.contains(e.target) && !customerResults.contains(e.target)) {
            customerResults.style.display = 'none';
        }
    });

    // Seleciona um cliente
    function selectCustomer(cust) {
        currentCustomerData = cust;
        selectedCustomerId.value = cust.id;
        customerSearchInput.value = cust.name;
        customerResults.style.display = 'none';

        if (cust.whatsapp || cust.phone) {
            targetPhone.value = cust.whatsapp || cust.phone;
        }

        customerBadge.style.display = 'inline-flex';
        customerNameBadge.textContent = cust.name;
        btnClearCustomer.style.display = 'block';

        // Se houver template selecionado, re-renderiza com as variáveis do cliente
        if (templateSelect.value) {
            loadTemplate(templateSelect.value, cust.id);
        }

        updatePreview();
    }

    // Atalhos de clientes recentes
    document.querySelectorAll('.js-quick-customer').forEach(btn => {
        btn.addEventListener('click', () => {
            selectCustomer({
                id: btn.dataset.id,
                name: btn.dataset.name,
                whatsapp: btn.dataset.whatsapp
            });
        });
    });

    // Limpar cliente selecionado
    btnClearCustomer.addEventListener('click', function () {
        currentCustomerData = null;
        selectedCustomerId.value = '';
        customerSearchInput.value = '';
        customerBadge.style.display = 'none';
        btnClearCustomer.style.display = 'none';
        updatePreview();
    });

    // Carregar template selecionado
    async function loadTemplate(templateId, customerId = '') {
        if (!templateId) return;

        try {
            const res = await fetch(`{{ route('tenant.whatsapp.render-template') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    template_id: templateId,
                    customer_id: customerId
                })
            });
            const data = await res.json();
            if (data.success) {
                messageBody.value = data.rendered_text;
                updatePreview();
            }
        } catch (err) {
            console.error('Erro ao renderizar template:', err);
        }
    }

    templateSelect.addEventListener('change', function () {
        const tplId = this.value;
        if (tplId) {
            loadTemplate(tplId, selectedCustomerId.value);
        }
    });

    // Limpar todo o formulário
    btnResetForm.addEventListener('click', function () {
        document.getElementById('frm-manual-whatsapp').reset();
        currentCustomerData = null;
        selectedCustomerId.value = '';
        customerBadge.style.display = 'none';
        btnClearCustomer.style.display = 'none';
        updatePreview();
    });

    // Submissão do Formulário com SweetAlert2 e Toastify
    document.getElementById('frm-manual-whatsapp').addEventListener('submit', async function (e) {
        e.preventDefault();

        const phone = targetPhone.value.trim();
        const message = messageBody.value.trim();
        const templateId = templateSelect.value;

        if (!templateId) {
            Swal.fire({
                icon: 'warning',
                title: 'Template Obrigatório',
                text: 'Selecione um template de mensagem válido antes de realizar o envio.',
                confirmButtonColor: '#005c4b'
            });
            templateSelect.focus();
            return;
        }

        if (!phone || !message) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos Obrigatórios',
                text: 'Por favor, preencha o número do destinatário e a mensagem.',
                confirmButtonColor: '#005c4b'
            });
            return;
        }

        const confirm = await Swal.fire({
            title: 'Confirmar Disparo?',
            html: `A mensagem será enviada imediatamente via WhatsApp para <strong>${phone}</strong>.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-send-fill me-1"></i> Sim, disparar!',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d'
        });

        if (!confirm.isConfirmed) return;

        const btnSubmit = document.getElementById('btn-submit-send');
        const originalHtml = btnSubmit.innerHTML;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Enviando...';

        try {
            const res = await fetch(`{{ route('tenant.whatsapp.send-manual') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    to: phone,
                    message: message,
                    customer_id: selectedCustomerId.value || null,
                    template_id: templateSelect.value || null
                })
            });

            const data = await res.json();

            if (res.ok && data.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Mensagem Enviada!',
                    text: data.message || 'Disparo manual concluído com sucesso.',
                    confirmButtonColor: '#198754'
                });

                Toastify({
                    text: "✓ Mensagem enviada com sucesso!",
                    duration: 4000,
                    gravity: "top",
                    position: "right",
                    style: { background: "#198754" }
                }).showToast();

                // Opcional: limpar textarea ou manter para novos disparos
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Falha no Envio',
                    text: data.message || 'Não foi possível entregar a mensagem. Verifique a conexão da sua instância de WhatsApp.',
                    confirmButtonColor: '#dc3545'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Erro de Conexão',
                text: 'Falha de comunicação com o servidor. Tente novamente em instantes.',
                confirmButtonColor: '#dc3545'
            });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalHtml;
        }
    });

    updatePreview();
});
</script>
@endpush
