@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Templates de Email')

@push('head')
<style>
.email-template-page {
    --email-border: rgba(15,23,42,.08);
    --email-panel-bg: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --email-accent-soft: color-mix(in srgb, var(--bs-primary) 12%, transparent);
}
.email-hero {
    border: 1px solid var(--email-border);
    border-radius: 18px;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 10%, transparent), transparent 40%),
        linear-gradient(180deg,var(--bs-body-bg),color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%));
    padding: 1.4rem;
}
.email-hero-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: var(--email-accent-soft);
    color: var(--bs-primary);
    font-size: 1.45rem;
}
.email-metric {
    border: 1px solid var(--email-border);
    border-radius: 16px;
    background: var(--bs-body-bg);
    padding: 1rem 1.05rem;
    min-height: 78px;
    box-shadow: 0 14px 35px rgba(15,23,42,.05);
}
.email-template-card {
    overflow: hidden;
    border-color: var(--email-border);
    border-radius: 18px;
    box-shadow: 0 14px 35px rgba(15,23,42,.05) !important;
}
.email-template-card::before {
    content: "";
    display: block;
    height: 3px;
    background: var(--template-color, var(--bs-primary));
}
.email-template-card .card-body {
    display: flex;
    flex-direction: column;
    min-height: 220px;
}
.template-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: color-mix(in srgb, var(--template-color, var(--bs-primary)) 13%, transparent);
    color: var(--template-color, var(--bs-primary));
    font-size: 1.1rem;
}
.template-subject {
    line-height: 1.35;
    min-height: 38px;
}
.template-preview {
    color: var(--bs-secondary-color);
    line-height: 1.45;
    min-height: 58px;
}
.template-meta {
    border-top: 1px solid var(--email-border);
    padding-top: .75rem;
}
[data-bs-theme="dark"] .email-template-page { --email-border: rgba(255,255,255,.08); }
[data-bs-theme="dark"] .email-hero,
[data-bs-theme="dark"] .email-metric,
[data-bs-theme="dark"] .email-template-card {
    background-color: #1f242a;
    box-shadow: 0 14px 35px rgba(0,0,0,.22) !important;
}
</style>
@endpush

@section('content')
@php
    $items = $templates->getCollection();
    $activeCount = $items->where('active', true)->count();
    $scheduledCount = $items->filter(fn ($item) => filled($item->schedule_offset))->count();
    $categoryCount = $items->pluck('category')->filter()->unique()->count();
    $catColors = ['cobranca'=>'#0d6efd','lembrete'=>'#0dcaf0','atraso'=>'#dc3545','agradecimento'=>'#198754','custom'=>'#6c757d'];
    $catClasses = ['cobranca'=>'primary','lembrete'=>'info','atraso'=>'danger','agradecimento'=>'success','custom'=>'secondary'];
    $catIcons  = ['cobranca'=>'receipt-cutoff','lembrete'=>'bell','atraso'=>'exclamation-triangle','agradecimento'=>'heart','custom'=>'code-square'];
    $catLabels = ['cobranca'=>'Cobrança','lembrete'=>'Lembrete','atraso'=>'Atraso','agradecimento'=>'Agradecimento','custom'=>'Custom'];
    $preferredIds = collect($preferredTemplateIds ?? [])->values()->map(fn ($id) => (int) $id)->all();
@endphp

