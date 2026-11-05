@extends('admin.layouts.app')
@section('title', 'Sistema - Auditoria')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Auditoria do Sistema</h1>
            <div class="text-muted small">Leitura operacional de saúde técnica com execução em tempo real.</div>
        </div>
        <button class="btn btn-primary" id="btn-run-audit">
            <i class="bi bi-play-circle me-1"></i> Executar auditoria agora
        </button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <x-ui.premium-card title="Score Técnico">
                <div class="display-6 fw-bold" id="audit-score">{{ $score }}</div>
                <div class="text-muted small">Atualizado em <span id="audit-updated">{{ $updated_at }}</span></div>
            </x-ui.premium-card>
        </div>
        <div class="col-md-9">
            <x-ui.premium-card title="Indicadores Críticos">
                <div class="row g-2 small">
                    <div class="col-sm-3"><strong>Rotas críticas:</strong> <span id="m-rotas-criticas">{{ $metrics['rotas_criticas'] ?? 0 }}</span></div>
                    <div class="col-sm-3"><strong>Rotas sem middleware:</strong> <span id="m-rotas-sem-mw">{{ $metrics['rotas_sem_middleware'] ?? 0 }}</span></div>
                    <div class="col-sm-3"><strong>Métodos vazios:</strong> <span id="m-metodos-vazios">{{ $metrics['metodos_vazios'] ?? 0 }}</span></div>
                    <div class="col-sm-3"><strong>Debug leftovers:</strong> <span id="m-debug">{{ $metrics['debug_leftovers'] ?? 0 }}</span></div>
                </div>
            </x-ui.premium-card>
        </div>
    </div>

    <x-ui.premium-card title="Resumo da Última Auditoria">
        <pre id="audit-excerpt" class="mb-0" style="white-space: pre-wrap; max-height: 420px; overflow:auto;">{{ $report_excerpt }}</pre>
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
const auditCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

document.getElementById('btn-run-audit')?.addEventListener('click', async function () {
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Executando...';
    try {
        const r = await fetch('{{ route("admin.system.audit.run") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': auditCsrfToken,
                'Accept': 'application/json'
            }
        });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Falha ao executar auditoria.');
        document.getElementById('audit-score').textContent = d.score ?? 0;
        document.getElementById('audit-updated').textContent = d.updated_at || '-';
        document.getElementById('m-rotas-criticas').textContent = d.metrics?.rotas_criticas ?? 0;
        document.getElementById('m-rotas-sem-mw').textContent = d.metrics?.rotas_sem_middleware ?? 0;
        document.getElementById('m-metodos-vazios').textContent = d.metrics?.metodos_vazios ?? 0;
        document.getElementById('m-debug').textContent = d.metrics?.debug_leftovers ?? 0;
        document.getElementById('audit-excerpt').textContent = d.report_excerpt || '';
        if (window.toast?.success) window.toast.success(d.message || 'Auditoria concluída.');
    } catch (e) {
        if (window.toast?.error) window.toast.error(e.message || 'Erro ao executar auditoria.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});
</script>
@endpush
