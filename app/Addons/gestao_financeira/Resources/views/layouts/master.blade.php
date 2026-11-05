@php
    // Modo assistido: superadmin supervisiona tenant — sempre usa layout de tenant
    $isAssistMode = session('admin_assist_mode');
    $isTenantRoute = request()->routeIs('tenant.*') || request()->is('painel/*');
    $isAdmin = Auth::guard('admin')->check() && ! $isTenantRoute && ! $isAssistMode;
    $routePrefix  = $isAdmin ? 'admin.gestao-financeira.' : 'tenant.gestao-financeira.';
    $baseUrl      = $isAdmin ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends($isAdmin ? 'admin.layouts.app' : 'tenancy::tenant.layout')

@push('head')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@push('styles')
<style>
    .finance-addon-shell {
        color: var(--bs-body-color);
    }

    .finance-addon-shell .finance-addon-nav {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
    }

    .finance-addon-shell .finance-addon-nav .nav-link {
        border: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
        color: var(--bs-body-color);
        border-radius: .85rem;
        padding: .8rem 1rem;
        font-size: .875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        transition: .2s ease;
    }

    .finance-addon-shell .finance-addon-nav .nav-link:hover {
        background: color-mix(in srgb, var(--bs-primary) 10%, var(--bs-tertiary-bg));
        border-color: color-mix(in srgb, var(--bs-primary) 40%, var(--bs-border-color));
        color: var(--bs-body-color);
    }

    .finance-addon-shell .finance-addon-nav .nav-link.active {
        background: var(--bs-primary);
        border-color: var(--bs-primary);
        color: #fff;
        box-shadow: 0 .75rem 1.5rem color-mix(in srgb, var(--bs-primary) 22%, transparent);
    }

    .finance-addon-shell .finance-addon-chip {
        border: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
        color: var(--bs-secondary-color);
        border-radius: 999px;
        padding: .35rem .75rem;
        font-size: .75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }

    .finance-addon-shell .finance-addon-kpi {
        border: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
        border-radius: 1rem;
        padding: 1.25rem;
        height: 100%;
    }

    .finance-addon-shell .finance-addon-kpi__label {
        color: var(--bs-secondary-color);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .finance-addon-shell .finance-addon-kpi__value {
        display: block;
        margin-top: .5rem;
        font-size: 1.75rem;
        line-height: 1.1;
        font-weight: 700;
    }

    .finance-addon-shell .finance-addon-kpi__meta {
        margin-top: .5rem;
        color: var(--bs-secondary-color);
        font-size: .8rem;
    }

    .finance-addon-shell .finance-addon-form .form-control,
    .finance-addon-shell .finance-addon-form .form-select {
        min-height: 48px;
    }

    .finance-addon-shell .finance-addon-form textarea.form-control {
        min-height: 120px;
    }

    .finance-addon-shell .finance-addon-dropzone {
        position: relative;
        border: 1px dashed var(--bs-border-color);
        background: var(--bs-tertiary-bg);
        border-radius: 1rem;
        padding: 1.5rem;
        text-align: center;
    }

    .finance-addon-shell .finance-addon-dropzone input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }

    .finance-addon-shell .finance-addon-table .table > :not(caption) > * > * {
        background: transparent;
        color: inherit;
        border-bottom-color: var(--bs-border-color);
        vertical-align: middle;
    }

    .finance-addon-shell .finance-addon-table .table thead th {
        color: var(--bs-secondary-color);
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .finance-addon-shell .finance-addon-muted {
        color: var(--bs-secondary-color);
    }

    .finance-addon-shell .finance-addon-chart {
        position: relative;
        min-height: 320px;
    }

    .finance-addon-shell .finance-addon-section-note {
        font-size: .8rem;
        color: var(--bs-secondary-color);
    }
</style>
@endpush

@section('content')
    @php
        $pageTitleResolved = $__env->yieldContent('page_title', 'Painel financeiro do módulo');
    @endphp
    <div class="container-fluid finance-addon-shell">
        <x-ui.page-header
            title="Gestão Financeira"
            :subtitle="$pageTitleResolved"
            eyebrow="Addon financeiro"
        >
            <x-slot:actions>
                <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                    @if(! $isAdmin)
                        @yield('page_actions')
                    @endif
                    <div class="d-none d-lg-flex flex-wrap gap-2">
                        <span class="finance-addon-chip"><i class="bi bi-wallet2"></i> KDK Finance</span>
                        <span class="finance-addon-chip"><i class="bi bi-layout-sidebar-inset"></i> {{ $isAdmin ? 'Integrado ao Admin' : 'Painel do Cliente' }}</span>
                    </div>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.premium-card class="mb-4">
            <nav class="finance-addon-nav">
                <a href="{{ $baseUrl }}" class="nav-link {{ request()->is(ltrim($baseUrl, '/')) ? 'active' : '' }}">
                    <i class="bi bi-grid"></i> Dashboard
                </a>
                <a href="{{ $baseUrl }}/transacoes" class="nav-link {{ request()->is(ltrim($baseUrl, '/').'/transacoes*') ? 'active' : '' }}">
                    <i class="bi bi-arrow-down-up"></i> Lançamentos
                </a>
                <a href="{{ $baseUrl }}/categorias" class="nav-link {{ request()->is(ltrim($baseUrl, '/').'/categorias*') ? 'active' : '' }}">
                    <i class="bi bi-tags"></i> Categorias
                </a>
                <a href="{{ $baseUrl }}/contas" class="nav-link {{ request()->is(ltrim($baseUrl, '/').'/contas*') ? 'active' : '' }}">
                    <i class="bi bi-bank"></i> Contas
                </a>
                <a href="{{ $baseUrl }}/relatorios" class="nav-link {{ request()->is(ltrim($baseUrl, '/').'/relatorios*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-bar-graph"></i> Relatórios
                </a>
            </nav>
        </x-ui.premium-card>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        @endif

        @yield('addon_content')
    </div>

    @yield('scripts')
@endsection
