@php
    $triggerMap = [
        'time'   => ['icon' => 'bi-stopwatch',  'color' => 'primary',   'label' => 'Tempo',       'unit' => 's'],
        'scroll' => ['icon' => 'bi-arrow-down', 'color' => 'info',      'label' => 'Scroll',      'unit' => 'px'],
        'exit'   => ['icon' => 'bi-box-arrow-right', 'color' => 'warning', 'label' => 'Exit intent', 'unit' => ''],
        'click'  => ['icon' => 'bi-mouse',     'color' => 'success',   'label' => 'Click',        'unit' => ''],
    ];
    $tInfo = $triggerMap[$p->trigger] ?? $triggerMap['time'];
    $hasCta = ! empty($p->cta_label) && ! empty($p->cta_url);
    $today = now()->toDateString();
    $expired = $p->ends_at && $p->ends_at < $today;
    $upcoming = $p->starts_at && $p->starts_at > $today;
@endphp
<tr id="popup-{{ $p->id }}"
    data-id="{{ $p->id }}"
    data-name="{{ $p->name }}"
    data-trigger="{{ $p->trigger }}"
    data-trigger-value="{{ $p->trigger_value }}"
    data-image="{{ $p->image }}"
    data-html="{{ $p->html }}"
    data-cta-label="{{ $p->cta_label }}"
    data-cta-url="{{ $p->cta_url }}"
    data-starts-at="{{ $p->starts_at }}"
    data-ends-at="{{ $p->ends_at }}"
    data-active="{{ $p->active ? '1' : '0' }}"
    style="cursor: pointer;">

    <td>
        <div class="d-flex align-items-center gap-2">
            @if($p->image)
                <img src="{{ $p->image }}" alt="" style="width: 32px; height: 32px; border-radius: .375rem; object-fit: cover; flex-shrink: 0;">
            @else
                <div class="popup-trigger-icon bg-{{ $tInfo['color'] }}-subtle text-{{ $tInfo['color'] }}">
                    <i class="bi bi-window-stack"></i>
                </div>
            @endif
            <div class="min-w-0">
                <strong class="d-block text-truncate">{{ $p->name }}</strong>
                <small class="text-muted">#{{ $p->id }}</small>
            </div>
        </div>
    </td>

    <td>
        <div class="d-flex align-items-center gap-2">
            <span class="popup-trigger-icon bg-{{ $tInfo['color'] }}-subtle text-{{ $tInfo['color'] }}">
                <i class="bi {{ $tInfo['icon'] }}"></i>
            </span>
            <div>
                <div class="small fw-semibold">{{ $tInfo['label'] }}</div>
                @if($p->trigger !== 'exit')
                    <small class="text-muted">{{ $p->trigger_value }}{{ $tInfo['unit'] }}</small>
                @endif
            </div>
        </div>
    </td>

    <td>
        @if($p->starts_at || $p->ends_at)
            <small class="d-block">
                <i class="bi bi-calendar-event me-1 text-muted"></i>
                {{ $p->starts_at ? \Illuminate\Support\Carbon::parse($p->starts_at)->format('d/m/Y') : '—' }}
                <span class="text-muted">a</span>
                {{ $p->ends_at ? \Illuminate\Support\Carbon::parse($p->ends_at)->format('d/m/Y') : '—' }}
            </small>
            @if($expired)
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle small mt-1">
                    <i class="bi bi-clock-history me-1"></i> Vencido
                </span>
            @elseif($upcoming)
                <span class="badge bg-info-subtle text-info border border-info-subtle small mt-1">
                    <i class="bi bi-calendar-plus me-1"></i> Agendado
                </span>
            @endif
        @else
            <small class="text-muted">Sempre</small>
        @endif
    </td>

    <td>
        @if($hasCta)
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                <i class="bi bi-cursor me-1"></i> {{ \Illuminate\Support\Str::limit($p->cta_label, 20) }}
            </span>
        @else
            <small class="text-muted">—</small>
        @endif
    </td>

    <td>
        <span class="popup-status-badge badge bg-{{ $p->active ? 'success' : 'secondary' }}">
            <i class="bi bi-{{ $p->active ? 'eye' : 'eye-slash' }} me-1"></i>
            {{ $p->active ? 'Ativo' : 'Inativo' }}
        </span>
    </td>

    <td class="all text-end no-export" style="width: 100px; min-width: 90px; max-width: 110px; white-space: nowrap;">
        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary p-1 px-2" data-action="toggle" title="{{ $p->active ? 'Desativar' : 'Ativar' }}">
                <i class="bi bi-{{ $p->active ? 'pause-fill' : 'play-fill' }}"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" data-action="delete" title="Excluir">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </td>
</tr>
