@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Notificações')

@push('head')
<style>
.notifications-page .notifications-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
}
.notifications-group-filter {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    align-items: center;
}
</style>
@endpush

@section('content')
@php
    $isTenantPanel = request()->is('painel*');
    $indexUrl = $isTenantPanel ? route('tenant.notifications') : route('admin.notifications');
    $readAllUrl = $isTenantPanel ? route('tenant.notifications.readAll') : route('admin.notifications.readAll');
    $groupMeta = [
        'cancellation' => ['label' => 'Cancelamentos', 'icon' => 'bi-x-circle-fill', 'color' => 'danger'],
        'settlement'   => ['label' => 'Quitações', 'icon' => 'bi-check-circle-fill', 'color' => 'success'],
        'registration' => ['label' => 'Cadastros', 'icon' => 'bi-person-plus-fill', 'color' => 'info'],
        'subscription' => ['label' => 'Assinaturas', 'icon' => 'bi-stars', 'color' => 'primary'],
        'billing'      => ['label' => 'Faturas & Cobranças', 'icon' => 'bi-receipt', 'color' => 'warning'],
        'whatsapp'     => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'color' => 'success'],
        'security'     => ['label' => 'Segurança', 'icon' => 'bi-shield-check', 'color' => 'danger'],
        'system'       => ['label' => 'Sistema', 'icon' => 'bi-gear-fill', 'color' => 'secondary'],
    ];
