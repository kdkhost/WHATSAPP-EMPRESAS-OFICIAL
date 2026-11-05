@extends('admin.layouts.app')

@section('title', $addon['name'])

@section('content')
@php
    $menu = $addon['meta']['menu'] ?? [];
    $routeValue = trim((string) ($addon['route'] ?? ''));
    if ($routeValue && \Illuminate\Support\Facades\Route::has($routeValue)) {
        $openUrl = route($routeValue);
    } elseif ($routeValue && !str_starts_with($routeValue, 'http')) {
        $openUrl = url($routeValue);
    } else {
        $openUrl = null;
    }
@endphp
<div class="container-fluid">
    <x-ui.page-header
        :title="$addon['name']"
        :subtitle="$addon['description'] ?: 'Módulo sem descrição cadastrada no manifesto.'"
        eyebrow="Addon instalado">
        <x-slot:actions>
            <a href="{{ route('admin.addons.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Status</span>
                <span class="dashboard-note__value">{{ $addon['db_enabled'] ? 'Ativo' : 'Desativado' }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Versão</span>
                <span class="dashboard-note__value">v{{ $addon['version'] }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Versão" :value="'v'.$addon['version']" :icon="str_starts_with($addon['icon'], 'bi-') ? $addon['icon'] : 'bi-puzzle'" color="primary">
                <x-slot:meta>Versão declarada no manifesto do addon.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Status" :value="$addon['db_enabled'] ? 'Ativo' : 'Desativado'" icon="bi-check-circle" :color="$addon['db_enabled'] ? 'success' : 'secondary'">
                <x-slot:meta>Estado operacional atual dentro do sistema.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Autor" :value="$addon['author'] ?: 'Não informado'" icon="bi-person-badge" color="info">
                <x-slot:meta>Responsável declarado no pacote.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Rota" :value="$addon['route'] ?? '/admin/addons/'.$addon['slug']" icon="bi-signpost-split" color="warning">
                <x-slot:meta>Ponto de entrada principal do módulo.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card title="Ações do addon" subtitle="Acesse o painel do módulo ou volte para a central de addons." class="mb-4">
        <div class="d-flex flex-wrap gap-2">
            @if($openUrl)
                <a href="{{ $openUrl }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Abrir painel do addon
                </a>
            @endif
            <a href="{{ route('admin.addons.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-puzzle me-1"></i> Gerenciar addons
            </a>
        </div>
    </x-ui.premium-card>

    @if(!empty($addon['permissions']))
        <x-ui.premium-card title="Permissões declaradas" subtitle="Escopo funcional informado pelo addon.">
            <div class="d-flex flex-wrap gap-2">
                @foreach($addon['permissions'] as $permission)
                    @php
                        $label = is_array($permission) ? ($permission['label'] ?? $permission['action'] ?? 'Permissão') : $permission;
                    @endphp
                    <span class="badge text-bg-light border">{{ $label }}</span>
                @endforeach
            </div>
        </x-ui.premium-card>
    @endif
</div>
@endsection
