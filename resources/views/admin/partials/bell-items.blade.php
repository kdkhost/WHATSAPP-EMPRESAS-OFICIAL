@php
    $groupConfig = [
        'cancellation' => ['label' => 'Cancelamentos', 'icon' => 'bi-x-circle-fill', 'badge' => 'text-bg-danger', 'color' => 'danger'],
        'settlement'   => ['label' => 'Quitações', 'icon' => 'bi-check-circle-fill', 'badge' => 'text-bg-success', 'color' => 'success'],
        'registration' => ['label' => 'Cadastros', 'icon' => 'bi-person-plus-fill', 'badge' => 'text-bg-info text-white', 'color' => 'info'],
        'subscription' => ['label' => 'Assinaturas', 'icon' => 'bi-stars', 'badge' => 'text-bg-primary', 'color' => 'primary'],
        'billing'      => ['label' => 'Faturas & Cobranças', 'icon' => 'bi-receipt', 'badge' => 'text-bg-warning text-dark', 'color' => 'warning'],
        'whatsapp'     => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'badge' => 'text-bg-success', 'color' => 'success'],
        'security'     => ['label' => 'Segurança', 'icon' => 'bi-shield-check', 'badge' => 'text-bg-danger', 'color' => 'danger'],
        'system'       => ['label' => 'Sistema', 'icon' => 'bi-gear-fill', 'badge' => 'text-bg-secondary', 'color' => 'secondary'],
    ];
@endphp

@if($grouped->count() > 0)
    {{-- Barra de Pílulas de Filtro de Grupos --}}
    @if($grouped->count() > 1)
        <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 overflow-x-auto text-nowrap notification-group-pills" style="scrollbar-width: none;">
            <button type="button" class="btn btn-xs btn-primary rounded-pill px-2 py-0 active" data-filter-group="all" onclick="filterNotificationGroup(this, 'all')">
                Todas <span class="badge text-bg-light text-primary rounded-pill ms-1">{{ $grouped->flatten()->count() }}</span>
            </button>
            @foreach($grouped as $grp => $items)
                @php
                    $conf = $groupConfig[$grp] ?? ['label' => ucfirst((string) $grp), 'icon' => 'bi-tag', 'badge' => 'text-bg-secondary'];
                @endphp
                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0" data-filter-group="{{ $grp }}" onclick="filterNotificationGroup(this, '{{ $grp }}')">
                    <i class="bi {{ $conf['icon'] }} me-1"></i>{{ $conf['label'] }} <span class="badge {{ $conf['badge'] }} rounded-pill ms-1">{{ $items->count() }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Lista de Itens Agrupados --}}
    <div class="notification-items-container">
        @foreach($grouped as $group => $items)
            @php
                $conf = $groupConfig[$group] ?? ['label' => ucfirst((string) $group), 'icon' => 'bi-tag', 'badge' => 'text-bg-secondary', 'color' => 'secondary'];
            @endphp
            <div class="notification-group-section" data-group-section="{{ $group }}">
                <div class="px-3 py-1 bg-body-tertiary small fw-bold text-uppercase text-muted d-flex justify-content-between align-items-center border-bottom border-top">
                    <span>
                        <i class="bi {{ $conf['icon'] }} me-1 text-{{ $conf['color'] }}"></i> {{ $conf['label'] }}
                    </span>
                    <span class="badge {{ $conf['badge'] }} rounded-pill">{{ $items->count() }}</span>
                </div>
                @foreach($items->take(6) as $n)
                    @php
                        $detailUrl = $isTenantPanel ? route('tenant.notifications.show', $n->id) : route('admin.notifications.show', $n->id);
                        $itemColor = $n->color ?? $conf['color'];
                        $itemIcon = $n->icon ?? $conf['icon'];
                    @endphp
                    <a href="{{ $detailUrl }}"
                       class="dropdown-item unread d-flex align-items-start gap-2 py-2 border-bottom text-wrap"
                       data-notification-item
                       data-id="{{ $n->id }}"
                       data-group="{{ $group }}"
                       data-url="{{ $detailUrl }}"
                       data-title="{{ e($n->title) }}"
                       data-message="{{ e((string) ($n->message ?? '')) }}"
                       style="white-space: normal;">
                        <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-{{ $itemColor }}-subtle text-{{ $itemColor }}" style="width: 32px; height: 32px;">
                            <i class="bi {{ $itemIcon }} fs-6"></i>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-dark small lh-sm">{{ $n->title }}</div>
                            @if($n->message)
                                <div class="small text-muted text-truncate-2 mt-1 lh-sm" style="font-size: 0.775rem;">
                                    {{ \Illuminate\Support\Str::limit($n->message, 90) }}
                                </div>
                            @endif
                            <div class="small text-muted mt-1" style="font-size: 0.7rem;">
                                <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}
                            </div>
                        </div>
                    </a>
                @endforeach
                @if($items->count() > 6)
                    <a href="{{ $indexUrl }}?group={{ $group }}" class="dropdown-item text-center small py-1 bg-light text-primary fw-semibold">
                        Ver mais {{ $items->count() - 6 }} de {{ $conf['label'] }} <i class="bi bi-chevron-right ms-1"></i>
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@else
    <div class="text-center text-muted p-4">
        <div class="rounded-circle bg-light d-inline-flex p-3 mb-2">
            <i class="bi bi-bell-slash fs-2 text-secondary"></i>
        </div>
        <p class="m-0 fw-semibold small">Sem novas notificações</p>
        <small class="text-muted" style="font-size: 0.75rem;">Você está em dia com todos os avisos.</small>
    </div>
@endif
