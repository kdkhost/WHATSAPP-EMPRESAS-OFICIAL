<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $contract->title ?: 'Contrato Digital' }} | Assinatura Eletrônica Segura</title>

{{-- Pré-conexão e Fontes Modernas (Inter + Fontes Caligráficas para Assinatura Digitada) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Dancing+Script:wght@600;700&family=Great+Vibes&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.min.css">

@php
    $tenant = $contract->tenant;
    $settingsManager = app(\App\Modules\Settings\Services\SettingsManager::class);
    $tenantWhiteLabel = $tenant?->whiteLabelSettings() ?? [];
    $tenantWhiteLabelEnabled = $tenant?->whiteLabelEnabled() ?? false;
    $companyName = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['brand_name'])
        ? $tenantWhiteLabel['brand_name']
        : ($tenant?->company_name ?: $settingsManager->get('app.name', config('app.name')));
    
    $rawLogo = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['logo_url'])
        ? $tenantWhiteLabel['logo_url']
        : ($settingsManager->get('app.logo') ?: null);
    
    $companyLogo = null;
    if ($rawLogo) {
        $companyLogo = (str_starts_with($rawLogo, 'http://') || str_starts_with($rawLogo, 'https://') || str_starts_with($rawLogo, 'data:'))
            ? $rawLogo
            : asset(ltrim($rawLogo, '/'));
    }
@endphp

<style>
:root {
    --page-bg: #f8fafc;
    --panel-bg: #ffffff;
    --panel-border: rgba(15, 23, 42, 0.08);
    --panel-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
    --ink: #0f172a;
    --muted: #64748b;
    --brand-teal: #0f766e;
    --brand-teal-light: #f0fdfa;
    --brand-dark: #0f172a;
    --brand-blue: #2563eb;
    --success: #10b981;
}

* { box-sizing: border-box; }

html {
    height: 100%;
}

body {
    min-height: 100%;
    margin: 0;
    padding: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background:
        radial-gradient(circle at 10% 10%, rgba(15, 118, 110, 0.05), transparent 40%),
        radial-gradient(circle at 90% 15%, rgba(37, 99, 235, 0.04), transparent 35%),
        var(--page-bg);
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
    display: flex;
    flex-direction: column;
}

.contract-shell {
    flex: 1 0 auto;
    width: 100%;
    max-width: 1440px;
    margin: 0 auto;
    padding: 24px 20px 0;
}

/* ── TOPBAR MODERNA COM LOGO E GLASSMORPHISM ── */
.contract-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 28px;
    padding: 20px 28px;
    background: linear-gradient(135deg, #091322 0%, #0d2b2a 100%);
    color: #fff;
    border-radius: 22px;
    box-shadow: 0 18px 40px rgba(9, 19, 34, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

.contract-topbar::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0; width: 35%;
    background: radial-gradient(circle at top right, rgba(20, 184, 166, 0.15), transparent 70%);
    pointer-events: none;
}

.topbar-brand-area {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 0;
    z-index: 1;
}

.company-logo-badge {
    background: #ffffff;
    padding: 8px 14px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
    flex-shrink: 0;
    max-width: 190px;
}

.company-logo-img {
    max-height: 48px;
    max-width: 160px;
    width: auto;
    height: auto;
    object-fit: contain;
    display: block;
}

.company-logo-fallback {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.3), rgba(37, 99, 235, 0.2));
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    font-weight: 700;
    flex-shrink: 0;
}

.topbar-info {
    min-width: 0;
    flex: 1;
}

.company-name-tag {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: #2dd4bf;
}

.badge-light-blur {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    font-size: 11px;
    padding: 3px 9px;
    border-radius: 8px;
    font-weight: 600;
    backdrop-filter: blur(6px);
}

.badge-subtle-white {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
    font-size: 11px;
    padding: 3px 9px;
    border-radius: 8px;
}

.badge-dark-glass {
    background: rgba(0, 0, 0, 0.4);
    color: #38bdf8;
    border: 1px solid rgba(56, 189, 248, 0.25);
    font-size: 11px;
    padding: 3px 9px;
    border-radius: 8px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.contract-title-heading {
    font-size: 21px;
    font-weight: 800;
    margin: 4px 0 3px;
    color: #ffffff;
    line-height: 1.25;
    word-break: break-word;
}

.contract-subtitle-text {
    margin: 0;
    font-size: 13px;
    color: #cbd5e1;
    line-height: 1.5;
}

.topbar-security-seal {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 18px;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 16px;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
    z-index: 1;
}

/* ── GRID PRINCIPAL: DOCUMENTO (ESQUERDA) + ASSINATURA (DIREITA) ── */
.contract-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(370px, 460px);
    gap: 28px;
    align-items: start;
}

.document-panel,
.sign-panel {
    background: var(--panel-bg);
    border: 1px solid var(--panel-border);
    border-radius: 24px;
    box-shadow: var(--panel-shadow);
    overflow: visible;
}

.document-panel {
    padding: 22px;
}

