@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Sites aaPanel - ' . $server->name)

@section('content')
<div class="container-fluid py-3">
    @include('aapanel::partials.header', [
        'title' => 'Sites do Servidor: ' . $server->name,
        'subtitle' => 'Gerencie sites e domínios hospedados diretamente no servidor ' . $server->host
    ])
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary btn-sm" data-sync data-server="{{ $server->id }}">
            <i class="bi bi-arrow-repeat me-1"></i> Sincronizar para Contas
        </button>
    </div>

    @if(!$apiOk)
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Nao foi possivel conectar ao aaPanel.</strong><br>
            <span class="small">{{ $apiMessage ?? 'Verifique conexao do servidor.' }}</span>
        </div>
    </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Dominio</th>
                            <th>Caminho</th>
                            <th>PHP</th>
                            <th>Status</th>
                            <th>SSL</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sites as $site)
                        <tr id="site-{{ $site['id'] ?? 0 }}">
                            <td class="small text-muted">{{ $site['id'] ?? '-' }}</td>
                            <td class="fw-semibold">{{ $site['name'] ?? $site['domain'] ?? '-' }}</td>
                            <td class="small text-muted">{{ $site['path'] ?? '-' }}</td>
                            <td><span class="badge bg-info-subtle text-info">{{ $site['php_version'] ?? '?' }}</span></td>
                            <td>
                                @if(($site['status'] ?? 1) == 1)
                                    <span class="badge bg-success">Ativo</span>
                                @else
                                    <span class="badge bg-secondary">Parado</span>
                                @endif
                            </td>
                            <td>
                                @if($site['ssl'] ?? false)
                                    <i class="bi bi-shield-check text-success" title="SSL ativo"></i>
                                @else
                                    <i class="bi bi-x text-muted" title="Sem SSL"></i>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(($site['status'] ?? 1) == 1)
                                    <button class="btn btn-sm btn-outline-warning" data-stop="{{ $site['id'] }}" title="Parar"><i class="bi bi-pause-fill"></i></button>
                                @else
                                    <button class="btn btn-sm btn-outline-success" data-start="{{ $site['id'] }}" title="Iniciar"><i class="bi bi-play-fill"></i></button>
                                @endif
                                <button class="btn btn-sm btn-outline-danger" data-del="{{ $site['id'] }}" data-name="{{ $site['name'] ?? $site['domain'] ?? '' }}" title="Remover"><i class="bi bi-trash"></i></button>
                                <button class="btn btn-sm btn-outline-info" data-import="{{ $site['id'] }}" data-name="{{ $site['name'] ?? $site['domain'] ?? '' }}" title="Importar como Conta"><i class="bi bi-box-arrow-in-down"></i></button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum site encontrado no aaPanel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
const SERVER_ID = {{ $server->id }};

function actionBtn(btn, promiseFn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    promiseFn().finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

document.querySelector('[data-sync]')?.addEventListener('click', () => {
    const btn = document.querySelector('[data-sync]');
    actionBtn(btn, async () => {
        try {
            const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";
            const r = await fetch(`${baseUrl}/${SERVER_ID}/sites/sync`, { method: 'POST', credentials: 'same-origin', headers });
            const d = await r.json();
            d.ok ? window.toast.success(d.message) : window.toast.error(d.message || 'Falha.');
            if (d.ok) setTimeout(() => location.reload(), 1200);
        } catch { window.toast.error('Erro de rede.'); }
    });
});

document.querySelectorAll('[data-start]').forEach(b => b.addEventListener('click', () => {
    actionBtn(b, async () => {
        try {
            const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";
            const r = await fetch(`${baseUrl}/${SERVER_ID}/sites/${b.dataset.start}/start`, { method: 'POST', credentials: 'same-origin', headers });
            const d = await r.json();
            d.ok ? window.toast.success('Site iniciado.') : window.toast.error(d.message || 'Falha.');
            if (d.ok) setTimeout(() => location.reload(), 800);
        } catch { window.toast.error('Erro de rede.'); }
    });
}));

document.querySelectorAll('[data-stop]').forEach(b => b.addEventListener('click', () => {
    actionBtn(b, async () => {
        try {
            const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";
            const r = await fetch(`${baseUrl}/${SERVER_ID}/sites/${b.dataset.stop}/stop`, { method: 'POST', credentials: 'same-origin', headers });
            const d = await r.json();
            d.ok ? window.toast.success('Site parado.') : window.toast.error(d.message || 'Falha.');
            if (d.ok) setTimeout(() => location.reload(), 800);
        } catch { window.toast.error('Erro de rede.'); }
    });
}));

document.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => {
    const name = b.dataset.name;
    Swal.fire({
        icon: 'warning', title: 'Remover site?',
        text: `Site: ${name}\nTodos os dados serao removidos do aaPanel.`,
        showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(async res => {
        if (!res.isConfirmed) return;
        actionBtn(b, async () => {
            try {
                const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";
                const r = await fetch(`${baseUrl}/${SERVER_ID}/sites/${b.dataset.del}`, { method: 'DELETE', credentials: 'same-origin', headers });
                const d = await r.json();
                d.ok ? window.toast.success('Site removido.') : window.toast.error(d.message || 'Falha.');
                if (d.ok) document.getElementById(`site-${b.dataset.del}`)?.remove();
            } catch { window.toast.error('Erro de rede.'); }
        });
    });
}));

document.querySelectorAll('[data-import]').forEach(b => b.addEventListener('click', () => {
    const name = b.dataset.name;
    actionBtn(b, async () => {
        try {
            const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";
            const r = await fetch(`${baseUrl}/${SERVER_ID}/sites/${b.dataset.import}/import`, { method: 'POST', credentials: 'same-origin', headers });
            const d = await r.json();
            d.ok ? window.toast.success(d.message || 'Importado com sucesso.') : window.toast.error(d.message || 'Falha.');
        } catch { window.toast.error('Erro de rede.'); }
    });
}));
</script>
@endpush
