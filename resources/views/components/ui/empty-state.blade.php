@props(['icon' => 'bi-inbox', 'title' => 'Nada por aqui', 'message' => 'Nao ha dados para exibir no momento.'])

<div {{ $attributes->class('text-center py-5') }}>
    <div class="display-6 text-muted mb-3"><i class="bi {{ $icon }}"></i></div>
    <h3 class="h5 mb-2">{{ $title }}</h3>
    <p class="text-muted mb-0">{{ $message }}</p>
    @isset($actions)
        <div class="mt-3">{{ $actions }}</div>
    @endisset
</div>
