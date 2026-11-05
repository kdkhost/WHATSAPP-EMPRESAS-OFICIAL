@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Templates de contrato')

@section('content')
@php
    $contractRoutePrefix = $contractRoutePrefix ?? (request()->is('painel*') ? 'tenant.contracts.' : 'admin.contracts.');
@endphp
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-file-earmark-richtext me-2 text-primary"></i>Templates de contrato</h3>
            <p class="text-muted mb-0 small">Modelos com timbrado, layout posicionado e conteúdo profissional para envio e assinatura online.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route($contractRoutePrefix . 'index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Contratos
            </a>
            <a href="{{ route($contractRoutePrefix . 'templates.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Novo template
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted fw-semibold small mb-1">Templates ativos</div>
                    <div class="fs-3 fw-bold">{{ $templates->getCollection()->where('active', true)->count() }}</div>
                    <small class="text-muted">Disponíveis para novos contratos</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted fw-semibold small mb-1">Com timbrado</div>
                    <div class="fs-3 fw-bold">{{ $templates->getCollection()->whereNotNull('background_path')->count() }}</div>
                    <small class="text-muted">Imagem ou PDF vinculados</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted fw-semibold small mb-1">Variáveis padrão</div>
                    <div class="fs-3 fw-bold">{{ count($variables) }}</div>
                    <small class="text-muted">Campos automáticos do cliente, empresa e fatura</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @forelse($templates as $template)
            <div class="col-md-6 col-xl-4">
                <div class="card shadow-sm h-100 contract-template-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge text-bg-light border">{{ $template->category ?: 'Serviços' }}</span>
                                    <span class="badge text-bg-{{ $template->active ? 'success' : 'secondary' }}">{{ $template->active ? 'Ativo' : 'Inativo' }}</span>
                                </div>
                                <h5 class="mb-1">{{ $template->name }}</h5>
                                <p class="text-muted small mb-0">{{ $template->description ?: 'Template profissional pronto para personalização.' }}</p>
                            </div>
                            <div class="contract-template-icon">
                                <i class="bi bi-vector-pen"></i>
                            </div>
                        </div>

                        <div class="contract-template-meta small text-muted mb-3">
                            <div><i class="bi bi-grid-3x3-gap me-1"></i> Layout posicionado: {{ $template->layout_json ? 'sim' : 'padrão' }}</div>
                            <div><i class="bi bi-image me-1"></i> Timbrado: {{ $template->background_path ? ($template->background_type === 'pdf' ? 'PDF' : 'Imagem') : 'não configurado' }}</div>
                            <div><i class="bi bi-clock-history me-1"></i> Atualizado {{ $template->updated_at?->diffForHumans() }}</div>
                        </div>

                        <div class="contract-template-preview flex-grow-1">
                            {!! \Illuminate\Support\Str::limit(strip_tags($template->content_html), 260) !!}
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <a href="{{ route($contractRoutePrefix . 'create', ['template' => $template->id]) }}" class="btn btn-sm btn-outline-success flex-grow-1">
                                <i class="bi bi-file-earmark-plus me-1"></i> Usar
                            </a>
                            <a href="{{ route($contractRoutePrefix . 'templates.edit', $template->id) }}" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="bi bi-pencil-square me-1"></i> Editar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-earmark-richtext fs-1 text-muted opacity-50 d-block mb-3"></i>
                        <h5 class="mb-1">Nenhum template encontrado</h5>
                        <p class="text-muted small mb-4">Crie seu primeiro modelo para centralizar contratos com timbrado, assinatura e envio automático.</p>
                        <a href="{{ route($contractRoutePrefix . 'templates.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Criar template
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if($templates->hasPages())
        <div class="card mt-4">
            <div class="card-footer">{{ $templates->links() }}</div>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.contract-template-card {
    border: 1px solid rgba(15, 23, 42, .08);
}
.contract-template-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--bs-primary) 10%, transparent);
    color: var(--bs-primary);
    font-size: 1.15rem;
}
.contract-template-meta {
    display: grid;
    gap: 6px;
}
.contract-template-preview {
    padding: 14px;
    border-radius: 16px;
    border: 1px dashed var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    line-height: 1.7;
    font-size: .9rem;
    color: var(--bs-body-color);
}
</style>
@endpush
