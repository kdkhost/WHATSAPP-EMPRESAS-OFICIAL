@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Contas WHM: ' . $server->name)

@section('content')
<div class="container-fluid">
    @php
        $isTenant = request()->is('painel*');
        $backUrl = $isTenant ? route('tenant.cpanel.index') : route('admin.cpanel.index');
        $cpanelBaseUrl = $isTenant ? url('/painel/cpanel') : url('/admin/cpanel');
    @endphp

    <div class="d-flex align-items-center mb-4">
        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <div>
            <h4 class="mb-0">Contas do Servidor</h4>
            <div class="text-muted small">Servidor: <strong>{{ $server->name }}</strong> ({{ $server->hostname }})</div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header py-3">
            <h6 class="mb-0"><i class="bi bi-people me-2 text-primary"></i> Lista de Contas ({{ count($accounts) }})</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-cpanel-accounts" data-export-title="Contas do Servidor cPanel" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Usuário</th>
                        <th class="all" data-priority="2">Domínio</th>
                        <th class="desktop">Plano</th>
                        <th class="min-tablet" data-priority="3">Status</th>
                        <th class="all text-end no-export" data-priority="1" style="min-width: 90px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td><code>{{ $acc['user'] ?? '-' }}</code></td>
                            <td><a href="http://{{ $acc['domain'] ?? '' }}" target="_blank" class="text-decoration-none">{{ $acc['domain'] ?? '-' }} <i class="bi bi-box-arrow-up-right small ms-1"></i></a></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ $acc['plan'] ?? '-' }}</span>
                            </td>
                            <td>
                                @if(!empty($acc['suspended']))
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-pause-circle me-1"></i> Suspenso</span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-play-circle me-1"></i> Ativo</span>
                                @endif
                            </td>
                            <td class="all text-end no-export">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <button class="btn btn-sm btn-outline-warning p-1 px-2" data-action-suspend="{{ $acc['user'] ?? '' }}" title="Suspender">
                                        <i class="bi bi-pause-circle"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success p-1 px-2" data-action-unsuspend="{{ $acc['user'] ?? '' }}" title="Reativar">
                                        <i class="bi bi-play-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                Nenhuma conta encontrada neste servidor.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    const serverId = {{ $server->id }};
    const baseUrl = `{{ $cpanelBaseUrl }}/${serverId}`;

    document.querySelectorAll('[data-action-suspend]').forEach(b => b.onclick = async () => {
        const username = b.dataset.actionSuspend;
        const { value: reason } = await Swal.fire({
            icon: 'warning',
            title: 'Suspender conta',
            text: `Informe o motivo para suspender a conta ${username}:`,
            input: 'text',
            inputPlaceholder: 'Inadimplência',
            showCancelButton: true,
            confirmButtonText: 'Suspender',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
        });

        if (reason === undefined) return;

        const fd = new FormData();
        fd.append('username', username);
        fd.append('reason', reason || 'Inadimplencia');
        
        const orig = b.innerHTML;
        b.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        b.disabled = true;

        try {
            const r = await fetch(`${baseUrl}/suspend`, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            const d = await r.json();
            if (d.ok) {
                window.toast.success('Conta suspensa com sucesso!');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                window.toast.error(d.error || 'Falha ao suspender conta.');
                b.innerHTML = orig; b.disabled = false;
            }
        } catch (err) { 
            window.toast.error('Erro de rede.');
            b.innerHTML = orig; b.disabled = false;
        }
    });

    document.querySelectorAll('[data-action-unsuspend]').forEach(b => b.onclick = async () => {
        const username = b.dataset.actionUnsuspend;
        const ok = await Swal.fire({
            icon: 'question', 
            title: 'Reativar conta', 
            text: `Tem certeza que deseja reativar a conta ${username}?`,
            showCancelButton: true, 
            confirmButtonText: 'Reativar', 
            cancelButtonText: 'Cancelar', 
            confirmButtonColor: '#198754'
        });

        if (!ok.isConfirmed) return;

        const fd = new FormData();
        fd.append('username', username);
        
        const orig = b.innerHTML;
        b.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        b.disabled = true;

        try {
            const r = await fetch(`${baseUrl}/unsuspend`, { method: 'POST', credentials: 'same-origin', headers, body: fd });
            const d = await r.json();
            if (d.ok) {
                window.toast.success('Conta reativada com sucesso!');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                window.toast.error(d.error || 'Falha ao reativar conta.');
                b.innerHTML = orig; b.disabled = false;
            }
        } catch (err) { 
            window.toast.error('Erro de rede.');
            b.innerHTML = orig; b.disabled = false;
        }
    });
});
</script>
@endpush
