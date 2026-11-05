@extends('admin.layouts.app')
@section('title', 'WAF - Eventos')
@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Eventos de segurança"
        subtitle="Histórico completo de eventos detectados pelo WAF. Filtre por regra, severidade, IP ou período e revise ações de bloqueio."
        eyebrow="WAF Enterprise"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                <a href="{{ route('admin.security.dashboard') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
                <a href="{{ route('admin.security.blocklist') }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-slash-circle me-1"></i> Blocklist
                </a>
                <a href="{{ route('admin.security.rules') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-sliders me-1"></i> Regras
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Eventos (24h)', $stats['total_24h'], 'primary', 'bi-activity'],
                ['Bloqueios (24h)', $stats['blocked_24h'], 'danger', 'bi-shield-fill-x'],
                ['Críticos (24h)', $stats['critical_24h'], 'warning', 'bi-exclamation-octagon'],
                ['IPs únicos (24h)', $stats['unique_ips_24h'], 'info', 'bi-geo-alt'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color, $icon])
            <div class="col-md-6 col-lg-3">
                <x-ui.stats-card :label="$label" :value="number_format($value, 0, ',', '.')" :color="$color" :icon="$icon" class="h-100" />
            </div>
        @endforeach
    </div>

    <x-ui.premium-card title="Filtros" subtitle="Refine a auditoria por regra, severidade, ação, IP e período." class="mb-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-uppercase text-muted">Regra</label>
                <select name="rule" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach(['sqli' => 'SQL Injection', 'xss' => 'XSS', 'lfi' => 'LFI', 'rfi' => 'RFI', 'badbot' => 'Bad Bot', 'ratelimit' => 'Rate Limit', 'blocklist' => 'Blocklist'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(request('rule') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-uppercase text-muted">Severidade</label>
                <select name="severity" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="critical" @selected(request('severity') === 'critical')>Critical</option>
                    <option value="high" @selected(request('severity') === 'high')>High</option>
                    <option value="medium" @selected(request('severity') === 'medium')>Medium</option>
                    <option value="low" @selected(request('severity') === 'low')>Low</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-uppercase text-muted">Ação</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="blocked" @selected(request('action') === 'blocked')>Blocked</option>
                    <option value="challenged" @selected(request('action') === 'challenged')>Challenged</option>
                    <option value="logged" @selected(request('action') === 'logged')>Logged</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-uppercase text-muted">IP</label>
                <input name="ip" class="form-control form-control-sm" placeholder="0.0.0.0" value="{{ request('ip') }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-uppercase text-muted">De</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-uppercase text-muted">Até</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('admin.security.events') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x me-1"></i> Limpar
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </x-ui.premium-card>

    <x-ui.premium-card title="Eventos" subtitle="{{ $events->total() }} {{ $events->total() === 1 ? 'evento' : 'eventos' }}">
        <div class="p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-security-events" data-export-title="Eventos de Segurança" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1" style="width: 130px;">Data</th>
                        <th class="min-tablet">Regra</th>
                        <th class="min-tablet" data-priority="2">Severidade</th>
                        <th class="desktop">Ação</th>
                        <th class="all">IP</th>
                        <th class="desktop" style="width: 70px;">País</th>
                        <th class="desktop" style="width: 70px;">Método</th>
                        <th class="desktop">URI</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 80px; min-width: 75px; max-width: 90px; white-space: nowrap;">Bloquear</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $e)
                        @php
                            $severityMap = [
                                'low' => ['color' => 'secondary', 'icon' => 'bi-info-circle'],
                                'medium' => ['color' => 'warning', 'icon' => 'bi-exclamation-circle'],
                                'high' => ['color' => 'danger', 'icon' => 'bi-exclamation-triangle'],
                                'critical' => ['color' => 'dark', 'icon' => 'bi-exclamation-octagon'],
                            ];
                            $sev = $severityMap[$e->severity] ?? ['color' => 'secondary', 'icon' => 'bi-info-circle'];
                            $actionMap = [
                                'blocked' => ['color' => 'danger', 'icon' => 'bi-shield-fill-x'],
                                'challenged' => ['color' => 'warning', 'icon' => 'bi-question-circle'],
                                'logged' => ['color' => 'info', 'icon' => 'bi-eye'],
                            ];
                            $act = $actionMap[$e->action_taken] ?? ['color' => 'secondary', 'icon' => 'bi-circle'];
                            $methodColor = match($e->method) { 'GET' => 'success', 'POST' => 'primary', 'PUT' => 'info', 'DELETE' => 'danger', default => 'secondary' };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold text-nowrap">{{ \Carbon\Carbon::parse($e->created_at)->format('d/m H:i:s') }}</div>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($e->created_at)->diffForHumans(['short' => true]) }}</small>
                            </td>
                            <td><code class="fw-semibold">{{ $e->rule_code }}</code></td>
                            <td>
                                <span class="badge bg-{{ $sev['color'] }}">
                                    <i class="bi {{ $sev['icon'] }} me-1"></i>{{ ucfirst($e->severity) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $act['color'] }}-subtle text-{{ $act['color'] }} border border-{{ $act['color'] }}-subtle">
                                    <i class="bi {{ $act['icon'] }} me-1"></i>{{ ucfirst($e->action_taken) }}
                                </span>
                            </td>
                            <td>
                                <code class="text-nowrap">{{ $e->ip }}</code>
                                <a href="{{ route('admin.security.events', ['ip' => $e->ip]) }}" class="ms-1 text-muted small" title="Filtrar este IP">
                                    <i class="bi bi-funnel"></i>
                                </a>
                            </td>
                            <td>
                                @if($e->country)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" title="{{ $e->country }}">{{ $e->country }}</span>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td>
                                @if($e->method)
                                    <span class="badge bg-{{ $methodColor }}-subtle text-{{ $methodColor }} border border-{{ $methodColor }}-subtle">{{ $e->method }}</span>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td>
                                <code class="small text-truncate d-inline-block" style="max-width: 280px;" title="{{ $e->uri }}">{{ $e->uri ?? '-' }}</code>
                            </td>
                            <td class="all text-end no-export" style="width: 80px; min-width: 75px; max-width: 90px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button class="btn btn-sm btn-outline-danger p-1 px-2" data-block-ip="{{ $e->ip }}" title="Adicionar IP à blocklist">
                                        <i class="bi bi-shield-fill-x"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-shield-check fs-1 d-block mb-2 text-success"></i>
                                Nenhum evento encontrado nos filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events instanceof \Illuminate\Pagination\LengthAwarePaginator && $events->hasPages())
            <x-slot:footer>{{ $events->links() }}</x-slot:footer>
        @endif
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

document.querySelectorAll('[data-block-ip]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const ip = btn.dataset.blockIp;
        const ok = await Swal.fire({
            icon: 'warning',
            title: `Bloquear o IP ${ip}?`,
            text: 'Esse IP será adicionado à blocklist permanente.',
            showCancelButton: true,
            confirmButtonText: 'Bloquear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });
        if (!ok.isConfirmed) return;
        const fd = new FormData();
        fd.append('type', 'ip');
        fd.append('value', ip);
        fd.append('reason', 'Bloqueio manual via lista de eventos');
        try {
            const r = await fetch(`{{ route('admin.security.blocklist.store') }}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd
            });
            if (r.ok) {
                window.toast.success(`IP ${ip} bloqueado!`);
                document.querySelectorAll(`[data-block-ip="${ip}"]`).forEach(b => {
                    b.disabled = true;
                    b.classList.replace('btn-outline-danger', 'btn-success');
                    b.innerHTML = '<i class="bi bi-check2"></i>';
                });
            } else {
                window.toast.error('Erro ao bloquear.');
            }
        } catch (err) {
            window.toast.error('Erro de rede.');
        }
    });
});
</script>
@endpush
