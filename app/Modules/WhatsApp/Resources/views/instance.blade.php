@extends('admin.layouts.app')

@section('title', 'WhatsApp - Instância')

@php
    $initialStatus = $persistentStatus ?? [];
    $initialNumberDigits = preg_replace('/\D+/', '', (string) ($initialStatus['number'] ?? ''));
    $initialNumberLocal = str_starts_with($initialNumberDigits, '55') ? substr($initialNumberDigits, 2) : $initialNumberDigits;
    $initialNumberDisplay = $initialStatus['number'] ?? 'Não informado';
    $initialState = (string) ($initialStatus['state'] ?? 'close');
    $initialHasIdentity = filled($initialStatus['number'] ?? null) || filled($initialStatus['jid'] ?? null);
    $initialConnected = $initialHasIdentity && (
        (bool) ($initialStatus['connected'] ?? false)
        || $initialState === 'open'
        || (string) ($initialStatus['status'] ?? '') === 'connected'
    );
    $initialLabel = (string) ($initialStatus['status_label'] ?? ($initialConnected ? 'Conectado' : 'Desconectado'));
    $initialBadgeClass = 'bg-secondary';

    if (strlen($initialNumberLocal) === 11) {
        $initialNumberDisplay = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $initialNumberLocal);
    } elseif (strlen($initialNumberLocal) === 10) {
        $initialNumberDisplay = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $initialNumberLocal);
    }

    if ($initialConnected) {
        $initialBadgeClass = 'bg-success';
    } elseif ($initialState === 'connecting') {
        $initialBadgeClass = 'bg-warning text-dark';
    } elseif (in_array($initialState, ['close', 'error'], true)) {
        $initialBadgeClass = 'bg-danger';
    }

    $mainWebhookUrl = url('/api/v1/webhook');
    $mainWebhookToken = config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026'));
@endphp

