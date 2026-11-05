@extends('tenancy::tenant.layout')
@section('title', 'Bem-vindo!')
@section('content')
<div class="container py-4">
    <div class="text-center mb-4">
        <i class="bi bi-rocket-takeoff text-primary" style="font-size: 4rem;"></i>
        <h1 class="mt-3">Bem-vindo, {{ $tenant->user->name ?? 'Cliente' }}!</h1>
        <p class="text-muted lead">Vamos configurar sua conta em 5 passos rapidos.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Progresso</strong>
                <span class="badge text-bg-primary">{{ $progress['completed'] }}/{{ $progress['total'] }}</span>
            </div>
            <div class="progress" style="height: 12px;">
                <div class="progress-bar bg-success" role="progressbar"
                     style="width: {{ $progress['percentage'] }}%">{{ $progress['percentage'] }}%</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach($progress['steps'] as $key => $step)
        <div class="col-12">
            <div class="card {{ $step['completed'] ? 'border-success' : '' }}">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        @if($step['completed'])
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem;"></i>
                        @else
                        <i class="bi {{ $step['icon'] }} text-primary" style="font-size: 2.5rem;"></i>
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">{{ $step['label'] }}</h5>
                        <p class="text-muted mb-0 small">{{ $step['description'] }}</p>
                    </div>
                    <div>
                        @if($step['completed'])
                        <span class="badge text-bg-success">Concluido</span>
                        @else
                        <a href="{{ $step['url'] }}" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Configurar
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="text-center mt-4">
        @if($progress['all_done'])
        <div class="alert alert-success">
            <i class="bi bi-trophy-fill"></i> <strong>Parabens!</strong> Sua conta esta totalmente configurada.
        </div>
        <a href="/painel" class="btn btn-success btn-lg">
            <i class="bi bi-speedometer2"></i> Ir para o Dashboard
        </a>
        @else
        <button class="btn btn-link text-muted" id="btn-skip">Pular onboarding</button>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('btn-skip')?.addEventListener('click', async () => {
    if (!await window.confirm2('Pular onboarding?', 'Voce pode configurar tudo depois.')) return;
    await fetch('{{ route("tenant.onboarding.skip") }}', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    window.location = '/painel';
});
</script>
@endpush
