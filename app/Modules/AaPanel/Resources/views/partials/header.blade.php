@props(['title' => 'Servidores aaPanel', 'subtitle' => 'Gerencie e monitore servidores aaPanel com métricas em tempo real.'])

<div class="aapanel-header mb-4">
    <div class="card border border-subtle shadow-sm bg-gradient-header overflow-hidden position-relative">
        <div class="card-body p-4 position-relative z-1">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-avatar bg-primary text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 52px; height: 52px; font-size: 1.5rem;">
                        <i class="bi bi-hdd-rack"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold uppercase-track">Módulo Addon</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold"><i class="bi bi-broadcast me-1"></i> Tempo Real</span>
                        </div>
                        <h3 class="mb-0 fw-bold text-body">{{ $title }}</h3>
                        <p class="text-muted small mb-0">{{ $subtitle }}</p>
                    </div>
                </div>

                @if(isset($actions))
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    {{ $actions }}
                </div>
                @endif
            </div>
        </div>
        <div class="header-bg-glow"></div>
    </div>

    <!-- Navegação Exclusiva e Interna do Módulo aaPanel -->
    <div class="aapanel-nav-container mt-3">
        <ul class="nav nav-pills custom-addon-tabs p-1 bg-body rounded-3 shadow-sm border border-subtle">
            <li class="nav-item">
                <a href="{{ request()->is('painel*') ? route('painel.aapanel.dashboard') : route('admin.aapanel.dashboard') }}"
                   class="nav-link px-3 py-2 rounded-2 {{ request()->routeIs('painel.aapanel.dashboard', 'admin.aapanel.dashboard') ? 'active fw-semibold' : 'text-body-secondary' }}">
                    <i class="bi bi-speedometer2 me-1.5"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ request()->is('painel*') ? route('painel.aapanel.servers.index') : route('admin.aapanel.servers.index') }}"
                   class="nav-link px-3 py-2 rounded-2 {{ request()->routeIs('painel.aapanel.servers.*', 'admin.aapanel.servers.*') ? 'active fw-semibold' : 'text-body-secondary' }}">
                    <i class="bi bi-server me-1.5"></i> Servidores
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ request()->is('painel*') ? route('painel.aapanel.plans.index') : route('admin.aapanel.plans.index') }}"
                   class="nav-link px-3 py-2 rounded-2 {{ request()->routeIs('painel.aapanel.plans.*', 'admin.aapanel.plans.*') ? 'active fw-semibold' : 'text-body-secondary' }}">
                    <i class="bi bi-layers me-1.5"></i> Planos
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ request()->is('painel*') ? route('painel.aapanel.accounts.index') : route('admin.aapanel.accounts.index') }}"
                   class="nav-link px-3 py-2 rounded-2 {{ request()->routeIs('painel.aapanel.accounts.*', 'admin.aapanel.accounts.*') ? 'active fw-semibold' : 'text-body-secondary' }}">
                    <i class="bi bi-globe me-1.5"></i> Contas de Hospedagem
                </a>
            </li>
            @if(request()->is('painel*') && Route::has('tenant.addons.aapanel.logs'))
            <li class="nav-item ms-auto">
                <a href="{{ route('tenant.addons.aapanel.logs') }}"
                   class="nav-link px-3 py-2 rounded-2 {{ request()->routeIs('tenant.addons.aapanel.logs') ? 'active fw-semibold' : 'text-body-secondary' }}">
                    <i class="bi bi-journal-text me-1.5"></i> Logs
                </a>
            </li>
            @endif
        </ul>
    </div>
</div>

<style>
.bg-gradient-header {
    background: var(--bs-card-bg, var(--bs-body-bg)) !important;
}
.header-bg-glow {
    position: absolute;
    top: -50px;
    right: -50px;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(13, 110, 253, 0.12) 0%, transparent 70%);
    pointer-events: none;
}
.custom-addon-tabs {
    background-color: var(--bs-card-bg, var(--bs-body-bg)) !important;
}
.custom-addon-tabs .nav-link {
    transition: all 0.2s ease-in-out;
    font-size: 0.9rem;
    color: var(--bs-secondary-color);
}
.custom-addon-tabs .nav-link:hover:not(.active) {
    background-color: var(--bs-tertiary-bg);
    color: var(--bs-body-color) !important;
}
.custom-addon-tabs .nav-link.active {
    background: #0d6efd !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}
.uppercase-track {
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.68rem;
}
</style>
