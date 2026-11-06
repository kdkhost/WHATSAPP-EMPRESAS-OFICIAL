@php
    use App\Support\Theme\ThemeManager;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;

    $admin = Auth::guard('admin')->user();
    $appName = $settingsManager->get('app.name', config('app.name'));
    $appLogo = $settingsManager->get('app.logo');
    $appFavicon = $settingsManager->get('app.favicon');
    $themeManager = app(ThemeManager::class);
    $theme = $themeManager->forAdmin($admin, request());
    $themeModes = [
        'light' => $themeManager->variablesForMode('light'),
        'dark' => $themeManager->variablesForMode('dark'),
    ];
    $themeDark = $theme['is_dark'];
    $adminInitial = strtoupper(substr($admin->name ?? 'A', 0, 1));
    $adminAvatar = $admin && array_key_exists('avatar_url', $admin->getAttributes())
        ? $admin->getAttribute('avatar_url')
        : null;
    $pageTitle = trim($__env->yieldContent('title', 'Painel'));
    $breadcrumbLabels = [
        'admin' => 'Painel',
        'painel' => 'Painel',
        'dashboard' => 'Dashboard',
        'settings' => 'Configurações',
        'config' => 'Configurações',
        'general' => 'Geral',
        'addons' => 'Addons',
        'gestao-financeira' => 'Gestão Financeira',
        'transactions' => 'Lançamentos',
        'categorias' => 'Categorias',
        'categories' => 'Categorias',
        'contas' => 'Contas',
        'accounts' => 'Contas',
        'reports' => 'Relatórios',
        'relatorios' => 'Relatórios',
        'templates' => 'Templates',
        'notifications' => 'Notificações',
        'security' => 'Segurança',
        'tenancy' => 'Planos e Tenants',
        'profile' => 'Perfil',
    ];
    $breadcrumbSegments = collect(request()->segments())
        ->reject(fn ($segment) => is_numeric($segment))
        ->values();
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="{{ $themeDark ? 'dark' : 'light' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

{{-- Dark mode automatico no PWA + iOS / Android (Chromium 93+, Safari 15+) --}}
<meta name="theme-color" content="{{ $theme['theme_color'] }}">
<meta name="color-scheme" content="light dark">

<title>@yield('title', 'Painel') - {{ $appName }}</title>
@php
    $defaultFaviconVersion = file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : time();
    $defaultFaviconHref = asset('favicon.ico') . '?v=' . $defaultFaviconVersion;
@endphp
@if(filled($appFavicon))
@php $faviconHref = str_starts_with($appFavicon, 'http') || str_starts_with($appFavicon, 'data:') ? $appFavicon : asset($appFavicon); @endphp
<link rel="icon" href="{{ $faviconHref }}">
<link rel="shortcut icon" href="{{ $faviconHref }}">
<link rel="apple-touch-icon" href="{{ $faviconHref }}">
@endif
@if(file_exists(public_path('favicon.ico')))
<link rel="icon" type="image/x-icon" href="{{ $defaultFaviconHref }}">
<link rel="shortcut icon" type="image/x-icon" href="{{ $defaultFaviconHref }}">
@else
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%B1%3C/text%3E%3C/svg%3E">
@endif

