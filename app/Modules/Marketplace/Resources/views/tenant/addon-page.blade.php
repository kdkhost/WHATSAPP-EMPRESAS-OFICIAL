@extends('tenancy::tenant.layout')

@section('title', $module['name'])

@section('content')
<div class="tenant-premium-hero mb-4">
    <div>
        <div class="tenant-premium-hero__eyebrow">Addon</div>
        <h1 class="tenant-premium-hero__title">{{ $module['name'] }}</h1>
        <p class="tenant-premium-hero__text">{{ $module['description'] }}</p>
    </div>
    <div class="text-end">
        <span class="badge bg-success-subtle text-success border border-success-subtle">
            <i class="bi bi-check-circle me-1"></i>Ativo na sua conta
        </span>
    </div>
</div>

<div class="premium-panel">
    <div class="premium-panel__header">
        <div>
            <div class="premium-panel__kicker">Dashboard do addon</div>
            <h5 class="premium-panel__title">{{ $module['name'] }}</h5>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">v{{ $module['version'] }}</span>
        </div>
    </div>
    <div class="premium-panel__body text-center py-5">
        <i class="bi {{ $module['icon'] }} fs-1 text-primary mb-3 d-block"></i>
        <h5 class="mb-2">{{ $module['name'] }}</h5>
        <p class="text-muted mb-4 max-w-md mx-auto">Este addon esta instalado e ativo na sua conta. A interface completa do addon esta em desenvolvimento para o painel do tenant.</p>
        <div class="d-inline-flex flex-wrap gap-2 justify-content-center">
            <span class="badge bg-secondary">Slug: {{ $module['slug'] }}</span>
            <span class="badge bg-secondary">Pagina: {{ $page }}</span>
        </div>
    </div>
</div>
@endsection
