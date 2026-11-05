@extends('admin.layouts.app')
@section('title', 'WAF - Allowlist')
@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Allowlist"
        subtitle="IPs, faixas CIDR e User-Agents sempre permitidos. Útil para liberar admins, devs e serviços confiáveis sem bloqueios automáticos."
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
            <a href="{{ route('admin.security.blocklist') }}" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-slash-circle me-1"></i> Blocklist
            </a>
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulk">
                <i class="bi bi-list-task me-1"></i> Importar em massa
            </button>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total na allowlist', $stats['total'], 'success', 'bi-check-circle'],
                ['IPs individuais',    $stats['ips'],   'primary', 'bi-router'],
                ['Faixas CIDR',        $stats['cidrs'], 'info',    'bi-diagram-3'],
                ['User-Agents',        $stats['uas'],   'warning', 'bi-window'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color, $icon])
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card :label="$label" :value="number_format($value, 0, ',', '.')" :color="$color" :icon="$icon" class="h-100" />
        </div>
        @endforeach
    </div>

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-info-circle-fill fs-3 text-info"></i>
            <div class="small mb-0">
                <strong>Importante:</strong> itens da allowlist <strong>têm prioridade sobre a blocklist</strong> e regras WAF. Use com cuidado para não expor o sistema a IPs comprometidos.
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ========== ADICIONAR + DISTRIBUICAO ========== --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-plus-circle me-1 text-success"></i> Novo item confiavel</strong>
                </div>
                <div class="card-body">
                    <form id="frm-allow">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Tipo</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="ip">IP individual</option>
                                <option value="cidr">CIDR (faixa)</option>
                                <option value="user_agent">User-Agent</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Valor</label>
                            <input name="value" class="form-control form-control-sm" placeholder="Ex.: 192.168.0.1" required>
                            <small class="text-muted" id="value-hint">Endereço IP no formato IPv4 ou IPv6</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Apelido (opcional)</label>
                            <input name="label" class="form-control form-control-sm" maxlength="120" placeholder="Ex.: Escritorio - Joao">
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-check-circle me-1"></i> Adicionar a allowlist
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
                            'ip'         => ['label' => 'IP individual', 'icon' => 'bi-router',    'color' => 'primary'],
                            'cidr'       => ['label' => 'CIDR (faixa)',  'icon' => 'bi-diagram-3', 'color' => 'info'],
                            'user_agent' => ['label' => 'User-Agent',    'icon' => 'bi-window',    'color' => 'warning'],
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
                    <strong><i class="bi bi-list-ul me-1 text-primary"></i> Itens confiáveis</strong>
                    <small class="text-muted">{{ $list->total() }} {{ $list->total() === 1 ? 'item' : 'itens' }}</small>
                </div>

                {{-- Filtros --}}
                <div class="card-body border-bottom py-2">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <select name="type" class="form-select form-select-sm">
                                <option value="">Todos os tipos</option>
                                <option value="ip"         @selected(request('type') === 'ip')>IP</option>
                                <option value="cidr"       @selected(request('type') === 'cidr')>CIDR</option>
                                <option value="user_agent" @selected(request('type') === 'user_agent')>User-Agent</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                                <input type="search" name="q" class="form-control form-control-sm ps-4" placeholder="Buscar valor ou apelido..." value="{{ request('q') }}">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="bi bi-search"></i>
                            </button>
                            @if(request()->hasAny(['type', 'q']))
                                <a href="{{ route('admin.security.allowlist') }}" class="btn btn-sm btn-outline-secondary" title="Limpar">
                                    <i class="bi bi-x"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-security-allowlist" data-export-title="Allowlist WAF" style="width: 100% !important;">
                            <thead class="table-light">
                                <tr>
                                    <th class="min-tablet" data-priority="2">Tipo</th>
                                    <th class="all" data-priority="1">Valor</th>
                                    <th class="desktop">Apelido</th>
                                    <th class="desktop">Adicionado</th>
                                    <th class="all text-end no-export" data-priority="1" style="min-width: 70px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($list as $r)
                                @php
                                    $meta = $typeMeta[$r->type] ?? ['label' => $r->type, 'icon' => 'bi-shield', 'color' => 'secondary'];
                                @endphp
                                <tr id="al-{{ $r->id }}">
                                    <td>
                                        <span class="badge bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }} border border-{{ $meta['color'] }}-subtle">
                                            <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <code class="fw-semibold text-break">{{ $r->value }}</code>
                                    </td>
                                    <td class="small">
                                        @if($r->label)
                                            <span class="text-truncate d-inline-block" style="max-width: 220px;" title="{{ $r->label }}">{{ $r->label }}</span>
                                        @else
                                            <small class="text-muted">—</small>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($r->created_at)
                                            <div class="fw-semibold">{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</div>
                                            <small class="text-muted">{{ \Carbon\Carbon::parse($r->created_at)->diffForHumans() }}</small>
                                        @else
                                            —
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
                                        <i class="bi bi-shield-exclamation fs-1 d-block mb-2"></i>
                                        Allowlist vazia.
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
                    <h5 class="modal-title"><i class="bi bi-list-task me-2"></i> Importar em massa</h5>
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
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Valores (um por linha)</label>
                            <textarea name="values" class="form-control font-monospace" rows="10" placeholder="192.168.0.1&#10;10.0.0.0/8&#10;201.10.20.30" required style="font-size: .85rem;"></textarea>
                            <small class="text-muted">Aceita um valor por linha. Duplicatas são ignoradas automaticamente.</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-uppercase text-muted">Apelido comum (opcional)</label>
                            <input name="label" class="form-control" maxlength="120" placeholder="Ex.: Escritório matriz">
                            <small class="text-muted">Aplicado a todos os itens importados.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Importar
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

const hints = {
    ip: 'Endereço IP no formato IPv4 ou IPv6',
    cidr: 'Faixa CIDR (ex.: 10.0.0.0/8 ou 2001:db8::/32)',
    user_agent: 'Substring que será permitida no User-Agent'
};
const placeholders = {
    ip: 'Ex.: 192.168.0.1',
    cidr: 'Ex.: 10.0.0.0/8',
    user_agent: 'Ex.: MeuMonitor/1.0'
};
document.querySelector('#frm-allow [name=type]').addEventListener('change', e => {
    document.getElementById('value-hint').textContent = hints[e.target.value];
    document.querySelector('#frm-allow [name=value]').placeholder = placeholders[e.target.value];
});

// Adicionar individual
document.getElementById('frm-allow').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch(`{{ route('admin.security.allowlist.store') }}`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        if (r.ok) { window.toast.success('Adicionado a allowlist!'); setTimeout(() => location.reload(), 600); }
        else window.toast.error('Erro ao adicionar.');
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
        const r = await fetch(`{{ route('admin.security.allowlist.bulk') }}`, {
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
        icon: 'warning', title: 'Remover da allowlist?',
        text: 'Este item poderá ser bloqueado novamente pelas regras WAF.',
        showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch(`{{ url('/admin/security/allowlist') }}/${btn.dataset.del}`, {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        if (r.ok) { document.getElementById(`al-${btn.dataset.del}`).remove(); window.toast.success('Removido!'); }
        else window.toast.error('Erro ao remover.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});
</script>
@endpush
