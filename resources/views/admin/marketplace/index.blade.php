@extends('admin.layouts.app')
@section('title', 'Marketplace de Addons')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="m-0"><i class="bi bi-shop me-2"></i>Marketplace</h4>
    </div>

    <div class="row g-4">
        @php
            $addons = \Illuminate\Support\Facades\DB::table('marketplace_addons')->orderBy('name')->get();
        @endphp

        @if($addons->isEmpty())
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-puzzle display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">Nenhum addon disponivel</h5>
                        <p class="text-muted">Addons de terceiros aparecerão aqui quando disponíveis.</p>
                    </div>
                </div>
            </div>
        @else
            @foreach($addons as $addon)
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <i class="bi bi-puzzle-fill fs-2 text-primary"></i>
                                <div>
                                    <h5 class="m-0">{{ $addon->name }}</h5>
                                    <small class="text-muted">v{{ $addon->version }} · {{ $addon->author ?? 'Comunidade' }}</small>
                                </div>
                            </div>
                            <p class="text-muted small">{{ $addon->description ?? 'Sem descricao.' }}</p>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <span class="badge bg-{{ $addon->installed ? 'success' : 'secondary' }}">{{ $addon->installed ? 'Instalado' : 'Disponivel' }}</span>
                            @if($addon->price > 0)
                                <strong class="text-primary">R$ {{ number_format($addon->price, 2, ',', '.') }}</strong>
                            @else
                                <span class="text-success">Gratuito</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