{{-- Bootstrap 5 (base do AdminLTE 4) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

{{-- AdminLTE 4 (premium) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">

{{-- Toastify CSS --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css">

<link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
{{-- DataTables Bootstrap 5 + Responsive + Buttons --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/datatables-custom.css') }}?v={{ time() }}">
<link rel="manifest" href="{{ url('/manifest.json') }}">

{{-- Premium Admin Theme - AdminLTE 4 proportions --}}
<style>
/* ============================================================
   CORES CUSTOMIZAVEIS
   ============================================================ */
:root {
    {!! $theme['css_variables'] !!}
}

/* ============================================================
   GLOBAL
   ============================================================ */
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 0.875rem; }
h1, h2, h3, h4, h5, h6 { font-weight: 600; }

.form-control:not(textarea):not(.form-control-sm):not(.form-control-lg):not(.form-control-color),
.form-select:not(.form-select-sm):not(.form-select-lg),
.input-group-text {
    min-height: 44px;
}

.input-group > .btn,
.input-group > .form-control,
.input-group > .form-select,
.input-group > .input-group-text {
    min-height: 44px;
}

/* ============================================================
   CARDS
   ============================================================ */
.card {
    border: 1px solid var(--bs-border-color);
    border-radius: var(--app-radius-premium);
    box-shadow: var(--app-shadow-premium);
    transition: var(--app-transition);
    overflow: hidden;
    background-color: var(--app-surface-bg);
    color: var(--app-body-color);
}
.card-header {
    background: var(--app-surface-bg);
    border-bottom: 1px solid var(--bs-border-color);
    padding: 0.75rem 1rem;
    font-weight: 600;
    font-size: 0.875rem;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.card-header > :first-child { flex: 1; }
@media (max-width: 767.98px) {
    .card-header {
        flex-wrap: wrap !important;
        align-items: flex-start !important;
    }
    .card-header > :first-child {
        flex: 1 1 100% !important;
        width: 100% !important;
        margin-bottom: 0.25rem;
    }
}
.card-body { padding: 1rem; }
.card-footer { background: var(--app-surface-bg); border-top: 1px solid var(--bs-border-color); padding: 0.6rem 1rem; }

/* ============================================================
   PADRAO GOURMET PREMIUM GLOBAL
   ============================================================ */
.premium-panel,
.premium-sidecard,
.admin-premium-panel {
    border: 1px solid var(--app-border-soft);
    border-radius: var(--app-radius-premium);
    background: var(--app-surface-bg);
    overflow: hidden;
    box-shadow: var(--app-shadow-premium);
}
.premium-panel__header,
.premium-sidecard__head,
.admin-premium-panel__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.2rem;
    border-bottom: 1px solid var(--app-border-soft);
}
.premium-panel__body,
.premium-sidecard__body,
.admin-premium-panel__body { padding: 1.2rem; }
.premium-panel__footer,
.admin-premium-panel__footer {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    padding: 1rem 1.2rem;
    border-top: 1px solid var(--app-border-soft);
    background: color-mix(in srgb,var(--app-surface-bg) 88%,var(--bs-primary) 12%);
}
.premium-panel__kicker,
.premium-sidecard__kicker,
.admin-premium-kicker {
    display: block;
    font-size: .74rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .3rem;
}
.premium-panel__title,
.admin-premium-title { margin: 0; font-size: 1.08rem; }
.premium-panel__subtitle,
.admin-premium-subtitle { margin: 0; color: var(--bs-secondary-color); line-height: 1.55; }
.admin-premium-hero {
    display: grid;
    grid-template-columns: minmax(0,1fr);
    gap: 1.25rem;
    position: relative;
    clear: both;
    overflow: visible;
    margin-bottom: 1rem !important;
    padding: 1.4rem;
    border: 1px solid var(--app-border-soft);
    border-radius: var(--app-radius-premium);
    background:
        linear-gradient(135deg,color-mix(in srgb,var(--bs-primary) 10%,transparent),transparent 40%),
        linear-gradient(180deg,var(--bs-body-bg),color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%));
}
.admin-premium-hero.mb-3,
.admin-premium-hero.mb-4,
.admin-premium-hero.mb-5 {
    margin-bottom: 1rem !important;
}
.admin-premium-hero + .row {
    margin-top: 0 !important;
}
.admin-premium-hero--with-aside {
    grid-template-columns: minmax(0,1.5fr) minmax(300px,1fr);
    align-items: stretch;
}
.admin-premium-hero--with-actions {
    grid-template-columns: minmax(0,1fr) auto;
    align-items: center;
}
.admin-premium-hero__actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: .65rem;
    flex-wrap: wrap;
    min-width: max-content;
}
.admin-premium-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    padding: .45rem .75rem;
    border-radius: 999px;
    background: color-mix(in srgb,var(--bs-primary) 8%,transparent);
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--bs-primary);
    margin-bottom: .9rem;
}
.admin-premium-hero__title { font-size: 1.9rem; line-height: 1.1; margin: 0 0 .75rem; }
.admin-premium-hero__text { max-width: 58rem; color: var(--bs-secondary-color); margin: 0; }
.admin-premium-hero--simple .admin-premium-hero__text,
.admin-premium-hero--with-actions .admin-premium-hero__text { max-width: 72rem; }
.dashboard-hero-notes,
.admin-hero-notes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
    min-width: 0;
}
.dashboard-note,
.admin-hero-note {
    min-width: 0;
    border: 1px solid var(--app-border-soft);
    border-radius: 16px;
    padding: 1rem;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
}
.dashboard-note__label,
.admin-hero-note__label {
    display: block;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .35rem;
}
.dashboard-note__value,
.admin-hero-note__value {
    display: block;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.15;
}
.dashboard-panel__title,
.admin-panel-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}
.dashboard-panel__subtitle,
.admin-panel-subtitle {
    display: block;
    margin-top: .15rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
}
.admin-premium-metric {
    padding: 1rem 1.05rem;
    border-radius: 16px;
    border: 1px solid var(--app-border-soft);
    background: var(--bs-body-bg);
}
@media (max-width: 1199.98px) {
    .admin-premium-hero,
    .admin-premium-hero--with-aside,
    .admin-premium-hero--with-actions { grid-template-columns: 1fr; }
    .admin-premium-hero__actions { justify-content: flex-start; min-width: 0; }
}
@media (max-width: 767.98px) {
    .premium-panel__footer,
    .admin-premium-panel__footer { flex-direction: column; }
    .admin-premium-hero__title { font-size: 1.5rem; }
    .dashboard-hero-notes,
    .admin-hero-notes { grid-template-columns: 1fr; }
}

/* ============================================================
   BOTOES
   ============================================================ */
.btn { border-radius: var(--app-radius); font-weight: 500; transition: var(--app-transition); }
.btn > .bi { font-size: .95em !important; line-height: 1; color: currentColor; }
.btn-primary { background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; }
.btn-success { background-color: var(--bs-success) !important; }
.btn-danger { background-color: var(--bs-danger) !important; }
.btn-warning { background-color: var(--bs-warning) !important; }
.btn-info { background-color: var(--bs-info) !important; }
.text-primary { color: var(--bs-primary) !important; }

/* ============================================================
   TABELAS
   ============================================================ */
.table thead th {
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
/* Tabelas responsivas - nunca quebrar container */
.card-body.p-0 { overflow-x: auto; }
.table { min-width: 100%; }
.table:not(.dataTable) { white-space: nowrap; }

/* ============================================================
   FORMULARIOS
   ============================================================ */
.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--bs-primary) 25%, transparent);
    border-color: var(--bs-primary);
}
.form-label { font-weight: 500; font-size: 0.8125rem; }

/* ============================================================
   NAVBAR (mesma altura do brand da sidebar = 57px AdminLTE padrao)
   ============================================================ */
/* Nenhum override - AdminLTE 4 padrao */

/* ============================================================
   SIDEBAR (largura AdminLTE padrao = 250px)
   ============================================================ */
.app-sidebar { background-color: var(--app-sidebar-bg) !important; border-right: none !important; }
.app-sidebar .nav-link {
    color: var(--app-sidebar-muted) !important;
    border-radius: var(--app-radius);
    margin: 1px 8px;
    padding: 0.5rem 0.85rem;
    font-size: 0.8125rem;
    transition: var(--app-transition);
}
.app-sidebar .nav-link:hover { color: var(--app-sidebar-color) !important; background: var(--app-sidebar-hover) !important; }
.app-sidebar .nav-link.active {
    color: var(--app-sidebar-active-color) !important;
    background: var(--app-sidebar-active) !important;
    font-weight: 500;
}
.app-sidebar .nav-link i { width: 1.4rem; font-size: 0.95rem; opacity: 0.85; }
.app-sidebar .nav-treeview .nav-link { padding: 0.35rem 0.85rem 0.35rem 2.4rem; font-size: 0.8rem; margin: 0 8px; }
.app-sidebar .nav-treeview .nav-link.active { background: var(--app-sidebar-tree-active) !important; color: var(--app-sidebar-color) !important; }
.sidebar-brand {
    color: var(--app-sidebar-color) !important;
    font-weight: 700;
    background: var(--app-sidebar-brand-bg);
    border-color: var(--app-sidebar-border) !important;
}
.sidebar-brand .brand-link,
.sidebar-brand .brand-link:hover,
.sidebar-brand .brand-link:focus,
.sidebar-brand .brand-text {
    color: var(--app-sidebar-color) !important;
}
.sidebar-wrapper { overflow-y: auto; overflow-x: hidden; height: calc(100vh - 57px); padding-bottom: 60px; }
.sidebar-wrapper::-webkit-scrollbar { width: 3px; }
.sidebar-wrapper::-webkit-scrollbar-thumb { background: var(--app-sidebar-scrollbar); border-radius: 3px; }

/* Treeview */
.sidebar-menu .nav-treeview { display: none; }
.sidebar-menu .nav-item.menu-open > .nav-treeview { display: block !important; }
.sidebar-menu .nav-item.menu-open > .nav-link .nav-arrow { transform: rotate(90deg); }
.sidebar-menu .nav-arrow { transition: transform 0.2s ease; margin-left: auto; }

/* ============================================================
   DARK MODE - textos visiveis + bordas sutis
   ============================================================ */
