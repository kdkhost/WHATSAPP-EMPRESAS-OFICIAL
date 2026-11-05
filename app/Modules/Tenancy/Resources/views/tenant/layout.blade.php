@php
    use App\Support\Theme\ThemeManager;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;

    $user = Auth::user();
    $tenant = $tenant ?? \App\Modules\Tenancy\Models\Tenant::with(['plan'])
        ->where('user_id', $user?->id)
        ->first();

    $tenantWhiteLabel = $tenant?->whiteLabelSettings() ?? [];
    $tenantWhiteLabelEnabled = $tenant?->whiteLabelEnabled() ?? false;
    $appName = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['brand_name'])
        ? $tenantWhiteLabel['brand_name']
        : $settingsManager->get('app.name', config('app.name'));
    $appLogo = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['logo_url'])
        ? $tenantWhiteLabel['logo_url']
        : $settingsManager->get('app.logo');
    $appFavicon = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['favicon_url'])
        ? $tenantWhiteLabel['favicon_url']
        : $settingsManager->get('app.favicon');
    $tenantCustomCss = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['custom_css'])
        ? str_ireplace('</style', '<\/style', (string) $tenantWhiteLabel['custom_css'])
        : '';
    $tenantFooterText = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['footer_text'])
        ? $tenantWhiteLabel['footer_text']
        : null;
    $tenantName = $tenant->company_name ?? $user?->name ?? 'Painel do cliente';
    $themeManager = app(ThemeManager::class);
    $theme = $themeManager->forTenant($user, $tenant, request());
    $themeModes = [
        'light' => $themeManager->variablesForMode('light', $tenant),
        'dark' => $themeManager->variablesForMode('dark', $tenant),
    ];
    $themeDark = $theme['is_dark'];
    $initial = strtoupper(substr($user?->name ?? $tenantName, 0, 1));
    $userAvatar = $user && array_key_exists('avatar_url', $user->getAttributes())
        ? $user->getAttribute('avatar_url')
        : null;

$operationOpen = request()->routeIs('tenant.instances*', 'tenant.templates*', 'tenant.gateways*', 'tenant.billing*', 'tenant.invoices*', 'tenant.packages*', 'tenant.customers*', 'tenant.api.*', 'tenant.reports*', 'tenant.history.whatsapp*');
$emailOpen = (request()->routeIs('tenant.email.*', 'tenant.history.email*') || request()->is('painel/email*')) && !request()->routeIs('tenant.contracts.*') && !request()->is('painel/contratos*');
    $tenantAddonMenu = class_exists(\App\Modules\Marketplace\Services\AddonMarketplaceService::class) && $tenant
        ? app(\App\Modules\Marketplace\Services\AddonMarketplaceService::class)->tenantMenus($tenant)
        : [];
    $tenantAddonOpen = request()->routeIs('tenant.addons.*', 'painel.aapanel.*', 'tenant.whmcs.*', 'tenant.cpanel.*')
        || collect($tenantAddonMenu)->contains(function ($m) {
            $slug = $m['slug'] ?? '';
            $r = $m['route'] ?? '';
            $prefix = $r ? \Illuminate\Support\Str::beforeLast($r, '.') : '';
            return ($r && request()->routeIs($r))
                || ($prefix && request()->routeIs($prefix.'.*'))
                || ($slug && (request()->is('*'.$slug.'*') || request()->routeIs('*'.$slug.'*')));
        });
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
<meta name="theme-color" content="{{ $theme['theme_color'] }}">
<meta name="color-scheme" content="light dark">
<title>@yield('title', 'Painel') - {{ $tenantWhiteLabelEnabled ? $appName : $tenantName }}</title>
@if($appFavicon)
<link rel="icon" href="{{ $appFavicon }}">
<link rel="shortcut icon" href="{{ $appFavicon }}">
<link rel="apple-touch-icon" href="{{ $appFavicon }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
{{-- DataTables Bootstrap 5 + Responsive + Buttons --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/datatables-custom.css') }}?v={{ time() }}">
<link rel="manifest" href="{{ url('/manifest.json') }}">

<style>
:root {
    {!! $theme['css_variables'] !!}
}
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: .875rem; }
h1, h2, h3, h4, h5, h6 { font-weight: 600; letter-spacing: 0; }
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
    padding: .75rem 1rem;
    font-weight: 600;
    font-size: .875rem;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}
