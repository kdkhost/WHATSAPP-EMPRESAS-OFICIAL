@php
    $hasCta = ! empty($s->cta_label) && ! empty($s->cta_url);
@endphp
<div class="col-md-6 col-lg-4" id="slide-{{ $s->id }}">
    <div class="card slide-card shadow-sm h-100"
         data-id="{{ $s->id }}"
         data-title="{{ $s->title }}"
         data-subtitle="{{ $s->subtitle }}"
         data-image="{{ $s->image }}"
         data-cta-label="{{ $s->cta_label }}"
         data-cta-url="{{ $s->cta_url }}"
         data-animation="{{ $s->animation }}"
         data-active="{{ $s->active ? '1' : '0' }}"
         data-cta="{{ $hasCta ? '1' : '0' }}">
        <div class="slide-card-img {{ $s->image ? '' : 'empty' }}"
             style="{{ $s->image ? "background-image: url('".$s->image."');" : '' }}">
            <div class="slide-drag-handle" title="Arrastar para reordenar">
                <i class="bi bi-grip-vertical"></i>
            </div>
            <div class="slide-position-badge">{{ $loop->iteration ?? '' }}</div>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="flex-grow-1 min-w-0">
                    <h6 class="mb-1 text-truncate">{{ $s->title ?: '(sem titulo)' }}</h6>
                    @if($s->subtitle)
                        <small class="text-muted d-block text-truncate">{{ $s->subtitle }}</small>
                    @endif
                </div>
                <div class="slide-card-actions d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary slide-toggle-btn" title="{{ $s->active ? 'Desativar' : 'Ativar' }}">
                        <i class="bi bi-{{ $s->active ? 'pause-fill' : 'play-fill' }}"></i>
                    </button>
                </div>
            </div>
            <div class="d-flex gap-1 flex-wrap mt-2">
                <span class="slide-status-badge badge bg-{{ $s->active ? 'success' : 'secondary' }}">
                    <i class="bi bi-{{ $s->active ? 'eye' : 'eye-slash' }} me-1"></i>
                    {{ $s->active ? 'Ativo' : 'Inativo' }}
                </span>
                <span class="badge bg-info-subtle text-info border border-info-subtle">
                    <i class="bi bi-magic me-1"></i> {{ ucfirst($s->animation) }}
                </span>
                @if($hasCta)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="bi bi-cursor me-1"></i> CTA
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