[data-bs-theme="dark"] { color-scheme: dark; }
[data-bs-theme="dark"] body,
[data-bs-theme="dark"] .app-main,
[data-bs-theme="dark"] .app-content {
    background: var(--admin-dark-bg) !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .app-header.navbar,
[data-bs-theme="dark"] .app-content-header,
[data-bs-theme="dark"] .app-footer {
    background: var(--admin-dark-surface) !important;
    border-color: var(--admin-dark-border) !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .app-header.navbar {
    box-shadow: none !important;
    border-bottom: 1px solid var(--admin-dark-border);
}
[data-bs-theme="dark"] .app-content-header {
    border-bottom: 1px solid var(--admin-dark-border);
}
[data-bs-theme="dark"] .app-content {
    min-height: calc(100vh - 115px);
}
[data-bs-theme="dark"] .card,
[data-bs-theme="dark"] .premium-panel,
[data-bs-theme="dark"] .premium-sidecard,
[data-bs-theme="dark"] .admin-premium-panel,
[data-bs-theme="dark"] .admin-premium-metric,
[data-bs-theme="dark"] .kanban-card,
[data-bs-theme="dark"] .upload-preview-card,
[data-bs-theme="dark"] .upload-done-card {
    background: var(--admin-dark-surface) !important;
    border-color: var(--admin-dark-border) !important;
    box-shadow: none !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer,
[data-bs-theme="dark"] .premium-panel__header,
[data-bs-theme="dark"] .premium-sidecard__head,
[data-bs-theme="dark"] .admin-premium-panel__header,
[data-bs-theme="dark"] .premium-panel__footer,
[data-bs-theme="dark"] .admin-premium-panel__footer {
    background: var(--admin-dark-surface) !important;
    border-color: var(--admin-dark-border) !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .admin-premium-hero,
[data-bs-theme="dark"] .tenant-hero,
[data-bs-theme="dark"] .premium-hero {
    background: var(--admin-dark-surface) !important;
    border-color: var(--admin-dark-border) !important;
    box-shadow: none !important;
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select,
[data-bs-theme="dark"] textarea.form-control,
[data-bs-theme="dark"] .form-control[readonly],
[data-bs-theme="dark"] .form-control.bg-light,
[data-bs-theme="dark"] .input-group-text {
    background-color: var(--admin-dark-field) !important;
    border-color: var(--admin-dark-border) !important;
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .form-control::placeholder {
    color: #64748b;
}
[data-bs-theme="dark"] .form-control:focus,
[data-bs-theme="dark"] .form-select:focus,
[data-bs-theme="dark"] textarea.form-control:focus {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0 0 .18rem rgba(var(--bs-primary-rgb), .22) !important;
}
[data-bs-theme="dark"] .btn-primary {
    background: var(--bs-primary) !important;
    border-color: var(--bs-primary) !important;
    color: #fff !important;
    box-shadow: none !important;
}
[data-bs-theme="dark"] .btn-secondary {
    background: #1e293b !important;
    border-color: #1e293b !important;
    color: #e5e7eb !important;
}
[data-bs-theme="dark"] .table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--bs-body-color);
    --bs-table-border-color: var(--admin-dark-border);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .table-light {
    --bs-table-bg: var(--admin-dark-surface);
    color: var(--admin-dark-muted);
    border-color: var(--admin-dark-border);
}
[data-bs-theme="dark"] .table-light th { color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .shadow-sm { box-shadow: 0 1px 3px rgba(0,0,0,.3) !important; }
[data-bs-theme="dark"] .text-muted { color: var(--admin-dark-muted) !important; }
[data-bs-theme="dark"] .text-dark { color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .small, [data-bs-theme="dark"] small { color: inherit; }
[data-bs-theme="dark"] .card-body .text-uppercase { color: var(--admin-dark-muted); }
[data-bs-theme="dark"] .table { color: var(--bs-body-color); }
[data-bs-theme="dark"] .table td, [data-bs-theme="dark"] .table th { color: var(--bs-body-color); }
[data-bs-theme="dark"] .btn-outline-primary { color: var(--bs-primary); border-color: var(--bs-primary); }
[data-bs-theme="dark"] .btn-outline-primary:hover, [data-bs-theme="dark"] .btn-outline-primary:focus { background-color: var(--bs-primary); color: #fff !important; border-color: var(--bs-primary); }
[data-bs-theme="dark"] .btn-outline-secondary { color: var(--bs-body-color); border-color: var(--admin-dark-border); }
[data-bs-theme="dark"] .btn-outline-secondary:hover, [data-bs-theme="dark"] .btn-outline-secondary:focus { background-color: #1e293b; color: #fff !important; }
[data-bs-theme="dark"] .btn-outline-success { color: var(--bs-success); border-color: var(--bs-success); }
[data-bs-theme="dark"] .btn-outline-success:hover { background-color: var(--bs-success); color: #fff !important; }
[data-bs-theme="dark"] .btn-outline-danger { color: var(--bs-danger); border-color: var(--bs-danger); }
[data-bs-theme="dark"] .btn-outline-danger:hover { background-color: var(--bs-danger); color: #fff !important; }
[data-bs-theme="dark"] .btn-outline-warning { color: var(--bs-warning); border-color: var(--bs-warning); }
[data-bs-theme="dark"] .btn-outline-warning:hover { background-color: var(--bs-warning); color: #000 !important; }
[data-bs-theme="dark"] .btn-outline-info { color: var(--bs-info); border-color: var(--bs-info); }
[data-bs-theme="dark"] .btn-outline-info:hover { background-color: var(--bs-info); color: #000 !important; }
[data-bs-theme="dark"] strong, [data-bs-theme="dark"] b { color: var(--bs-body-color); }
[data-bs-theme="dark"] .fs-3, [data-bs-theme="dark"] .fs-4 { color: var(--bs-body-color); }
[data-bs-theme="dark"] .fw-bold { color: inherit; }
[data-bs-theme="dark"] .bg-light { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .bg-white,
[data-bs-theme="dark"] .card.bg-white,
[data-bs-theme="dark"] .card-header.bg-white,
[data-bs-theme="dark"] .card-footer.bg-white {
    background-color: var(--admin-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--admin-dark-border) !important;
}
[data-bs-theme="dark"] .bg-body,
[data-bs-theme="dark"] .bg-body-secondary,
[data-bs-theme="dark"] .bg-body-tertiary {
    background-color: var(--admin-dark-bg) !important;
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .border-light,
[data-bs-theme="dark"] .border-white {
    border-color: var(--admin-dark-border) !important;
}
[data-bs-theme="dark"] .nav-tabs .nav-link {
    color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .nav-tabs .nav-link.active {
    background-color: var(--admin-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--admin-dark-border) var(--admin-dark-border) transparent !important;
}
[data-bs-theme="dark"] .swal2-popup {
    background: var(--admin-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border: 1px solid var(--admin-dark-border) !important;
}
[data-bs-theme="dark"] .swal2-title,
[data-bs-theme="dark"] .swal2-html-container {
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .swal2-input,
[data-bs-theme="dark"] .swal2-textarea,
[data-bs-theme="dark"] .swal2-select {
    background-color: var(--admin-dark-field) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--admin-dark-border) !important;
}
[data-bs-theme="dark"] .toastify {
    border: 1px solid var(--admin-dark-border);
}
[data-bs-theme="dark"] .list-group-item { color: var(--bs-body-color); }
[data-bs-theme="dark"] .alert { color: inherit; }
[data-bs-theme="dark"] .input-group-text { color: var(--bs-body-color); }
[data-bs-theme="dark"] code { color: #e685b5; }
[data-bs-theme="dark"] pre { color: var(--bs-body-color); }
[data-bs-theme="dark"] .form-check-label { color: var(--bs-body-color); }
[data-bs-theme="dark"] label { color: var(--bs-body-color); }
[data-bs-theme="dark"] p { color: var(--bs-body-color); }
[data-bs-theme="dark"] li { color: var(--bs-body-color); }
[data-bs-theme="dark"] .border { border-color: var(--admin-dark-border) !important; }
[data-bs-theme="dark"] .table-hover > tbody > tr:hover > * { background-color: rgba(var(--bs-primary-rgb), .08); color: var(--bs-body-color); }
[data-bs-theme="dark"] .table > :not(caption) > * > * { border-bottom-color: var(--admin-dark-border); }
[data-bs-theme="dark"] .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: rgba(148,163,184,.03); color: var(--bs-body-color); }
[data-bs-theme="dark"] .badge.bg-light { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; border: 1px solid var(--admin-dark-border); }
[data-bs-theme="dark"] .dropdown-menu {
    background-color: var(--admin-dark-surface);
    border-color: var(--admin-dark-border);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .dropdown-item { color: var(--bs-body-color); }
[data-bs-theme="dark"] .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-item:focus { background-color: rgba(var(--bs-primary-rgb), .12); color: #fff; }
[data-bs-theme="dark"] .dropdown-divider { border-top-color: var(--admin-dark-border); }
[data-bs-theme="dark"] .modal-content {
    background-color: var(--admin-dark-surface);
    color: var(--bs-body-color);
    border-color: var(--admin-dark-border);
}
[data-bs-theme="dark"] .modal-header,
[data-bs-theme="dark"] .modal-footer { border-color: var(--admin-dark-border); }
[data-bs-theme="dark"] .note-editor.note-frame,
[data-bs-theme="dark"] .note-editor .note-toolbar,
[data-bs-theme="dark"] .note-editor .note-statusbar {
    background: var(--admin-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--admin-dark-border) !important;
}
[data-bs-theme="dark"] .note-editor .note-editing-area .note-editable {
    background: var(--admin-dark-field) !important;
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .note-editor .dropdown-menu { background-color: var(--admin-dark-surface) !important; color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .note-editor .note-btn { color: var(--bs-body-color) !important; border-color: var(--admin-dark-border) !important; }
[data-bs-theme="dark"] .note-editor .note-btn:hover { background: rgba(var(--bs-primary-rgb), .12) !important; }
[data-bs-theme="dark"] .fc .fc-scrollgrid,
[data-bs-theme="dark"] .fc .fc-theme-standard td,
[data-bs-theme="dark"] .fc .fc-theme-standard th { border-color: var(--admin-dark-border); }

/* ============================================================
   LIGHT MODE
   ============================================================ */
[data-bs-theme="light"] body { background-color: var(--app-body-bg) !important; }
[data-bs-theme="light"] .app-main,
[data-bs-theme="light"] .app-content { background-color: var(--app-body-bg) !important; color: var(--app-body-color) !important; }
[data-bs-theme="light"] .app-header.navbar,
[data-bs-theme="light"] .app-content-header,
[data-bs-theme="light"] .app-footer {
    background-color: var(--app-navbar-bg) !important;
    color: var(--app-navbar-color) !important;
    border-color: var(--app-navbar-border) !important;
}
[data-bs-theme="light"] .app-header .nav-link,
[data-bs-theme="light"] .app-header .btn,
[data-bs-theme="light"] .app-header .btn-link,
[data-bs-theme="light"] .app-header .dropdown-toggle,
[data-bs-theme="light"] .app-footer,
[data-bs-theme="light"] .app-content-header h1,
[data-bs-theme="light"] .app-content-header .breadcrumb-item,
[data-bs-theme="light"] .app-content-header .breadcrumb-item a {
    color: var(--app-navbar-color) !important;
}
[data-bs-theme="light"] .text-muted { color: var(--app-body-muted) !important; }
[data-bs-theme="light"] .border { border-color: var(--app-surface-border) !important; }
[data-bs-theme="light"] .table-light { --bs-table-bg: #f8f9fa; color: #495057; }
[data-bs-theme="light"] .table > :not(caption) > * > * { border-bottom-color: rgba(0,0,0,.08); }
[data-bs-theme="light"] .modal-content { border-color: rgba(0,0,0,.12); }
[data-bs-theme="light"] .dropdown-menu { border-color: rgba(0,0,0,.12); }
[data-bs-theme="light"] .badge.bg-light { border: 1px solid rgba(0,0,0,.08); }

/* ============================================================
   FOOTER
   ============================================================ */
.app-footer { font-size: 0.8rem; border-top: 1px solid var(--bs-border-color); }

/* ============================================================
   STAT ICON - circulo perfeito para cards de estatistica
   Padrao premium gourmet: fundo subtle + texto na cor (nao mais bg solido)
   ============================================================ */
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
}

/* Override: stat-icon antigo (bg-X text-white) vira subtle/cor automatico */
.stat-icon.bg-primary.text-white   { background-color: var(--bs-primary-bg-subtle) !important;   color: var(--bs-primary)   !important; }
.stat-icon.bg-success.text-white   { background-color: var(--bs-success-bg-subtle) !important;   color: var(--bs-success)   !important; }
.stat-icon.bg-danger.text-white    { background-color: var(--bs-danger-bg-subtle) !important;    color: var(--bs-danger)    !important; }
.stat-icon.bg-warning.text-white   { background-color: var(--bs-warning-bg-subtle) !important;   color: var(--bs-warning)   !important; }
.stat-icon.bg-info.text-white      { background-color: var(--bs-info-bg-subtle) !important;      color: var(--bs-info)      !important; }
.stat-icon.bg-secondary.text-white { background-color: var(--bs-secondary-bg-subtle) !important; color: var(--bs-secondary) !important; }
.stat-icon.bg-dark.text-white      { background-color: var(--bs-tertiary-bg) !important;         color: var(--bs-body-color) !important; }

/* Card de KPI - layout consistente: gap entre icon e texto, padding uniforme */
.card .card-body.p-3.d-flex.justify-content-between {
    padding: 1rem !important;
    gap: 0.75rem;
}

/* PADRONIZACAO GLOBAL: qualquer card-body que contenha stat-icon fica
   com layout unificado: ICONE A ESQUERDA + TEXTO A DIREITA + gap consistente.
   Isso normaliza paginas antigas (text-then-icon com justify-between) e
   novas (icon-then-text com gap) na MESMA aparencia. */
.card-body:has(.stat-icon) {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    justify-content: flex-start !important;
    padding: 1rem !important;
}
.card-body:has(.stat-icon) > .stat-icon {
    order: -1;
    margin: 0 !important;
}
.card-body:has(.stat-icon) > div:not(.stat-icon),
.card-body:has(.stat-icon) > .flex-grow-1 {
    flex: 1 1 auto;
    min-width: 0;
    text-align: left !important;
}
/* Padroniza tipografia interna do KPI: label uppercase muted + valor fs-4 */
.card-body:has(.stat-icon) .text-muted.small,
.card-body:has(.stat-icon) .small.text-muted {
    text-transform: uppercase;
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.02em;
}

/* ============================================================
   SWEETALERT2 - segue o tema (light/dark) via Bootstrap variables
   ============================================================ */
.swal2-popup {
    background-color: var(--bs-body-bg) !important;
    color: var(--bs-body-color) !important;
    border: 1px solid var(--bs-border-color);
    border-radius: var(--app-radius);
}
.swal2-title { color: var(--bs-body-color) !important; }
.swal2-html-container { color: var(--bs-body-color) !important; }
.swal2-html-container table { color: var(--bs-body-color); }
.swal2-html-container .table { color: var(--bs-body-color); --bs-table-bg: transparent; }
.swal2-html-container .table-light { --bs-table-bg: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.swal2-html-container .table-light th { color: var(--bs-body-color) !important; }
.swal2-html-container code { color: var(--bs-primary); }
.swal2-html-container pre { color: var(--bs-body-color); background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); padding: .65rem; border-radius: .375rem; }
.swal2-input, .swal2-textarea, .swal2-select {
    background-color: var(--bs-body-bg) !important;
    color: var(--bs-body-color) !important;
    border: 1px solid var(--bs-border-color) !important;
}
.swal2-input:focus, .swal2-textarea:focus, .swal2-select:focus {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--bs-primary) 25%, transparent) !important;
}
.swal2-validation-message { background-color: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.swal2-styled.swal2-confirm { background-color: var(--bs-primary) !important; }
.swal2-actions { gap: .5rem; }
[data-bs-theme="dark"] .swal2-popup { box-shadow: 0 10px 30px rgba(0,0,0,.5); }
[data-bs-theme="dark"] .swal2-html-container .badge.bg-light { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; border-color: var(--bs-border-color); }

/* ============================================================
   PAGINATION - padronizada Bootstrap 5
   ============================================================ */
.card-footer .pagination {
    margin-bottom: 0;
    --bs-pagination-padding-x: .65rem;
    --bs-pagination-padding-y: .35rem;
    --bs-pagination-font-size: .8125rem;
    --bs-pagination-border-radius: .375rem;
    flex-wrap: wrap;
    gap: 2px;
}
.card-footer nav[role="navigation"] {
    display: flex;
    justify-content: center;
}
.card-footer nav[role="navigation"] > .flex-1 { display: none; } /* esconde texto Tailwind padrao */
.pagination .page-link { color: var(--bs-primary); }
.pagination .page-item.active .page-link { background-color: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
.pagination .page-item.disabled .page-link { color: var(--bs-secondary-color); }
[data-bs-theme="dark"] .pagination .page-link { background-color: var(--bs-tertiary-bg); border-color: rgba(255,255,255,.1); color: var(--bs-primary); }
[data-bs-theme="dark"] .pagination .page-item.active .page-link { background-color: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
[data-bs-theme="dark"] .pagination .page-item.disabled .page-link { background-color: rgba(255,255,255,.03); color: rgba(255,255,255,.35); border-color: rgba(255,255,255,.08); }
[data-bs-theme="dark"] .pagination .page-link:hover { background-color: rgba(255,255,255,.08); border-color: rgba(255,255,255,.15); }

/* ============================================================
   ADMINLTE 4 PAGE HEADER + BREADCRUMB OFICIAL
   ============================================================ */
.app-content-header {
    padding: 1rem 0 .35rem;
}
.app-content-header h1 {
    font-size: 1.35rem;
    line-height: 1.2;
    margin: 0;
}
.app-content-header .breadcrumb {
    margin-bottom: 0;
    font-size: .8125rem;
}
.app-content-header .breadcrumb-item a {
    color: var(--bs-secondary-color);
    text-decoration: none;
}
.app-content-header .breadcrumb-item a:hover {
    color: var(--bs-primary);
}
.app-content-header .breadcrumb-item.active {
    color: var(--bs-body-color);
    font-weight: 600;
}
.app-content-header__actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: .5rem;
    flex-wrap: wrap;
}
[data-bs-theme="dark"] .app-content-header .breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255,255,255,.4);
}
</style>
@stack('head')
@stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary {{ auth('admin')->check() ? 'has-pwa-footer' : '' }}">

<div class="app-wrapper">

    {{-- ===================== NAVBAR FIXA ===================== --}}
    <nav class="app-header navbar navbar-expand bg-body sticky-top shadow-sm">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list fs-4"></i></a></li>
                <li class="nav-item d-none d-md-block"><a class="nav-link" href="{{ route('admin.dashboard') }}">Inicio</a></li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">

                {{-- Cache rapido --}}
                <li class="nav-item">
                    <button id="quick-cache-clear" class="btn btn-link nav-link" title="Limpar cache rapido">
                        <i class="bi bi-lightning-charge"></i>
                    </button>
                </li>

                {{-- Toggle dark/light --}}
                <li class="nav-item">
                    <button id="theme-toggle" class="btn btn-link nav-link" title="Alternar tema">
                        <i class="bi {{ $themeDark ? 'bi-sun-fill' : 'bi-moon-stars-fill' }}"></i>
                    </button>
                </li>

                {{-- Reiniciar tour guiado --}}
                <li class="nav-item">
                    <button id="restart-tour" class="btn btn-link nav-link" title="Reiniciar tour guiado">
                        <i class="bi bi-question-circle"></i>
                    </button>
                </li>

                {{-- Sininho de notificacoes agrupadas --}}
                @include('admin.partials.bell')

                {{-- Dropdown do usuario (padrao AdminLTE 4) --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 overflow-hidden"
                              style="width:32px;height:32px;font-size:.85rem;flex-shrink:0;">
                            @if($adminAvatar)
                                <img src="{{ $adminAvatar }}" alt="{{ $admin->name ?? 'Admin' }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                {{ $adminInitial }}
                            @endif
                        </span>
                        <span class="d-none d-md-inline">{{ $admin->name ?? 'Admin' }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 260px;">
                        {{-- Header com avatar e dados --}}
                        <li class="px-3 py-2">
                            <div class="d-flex align-items-center gap-3">
                                <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center overflow-hidden"
                                      style="width:48px;height:48px;font-size:1.2rem;flex-shrink:0;">
                                    @if($adminAvatar)
                                        <img src="{{ $adminAvatar }}" alt="{{ $admin->name ?? 'Admin' }}" style="width:100%;height:100%;object-fit:cover;">
                                    @else
                                        {{ $adminInitial }}
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $admin->name ?? 'Admin' }}</div>
                                    <small class="text-muted text-truncate d-block">{{ $admin->email ?? '' }}</small>
                                    @if($admin->roles ?? false)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1" style="font-size:.65rem;">
                                            {{ $admin->roles->first()?->name ?? 'Admin' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>

                        {{-- Links --}}
                        <li>
                            <a class="dropdown-item py-2" href="{{ url('/admin/profile') }}">
                                <i class="bi bi-person me-2 text-primary"></i> Meu perfil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="{{ url('/admin/settings/general') }}">
                                <i class="bi bi-gear me-2 text-secondary"></i> Configuracoes
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="{{ url('/admin/notifications') }}">
                                <i class="bi bi-bell me-2 text-info"></i> Notificacoes
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">@csrf
                                <button type="submit" class="dropdown-item py-2 text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i> Sair
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    {{-- ===================== SIDEBAR ===================== --}}
    @include('admin.partials.sidebar')

    {{-- ===================== CONTENT ===================== --}}
    <main class="app-main">
        @hasSection('page_header')
            @yield('page_header')
        @else
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center g-2">
                        <div class="col-sm-6">
                            <h1>{{ $pageTitle }}</h1>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center justify-content-sm-end gap-2 flex-wrap">
                                @hasSection('page_actions')
                                    <div class="app-content-header__actions">
                                        @yield('page_actions')
                                    </div>
                                @endif

                                @hasSection('breadcrumbs')
                                    @yield('breadcrumbs')
                                @else
                                    <ol class="breadcrumb float-sm-end">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('admin.dashboard') }}">Inicio</a>
                                        </li>
                                        @foreach($breadcrumbSegments as $index => $segment)
                                            @continue($segment === 'admin')
                                            @php
                                                $isLast = $loop->last;
                                                $label = $breadcrumbLabels[$segment] ?? Str::headline(str_replace(['-', '_'], ' ', $segment));
                                                $url = url(implode('/', $breadcrumbSegments->take($index + 1)->all()));
                                            @endphp
                                            <li class="breadcrumb-item {{ $isLast ? 'active' : '' }}" @if($isLast) aria-current="page" @endif>
                                                @if($isLast)
                                                    {{ $label }}
                                                @else
                                                    <a href="{{ $url }}">{{ $label }}</a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="app-content py-3">
            @yield('content')
        </div>
    </main>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">v1.0.0</div>
        <strong>&copy; {{ date('Y') }} {{ $appName }}.</strong> Todos os direitos reservados.
    </footer>
</div>

{{-- SweetAlert2/Toastify substituem completamente os toasts Bootstrap --}}

{{-- Upload drag-drop modal global --}}


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
@include('admin.partials.upload-modal')
<script>
(function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal && bootstrap.Modal.prototype && typeof bootstrap.Modal.prototype.show === 'function') return;

    var _modalInstances = new WeakMap();

    function StubModal(el) {
        this._element = el;
        _modalInstances.set(el, this);
    }
    StubModal.getInstance = function(el) { return _modalInstances.get(el) || null; };
    StubModal.prototype.show = function() {
        var el = this._element;
        if (!el) return;
        el.classList.add('show');
        el.style.display = 'block';
        el.setAttribute('aria-hidden', 'false');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('role', 'dialog');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop')) {
            var bd = document.createElement('div');
            bd.className = 'modal-backdrop fade show';
            document.body.appendChild(bd);
        }
    };
    StubModal.prototype.hide = function() {
        var el = this._element;
        if (!el) return;
        el.classList.remove('show');
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        el.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function(b) { b.remove(); });
    };
    StubModal.prototype.toggle = function() {
        if (this._element && this._element.classList.contains('show')) this.hide(); else this.show();
    };
    StubModal.prototype.dispose = function() {
        if (this._element) _modalInstances.delete(this._element);
    };

    function StubTooltip(el) { this._element = el; }
    StubTooltip.prototype.show = function() {};
    StubTooltip.prototype.hide = function() {};
    StubTooltip.prototype.dispose = function() {};

    function StubDropdown(el) { this._element = el; }
    StubDropdown.prototype.show = function() {};
    StubDropdown.prototype.hide = function() {};
    StubDropdown.prototype.dispose = function() {};

    function StubToast(el) { this._element = el; }
    StubToast.prototype.show = function() {};
    StubToast.prototype.hide = function() {};
    StubToast.prototype.dispose = function() {};

    window.bootstrap = {
        Modal: StubModal,
        Tooltip: StubTooltip,
        Dropdown: StubDropdown,
        Toast: StubToast
    };
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js"></script>

{{-- SweetAlert2 (confirmacoes globais) + Toastify (notificacoes globais) --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>

{{-- Intro.js para tour guiado no primeiro login --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css">
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/intro.min.js"></script>

{{-- JS global do admin: mascaras, viacep, toasts, upload drag-drop, theme toggle --}}
<script>
window.__adminThemeVariables = @json($themeModes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
</script>
{{-- jQuery + DataTables Bootstrap 5 & Plugins de Exportação --}}

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="{{ asset('assets/js/datatables-init.js') }}?v={{ time() }}"></script>
<script src="{{ asset('assets/js/admin.js') }}?v={{ time() }}"></script>

{{-- Forcar menu aberto no item ativo + treeview toggle manual --}}
<script>
window.initAdminSidebar = function() {
    var sidebarWrapper = document.querySelector('.app-sidebar .sidebar-wrapper') 
        || document.querySelector('.sidebar-wrapper') 
        || document.querySelector('.app-sidebar');
    var storageKey = 'admin_sidebar_scroll_top';

    // 1. Abrir submenus com item ativo
    document.querySelectorAll('.sidebar-menu .nav-treeview .nav-link.active').forEach(function(link) {
        var subtree = link.closest('.nav-treeview');
        if (!subtree) return;
        var parentLi = subtree.closest('.nav-item');
        if (parentLi) {
            parentLi.classList.add('menu-open');
        }
    });

    // 2. Restaurar e manter posicao do scroll da sidebar
    if (sidebarWrapper) {
        function restoreSidebarScroll() {
            var saved = sessionStorage.getItem(storageKey);
            if (saved !== null && !isNaN(parseInt(saved, 10))) {
                sidebarWrapper.scrollTop = parseInt(saved, 10);
            }

            // Garantir que o item ativo esteja visivel sem precisar rolar
            var activeLink = sidebarWrapper.querySelector('.sidebar-menu .nav-link.active');
            if (activeLink) {
                var wrapperRect = sidebarWrapper.getBoundingClientRect();
                var linkRect = activeLink.getBoundingClientRect();
                var isOutOfView = (linkRect.top < wrapperRect.top) || (linkRect.bottom > wrapperRect.bottom);
                if (isOutOfView) {
                    activeLink.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
                    sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
                }
            }
        }

        // Restaura imediatamente e tambem apos acomodacao do layout
        restoreSidebarScroll();
        requestAnimationFrame(restoreSidebarScroll);
        setTimeout(restoreSidebarScroll, 60);
        setTimeout(restoreSidebarScroll, 200);

        // Salvar posicao durante o scroll (passivo)
        sidebarWrapper.addEventListener('scroll', function() {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        }, { passive: true });

        // Salvar ao descarregar a pagina
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        });
        window.addEventListener('pagehide', function() {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        });
    }

    var sidebarMenu = document.querySelector('.sidebar-menu');
    if (!sidebarMenu) {
        return;
    }

    // 3. Salvar scroll ao clicar em links e gerenciar submenus
    sidebarMenu.addEventListener('click', function(e) {
        var link = e.target.closest('.nav-link');
        if (!link || !sidebarMenu.contains(link)) {
            return;
        }

        var item = link.closest('.nav-item');
        if (!item) {
            return;
        }

        var submenu = item.querySelector('.nav-treeview');
        if (submenu) {
            e.preventDefault();
            e.stopPropagation();
            item.classList.toggle('menu-open');
            if (sidebarWrapper) {
                sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
            }
            return;
        }

        // Se e um link de navegacao normal, salva a posicao de rolagem imediatamente
        if (sidebarWrapper) {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        }
    });
};

document.addEventListener('DOMContentLoaded', function() {
    window.initAdminSidebar();

    var qcc = document.getElementById('quick-cache-clear');
    if (qcc) {
        qcc.addEventListener('click', async function(e) {
            e.preventDefault();
            qcc.classList.add('disabled');
            qcc.querySelector('i').className = 'bi bi-arrow-repeat';
            qcc.style.transform = 'rotate(360deg)';
            qcc.style.transition = 'transform 1s';
            try {
                var r = await fetch('{{ url("/admin/cache/clear-all") }}', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
                var data = await r.json();
                if (data.ok) window.toast.success('Cache limpo em ' + data.duration_ms + 'ms!');
                else window.toast.error(data.message || 'Erro');
            } catch(err) {
                window.toast.error('Erro de rede');
            } finally {
                setTimeout(function() {
                    qcc.classList.remove('disabled');
                    qcc.querySelector('i').className = 'bi bi-lightning-charge';
                    qcc.style.transform = '';
                }, 1000);
            }
        });
    }
});
</script>

@stack('scripts')

<script>
window.startAdminTour = function() {
    if (typeof introJs !== 'function') return;

    const steps = [];
    const pageTitle = document.querySelector('.app-content-header h1, .content-header h1, .app-main h1, .app-main .h1, .card-title');

    if (pageTitle) steps.push({
        element: pageTitle,
        title: 'Contexto da pagina',
        intro: 'Aqui voce identifica rapidamente onde esta e qual fluxo administrativo esta usando.',
        position: 'bottom'
    });

    const sidebar = document.querySelector('.app-sidebar');
    if (sidebar) steps.push({
        element: sidebar,
        title: 'Menu principal',
        intro: 'Use a barra lateral para navegar pelos modulos do sistema, addons e areas administrativas sem perder o contexto.',
        position: 'right'
    });

    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) steps.push({
        element: themeBtn,
        title: 'Tema claro/escuro',
        intro: 'Alterne entre tema claro e escuro com um clique. Sua preferencia e salva automaticamente.',
        position: 'bottom'
    });

    const restartBtn = document.getElementById('restart-tour');
    if (restartBtn) steps.push({
        element: restartBtn,
        title: 'Abrir tour quando quiser',
        intro: 'Depois do primeiro acesso o tour nao abre sozinho, mas este botao permite iniciar novamente a qualquer momento.',
        position: 'bottom'
    });

    const cacheBtn = document.getElementById('quick-cache-clear');
    if (cacheBtn) steps.push({
        element: cacheBtn,
        title: 'Limpar cache rapido',
        intro: 'Atalho para limpar todos os caches do sistema instantaneamente.',
        position: 'bottom'
    });

    const bell = document.querySelector('.nav-item .bi-bell, .nav-item .bi-bell-fill');
    if (bell) steps.push({
        element: bell.closest('.nav-item') || bell,
        title: 'Notificacoes',
        intro: 'Acompanhe alertas do sistema agrupados: cobrancas, WhatsApp, seguranca e sistema.',
        position: 'bottom'
    });

    const userMenu = document.querySelector('.navbar-nav .dropdown:last-child');
    if (userMenu) steps.push({
        element: userMenu,
        title: 'Seu perfil',
        intro: 'Acesse seu perfil, configure 2FA, altere senha ou faca logout por aqui.',
        position: 'bottom-right-aligned'
    });

    const primaryAction = document.querySelector('.app-main .btn-primary, .app-main .btn-success, .content-wrapper .btn-primary, .content-wrapper .btn-success');
    if (primaryAction) steps.push({
        element: primaryAction,
        title: 'Acao principal',
        intro: 'Os botoes de destaque concentram as acoes mais importantes da tela atual.',
        position: 'bottom'
    });

    if (steps.length === 0) return;

    const tour = introJs().setOptions({
        steps,
        nextLabel: 'Proximo',
        prevLabel: 'Anterior',
        doneLabel: 'Concluir',
        skipLabel: '&times;',
        showProgress: true,
        showBullets: false,
        showStepNumbers: false,
        scrollToElement: true,
        scrollPadding: 24,
        exitOnOverlayClick: false,
        exitOnEsc: true,
        tooltipClass: 'admin-tour-tooltip',
        highlightClass: 'admin-tour-highlight',
        disableInteraction: false,
    });

    tour.onbeforechange(function() {
        document.querySelectorAll('.admin-tour-current').forEach((element) => element.classList.remove('admin-tour-current'));
        window.requestAnimationFrame(() => {
            const tooltip = document.querySelector('.introjs-tooltip');
            if (!tooltip || tooltip.querySelector('.admin-tour-counter')) return;

            const counter = document.createElement('div');
            counter.className = 'admin-tour-counter';
            counter.textContent = `${tour._currentStep + 1} de ${steps.length}`;
            tooltip.appendChild(counter);
        });
    }).onafterchange(function() {
        const currentStep = steps[tour._currentStep];
        if (currentStep?.element instanceof Element) {
            currentStep.element.classList.add('admin-tour-current');
        }

        const counter = document.querySelector('.introjs-tooltip .admin-tour-counter');
        if (counter) counter.textContent = `${tour._currentStep + 1} de ${steps.length}`;
    }).oncomplete(() => {
        document.querySelectorAll('.admin-tour-current').forEach((element) => element.classList.remove('admin-tour-current'));
        markComplete();
    }).onexit(() => {
        document.querySelectorAll('.admin-tour-current').forEach((element) => element.classList.remove('admin-tour-current'));
        markComplete();
    }).start();
};

async function markComplete() {
    try {
        await fetch('{{ route("admin.tour.complete") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json'
            },
        });
    } catch (e) {}
}

document.addEventListener('DOMContentLoaded', () => {
    const restartBtn = document.getElementById('restart-tour');
    if (restartBtn) {
        restartBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.startAdminTour();
        });
    }

    @if(empty($admin->tour_completed_at))
    setTimeout(() => window.startAdminTour(), 1000);
    @endif
});
</script>
<style>
.introjs-overlay {
    z-index: 10000000 !important;
    backdrop-filter: blur(6px);
    background: rgba(2, 6, 23, .7) !important;
    opacity: 1 !important;
}
.introjs-tooltip.admin-tour-tooltip {
    width: min(430px, calc(100vw - 32px));
    max-width: 430px;
    padding: 0;
    color: var(--bs-body-color);
    background:
        radial-gradient(circle at top right, rgba(var(--bs-primary-rgb), .15), transparent 34%),
        linear-gradient(145deg, var(--bs-body-bg), var(--bs-tertiary-bg));
    border: 1px solid rgba(var(--bs-primary-rgb), .22);
    border-radius: 18px;
    box-shadow: 0 24px 70px rgba(0, 0, 0, .42), 0 0 0 1px rgba(255,255,255,.03) inset;
    overflow: hidden;
}
.introjs-tooltip.admin-tour-tooltip .introjs-tooltip-header { min-height: 0; padding: 1.35rem 3.25rem .25rem 1.35rem; }
.introjs-tooltip.admin-tour-tooltip .introjs-tooltip-title {
    color: var(--bs-body-color);
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: 0;
    line-height: 1.25;
}
.introjs-tooltip.admin-tour-tooltip .introjs-tooltiptext {
    padding: .65rem 1.35rem 1.25rem;
    color: var(--bs-secondary-color);
    font-size: .95rem;
    line-height: 1.6;
}
.introjs-tooltip.admin-tour-tooltip .introjs-tooltipbuttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: .75rem;
    padding: 1rem 1.35rem 1.2rem;
    border-top: 1px solid var(--bs-border-color);
}
.introjs-tooltip.admin-tour-tooltip .introjs-button {
    min-width: 100px;
    padding: .6rem 1rem;
    color: var(--bs-body-color);
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    box-shadow: none;
    text-shadow: none;
    font-weight: 700;
    line-height: 1;
    transition: all 0.2s;
    text-align: center;
}
.introjs-tooltip.admin-tour-tooltip .introjs-button:hover { 
    background: var(--bs-secondary-bg); 
    color: var(--bs-body-color);
}
.introjs-tooltip.admin-tour-tooltip .introjs-nextbutton,
.introjs-tooltip.admin-tour-tooltip .introjs-donebutton {
    color: #fff;
    background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
    border-color: transparent;
    box-shadow: 0 4px 15px rgba(var(--bs-primary-rgb), 0.3);
}
.introjs-tooltip.admin-tour-tooltip .introjs-nextbutton:hover,
.introjs-tooltip.admin-tour-tooltip .introjs-donebutton:hover {
    box-shadow: 0 6px 20px rgba(var(--bs-primary-rgb), 0.4);
    transform: translateY(-1px);
    color: #fff;
}
.introjs-tooltip.admin-tour-tooltip .introjs-skipbutton {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 34px;
    height: 34px;
    min-width: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 50%;
    font-size: 1.35rem;
    line-height: 1;
    text-decoration: none;
    transition: all 0.2s;
}
.introjs-tooltip.admin-tour-tooltip .introjs-skipbutton:hover { 
    color: var(--bs-body-color); 
    background: var(--bs-secondary-bg); 
}
.introjs-tooltip.admin-tour-tooltip .introjs-progress {
    height: 6px;
    margin: 0 1.35rem;
    background: var(--bs-tertiary-bg);
    border-radius: 999px;
    overflow: hidden;
}
.introjs-tooltip.admin-tour-tooltip .introjs-progressbar { 
    background: linear-gradient(90deg, var(--bs-primary), var(--bs-info)); 
}
.admin-tour-counter {
    position: absolute;
    left: 1.35rem;
    bottom: 1.25rem;
    color: var(--bs-secondary-color);
    font-size: .8rem;
    font-weight: 800;
    text-transform: uppercase;
}
.introjs-helperLayer.admin-tour-highlight {
    z-index: 10000010 !important;
    background: transparent !important;
    border: 2px solid var(--bs-primary) !important;
    border-radius: 12px !important;
    box-shadow: 0 0 0 6px rgba(var(--bs-primary-rgb), .15), rgba(2, 6, 23, .8) 0 0 0 5000px !important;
    pointer-events: none !important;
}
.introjs-showElement,
.introjs-showElement > *,
.introjs-fixParent {
    position: relative !important;
    z-index: 10000030 !important;
    opacity: 1 !important;
    filter: none !important;
    visibility: visible !important;
}
/* O background do showElement garante que elementos com fundo transparente nao sumam */
.introjs-showElement {
    isolation: isolate !important;
    background-color: var(--bs-body-bg) !important;
    box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), .2) !important;
    border-radius: 8px !important;
}
.admin-tour-current {
    position: relative !important;
    z-index: 10000035 !important;
    opacity: 1 !important;
    filter: none !important;
    visibility: visible !important;
}
.introjs-tooltipReferenceLayer,
.introjs-tooltip,
.introjs-floating {
    z-index: 10000050 !important;
}
@media (max-width: 575.98px) {
    .introjs-tooltip.admin-tour-tooltip .introjs-tooltipbuttons { padding-bottom: 2.8rem; }
    .admin-tour-counter { bottom: 1rem; }
}

/* PWA mobile footer */
.pwa-footer-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1040;
    display: flex;
    align-items: center;
    justify-content: space-around;
    min-height: 64px;
    padding: .35rem .25rem calc(.35rem + env(safe-area-inset-bottom));
    background: var(--bs-body-bg);
    border-top: 1px solid rgba(148,163,184,.25);
}
.pwa-footer-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .2rem;
    text-decoration: none;
    color: var(--bs-secondary-color);
    font-size: .7rem;
    font-weight: 500;
    transition: color .15s ease;
}
.pwa-footer-item i { font-size: 1.2rem; line-height: 1; }
.pwa-footer-item.is-active { color: var(--bs-primary) !important; }
.pwa-footer-item:hover { color: var(--bs-primary); text-decoration: none; }
@media (max-width: 991.98px) {
    body.has-pwa-footer { padding-bottom: calc(74px + env(safe-area-inset-bottom)); }
}
</style>
<x-pwa.footer-nav />
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js?v={{ file_exists(public_path('sw.js')) ? filemtime(public_path('sw.js')) : time() }}')
                .catch(function (error) {
                    console.warn('Service Worker não registrado:', error);
                });
        });
    }
</script>
@include('partials.meta-help-drawer')
</body>
</html>
