@props(['title' => null, 'subtitle' => null, 'method' => 'POST'])

<x-ui.premium-card :title="$title" :subtitle="$subtitle" {{ $attributes }}>
    <form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" class="row g-3 premium-form">
        @if(!in_array(strtoupper($method), ['GET', 'POST'], true))
            @method($method)
        @endif
        @unless(strtoupper($method) === 'GET')
            @csrf
        @endunless
        {{ $slot }}
    </form>
</x-ui.premium-card>
