@props(['id', 'title', 'size' => null])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog {{ $size ? 'modal-'.$size : '' }}">
        <div class="modal-content premium-panel">
            <div class="modal-header premium-panel__header">
                <div>
                    <h2 class="premium-panel__title">{{ $title }}</h2>
                    @isset($subtitle)
                        <p class="premium-panel__subtitle">{{ $subtitle }}</p>
                    @endisset
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body premium-panel__body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="modal-footer premium-panel__footer">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
