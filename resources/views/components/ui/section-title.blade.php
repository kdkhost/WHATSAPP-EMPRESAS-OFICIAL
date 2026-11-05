@props(['title', 'subtitle' => null])

<div {{ $attributes->class('mb-3') }}>
    <h2 class="admin-panel-title">{{ $title }}</h2>
    @if($subtitle)
        <span class="admin-panel-subtitle">{{ $subtitle }}</span>
    @endif
</div>
