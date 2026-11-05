@php
    use App\Support\NotificationAudience;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Schema;

    $isTenantPanel = request()->is('painel*') && Auth::guard('web')->check();
    $rows = collect();
    $indexUrl = '#';
    $readAllUrl = '#';
    $feedUrl = '#';
    $soundUrl = '#';
    $readUrlTemplate = '#';
    $soundEnabled = true;

    try {
        $user = $isTenantPanel ? Auth::guard('web')->user() : Auth::guard('admin')->user();
        $query = $isTenantPanel
            ? NotificationAudience::tenantQuery(Auth::guard('web')->id())
            : NotificationAudience::adminQuery(Auth::guard('admin')->user());

        $totalUnread = (clone $query)->whereNull('read_at')->count();
        $rows = (clone $query)->whereNull('read_at')->orderByDesc('created_at')->limit(50)->get();
        $indexUrl = $isTenantPanel ? route('tenant.notifications') : route('admin.notifications');
        $readAllUrl = $isTenantPanel ? route('tenant.notifications.readAll') : route('admin.notifications.readAll');
        $feedUrl = $isTenantPanel ? route('tenant.notifications.feed') : route('admin.notifications.feed');
        $soundUrl = $isTenantPanel ? route('tenant.notifications.sound') : route('admin.notifications.sound');
        $readUrlTemplate = $isTenantPanel ? route('tenant.notifications.read', ['notification' => '__ID__']) : route('admin.notifications.read', ['notification' => '__ID__']);

        if ($user && Schema::hasColumn($user->getTable(), 'notification_sound_enabled')) {
            $soundEnabled = (bool) $user->notification_sound_enabled;
        }
    } catch (\Throwable) {}

    $grouped = $rows->groupBy('group');
    $totalUnread = $totalUnread ?? $rows->count();
    $latestId = (int) ($rows->max('id') ?? 0);
@endphp

<li class="nav-item dropdown notification-bell"
    data-notification-bell
    data-feed-endpoint="{{ $feedUrl }}"
    data-read-all-endpoint="{{ $readAllUrl }}"
    data-read-endpoint-template="{{ $readUrlTemplate }}"
    data-sound-endpoint="{{ $soundUrl }}"
    data-index-url="{{ $indexUrl }}"
    data-current-count="{{ $totalUnread }}"
    data-latest-id="{{ $latestId }}"
    data-sound-enabled="{{ $soundEnabled ? '1' : '0' }}">
    <a class="nav-link notification-bell__link" data-bs-toggle="dropdown" href="#" aria-label="Notificações" data-notification-toggle>
        <i class="bi bi-bell fs-5"></i>
        <span class="notification-bell__badge badge rounded-pill text-bg-danger {{ $totalUnread ? '' : 'd-none' }}"
              data-notification-count>{{ $totalUnread > 99 ? '99+' : $totalUnread }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 notification-dropdown-menu" style="width: 380px; max-width: min(380px, calc(100vw - 20px)); margin-top: 8px;">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-bell-fill text-primary"></i>
                <strong class="small text-uppercase fw-bold letter-spacing-1">Notificações</strong>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button"
                        class="btn btn-sm btn-link p-0 text-decoration-none text-muted"
                        data-notification-sound-toggle
                        title="{{ $soundEnabled ? 'Desativar som' : 'Ativar som' }}">
                    <i class="bi {{ $soundEnabled ? 'bi-volume-up-fill text-success' : 'bi-volume-mute-fill text-secondary' }} fs-6"></i>
                    <span class="visually-hidden">{{ $soundEnabled ? 'Som ativo' : 'Som inativo' }}</span>
                </button>
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none small {{ $totalUnread ? '' : 'd-none' }}" data-mark-all-read style="font-size: 0.75rem;">
                    Limpar todas
                </button>
            </div>
        </div>

        <div style="max-height: 400px; overflow-y: auto;" data-notification-list>
            @include('admin.partials.bell-items', [
                'grouped' => $grouped,
                'indexUrl' => $indexUrl,
                'isTenantPanel' => $isTenantPanel,
            ])
        </div>

        <div class="px-3 py-2 border-top bg-light text-center small text-muted d-flex justify-content-between align-items-center">
            <span data-notification-updated style="font-size: 0.725rem;"><i class="bi bi-arrow-repeat me-1"></i>{{ now()->format('H:i:s') }}</span>
            <a href="{{ $indexUrl }}" class="text-decoration-none fw-semibold small">Ver histórico completo <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</li>

@push('styles')
<style>
.notification-bell.notification-bell-pulse .notification-bell__link i {
    animation: bellShake 1.2s ease-in-out infinite;
    color: #dc3545 !important;
}
@keyframes bellShake {
    0%, 100% { transform: rotate(0); }
    15% { transform: rotate(14deg); }
    30% { transform: rotate(-14deg); }
    45% { transform: rotate(10deg); }
    60% { transform: rotate(-10deg); }
    75% { transform: rotate(4deg); }
    85% { transform: rotate(-4deg); }
}
.notification-dropdown-menu {
    border-radius: 12px !important;
    overflow: hidden;
    z-index: 1060;
}
.notification-dropdown-menu .dropdown-item:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}
@media (max-width: 575.98px) {
    .notification-dropdown-menu {
        position: fixed !important;
        top: 56px !important;
        left: 10px !important;
        right: 10px !important;
        width: auto !important;
        max-width: none !important;
    }
}
</style>
@endpush
