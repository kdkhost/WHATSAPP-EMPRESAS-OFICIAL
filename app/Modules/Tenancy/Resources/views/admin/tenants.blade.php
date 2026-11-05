@extends('admin.layouts.app')
@section('title', 'Gestão e Governança de Clientes SaaS (Tenants)')

@push('head')
<style>
/* ==========================================================================
   TENANCY MANAGEMENT PAGE - PREMIUM STYLES
   ========================================================================== */
.tenants-management-page {
    --tenant-card-bg: var(--bs-body-bg);
    --tenant-border: var(--bs-border-color);
    --tenant-surface-soft: color-mix(in srgb, var(--bs-body-bg) 94%, var(--bs-primary) 6%);
}

/* KPI Cards com micro-interações */
.tenant-kpi-card {
    border: 1px solid var(--tenant-border);
    border-radius: 18px;
    background: var(--tenant-card-bg);
    padding: 1.25rem;
    position: relative;
    overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
}
.tenant-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
    border-color: color-mix(in srgb, var(--bs-primary) 40%, var(--tenant-border));
}
.tenant-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.85;
}
.tenant-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    flex-shrink: 0;
}

/* Filter Pills */
.tenant-filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
}
.tenant-pill-btn {
    border: 1px solid var(--tenant-border);
    border-radius: 50rem;
    background: var(--tenant-card-bg);
    color: var(--bs-secondary-color);
    padding: .45rem 1rem;
    font-size: .84rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    text-decoration: none;
    transition: all .18s ease;
}
.tenant-pill-btn:hover {
    color: var(--bs-body-color);
    border-color: var(--bs-primary);
    background: var(--tenant-surface-soft);
}
.tenant-pill-btn.active {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff !important;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(var(--bs-primary-rgb), 0.35);
}
.tenant-pill-btn .badge {
    font-size: .72rem;
    font-weight: 600;
    border-radius: 50rem;
    padding: .25em .6em;
}

/* Table Card */
.tenant-table-wrapper {
    background: var(--tenant-card-bg);
    border: 1px solid var(--tenant-border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}
.tenant-table {
    margin-bottom: 0;
}
.tenant-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .9rem 1rem;
    border-bottom: 1px solid var(--tenant-border);
    white-space: nowrap;
}
.tenant-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid color-mix(in srgb, var(--tenant-border) 70%, transparent);
    font-size: .88rem;
}
.tenant-table tbody tr:hover td {
    background: var(--tenant-surface-soft);
}

/* Company Avatar */
.tenant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 80%, #000), color-mix(in srgb, var(--bs-primary) 40%, #000));
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    flex-shrink: 0;
}

/* Toggle Switch Rápido */
.tenant-status-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}
.tenant-status-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.tenant-status-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 24px;
}
.tenant-status-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
input:checked + .tenant-status-slider {
    background-color: #10b981;
}
input:checked + .tenant-status-slider:before {
    transform: translateX(20px);
}
[data-bs-theme="dark"] .tenant-status-slider {
    background-color: #475569;
}
[data-bs-theme="dark"] input:checked + .tenant-status-slider {
    background-color: #059669;
}

/* Linha com destaque quando não-brasileiro */
.row-non-brazilian {
    background-color: rgba(239, 68, 68, 0.035);
}
</style>
@endpush

