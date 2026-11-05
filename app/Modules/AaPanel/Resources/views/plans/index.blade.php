@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Planos aaPanel')

@section('content')
<div class="container-fluid py-3">
    @include('aapanel::partials.header', [
        'title' => 'Planos de Hospedagem aaPanel',
        'subtitle' => 'Configure limites e recursos para provisionamento automático de contas de hospedagem.'
    ])
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPlan">
            <i class="bi bi-plus-lg me-1"></i> Criar Novo Plano
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Servidor</th>
                            <th>Disco</th>
                            <th>Banda</th>
                            <th>BD/FTP/Dom</th>
                            <th>PHP</th>
                            <th>SSL</th>
                            <th>Status</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $p)
                        <tr id="plan-{{ $p->id }}">
                            <td class="fw-semibold">{{ $p->name }}</td>
                            <td class="small">{{ $p->server?->name ?? '-' }}</td>
                            <td>{{ $p->disk_mb }} MB</td>
                            <td>{{ $p->bandwidth_mb }} MB</td>
                            <td>{{ $p->database_limit }}/{{ $p->ftp_limit }}/{{ $p->domain_limit }}</td>
                            <td><span class="badge bg-info-subtle text-info">{{ $p->php_version }}</span></td>
                            <td>{!! $p->ssl_enabled ? '<i class="bi bi-shield-check text-success"></i>' : '<i class="bi bi-x text-muted"></i>' !!}</td>
                            <td><span class="badge bg-{{ $p->active ? 'success' : 'secondary' }}">{{ $p->active ? 'Ativo' : 'Inativo' }}</span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-info" data-edit="{{ $p->id }}" data-json="{{ json_encode($p->only(['name','server_id','disk_mb','bandwidth_mb','database_limit','ftp_limit','domain_limit','php_version','ssl_enabled','active'])) }}" title="Editar"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger" data-del="{{ $p->id }}" title="Excluir"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Nenhum plano configurado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $plans->links() }}</div>
</div>

<div class="modal fade" id="modalPlan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-plan">
                @csrf
                <input type="hidden" name="id" id="plan-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-layers me-2"></i> Plano aaPanel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" maxlength="120" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Servidor</label>
                            <select name="server_id" class="form-select">
                                <option value="">-- Nenhum --</option>
                                @foreach($servers as $sv)
                                <option value="{{ $sv->id }}">{{ $sv->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Disco (MB)</label>
                            <input name="disk_mb" type="number" class="form-control" value="1024" min="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Banda (MB)</label>
                            <input name="bandwidth_mb" type="number" class="form-control" value="10240" min="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PHP <span class="spinner-border spinner-border-sm d-none" id="php-loader"></span></label>
                            <select name="php_version" id="php-version-select" class="form-select" required>
                                <option value="">-- Selecione servidor --</option>
                            </select>
                            <div class="form-text text-muted small" id="php-hint">Selecione um servidor para carregar versoes PHP disponiveis.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Limite BD</label>
                            <input name="database_limit" type="number" class="form-control" value="1" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Limite FTP</label>
                            <input name="ftp_limit" type="number" class="form-control" value="1" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Limite Dom.</label>
                            <input name="domain_limit" type="number" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ssl_enabled" value="1" checked id="plan-ssl">
                                <label class="form-check-label" for="plan-ssl">SSL/LetsEncrypt automatico</label>
                            </div>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="active" value="1" checked id="plan-active">
                                <label class="form-check-label" for="plan-active">Ativo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showModal(el) {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        new bootstrap.Modal(el).show();
    } else {
        el.classList.add('show');
        el.style.display = 'block';
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }
}
const csrf = document.querySelector('meta[name=csrf-token]').content;
const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

document.getElementById('frm-plan').addEventListener('submit', e => {
    e.preventDefault();
    const id = document.getElementById('plan-id').value;
    const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/plans') : url('/admin/aapanel/plans') }}";
    const url = id ? `${baseUrl}/${id}` : baseUrl;
    if (id) {
        let methodInput = e.target.querySelector('input[name="_method"]');
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            e.target.appendChild(methodInput);
        }
        methodInput.value = 'PUT';
    }
    const btn = e.target.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
    const fd = new FormData(e.target);
    if (!fd.has('ssl_enabled')) fd.set('ssl_enabled', '0');
    if (!fd.has('active')) fd.set('active', '0');
    fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) { window.toast.success(d.message); setTimeout(() => location.reload(), 800); } else window.toast.error(d.message || 'Falha.'); })
        .catch(() => window.toast.error('Erro de rede.'))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
});