.sign-panel {
    position: sticky;
    top: 20px;
    overflow: visible;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.cs-readonly-input {
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
    color: #0f172a !important;
    font-weight: 600 !important;
    cursor: default !important;
    user-select: all;
}

.cs-readonly-input:focus {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    box-shadow: none !important;
}

/* ── TOOLBAR DO VISUALIZADOR ── */
.doc-toolbar {
    padding-bottom: 14px;
    border-bottom: 1px solid var(--panel-border);
}

.btn-zoom {
    font-size: 12px;
    padding: 5px 11px;
    border-radius: 10px;
    font-weight: 600;
    color: var(--muted);
    border-color: #e2e8f0;
    background: #fff;
    transition: all .15s ease;
}

.btn-zoom:hover {
    color: var(--ink);
    border-color: #cbd5e1;
    background: #f8fafc;
}

/* ── SCALER RESPONSIVO DA FOLHA A4 ── */
.cs-doc-scaler {
    width: 100%;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    overflow: visible;
    padding-top: 10px;
    padding-bottom: 0;
    margin-bottom: 0;
}

/* ── RODAPÉ PADRÃO DO SITE ── */
.site-footer {
    width: 100%;
    margin-top: 36px;
    margin-bottom: 0;
    padding: 28px 16px 22px;
    background: #091322;
    color: #94a3b8;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.06);
    position: relative;
    z-index: 10;
}

.footer-seals {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.footer-seal-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    font-size: 11.5px;
    color: #cbd5e1;
    font-weight: 500;
}

.footer-links a {
    color: #94a3b8;
    text-decoration: none;
    transition: color .15s ease;
}

.footer-links a:hover {
    color: #fff;
}

/* ── OVERLAY DE CARREGAMENTO (SPINNER CENTRALIZADO) ── */
.sign-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.sign-overlay.show {
    display: flex !important;
}

.sign-overlay-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 36px 32px;
    max-width: 440px;
    text-align: center;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* ── BARRA FIXA FLUTUANTE EM DISPOSITIVOS MÓVEIS ── */
.mobile-sticky-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-top: 1px solid rgba(15, 23, 42, 0.1);
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
    z-index: 999;
}

.btn-mobile-sign {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 12px 18px;
    background: linear-gradient(135deg, var(--brand-teal), #0d9488);
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
    border-radius: 14px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(15, 118, 110, 0.35);
}

.btn-mobile-sign:hover {
    color: #ffffff;
}

/* ── PAINEL DE ASSINATURA ── */
.sign-panel-header {
    padding: 22px 24px 18px;
    border-bottom: 1px solid var(--panel-border);
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    border-radius: 24px 24px 0 0;
}

.sign-panel-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}

.sign-panel-header p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.5;
}

.sign-panel-body {
    padding: 24px;
}

.sign-card-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.sign-meta-box {
    border: 1px solid var(--panel-border);
    border-radius: 14px;
    padding: 12px 14px;
    background: #f8fafc;
}

.sign-meta-box strong {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted);
    display: block;
    margin-bottom: 3px;
}

.sign-meta-box span {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--ink);
    display: block;
}

/* ── ABAS DE ASSINATURA (DESENHAR VS DIGITAR) ── */
.sig-mode-tabs {
    display: flex;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
    margin-bottom: 14px;
}

.sig-mode-tab {
    flex: 1;
    border: 0;
    background: transparent;
    padding: 8px 12px;
    border-radius: 9px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--muted);
    transition: all .15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.sig-mode-tab.active {
    background: #ffffff;
    color: var(--brand-teal);
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

/* ── CANVAS DE ASSINATURA ── */
.canvas-wrap {
    border: 2px dashed rgba(15, 118, 110, 0.28);
    border-radius: 16px;
    background: #ffffff;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: border-color .18s;
}

.canvas-wrap:hover {
    border-color: rgba(15, 118, 110, 0.5);
}

.canvas-wrap.has-sign {
    border-style: solid;
    border-color: rgba(16, 185, 129, 0.45);
    background: #fcfdfd;
}

#canvas {
    display: block;
    width: 100%;
    height: 160px;
    touch-action: none;
    cursor: crosshair;
}

/* Paleta de Cores de Caneta */
.pen-color-picker {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pen-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #cbd5e1;
    transition: transform .12s;
}

.pen-dot.active {
    transform: scale(1.25);
    box-shadow: 0 0 0 2px var(--brand-teal);
}

/* Caixa de Assinatura Digitada (Modo Caligráfico) */
.typed-sig-wrap {
    display: none;
    flex-direction: column;
    gap: 12px;
}

.typed-sig-preview-card {
    border: 2px dashed rgba(15, 118, 110, 0.28);
    border-radius: 16px;
    padding: 24px 16px;
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    text-align: center;
}

.typed-sig-text {
    font-size: 34px;
    color: #0f172a;
    line-height: 1.2;
    word-break: break-word;
}

.font-choice-btn {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 12px;
    cursor: pointer;
    transition: all .15s ease;
}

.font-choice-btn.active {
    border-color: var(--brand-teal);
    background: var(--brand-teal-light);
    color: var(--brand-teal);
    font-weight: 700;
}

/* ── CARDS DE CANAL DE RECEBIMENTO ── */
.channel-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.channel-card {
    border: 1.5px solid var(--panel-border);
    border-radius: 14px;
    padding: 12px 10px;
    text-align: center;
    cursor: pointer;
    transition: all .15s ease;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.channel-card input { display: none; }

.channel-card:hover {
    border-color: var(--brand-teal);
    background: rgba(15, 118, 110, 0.02);
}

.channel-card.selected {
    border-color: var(--brand-teal);
    background: rgba(15, 118, 110, 0.06);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

.channel-card i {
    font-size: 22px;
    margin-bottom: 4px;
}

.channel-card strong {
    font-size: 12px;
    display: block;
}

.channel-card small {
    font-size: 10px;
    color: var(--muted);
    line-height: 1.3;
    margin-top: 2px;
    display: block;
    word-break: break-all;
}

.channel-card.disabled {
    opacity: .45;
    cursor: not-allowed;
    border-style: dashed;
}

/* ── TERMOS DE ACEITE ── */
.terms-box {
    border-radius: 14px;
    padding: 14px 16px;
    background: #f8fafc;
    border: 1px solid var(--panel-border);
    font-size: 12.5px;
    line-height: 1.55;
    transition: border-color .15s;
}

.terms-box:has(:checked) {
    border-color: rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.03);
}

/* ── BOTÃO DE CONFIRMAR ASSINATURA ── */
.btn-sign {
    width: 100%;
    border: 0;
    border-radius: 14px;
    padding: 16px 24px;
    background: linear-gradient(135deg, #10b981 0%, #0f766e 100%);
    color: #fff;
    font-weight: 700;
    font-size: 15.5px;
    letter-spacing: .01em;
    box-shadow: 0 10px 24px rgba(16, 185, 129, 0.28);
    transition: all .2s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-sign:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(16, 185, 129, 0.35);
}

.btn-sign:disabled {
    opacity: .45;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
    filter: grayscale(60%);
}

/* ── BOTÃO FLUTUANTE EM MOBILE ── */
.mobile-sticky-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    padding: 12px 16px;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(12px);
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    z-index: 1040;
    display: flex;
    justify-content: center;
}

.btn-mobile-sign {
    width: 100%;
    max-width: 440px;
    padding: 12px 20px;
    border-radius: 12px;
    background: #10b981;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    border: 0;
    text-align: center;
    text-decoration: none;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

/* ── MODAL OVERLAY DE ASSINATURA SENDO REGISTRADA ── */
.sign-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.75);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(6px);
}

