@props(['lines' => 3])

<div {{ $attributes->class('placeholder-glow') }}>
    @for($i = 0; $i < $lines; $i++)
        <span class="placeholder col-{{ $i === 0 ? 12 : ($i % 2 === 0 ? 8 : 10) }} mb-2"></span>
    @endfor
</div>
