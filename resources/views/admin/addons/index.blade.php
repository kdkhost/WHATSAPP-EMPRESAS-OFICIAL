@extends('admin.layouts.app')
@section('title', 'Addons')

@push('head')
<style>
.addons-page .addon-card {
    height: 100%;
}
.addons-page .addon-card__meta {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    font-size: .85rem;
    color: var(--bs-secondary-color);
}
</style>
@endpush

@section('content')
@php
    $stats = [
        'total' => count($list),
        'installed' => collect($list)->where('installed', true)->count(),
        'enabled' => collect($list)->where('installed', true)->where('db_enabled', true)->count(),
        'available' => count($marketplace ?? []),
    ];
@endphp
<div class="container-fluid addons-page" id="addons-page-shell">
    <x-ui.page-header
        title="Addons"
        subtitle="Gerencie módulos instalados, descubra novos pacotes e mantenha a operação centralizada no marketplace."
        eyebrow="Extensões do sistema">
        <x-slot:actions>
            <a href="https://github.com/kdkhost/whatsapp/blob/main/app/Addons/README.md" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-book me-1"></i> Documentação
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Ativos</span>
                <span class="dashboard-note__value text-success">{{ $stats['enabled'] }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Marketplace</span>
                <span class="dashboard-note__value">{{ $stats['available'] }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total listados" :value="$stats['total']" icon="bi-puzzle" color="primary">
                <x-slot:meta>Addons reconhecidos pelo sistema.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Ativos" :value="$stats['enabled']" icon="bi-check-circle" color="success">
                <x-slot:meta>Módulos já habilitados e em operação.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Disponíveis na loja" :value="$stats['available']" icon="bi-cloud-download" color="info">
                <x-slot:meta>Itens comercializáveis ou instaláveis no catálogo.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Instalados" :value="$stats['installed']" icon="bi-download" color="warning">
                <x-slot:meta>Pacotes locais já presentes no ambiente.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card title="Catálogo de addons" subtitle="Alterne entre pacotes instalados e itens disponíveis no marketplace.">
        <ul class="nav nav-tabs mb-3" id="addons-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-installed-btn" data-bs-toggle="tab" data-bs-target="#tab-installed" type="button" role="tab">
                    <i class="bi bi-collection me-1"></i> Instalados ({{ $stats['total'] }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-marketplace-btn" data-bs-toggle="tab" data-bs-target="#tab-marketplace" type="button" role="tab">
                    <i class="bi bi-shop me-1"></i> Marketplace ({{ $stats['available'] }})
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-installed" role="tabpanel">
                @if(empty($list))
                    <x-ui.empty-state icon="bi-puzzle" title="Nenhum addon instalado" message="Abra o marketplace para descobrir e instalar novos addons.">
                        <x-slot:actions>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('tab-marketplace-btn').click();">
                                <i class="bi bi-shop me-1"></i> Abrir marketplace
                            </button>
                        </x-slot:actions>
                    </x-ui.empty-state>
                @else
                    <div class="row g-3">
                        @foreach($list as $addon)
                            @php
                                $statusColor = $addon['installed'] ? ($addon['db_enabled'] ?? false ? 'success' : 'secondary') : 'warning';
                                $statusLabel = $addon['installed'] ? (($addon['db_enabled'] ?? false) ? 'Ativo' : 'Desativado') : 'Não instalado';
                                $statusIcon = $addon['installed'] ? (($addon['db_enabled'] ?? false) ? 'check-circle' : 'pause-circle') : 'download';
                            @endphp
                            <div class="col-md-6 col-lg-4">
                                <x-ui.premium-card class="addon-card" :title="$addon['name']" :subtitle="$addon['description']">
                                    <x-slot:header>
                                        <x-ui.status-badge :status="$statusColor === 'success' ? 'active' : ($statusColor === 'warning' ? 'pending' : 'inactive')" :label="$statusLabel" />
                                    </x-slot:header>
                                    <div class="addon-card__meta mb-3">
                                        <span>v{{ $addon['version'] }}</span>
                                        <span>•</span>
                                        <span>{{ $addon['author'] }}</span>
                                    </div>

                                    @if(!empty($addon['requires']))
                                        <div class="small mb-3">
                                            <span class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Requer:</span>
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                @foreach($addon['requires'] as $dep)
                                                    <span class="badge text-bg-light border">{{ $dep }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="d-flex gap-1 flex-wrap mt-auto pt-2 border-top">
                                        @if(!$addon['installed'])
                                            <button class="btn btn-sm btn-success" data-addon-action="install" data-slug="{{ $addon['slug'] }}">
                                                <i class="bi bi-download me-1"></i> Instalar
                                            </button>
                                        @else
                                            @if($addon['db_enabled'])
                                                <button class="btn btn-sm btn-outline-warning" data-addon-action="disable" data-slug="{{ $addon['slug'] }}">
                                                    <i class="bi bi-pause-circle me-1"></i> Desativar
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-success" data-addon-action="enable" data-slug="{{ $addon['slug'] }}">
                                                    <i class="bi bi-play-circle me-1"></i> Ativar
                                                </button>
                                            @endif
                                            <button class="btn btn-sm btn-outline-info" data-addon-action="update" data-slug="{{ $addon['slug'] }}" title="Atualizar">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger ms-auto" data-addon-action="uninstall" data-slug="{{ $addon['slug'] }}" title="Desinstalar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </x-ui.premium-card>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="tab-pane fade" id="tab-marketplace" role="tabpanel">
                @if(empty($marketplace))
                    <x-ui.empty-state icon="bi-shop" title="Marketplace vazio" message="Não há addons disponíveis no momento." />
                @else
                    <div class="row g-3">
                        @foreach($marketplace as $item)
                            <div class="col-md-6 col-lg-4">
                                <x-ui.premium-card class="addon-card" :title="$item['name']" :subtitle="$item['description']">
                                    <x-slot:header>
                                        @if($item['price'] > 0)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                R$ {{ number_format($item['price'], 2, ',', '.') }}/mês
                                            </span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-gift me-1"></i> Grátis
                                            </span>
                                        @endif
                                    </x-slot:header>

                                    <div class="d-flex align-items-start gap-3 mb-3">
                                        <div class="stat-icon bg-{{ $item['color'] }}-subtle text-{{ $item['color'] }}" style="width:48px;height:48px;font-size:1.4rem;">
                                            <i class="bi {{ $item['icon'] }}"></i>
                                        </div>
                                        <div class="addon-card__meta">
                                            <span>v{{ $item['version'] }}</span>
                                            <span>•</span>
                                            <span>{{ $item['author'] }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $item['category'] }}</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Comercializável</span>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ $item['audience'] ?? 'Admin/Tenant' }}</span>
                                        @if($item['bundled'] ?? false)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Pacote interno</span>
                                        @endif
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center small text-muted mb-3">
                                        <div>
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="bi bi-star{{ $i <= floor($item['rating']) ? '-fill text-warning' : ($i - 0.5 <= $item['rating'] ? '-half text-warning' : ' text-muted') }}"></i>
                                            @endfor
                                            <span class="ms-1">{{ number_format($item['rating'], 1, ',', '.') }}</span>
                                        </div>
                                        <div><i class="bi bi-download me-1"></i>{{ number_format($item['downloads'], 0, ',', '.') }}</div>
                                    </div>

                                    <div class="mt-auto">
                                        @if($item['enabled_local'] ?? false)
                                            <a class="btn btn-sm btn-outline-primary w-100" href="{{ url('/admin/addons/'.$item['slug']) }}">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> Abrir módulo
                                            </a>
                                        @elseif($item['installed_local'] ?? false)
                                            <button class="btn btn-sm btn-success w-100" data-addon-action="enable" data-slug="{{ $item['slug'] }}">
                                                <i class="bi bi-play-circle me-1"></i> Ativar módulo
                                            </button>
                                        @elseif(!($item['installable'] ?? false))
                                            <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                                <i class="bi bi-clock me-1"></i> Em breve
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-success w-100" data-marketplace-install="{{ $item['slug'] }}">
                                                <i class="bi bi-cloud-download me-1"></i> {{ ($item['available_local'] ?? false) ? 'Instalar local' : 'Instalar agora' }}
                                            </button>
                                        @endif
                                    </div>
                                </x-ui.premium-card>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

async function reloadAdminSidebar() {
    try {
        const r = await fetch('{{ route("admin.sidebar.reload") }}', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const data = await r.json();
        if (data.ok && data.html) {
            const current = document.querySelector('.app-sidebar');
            const tmp = document.createElement('div');
            tmp.innerHTML = data.html.trim();
            const next = tmp.querySelector('.app-sidebar');
            if (current && next) {
                current.replaceWith(next);
                if (typeof window.initAdminSidebar === 'function') {
                    window.initAdminSidebar();
                }
            }
        }
    } catch (e) {}
}

async function reloadAddonsContent() {
    const shell = document.getElementById('addons-page-shell');
    if (!shell) return;

    const activePane = document.querySelector('#addons-tabs .nav-link.active')?.dataset.bsTarget || '#tab-installed';
    const r = await fetch(window.location.href, {
        headers: { 'Accept': 'text/html' },
        credentials: 'same-origin',
    });
    const html = await r.text();
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const next = tmp.querySelector('#addons-page-shell');
    if (!next) return;

    shell.innerHTML = next.innerHTML;
    bindAddonActions(shell);

    const tabButton = shell.querySelector(`[data-bs-target="${activePane}"]`);
    if (tabButton) {
        bootstrap.Tab.getOrCreateInstance(tabButton).show();
    }
}

function bindAddonActions(root = document) {
    root.querySelectorAll('[data-addon-action]').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => handleAddonAction(btn));
    });

    root.querySelectorAll('[data-marketplace-install]').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => handleMarketplaceInstall(btn));
    });
}

