@php
    use App\Modules\Settings\Services\SettingsManager;
    use App\Support\Theme\ThemeManager;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;

    $settings = app(SettingsManager::class);
    $themeManager = app(ThemeManager::class);
    $theme = $themeManager->forAdmin(Auth::guard('admin')->user(), request());
    $themeDark = $theme['is_dark'];

    $appName = $settings->get('app.name', config('app.name'));
    $appLogo = trim((string) $settings->get('app.logo'));
    $appFavicon = trim((string) $settings->get('app.favicon'));
    $assetUrl = static function (?string $path): ?string {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        return url($path);
    };

    $logoUrl = $assetUrl($appLogo);
    $faviconUrl = $assetUrl($appFavicon) ?: $logoUrl;
    $openApiUrl = url('/api/openapi.json');
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="{{ $themeDark ? 'dark' : 'light' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $theme['theme_color'] }}">
<meta name="color-scheme" content="light dark">
<title>Documentacao da API - {{ $appName }}</title>
@if($faviconUrl)
<link rel="icon" href="{{ $faviconUrl }}">
<link rel="shortcut icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@elseif(file_exists(public_path('favicon.ico')))
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
@endif
<link rel="manifest" href="{{ url('/manifest.json') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
<style>
:root {
    {!! $theme['css_variables'] !!}
    color-scheme: dark;
    --api-readable-text: #f8fafc;
    --api-readable-muted: #9ca3af;
    --api-readable-accent: color-mix(in srgb, var(--bs-primary) 48%, #f8fafc);
    --api-readable-link: color-mix(in srgb, var(--bs-primary) 58%, #f8fafc);
    --api-page-bg: #020617;
    --api-shell-bg: #0b1020;
    --api-shell-soft: #101827;
    --api-shell-elevated: #131d31;
    --api-field-bg: #050a18;
    --api-line: rgba(148, 163, 184, .16);
    --api-overlay: rgba(2, 6, 23, .82);
}

[data-bs-theme="light"] {
    color-scheme: dark;
    --api-readable-text: #f8fafc;
    --api-readable-muted: #9ca3af;
    --api-readable-accent: color-mix(in srgb, var(--bs-primary) 48%, #f8fafc);
    --api-readable-link: color-mix(in srgb, var(--bs-primary) 58%, #f8fafc);
    --api-page-bg: #020617;
    --api-shell-bg: #0b1020;
    --api-shell-soft: #101827;
    --api-shell-elevated: #131d31;
    --api-field-bg: #050a18;
    --api-line: rgba(148, 163, 184, .16);
}

[data-bs-theme="dark"] {
    --api-readable-accent: color-mix(in srgb, var(--bs-primary) 48%, #f8fafc);
    --api-readable-link: color-mix(in srgb, var(--bs-primary) 58%, #f8fafc);
    --api-page-bg: #020617;
    --api-shell-bg: #0b1020;
    --api-shell-soft: #101827;
    --api-shell-elevated: #131d31;
    --api-field-bg: #050a18;
    --api-line: rgba(148, 163, 184, .16);
}

*, *::before, *::after {
    box-sizing: border-box;
}

html {
    min-height: 100%;
}

body {
    margin: 0;
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(var(--bs-primary-rgb), .16), transparent 34rem),
        var(--api-page-bg);
    color: var(--api-readable-text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 15px;
    letter-spacing: 0;
}

a {
    color: inherit;
}

.api-docs-page {
    min-height: 100vh;
}

.api-topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .9rem clamp(1rem, 3vw, 2rem);
    background: color-mix(in srgb, var(--api-shell-bg) 88%, transparent);
    border-bottom: 1px solid var(--api-line);
    backdrop-filter: blur(18px);
}

.api-brand {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    gap: .75rem;
    text-decoration: none;
}

.api-brand-logo {
    width: 2.65rem;
    height: 2.65rem;
    object-fit: contain;
    border-radius: var(--app-radius);
    background: var(--api-shell-soft);
    border: 1px solid var(--api-line);
    padding: .35rem;
}

.api-brand-mark {
    width: 2.65rem;
    height: 2.65rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    color: var(--app-sidebar-active-color);
    background: var(--app-sidebar-active);
    border-radius: var(--app-radius);
    font-size: 1.25rem;
}

.api-brand-text {
    min-width: 0;
    display: grid;
    gap: .05rem;
}

.api-brand-text strong {
    color: var(--api-readable-text);
    font-size: 1rem;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.api-brand-text span {
    color: var(--api-readable-muted);
    font-size: .78rem;
    line-height: 1.2;
}

.api-actions {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.api-action {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    min-height: 2.35rem;
    padding: .45rem .8rem;
    color: var(--api-readable-text);
    background: var(--api-shell-soft);
    border: 1px solid var(--api-line);
    border-radius: var(--app-radius);
    text-decoration: none;
    font-weight: 700;
    font-size: .86rem;
    transition: var(--app-transition);
}

.api-action i {
    color: var(--api-readable-accent);
}

.api-action:hover {
    color: var(--app-sidebar-active-color);
    background: var(--app-sidebar-active);
    border-color: var(--app-sidebar-active);
}

.api-action:hover i {
    color: inherit;
}

.api-shell {
    width: min(100%, 1480px);
    margin: 0 auto;
    padding: clamp(1rem, 3vw, 2rem);
}

.api-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
    align-items: end;
    padding: clamp(1.15rem, 3vw, 2rem);
    margin-bottom: 1rem;
    color: var(--api-readable-text);
    background: linear-gradient(135deg, color-mix(in srgb, var(--api-shell-bg) 92%, var(--bs-primary) 8%), var(--api-shell-bg));
    border: 1px solid var(--api-line);
    border-radius: var(--app-radius-premium);
    box-shadow: var(--app-shadow-premium);
}

.api-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    width: fit-content;
    margin-bottom: .7rem;
    padding: .35rem .65rem;
    color: var(--api-readable-text);
    background: var(--bs-primary-bg-subtle);
    border: 1px solid var(--bs-primary-border-subtle);
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 800;
}

.api-eyebrow i,
.api-meta-pill i {
    color: var(--api-readable-accent);
}

.api-hero h1 {
    margin: 0;
    color: var(--api-readable-text);
    font-size: clamp(1.65rem, 4vw, 2.65rem);
    line-height: 1.08;
    letter-spacing: 0;
}

.api-hero p {
    max-width: 780px;
    margin: .75rem 0 0;
    color: var(--api-readable-muted);
    font-size: 1rem;
    line-height: 1.65;
}

.api-hero-meta {
    display: grid;
    gap: .6rem;
    justify-items: end;
}

.api-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    min-height: 2.25rem;
    padding: .45rem .7rem;
    color: var(--api-readable-text);
    background: var(--api-shell-soft);
    border: 1px solid var(--api-line);
    border-radius: 999px;
    font-weight: 800;
    font-size: .83rem;
    white-space: nowrap;
}

.swagger-panel {
    overflow: hidden;
    background: var(--api-shell-bg);
    border: 1px solid var(--api-line);
    border-radius: var(--app-radius-premium);
    box-shadow: var(--app-shadow-premium);
}

#swagger-ui {
    min-height: 70vh;
}

.swagger-ui {
    color: var(--api-readable-text);
    font-family: inherit;
}

.swagger-ui a,
.swagger-ui a.nostyle,
.swagger-ui .link {
    color: var(--api-readable-link);
}

.swagger-ui .topbar {
    display: none;
}

.swagger-ui .wrapper {
    max-width: none;
    padding: 0 clamp(1rem, 2.4vw, 2rem);
}

.swagger-ui .info {
    margin: 1.5rem 0 1rem;
}

.swagger-ui .info .title,
.swagger-ui .opblock-tag,
.swagger-ui table thead tr td,
.swagger-ui table thead tr th,
.swagger-ui .response-col_status,
.swagger-ui .parameter__name,
.swagger-ui .model-title,
.swagger-ui .model-title__text,
.swagger-ui .opblock .opblock-summary-path,
.swagger-ui .opblock .opblock-summary-path span,
.swagger-ui .opblock .opblock-summary-path a,
.swagger-ui .opblock .opblock-summary-operation-id,
.swagger-ui .opblock-section-header h4,
.swagger-ui .servers-title,
.swagger-ui .servers > label,
.swagger-ui .scheme-container .schemes > label,
.swagger-ui .tab li button.tablinks,
.swagger-ui .auth-container h4,
.swagger-ui .dialog-ux .modal-ux-header h3,
    .swagger-ui .dialog-ux .modal-ux-content h4 {
    color: var(--api-readable-text);
}

.swagger-ui .info .title small,
.swagger-ui .info p,
.swagger-ui .info li,
.swagger-ui .opblock-summary-description,
.swagger-ui .parameter__type,
.swagger-ui .parameter__deprecated,
.swagger-ui .parameter__in,
.swagger-ui .response-col_description,
.swagger-ui .model,
.swagger-ui .prop-format,
.swagger-ui .renderedMarkdown p,
.swagger-ui .opblock-tag small,
.swagger-ui .opblock-description-wrapper p,
.swagger-ui .opblock-external-docs-wrapper p,
.swagger-ui .responses-table,
.swagger-ui .parameter__extension,
    .swagger-ui .tab li {
    color: var(--api-readable-muted);
}

.swagger-ui .info .title small {
    color: var(--api-readable-text);
    background: var(--app-surface-soft);
}

.swagger-ui .info a,
.swagger-ui .renderedMarkdown a {
    color: var(--api-readable-link);
    font-weight: 700;
}

.swagger-ui .opblock .opblock-summary-path,
.swagger-ui .opblock .opblock-summary-path span,
.swagger-ui .opblock .opblock-summary-path a {
    color: var(--api-readable-text) !important;
}

.swagger-ui .opblock-summary-description,
.swagger-ui .opblock-summary-operation-id,
.swagger-ui .prop-type,
.swagger-ui .model .property,
.swagger-ui .model .property.primitive {
    color: var(--api-readable-muted) !important;
}

.swagger-ui .model .prop-type,
.swagger-ui .model .prop-format {
    color: var(--api-readable-accent) !important;
}

.swagger-ui .scheme-container,
.swagger-ui .opblock,
.swagger-ui section.models,
.swagger-ui .model-box,
.swagger-ui .responses-inner,
.swagger-ui .parameters-container,
.swagger-ui .request-body,
.swagger-ui .execute-wrapper {
    background: var(--app-surface-soft);
    border-color: var(--app-surface-border);
    box-shadow: none;
}

.swagger-ui .opblock,
.swagger-ui section.models {
    border-radius: var(--app-radius);
}

.swagger-ui .opblock .opblock-summary {
    border-color: var(--app-surface-border);
}

.swagger-ui .opblock.opblock-get,
.swagger-ui .opblock.opblock-post,
.swagger-ui .opblock.opblock-put,
.swagger-ui .opblock.opblock-delete,
.swagger-ui .opblock.opblock-patch {
    background: var(--app-surface-bg);
    border-color: var(--app-surface-border);
}

.swagger-ui .opblock .opblock-summary-method {
    border-radius: calc(var(--app-radius) - 2px);
    font-weight: 900;
}

.swagger-ui input,
.swagger-ui textarea,
.swagger-ui select {
    color: var(--api-readable-text);
    background: var(--bs-body-bg);
    border-color: var(--app-surface-border);
    border-radius: var(--app-radius);
}

.swagger-ui .btn,
.swagger-ui .download-url-wrapper .download-url-button {
    border-radius: var(--app-radius);
    font-weight: 800;
}

.swagger-ui .btn.authorize {
    color: var(--api-readable-text);
    background: var(--app-surface-bg);
    border-color: var(--api-readable-accent);
}

.swagger-ui .btn.authorize svg {
    fill: var(--api-readable-accent);
}

.swagger-ui .btn.authorize:hover {
    color: var(--api-readable-text);
    background: var(--bs-primary-bg-subtle);
    border-color: var(--api-readable-accent);
}

.swagger-ui .btn.execute {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
}

.swagger-ui .filter .operation-filter-input {
    background: var(--app-surface-bg);
    border-color: var(--app-surface-border);
}

.swagger-ui .highlight-code,
.swagger-ui .microlight {
    background: #020617;
}

.swagger-ui,
.swagger-ui * {
    font-family: inherit !important;
}

.swagger-ui {
    background: transparent !important;
}

.swagger-ui .info {
    display: none !important;
}

.swagger-ui .wrapper {
    max-width: none !important;
}

.swagger-ui .scheme-container {
    margin: 0 !important;
    padding: 1.55rem clamp(1rem, 2.4vw, 2rem) !important;
    background: var(--api-shell-elevated) !important;
    border: 0 !important;
    border-bottom: 1px solid var(--api-line) !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

.swagger-ui .scheme-container .schemes {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 1rem !important;
    flex-wrap: wrap !important;
}

.swagger-ui .servers,
.swagger-ui .scheme-container .schemes > label {
    color: var(--api-readable-text) !important;
    font-weight: 800 !important;
}

.swagger-ui .servers select,
.swagger-ui .scheme-container select {
    min-height: 2.35rem !important;
    color: var(--api-readable-text) !important;
    background: var(--api-field-bg) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: var(--app-radius) !important;
}

.swagger-ui .filter {
    padding: 1.5rem clamp(1rem, 2.4vw, 2rem) .5rem !important;
}

.swagger-ui .filter .operation-filter-input {
    width: 100% !important;
    min-height: 2.75rem !important;
    padding: .65rem .85rem !important;
    color: var(--api-readable-text) !important;
    background: var(--api-field-bg) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: var(--app-radius) !important;
    box-shadow: none !important;
}

.swagger-ui .filter .operation-filter-input::placeholder,
.swagger-ui input::placeholder,
.swagger-ui textarea::placeholder {
    color: color-mix(in srgb, var(--api-readable-muted) 78%, transparent) !important;
}

.swagger-ui .opblock-tag-section {
    padding: 0 clamp(1rem, 2.4vw, 2rem) !important;
}

.swagger-ui .opblock-tag {
    margin: 1.55rem 0 .7rem !important;
    padding: 0 0 .8rem !important;
    border-bottom: 1px solid var(--api-line) !important;
    color: var(--api-readable-accent) !important;
    font-size: 1.35rem !important;
    font-weight: 900 !important;
}

.swagger-ui .opblock-tag small {
    color: var(--api-readable-muted) !important;
    font-size: .86rem !important;
    font-weight: 500 !important;
}

.swagger-ui .opblock-tag svg,
.swagger-ui .expand-operation svg,
.swagger-ui .opblock-control-arrow svg,
.swagger-ui .authorization__btn svg,
.swagger-ui .btn svg {
    color: var(--api-readable-muted) !important;
    fill: currentColor !important;
}

.swagger-ui .opblock-tag:hover svg,
.swagger-ui .opblock-summary:hover .opblock-control-arrow svg,
.swagger-ui .authorization__btn:hover svg {
    color: var(--api-readable-accent) !important;
}

.swagger-ui .opblock {
    margin: 0 0 .65rem !important;
    overflow: hidden !important;
    background: var(--api-shell-soft) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: 10px !important;
    box-shadow: none !important;
}

.swagger-ui .opblock .opblock-summary {
    min-height: 3.25rem !important;
    padding: .55rem .7rem !important;
    background: color-mix(in srgb, var(--api-shell-bg) 72%, var(--api-shell-soft) 28%) !important;
    border: 0 !important;
    border-bottom: 1px solid transparent !important;
}

.swagger-ui .opblock.is-open .opblock-summary {
    border-bottom-color: var(--api-line) !important;
}

.swagger-ui .opblock .opblock-summary-method {
    min-width: 5rem !important;
    min-height: 2rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 7px !important;
    color: #fff !important;
    font-size: .82rem !important;
    font-weight: 900 !important;
    letter-spacing: 0 !important;
    text-shadow: none !important;
}

.swagger-ui .opblock.opblock-get .opblock-summary-method {
    background: #3b82f6 !important;
}

.swagger-ui .opblock.opblock-post .opblock-summary-method {
    background: #22c55e !important;
}

.swagger-ui .opblock.opblock-put .opblock-summary-method {
    background: #f59e0b !important;
}

.swagger-ui .opblock.opblock-delete .opblock-summary-method {
    background: #ef4444 !important;
}

.swagger-ui .opblock .opblock-summary-path,
.swagger-ui .opblock .opblock-summary-path span,
.swagger-ui .opblock .opblock-summary-path a {
    color: var(--api-readable-text) !important;
    font-size: .95rem !important;
    font-weight: 900 !important;
}

.swagger-ui .opblock .opblock-summary-description {
    color: var(--api-readable-muted) !important;
    font-size: .87rem !important;
}

.swagger-ui .authorization__btn {
    color: var(--api-readable-muted) !important;
    background: transparent !important;
    border: 0 !important;
}

.swagger-ui .opblock-section-header,
.swagger-ui .responses-inner h4,
.swagger-ui .responses-wrapper .opblock-section-header,
.swagger-ui .request-body .opblock-section-header {
    min-height: 3rem !important;
    padding: .8rem 1.15rem !important;
    color: var(--api-readable-text) !important;
    background: var(--api-shell-elevated) !important;
    border: 0 !important;
    border-bottom: 1px solid var(--api-line) !important;
    box-shadow: none !important;
}

.swagger-ui .opblock-section-header h4,
.swagger-ui .responses-inner h4,
.swagger-ui .tab li button.tablinks {
    color: var(--api-readable-text) !important;
    font-weight: 900 !important;
}

.swagger-ui .opblock-description-wrapper,
.swagger-ui .opblock-external-docs-wrapper,
.swagger-ui .opblock-title_normal,
.swagger-ui .parameters-container,
.swagger-ui .responses-wrapper,
.swagger-ui .request-body,
.swagger-ui .execute-wrapper,
.swagger-ui .model-example,
.swagger-ui .model-box,
.swagger-ui section.models {
    color: var(--api-readable-text) !important;
    background: var(--api-shell-soft) !important;
    border-color: var(--api-line) !important;
}

.swagger-ui table,
.swagger-ui thead,
.swagger-ui tbody,
.swagger-ui tr,
.swagger-ui td,
.swagger-ui th {
    color: var(--api-readable-text) !important;
    background: transparent !important;
    border-color: var(--api-line) !important;
}

.swagger-ui .parameters-col_name,
.swagger-ui .parameters-col_description,
.swagger-ui .response-col_status,
.swagger-ui .response-col_description,
.swagger-ui .response-col_links {
    color: var(--api-readable-text) !important;
}

.swagger-ui .parameter__name,
.swagger-ui .parameter__type,
.swagger-ui .parameter__in,
.swagger-ui .parameter__deprecated,
.swagger-ui .response-col_description__inner,
.swagger-ui .renderedMarkdown,
.swagger-ui .renderedMarkdown p,
.swagger-ui .renderedMarkdown code {
    color: var(--api-readable-text) !important;
}

.swagger-ui input,
.swagger-ui textarea,
.swagger-ui select {
    color: var(--api-readable-text) !important;
    background: var(--api-field-bg) !important;
    border: 1px solid var(--api-line) !important;
    box-shadow: none !important;
}

.swagger-ui textarea,
.swagger-ui .body-param__text {
    min-height: 8rem !important;
    border-radius: var(--app-radius) !important;
}

.swagger-ui .btn,
.swagger-ui .btn.cancel,
.swagger-ui .btn.try-out__btn,
.swagger-ui .download-url-wrapper .download-url-button {
    min-height: 2.25rem !important;
    color: var(--api-readable-text) !important;
    background: var(--api-shell-bg) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: var(--app-radius) !important;
    box-shadow: none !important;
    font-weight: 900 !important;
}

.swagger-ui .btn:hover,
.swagger-ui .btn.cancel:hover,
.swagger-ui .btn.try-out__btn:hover {
    border-color: var(--api-readable-accent) !important;
}

.swagger-ui .btn.execute {
    width: calc(100% - 2rem) !important;
    margin: 1rem !important;
    color: #fff !important;
    background: #22c99a !important;
    border-color: #22c99a !important;
}

.swagger-ui .btn.authorize {
    color: var(--api-readable-text) !important;
    background: var(--api-field-bg) !important;
    border: 1px solid var(--api-readable-accent) !important;
}

.swagger-ui .btn.authorize svg,
.swagger-ui .btn.authorize svg path {
    color: var(--api-readable-accent) !important;
    fill: currentColor !important;
}

.swagger-ui .highlight-code,
.swagger-ui .microlight,
.swagger-ui .curl-command,
.swagger-ui .request-url,
.swagger-ui .responses-table .response-col_description pre {
    color: #dbeafe !important;
    background: #020617 !important;
    border: 1px solid rgba(148, 163, 184, .18) !important;
    border-radius: var(--app-radius) !important;
}

.swagger-ui .responses-inner {
    padding: 0 1rem 1rem !important;
    background: var(--api-shell-soft) !important;
}

body .dialog-ux,
.swagger-ui .dialog-ux {
    background: var(--api-overlay) !important;
    backdrop-filter: blur(12px);
}

body .dialog-ux .modal-ux,
.swagger-ui .dialog-ux .modal-ux {
    width: min(680px, calc(100vw - 2rem)) !important;
    color: var(--api-readable-text) !important;
    background: var(--api-shell-bg) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: var(--app-radius-premium) !important;
    box-shadow: 0 24px 80px rgba(0, 0, 0, .45) !important;
}

body .dialog-ux .modal-ux-header,
.swagger-ui .dialog-ux .modal-ux-header {
    min-height: 4rem !important;
    padding: 1rem 1.25rem !important;
    color: var(--api-readable-text) !important;
    background: var(--api-shell-elevated) !important;
    border-bottom: 1px solid var(--api-line) !important;
}

body .dialog-ux .modal-ux-header h3,
.swagger-ui .dialog-ux .modal-ux-header h3 {
    color: var(--api-readable-text) !important;
    font-size: 1.05rem !important;
    font-weight: 900 !important;
}

body .dialog-ux .modal-ux-content,
.swagger-ui .dialog-ux .modal-ux-content {
    color: var(--api-readable-text) !important;
    background: var(--api-shell-bg) !important;
}

body .dialog-ux .modal-ux-content h4,
.swagger-ui .dialog-ux .modal-ux-content h4,
body .dialog-ux .modal-ux-content label,
.swagger-ui .dialog-ux .modal-ux-content label,
body .dialog-ux .modal-ux-content p,
.swagger-ui .dialog-ux .modal-ux-content p,
body .dialog-ux .modal-ux-content code,
.swagger-ui .dialog-ux .modal-ux-content code {
    color: var(--api-readable-text) !important;
}

body .dialog-ux .modal-ux .close-modal,
.swagger-ui .dialog-ux .modal-ux .close-modal {
    color: var(--api-readable-text) !important;
    opacity: 1 !important;
}

body .dialog-ux .modal-ux .close-modal svg,
body .dialog-ux .modal-ux .close-modal svg path,
.swagger-ui .dialog-ux .modal-ux .close-modal svg,
.swagger-ui .dialog-ux .modal-ux .close-modal svg path {
    color: var(--api-readable-text) !important;
    fill: currentColor !important;
}

body .dialog-ux .auth-container,
.swagger-ui .dialog-ux .auth-container {
    margin: 0 !important;
    padding: 1.2rem !important;
    background: var(--api-shell-soft) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: var(--app-radius) !important;
}

body .dialog-ux .auth-btn-wrapper,
.swagger-ui .dialog-ux .auth-btn-wrapper {
    display: flex !important;
    justify-content: flex-end !important;
    gap: .75rem !important;
    padding-top: 1rem !important;
}

body .dialog-ux .auth-btn-wrapper .btn,
.swagger-ui .dialog-ux .auth-btn-wrapper .btn {
    min-width: 7.5rem !important;
}

body .dialog-ux .auth-btn-wrapper .authorize,
.swagger-ui .dialog-ux .auth-btn-wrapper .authorize,
body .dialog-ux .auth-btn-wrapper .btn-done,
.swagger-ui .dialog-ux .auth-btn-wrapper .btn-done {
    color: #fff !important;
    background: var(--bs-primary) !important;
    border-color: var(--bs-primary) !important;
}

body .dialog-ux input,
body .dialog-ux textarea,
body .dialog-ux select,
.swagger-ui .dialog-ux input,
.swagger-ui .dialog-ux textarea,
.swagger-ui .dialog-ux select {
    color: var(--api-readable-text) !important;
    background: var(--api-field-bg) !important;
    border: 1px solid var(--api-line) !important;
    border-radius: var(--app-radius) !important;
}

@media (max-width: 760px) {
    .api-topbar,
    .api-hero {
        align-items: stretch;
        grid-template-columns: 1fr;
    }

    .api-topbar {
        position: static;
        flex-direction: column;
    }

    .api-actions {
        width: 100%;
        justify-content: stretch;
    }

    .api-action {
        flex: 1 1 0;
        justify-content: center;
    }

    .api-hero-meta {
        justify-items: stretch;
    }

    .api-meta-pill {
        justify-content: center;
        white-space: normal;
    }
}
</style>
</head>
<body>
<div class="api-docs-page">
    <header class="api-topbar">
        <a class="api-brand" href="{{ url('/') }}" aria-label="{{ $appName }}">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="api-brand-logo">
            @else
                <span class="api-brand-mark" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
            @endif
            <span class="api-brand-text">
                <strong>{{ $appName }}</strong>
                <span>Documentacao oficial da API</span>
            </span>
        </a>

        <nav class="api-actions" aria-label="Acoes da documentacao">
            <a class="api-action" href="{{ url('/admin') }}">
                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                <span>Painel</span>
            </a>
            <a class="api-action" href="{{ $openApiUrl }}">
                <i class="bi bi-braces" aria-hidden="true"></i>
                <span>OpenAPI JSON</span>
            </a>
        </nav>
    </header>

    <main class="api-shell">
        <section class="api-hero" aria-labelledby="api-docs-title">
            <div>
                <span class="api-eyebrow"><i class="bi bi-code-slash" aria-hidden="true"></i> OpenAPI 3.0</span>
                <h1 id="api-docs-title">API publica {{ $appName }}</h1>
                <p>Consulte endpoints, contratos e autenticacao Bearer Token em uma interface sincronizada com o nome, logo e cores configurados no painel.</p>
            </div>
            <div class="api-hero-meta" aria-label="Resumo da API">
                <span class="api-meta-pill"><i class="bi bi-shield-check" aria-hidden="true"></i> Bearer Token Sanctum</span>
                <span class="api-meta-pill"><i class="bi bi-diagram-3" aria-hidden="true"></i> v1.0.0</span>
            </div>
        </section>

        <section class="swagger-panel" aria-label="Documentacao interativa">
            <div id="swagger-ui" data-openapi-url="{{ $openApiUrl }}"></div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js"></script>
<script>
window.addEventListener('load', function () {
    const target = document.getElementById('swagger-ui');

    SwaggerUIBundle({
        url: target.dataset.openapiUrl,
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset.slice(1),
        ],
        plugins: [SwaggerUIBundle.plugins.DownloadUrl],
        layout: 'StandaloneLayout',
        tryItOutEnabled: true,
        persistAuthorization: true,
        displayRequestDuration: true,
        filter: true,
        defaultModelsExpandDepth: 1,
        docExpansion: 'list',
    });
});
</script>
</body>
</html>