<div class="container-fluid email-template-page">
    <div class="email-hero mb-4">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-start gap-3">
                <div class="email-hero-icon">
                    <i class="bi bi-envelope-paper"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h3 class="mb-0">Templates de Email</h3>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">SMTP por tenant</span>
                    </div>
                    <p class="text-muted mb-0">
                        Modelos HTML para cobranças automáticas, lembretes e atrasos, com envio em fila e horário configurado por conta.
                    </p>
                </div>
            </div>
            <a href="{{ route($routeNames['templatesCreate']) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Novo template
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="email-metric">
                <div class="text-muted small text-uppercase fw-semibold">Total</div>
                <div class="fs-4 fw-bold">{{ $templates->total() }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="email-metric">
                <div class="text-muted small text-uppercase fw-semibold">Ativos nesta página</div>
                <div class="fs-4 fw-bold text-success">{{ $activeCount }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="email-metric">
                <div class="text-muted small text-uppercase fw-semibold">Com disparo</div>
                <div class="fs-4 fw-bold text-info">{{ $scheduledCount }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="email-metric">
                <div class="text-muted small text-uppercase fw-semibold">Categorias</div>
                <div class="fs-4 fw-bold">{{ $categoryCount }}</div>
            </div>
        </div>
    </div>

    @if($templates->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <div class="email-hero-icon mx-auto mb-3">
                    <i class="bi bi-envelope-plus"></i>
                </div>
                <h5 class="mb-2">Nenhum template cadastrado</h5>
                <p class="text-muted small mb-4">Crie o primeiro modelo de cobrança por email para usar junto com WhatsApp ou de forma independente.</p>
                <a href="{{ route($routeNames['templatesCreate']) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Criar template
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($templates as $t)
                @php
                    $color = $catColors[$t->category] ?? '#6c757d';
                    $class = $catClasses[$t->category] ?? 'secondary';
                    $preview = \Illuminate\Support\Str::of(strip_tags($t->body_html ?? ''))->squish()->limit(132);
                    $isPreferred = in_array((int) $t->id, $preferredIds, true);
                @endphp
                <div class="col-md-6 col-xl-4" id="card-{{ $t->id }}">
                    <div class="card shadow-sm h-100 email-template-card" style="--template-color: {{ $color }};">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <div class="template-icon flex-shrink-0">
                                        <i class="bi bi-{{ $catIcons[$t->category] ?? 'envelope' }}"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate" title="{{ $t->name }}">{{ $t->name }}</div>
                                        <span class="badge bg-{{ $class }}-subtle text-{{ $class }} border border-{{ $class }}-subtle">
                                            {{ $catLabels[$t->category] ?? $t->category }}
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <span class="badge bg-{{ $t->active ? 'success' : 'secondary' }}-subtle text-{{ $t->active ? 'success' : 'secondary' }} border border-{{ $t->active ? 'success' : 'secondary' }}-subtle">
                                        {{ $t->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                    @if($t->locked || $t->is_system)
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Sistema</span>
                                    @endif
                                    @if($isPreferred)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-star-fill me-1"></i>Padrao</span>
                                    @endif
                                </div>
                            </div>

                            <div class="template-subject fw-semibold mb-2" title="{{ $t->subject }}">
                                <i class="bi bi-envelope me-1 text-muted"></i>{{ $t->subject ?: 'Sem assunto' }}
                            </div>

                            <div class="template-preview small mb-3">
                                {{ $preview ?: 'Sem corpo HTML cadastrado.' }}
                            </div>

                            <div class="template-meta mt-auto d-flex align-items-center justify-content-between gap-2 flex-wrap small">
                                <span class="badge bg-body-tertiary text-body border">
                                    <i class="bi bi-clock me-1"></i>{{ $t->schedule_offset ?: 'Manual' }}
                                </span>
                                <span class="text-muted">
                                    <i class="bi bi-calendar2-check me-1"></i>{{ optional($t->updated_at)->format('d/m/Y H:i') ?? 'Sem data' }}
                                </span>
                            </div>
                        </div>
                        <div class="card-footer d-flex gap-2">
                            @if($t->locked || $t->is_system)
                                <button class="btn btn-sm btn-outline-secondary" disabled title="Template protegido">
                                    <i class="bi bi-shield-lock me-1"></i> Sistema
                                </button>
                            @else
                                <a href="{{ route($routeNames['templatesEdit'], $t->id) }}" class="btn btn-sm btn-primary flex-grow-1">
                                    <i class="bi bi-pencil-square me-1"></i> Editar
                                </a>
                                <button class="btn btn-sm btn-outline-danger" data-delete="{{ $t->id }}" title="Excluir template">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                            <button class="btn btn-sm {{ $isPreferred ? 'btn-success' : 'btn-outline-success' }}" data-default="{{ $t->id }}" {{ $isPreferred || ! $t->active ? 'disabled' : '' }} title="Definir como padrao">
                                <i class="bi bi-star"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($templates->hasPages())
            <div class="card mt-4">
                <div class="card-footer">{{ $templates->links() }}</div>
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

document.querySelectorAll('[data-delete]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Excluir template?',
            text: 'Esta ação não pode ser desfeita.',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!ok.isConfirmed) return;

        const r = await fetch(`{{ $templateBaseUrl }}/${btn.dataset.delete}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });

        const d = await r.json().catch(() => ({}));
        if (r.ok && d.ok !== false) {
            document.getElementById(`card-${btn.dataset.delete}`)?.remove();
            window.toast.success('Template excluído.');
        } else {
            window.toast.error(d.error || d.message || 'Erro ao excluir.');
        }
    });
});

document.querySelectorAll('[data-default]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const r = await fetch(`{{ $templateBaseUrl }}/${btn.dataset.default}/padrao`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const d = await r.json().catch(() => ({}));
        if (r.ok && d.ok) {
            window.toast.success('Template padrao atualizado.');
            setTimeout(() => location.reload(), 500);
        } else {
            window.toast.error(d.error || d.message || 'Erro ao definir padrao.');
        }
    });
});
</script>
@endpush
