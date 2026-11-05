@php
    $initials = collect(explode(' ', trim($t->name)))->map(fn ($n) => mb_substr($n, 0, 1))->take(2)->implode('');
@endphp
<div class="col-md-6 col-lg-4">
    <div class="card testimonial-card shadow-sm"
         data-id="{{ $t->id }}"
         data-name="{{ $t->name }}"
         data-role="{{ $t->role }}"
         data-avatar="{{ $t->avatar }}"
         data-rating="{{ $t->rating }}"
         data-content="{{ $t->content }}"
         data-source="{{ $t->source }}"
         data-active="{{ $t->active ? '1' : '0' }}">

        <div class="card-body">
            <div class="d-flex align-items-start gap-2 mb-3">
                @if($t->avatar)
                    <img src="{{ $t->avatar }}" class="testimonial-avatar" alt="{{ $t->name }}">
                @else
                    <div class="testimonial-avatar">{{ strtoupper($initials) }}</div>
                @endif
                <div class="flex-grow-1 min-w-0">
                    <strong class="d-block text-truncate">{{ $t->name }}</strong>
                    @if($t->role)
                        <small class="text-muted d-block text-truncate">{{ $t->role }}</small>
                    @endif
                </div>
                <div class="testimonial-drag-handle pt-1" title="Arrastar para reordenar">
                    <i class="bi bi-grip-vertical"></i>
                </div>
            </div>

            <div class="testimonial-rating mb-2">
                @for($i = 1; $i <= 5; $i++)
                    <i class="bi {{ $i <= $t->rating ? 'bi-star-fill' : 'bi-star' }}"></i>
                @endfor
            </div>

            <p class="testimonial-content mb-3 flex-grow-1">{{ \Illuminate\Support\Str::limit($t->content, 180) }}</p>

            <div class="d-flex align-items-center justify-content-between mt-auto">
                <div class="d-flex gap-1 flex-wrap">
                    <span class="testimonial-status-badge badge bg-{{ $t->active ? 'success' : 'secondary' }}">
                        <i class="bi bi-{{ $t->active ? 'eye' : 'eye-slash' }} me-1"></i>
                        {{ $t->active ? 'Ativo' : 'Inativo' }}
                    </span>
                    @if($t->source === 'google')
                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                            <i class="bi bi-google me-1"></i> Google
                        </span>
                    @endif
                </div>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="toggle" title="{{ $t->active ? 'Desativar' : 'Ativar' }}">
                        <i class="bi bi-{{ $t->active ? 'pause-fill' : 'play-fill' }}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
