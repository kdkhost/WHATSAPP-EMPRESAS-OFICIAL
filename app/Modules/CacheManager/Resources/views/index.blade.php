@extends('admin.layouts.app')
@section('title', 'Cache do Sistema')
@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Cache do Sistema"
        subtitle="Limpe, otimize e audite os caches do Laravel por dentro do painel, com acesso restrito a superadmin e 2FA."
        eyebrow="Superadmin protegido"
        class="mb-4"
    >
        <x-slot:actions>
        <div class="d-flex gap-2 align-items-center justify-content-end">
            <button type="button" class="btn btn-success btn-sm" id="btn-optimize" title="Compilar caches para produção">
                <i class="bi bi-rocket-takeoff me-1"></i> Otimizar
            </button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-clear-all">
                <i class="bi bi-trash me-1"></i> Limpar tudo
            </button>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs (estatísticas em tempo real) ========== --}}
    <div class="row g-3 mb-4">
        @php
            $hasOpcache = !empty($stats['opcache']['available']);
            $kpiCards = [
                'config'   => ['label' => 'Bootstrap',   'color' => 'primary',   'icon' => 'bi-gear'],
                'view'     => ['label' => 'Views',       'color' => 'info',      'icon' => 'bi-file-code'],
                'app'      => ['label' => 'Aplicação',   'color' => 'success',   'icon' => 'bi-app-indicator'],
                'logs'     => ['label' => 'Logs',        'color' => 'warning',   'icon' => 'bi-file-text'],
                'sessions' => ['label' => 'Sessions',    'color' => 'secondary', 'icon' => 'bi-person-badge'],
            ];

            if ($hasOpcache) {
                $kpiCards['opcache'] = ['label' => 'OPcache', 'color' => 'success', 'icon' => 'bi-cpu'];
            } else {
                $kpiCards['total'] = ['label' => 'Total Cache', 'color' => 'primary', 'icon' => 'bi-pie-chart'];
            }
        @endphp
        @foreach($kpiCards as $key => $card)
        <div class="col-6 col-md-4 col-lg-2">
            <div class="admin-premium-metric h-100">
                <div class="d-flex align-items-center gap-3">
                    <span class="stat-icon bg-{{ $card['color'] }}-subtle text-{{ $card['color'] }}"><i class="bi {{ $card['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <span class="dashboard-note__label">{{ $card['label'] }}</span>
                        <strong class="dashboard-note__value d-block" id="stat-{{ $key }}-size">{{ $stats[$key]['human'] ?? '0 B' }}</strong>
                        <span class="text-muted small d-block mt-1"><span id="stat-{{ $key }}-files">{{ $stats[$key]['files'] ?? 0 }}</span> {{ $key === 'opcache' ? 'scripts' : 'arquivos' }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- ========== LISTA DE CACHES ========== --}}
        <div class="col-lg-8">
            <div class="card shadow-sm premium-panel">
                <div class="card-header panel-heading">
                    <strong><i class="bi bi-list-ul me-1 text-primary"></i> Caches disponíveis</strong>
                    <small class="text-muted">Limpe individualmente sem refresh</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover m-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 60px;"></th>
                                    <th>Cache</th>
                                    <th>Descricao</th>
                                    <th class="text-end" style="width: 130px;">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($caches as $type => $cache)
                                <tr id="row-{{ $type }}">
                                    <td>
                                        <div class="stat-icon bg-primary-subtle text-primary" style="width:40px;height:40px;">
                                            <i class="bi {{ $cache['icon'] }}"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $cache['label'] }}</strong>
                                    </td>
                                    <td class="small text-muted">{{ $cache['desc'] }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary btn-clear" data-type="{{ $type }}">
                                            <i class="bi bi-eraser me-1"></i> Limpar
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== SIDEBAR ========== --}}
        <div class="col-lg-4">
            <div class="card shadow-sm premium-panel">
                <div class="card-header panel-heading">
                    <strong><i class="bi bi-clock-history me-1 text-info"></i> Limpeza automática</strong>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="auto-clear" {{ $autoEnabled ? 'checked' : '' }}>
                        <label class="form-check-label" for="auto-clear"><strong>Ativar limpeza diaria</strong></label>
                    </div>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Quando ativado, o cron limpa todos os caches <strong>todo dia as 03:00</strong> em segundo plano.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm mt-3 premium-panel">
                <div class="card-header panel-heading">
                    <strong><i class="bi bi-clock me-1 text-warning"></i> Últimas limpezas</strong>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small" id="history-list">
                        @forelse(\Illuminate\Support\Facades\Cache::get('cache_manager.history', []) as $entry)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-eraser text-primary me-1"></i> <strong>{{ ucfirst($entry['type']) }}</strong></span>
                            <span class="text-muted" style="font-size: .75rem;">{{ $entry['at'] }}</span>
                        </li>
                        @empty
                        <li class="list-group-item text-center text-muted py-3">
                            <small>Nenhuma limpeza registrada.</small>
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.panel-heading { background: rgba(148,163,184,.06); border-bottom: 1px solid rgba(148,163,184,.20); display: flex; justify-content: space-between; gap: 12px; align-items: center; }
</style>
@endpush

