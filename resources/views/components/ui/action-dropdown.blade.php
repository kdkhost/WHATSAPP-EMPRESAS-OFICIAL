@props(['label' => 'Acoes'])

<div class="dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">
        {{ $label }}
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        {{ $slot }}
    </div>
</div>
