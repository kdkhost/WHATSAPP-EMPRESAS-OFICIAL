@props(['label', 'value', 'icon' => 'bi-bar-chart', 'color' => 'primary'])

<div {{ $attributes->class('admin-premium-metric') }}>
    <div class="d-flex align-items-center gap-3">
        <span class="stat-icon bg-{{ $color }} text-white"><i class="bi {{ $icon }}"></i></span>
        <div class="flex-grow-1 min-w-0">
            <span class="dashboard-note__label">{{ $label }}</span>
            <strong class="dashboard-note__value d-block">{{ $value }}</strong>
            @if(isset($meta))
                <span class="text-muted small d-block mt-1">{{ $meta }}</span>
            @endif
        </div>
    </div>
</div>
