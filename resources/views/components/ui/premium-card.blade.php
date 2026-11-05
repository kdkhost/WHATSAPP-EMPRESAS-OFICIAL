@props(['title' => null, 'subtitle' => null, 'footer' => null])

<section {{ $attributes->class('premium-panel') }}>
    @if($title || $subtitle || isset($header))
        <div class="premium-panel__header">
            <div>
                @isset($kicker)
                    <span class="premium-panel__kicker">{{ $kicker }}</span>
                @endisset
                @if($title)
                    <h2 class="premium-panel__title">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="premium-panel__subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($header)
                <div>{{ $header }}</div>
            @endisset
        </div>
    @endif
    <div class="premium-panel__body">
        {{ $slot }}
    </div>
    @if($footer || isset($actions))
        <div class="premium-panel__footer">
            {{ $footer ?? $actions }}
        </div>
    @endif
</section>
