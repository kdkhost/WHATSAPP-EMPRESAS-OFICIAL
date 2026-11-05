@props([
    'slug',
    'title',
    'description' => '',
    'icon' => 'bi bi-puzzle',
    'items' => [],
    'currentLabel' => null,
    'backUrl' => null,
    'marketplaceUrl' => null,
    'addonsUrl' => null,
])

@php
    $previous = url()->previous();
    $current = url()->current();

    $resolvedBackUrl = $backUrl;
    if (! $resolvedBackUrl) {
        $resolvedBackUrl = $previous && $previous !== $current
            ? $previous
            : (Route::has('tenant.addons.index') ? route('tenant.addons.index') : url('/painel/addons'));
    }

    $resolvedAddonsUrl = $addonsUrl ?: (Route::has('tenant.addons.index') ? route('tenant.addons.index') : url('/painel/addons'));
    $resolvedMarketplaceUrl = $marketplaceUrl ?: (Route::has('tenant.addons.index') ? route('tenant.addons.index') : url('/painel/addons'));

    $safeItems = collect($items)
        ->filter(fn ($item) => ! empty($item['label']) && ! empty($item['url']))
        ->values()
        ->all();

    $activeItem = collect($safeItems)->firstWhere('active', true);
    $resolvedCurrentLabel = $currentLabel ?: ($activeItem['label'] ?? $title);
@endphp

@once
    @push('head')
    <style>
        .tenant-addon-shell { max-width: 100%; overflow-x: hidden; }
        .tenant-addon-breadcrumb { margin-bottom: .75rem; }
        .tenant-addon-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .875rem; }
        .tenant-addon-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
            border: 1px solid rgba(16, 185, 129, .22);
            border-radius: 22px;
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, .14), rgba(15, 23, 42, .03));
            margin-bottom: 1rem;
        }
        .tenant-addon-heading { min-width: 0; }
        .tenant-addon-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            border-radius: 999px; padding: .35rem .65rem; margin-bottom: .75rem;
            background: rgba(16, 185, 129, .12); color: #10b981;
            font-size: .78rem; font-weight: 800; letter-spacing: .04em;
        }
        .tenant-addon-heading h1 { margin: 0; font-size: clamp(1.5rem, 2.2vw, 2.25rem); font-weight: 800; }
        .tenant-addon-heading p { margin: .35rem 0 0; color: var(--bs-secondary-color, #94a3b8); }
        .tenant-addon-actions {
            display: flex; align-items: center; justify-content: flex-end;
            gap: .5rem; flex-wrap: wrap;
        }
        .tenant-addon-nav {
            display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;
            padding: .75rem;
            border: 1px solid var(--app-border-soft, rgba(148, 163, 184, .18));
            border-radius: 18px;
            background: var(--bs-body-bg, #ffffff);
            margin-bottom: 1rem;
        }
        .tenant-addon-nav-link {
            display: inline-flex; align-items: center; gap: .45rem;
            border-radius: 999px; padding: .55rem .85rem;
            text-decoration: none; font-weight: 700; font-size: .9rem;
            color: var(--bs-secondary-color, #64748b);
            border: 1px solid transparent;
            transition: all .15s ease;
        }
        .tenant-addon-nav-link:hover {
            color: var(--bs-primary, #0d6efd);
            background: rgba(13, 110, 253, .08);
        }
        .tenant-addon-nav-link.active {
            color: #ffffff;
            background: var(--bs-primary, #0d6efd);
            border-color: var(--bs-primary, #0d6efd);
        }
        .tenant-addon-content { min-width: 0; }
        @media (max-width: 768px) {
            .tenant-addon-header { flex-direction: column; }
            .tenant-addon-actions { width: 100%; justify-content: stretch; }
            .tenant-addon-actions .btn { flex: 1 1 auto; }
            .tenant-addon-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .tenant-addon-nav-link { justify-content: center; }
        }
        @media (max-width: 420px) {
            .tenant-addon-nav { grid-template-columns: 1fr; }
        }
    </style>
    @endpush
@endonce

<div class="tenant-addon-shell tenant-addon-shell-{{ $slug }}">
    <nav class="tenant-addon-breadcrumb" aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/painel') }}">Início</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ $resolvedAddonsUrl }}">Addons</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ $safeItems[0]['url'] ?? $resolvedAddonsUrl }}">{{ $title }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $resolvedCurrentLabel }}</li>
        </ol>
    </nav>

    <div class="tenant-addon-header">
        <div class="tenant-addon-heading">
            <span class="tenant-addon-badge">
                <i class="{{ $icon }}" aria-hidden="true"></i>
                ADDON INSTALADO
            </span>

            <h1>{{ $title }}</h1>

            @if($description)
                <p>{{ $description }}</p>
            @endif
        </div>

        <div class="tenant-addon-actions">
            <a href="{{ $resolvedBackUrl }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
                Voltar
            </a>

            <a href="{{ $resolvedAddonsUrl }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-puzzle"></i>
                Meus Addons
            </a>

            <a href="{{ $resolvedMarketplaceUrl }}" class="btn btn-success btn-sm">
                <i class="bi bi-shop"></i>
                Marketplace
            </a>
        </div>
    </div>

    @if(count($safeItems) > 0)
        <nav class="tenant-addon-nav" aria-label="Menu interno do addon {{ $title }}">
            @foreach($safeItems as $item)
                <a href="{{ $item['url'] }}"
                   class="tenant-addon-nav-link {{ ! empty($item['active']) ? 'active' : '' }}">
                    @if(! empty($item['icon']))
                        <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    @endif
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    <div class="tenant-addon-content">
        {{ $slot }}
    </div>
</div>