.card-header > :first-child { flex: 1; }
.card-body { padding: 1rem; }
.card-footer { background: var(--app-surface-bg); border-top: 1px solid var(--bs-border-color); padding: .6rem 1rem; }
.premium-panel,
.premium-sidecard,
.tenant-premium-panel {
    border: 1px solid var(--app-border-soft);
    border-radius: var(--app-radius-premium);
    background: var(--app-surface-bg);
    overflow: hidden;
    box-shadow: var(--app-shadow-premium);
}
.premium-panel__header,
.premium-sidecard__head,
.tenant-premium-panel__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.2rem;
    border-bottom: 1px solid var(--app-border-soft);
}
.premium-panel__body,
.premium-sidecard__body,
.tenant-premium-panel__body { padding: 1.2rem; }
.premium-panel__footer,
.tenant-premium-panel__footer {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    padding: 1rem 1.2rem;
    border-top: 1px solid var(--app-border-soft);
    background: color-mix(in srgb,var(--app-surface-bg) 88%,var(--bs-primary) 12%);
}
.premium-panel__kicker,
.premium-sidecard__kicker,
.tenant-premium-kicker {
    display: block;
    font-size: .74rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .3rem;
}
.premium-panel__title,
.tenant-premium-title { margin: 0; font-size: 1.08rem; }
.premium-panel__subtitle,
.tenant-premium-subtitle { margin: 0; color: var(--bs-secondary-color); line-height: 1.55; }
.tenant-premium-hero {
    display: grid;
    grid-template-columns: minmax(0,1.5fr) minmax(280px,.8fr);
    gap: 1.25rem;
    padding: 1.4rem;
    border: 1px solid var(--app-border-soft);
    border-radius: var(--app-radius-premium);
    background:
        linear-gradient(135deg,color-mix(in srgb,var(--bs-primary) 10%,transparent),transparent 40%),
        linear-gradient(180deg,var(--bs-body-bg),color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%));
}
.tenant-premium-hero__eyebrow {
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
.tenant-premium-hero__title { font-size: 1.9rem; line-height: 1.1; margin: 0 0 .75rem; }
.tenant-premium-hero__text { max-width: 58rem; color: var(--bs-secondary-color); margin: 0; }
.tenant-premium-metric {
    padding: 1rem 1.05rem;
    border-radius: 16px;
    border: 1px solid var(--app-border-soft);
    background: var(--bs-body-bg);
}
.btn { border-radius: var(--app-radius); font-weight: 500; transition: var(--app-transition); }
.btn > .bi { font-size: .95em !important; line-height: 1; color: currentColor; }
.btn-primary { background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; }
.text-primary { color: var(--bs-primary) !important; }
.table thead th { font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
.card-body.p-0 { overflow-x: auto; overflow-y: visible; }
.card-body.p-0:has(.table-responsive) { overflow: visible; }
.table { min-width: 100%; white-space: nowrap; }
.form-control:focus, .form-select:focus { box-shadow: 0 0 0 2px color-mix(in srgb, var(--bs-primary) 25%, transparent); border-color: var(--bs-primary); }
.form-label { font-weight: 500; font-size: .8125rem; }
html, body {
    min-height: 100%;
}
.app-wrapper {
    min-height: 100vh !important;
    display: flex !important;
    flex-direction: column !important;
}
.app-main {
    flex: 1 0 auto !important;
    min-width: 0 !important;
}
.app-footer {
    font-size: .8rem;
    border-top: 1px solid var(--bs-border-color);
    padding: .85rem 1.25rem;
    margin-top: auto !important;
    flex-shrink: 0 !important;
}
.app-sidebar { background-color: var(--app-sidebar-bg) !important; border-right: none !important; }
.app-sidebar .nav-link {
    color: var(--app-sidebar-muted) !important;
    border-radius: var(--app-radius);
    margin: 1px 8px;
    padding: .5rem .85rem;
    font-size: .8125rem;
    transition: var(--app-transition);
}
.app-sidebar .nav-link:hover { color: var(--app-sidebar-color) !important; background: var(--app-sidebar-hover) !important; }
.app-sidebar .nav-link.active { color: var(--app-sidebar-active-color) !important; background: var(--app-sidebar-active) !important; font-weight: 500; }
.app-sidebar .nav-link i { width: 1.4rem; font-size: .95rem; opacity: .85; }
.app-sidebar .nav-treeview .nav-link { padding: .35rem .85rem .35rem 2.4rem; font-size: .8rem; margin: 0 8px; }
.app-sidebar .nav-treeview .nav-link.active { background: var(--app-sidebar-tree-active) !important; color: var(--app-sidebar-color) !important; }
.sidebar-brand { color: var(--app-sidebar-color) !important; font-weight: 700; background: var(--app-sidebar-brand-bg); border-color: var(--app-sidebar-border) !important; }
.sidebar-brand .brand-link,
.sidebar-brand .brand-link:hover,
.sidebar-brand .brand-link:focus,
.sidebar-brand .brand-text { color: var(--app-sidebar-color) !important; }
.sidebar-wrapper { overflow-y: auto; overflow-x: hidden; height: calc(100vh - 57px); padding-bottom: 60px; }
.sidebar-wrapper::-webkit-scrollbar { width: 3px; }
.sidebar-wrapper::-webkit-scrollbar-thumb { background: var(--app-sidebar-scrollbar); border-radius: 3px; }
.sidebar-menu .nav-treeview { display: none; }
.sidebar-menu .nav-item.menu-open > .nav-treeview { display: block !important; }
.sidebar-menu .nav-item.menu-open > .nav-link .nav-arrow { transform: rotate(90deg); }
.sidebar-menu .nav-arrow { transition: transform .2s ease; margin-left: auto; }

@media (min-width: 992px) {
    .app-sidebar {
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 250px !important;
        height: 100vh !important;
        max-height: 100vh !important;
        z-index: 1035 !important;
    }
    .sidebar-wrapper {
        height: calc(100vh - 60px) !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }
    .app-header,
    .app-main,
    .app-footer {
        margin-left: 250px !important;
        width: calc(100% - 250px) !important;
        max-width: calc(100% - 250px) !important;
        transition: margin-left .2s ease, width .2s ease;
    }
    body.sidebar-collapse .app-sidebar {
        margin-left: -250px !important;
    }
    body.sidebar-collapse .app-header,
    body.sidebar-collapse .app-main,
    body.sidebar-collapse .app-footer {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
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
.stat-icon.bg-primary.text-white { background-color: var(--bs-primary-bg-subtle) !important; color: var(--bs-primary) !important; }
.stat-icon.bg-success.text-white { background-color: var(--bs-success-bg-subtle) !important; color: var(--bs-success) !important; }
.stat-icon.bg-danger.text-white { background-color: var(--bs-danger-bg-subtle) !important; color: var(--bs-danger) !important; }
.stat-icon.bg-warning.text-white { background-color: var(--bs-warning-bg-subtle) !important; color: var(--bs-warning) !important; }
.stat-icon.bg-info.text-white { background-color: var(--bs-info-bg-subtle) !important; color: var(--bs-info) !important; }
.stat-icon.bg-secondary.text-white { background-color: var(--bs-secondary-bg-subtle) !important; color: var(--bs-secondary) !important; }
.stat-icon.bg-dark.text-white { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; }
.card-body:has(.stat-icon) {
    display: flex !important;
    align-items: center !important;
    gap: .75rem !important;
    justify-content: flex-start !important;
    padding: 1rem !important;
}
.card-body:has(.stat-icon) > .stat-icon { order: -1; margin: 0 !important; }
.card-body:has(.stat-icon) > div:not(.stat-icon),
.card-body:has(.stat-icon) > .flex-grow-1 { flex: 1 1 auto; min-width: 0; text-align: left !important; }
.card-body:has(.stat-icon) .text-muted.small,
.card-body:has(.stat-icon) .small.text-muted { text-transform: uppercase; font-weight: 600; font-size: .75rem; letter-spacing: .02em; }
.tenant-assist-banner {
    border: 1px solid color-mix(in srgb, var(--bs-warning) 40%, var(--app-border-soft));
    border-radius: var(--app-radius-premium);
    background: color-mix(in srgb, var(--bs-warning) 12%, var(--bs-body-bg));
    box-shadow: var(--app-shadow-premium);
}
[data-bs-theme="dark"] { color-scheme: dark; }
[data-bs-theme="dark"] body,
[data-bs-theme="dark"] .app-main,
[data-bs-theme="dark"] .app-content {
    background: var(--tenant-dark-bg) !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .app-header.navbar,
[data-bs-theme="dark"] .app-footer {
    background: var(--tenant-dark-surface) !important;
    border-color: var(--tenant-dark-border) !important;
    box-shadow: none !important;
}
[data-bs-theme="dark"] .card,
[data-bs-theme="dark"] .premium-panel,
[data-bs-theme="dark"] .premium-sidecard,
[data-bs-theme="dark"] .tenant-premium-panel {
    background: var(--tenant-dark-surface) !important;
    border-color: var(--tenant-dark-border) !important;
    box-shadow: none !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer,
[data-bs-theme="dark"] .premium-panel__header,
[data-bs-theme="dark"] .premium-sidecard__head,
[data-bs-theme="dark"] .tenant-premium-panel__header,
[data-bs-theme="dark"] .premium-panel__footer,
[data-bs-theme="dark"] .tenant-premium-panel__footer {
    background: var(--tenant-dark-surface) !important;
    border-color: var(--tenant-dark-border) !important;
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .table-light {
    --bs-table-bg: var(--tenant-dark-surface);
    color: var(--tenant-dark-muted);
}
[data-bs-theme="dark"] .table-light th { color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .text-muted { color: var(--tenant-dark-muted) !important; }
[data-bs-theme="dark"] .text-dark { color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--bs-body-color);
    --bs-table-border-color: var(--tenant-dark-border);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .table td, [data-bs-theme="dark"] .table th { color: var(--bs-body-color); }
[data-bs-theme="dark"] .table > :not(caption) > * > * { border-bottom-color: var(--tenant-dark-border); }
[data-bs-theme="dark"] .table-hover > tbody > tr:hover > * { background-color: rgba(91,77,246,.08); color: var(--bs-body-color); }
[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select,
[data-bs-theme="dark"] .input-group-text {
    background-color: var(--tenant-dark-field) !important;
    border-color: var(--tenant-dark-border) !important;
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .form-control::placeholder { color: #64748b; }
[data-bs-theme="dark"] .form-control:focus,
[data-bs-theme="dark"] .form-select:focus {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0 0 .18rem rgba(var(--bs-primary-rgb), .22) !important;
}
[data-bs-theme="dark"] .btn-primary { background: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; box-shadow: none !important; }
[data-bs-theme="dark"] .modal-content { background-color: var(--tenant-dark-surface); color: var(--bs-body-color); border-color: var(--tenant-dark-border); }
[data-bs-theme="dark"] .modal-header,
[data-bs-theme="dark"] .modal-footer { border-color: var(--tenant-dark-border); }
[data-bs-theme="dark"] .dropdown-menu { background-color: var(--tenant-dark-surface); border-color: var(--tenant-dark-border); color: var(--bs-body-color); }
[data-bs-theme="dark"] .dropdown-item { color: var(--bs-body-color); }
[data-bs-theme="dark"] .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-item:focus { background-color: rgba(var(--bs-primary-rgb), .12); color: #fff; }
[data-bs-theme="dark"] .dropdown-divider { border-top-color: var(--tenant-dark-border); }
[data-bs-theme="dark"] .btn-outline-secondary,
[data-bs-theme="dark"] .btn-outline-dark { color: var(--bs-body-color); border-color: var(--tenant-dark-border); }
[data-bs-theme="dark"] .btn-outline-dark:hover { background-color: #1e293b; color: #fff !important; }
[data-bs-theme="dark"] .bg-light { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .bg-white,
[data-bs-theme="dark"] .card.bg-white,
[data-bs-theme="dark"] .card-header.bg-white,
[data-bs-theme="dark"] .card-footer.bg-white {
    background-color: var(--tenant-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--tenant-dark-border) !important;
}
[data-bs-theme="dark"] .bg-body,
[data-bs-theme="dark"] .bg-body-secondary,
[data-bs-theme="dark"] .bg-body-tertiary {
    background-color: var(--tenant-dark-bg) !important;
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .border-light,
[data-bs-theme="dark"] .border-white {
    border-color: var(--tenant-dark-border) !important;
}
[data-bs-theme="dark"] .nav-tabs .nav-link {
    color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .nav-tabs .nav-link.active {
    background-color: var(--tenant-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--tenant-dark-border) var(--tenant-dark-border) transparent !important;
}
[data-bs-theme="dark"] .swal2-popup {
    background: var(--tenant-dark-surface) !important;
    color: var(--bs-body-color) !important;
    border: 1px solid var(--tenant-dark-border) !important;
}
[data-bs-theme="dark"] .swal2-title,
[data-bs-theme="dark"] .swal2-html-container {
    color: var(--bs-body-color) !important;
}
[data-bs-theme="dark"] .swal2-input,
[data-bs-theme="dark"] .swal2-textarea,
[data-bs-theme="dark"] .swal2-select {
    background-color: var(--tenant-dark-field) !important;
    color: var(--bs-body-color) !important;
    border-color: var(--tenant-dark-border) !important;
}
[data-bs-theme="dark"] .toastify {
    border: 1px solid var(--tenant-dark-border);
}
[data-bs-theme="dark"] .badge.bg-light { background-color: var(--bs-tertiary-bg) !important; color: var(--bs-body-color) !important; border: 1px solid var(--tenant-dark-border); }
[data-bs-theme="dark"] .note-editor.note-frame,
[data-bs-theme="dark"] .note-editor .note-toolbar,
[data-bs-theme="dark"] .note-editor .note-statusbar { background: var(--tenant-dark-surface) !important; color: var(--bs-body-color) !important; border-color: var(--tenant-dark-border) !important; }
[data-bs-theme="dark"] .note-editor .note-editing-area .note-editable { background: var(--tenant-dark-field) !important; color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .note-editor .dropdown-menu { background-color: var(--tenant-dark-surface) !important; color: var(--bs-body-color) !important; }
[data-bs-theme="dark"] .note-editor .note-btn { color: var(--bs-body-color) !important; border-color: var(--tenant-dark-border) !important; }
[data-bs-theme="light"] body { background-color: var(--app-body-bg) !important; color: var(--app-body-color) !important; }
[data-bs-theme="light"] .app-main,
[data-bs-theme="light"] .app-content { background-color: var(--app-body-bg) !important; color: var(--app-body-color) !important; }
[data-bs-theme="light"] .app-header.navbar,
[data-bs-theme="light"] .app-footer {
    background-color: var(--app-navbar-bg) !important;
    color: var(--app-navbar-color) !important;
    border-color: var(--app-navbar-border) !important;
}
[data-bs-theme="light"] .app-header .nav-link,
[data-bs-theme="light"] .app-header .btn,
[data-bs-theme="light"] .app-header .btn-link,
[data-bs-theme="light"] .app-header .dropdown-toggle,
[data-bs-theme="light"] .app-footer { color: var(--app-navbar-color) !important; }
[data-bs-theme="light"] .text-muted { color: var(--app-body-muted) !important; }
[data-bs-theme="light"] .table-light { --bs-table-bg: #f8f9fa; color: #495057; }
.swal2-popup { background-color: var(--bs-body-bg) !important; color: var(--bs-body-color) !important; border: 1px solid var(--bs-border-color); border-radius: var(--app-radius); }
.swal2-title,
.swal2-html-container { color: var(--bs-body-color) !important; }
.swal2-styled.swal2-confirm { background-color: var(--bs-primary) !important; }
.swal2-actions { gap: .5rem; }
@media (max-width: 1199.98px) { .tenant-premium-hero { grid-template-columns: 1fr; } }
@media (max-width: 767.98px) {
    .tenant-premium-hero__title { font-size: 1.5rem; }
    .premium-panel__footer,
    .tenant-premium-panel__footer { flex-direction: column; }
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
@if($tenantCustomCss)
<style id="tenant-whitelabel-css">{!! $tenantCustomCss !!}</style>
@endif
@stack('head')
@stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary tenant-admin-layout {{ (auth()->check() || auth('admin')->check()) ? 'has-pwa-footer' : '' }}">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body sticky-top shadow-sm">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list fs-4"></i></a></li>
                <li class="nav-item d-none d-md-block"><a class="nav-link" href="/painel">Inicio</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                @if(session('admin_assist_mode'))
                    <li class="nav-item d-none d-lg-block">
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bi bi-person-badge me-1"></i> Modo assistido
                        </span>
                    </li>
                @endif
                <li class="nav-item">
                    <button id="theme-toggle"
                            class="btn btn-link nav-link"
                            title="Alternar tema"
                            data-theme-toggle
                            data-theme-endpoint="{{ route('tenant.preferences.theme') }}">
                        <i class="bi {{ $themeDark ? 'bi-sun-fill' : 'bi-moon-stars-fill' }}"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <button id="restart-tenant-tour" class="btn btn-link nav-link" title="Reiniciar tour guiado">
                        <i class="bi bi-question-circle"></i>
                    </button>
                </li>
                @include('admin.partials.bell')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 overflow-hidden"
                              style="width:32px;height:32px;font-size:.85rem;flex-shrink:0;">
                            @if($userAvatar)
                                <img src="{{ $userAvatar }}" alt="{{ $user?->name ?? $tenantName }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                {{ $initial }}
                            @endif
                        </span>
                        <span class="d-none d-md-inline">{{ $user?->name ?? $tenantName }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:260px;">
                        <li class="px-3 py-2">
                            <div class="d-flex align-items-center gap-3">
                                <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center overflow-hidden"
                                      style="width:48px;height:48px;font-size:1.2rem;flex-shrink:0;">
                                    @if($userAvatar)
                                        <img src="{{ $userAvatar }}" alt="{{ $user?->name ?? $tenantName }}" style="width:100%;height:100%;object-fit:cover;">
                                    @else
                                        {{ $initial }}
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $user?->name ?? $tenantName }}</div>
                                    <small class="text-muted text-truncate d-block">{{ $user?->email ?? '' }}</small>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1" style="font-size:.65rem;">
                                        Cliente tenant
                                    </span>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2" href="/painel"><i class="bi bi-speedometer2 me-2 text-primary"></i> Painel</a></li>
                        @if(Route::has('tenant.profile'))
                            <li><a class="dropdown-item py-2" href="{{ route('tenant.profile') }}"><i class="bi bi-person-circle me-2 text-success"></i> Meu perfil</a></li>
                        @endif
                        @if(Route::has('tenant.onboarding.index'))
                            <li><a class="dropdown-item py-2" href="{{ route('tenant.onboarding.index') }}"><i class="bi bi-magic me-2 text-info"></i> Onboarding</a></li>
                        @endif
                        @if(session('admin_assist_mode'))
                            <li>
                                <form method="POST" action="{{ route('tenant.assist.stop') }}">
                                    @csrf
                                    <button class="dropdown-item py-2 text-warning"><i class="bi bi-arrow-return-left me-2"></i>Voltar para Admin</button>
                                </form>
                            </li>
                        @endif
                        @unless(session('admin_assist_mode'))
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item py-2 text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
                                </form>
                            </li>
                        @endunless
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow">
        <div class="sidebar-brand p-3 border-bottom">
            <a href="/painel" class="brand-link d-flex align-items-center text-decoration-none">
                @if($appLogo)
                    <img src="{{ $appLogo }}" alt="{{ $appName }}" class="me-2" style="max-height:36px;max-width:150px;object-fit:contain;">
                @else
                    <i class="bi bi-whatsapp text-success fs-3 me-2"></i>
                @endif
                <span class="brand-text fw-bold text-truncate">{{ $tenantWhiteLabelEnabled ? $appName : $tenantName }}</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" role="menu">
                    <li class="nav-header text-uppercase text-secondary fw-bold mt-2 mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Principal</li>
                    <li class="nav-item">
                        <a href="/painel" class="nav-link {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('tenant.notifications') }}" class="nav-link {{ request()->routeIs('tenant.notifications*') || request()->is('painel/notificacoes*') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-bell"></i><p>Notificações</p>
                        </a>
                    </li>
                    @if(Route::has('tenant.onboarding.index'))
                        <li class="nav-item">
                            <a href="{{ route('tenant.onboarding.index') }}" class="nav-link {{ request()->routeIs('tenant.onboarding.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-magic"></i><p>Onboarding</p>
                            </a>
                        </li>
                    @endif
                    @if(Route::has('tenant.affiliate.dashboard'))
                        <li class="nav-item">
                            <a href="{{ route('tenant.affiliate.dashboard') }}" class="nav-link {{ request()->routeIs('tenant.affiliate.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-people-fill"></i><p>Programa de Afiliados</p>
                            </a>
                        </li>
                    @endif

                    <li class="nav-header text-uppercase text-secondary fw-bold mt-3 mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Comunicação</li>
                    @php
                        $wppOpen = request()->routeIs('tenant.instances*', 'tenant.templates*', 'tenant.packages*', 'tenant.whatsapp.manual*', 'tenant.dispatches*', 'tenant.history.whatsapp*')
                            || request()->is('painel/instancias*', 'painel/templates*', 'painel/pacotes*', 'painel/whatsapp*', 'painel/disparos*');
                    @endphp
                    <li class="nav-item {{ $wppOpen ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ $wppOpen ? 'active' : '' }}">
                            <i class="nav-icon bi bi-whatsapp text-success"></i><p>WhatsApp <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="{{ route('tenant.instances') }}" class="nav-link {{ request()->routeIs('tenant.instances*') || request()->is('painel/instancias*') ? 'active' : '' }}"><i class="nav-icon bi bi-phone"></i><p>Instâncias</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.templates') }}" class="nav-link {{ request()->routeIs('tenant.templates*') || request()->is('painel/templates*') ? 'active' : '' }}"><i class="nav-icon bi bi-chat-square-text"></i><p>Templates</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.packages') }}" class="nav-link {{ request()->routeIs('tenant.packages*') || request()->is('painel/pacotes*') ? 'active' : '' }}"><i class="nav-icon bi bi-box-seam"></i><p>Pacotes</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.whatsapp.manual') }}" class="nav-link {{ request()->routeIs('tenant.whatsapp.manual*') || request()->is('painel/whatsapp/manual*') ? 'active' : '' }}"><i class="nav-icon bi bi-chat-dots-fill text-success"></i><p>Envio Manual</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.dispatches') }}" class="nav-link {{ request()->routeIs('tenant.dispatches*') || request()->is('painel/disparos*') ? 'active' : '' }}"><i class="nav-icon bi bi-send"></i><p>Envios Agendados</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.history.whatsapp') }}" class="nav-link {{ request()->routeIs('tenant.history.whatsapp*') || request()->is('painel/whatsapp/historico*') ? 'active' : '' }}"><i class="nav-icon bi bi-clock-history"></i><p>Histórico WhatsApp</p></a></li>
                        </ul>
                    </li>
                    <li class="nav-item {{ $emailOpen ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ $emailOpen ? 'active' : '' }}">
                            <i class="nav-icon bi bi-envelope-paper"></i><p>E-mail <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(Route::has('tenant.email.config'))
                                <li class="nav-item"><a href="{{ route('tenant.email.config') }}" class="nav-link {{ request()->routeIs('tenant.email.config*') || request()->is('painel/email/config*') ? 'active' : '' }}"><i class="nav-icon bi bi-gear"></i><p>Configurar SMTP</p></a></li>
                            @endif
                            @if(Route::has('tenant.email.templates'))
                                <li class="nav-item"><a href="{{ route('tenant.email.templates') }}" class="nav-link {{ request()->routeIs('tenant.email.templates*') || request()->is('painel/email/templates*') ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-text"></i><p>Templates de E-mail</p></a></li>
                            @endif
                            @if(Route::has('tenant.history.email'))
                                <li class="nav-item"><a href="{{ route('tenant.history.email') }}" class="nav-link {{ request()->routeIs('tenant.history.email*') || request()->is('painel/email/historico*') ? 'active' : '' }}"><i class="nav-icon bi bi-envelope"></i><p>Histórico E-mail</p></a></li>
                            @endif
                        </ul>
                    </li>

                    <li class="nav-header text-uppercase text-secondary fw-bold mt-3 mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Comercial & Financeiro</li>
                    <li class="nav-item">
                        <a href="{{ route('tenant.customers') }}" class="nav-link {{ request()->routeIs('tenant.customers*') || request()->is('painel/clientes*') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-people"></i><p>Clientes (CRM)</p>
                        </a>
                    </li>
                    @php
                        $finOpen = request()->routeIs('tenant.invoices*', 'tenant.billing*', 'tenant.gateways*', 'tenant.calendar*', 'tenant.reports*')
                            || request()->is('painel/faturas*', 'painel/cobranca*', 'painel/historico-cobranca*', 'painel/gateways*', 'painel/calendario*', 'painel/relatorios*');
                    @endphp
                    <li class="nav-item {{ $finOpen ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ $finOpen ? 'active' : '' }}">
                            <i class="nav-icon bi bi-currency-dollar"></i><p>Financeiro <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="{{ route('tenant.invoices') }}" class="nav-link {{ (request()->routeIs('tenant.invoices*') || request()->is('painel/faturas*')) && request('tipo') !== 'avulsa' ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-text"></i><p>Faturas & Cobranças</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.invoices') }}?tipo=avulsa" class="nav-link {{ (request()->routeIs('tenant.invoices*') || request()->is('painel/faturas*')) && request('tipo') === 'avulsa' ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-plus"></i><p>Cobranças Avulsas</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.billing') }}" class="nav-link {{ ((request()->routeIs('tenant.billing') || request()->is('painel/cobranca*')) && !request()->routeIs('tenant.billing.history*') && !request()->is('painel/historico-cobranca*')) ? 'active' : '' }}"><i class="nav-icon bi bi-gear"></i><p>Cobrança Automática</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.billing.history') }}" class="nav-link {{ request()->routeIs('tenant.billing.history*') || request()->is('painel/historico-cobranca*') ? 'active' : '' }}"><i class="nav-icon bi bi-chat-dots"></i><p>Histórico Cobrança</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.gateways') }}" class="nav-link {{ request()->routeIs('tenant.gateways*') || request()->is('painel/gateways*') ? 'active' : '' }}"><i class="nav-icon bi bi-credit-card"></i><p>Gateways de Pagamento</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.calendar') }}" class="nav-link {{ request()->routeIs('tenant.calendar*') || request()->is('painel/calendario*') ? 'active' : '' }}"><i class="nav-icon bi bi-calendar3"></i><p>Calendário</p></a></li>
                            <li class="nav-item"><a href="{{ route('tenant.reports') }}" class="nav-link {{ request()->routeIs('tenant.reports*') || request()->is('painel/relatorios*') ? 'active' : '' }}"><i class="nav-icon bi bi-bar-chart"></i><p>Relatórios</p></a></li>
                        </ul>
                    </li>
                    @contractsEnabled
                        <li class="nav-item">
                            <a href="{{ route('tenant.contracts.index') }}" class="nav-link {{ request()->routeIs('tenant.contracts.*') || request()->is('painel/contratos*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-file-earmark-check"></i><p>Contratos</p>
                            </a>
                        </li>
                    @endcontractsEnabled

                    <li class="nav-header text-uppercase text-secondary fw-bold mt-3 mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Módulos & Integrações</li>
                    @if(Route::has('tenant.api.index'))
                        <li class="nav-item">
                            <a href="{{ route('tenant.api.index') }}" class="nav-link {{ request()->routeIs('tenant.api.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-braces"></i><p>API REST</p>
                            </a>
                        </li>
                    @endif
                    @if(Route::has('tenant.whitelabel.index') && $settingsManager->get('whitelabel.tenant_enabled', true))
                        <li class="nav-item">
                            <a href="{{ route('tenant.whitelabel.index') }}" class="nav-link {{ request()->routeIs('tenant.whitelabel.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-palette"></i><p>White Label</p>
                            </a>
                        </li>
                    @endif
                    @if(Route::has('tenant.addons.index'))
                        <li class="nav-item {{ $tenantAddonOpen ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $tenantAddonOpen ? 'active' : '' }}">
                                <i class="nav-icon bi bi-grid-1x2"></i><p>Addons & Apps <i class="nav-arrow bi bi-chevron-right"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="{{ route('tenant.addons.index') }}" class="nav-link {{ request()->routeIs('tenant.addons.index') ? 'active' : '' }}"><i class="nav-icon bi bi-shop"></i><p>Marketplace</p></a></li>

                                {{-- aaPanel — somente se instalado pelo tenant --}}
                                @if(Route::has('painel.aapanel.dashboard') && class_exists(\App\Modules\Marketplace\Services\AddonMarketplaceService::class) && app(\App\Modules\Marketplace\Services\AddonMarketplaceService::class)->tenantHasAddon($tenant, 'aapanel'))
                                    <li class="nav-item">
                                        <a href="{{ route('painel.aapanel.dashboard') }}" class="nav-link {{ request()->routeIs('painel.aapanel.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-server"></i><p>aaPanel</p>
                                        </a>
                                    </li>
                                @endif

                                {{-- Integração WHMCS — somente se instalado pelo tenant --}}
                                @if(Route::has('tenant.whmcs.index') && class_exists(\App\Modules\Marketplace\Services\AddonMarketplaceService::class) && app(\App\Modules\Marketplace\Services\AddonMarketplaceService::class)->tenantHasAddon($tenant, 'whmcs'))
                                    <li class="nav-item">
                                        <a href="{{ route('tenant.whmcs.index') }}" class="nav-link {{ request()->routeIs('tenant.whmcs.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-box-seam"></i><p>Integração WHMCS</p>
                                        </a>
                                    </li>
                                @endif

                                {{-- Integração cPanel — somente se instalado pelo tenant --}}
                                @if(Route::has('tenant.cpanel.index') && class_exists(\App\Modules\Marketplace\Services\AddonMarketplaceService::class) && app(\App\Modules\Marketplace\Services\AddonMarketplaceService::class)->tenantHasAddon($tenant, 'cpanel'))
                                    <li class="nav-item">
                                        <a href="{{ route('tenant.cpanel.index') }}" class="nav-link {{ request()->routeIs('tenant.cpanel.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-server"></i><p>Integração cPanel</p>
                                        </a>
                                    </li>
                                @endif

                                {{-- Addons dinamicos do marketplace --}}
                                @foreach($tenantAddonMenu as $menu)
                                    @php
                                        $menuSlug = $menu['slug'] ?? '';
                                        if (in_array($menuSlug, ['aapanel', 'whmcs', 'cpanel'], true)) {
                                            continue;
                                        }
                                        $menuUrl = $menu['url'] ?? '';
                                        $menuRoute = trim((string) ($menu['route'] ?? ''));
                                        if ($menuUrl !== '') {
                                            $menuActive = request()->is(trim($menuUrl, '/').'*')
                                                || ($menuSlug !== '' && (request()->is('*'.$menuSlug.'*') || request()->routeIs('*'.$menuSlug.'*')));
                                        } elseif (Route::has($menuRoute)) {
                                            $menuUrl = route($menuRoute);
                                            $prefixRoute = \Illuminate\Support\Str::beforeLast($menuRoute, '.');
                                            $menuActive = request()->routeIs($menuRoute)
                                                || ($prefixRoute !== '' && request()->routeIs($prefixRoute.'.*'))
                                                || ($menuSlug !== '' && (request()->is('*'.$menuSlug.'*') || request()->routeIs('*'.$menuSlug.'*')));
                                        } else {
                                            $menuUrl = url($menuRoute);
                                            $menuActive = request()->is(trim($menuRoute, '/').'*')
                                                || ($menuSlug !== '' && (request()->is('*'.$menuSlug.'*') || request()->routeIs('*'.$menuSlug.'*')));
                                        }
                                        $menuIcon = str_starts_with($menu['icon'], 'bi ') ? $menu['icon'] : (str_starts_with($menu['icon'], 'bi-') ? 'bi '.$menu['icon'] : $menu['icon']);
                                    @endphp
                                    <li class="nav-item"><a href="{{ $menuUrl }}" class="nav-link {{ $menuActive ? 'active' : '' }}"><i class="nav-icon {{ $menuIcon }}"></i><p>{{ $menu['title'] }}</p></a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content py-3">
            <div class="container-fluid">
                @if(session('admin_assist_mode'))
                    <div class="tenant-assist-banner d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 mb-3">
                        <div class="fw-semibold"><i class="bi bi-person-badge me-2 text-warning"></i>Modo assistido ativo: voce esta operando como tenant.</div>
                        <form method="POST" action="{{ route('tenant.assist.stop') }}" class="m-0">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-return-left me-1"></i>Voltar para Admin</button>
                        </form>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">Painel do cliente</div>
        @if($tenantFooterText)
            <strong>{{ $tenantFooterText }}</strong>
        @else
            <strong>&copy; {{ date('Y') }} {{ $appName }}.</strong> Todos os direitos reservados.
        @endif
    </footer>
    @if(auth()->check() || auth('admin')->check())
        <x-pwa.footer-nav />
        <script>document.body.classList.add('has-pwa-footer');</script>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/intro.min.js"></script>
<script>
window.__adminThemeVariables = @json($themeModes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
</script>
{{-- jQuery + DataTables Bootstrap 5 & Plugins de Exportação --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="{{ asset('assets/js/datatables-init.js') }}?v={{ time() }}"></script>
<script src="{{ asset('assets/js/admin.js') }}"></script>
<script>
window.csrf = document.querySelector('meta[name="csrf-token"]').content;

document.addEventListener('DOMContentLoaded', function() {
    var curPath = window.location.pathname.replace(/\/+$/, '');
    var curSearch = window.location.search || '';

    // 1. Sincronização inteligente de link ativo
    var activeLinks = document.querySelectorAll('.sidebar-menu .nav-link.active');
    if (activeLinks.length === 0) {
        var matchedLinks = [];
        document.querySelectorAll('.sidebar-menu a.nav-link[href]').forEach(function(link) {
            var href = link.getAttribute('href');
            if (!href || href === '#' || href.startsWith('javascript:')) return;
            try {
                var url = new URL(href, window.location.origin);
                var lPath = url.pathname.replace(/\/+$/, '');
                var lSearch = url.search || '';

                if (lSearch && lPath === curPath && lSearch === curSearch) {
                    matchedLinks.push({ link: link, score: 3 });
                } else if (!lSearch && lPath === curPath) {
                    if (!curSearch || !curSearch.includes('tipo=avulsa')) {
                        matchedLinks.push({ link: link, score: 2 });
                    }
                } else if (lPath !== '/painel' && curPath.startsWith(lPath + '/')) {
                    matchedLinks.push({ link: link, score: 1 });
                }
            } catch (e) {}
        });

        if (matchedLinks.length > 0) {
            matchedLinks.sort(function(a, b) { return b.score - a.score; });
            matchedLinks[0].link.classList.add('active');
        }
    }

    // 2. Abre automaticamente o menu-open de qualquer nav-item pai cujo filho estiver ativo
    document.querySelectorAll('.sidebar-menu .nav-treeview .nav-link.active').forEach(function(link) {
        var parentLi = link.closest('.sidebar-menu > .nav-item');
        if (parentLi) {
            parentLi.classList.add('menu-open');
            var parentLink = parentLi.querySelector(':scope > .nav-link');
            if (parentLink) parentLink.classList.add('active');
        }
    });

    // 3. Persistência de scroll da sidebar
    var sidebarWrapper = document.querySelector('.app-sidebar .sidebar-wrapper') 
        || document.querySelector('.sidebar-wrapper') 
        || document.querySelector('.app-sidebar');
    var storageKey = 'tenant_sidebar_scroll_top';

    if (sidebarWrapper) {
        function restoreSidebarScroll() {
            var saved = sessionStorage.getItem(storageKey);
            if (saved !== null && !isNaN(parseInt(saved, 10))) {
                sidebarWrapper.scrollTop = parseInt(saved, 10);
            }

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

        restoreSidebarScroll();
        requestAnimationFrame(restoreSidebarScroll);
        setTimeout(restoreSidebarScroll, 60);
        setTimeout(restoreSidebarScroll, 200);

        sidebarWrapper.addEventListener('scroll', function() {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        }, { passive: true });

        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        });
        window.addEventListener('pagehide', function() {
            sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
        });
    }

    // 4. Toggle de submenus e salvamento de scroll ao navegar
    document.querySelectorAll('.sidebar-menu a.nav-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var parentLi = link.parentElement;
            var submenu = parentLi ? parentLi.querySelector(':scope > .nav-treeview') : null;
            if (submenu) {
                e.preventDefault();
                e.stopPropagation();
                parentLi.classList.toggle('menu-open');
                if (sidebarWrapper) {
                    sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
                }
                return;
            }

            if (sidebarWrapper) {
                sessionStorage.setItem(storageKey, sidebarWrapper.scrollTop);
            }
        });
    });
});
</script>
<script>
window.startTenantTour = function() {
    if (typeof introJs !== 'function') return;

    const steps = [];
    const pageTitle = document.querySelector('.app-main h1, .app-main .h1, .card-title, .premium-panel__title, .tenant-premium-hero__title');

    if (pageTitle) steps.push({
        element: pageTitle,
        title: 'Contexto da pagina',
        intro: 'Aqui voce acompanha em qual area do painel esta trabalhando.',
        position: 'bottom'
    });

    const sidebar = document.querySelector('.app-sidebar');
    if (sidebar) steps.push({
        element: sidebar,
        title: 'Menu do painel',
        intro: 'Acesse os recursos do seu tenant, cobrancas, templates, gateways, API REST e configuracoes pelo menu lateral.',
        position: 'right'
    });

    const operationMenu = document.querySelector('.sidebar-menu > .nav-item.menu-open, .sidebar-menu > .nav-item:nth-child(2)');
    if (operationMenu) steps.push({
        element: operationMenu,
        title: 'Menus agrupados',
        intro: 'Os grupos mantem as funcoes relacionadas juntas para agilizar a operacao diaria.',
        position: 'right'
    });

    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) steps.push({
        element: themeBtn,
        title: 'Tema claro/escuro',
        intro: 'Alterne o visual do painel e mantenha sua preferencia salva para os proximos acessos.',
        position: 'bottom'
    });

    const restartBtn = document.getElementById('restart-tenant-tour');
    if (restartBtn) steps.push({
        element: restartBtn,
        title: 'Reabrir tour',
        intro: 'O tour aparece automaticamente no primeiro acesso e pode ser iniciado novamente por este botao.',
        position: 'bottom'
    });

    const bell = document.querySelector('.nav-item .bi-bell, .nav-item .bi-bell-fill');
    if (bell) steps.push({
        element: bell.closest('.nav-item') || bell,
        title: 'Notificacoes',
        intro: 'Acompanhe avisos e alertas importantes do painel sem sair da tela atual.',
        position: 'bottom'
    });

    const userMenu = document.querySelector('.navbar-nav .dropdown:last-child');
    if (userMenu) steps.push({
        element: userMenu,
        title: 'Perfil e acesso',
        intro: 'Acesse perfil, onboarding, retorno ao admin em modo assistido ou encerramento da sessao.',
        position: 'bottom-right-aligned'
    });

    const primaryAction = document.querySelector('.app-main .btn-primary, .app-main .btn-success');
    if (primaryAction) steps.push({
        element: primaryAction,
        title: 'Acao principal',
        intro: 'Botoes destacados executam as acoes mais importantes da tela.',
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
        markTenantTourComplete();
    }).onexit(() => {
        document.querySelectorAll('.admin-tour-current').forEach((element) => element.classList.remove('admin-tour-current'));
        markTenantTourComplete();
    }).start();
};

async function markTenantTourComplete() {
    try {
        await fetch('{{ route("tenant.tour.complete") }}', {
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
    const restartBtn = document.getElementById('restart-tenant-tour');
    if (restartBtn) {
        restartBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.startTenantTour();
        });
    }

    @if(empty($user?->tour_completed_at))
    setTimeout(() => window.startTenantTour(), 1000);
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
</style>
@include('admin.partials.upload-modal', ['uploadPrefix' => '/painel/uploads'])
@stack('scripts')
</body>
</html>
