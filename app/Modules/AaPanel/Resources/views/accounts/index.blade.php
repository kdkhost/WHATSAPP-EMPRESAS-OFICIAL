@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Contas aaPanel')

@section('content')
<div class="container-fluid py-3">
    @include('aapanel::partials.header', [
        'title' => 'Contas de Hospedagem aaPanel',
        'subtitle' => 'Gerencie contas de hospedagem, domínios, SSL e provisionamento nos servidores.'
    ])
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAccount">
            <i class="bi bi-plus-lg me-1"></i> Nova Conta de Hospedagem
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead>
                        <tr>
                            <th>Domínio</th>
                            <th>Servidor</th>
                            <th>Plano</th>
                            <th>Status</th>
                            <th>SSL</th>
                            <th>Criada</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $a)
                        <tr id="acc-{{ $a->id }}">
                            <td class="fw-semibold">{{ $a->domain }}</td>
                            <td class="small">{{ $a->server?->name ?? '-' }}</td>
                            <td class="small">{{ $a->plan?->name ?? '-' }}</td>
                            <td><span class="badge bg-{{ $a->status === 'active' ? 'success' : ($a->status === 'suspended' ? 'warning' : ($a->status === 'terminated' ? 'danger' : 'secondary')) }}">{{ ucfirst($a->status) }}</span></td>
                            <td>{!! $a->ssl_enabled ? '<i class="bi bi-shield-check text-success"></i>' : '<i class="bi bi-x text-muted"></i>' !!}</td>
                            <td class="small text-muted">{{ $a->created_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ request()->is('painel*') ? route('painel.aapanel.accounts.show', $a) : route('admin.aapanel.accounts.show', $a) }}" class="btn btn-sm btn-outline-info" title="Ver"><i class="bi bi-eye"></i></a>
                                @if($a->status === 'pending')
                                <button class="btn btn-sm btn-outline-success" data-provision="{{ $a->id }}" title="Provisionar"><i class="bi bi-play-fill"></i></button>
                                @endif
                                @if($a->status === 'active')
                                <button class="btn btn-sm btn-outline-warning" data-suspend="{{ $a->id }}" title="Suspender"><i class="bi bi-pause-fill"></i></button>
                                @endif
                                @if($a->status === 'suspended')
                                <button class="btn btn-sm btn-outline-success" data-unsuspend="{{ $a->id }}" title="Reativar"><i class="bi bi-play-fill"></i></button>
                                @endif
                                @if(in_array($a->status, ['active','suspended','pending']))
                                <button class="btn btn-sm btn-outline-danger" data-terminate="{{ $a->id }}" title="Terminar"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma conta configurada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $accounts->links() }}</div>
</div>

<div class="modal fade" id="modalAccount" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-acc">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-globe me-2"></i> Nova conta aaPanel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Servidor <span class="text-danger">*</span></label>
                            <select name="server_id" class="form-select" required>
                                <option value="">-- Selecione --</option>
                                @foreach($servers as $sv)
                                <option value="{{ $sv->id }}">{{ $sv->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Plano</label>
                            <select name="plan_id" class="form-select">
                                <option value="">-- Nenhum --</option>
                                @foreach($plans as $pl)
                                <option value="{{ $pl->id }}">{{ $pl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Domínio <span class="text-danger">*</span></label>
                            <input name="domain" class="form-control" required placeholder="exemplo.com">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Usuário (opcional)</label>
                            <input name="username" class="form-control" maxlength="60" placeholder="Gerado automaticamente">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Criar</button>
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

function actionBtn(btn, promiseFn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    promiseFn().finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

document.getElementById('frm-acc').addEventListener('submit', e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Criando...';
    const storeUrl = "{{ request()->is('painel*') ? route('painel.aapanel.accounts.store') : route('admin.aapanel.accounts.store') }}";
    fetch(storeUrl, { method: 'POST', credentials: 'same-origin', headers, body: new FormData(e.target) })
        .then(r => r.json())
        .then(d => { if (d.ok) { window.toast.success(d.message); setTimeout(() => location.reload(), 800); } else window.toast.error(d.message || 'Falha.'); })
        .catch(() => window.toast.error('Erro de rede.'))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
});

['provision','suspend','unsuspend'].forEach(action => {
    document.querySelectorAll(`[data-${action}]`).forEach(b => b.addEventListener('click', () => {
        actionBtn(b, async () => {
            try {
                const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/accounts') : url('/admin/aapanel/accounts') }}";
                const r = await fetch(`${baseUrl}/${b.dataset[action]}/${action}`, { method: 'POST', credentials: 'same-origin', headers });
                const d = await r.json();
                d.ok ? window.toast.success(d.message || 'OK') : window.toast.error(d.message || 'Falha.');
                if (d.ok) setTimeout(() => location.reload(), 800);
            } catch { window.toast.error('Erro de rede.'); }
        });
    }));
});

document.querySelectorAll('[data-terminate]').forEach(b => b.addEventListener('click', () => {
    Swal.fire({
        icon: 'warning', title: 'Terminar conta?',
        text: 'Todos os dados serao removidos do servidor aaPanel.',
        showCancelButton: true, confirmButtonText: 'Terminar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(async res => {
        if (!res.isConfirmed) return;
        actionBtn(b, async () => {
            try {
                const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/accounts') : url('/admin/aapanel/accounts') }}";
                const r = await fetch(`${baseUrl}/${b.dataset.terminate}/terminate`, { method: 'DELETE', credentials: 'same-origin', headers });
                const d = await r.json();
                d.ok ? window.toast.success('Conta terminada.') : window.toast.error(d.message || 'Falha.');
                if (d.ok) setTimeout(() => location.reload(), 800);
            } catch { window.toast.error('Erro de rede.'); }
        });
    });
}));
</script>
@endpush
