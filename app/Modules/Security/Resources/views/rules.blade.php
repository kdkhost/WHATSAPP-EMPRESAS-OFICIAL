@extends('admin.layouts.app')
@section('title', 'WAF - Regras')
@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Regras do WAF"
        subtitle="Configure cada regra de detecção: ative/desative, defina a ação (log, bloqueio ou desafio) e veja o impacto em tempo real."
        eyebrow="WAF Enterprise"
        class="mb-4"
    >
        <x-slot:actions>
        <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
            <a href="{{ route('admin.security.dashboard') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
            <a href="{{ route('admin.security.events') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-shield-exclamation me-1"></i> Eventos
            </a>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total de regras',  $stats['total'],    'primary',   'bi-sliders'],
                ['Habilitadas',      $stats['enabled'],  'success',   'bi-check-circle'],
                ['Desabilitadas',    $stats['disabled'], 'secondary', 'bi-pause-circle'],
                ['Bloqueando',       $stats['blocking'], 'danger',    'bi-shield-fill-x'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color, $icon])
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">{{ $label }}</div>
                        <div class="fs-4 fw-bold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ========== FILTROS + LISTA ========== --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-list-ul me-1 text-primary"></i> Regras configuradas</strong>
            <small class="text-muted">{{ count($rules) }} {{ count($rules) === 1 ? 'regra' : 'regras' }}</small>
        </div>

        {{-- Filtros --}}
        <div class="card-body border-bottom py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                        <input type="search" name="q" class="form-control form-control-sm ps-4" placeholder="Buscar por nome, codigo ou descricao..." value="{{ request('q') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos os status</option>
                        <option value="enabled"  @selected(request('status') === 'enabled')>Habilitadas</option>
                        <option value="disabled" @selected(request('status') === 'disabled')>Desabilitadas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="action" class="form-select form-select-sm">
                        <option value="">Todas as ações</option>
                        <option value="block"     @selected(request('action') === 'block')>Bloquear</option>
                        <option value="log"       @selected(request('action') === 'log')>Apenas log</option>
                        <option value="challenge" @selected(request('action') === 'challenge')>Desafio</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    @if(request()->hasAny(['q', 'status', 'action']))
                        <a href="{{ route('admin.security.rules') }}" class="btn btn-sm btn-outline-secondary" title="Limpar">
                            <i class="bi bi-x"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tabela --}}
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-security-rules" data-export-title="Regras WAF" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Regra</th>
                            <th class="desktop">Categoria</th>
                            <th class="min-tablet" data-priority="2">Severidade</th>
                            <th class="desktop text-center" style="width: 110px;">Eventos (7d)</th>
                            <th class="desktop" style="width: 160px;">Ação</th>
                            <th class="min-tablet text-center" data-priority="3" style="width: 80px;">Status</th>
                            <th class="all text-end no-export" data-priority="1" style="width: 80px;">Salvar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $r)
                        @php
                            $sevMap = [
                                'low'      => ['color' => 'secondary', 'icon' => 'bi-info-circle'],
                                'medium'   => ['color' => 'warning',   'icon' => 'bi-exclamation-circle'],
                                'high'     => ['color' => 'danger',    'icon' => 'bi-exclamation-triangle'],
                                'critical' => ['color' => 'dark',      'icon' => 'bi-exclamation-octagon'],
                            ];
                            $sev = $sevMap[$r->_meta['severity']] ?? $sevMap['medium'];
                        @endphp
                        <tr id="rule-{{ $r->code }}" data-rule="{{ $r->code }}" class="rule-row">
                            <td>
                                <div class="d-flex align-items-start gap-2">
                                    <div class="stat-icon bg-{{ $sev['color'] }}-subtle text-{{ $sev['color'] }}" style="width:36px;height:36px;font-size:.95rem;">
                                        <i class="bi {{ $r->_meta['icon'] }}"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <strong>{{ $r->_meta['name'] }}</strong>
                                            <code class="small text-muted">{{ $r->code }}</code>
                                        </div>
                                        <small class="text-muted d-block">{{ $r->_meta['description'] }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $r->_meta['category'] }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $sev['color'] }}">
                                    <i class="bi {{ $sev['icon'] }} me-1"></i>{{ ucfirst($r->_meta['severity']) }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($r->_events_7d > 0)
                                    <a href="{{ route('admin.security.events', ['rule' => $r->code]) }}" class="badge bg-info-subtle text-info border border-info-subtle text-decoration-none" title="Ver eventos">
                                        {{ number_format($r->_events_7d, 0, ',', '.') }}
                                    </a>
                                @else
                                    <small class="text-muted">—</small>
                                @endif
                            </td>
                            <td>
                                <select class="form-select form-select-sm rule-action" data-field="action">
                                    <option value="log"       @selected($r->action === 'log')>Apenas log</option>
                                    <option value="block"     @selected($r->action === 'block')>Bloquear</option>
                                    <option value="challenge" @selected($r->action === 'challenge')>Desafio</option>
                                </select>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-flex justify-content-center">
                                    <input class="form-check-input rule-enabled" type="checkbox" data-field="enabled" @checked($r->enabled)>
                                </div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary rule-save" title="Salvar">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-shield-x fs-1 d-block mb-2"></i>
                                Nenhuma regra encontrada com os filtros aplicados.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(count($rules) > 0)
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Use <strong>"Apenas log"</strong> para monitorar sem bloquear, ou <strong>"Bloquear"</strong> em produção.
            </small>
            <button type="button" class="btn btn-sm btn-success" id="btn-save-all">
                <i class="bi bi-check2-all me-1"></i> Salvar todas
            </button>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

async function saveRule(row, silent = false) {
    const code = row.dataset.rule;
    const action = row.querySelector('.rule-action').value;
    const enabled = row.querySelector('.rule-enabled').checked;
    const btn = row.querySelector('.rule-save');
    const orig = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    try {
        const r = await fetch(`{{ url('/admin/security/rules') }}/${code}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ action, enabled }),
        });
        if (r.ok) {
            if (!silent) window.toast.success(`Regra ${code} atualizada!`);
            row.classList.add('row-saved');
            setTimeout(() => row.classList.remove('row-saved'), 1500);
            return true;
        }
        if (!silent) window.toast.error(`Erro ao salvar ${code}.`);
        return false;
    } catch (err) {
        if (!silent) window.toast.error('Erro de rede.');
        return false;
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
}

// Salvar individual
document.querySelectorAll('.rule-save').forEach(btn => {
    btn.addEventListener('click', () => saveRule(btn.closest('.rule-row')));
});

// Salvar todas
document.getElementById('btn-save-all')?.addEventListener('click', async () => {
    const ok = await Swal.fire({
        icon: 'question', title: 'Salvar todas as regras?',
        text: 'Aplicará as configurações atuais de todas as regras visíveis.',
        showCancelButton: true, confirmButtonText: 'Salvar todas', cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;
    const rows = document.querySelectorAll('.rule-row');
    const btn = document.getElementById('btn-save-all');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
    let success = 0, failed = 0;
    for (const row of rows) {
        const r = await saveRule(row, true);
        r ? success++ : failed++;
    }
    btn.disabled = false;
    btn.innerHTML = orig;
    if (failed === 0) window.toast.success(`Todas as ${success} regras foram salvas!`);
    else window.toast.error(`${success} OK, ${failed} falha(s).`);
});

// Auto-save on toggle (UX rapido)
document.querySelectorAll('.rule-enabled').forEach(input => {
    input.addEventListener('change', () => {
        saveRule(input.closest('.rule-row'));
    });
});
</script>
@endpush

@push('head')
<style>
.rule-row.row-saved {
    background: color-mix(in srgb, var(--bs-success) 8%, transparent) !important;
    transition: background .8s;
}
[data-bs-theme="dark"] .rule-row.row-saved {
    background: rgba(25,135,84,.15) !important;
}
</style>
@endpush