async function loadPhpVersions(serverId, selectedVersion = '') {
    const select = document.getElementById('php-version-select');
    const loader = document.getElementById('php-loader');
    const hint = document.getElementById('php-hint');

    if (!serverId) {
        select.innerHTML = '<option value="">-- Selecione servidor --</option>';
        hint.textContent = 'Selecione um servidor para carregar versoes PHP disponiveis.';
        return;
    }

    loader.classList.remove('d-none');
    select.disabled = true;

    try {
        const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";
        const r = await fetch(`${baseUrl}/${serverId}/php-versions`, { headers });
        const d = await r.json();

        select.innerHTML = '';
        if (d.ok && Array.isArray(d.data)) {
            d.data.forEach(v => {
                const ver = v.version ?? v.name ?? v;
                const opt = document.createElement('option');
                opt.value = ver;
                opt.textContent = v.name ?? `PHP ${ver}`;
                if (selectedVersion && String(selectedVersion) === String(ver)) opt.selected = true;
                select.appendChild(opt);
            });
            hint.textContent = `${d.data.length} versao(oes) PHP disponivel(is).`;
        } else {
            select.innerHTML = '<option value="83">PHP 83 (padrao)</option>';
            hint.textContent = 'Nao foi possivel carregar versoes. Usando padrao 83.';
        }
    } catch {
        select.innerHTML = '<option value="83">PHP 83 (padrao)</option>';
        hint.textContent = 'Erro ao carregar versoes. Usando padrao 83.';
    } finally {
        loader.classList.add('d-none');
        select.disabled = false;
    }
}

document.querySelector('[name=server_id]')?.addEventListener('change', e => {
    loadPhpVersions(e.target.value);
});

document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
    const d = JSON.parse(b.dataset.json);
    document.getElementById('plan-id').value = b.dataset.edit;
    const f = document.getElementById('frm-plan');
    f.querySelector('[name=name]').value = d.name;
    f.querySelector('[name=server_id]').value = d.server_id ?? '';
    f.querySelector('[name=disk_mb]').value = d.disk_mb;
    f.querySelector('[name=bandwidth_mb]').value = d.bandwidth_mb;
    f.querySelector('[name=database_limit]').value = d.database_limit;
    f.querySelector('[name=ftp_limit]').value = d.ftp_limit;
    f.querySelector('[name=domain_limit]').value = d.domain_limit;
    f.querySelector('[name=ssl_enabled]').checked = d.ssl_enabled;
    f.querySelector('[name=active]').checked = d.active;
    loadPhpVersions(d.server_id ?? '', d.php_version);
    showModal(document.getElementById('modalPlan'));
}));

document.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => {
    Swal.fire({
        icon: 'warning', title: 'Remover plano?',
        text: 'Esta acao nao pode ser desfeita.',
        showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(async res => {
        if (!res.isConfirmed) return;
        const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/plans') : url('/admin/aapanel/plans') }}";
        try {
            const r = await fetch(`${baseUrl}/${b.dataset.del}`, { method: 'DELETE', credentials: 'same-origin', headers });
            const d = await r.json();
            d.ok ? window.toast.success('Plano removido.') : window.toast.error(d.message || 'Falha.');
            if (d.ok) document.getElementById(`plan-${b.dataset.del}`)?.remove();
        } catch { window.toast.error('Erro de rede.'); }
    });
}));
</script>
@endpush