@endphp
<div class="container-fluid notifications-page">
    <x-ui.page-header
        title="Notificações"
        subtitle="Histórico do seu acesso agrupado por categoria, com leitura rápida do que ainda exige atenção operacional."
        eyebrow="Central de alertas">
        <x-slot:actions>
            @if(($stats['unread'] ?? 0) > 0)
                <button type="button" class="btn btn-outline-success" id="btn-read-all">
                    <i class="bi bi-check2-all me-1"></i> Marcar todas como lidas
                </button>
            @endif
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Não lidas</span>
                <span class="dashboard-note__value text-danger">{{ number_format($stats['unread'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Hoje</span>
                <span class="dashboard-note__value">{{ number_format($stats['today'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total" :value="number_format($stats['total'] ?? 0, 0, ',', '.')" icon="bi-bell" color="primary">
                <x-slot:meta>Notificações registradas para este acesso.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Não lidas" :value="number_format($stats['unread'] ?? 0, 0, ',', '.')" icon="bi-bell-fill" color="danger">
                <x-slot:meta>Itens que ainda não foram abertos nem reconhecidos.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Hoje" :value="number_format($stats['today'] ?? 0, 0, ',', '.')" icon="bi-calendar-event" color="info">
                <x-slot:meta>Eventos gerados nas últimas 24 horas.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Grupos" :value="count($groups)" icon="bi-grid-3x3-gap" color="success">
                <x-slot:meta>Categorias distintas disponíveis para filtro.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card title="Filtros por grupo" subtitle="Restrinja o histórico a uma categoria específica." class="mb-4">
        <div class="notifications-group-filter">
            <span class="small fw-semibold text-muted">Filtrar:</span>
            <a href="{{ $indexUrl }}" class="btn btn-sm {{ !request('group') ? 'btn-primary' : 'btn-outline-secondary' }}">Todas</a>
            @foreach($groupMeta as $key => $meta)
                <a href="{{ $indexUrl }}?group={{ $key }}" class="btn btn-sm {{ request('group') === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi {{ $meta['icon'] }} me-1"></i> {{ $meta['label'] }}
                    @if(isset($groups[$key]))
                        <span class="badge text-bg-light border ms-1">{{ $groups[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </x-ui.premium-card>

    @if(!empty($selectedNotification))
        @php
            $selectedUrl = $selectedNotification->url ?: null;
            if ($isTenantPanel && $selectedUrl && str_starts_with($selectedUrl, '/admin')) {
                $selectedUrl = null;
            }
            $selMeta = $groupMeta[$selectedNotification->group] ?? ['label' => ucfirst((string) $selectedNotification->group), 'icon' => 'bi-bell-fill', 'color' => 'primary'];
            $isSelUnread = empty($selectedNotification->read_at);
        @endphp
        <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden border-top border-4 border-{{ $selMeta['color'] }}">
            <div class="card-header bg-body-tertiary border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-flex align-items-center justify-content-center bg-{{ $selMeta['color'] }}-subtle text-{{ $selMeta['color'] }}" style="width: 38px; height: 38px;">
                        <i class="bi {{ $selMeta['icon'] }} fs-5"></i>
                    </span>
                    <div>
                        <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">
                            {{ $selMeta['label'] }} • Notificação #{{ $selectedNotification->id }}
                        </div>
                        <h5 class="mb-0 fw-bold text-body">{{ $selectedNotification->title }}</h5>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $selectedNotification->read_at ? 'success' : 'danger' }}-subtle text-{{ $selectedNotification->read_at ? 'success' : 'danger' }} border border-{{ $selectedNotification->read_at ? 'success' : 'danger' }}-subtle px-3 py-2 rounded-pill">
                        <i class="bi {{ $selectedNotification->read_at ? 'bi-check2-all' : 'bi-bell-fill' }} me-1"></i>
                        {{ $selectedNotification->read_at ? 'Lida em ' . \Carbon\Carbon::parse($selectedNotification->read_at)->format('d/m/Y H:i') : 'Nova (Não Lida)' }}
                    </span>
                    <a href="{{ $indexUrl }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-arrow-left me-1"></i> Voltar ao Histórico
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="p-3 border rounded-3 bg-body-tertiary h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-bold text-uppercase text-muted"><i class="bi bi-chat-left-text me-1"></i>Mensagem da Notificação</span>
                                <span class="small text-muted"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($selectedNotification->created_at)->diffForHumans() }}</span>
                            </div>
                            <div class="fs-6 text-body lh-base" style="white-space: pre-line;">{{ $selectedNotification->message ?: 'Sem mensagem complementar.' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="p-3 border rounded-3 bg-body-tertiary h-100 d-flex flex-column justify-content-between gap-3">
                            <div>
                                <div class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Dados da Notificação</div>
                                <div class="list-group list-group-flush small">
                                    <div class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between">
                                        <span class="text-muted">ID:</span>
                                        <span class="fw-semibold">#{{ $selectedNotification->id }}</span>
                                    </div>
                                    <div class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between">
                                        <span class="text-muted">Categoria:</span>
                                        <span class="badge text-bg-{{ $selMeta['color'] }}">{{ $selMeta['label'] }}</span>
                                    </div>
                                    <div class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between">
                                        <span class="text-muted">Tipo:</span>
                                        <code>{{ $selectedNotification->type }}</code>
                                    </div>
                                    <div class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between">
                                        <span class="text-muted">Recebida em:</span>
                                        <span class="fw-semibold">{{ \Carbon\Carbon::parse($selectedNotification->created_at)->format('d/m/Y H:i:s') }}</span>
                                    </div>
                                    <div class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between">
                                        <span class="text-muted">Status:</span>
                                        <span class="fw-semibold text-{{ $selectedNotification->read_at ? 'success' : 'danger' }}">{{ $selectedNotification->read_at ? 'Lida' : 'Nova' }}</span>
                                    </div>
                                </div>
                            </div>
                            @if($selectedUrl)
                                <div class="pt-2 border-top">
                                    <a href="{{ $selectedUrl }}" class="btn btn-primary w-100 shadow-sm rounded-pill py-2">
                                        <i class="bi bi-box-arrow-up-right me-2"></i> Abrir Página Relacionada
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-ui.premium-card title="Histórico" :subtitle="$notifications->total() . ' notificação(ões) encontradas.'">
        <div class="notifications-table p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-notifications" data-export-title="Notificações" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" style="width: 40px;" data-orderable="false"></th>
                        <th class="min-tablet">Grupo</th>
                        <th class="all" data-priority="1">Título</th>
                        <th class="desktop">Mensagem</th>
                        <th class="desktop">Data</th>
                        <th class="min-tablet text-center" data-priority="2">Status</th>
                        <th class="all text-end no-export" data-priority="1" data-orderable="false" style="width: 70px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $n)
                        @php
                            $meta = $groupMeta[$n->group] ?? ['label' => $n->group, 'icon' => 'bi-bell', 'color' => 'secondary'];
                            $isUnread = ! $n->read_at;
                            $detailUrl = $isTenantPanel ? route('tenant.notifications.show', $n->id) : route('admin.notifications.show', $n->id);
                        @endphp
                        <tr class="{{ $isUnread ? 'fw-semibold' : '' }}">
                            <td>
                                @if($isUnread)
                                    <span class="badge bg-danger rounded-circle p-1" style="width:10px;height:10px;" title="Não lida"></span>
                                @endif
                            </td>
                            <td>
                                <x-ui.status-badge :status="$meta['color'] === 'success' ? 'active' : ($meta['color'] === 'danger' ? 'overdue' : ($meta['color'] === 'info' ? 'sandbox' : 'secondary'))" :label="$meta['label']" />
                            </td>
                            <td>
                                <a href="{{ $detailUrl }}" class="text-decoration-none fw-semibold">{{ $n->title }}</a>
                            </td>
                            <td class="small text-muted">
                                <span class="text-truncate d-inline-block" style="max-width: 340px;" title="{{ $n->message }}">
                                    {{ \Illuminate\Support\Str::limit($n->message, 88) }}
                                </span>
                            </td>
                            <td class="small text-nowrap">
                                <div>{{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y H:i') }}</div>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</small>
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge :status="$n->read_at ? 'active' : 'pending'" :label="$n->read_at ? 'Lida' : 'Nova'" />
                            </td>
                            <td class="all text-end no-export">
                                <a href="{{ $detailUrl }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Visualizar dados sobre a notificação">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-bell-slash fs-1 d-block mb-2 opacity-50"></i>
                                Nenhuma notificação encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($notifications instanceof \Illuminate\Pagination\LengthAwarePaginator && $notifications->hasPages())
            <div class="card-footer py-2 mt-3 bg-transparent border-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">Mostrando {{ $notifications->firstItem() }} a {{ $notifications->lastItem() }} de {{ $notifications->total() }} registros</small>
                    {{ $notifications->links() }}
                </div>
            </div>
        @endif
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('btn-read-all')?.addEventListener('click', async () => {
    const ok = await Swal.fire({
        icon: 'question',
        title: 'Marcar todas como lidas?',
        showCancelButton: true,
        confirmButtonText: 'Sim',
        cancelButtonText: 'Cancelar'
    });

    if (!ok.isConfirmed) {
        return;
    }

    try {
        const r = await fetch('{{ $readAllUrl }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json'
            },
        });

        if (r.ok) {
            window.toast.success('Todas marcadas como lidas.');
            setTimeout(() => location.reload(), 600);
        } else {
            window.toast.error('Erro ao atualizar notificações.');
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});
</script>
@endpush