@push('head')
<style>
    .wa-theme-scope {
        --wa-border: color-mix(in srgb, var(--app-border-soft) 82%, transparent);
        --wa-surface-soft: color-mix(in srgb, var(--app-surface-bg) 84%, var(--bs-body-bg) 16%);
        --wa-surface-muted: color-mix(in srgb, var(--app-surface-bg) 76%, var(--bs-body-bg) 24%);
        --wa-success-bg: color-mix(in srgb, var(--bs-success) 16%, var(--app-surface-bg) 84%);
        --wa-success-color: color-mix(in srgb, var(--bs-success) 78%, var(--bs-body-color) 22%);
        --wa-error-bg: color-mix(in srgb, var(--bs-danger) 16%, var(--app-surface-bg) 84%);
        --wa-error-color: color-mix(in srgb, var(--bs-danger) 78%, var(--bs-body-color) 22%);
    }

    .wa-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
        padding: 1.25rem 1.35rem;
        border: 1px solid var(--wa-border);
        border-radius: var(--app-radius-premium);
        background:
            linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 10%, transparent), transparent 42%),
            linear-gradient(180deg, var(--app-surface-bg), var(--wa-surface-soft));
    }

    .wa-status-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }

    .wa-status-row {
        padding: .75rem;
        border: 1px solid var(--wa-border);
        border-radius: .5rem;
        background: var(--wa-surface-soft);
    }

    .wa-status-label {
        display: block;
        font-size: .75rem;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        margin-bottom: .25rem;
        font-weight: 700;
    }

    .wa-status-value {
        font-size: .95rem;
        font-weight: 600;
        color: var(--bs-body-color);
        word-break: break-word;
    }

    .wa-qr-box {
        min-height: 260px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border: 1px dashed color-mix(in srgb, var(--wa-border) 80%, var(--bs-primary) 20%);
        border-radius: .75rem;
        background: var(--wa-surface-soft);
        padding: 1rem;
    }

    .wa-qr-message {
        width: 100%;
        padding: 1rem;
        border-radius: .5rem;
        background: var(--wa-surface-muted);
        color: var(--bs-body-color);
        font-weight: 600;
        border: 1px solid var(--wa-border);
    }

    .wa-qr-generated {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: .75rem;
    }

    .wa-qr-generated canvas,
    .wa-qr-generated img {
        display: block;
        width: 260px;
        height: 260px;
        max-width: 100%;
        padding: .5rem;
        border-radius: .5rem;
        background: #fff;
        border: 1px solid var(--wa-border);
    }

    .wa-qr-success {
        background: var(--wa-success-bg);
        color: var(--wa-success-color);
    }

    .wa-qr-error {
        background: var(--wa-error-bg);
        color: var(--wa-error-color);
    }

    .wa-actions .btn {
        width: 100%;
        margin-bottom: .5rem;
    }

    .wa-config-panel {
        border: 1px solid var(--wa-border);
        border-radius: .85rem;
        padding: .95rem;
        background: var(--wa-surface-soft);
    }

    .wa-config-section + .wa-config-section {
        margin-top: 1rem;
    }

    .wa-config-section__title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .75rem;
    }

    .wa-config-section__title strong {
        display: block;
    }

    .wa-config-section__title small {
        display: block;
        color: var(--bs-secondary-color);
    }

    .wa-field-help {
        color: var(--bs-secondary-color);
        font-size: .8rem;
        margin-top: .35rem;
    }

    .wa-basic-config-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem 1rem;
        margin-bottom: 1rem;
    }

    .wa-basic-config-grid > .mb-3 {
        margin-bottom: 0 !important;
    }

    .wa-basic-config-grid .wa-field-span-2 {
        grid-column: 1 / -1;
    }

    .wa-form-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }

    .wa-form-actions .btn {
        width: 100%;
    }

    .wa-copy-group {
        display: flex;
        gap: .65rem;
        align-items: stretch;
    }

    .wa-copy-group .form-control {
        flex: 1 1 auto;
    }

    .wa-event-grid,
    .wa-advanced-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .55rem;
    }

    .wa-option-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .55rem;
    }

    .wa-option-item {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        padding: .65rem .75rem;
        border: 1px solid var(--wa-border);
        border-radius: .55rem;
        background: var(--wa-surface-muted);
        min-height: 100%;
    }

    .wa-option-item code {
        font-size: .72rem;
    }

    .wa-option-item .form-check-input {
        margin-top: .2rem;
        flex: 0 0 auto;
    }

    .wa-option-item__body {
        min-width: 0;
        flex: 1 1 auto;
    }

    .wa-option-item__body strong {
        display: flex;
        align-items: center;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .wa-option-item__body small {
        display: block;
        margin-top: .2rem;
    }

    .wa-status-card {
        border: 1px solid var(--wa-border);
        border-radius: .85rem;
        background: var(--wa-surface-soft);
        padding: 1rem;
    }

    .wa-actions-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .5rem;
    }

    .wa-actions-grid .btn {
        height: 100%;
        min-height: 44px;
        margin-bottom: 0;
    }

    @media (min-width: 1400px) {
        .wa-event-grid,
        .wa-advanced-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .wa-page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .wa-event-grid,
        .wa-advanced-grid,
        .wa-basic-config-grid,
        .wa-form-actions,
        .wa-actions-grid,
        .wa-status-grid {
            grid-template-columns: 1fr;
        }

        .wa-copy-group {
            flex-direction: column;
        }
    }

    [data-bs-theme="dark"] .wa-page-header {
        background:
            linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 12%, transparent), transparent 42%),
            linear-gradient(180deg, var(--admin-dark-surface, #0b1020), var(--admin-dark-surface-soft, #0f172a));
        border-color: var(--admin-dark-border, rgba(255,255,255,.1));
    }
    [data-bs-theme="dark"] .wa-status-row,
    [data-bs-theme="dark"] .wa-config-panel,
    [data-bs-theme="dark"] .wa-status-card {
        background: var(--admin-dark-surface-soft, #0f172a) !important;
        border-color: var(--admin-dark-border, rgba(255,255,255,.1)) !important;
    }
    [data-bs-theme="dark"] .wa-option-item {
        background: var(--admin-dark-field, #050a18) !important;
        border-color: var(--admin-dark-border, rgba(255,255,255,.1)) !important;
        color: var(--bs-body-color) !important;
    }
    [data-bs-theme="dark"] .wa-qr-box {
        background: var(--admin-dark-surface-soft, #0f172a) !important;
        border-color: var(--admin-dark-border, rgba(255,255,255,.1)) !important;
    }
    [data-bs-theme="dark"] .wa-qr-message {
        background: var(--admin-dark-field, #050a18) !important;
        color: var(--bs-body-color) !important;
        border-color: var(--admin-dark-border, rgba(255,255,255,.1)) !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid wa-theme-scope">
    <div class="wa-page-header">
        <div>
            <h1 class="h3 mb-1">WhatsApp Cloud API Oficial (Meta Graph API)</h1>
            <p class="text-muted mb-0">Comunicação corporativa 100% oficial com a Meta, sem risco de banimento e sem emulação de navegador.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span id="wa-status-badge" class="badge {{ $initialBadgeClass }}">{{ $initialLabel }}</span>
            <button type="button" class="btn btn-outline-info" id="btn-refresh-status" style="display:none" aria-hidden="true" tabindex="-1"></button>
            <button type="button" id="wa-btn-refresh-status" class="btn btn-sm btn-primary">
                Testar conexão Meta
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary js-meta-help-trigger" data-help-topic="meta-overview" title="Guia Passo a Passo Oficial Meta">
                <i class="bi bi-question-circle-fill me-1"></i> Ajuda & Passo a Passo Meta
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7 col-lg-7 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Configuração da Meta WhatsApp Cloud API (Graph API)</strong>
                    <span class="badge bg-primary">v21.0 Oficial</span>
                </div>

                <div class="card-body">
                    <form id="wa-config-form" data-legacy-form="frm-whatsapp-config">
                        @csrf

                        <div class="wa-basic-config-grid">
                            <div class="mb-3">
                                <label for="wa-meta-phone-id" class="form-label d-flex align-items-center justify-content-between">
                                    <span>Phone Number ID da Meta</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-primary small text-decoration-none" data-help-topic="meta-phone-id" title="Ver passo a passo de como obter o Phone Number ID">
                                        <i class="bi bi-question-circle-fill me-1"></i>Como obter?
                                    </a>
                                </label>
                                <input type="text" class="form-control" id="wa-meta-phone-id" name="meta_phone_number_id" value="{{ config('whatsapp.meta_phone_number_id', env('META_PHONE_NUMBER_ID', '')) }}" placeholder="Ex: 109283746592817" required>
                                <small class="text-muted">Obtido no painel Meta for Developers > WhatsApp > Configuração da API.</small>
                            </div>

                            <div class="mb-3">
                                <label for="wa-meta-waba-id" class="form-label d-flex align-items-center justify-content-between">
                                    <span>WhatsApp Business Account ID (WABA)</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-primary small text-decoration-none" data-help-topic="meta-waba-id" title="Ver passo a passo do WABA ID">
                                        <i class="bi bi-question-circle-fill me-1"></i>Como obter?
                                    </a>
                                </label>
                                <input type="text" class="form-control" id="wa-meta-waba-id" name="meta_waba_id" value="{{ config('whatsapp.meta_waba_id', env('META_WABA_ID', '')) }}" placeholder="Ex: 192837465019283" required>
                                <small class="text-muted">Identificador da sua conta comercial na Meta.</small>
                            </div>

                            <div class="mb-3 wa-field-span-2">
                                <label for="wa-meta-access-token" class="form-label d-flex align-items-center justify-content-between">
                                    <span>Token Permanente de Sistema (System User Token)</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-primary small text-decoration-none" data-help-topic="meta-system-user-token" title="Ver passo a passo para gerar token permanente sem expiração de 24h">
                                        <i class="bi bi-question-circle-fill me-1"></i>Como gerar token permanente?
                                    </a>
                                </label>
                                <input type="password" class="form-control" id="wa-meta-access-token" name="meta_access_token" value="{{ config('whatsapp.meta_access_token', env('META_ACCESS_TOKEN', '')) }}" placeholder="EAAG..." autocomplete="off" spellcheck="false" required>
                                <small class="text-muted">Token gerado no Meta Business Suite para o Usuário de Sistema com permissão whatsapp_business_messaging.</small>
                            </div>

                            <div class="mb-3">
                                <label for="wa-meta-app-secret" class="form-label d-flex align-items-center justify-content-between">
                                    <span>Meta App Secret (HMAC-SHA256)</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-primary small text-decoration-none" data-help-topic="meta-app-secret" title="Ver passo a passo do App Secret">
                                        <i class="bi bi-question-circle-fill me-1"></i>Onde encontrar?
                                    </a>
                                </label>
                                <input type="password" class="form-control" id="wa-meta-app-secret" name="meta_app_secret" value="{{ config('whatsapp.meta_app_secret', env('META_APP_SECRET', '')) }}" placeholder="Chave secreta do App" autocomplete="off" spellcheck="false">
                                <small class="text-muted">Usado para validar a integridade x-hub-signature-256 no webhook oficial.</small>
                            </div>

                            <div class="mb-3">
                                <label for="wa-meta-verify-token" class="form-label d-flex align-items-center justify-content-between">
                                    <span>Token de Verificação do Webhook</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-primary small text-decoration-none" data-help-topic="meta-webhook" title="Ver passo a passo da configuração do Webhook">
                                        <i class="bi bi-question-circle-fill me-1"></i>Como configurar?
                                    </a>
                                </label>
                                <input type="text" class="form-control" id="wa-meta-verify-token" name="meta_webhook_verify_token" value="{{ config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_token')) }}">
                                <small class="text-muted">Token arbitrário para responder ao handshake hub.verify_token da Meta.</small>
                            </div>
                        </div>

                        <div class="wa-config-panel mb-3">
                            <div class="wa-config-section">
                                <div class="wa-config-section__title">
                                    <div>
                                        <strong>Webhook Oficial Meta Cloud API</strong>
                                        <small class="text-muted d-block">Configure este endpoint no painel Meta for Developers para receber status de entrega e interações.</small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-info js-meta-help-trigger" data-help-topic="meta-webhook">
                                        <i class="bi bi-question-circle me-1"></i>Instruções do Webhook
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label for="wa-webhook-url-display" class="form-label">URL de Callback (Webhook)</label>
                                    <div class="wa-copy-group d-flex gap-2">
                                        <input type="text" class="form-control" id="wa-webhook-url-display" value="{{ url('/api/v1/webhook') }}" readonly data-wa-webhook-url>
                                        <button type="button" class="btn btn-outline-secondary" id="wa-btn-copy-webhook" onclick="navigator.clipboard.writeText('{{ url('/api/v1/webhook') }}'); alert('URL do Webhook copiada!');">
                                            Copiar URL
                                        </button>
                                    </div>
                                    <div class="wa-field-help mt-1 text-muted small">Cole este endereço no campo <strong>URL de retorno de chamada</strong> no painel de desenvolvedores da Meta.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Token de Verificação (hub.verify_token)</label>
                                    <div class="wa-copy-group d-flex gap-2">
                                        <input type="text" class="form-control" value="{{ config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026')) }}" readonly>
                                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026')) }}'); alert('Token copiado!');">
                                            Copiar Token
                                        </button>
                                    </div>
                                    <div class="wa-field-help mt-1 text-muted small">Cole este token no campo <strong>Verificar token</strong> ao registrar o Webhook na Meta.</div>
                                </div>

                                <div class="alert alert-info py-2 px-3 small mb-0">
                                    <i class="bi bi-info-circle-fill me-1"></i> No painel da Meta, assine os campos: <strong>messages</strong> e <strong>message_template_status_update</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="wa-form-actions">
                            <button type="submit" class="btn btn-success">
                                Salvar configuração
                            </button>

                            <button type="button" id="wa-btn-test-connection" class="btn btn-outline-primary">
                                Testar conexão
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Ações da API Oficial Meta</strong>
                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2 py-0 js-meta-help-trigger" data-help-topic="meta-overview">
                        <i class="bi bi-question-circle"></i> Ajuda & Ações Meta
                    </button>
                </div>

                <div class="card-body wa-actions">
                    <div class="wa-actions-grid d-flex flex-wrap gap-2">
                        <button type="button" id="wa-btn-test-connection" class="btn btn-primary flex-fill">
                            <i class="bi bi-shield-check"></i> Testar Conexão Graph API
                        </button>

                        <button type="button" id="wa-btn-send-test" class="btn btn-success flex-fill">
                            <i class="bi bi-send"></i> Enviar Teste Oficial
                        </button>

                        <button type="button" id="wa-btn-copy-webhook" class="btn btn-secondary flex-fill">
                            <i class="bi bi-clipboard-check"></i> Copiar URL Webhook
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5 col-lg-5 mb-3">
            <div class="card mb-3 border-success">
                <div class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-patch-check-fill text-success"></i> WhatsApp Cloud API Oficial (Meta)</strong>
                    <span class="badge bg-success">v21.0 Oficial</span>
                </div>

                <div class="card-body">
                    <div id="wa-meta-status-box" class="p-3 rounded bg-light border">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 24px;">
                                <i class="bi bi-whatsapp"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Canal Oficial Homologado</h6>
                                <small class="text-muted">Zero risco de banimento por QR Code ou emulação</small>
                            </div>
                        </div>

                        <ul class="list-group list-group-flush small mb-3">
                            <li class="list-group-item d-flex justify-content-between px-0 bg-transparent">
                                <span class="text-muted">Motor de Conexão:</span>
                                <strong class="text-success"><i class="bi bi-cloud-check"></i> Meta Graph API</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 bg-transparent">
                                <span class="text-muted">Versão da API:</span>
                                <span class="badge bg-primary">v21.0</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                <span class="text-muted">Qualidade do Número:</span>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge bg-success" id="wa-meta-quality-badge">ALTA (GREEN)</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-success" data-help-topic="meta-quality-rating" title="Ver regras de Qualidade e Limites de Envio (Tiers)">
                                        <i class="bi bi-question-circle-fill"></i>
                                    </a>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                <span class="text-muted">Janela de Interação:</span>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge bg-info text-dark">Regra 24h Ativa</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-info" data-help-topic="meta-anti-ban" title="Entenda a janela de 24 horas e envio de templates">
                                        <i class="bi bi-question-circle-fill"></i>
                                    </a>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                <span class="text-muted">Segurança Webhook:</span>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge bg-dark"><i class="bi bi-lock-fill"></i> HMAC-SHA256</span>
                                    <a href="javascript:void(0)" class="js-meta-help-trigger text-secondary" data-help-topic="meta-app-secret" title="Entenda a validação de assinatura criptográfica">
                                        <i class="bi bi-question-circle-fill"></i>
                                    </a>
                                </div>
                            </li>
                        </ul>

                        <div class="alert alert-info py-2 px-3 mb-0 small">
                            <i class="bi bi-info-circle-fill"></i> Os disparos de cobrança fora da janela de 24 horas utilizam <strong>Message Templates Aprovados</strong> com chave Pix Copia e Cola e link dinâmico da fatura pública.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>Dados da instância</strong>
                </div>

                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded" style="background: var(--wa-surface-soft); border: 1px solid var(--wa-border);">
                        <div id="wa-avatar-container" class="position-relative" style="width: 52px; height: 52px; flex-shrink: 0;">
                            @if(!empty($initialStatus['avatar']))
                                <img id="wa-avatar-img" src="{{ $initialStatus['avatar'] }}" alt="WhatsApp Avatar" referrerpolicy="no-referrer" class="rounded-circle" style="width: 52px; height: 52px; object-fit: cover;" onerror="this.style.display='none'; document.getElementById('wa-avatar-placeholder').classList.remove('d-none'); document.getElementById('wa-avatar-placeholder').classList.add('d-flex');">
                                <div id="wa-avatar-placeholder" class="rounded-circle bg-primary-subtle text-primary d-none align-items-center justify-content-center fw-bold fs-4" style="width: 52px; height: 52px;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            @else
                                <div id="wa-avatar-placeholder" class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 52px; height: 52px;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <img id="wa-avatar-img" src="" alt="WhatsApp Avatar" referrerpolicy="no-referrer" class="rounded-circle d-none" style="width: 52px; height: 52px; object-fit: cover;" onerror="this.style.display='none'; document.getElementById('wa-avatar-placeholder').classList.remove('d-none'); document.getElementById('wa-avatar-placeholder').classList.add('d-flex');">
                            @endif
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <strong id="wa-profile-header-name" class="d-block text-truncate fs-6">{{ $initialStatus['name'] ?? ($initialConnected ? 'WhatsApp Conectado' : 'Não conectado') }}</strong>
                            <small id="wa-profile-header-number" class="text-muted d-block">{{ $initialNumberDisplay }}</small>
                            <span id="wa-profile-header-badge" class="badge {{ $initialBadgeClass }} mt-1">{{ $initialLabel }}</span>
                        </div>
                    </div>

                    <div class="wa-status-grid">
                        <div class="wa-status-row">
                            <span class="wa-status-label">Estado</span>
                            <span id="wa-status-state" class="wa-status-value">{{ $initialLabel }}</span>
                        </div>

                        <div class="wa-status-row">
                            <span class="wa-status-label">Número</span>
                            <span id="wa-status-number" class="wa-status-value">{{ $initialNumberDisplay }}</span>
                        </div>

                        <div class="wa-status-row">
                            <span class="wa-status-label">Nome</span>
                            <span id="wa-status-name" class="wa-status-value">{{ $initialStatus['name'] ?? 'Não informado' }}</span>
                        </div>

                        <div class="wa-status-row">
                            <span class="wa-status-label">Instância</span>
                            <span id="wa-status-instance" class="wa-status-value">{{ $initialStatus['instance'] ?? 'Não informado' }}</span>
                        </div>

                        <div class="wa-status-row">
                            <span class="wa-status-label">Cliente</span>
                            <span id="wa-status-client" class="wa-status-value">{{ $initialStatus['client_name'] ?? 'Não informado' }}</span>
                        </div>

                        <div class="wa-status-row">
                            <span class="wa-status-label">Criado em</span>
                            <span id="wa-status-created-at" class="wa-status-value">{{ $initialStatus['created_at_display'] ?? ($initialStatus['created_at'] ?? 'Não informado') }}</span>
                        </div>

                        <div class="wa-status-row">
                            <span class="wa-status-label">Última verificação</span>
                            <span id="wa-status-last-check" class="wa-status-value">{{ $initialStatus['last_check_display'] ?? ($initialStatus['last_check'] ?? 'Não verificado') }}</span>
                        </div>
                    </div>

                    <div class="d-none">
                        <span id="info-jid">{{ $initialStatus['jid'] ?? '' }}</span>
                        <span id="info-source">{{ $initialStatus['source'] ?? ($initialStatus['_source'] ?? '') }}</span>
                        <div class="js-sync-tenant-instance"></div>
                        <div id="modalInstanceConfig"></div>
                        <span>frm-whatsapp-config</span>
                        <span>btn-connect</span>
                        <span>btn-disconnect</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const whatsappRoutes = {
        status: "{{ route('admin.whatsapp.status') }}",
        test: "{{ route('admin.whatsapp.test') }}",
        sendTest: "{{ route('admin.whatsapp.send-test') }}",
        config: "{{ route('admin.whatsapp.config') }}"
    };
    let whatsappStatusRequestRunning = false;
    let whatsappStatusPollTimer = null;

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                ...(options.headers || {})
            },
            credentials: 'same-origin',
            body: options.body || null
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw data;
        }
        return data;
    }

    function showToast(message, type = 'success') {
        if (window.Toastify) {
            window.Toastify({
                text: message,
                duration: 4000,
                close: true,
                gravity: 'top',
                position: 'right',
                stopOnFocus: true,
                style: {
                    background: type === 'success' ? '#059669' : '#dc2626',
                    borderRadius: '8px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                    fontWeight: '500'
                }
            }).showToast();
            return;
        }

        if (window.toast) {
            if (type === 'success') window.toast.success(message);
            else window.toast.error(message);
            return;
        }

        if (window.Swal) {
            window.Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: 3500
            });
            return;
        }

        console.log(message);
    }

    function showSuccess(message) {
        showToast(message, 'success');
    }

    function showError(message) {
        showToast(message, 'error');
    }

    async function refreshWhatsAppStatus() {
        if (whatsappStatusRequestRunning) return;
        whatsappStatusRequestRunning = true;

        try {
            const payload = await requestJson(whatsappRoutes.status);
            applyMetaWhatsAppStatus(payload);
        } catch (error) {
            applyMetaWhatsAppStatus({
                ok: false,
                connected: false,
                state: 'error',
                status_label: 'Falha de comunicação',
                message: error.message || 'Erro ao consultar Meta Graph API.'
            });
        } finally {
            whatsappStatusRequestRunning = false;
        }
    }

    async function testWhatsAppConnection() {
        const button = document.getElementById('wa-btn-test-connection');

        try {
            setButtonLoading(button, true);
            const payload = await requestJson(whatsappRoutes.test, { method: 'POST' });
            applyMetaWhatsAppStatus(payload);

            if (window.Swal) {
                await window.Swal.fire({
                    title: 'Conexão Meta Verificada!',
                    text: payload.message || 'Comunicação oficial com a Meta Graph API v21.0 validada com sucesso.',
                    icon: 'success',
                    confirmButtonColor: '#059669',
                    confirmButtonText: 'Excelente'
                });
            } else {
                showSuccess(payload.message || 'Conexão oficial validada com a Meta.');
            }
        } catch (error) {
            const msg = error.message || error.error || 'Erro ao validar conexão com a Meta Graph API.';
            if (window.Swal) {
                window.Swal.fire({
                    title: 'Falha na Validação Meta',
                    text: msg,
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Entendido'
                });
            } else {
                showError(msg);
            }
        } finally {
            setButtonLoading(button, false);
        }
    }

    async function sendTestMessage() {
        if (!window.Swal) {
            showError('Interface de diálogo não disponível.');
            return;
        }

        const { value: targetPhone } = await window.Swal.fire({
            title: 'Disparo de Teste Oficial',
            text: 'Informe o número do WhatsApp com DDD (apenas números):',
            input: 'text',
            inputPlaceholder: 'Ex: 21981325441',
            inputAttributes: {
                maxlength: 15,
                autocapitalize: 'off',
                autocorrect: 'off'
            },
            showCancelButton: true,
            confirmButtonText: 'Enviar Mensagem',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            inputValidator: (value) => {
                if (!value || value.replace(/\D/g, '').length < 10) {
                    return 'Informe um número de WhatsApp válido com DDD!';
                }
            }
        });

        if (!targetPhone) return;

        const button = document.getElementById('wa-btn-send-test');
        try {
            setButtonLoading(button, true);
            const payload = await requestJson(whatsappRoutes.sendTest, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    to: targetPhone.replace(/\D/g, ''),
                    message: 'Mensagem de teste homologada via Meta Graph API v21.0 - KDKhost SaaS'
                })
            });

            await window.Swal.fire({
                title: 'Mensagem Enviada!',
                text: payload.message || 'Mensagem oficial despachada com sucesso pela Meta Graph API.',
                icon: 'success',
                confirmButtonColor: '#059669'
            });
        } catch (error) {
            window.Swal.fire({
                title: 'Falha no Disparo',
                text: error.message || 'Não foi possível enviar a mensagem de teste.',
                icon: 'error',
                confirmButtonColor: '#dc2626'
            });
        } finally {
            setButtonLoading(button, false);
        }
    }

    async function saveWhatsAppConfig() {
        const form = document.getElementById('wa-config-form');
        if (!form) return;

        const button = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        try {
            setButtonLoading(button, true);
            const response = await fetch(whatsappRoutes.config, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                credentials: 'same-origin',
                body: formData
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw payload;

            showSuccess(payload.message || 'Credenciais da Meta salvas com sucesso.');
            await refreshWhatsAppStatus();
        } catch (error) {
            showError(error.message || 'Erro ao gravar credenciais.');
        } finally {
            setButtonLoading(button, false);
        }
    }

    async function copyWebhookUrl() {
        const input = document.querySelector('[data-wa-webhook-url]');
        const value = input?.value?.trim() || (window.location.origin + '/api/v1/webhook');

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                input.focus();
                input.select();
                document.execCommand('copy');
            }

            showSuccess('URL do Webhook Oficial Meta copiada!');
        } catch (error) {
            showError('Não foi possível copiar a URL.');
        }
    }

    function applyMetaWhatsAppStatus(payload) {
        const data = payload?.data || payload || {};
        const connected = Boolean(data.connected);
        const label = connected ? 'Conectado (Meta Oficial)' : (data.status_label || 'Desconectado / Pendente');

        setText('wa-status-state', label);
        setText('wa-status-number', data.number || 'Configurado na Meta');
        setText('wa-status-name', data.name || data.verified_name || 'Conta Meta Oficial');
        setText('wa-status-instance', 'meta_official (Graph API v21.0)');
        setText('wa-status-client', 'Meta Cloud API v21.0');
        setText('wa-status-created-at', formatDateBR(data.created_at) || 'Produção');
        setText('wa-status-last-check', formatDateBR(data.last_check) || 'Agora');

        const qualityBadge = document.getElementById('wa-meta-quality-badge');
        if (qualityBadge) {
            const quality = (data.quality || 'GREEN').toUpperCase();
            qualityBadge.textContent = quality === 'GREEN' ? 'ALTA (GREEN)' : quality;
            qualityBadge.className = 'badge ' + (quality === 'GREEN' ? 'bg-success' : 'bg-warning text-dark');
        }

        const headerBadge = document.getElementById('wa-profile-header-badge');
        if (headerBadge) {
            headerBadge.className = 'badge ' + (connected ? 'bg-success' : 'bg-secondary');
            headerBadge.textContent = label;
        }

        const statusBadge = document.getElementById('wa-status-badge');
        if (statusBadge) {
            statusBadge.className = 'badge ' + (connected ? 'bg-success' : 'bg-danger');
            statusBadge.textContent = label;
        }
    }

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    function formatDateBR(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        }).format(date);
    }

    function setButtonLoading(button, loading) {
        if (!button) return;
        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processando...';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('wa-btn-refresh-status')?.addEventListener('click', refreshWhatsAppStatus);
        document.getElementById('wa-btn-test-connection')?.addEventListener('click', testWhatsAppConnection);
        document.getElementById('wa-btn-send-test')?.addEventListener('click', sendTestMessage);
        document.getElementById('wa-btn-copy-webhook')?.addEventListener('click', copyWebhookUrl);

        document.getElementById('wa-config-form')?.addEventListener('submit', function (event) {
            event.preventDefault();
            saveWhatsAppConfig();
        });

        refreshWhatsAppStatus();
    });
})();
</script>
@endpush
