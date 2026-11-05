@extends('tenancy::tenant.layout')

@section('title', 'API REST')

@section('content')
<div class="tenant-panel">
    <div class="premium-panel mb-4">
        <div class="premium-panel__header d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <span class="text-uppercase text-muted fw-semibold small">Integracoes externas</span>
                <h1 class="h4 mb-1">API REST</h1>
                <p class="text-muted mb-0">Gere tokens para Zapier, N8N, Make, Power Automate ou sistemas proprios.</p>
            </div>
            <a href="{{ route('api.docs') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-code me-1"></i> Documentacao
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <strong><i class="bi bi-key me-1"></i> Novo token</strong>
                </div>
                <div class="card-body">
                    @if($tenant->publicApiEnabled() && $tenant->isActive())
                        <form id="api-token-form">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Nome do token</label>
                                <input type="text" name="name" class="form-control" maxlength="80" required placeholder="Ex.: N8N Producao">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Gerar token
                            </button>
                        </form>
                    @else
                        <div class="alert alert-warning mb-0">
                            <strong>API nao contratada.</strong><br>
                            Esta API fica disponivel somente para tenants ativos com acesso REST habilitado no plano ou liberado pelo superadmin.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-shield-lock me-1"></i> Tokens ativos</strong>
                    <span class="badge text-bg-primary">{{ $tokens->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if($tokens->count())
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-public-api-tokens" data-export-title="Tokens API" style="width: 100% !important;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="all" data-priority="1">Nome</th>
                                        <th class="min-tablet" data-priority="2">Criado em</th>
                                        <th class="desktop">Último uso</th>
                                        <th class="all text-end no-export" data-priority="1" style="min-width: 70px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tokens as $token)
                                        <tr>
                                            <td class="fw-semibold">{{ $token->name }}</td>
                                            <td>{{ $token->created_at?->format('d/m/Y H:i') }}</td>
                                            <td>{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Nunca usado' }}</td>
                                            <td class="all text-end no-export">
                                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" data-revoke-token="{{ $token->id }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-key display-6 d-block mb-2"></i>
                            Nenhum token criado.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const apiCsrf = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('api-token-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;

    try {
        const response = await fetch('{{ route('tenant.api.tokens.store') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': apiCsrf, 'Accept': 'application/json' },
            body: new FormData(form),
        });
        const data = await response.json();

        if (!response.ok || !data.ok) {
            window.toast?.error(data.message || 'Nao foi possivel gerar o token.');
            return;
        }

        await Swal.fire({
            icon: 'success',
            title: 'Token criado',
            html: `<textarea class="form-control" rows="4" readonly>${data.token}</textarea><p class="small text-muted mt-2 mb-0">Copie agora. O token nao sera exibido novamente.</p>`,
            confirmButtonText: 'Entendi',
            background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
            color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
        });

        location.reload();
    } finally {
        button.disabled = false;
    }
});

document.querySelectorAll('[data-revoke-token]').forEach((button) => {
    button.addEventListener('click', async () => {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Revogar token?',
            text: 'Integracoes que usam este token vao parar de funcionar.',
            showCancelButton: true,
            confirmButtonText: 'Revogar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
            color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
        });

        if (!result.isConfirmed) return;

        const response = await fetch(`{{ url('/painel/api/tokens') }}/${button.dataset.revokeToken}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': apiCsrf, 'Accept': 'application/json' },
        });
        const data = await response.json();

        if (response.ok && data.ok) {
            window.toast?.success('Token revogado.');
            button.closest('tr')?.remove();
        } else {
            window.toast?.error('Nao foi possivel revogar o token.');
        }
    });
});
</script>
@endpush