.sign-overlay.show {
    display: flex;
}

.sign-overlay-card {
    width: min(92vw, 420px);
    padding: 36px 30px;
    text-align: center;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.35);
}

/* ── RESPONSIVIDADE ── */
@media (max-width: 1080px) {
    .contract-grid {
        grid-template-columns: 1fr;
    }
    .sign-panel {
        position: static;
        margin-top: 24px;
    }
}

@media (max-width: 900px) {
    .btn-zoom-group {
        display: none !important;
    }
    .document-panel {
        padding: 14px 10px !important;
        border-radius: 18px !important;
    }
}

@media (max-width: 768px) {
    .contract-shell {
        padding: 14px 12px 90px;
    }
    .contract-topbar {
        flex-direction: column;
        align-items: stretch;
        padding: 18px 16px;
        gap: 16px;
    }
    .topbar-brand-area {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }
    .company-logo-badge {
        max-width: 100%;
    }
    .contract-title-heading {
        font-size: 19px;
    }
    .topbar-security-seal {
        justify-content: center;
        width: 100%;
    }
    .channel-grid {
        grid-template-columns: 1fr;
    }
    .sign-card-meta {
        grid-template-columns: 1fr;
    }
}

/* ── RESPONSIVIDADE E ADAPTAÇÃO MOBILE & TABLET (SEM OVERFLOW) ── */
html, body {
    overflow-x: hidden !important;
    width: 100% !important;
    max-width: 100% !important;
}

.contract-shell {
    width: 100%;
    box-sizing: border-box;
}

/* Modo Desktop padrão para o painel de assinatura */
.sign-panel-desktop-mode {
    position: sticky;
    top: 20px;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

/* Modo Modal para o painel de assinatura quando inserido no modal */
.sign-panel-modal-mode {
    position: static !important;
    box-shadow: none !important;
    border: none !important;
    border-radius: 0 !important;
    background: transparent !important;
    padding: 20px 18px 24px !important;
    width: 100% !important;
}

.sign-panel-modal-mode .sign-panel-header {
    display: none !important; /* Já temos o modal-header oficial */
}

/* Modal Responsivo Customizado */
.modal-sign-responsive .modal-content {
    border-radius: 24px !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25) !important;
    overflow: hidden;
}

.modal-sign-responsive .modal-header {
    background: linear-gradient(135deg, #091322 0%, #0d2b2a 100%) !important;
}

/* Barra Fixa Flutuante de Ação (Mobile e Tablet) */
.mobile-action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-top: 1px solid rgba(15, 23, 42, 0.1);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    z-index: 1040;
    display: flex;
    justify-content: center;
}

