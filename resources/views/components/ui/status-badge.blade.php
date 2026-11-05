@props(['status' => 'secondary', 'label' => null])

@php
    $palette = [
        'active' => 'success',
        'paid' => 'success',
        'pending' => 'warning',
        'overdue' => 'danger',
        'inactive' => 'secondary',
        'disabled' => 'secondary',
        'sandbox' => 'info',
        'production' => 'primary',
    ];
    $resolved = $palette[$status] ?? $status;
@endphp

<span {{ $attributes->class('badge text-bg-'.$resolved.' fs-6') }}>
    {{ $label ?? ucfirst(str_replace('_', ' ', $status)) }}
</span>
