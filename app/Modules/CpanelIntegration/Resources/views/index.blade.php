@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'WHM/cPanel')
@section('content')
@php
    $stats = [
        'total'    => $servers->count(),
        'active'   => $servers->where('active', true)->count(),
        'inactive' => $servers->where('active', false)->count(),
        'errors'   => $servers->where('last_check_status', 'error')->count(),
    ];
@endphp
<div class="container-fluid">

    @php
        $isTenant = request()->is('painel*');
        $cpanelBaseUrl = $isTenant ? url('/painel/cpanel') : url('/admin/cpanel');
        $cpanelStoreRoute = $isTenant ? route('tenant.cpanel.store') : route('admin.cpanel.store');
    @endphp
    <x-ui.page-header
        title="WHM/cPanel"
        subtitle="Conecte servidores WHM/cPanel para suspender contas automaticamente quando faturas atrasam, listar contas e auditar ações."
        :eyebrow="$isTenant ? 'Minha Conta' : 'Superadmin'"
        class="mb-4"
    >
        <x-slot:actions>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSrv">
            <i class="bi bi-plus-lg me-1"></i> Adicionar servidor
        </button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total de servidores', $stats['total'],    'primary', 'bi-server'],
                ['Ativos',              $stats['active'],   'success', 'bi-check-circle'],
                ['Inativos',            $stats['inactive'], 'secondary','bi-pause-circle'],
                ['Com erro',            $stats['errors'],   'danger',  'bi-x-circle'],
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

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-info-circle-fill fs-3 text-info"></i>
            <div class="small mb-0">
                <strong>Pre-requisitos:</strong> Crie um <strong>API Token</strong> em <code>WHM &gt; Development &gt; Manage API Tokens</code> com permissoes
                <code>list-accts</code>, <code>suspend-acct</code>, <code>unsuspend-acct</code>, <code>create-acct</code>.
                Permite suspensão automática em atrasos.
            </div>
        </div>
    </div>

    {{-- ========== GRID DE SERVIDORES ========== --}}
    <div class="row g-3">
        @forelse($servers as $s)
        <div class="col-md-6 col-lg-4" id="srv-{{ $s->id }}">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                        <div class="min-w-0">
                            <h6 class="mb-1 text-truncate">{{ $s->name }}</h6>
                            <code class="small text-muted">{{ $s->hostname }}:{{ $s->port }}</code>
                        </div>
                        <span class="badge bg-{{ $s->active ? 'success' : 'secondary' }}">
                            <i class="bi bi-{{ $s->active ? 'check-circle' : 'pause-circle' }} me-1"></i>
                            {{ $s->active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mb-3 small">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bi bi-cpu me-1"></i>{{ strtoupper($s->type) }}
                        </span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                            <i class="bi bi-person me-1"></i>{{ $s->username }}
                        </span>
                        @if($s->last_check_status === 'ok')
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check2-circle me-1"></i>OK
                            </span>
                        @elseif($s->last_check_status === 'error')
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bi bi-x-circle me-1"></i>Erro
                            </span>
                        @endif
                    </div>

                    <div class="small text-muted mb-3">
                        <i class="bi bi-clock-history me-1"></i> Ultimo check:
                        {{ $s->last_check_at ? \Illuminate\Support\Carbon::parse($s->last_check_at)->diffForHumans() : 'Nunca' }}
                    </div>

                    <div class="d-flex gap-1 flex-wrap mt-auto">
                        <a href="{{ $cpanelBaseUrl }}/{{ $s->id }}/accounts" class="btn btn-sm btn-outline-primary" title="Listar contas">
                            <i class="bi bi-list-ul me-1"></i> Contas
                        </a>
                        <button class="btn btn-sm btn-outline-warning" data-action-suspend="{{ $s->id }}" title="Suspender">
                            <i class="bi bi-pause-circle me-1"></i> Suspender
                        </button>
                        <button class="btn btn-sm btn-outline-success" data-action-unsuspend="{{ $s->id }}" title="Reativar">
                            <i class="bi bi-play-circle me-1"></i> Reativar
                        </button>
                        <a href="{{ $cpanelBaseUrl }}/{{ $s->id }}/logs" class="btn btn-sm btn-outline-info" title="Logs">
                            <i class="bi bi-clock-history me-1"></i> Logs
                        </a>
                        <button class="btn btn-sm btn-outline-danger ms-auto" data-del="{{ $s->id }}" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-server fs-1 d-block mb-2"></i>
                    Nenhum servidor cPanel/WHM configurado.
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSrv">
                            <i class="bi bi-plus-lg me-1"></i> Adicionar primeiro servidor
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endforelse
    </div>
</div>

{{-- ========== MODAL: Servidor ========== --}}
<div class="modal fade" id="modalSrv" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-srv">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-server me-2"></i> Adicionar servidor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" maxlength="120" required placeholder="Ex.: Servidor Producao">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Hostname <span class="text-danger">*</span></label>
                            <input name="hostname" class="form-control" required placeholder="server.exemplo.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Porta</label>
                            <input name="port" type="number" class="form-control" value="2087" min="1" max="65535" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <input type="hidden" name="type" value="whm">
                            <input type="text" class="form-control bg-light" value="WHM (Root/Revenda)" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuário</label>
                            <input name="username" class="form-control" value="root" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">API Token <span class="text-danger">*</span></label>
                            <input name="api_token" type="password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="verify_ssl" value="1" checked id="vs">
                                <label class="form-check-label" for="vs"><i class="bi bi-shield-check text-success me-1"></i> Verificar certificado SSL</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plug me-1"></i> Conectar
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
const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

document.getElementById('frm-srv').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!fd.has('verify_ssl')) fd.set('verify_ssl', '0');
    const btn = e.target.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Conectando...';
    try {
        const r = await fetch('{{ $cpanelStoreRoute }}', { method: 'POST', credentials: 'same-origin', headers, body: fd });
        const d = await r.json();
        if (d.ok) { window.toast.success('Servidor conectado!'); setTimeout(() => location.reload(), 800); }
        else window.toast.error(d.error || 'Falha na conexão.');
    } catch (err) { window.toast.error('Erro de rede.'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
});



document.querySelectorAll('[data-action-suspend]').forEach(b => b.onclick = async () => {
    const { value: form } = await Swal.fire({
        icon: 'warning', title: 'Suspender conta cPanel',
        html: '<input id="su" class="swal2-input" placeholder="Username da conta">' +
              '<input id="sr" class="swal2-input" placeholder="Motivo (opcional)">',
        focusConfirm: false, showCancelButton: true,
        confirmButtonText: 'Suspender', cancelButtonText: 'Cancelar', confirmButtonColor: '#ffc107',
        preConfirm: () => ({ username: document.getElementById('su').value, reason: document.getElementById('sr').value }),
    });
    if (!form?.username) return;
    const fd = new FormData();
    fd.append('username', form.username);
    fd.append('reason', form.reason || '');
    try {
        const r = await fetch(`{{ $cpanelBaseUrl }}/${b.dataset.actionSuspend}/suspend`, { method: 'POST', credentials: 'same-origin', headers, body: fd });
        const d = await r.json();
        d.ok ? window.toast.success('Conta suspensa!') : window.toast.error('Falha ao suspender.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});

document.querySelectorAll('[data-action-unsuspend]').forEach(b => b.onclick = async () => {
    const { value: u } = await Swal.fire({
        icon: 'question', title: 'Reativar conta', input: 'text', inputLabel: 'Username da conta',
        showCancelButton: true, confirmButtonText: 'Reativar', cancelButtonText: 'Cancelar', confirmButtonColor: '#198754'
    });
    if (!u) return;
    const fd = new FormData();
    fd.append('username', u);
    try {
        const r = await fetch(`{{ $cpanelBaseUrl }}/${b.dataset.actionUnsuspend}/unsuspend`, { method: 'POST', credentials: 'same-origin', headers, body: fd });
        const d = await r.json();
        d.ok ? window.toast.success('Conta reativada!') : window.toast.error('Falha.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});

document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
    const ok = await Swal.fire({ icon: 'warning', title: 'Excluir servidor?', showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch(`{{ $cpanelBaseUrl }}/${b.dataset.del}`, { method: 'DELETE', credentials: 'same-origin', headers });
        if (r.ok) { document.getElementById(`srv-${b.dataset.del}`).remove(); window.toast.success('Excluído!'); }
        else window.toast.error('Erro.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});


</script>
@endpush