.btn-mobile-open-sign {
    width: 100%;
    max-width: 520px;
    padding: 13px 20px;
    background: linear-gradient(135deg, #0f766e, #0d9488);
    color: #ffffff;
    font-weight: 700;
    font-size: 15px;
    border-radius: 14px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 16px rgba(15, 118, 110, 0.35);
    transition: transform 0.15s ease, filter 0.15s ease;
    cursor: pointer;
}

.btn-mobile-open-sign:hover,
.btn-mobile-open-sign:focus {
    color: #ffffff;
    filter: brightness(1.08);
}

.btn-mobile-open-sign:active {
    transform: scale(0.98);
}

/* Card Chamada para Assinar no fim do documento */
.call-to-sign-box {
    margin-top: 32px;
    padding: 24px 18px;
    background: linear-gradient(135deg, #f0fdfa 0%, #f8fafc 100%);
    border: 1.5px dashed #0d9488;
    border-radius: 18px;
    text-align: center;
}

.btn-teal-primary {
    background: linear-gradient(135deg, #0f766e, #0d9488);
    color: #fff;
    border: none;
}
.btn-teal-primary:hover {
    color: #fff;
    filter: brightness(1.08);
}

@media (max-width: 991.98px) {
    .contract-shell {
        padding: 16px 12px 90px !important;
        max-width: 100% !important;
        width: 100% !important;
        overflow-x: hidden !important;
    }

    .contract-topbar {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px;
        gap: 14px;
        border-radius: 18px;
        margin-bottom: 18px;
    }

    .topbar-brand-area {
        width: 100%;
        gap: 12px;
    }

    .company-logo-badge {
        max-width: 130px;
        padding: 6px 10px;
    }

    .contract-title-heading {
        font-size: 17px;
    }

    .topbar-security-seal {
        width: 100%;
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 12px;
        box-sizing: border-box;
    }

    .contract-grid {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .document-panel {
        padding: 18px 14px !important;
        border-radius: 18px !important;
        border: 1px solid var(--panel-border) !important;
        background: #ffffff !important;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05) !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
    }

    .doc-toolbar {
        padding-bottom: 10px;
        margin-bottom: 12px;
    }

    .doc-toolbar .btn-zoom,
    .doc-toolbar #zoom-label {
        display: none !important;
    }

    .cs-doc-scaler {
        padding-top: 0 !important;
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        width: 100% !important;
    }

    /* Rodapé dá espaço para a barra fixa inferior no mobile/tablet */
    .site-footer {
        padding-bottom: 90px !important;
    }
}

</style>
</head>
<body>

<div class="contract-shell">

    {{-- TOPBAR PRINCIPAL COM LOGO DA EMPRESA E SELO DE VALIDADE --}}
    <header class="contract-topbar">
        <div class="topbar-brand-area">
            @if($companyLogo)
                <div class="company-logo-badge">
                    <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="company-logo-img">
                </div>
            @else
                <div class="company-logo-fallback">
                    {{ strtoupper(substr($companyName ?: 'C', 0, 1)) }}
                </div>
            @endif

            <div class="topbar-info">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="company-name-tag">{{ $companyName }}</span>
                    <span class="badge badge-light-blur">
                        <i class="bi bi-file-earmark-check me-1"></i>{{ $contract->template?->name ?: 'Contrato Digital' }}
                    </span>
                    @if($contract->template?->category)
                        <span class="badge badge-subtle-white">{{ $contract->template->category }}</span>
                    @endif
                    <span class="badge badge-dark-glass">{{ $contract->number }}</span>
                </div>
                <h1 class="contract-title-heading">{{ $contract->title ?: ($contract->template?->name ?: 'Instrumento Particular de Contrato') }}</h1>
                <p class="contract-subtitle-text">
                    @if($contract->template?->description)
                        <strong>Finalidade:</strong> {{ $contract->template->description }} &bull;
                    @endif
                    Emitido com segurança para <strong>{{ $contract->customer?->name }}</strong>
                </p>
            </div>
        </div>

        <div class="topbar-security-seal">
            <i class="bi bi-shield-check text-success fs-3"></i>
            <div>
                <div class="fw-bold" style="font-size:12px;letter-spacing:.02em;">ASSINATURA ELETRÔNICA VÁLIDA</div>
                <div style="font-size:11px;opacity:.85;">MP 2.200-2/2001 &bull; Rastreabilidade por IP e Criptografia</div>
            </div>
        </div>
    </header>

    {{-- GRID: FOLHA DO CONTRATO + PAINEL DE ASSINATURA --}}
    <div class="contract-grid">

        {{-- COLUNA 1: VISUALIZADOR DA FOLHA A4 COM CONTROLES DE ZOOM --}}
        <section class="document-panel">
            <div class="doc-toolbar d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis" style="font-size:12px;border-radius:8px;padding:6px 10px;">
                        <i class="bi bi-file-earmark-richtext me-1"></i>Documento Oficial
                    </span>
                    <small class="text-muted d-none d-sm-inline">Role o documento para ler na íntegra</small>
                </div>
                <div class="d-flex align-items-center gap-1 btn-zoom-group">
                    <button type="button" class="btn btn-sm btn-zoom" id="btn-zoom-out" title="Diminuir zoom">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-zoom" id="btn-zoom-reset" title="Ajustar automaticamente à largura da tela">
                        <i class="bi bi-arrows-angle-contract me-1"></i><span id="zoom-label">Ajustar</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-zoom" id="btn-zoom-in" title="Aumentar zoom">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>
            </div>

            <div class="cs-doc-scaler" id="doc-scaler">
                {!! app(\App\Modules\Contracts\Services\ContractService::class)->renderDocumentHtml($contract, false) !!}
            </div>

            {{-- CHAMADA PARA ASSINAR NO FIM DO DOCUMENTO (MOBILE/TABLET) --}}
            <div class="call-to-sign-box d-lg-none">
                <span class="badge bg-success-subtle text-success fw-bold px-3 py-1 rounded-pill mb-2">
                    <i class="bi bi-check2-all me-1"></i> Fim do Documento
                </span>
                <h6 class="fw-bold text-dark mt-1 mb-2">Pronto para assinar este contrato?</h6>
                <p class="text-muted small mb-3">Você revisou todas as cláusulas na íntegra. Clique abaixo para abrir o formulário de assinatura eletrônica com validade jurídica.</p>
                <button type="button" class="btn btn-teal-primary w-100 py-3 fw-bold rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSign">
                    <i class="bi bi-pen-fill me-2"></i>Assinar Documento Agora
                </button>
            </div>
        </section>

        {{-- COLUNA 2: PAINEL DE ASSINATURA LATERAL STICKY (SLOT DESKTOP) --}}
        <aside class="sign-column-desktop d-none d-lg-block" id="sign-desktop-slot">
            <div class="sign-panel sign-panel-desktop-mode" id="sign-panel">
            <div class="sign-panel-header">
                <h2><i class="bi bi-pen text-primary"></i>Assinar Documento</h2>
                <p>Revise seus dados, informe sua assinatura eletrônica e selecione como deseja receber a cópia assinada.</p>
            </div>

            <div class="sign-panel-body">
                <div class="sign-card-meta">
                    <div class="sign-meta-box">
                        <strong>Nº do Contrato</strong>
                        <span>{{ $contract->number }}</span>
                    </div>
                    <div class="sign-meta-box">
                        <strong>Validade</strong>
                        <span>{{ $contract->expires_at?->timezone('America/Sao_Paulo')->format('d/m/Y') ?: 'Sem expiração' }}</span>
                    </div>
                </div>

                <form id="frm-sign" novalidate>
                    @php
                        $rawDoc = $contract->customer?->document;
                        $formattedDoc = \App\Support\Format::document($rawDoc);
                        if ($formattedDoc === '-' || empty($formattedDoc)) {
                            $formattedDoc = $rawDoc ?: '';
                        }
                    @endphp
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label fw-semibold small mb-1">Nome completo do Signatário <i class="bi bi-lock-fill text-muted ms-1" style="font-size:11px;" title="Dado cadastral confirmado"></i></label>
                            <input name="signer_name" id="inp-signer-name" class="form-control cs-readonly-input" readonly required value="{{ $contract->customer?->name }}" placeholder="Nome do signatário" style="border-radius:10px;">
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold small mb-1">CPF ou CNPJ <i class="bi bi-lock-fill text-muted ms-1" style="font-size:11px;" title="Formatado automaticamente"></i></label>
                            <input name="signer_document" id="inp-signer-doc" class="form-control cs-readonly-input" readonly value="{{ $formattedDoc }}" placeholder="000.000.000-00" style="border-radius:10px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px;">
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label fw-semibold small mb-1">E-mail <i class="bi bi-lock-fill text-muted ms-1" style="font-size:11px;" title="Auditado"></i></label>
                            <input type="email" name="signer_email" id="inp-signer-email" class="form-control cs-readonly-input" readonly required value="{{ $contract->customer?->email }}" placeholder="seu@email.com" style="border-radius:10px; font-size:12.5px; width: 100%;">
                        </div>
                    </div>

                    {{-- SELETOR DE MODO DE ASSINATURA (DESENHAR VS DIGITAR) --}}
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold small mb-0">Assinatura Eletrônica <span class="text-danger">*</span></label>
                            <div class="pen-color-picker" id="pen-picker">
                                <span class="small text-muted me-1" style="font-size:11px;">Cor:</span>
                                <div class="pen-dot active" data-color="#003399" style="background:#003399;" title="Azul Cartorial"></div>
                                <div class="pen-dot" data-color="#0f172a" style="background:#0f172a;" title="Preto Jurídico"></div>
                            </div>
                        </div>

                        <div class="sig-mode-tabs">
                            <button type="button" class="sig-mode-tab active" id="tab-btn-draw">
                                <i class="bi bi-brush"></i>Desenhar
                            </button>
                            <button type="button" class="sig-mode-tab" id="tab-btn-type">
                                <i class="bi bi-fonts"></i>Digitar Nome
                            </button>
                        </div>

                        {{-- MODO 1: CANVAS DE DESENHO --}}
                        <div id="draw-mode-box">
                            <div class="canvas-wrap" id="canvas-wrapper">
                                <canvas id="canvas"></canvas>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2" style="font-size:11.5px;">
                                <span class="text-muted"><i class="bi bi-hand-index-thumb me-1"></i>Use o mouse ou o dedo na tela para assinar.</span>
                                <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0 fw-semibold" id="btn-clear">
                                    <i class="bi bi-eraser me-1"></i>Limpar
                                </button>
                            </div>
                        </div>

                        {{-- MODO 2: DIGITAR NOME (ASSINATURA CALIGRÁFICA AUTOMÁTICA) --}}
                        <div id="type-mode-box" class="typed-sig-wrap">
                            <div class="d-flex gap-2 flex-wrap mb-1">
                                <button type="button" class="font-choice-btn active" data-font="'Dancing Script', cursive">Estilo Cursivo 1</button>
                                <button type="button" class="font-choice-btn" data-font="'Caveat', cursive">Estilo Cursivo 2</button>
                                <button type="button" class="font-choice-btn" data-font="'Great Vibes', cursive">Estilo Caligráfico</button>
                            </div>
                            <div class="typed-sig-preview-card" id="typed-sig-card">
                                <span class="typed-sig-text" id="typed-sig-render" style="font-family: 'Dancing Script', cursive; color: #003399;">
                                    {{ $contract->customer?->name ?: 'Sua Assinatura' }}
                                </span>
                            </div>
                            <small class="text-muted" style="font-size:11.5px;">
                                <i class="bi bi-magic me-1 text-primary"></i>Assinatura eletrônica caligráfica baseada no nome informado acima.
                            </small>
                        </div>

                        {{-- STATUS DO DESENHO / ASSINATURA --}}
                        <div class="mt-2 text-end">
                            <span id="sig-status" class="badge bg-secondary-subtle text-secondary" style="font-size:11px;padding:4px 8px;">
                                Aguardando assinatura
                            </span>
                        </div>
                    </div>

                    {{-- CANAL DE RECEBIMENTO DA CÓPIA ASSINADA --}}
                    <div class="mt-4">
                        <label class="form-label fw-semibold small mb-1">Recebimento da sua cópia assinada</label>
                        <div class="channel-grid">
                            <label class="channel-card selected">
                                <input type="radio" name="return_channel" value="email" checked>
                                <i class="bi bi-envelope-check text-primary"></i>
                                <strong>E-mail</strong>
                                <small id="lbl-channel-email">{{ $contract->customer?->email ?: 'E-mail informado' }}</small>
                            </label>

                            @if($contract->customer?->whatsapp)
                                <label class="channel-card">
                                    <input type="radio" name="return_channel" value="whatsapp">
                                    <i class="bi bi-whatsapp text-success"></i>
                                    <strong>WhatsApp</strong>
                                    <small>{{ $contract->customer->whatsapp }}</small>
                                </label>
                                <label class="channel-card">
                                    <input type="radio" name="return_channel" value="both">
                                    <i class="bi bi-send-check text-warning"></i>
                                    <strong>Ambos</strong>
                                    <small>Email + WhatsApp</small>
                                </label>
                            @else
                                <label class="channel-card disabled" title="Cliente sem WhatsApp cadastrado">
                                    <i class="bi bi-phone text-muted"></i>
                                    <strong>WhatsApp</strong>
                                    <small>Não cadastrado</small>
                                </label>
                            @endif
                        </div>
                        <div class="form-text small mt-1 text-muted">
                            <i class="bi bi-info-circle me-1"></i>O fornecedor do contrato também receberá uma via oficial no WhatsApp assim que confirmada.
                        </div>
                    </div>

                    {{-- TERMOS DE ACEITE --}}
                    <div class="terms-box mt-4">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" value="1" id="terms" name="accept_terms" required>
                            <label class="form-check-label" for="terms">
                                Li integralmente o contrato, concordo com todas as suas cláusulas e autorizo o registro desta assinatura eletrônica com validade jurídica plena (Medida Provisória nº 2.200-2/2001).
                            </label>
                        </div>
                    </div>

                    {{-- BOTÃO DE SUBMISSÃO --}}
                    <button type="submit" class="btn-sign mt-4" id="btn-submit" disabled>
                        <i class="bi bi-check2-circle me-2 fs-5"></i>Confirmar assinatura
                    </button>
                </form>
            </div>
        </div>{{-- /sign-panel --}}
        </aside>{{-- /sign-desktop-slot --}}

    </div>{{-- /contract-grid --}}
</div>{{-- /contract-shell --}}

{{-- RODAPÉ PADRÃO DO SITE --}}
<footer class="site-footer">
    <div class="container text-center">
        <div class="footer-seals">
            <span class="footer-seal-item">
                <i class="bi bi-shield-check text-success"></i>
                <span>Criptografia SHA-256</span>
            </span>
            <span class="footer-seal-item">
                <i class="bi bi-patch-check text-info"></i>
                <span>MP nº 2.200-2/2001</span>
            </span>
            <span class="footer-seal-item">
                <i class="bi bi-file-earmark-lock text-warning"></i>
                <span>Lei nº 14.063/2020</span>
            </span>
        </div>
        <p class="m-0 mb-1 small text-white-50">
            &copy; {{ date('Y') }} {{ $companyName }}. {{ $settingsManager->get('app.footer_text', 'Todos os direitos reservados.') }}
        </p>
        <p class="m-0 small footer-links">
            <a href="/privacidade" target="_blank" rel="noopener noreferrer">Política de Privacidade</a>
            <span class="text-white-50 mx-2">&middot;</span>
            <a href="/termos" target="_blank" rel="noopener noreferrer">Termos de Uso</a>
        </p>
    </div>
</footer>

{{-- MODAL DE ASSINATURA ELETRÔNICA (MOBILE E TABLET) --}}
<div class="modal fade modal-sign-responsive" id="modalSign" tabindex="-1" aria-labelledby="modalSignLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-white border-opacity-10 py-3 px-3 px-sm-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center rounded-3 p-2" style="background: rgba(45, 212, 191, 0.15); color: #2dd4bf; width: 36px; height: 36px;">
                        <i class="bi bi-pen-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" id="modalSignLabel">Assinatura Eletrônica Segura</h6>
                        <span class="text-white-50" style="font-size: 11px;">Validade jurídica plena conforme MP nº 2.200-2/2001</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0" id="sign-mobile-slot">
                {{-- O painel de assinatura (#sign-panel) é montado aqui dinamicamente no mobile/tablet --}}
            </div>
        </div>
    </div>
</div>

{{-- BARRA FIXA FLUTUANTE EM DISPOSITIVOS MÓVEIS E TABLETS --}}
<div class="mobile-action-bar d-lg-none">
    <button type="button" class="btn-mobile-open-sign" data-bs-toggle="modal" data-bs-target="#modalSign">
        <i class="bi bi-pen-fill"></i>Assinar Documento
    </button>
</div>



{{-- MODAL / OVERLAY DE CARREGAMENTO DURANTE O PROCESSAMENTO DA ASSINATURA --}}
<div class="sign-overlay" id="sign-overlay">
    <div class="sign-overlay-card">
        <div class="spinner-border text-success mb-3" style="width:3.2rem;height:3.2rem;" role="status"></div>
        <h5 class="fw-bold mb-2">Selando documento com validade jurídica…</h5>
        <p class="text-muted small mb-0">Gerando carimbo criptográfico, carimbando IP e despachando as vias assinadas para o cliente e fornecedor.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script>
(() => {
    'use strict';

    // Garante que versões cacheadas em runtime do Service Worker sejam eliminadas
    if ('serviceWorker' in navigator && 'caches' in window) {
        caches.keys().then(keys => {
            keys.forEach(k => {
                if (k.includes('kdkhost-whatsapp-')) {
                    caches.open(k).then(cache => {
                        cache.keys().then(reqs => {
                            reqs.forEach(req => {
                                if (req.url.includes('/contrato/')) {
                                    cache.delete(req);
                                }
                            });
                        });
                    });
                }
            });
        });
    }

    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const canvasWrapper = document.getElementById('canvas-wrapper');
    const sigStatus = document.getElementById('sig-status');
    const btnSubmit = document.getElementById('btn-submit');
    const termsCheck = document.getElementById('terms');
    const nameInput = document.getElementById('inp-signer-name');

    let currentMode = 'draw'; // 'draw' ou 'type'
    let penColor = '#003399';
    let selectedFont = "'Dancing Script', cursive";
    let drawing = false;
    let hasDrawnSignature = false;

    /* ── AJUSTE DE RESOLUÇÃO DO CANVAS (RETINA / HD) ── */
    function resizeCanvas() {
        if (!canvasWrapper) return;
        const ratio = Math.max(1, window.devicePixelRatio || 1);
        const rect = canvasWrapper.getBoundingClientRect();
        const displayW = Math.max(1, Math.floor(rect.width));
        const displayH = 160;

        // Só preserva imagem se o usuário tiver feito traço manual
        const prevData = hasDrawnSignature ? canvas.toDataURL('image/png') : null;

        canvas.width = Math.floor(displayW * ratio);
        canvas.height = Math.floor(displayH * ratio);
        canvas.style.width = displayW + 'px';
        canvas.style.height = displayH + 'px';

        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.8;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = penColor;

        if (prevData) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0, displayW, displayH);
            img.src = prevData;
        }
    }

    /* ── CAPTURA DE PONTOS COM PRECISÃO ── */
    function point(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function isSignatureReady() {
        if (currentMode === 'draw') {
            return hasDrawnSignature;
        }
        return (nameInput.value || '').trim().length > 0;
    }

    function updateButton() {
        const termsChecked = termsCheck.checked;
        const ready = isSignatureReady();
        btnSubmit.disabled = !(ready && termsChecked);

        if (ready) {
            sigStatus.textContent = 'Assinatura pronta ✓';
            sigStatus.className = 'badge bg-success-subtle text-success fw-semibold';
            canvasWrapper.classList.toggle('has-sign', currentMode === 'draw' && hasDrawnSignature);
        } else {
            sigStatus.textContent = 'Aguardando assinatura';
            sigStatus.className = 'badge bg-secondary-subtle text-secondary';
            canvasWrapper.classList.remove('has-sign');
        }
    }

    function start(e) {
        drawing = true;
        const p = point(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function move(e) {
        if (!drawing) return;
        const p = point(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        hasDrawnSignature = true;
        updateButton();
    }

    function end() {
        drawing = false;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);

    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); start(e); }, { passive: false });
    canvas.addEventListener('touchmove', (e) => { e.preventDefault(); move(e); }, { passive: false });
    canvas.addEventListener('touchend', (e) => { e.preventDefault(); end(); }, { passive: false });

    /* ── SELETOR DE CORES DA CANETA ── */
    document.querySelectorAll('.pen-dot').forEach(dot => {
        dot.addEventListener('click', () => {
            document.querySelectorAll('.pen-dot').forEach(d => d.classList.remove('active'));
            dot.classList.add('active');
            penColor = dot.dataset.color;
            ctx.strokeStyle = penColor;
            typedSigRender.style.color = penColor;
        });
    });

    /* ── BOTÃO LIMPAR ASSINATURA ── */
    document.getElementById('btn-clear').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawnSignature = false;
        updateButton();
    });

    /* ── ALTERNÂNCIA ENTRE MODO DESENHO E MODO DIGITADO ── */
    const tabBtnDraw = document.getElementById('tab-btn-draw');
    const tabBtnType = document.getElementById('tab-btn-type');
    const drawModeBox = document.getElementById('draw-mode-box');
    const typeModeBox = document.getElementById('type-mode-box');
    const typedSigRender = document.getElementById('typed-sig-render');

    tabBtnDraw.addEventListener('click', () => {
        currentMode = 'draw';
        tabBtnDraw.classList.add('active');
        tabBtnType.classList.remove('active');
        drawModeBox.style.display = 'block';
        typeModeBox.style.display = 'none';
        resizeCanvas();
        updateButton();
    });

    tabBtnType.addEventListener('click', () => {
        currentMode = 'type';
        tabBtnType.classList.add('active');
        tabBtnDraw.classList.remove('active');
        drawModeBox.style.display = 'none';
        typeModeBox.style.display = 'flex';
        updateTypedSignaturePreview();
        updateButton();
    });

    /* ── PREVIEW DA ASSINATURA CALIGRÁFICA (NUNCA TOCA NO CANVAS DE DESENHO) ── */
    function updateTypedSignaturePreview() {
        const text = (nameInput.value || '').trim() || 'Sua Assinatura';
        typedSigRender.textContent = text;
        typedSigRender.style.fontFamily = selectedFont;
        typedSigRender.style.color = penColor;
    }

    document.querySelectorAll('.font-choice-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.font-choice-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedFont = btn.dataset.font;
            updateTypedSignaturePreview();
        });
    });

    /* ── SELETOR DE CANAIS ── */
    document.querySelectorAll('.channel-card input[type=radio]').forEach((inp) => {
        inp.addEventListener('change', () => {
            document.querySelectorAll('.channel-card').forEach((c) => c.classList.remove('selected'));
            inp.closest('.channel-card')?.classList.add('selected');
        });
    });

    termsCheck.addEventListener('change', updateButton);

    /* ── SINCRONIZAÇÃO RESPONSIVA DE SLOTS: DESKTOP (STICKY) VS MOBILE/TABLET (MODAL) ── */
    function syncSignPlacement() {
        const isMobileOrTablet = window.innerWidth < 992;
        const signPanel = document.getElementById('sign-panel');
        const desktopSlot = document.getElementById('sign-desktop-slot');
        const mobileSlot = document.getElementById('sign-mobile-slot');
        if (!signPanel || !desktopSlot || !mobileSlot) return;

        if (isMobileOrTablet) {
            if (signPanel.parentElement !== mobileSlot) {
                mobileSlot.appendChild(signPanel);
                signPanel.classList.remove('sign-panel-desktop-mode');
                signPanel.classList.add('sign-panel-modal-mode');
                setTimeout(resizeCanvas, 150);
            }
        } else {
            if (signPanel.parentElement !== desktopSlot) {
                desktopSlot.appendChild(signPanel);
                signPanel.classList.add('sign-panel-desktop-mode');
                signPanel.classList.remove('sign-panel-modal-mode');
                setTimeout(resizeCanvas, 150);
            }
            const modalEl = document.getElementById('modalSign');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        }
    }

    window.addEventListener('resize', syncSignPlacement);
    document.addEventListener('DOMContentLoaded', syncSignPlacement);
    setTimeout(syncSignPlacement, 50);

    const modalSignEl = document.getElementById('modalSign');
    if (modalSignEl) {
        modalSignEl.addEventListener('shown.bs.modal', () => {
            setTimeout(resizeCanvas, 120);
        });
    }

    /* ── SCALER RESPONSIVO DA FOLHA A4 (SEMPRE NÍTIDO E SEM CORTE) ── */
    let currentZoom = 'auto';
    const scaler = document.getElementById('doc-scaler');
    const zoomLabel = document.getElementById('zoom-label');

    function applyDocumentScale() {
        if (!scaler) return;
        const page = scaler.querySelector('.cs-page');
        if (!page) return;

        if (window.innerWidth < 992) {
            page.style.transform = '';
            page.style.transformOrigin = '';
            scaler.style.height = 'auto';
            scaler.style.overflow = 'visible';
            return;
        }

        const availableW = scaler.clientWidth || (window.innerWidth - 32);
        const baseW = 794;

        if (currentZoom === 'auto') {
            if (availableW < baseW) {
                const sc = Math.max(0.35, Math.min(1, (availableW - 8) / baseW));
                page.style.transform = `scale(${sc})`;
                page.style.transformOrigin = 'top center';
                const rect = page.getBoundingClientRect();
                scaler.style.height = Math.ceil(rect.height) + 'px';
                scaler.style.overflow = 'visible';
                if (zoomLabel) zoomLabel.textContent = Math.round(sc * 100) + '%';
            } else {
                page.style.transform = 'none';
                page.style.transformOrigin = '';
                scaler.style.height = 'auto';
                scaler.style.overflow = 'visible';
                if (zoomLabel) zoomLabel.textContent = '100%';
            }
        } else {
            const sc = parseFloat(currentZoom);
            page.style.transform = `scale(${sc})`;
            page.style.transformOrigin = 'top center';
            const rect = page.getBoundingClientRect();
            scaler.style.height = Math.ceil(rect.height) + 'px';
            scaler.style.overflow = 'visible';
            if (zoomLabel) zoomLabel.textContent = Math.round(sc * 100) + '%';
        }
    }

    window.addEventListener('resize', applyDocumentScale);
    window.addEventListener('load', applyDocumentScale);
    setTimeout(applyDocumentScale, 200);
    setTimeout(applyDocumentScale, 600);

    document.getElementById('btn-zoom-out')?.addEventListener('click', () => {
        let cur = currentZoom === 'auto' ? (scaler.clientWidth / 794) : parseFloat(currentZoom);
        currentZoom = Math.max(0.4, cur - 0.15);
        applyDocumentScale();
    });
    document.getElementById('btn-zoom-in')?.addEventListener('click', () => {
        let cur = currentZoom === 'auto' ? (scaler.clientWidth / 794) : parseFloat(currentZoom);
        currentZoom = Math.min(1.6, cur + 0.15);
        applyDocumentScale();
    });
    document.getElementById('btn-zoom-reset')?.addEventListener('click', () => {
        currentZoom = currentZoom === 'auto' ? 1.0 : 'auto';
        applyDocumentScale();
    });

    /* ── SUBMISSÃO DA ASSINATURA ELETRÔNICA COM SWEETALERT2 ── */
    document.getElementById('frm-sign').addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!isSignatureReady()) {
            Swal.fire({
                icon: 'warning',
                title: 'Assinatura necessária',
                text: currentMode === 'draw'
                    ? 'Por favor, desenhe sua assinatura no campo indicado antes de confirmar.'
                    : 'Por favor, informe seu nome para gerar a assinatura eletrônica.',
                confirmButtonColor: '#0f766e'
            });
            return;
        }

        if (!nameInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Nome obrigatório',
                text: 'Informe seu nome completo para registro no termo de assinatura.',
                confirmButtonColor: '#0f766e'
            });
            return;
        }

        const emailInput = document.getElementById('inp-signer-email');
        if (!emailInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'E-mail obrigatório',
                text: 'Informe seu e-mail para validação da assinatura eletrônica.',
                confirmButtonColor: '#0f766e'
            });
            return;
        }

        if (!termsCheck.checked) {
            Swal.fire({
                icon: 'warning',
                title: 'Termo de Aceite',
                text: 'Marque a caixa de concordância com os termos do contrato para prosseguir.',
                confirmButtonColor: '#0f766e'
            });
            termsCheck.focus();
            return;
        }

        const overlay = document.getElementById('sign-overlay');
        overlay.classList.add('show');

        // Geração da assinatura em formato PNG com proporção perfeita
        let signatureDataUrl = '';
        if (currentMode === 'draw') {
            signatureDataUrl = canvas.toDataURL('image/png');
        } else {
            // Renderiza no canvas virtual em alta definição dedicado
            const vCanvas = document.createElement('canvas');
            vCanvas.width = 600;
            vCanvas.height = 180;
            const vCtx = vCanvas.getContext('2d');
            vCtx.clearRect(0, 0, 600, 180);
            vCtx.fillStyle = penColor;
            vCtx.textAlign = 'center';
            vCtx.textBaseline = 'middle';
            
            const sigText = (nameInput.value || '').trim() || 'Assinatura';
            let fSize = 44;
            if (sigText.length > 25) fSize = 28;
            else if (sigText.length > 18) fSize = 34;

            vCtx.font = `600 ${fSize}px ${selectedFont}`;
            vCtx.fillText(sigText, 300, 90);
            signatureDataUrl = vCanvas.toDataURL('image/png');
        }

        const fd = new FormData(e.target);
        fd.set('signature_image', signatureDataUrl);

        try {
            const res = await fetch('{{ route('public.contracts.sign', $contract->access_token) }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: fd
            });

            let data = null;
            try { data = await res.json(); } catch (_) { data = { ok: false, error: 'Erro de comunicação.' }; }
            overlay.classList.remove('show');

            if (res.ok && data.ok) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Contrato assinado com sucesso!',
                    text: 'Sua assinatura foi registrada com validade jurídica plena. As vias oficiais foram despachadas para você e para o fornecedor do contrato.',
                    confirmButtonText: '<i class="bi bi-file-earmark-check me-1"></i> Visualizar contrato assinado',
                    confirmButtonColor: '#10b981'
                });
                window.location = data.redirect;
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Não foi possível concluir',
                text: data.error || data.message || 'Verifique os campos informados e tente novamente.',
                confirmButtonColor: '#ef4444'
            });
        } catch (_) {
            overlay.classList.remove('show');
            Swal.fire({
                icon: 'error',
                title: 'Falha de conexão',
                text: 'Não foi possível conectar ao servidor. Verifique sua conexão com a internet e tente novamente.',
                confirmButtonColor: '#ef4444'
            });
        }
    });
})();
</script>
</body>
</html>
