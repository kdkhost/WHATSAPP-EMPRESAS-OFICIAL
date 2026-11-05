@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'actions' => null,
])

@php
    $hasActions = isset($actions) && trim((string) $actions) !== '';
    $hasAside = isset($aside) && trim((string) $aside) !== '';
    $modeClass = ($hasAside || $hasActions)
        ? 'admin-premium-hero--with-aside'
        : 'admin-premium-hero--simple';
@endphp

<section {{ $attributes->class(['admin-premium-hero', $modeClass, 'mb-4']) }}>
    <div>
        @if($eyebrow)
            <span class="admin-premium-hero__eyebrow">{{ $eyebrow }}</span>
        @endif
        <h1 class="admin-premium-hero__title">{{ $title }}</h1>
        @if($subtitle)
            <p class="admin-premium-hero__text">{{ $subtitle }}</p>
        @endif
    </div>

    @if($hasActions || $hasAside)
        <div class="d-flex flex-column align-items-end gap-3 justify-content-center">
            @if($hasActions)
                <div class="admin-premium-hero__actions w-100 justify-content-end">
                    {{ $actions }}
                </div>
            @endif

            @if($hasAside)
                <div class="dashboard-hero-notes w-100">
                    {{ $aside }}
                </div>
            @endif
        </div>
    @endif
</section>