@section('content')
<div class="container-fluid tenants-management-page py-3">

    {{-- CABEÇALHO DA PÁGINA --}}
    <x-ui.page-header
        title="Gestão de Tenants & Governança SaaS"
        subtitle="Controle operacional de assinaturas, ativação instantânea, auditoria de conformidade com o software brasileiro e expurgo total de contas."
        eyebrow="Administração e Multi-Tenancy"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('admin.tenancy.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
                    <i class="bi bi-layers me-1"></i> Planos & Preços
                </a>

                @if(($nonBrazilianCount ?? 0) > 0)
                    <button type="button" class="btn btn-danger d-inline-flex align-items-center" id="btnPurgeNonBrazilians">
                        <i class="bi bi-shield-x me-1"></i> Remover Não-Brasileiros ({{ $nonBrazilianCount }})
                    </button>
                @endif

                <button type="button" class="btn btn-primary d-inline-flex align-items-center" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                </button>
            </div>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total Registrados</span>
                <span class="dashboard-note__value">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Ativos</span>
                <span class="dashboard-note__value text-success font-monospace">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Não-Brasileiros</span>
                <span class="dashboard-note__value {{ ($stats['non_brazilians'] ?? 0) > 0 ? 'text-danger' : 'text-success' }} font-monospace">
                    {{ $stats['non_brazilians'] ?? 0 }}
                </span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    {{-- 4 KPIS MODERNOS --}}
    <div class="row g-3 mb-4">
        {{-- Total --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="tenant-kpi-card text-primary">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">Total de Tenants</span>
                    <div class="tenant-kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-buildings"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                        Empresas SaaS
                    </span>
                </div>
                <div class="text-muted small">Clientes cadastrados na plataforma.</div>
            </div>
        </div>

        {{-- Ativos --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="tenant-kpi-card text-success">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">Tenants Ativos</span>
                    <div class="tenant-kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['active'] ?? 0, 0, ',', '.') }}</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                        Operando normalmente
                    </span>
                </div>
                <div class="text-muted small">Contas ativas e períodos de teste vigentes.</div>
            </div>
        </div>

        {{-- Suspensos --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="tenant-kpi-card text-warning">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">Tenants Suspensos</span>
                    <div class="tenant-kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['suspended'] ?? 0, 0, ',', '.') }}</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">
                        Acesso bloqueado
                    </span>
                </div>
                <div class="text-muted small">Empresas com operação suspensa no painel.</div>
            </div>
        </div>

        {{-- Não-Brasileiros / Conformidade --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="tenant-kpi-card {{ ($stats['non_brazilians'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary small">Não-Brasileiros</span>
                    <div class="tenant-kpi-icon {{ ($stats['non_brazilians'] ?? 0) > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                        <i class="bi {{ ($stats['non_brazilians'] ?? 0) > 0 ? 'bi-globe-americas' : 'bi-shield-check' }}"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <span class="fs-2 fw-bold text-body">{{ number_format($stats['non_brazilians'] ?? 0, 0, ',', '.') }}</span>
                    @if(($stats['non_brazilians'] ?? 0) > 0)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                            Fora de conformidade
                        </span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                            100% Brasil
                        </span>
                    @endif
                </div>
                <div class="text-muted small">
                    {{ ($stats['non_brazilians'] ?? 0) > 0 ? 'Usuários com telefones/docs estrangeiros.' : 'Todos os tenants seguem a regulação nacional.' }}
                </div>
            </div>
        </div>
    </div>

    {{-- BARRA DE FILTROS E ABAS RÁPIDAS --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            
            {{-- Abas Rápidas (Pills) --}}
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="tenant-filter-pills">
                    @php
                        $curStatus = request('status');
                        $curFilter = request('filter');
                    @endphp

                    <a href="{{ route('admin.tenancy.tenants', array_merge(request()->except(['status', 'filter', 'page']))) }}"
                       class="tenant-pill-btn {{ empty($curStatus) && empty($curFilter) ? 'active' : '' }}">
                        <i class="bi bi-grid-fill"></i>
                        <span>Todos os tenants</span>
                        <span class="badge {{ empty($curStatus) && empty($curFilter) ? 'bg-white text-dark' : 'bg-secondary' }}">
                            {{ $stats['total'] }}
                        </span>
                    </a>

                    <a href="{{ route('admin.tenancy.tenants', array_merge(request()->except(['page', 'filter']), ['status' => 'active'])) }}"
                       class="tenant-pill-btn {{ $curStatus === 'active' ? 'active' : '' }}">
                        <i class="bi bi-check-circle text-success"></i>
                        <span>Ativos</span>
                        <span class="badge {{ $curStatus === 'active' ? 'bg-white text-dark' : 'bg-success-subtle text-success' }}">
                            {{ $stats['active'] }}
                        </span>
                    </a>

                    <a href="{{ route('admin.tenancy.tenants', array_merge(request()->except(['page', 'filter']), ['status' => 'suspended'])) }}"
                       class="tenant-pill-btn {{ $curStatus === 'suspended' ? 'active' : '' }}">
                        <i class="bi bi-pause-circle text-warning"></i>
                        <span>Suspensos</span>
                        <span class="badge {{ $curStatus === 'suspended' ? 'bg-white text-dark' : 'bg-warning-subtle text-warning' }}">
                            {{ $stats['suspended'] }}
                        </span>
                    </a>

                    <a href="{{ route('admin.tenancy.tenants', array_merge(request()->except(['page', 'status']), ['filter' => 'non_brazilian'])) }}"
                       class="tenant-pill-btn {{ $curFilter === 'non_brazilian' ? 'active' : '' }}">
                        <i class="bi bi-globe text-danger"></i>
                        <span>Não-Brasileiros</span>
                        <span class="badge {{ $curFilter === 'non_brazilian' ? 'bg-white text-dark' : 'bg-danger-subtle text-danger' }}">
                            {{ $stats['non_brazilians'] }}
                        </span>
                    </a>
                </div>

                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Mostrando <strong>{{ $tenants->firstItem() ?? 0 }}-{{ $tenants->lastItem() ?? 0 }}</strong> de <strong>{{ $tenants->total() }}</strong> empresas
                </div>
            </div>

            {{-- Formulário de Busca --}}
            <form method="GET" action="{{ route('admin.tenancy.tenants') }}" class="row g-2 align-items-end" id="filterTenantsForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif

                <div class="col-12 col-md-8 col-lg-6">
                    <label class="form-label small fw-semibold text-secondary mb-1">Buscar Empresa ou Usuário</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" name="q" class="form-control border-start-0" placeholder="Nome da empresa, slug, responsável, e-mail, telefone..." value="{{ request('q') }}">
                    </div>
                </div>

                <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                    @if(request()->hasAny(['q', 'status', 'filter']))
                        <a href="{{ route('admin.tenancy.tenants') }}" class="btn btn-outline-secondary w-50" title="Limpar busca">
                            <i class="bi bi-x-lg me-1"></i> Limpar
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary {{ request()->hasAny(['q', 'status', 'filter']) ? 'w-50' : 'w-100' }}">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- TABELA DE TENANTS --}}
    <div class="tenant-table-wrapper mb-4">
        <div>
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-tenants" style="width: 100% !important;" data-export-title="Tenants e Assinantes">
                <thead class="table-light">
                    <tr>
                        <th class="all" style="width: 70px;">ID</th>
                        <th class="all">Empresa & Responsável</th>
                        <th class="desktop">Contato & Documento</th>
                        <th class="desktop">Regulação / País</th>
                        <th class="min-tablet">Plano SaaS</th>
                        <th class="min-tablet text-center">Ativar / Desativar</th>
                        <th class="all text-end no-export">Ações Operacionais</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        @php
                            $isBrazilian = $tenant->is_brazilian ?? true;
                            $isActive = in_array($tenant->status, ['active', 'trial']);
                            $initial = mb_strtoupper(mb_substr($tenant->company_name ?? $tenant->name ?? 'T', 0, 1));
                            $user = $tenant->user;
                            $avatarUrl = $tenant->avatar_url;
                            $hasConnected = $tenant->has_connected_whats_app;
                        @endphp
                        <tr id="tenant-row-{{ $tenant->id }}" class="{{ ! $isBrazilian ? 'row-non-brazilian' : '' }}">
                            
                            {{-- 1. ID --}}
                            <td>
                                <span class="badge bg-body-secondary text-body font-monospace border">
                                    #{{ $tenant->id }}
                                </span>
                            </td>

                            {{-- 2. Empresa & Responsável --}}
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="tenant-avatar position-relative" style="{{ $avatarUrl ? 'background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12);' : '' }}">
                                        @if($avatarUrl)
                                            <img src="{{ $avatarUrl }}" alt="{{ $tenant->company_name ?? $tenant->name ?? '-' }}" referrerpolicy="no-referrer" style="width: 40px; height: 40px; object-fit: cover; border-radius: 12px; display: block;" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                                            <span class="d-none">{{ $initial }}</span>
                                        @else
                                            <span>{{ $initial }}</span>
                                        @endif

                                        @if($hasConnected)
                                            <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-dark rounded-circle" style="width: 12px; height: 12px; z-index: 2; transform: translate(15%, 15%);" title="WhatsApp Conectado"></span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-bold text-body">
                                            <a href="{{ route('admin.tenancy.tenants.show', $tenant->id) }}" class="text-decoration-none text-body">
                                                {{ $tenant->company_name ?? $tenant->name ?? '-' }}
                                            </a>
                                        </div>
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <i class="bi bi-link-45deg"></i>
                                            <span>{{ $tenant->slug }}</span>
                                        </div>
                                        @if($user)
                                            <div class="small text-muted" style="font-size: .75rem;">
                                                <i class="bi bi-person me-1"></i>{{ $user->name }} &bull; {{ $user->email }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- 3. Contato & Documento --}}
                            <td>
                                @if($user && $user->phone)
                                    <div class="fw-semibold text-body small d-flex align-items-center gap-1">
                                        <i class="bi bi-whatsapp text-success"></i>
                                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $user->phone) }}" target="_blank" class="text-decoration-none text-body" title="Abrir WhatsApp">
                                            {{ \App\Support\Format::phone($user->phone) }}
                                        </a>
                                    </div>
                                @else
                                    <div class="text-muted small">Sem telefone</div>
                                @endif

                                <div class="small text-muted font-monospace mt-1" style="font-size: .75rem;">
                                    Doc: {{ $user->document ? \App\Support\Format::document($user->document) : ($tenant->document ?? 'Não informado') }}
                                </div>
                            </td>

                            {{-- 4. Regulação / País --}}
                            <td>
                                @if($isBrazilian)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i> Brasil (+55)
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" title="Usuário não segue a regulamentação brasileira">
                                        <i class="bi bi-globe-americas me-1"></i> Não-Brasileiro
                                    </span>
                                @endif
                            </td>

                            {{-- 5. Plano SaaS --}}
                            <td>
                                <div class="fw-bold small text-body">
                                    {{ $tenant->plan->name ?? 'Sem Plano' }}
                                </div>
                                <div class="text-muted small" style="font-size: .75rem;">
                                    Criado {{ $tenant->created_at?->format('d/m/Y') ?? '-' }}
                                </div>
                            </td>

                            {{-- 6. Ativar / Desativar (Toggle Rápido AJAX) --}}
                            <td class="text-center">
                                <div class="d-inline-flex flex-column align-items-center gap-1">
                                    <label class="tenant-status-switch" title="Clique para ativar ou suspender o tenant">
                                        <input type="checkbox" 
                                               class="js-toggle-tenant-status"
                                               data-id="{{ $tenant->id }}"
                                               data-name="{{ $tenant->company_name ?? 'Tenant #' . $tenant->id }}"
                                               {{ $isActive ? 'checked' : '' }}>
                                        <span class="tenant-status-slider"></span>
                                    </label>
                                    <span class="badge rounded-pill text-bg-{{ $isActive ? 'success' : 'danger' }} status-badge-{{ $tenant->id }}" style="font-size: .68rem;">
                                        {{ $isActive ? 'Ativo' : 'Suspenso' }}
                                    </span>
                                </div>
                            </td>

                            {{-- 7. Ações Operacionais --}}
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 flex-wrap justify-content-end align-items-center">
                                    {{-- Detalhes --}}
                                    <a href="{{ route('admin.tenancy.tenants.show', $tenant->id) }}" class="btn btn-sm btn-outline-primary" title="Ver detalhes do tenant">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    {{-- Editar --}}
                                    <a href="{{ route('admin.tenancy.tenants.edit', $tenant->id) }}" class="btn btn-sm btn-outline-secondary" title="Editar tenant">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    {{-- Login Supervisionado --}}
                                    @if($tenant->user_id)
                                        <form method="POST" action="{{ route('admin.tenancy.tenants.assist.start', $tenant->id) }}" class="d-inline js-assist-form">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" title="Entrar no painel do cliente em modo supervisionado">
                                                <i class="bi bi-person-check me-1"></i> Acessar
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Deletar Conta & Expurgo Completo --}}
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger btn-delete-tenant"
                                            data-id="{{ $tenant->id }}"
                                            data-name="{{ $tenant->company_name ?? 'Tenant #' . $tenant->id }}"
                                            title="Deletar conta e expurgar dados vinculados">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center">
                                <div class="py-4">
                                    <div class="display-6 text-muted mb-3">
                                        <i class="bi bi-building-x"></i>
                                    </div>
                                    <h5 class="fw-bold text-body">Nenhum cliente SaaS encontrado</h5>
                                    <p class="text-muted small mb-3">
                                        Nenhum tenant corresponde aos filtros atuais.
                                    </p>
                                    @if(request()->hasAny(['q', 'status', 'filter']))
                                        <a href="{{ route('admin.tenancy.tenants') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar filtros ativos
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        @if($tenants->hasPages())
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Página {{ $tenants->currentPage() }} de {{ $tenants->lastPage() }}
                </div>
                <div>
                    {{ $tenants->links() }}
                </div>
            </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Helper Toastify seguro
    function showToast(message, isSuccess = true) {
        if (typeof Toastify === 'function') {
            Toastify({
                text: message,
                duration: 3500,
                gravity: "top",
                position: "right",
                style: {
                    background: isSuccess 
                        ? "linear-gradient(to right, #00b09b, #96c93d)" 
                        : "linear-gradient(to right, #ff5f6d, #ffc371)",
                    borderRadius: "10px",
                    boxShadow: "0 4px 12px rgba(0,0,0,0.15)"
                }
            }).showToast();
        }
    }

    // 1. Toggle Rápido Ativar / Desativar Usuário/Tenant
    document.querySelectorAll('.js-toggle-tenant-status').forEach(function (toggle) {
        toggle.addEventListener('change', async function () {
            const tenantId = this.getAttribute('data-id');
            const tenantName = this.getAttribute('data-name');
            const willActivate = this.checked;

            const confirmResult = await Swal.fire({
                icon: willActivate ? 'question' : 'warning',
                title: willActivate ? 'Reativar Tenant?' : 'Suspender Tenant?',
                html: `Deseja realmente <strong>${willActivate ? 'ativar' : 'suspender'}</strong> o acesso da empresa <strong>${tenantName}</strong>?`,
                showCancelButton: true,
                confirmButtonText: willActivate ? 'Sim, ativar acesso' : 'Sim, suspender agora',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: willActivate ? '#10b981' : '#f59e0b',
                cancelButtonColor: '#64748b',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });

            if (!confirmResult.isConfirmed) {
                // Reverte estado do checkbox
                this.checked = !willActivate;
                return;
            }

            try {
                const response = await fetch(`/admin/tenancy/tenants/${tenantId}/toggle-status`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.ok) {
                    showToast(data.message, true);
                    const badge = document.querySelector(`.status-badge-${tenantId}`);
                    if (badge) {
                        badge.className = `badge rounded-pill text-bg-${data.status === 'active' ? 'success' : 'danger'} status-badge-${tenantId}`;
                        badge.textContent = data.label;
                    }
                } else {
                    this.checked = !willActivate;
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao alterar status',
                        text: data.error || 'Não foi possível alterar o status do tenant.',
                    });
                }
            } catch (err) {
                this.checked = !willActivate;
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Falha na comunicação',
                    text: 'Ocorreu um erro ao comunicar com o servidor.',
                });
            }
        });
    });

    // 2. Deletar Conta & Expurgo Completo (Com Confirmação SweetAlert2)
    document.querySelectorAll('.btn-delete-tenant').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const tenantId = this.getAttribute('data-id');
            const tenantName = this.getAttribute('data-name');

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Deletar conta e expurgar dados?',
                html: `
                    <p class="text-danger fw-bold mb-2">⚠️ ESTA AÇÃO É PERMANENTE E IRREVERSÍVEL!</p>
                    <p class="text-muted small">
                        Ao deletar a empresa <strong>${tenantName}</strong>, o sistema removerá em cascata <strong>TODAS</strong> as faturas, mensagens, instâncias, clientes, webhooks e o usuário associado, garantindo conformidade total e limpeza completa de resíduos no banco de dados.
                    </p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar e expurgar tudo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });

            if (!result.isConfirmed) return;

            // Loading state
            Swal.fire({
                title: 'Expurgando conta...',
                text: 'Removendo todos os registros vinculados com segurança.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch(`/admin/tenancy/tenants/${tenantId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.ok) {
                    Swal.close();
                    showToast(data.message, true);
                    const row = document.getElementById(`tenant-row-${tenantId}`);
                    if (row) {
                        row.style.transition = 'all .4s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => row.remove(), 400);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro na exclusão',
                        text: data.error || 'Não foi possível excluir o tenant.',
                    });
                }
            } catch (err) {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro no servidor',
                    text: 'Ocorreu um erro durante o processo de exclusão.',
                });
            }
        });
    });

    // 3. Remover Usuários Não-Brasileiros em Lote
    const btnPurgeNonBr = document.getElementById('btnPurgeNonBrazilians');
    if (btnPurgeNonBr) {
        btnPurgeNonBr.addEventListener('click', async function () {
            const count = {{ $nonBrazilianCount ?? 0 }};

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Remover todos os Não-Brasileiros?',
                html: `
                    <p class="text-danger fw-bold mb-2">Remoção em Lote de Regulamentação</p>
                    <p class="small text-muted">
                        Foram identificados <strong>${count}</strong> tenant(s) que não atendem aos critérios de numeração ou documentação brasileira regular.
                    </p>
                    <p class="small text-muted">
                        Ao confirmar, todos os dados vinculados a essas contas serão totalmente expurgados do sistema.
                    </p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Sim, expurgar todos agora',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Expurgando registros estrangeiros...',
                text: 'Aguarde a finalização do processo atômico.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('/admin/tenancy/tenants/purge-non-brazilians', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.ok) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Expurgo Concluído',
                        text: data.message,
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Falha no expurgo',
                        text: data.error || 'Ocorreu um erro ao processar a limpeza.',
                    });
                }
            } catch (err) {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro na requisição',
                    text: 'Ocorreu um erro ao processar a requisição.',
                });
            }
        });
    }

    // 4. Login Supervisionado com SweetAlert2
    document.querySelectorAll('.js-assist-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await Swal.fire({
                icon: 'question',
                title: 'Entrar no painel do cliente?',
                text: 'Você acessará o tenant em modo supervisionado e poderá voltar ao painel de administração pela faixa de supervisão no topo.',
                showCancelButton: true,
                confirmButtonText: 'Entrar agora',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#64748b',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });

            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

});
</script>
@endpush
@endsection