async function handleAddonAction(btn) {
    const action = btn.dataset.addonAction;
    const slug = btn.dataset.slug;

    if (action === 'uninstall') {
        const ok = await Swal.fire({
            icon: 'warning', title: `Desinstalar ${slug}?`,
            text: 'Todos os dados do addon serão removidos.',
            showCancelButton: true, confirmButtonText: 'Desinstalar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!ok.isConfirmed) return;
    }

    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const r = await fetch(`{{ url('/admin/addons') }}/${slug}/${action}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await r.json();
        if (data.ok) {
            window.toast.success(data.message || `Addon ${action} com sucesso.`);
            await reloadAdminSidebar();
            await reloadAddonsContent();
        } else {
            window.toast.error(data.error || data.message || 'Erro.');
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function handleMarketplaceInstall(btn) {
    const slug = btn.dataset.marketplaceInstall;
    const ok = await Swal.fire({
        icon: 'question', title: `Instalar ${slug}?`,
        text: 'O addon será baixado e instalado automaticamente.',
        showCancelButton: true, confirmButtonText: 'Instalar', cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;

    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Baixando...';

    try {
        const r = await fetch(`{{ url('/admin/addons/marketplace') }}/${slug}/download`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await r.json();
        if (data.ok) {
            window.toast.success(data.message || 'Addon instalado.');
            await reloadAdminSidebar();
            await reloadAddonsContent();
        } else {
            window.toast.error(data.message || 'Erro ao instalar.');
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

bindAddonActions();
</script>
@endpush
