@extends('admin.layouts.app')
@section('title', 'WAF - Blocklist')
@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Blocklist"
        subtitle="IPs, faixas CIDR, User-Agents e países bloqueados pelo WAF. Pode ser permanente ou temporário com expiração automática."
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
            <a href="{{ route('admin.security.allowlist') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-check-circle me-1"></i> Allowlist
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulk">
                <i class="bi bi-list-task me-1"></i> Importar em massa
            </button>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total',          $stats['total'],     'primary',   'bi-slash-circle'],
                ['Ativos',         $stats['active'],    'danger',    'bi-shield-fill-x'],
                ['Permanentes',    $stats['permanent'], 'warning',   'bi-lock-fill'],
                ['Expirados',      $stats['expired'],   'secondary', 'bi-clock-history'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color, $icon])
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card :label="$label" :value="number_format($value, 0, ',', '.')" :color="$color" :icon="$icon" class="h-100" />
        </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- ========== ADICIONAR / DISTRIBUICAO ========== --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-plus-circle me-1 text-danger"></i> Novo bloqueio</strong>
                </div>
                <div class="card-body">
                    <form id="frm-block">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Tipo</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="ip">IP individual</option>
                                <option value="cidr">CIDR (faixa)</option>
                                <option value="user_agent">User-Agent</option>
                                <option value="country">País (código ISO 2 letras)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Valor</label>
                            <input name="value" class="form-control form-control-sm" placeholder="Ex.: 192.168.0.1" required>
                            <small class="text-muted" id="value-hint">Endereço IP no formato IPv4 ou IPv6</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Motivo</label>
                            <input name="reason" class="form-control form-control-sm" placeholder="Bot suspeito" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Expira em (opcional)</label>
                            <input type="datetime-local" name="expires_at" class="form-control form-control-sm">
                            <small class="text-muted">Em branco = permanente</small>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-shield-fill-x me-1"></i> Bloquear
                        </button>
                    </form>
                </div>
            </div>

            {{-- Distribuicao por tipo --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                        <strong><i class="bi bi-pie-chart me-1 text-info"></i> Distribuição por tipo</strong>
                </div>
                <div class="card-body">
                    @php
                        $typeMeta = [
                            'ip'         => ['label' => 'IP individual', 'icon' => 'bi-router', 'color' => 'primary'],
                            'cidr'       => ['label' => 'CIDR (faixa)',  'icon' => 'bi-diagram-3', 'color' => 'info'],
                            'user_agent' => ['label' => 'User-Agent',    'icon' => 'bi-window',    'color' => 'warning'],
                            'country'    => ['label' => 'País',          'icon' => 'bi-globe',     'color' => 'success'],
                        ];
                    @endphp
                    @foreach($typeMeta as $key => $meta)
                        @php $count = $byType[$key] ?? 0; @endphp
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="stat-icon bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }}" style="width:32px;height:32px;font-size:.85rem;">
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                </span>
                                <span class="small">{{ $meta['label'] }}</span>
                            </div>
                            <span class="badge bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }} border border-{{ $meta['color'] }}-subtle">
                                {{ number_format($count, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ========== TABELA ========== --}}
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-list-ul me-1 text-primary"></i> Bloqueios</strong>
                    <small class="text-muted">{{ $list->total() }} {{ $list->total() === 1 ? 'item' : 'itens' }}</small>
                </div>

                {{-- Filtros --}}
                <div class="card-body border-bottom py-2">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <select name="type" class="form-select form-select-sm">
                                <option value="">Todos os tipos</option>
                                <option value="ip"         @selected(request('type') === 'ip')>IP</option>
                                <option value="cidr"       @selected(request('type') === 'cidr')>CIDR</option>
                                <option value="user_agent" @selected(request('type') === 'user_agent')>User-Agent</option>
                                <option value="country"    @selected(request('type') === 'country')>País</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos status</option>
                                <option value="active"    @selected(request('status') === 'active')>Ativos</option>
                                <option value="permanent" @selected(request('status') === 'permanent')>Permanentes</option>
                                <option value="temporary" @selected(request('status') === 'temporary')>Temporários</option>
                                <option value="expired"   @selected(request('status') === 'expired')>Expirados</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                                <input type="search" name="q" class="form-control form-control-sm ps-4" placeholder="Buscar valor..." value="{{ request('q') }}">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="bi bi-search"></i>
                            </button>
                            @if(request()->hasAny(['type', 'status', 'q']))
                                <a href="{{ route('admin.security.blocklist') }}" class="btn btn-sm btn-outline-secondary" title="Limpar">
                                    <i class="bi bi-x"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-security-blocklist" data-export-title="Blocklist WAF" style="width: 100% !important;">
                            <thead class="table-light">
                                <tr>
                                    <th class="min-tablet" data-priority="2">Tipo</th>
                                    <th class="all" data-priority="1">Valor</th>
                                    <th class="desktop">Motivo</th>
                                    <th class="desktop">Expira</th>
                                    <th class="all text-end no-export" data-priority="1" style="min-width: 70px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($list as $r)
                                @php
                                    $meta = $typeMeta[$r->type] ?? ['label' => $r->type, 'icon' => 'bi-shield', 'color' => 'secondary'];
                                    $expired = $r->expires_at && \Carbon\Carbon::parse($r->expires_at)->isPast();
                                @endphp
                                <tr id="bl-{{ $r->id }}" class="{{ $expired ? 'opacity-50' : '' }}">
                                    <td>
                                        <span class="badge bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }} border border-{{ $meta['color'] }}-subtle">
                                            <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <code class="fw-semibold text-break">{{ $r->value }}</code>
                                    </td>
                                    <td class="small text-muted">
                                        @if($r->reason)
                                            <span class="text-truncate d-inline-block" style="max-width: 220px;" title="{{ $r->reason }}">{{ $r->reason }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($r->expires_at)
                                            @if($expired)
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                    <i class="bi bi-clock-history me-1"></i> Expirado
                                                </span>
                                            @else
                                                <div class="fw-semibold">{{ \Carbon\Carbon::parse($r->expires_at)->format('d/m H:i') }}</div>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($r->expires_at)->diffForHumans() }}</small>
                                            @endif
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                <i class="bi bi-lock-fill me-1"></i> Permanente
                                            </span>
                                        @endif
                                    </td>
                                    <td class="all text-end no-export">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                            <button class="btn btn-sm btn-outline-danger p-1 px-2" data-del="{{ $r->id }}" title="Remover">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="bi bi-shield-check fs-1 d-block mb-2 text-success"></i>
                                        Blocklist vazia.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($list instanceof \Illuminate\Pagination\LengthAwarePaginator && $list->hasPages())
                    <div class="card-footer">{{ $list->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Importar em massa ========== --}}
<div class="modal fade" id="modalBulk" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-bulk">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-list-task me-2"></i> Importar bloqueios em massa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Tipo</label>
                            <select name="type" class="form-select">
                                <option value="ip">IP individual</option>
                                <option value="cidr">CIDR (faixa)</option>
                                <option value="user_agent">User-Agent</option>
                                <option value="country">País (código ISO 2 letras)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Valores (um por linha)</label>
                            <textarea name="values" class="form-control font-monospace" rows="10" placeholder="192.168.0.1&#10;10.0.0.0/8&#10;203.0.113.45" required style="font-size: .85rem;"></textarea>
                            <small class="text-muted">Aceita um valor por linha. Duplicatas são ignoradas automaticamente.</small>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Motivo (opcional)</label>
                            <input name="reason" class="form-control" maxlength="255" placeholder="Lista de bots conhecidos">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Expira em (opcional)</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-shield-fill-x me-1"></i> Importar e bloquear
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const bulkModal = new bootstrap.Modal(document.getElementById('modalBulk'));

// Hint dinamico do campo Valor segundo o tipo
const hints = {
    ip: 'Endereço IP no formato IPv4 ou IPv6',
    cidr: 'Faixa CIDR (ex.: 10.0.0.0/8 ou 2001:db8::/32)',
    user_agent: 'Substring que será bloqueada se aparecer no User-Agent',
    country: 'Código ISO 2 letras (ex.: BR, US, CN, RU)'
};
const placeholders = {
    ip: 'Ex.: 192.168.0.1',
    cidr: 'Ex.: 10.0.0.0/8',
    user_agent: 'Ex.: BadBot/1.0',
    country: 'Ex.: CN'
};
document.querySelector('#frm-block [name=type]').addEventListener('change', e => {
    document.getElementById('value-hint').textContent = hints[e.target.value];
    document.querySelector('#frm-block [name=value]').placeholder = placeholders[e.target.value];
});

// Adicionar individual
document.getElementById('frm-block').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch(`{{ route('admin.security.blocklist.store') }}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        if (r.ok) { window.toast.success('Bloqueio adicionado!'); setTimeout(() => location.reload(), 600); }
        else window.toast.error('Erro ao bloquear.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});

// Importar em massa
document.getElementById('frm-bulk').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const btn = e.target.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importando...';
    try {
        const r = await fetch(`{{ route('admin.security.blocklist.bulk') }}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        const d = await r.json();
        if (d.ok) {
            window.toast.success(`${d.imported} importados${d.skipped ? `, ${d.skipped} ignorados` : ''}!`);
            bulkModal.hide();
            setTimeout(() => location.reload(), 800);
        } else window.toast.error('Erro ao importar.');
    } catch (err) { window.toast.error('Erro de rede.'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
});

// Excluir
document.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => {
    const ok = await Swal.fire({
        icon: 'warning', title: 'Remover bloqueio?',
        text: 'O IP/valor poderá acessar o sistema novamente.',
        showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch(`{{ url('/admin/security/blocklist') }}/${btn.dataset.del}`, {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        if (r.ok) { document.getElementById(`bl-${btn.dataset.del}`).remove(); window.toast.success('Removido!'); }
        else window.toast.error('Erro ao remover.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});
</script>
@endpush