@push('scripts')
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const baseUrl = '{{ url("/admin/cache") }}';

    async function refreshStats() {
        try {
            const r = await fetch(`${baseUrl}/stats`, {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            });
            const data = await r.json();
            Object.keys(data).forEach(key => {
                const sizeEl = document.getElementById(`stat-${key}-size`);
                const filesEl = document.getElementById(`stat-${key}-files`);
                if (sizeEl) sizeEl.textContent = data[key].human;
                if (filesEl) filesEl.textContent = data[key].files;
            });
        } catch (e) {}
    }

    document.querySelectorAll('.btn-clear').forEach(btn => {
        btn.addEventListener('click', async () => {
            const type = btn.dataset.type;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const r = await fetch(`${baseUrl}/${type}`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                });
                const data = await r.json();
                if (data.ok) { window.toast.success(`${data.message} (${data.duration_ms}ms)`); refreshStats(); }
                else window.toast.error(data.message || 'Erro ao limpar.');
            } catch (e) { window.toast.error('Erro de rede.'); }
            finally { btn.disabled = false; btn.innerHTML = original; }
        });
    });

    document.getElementById('btn-clear-all').addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'question', title: 'Limpar TODOS os caches?',
            text: 'Limpa config, rotas, views, app, eventos e classes compiladas.',
            showCancelButton: true, confirmButtonText: 'Sim, limpar tudo', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!ok.isConfirmed) return;
        const btn = document.getElementById('btn-clear-all');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Limpando...';
        try {
            const r = await fetch(`${baseUrl}/clear-all`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const data = await r.json();
            if (data.ok) { window.toast.success(`Tudo limpo em ${data.duration_ms}ms!`); refreshStats(); }
            else window.toast.error(data.message || 'Erro.');
        } catch (e) { window.toast.error('Erro de rede.'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    document.getElementById('btn-optimize').addEventListener('click', async () => {
        const btn = document.getElementById('btn-optimize');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Otimizando...';
        try {
            const r = await fetch(`${baseUrl}/optimize`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const data = await r.json();
            if (data.ok) { window.toast.success(`Otimizado em ${data.duration_ms}ms!`); refreshStats(); }
            else window.toast.error(data.message || 'Erro.');
        } catch (e) { window.toast.error('Erro de rede.'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    document.getElementById('auto-clear').addEventListener('change', async (e) => {
        const enabled = e.target.checked;
        try {
            const r = await fetch(`${baseUrl}/auto`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ enabled })
            });
            const data = await r.json();
            if (data.ok) window.toast.success(data.message);
            else { window.toast.error(data.message); e.target.checked = !enabled; }
        } catch (err) { window.toast.error('Erro de rede.'); e.target.checked = !enabled; }
    });

    setInterval(refreshStats, 30000);
})();
</script>
@endpush
