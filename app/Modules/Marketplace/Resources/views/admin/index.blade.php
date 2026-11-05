@extends('admin.layouts.app')

@section('title', 'Marketplace de Addons')

@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Marketplace de Addons"
        eyebrow="Curadoria e aprovacao"
        subtitle="Aprove addons enviados pelos usuarios, publique pacotes comerciais e acompanhe a vitrine pronta para assinatura dos tenants."
    >
        <x-slot:aside>
            <div class="admin-hero-note">
                <span class="admin-hero-note__label">Catalogo ativo</span>
                <span class="admin-hero-note__value">{{ number_format($catalog->count(), 0, ',', '.') }}</span>
            </div>
            <div class="admin-hero-note">
                <span class="admin-hero-note__label">Submissoes pendentes</span>
                <span class="admin-hero-note__value">{{ number_format($submissions->where('status', 'pending')->count(), 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <x-ui.premium-card title="Catalogo publicado" subtitle="Somente addons instalados e ativos podem ser assinados pelos tenants.">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Addon</th>
                                <th>Categoria</th>
                                <th>Preco</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($catalog as $addon)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $addon->name }}</div>
                                        <div class="small text-muted">{{ $addon->slug }} • v{{ $addon->version }}</div>
                                    </td>
                                    <td>{{ $addon->category ?: 'Addons' }}</td>
                                    <td>{{ (float) $addon->price > 0 ? 'R$ '.number_format((float) $addon->price, 2, ',', '.') : 'Gratuito' }}</td>
                                    <td><x-ui.status-badge :status="$addon->is_installed ? 'active' : 'inactive'" :label="$addon->is_installed ? 'Instalado' : 'Sem pacote'" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhum addon publicado ainda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.premium-card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.premium-card title="Fila de aprovacao" subtitle="O pacote so entra em app/Addons depois da aprovacao do superadmin.">
                <div class="d-grid gap-3">
                    @forelse($submissions as $submission)
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ $submission->name ?: 'Pacote sem nome' }}</div>
                                    <div class="small text-muted">{{ $submission->slug ?: '-' }} • {{ $submission->version ?: 'sem versao' }}</div>
                                    <div class="small text-muted mt-2">Status: {{ $submission->status }}</div>
                                    @if($submission->review_notes)
                                        <div class="small mt-2">{{ $submission->review_notes }}</div>
                                    @endif
                                </div>
                                <x-ui.status-badge
                                    :status="$submission->status === 'approved' ? 'active' : ($submission->status === 'rejected' ? 'overdue' : 'pending')"
                                    :label="ucfirst($submission->status)"
                                />
                            </div>
                            @if($submission->status === 'pending')
                                <div class="d-flex gap-2 mt-3">
                                    <button class="btn btn-sm btn-success" data-approve="{{ $submission->id }}"><i class="bi bi-check2-circle me-1"></i>Aprovar</button>
                                    <button class="btn btn-sm btn-outline-danger" data-reject="{{ $submission->id }}"><i class="bi bi-x-circle me-1"></i>Rejeitar</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty-state icon="bi-inboxes" title="Nada na fila" message="Quando um tenant enviar um ZIP de addon, a revisao aparecera aqui." />
                    @endforelse
                </div>
            </x-ui.premium-card>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-approve]').forEach((button) => {
    button.addEventListener('click', async () => {
        const response = await fetch(`{{ url('/admin/marketplace/submissions') }}/${button.dataset.approve}/approve`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.toast?.error(data.message || 'Falha ao aprovar o addon.');
            return;
        }
        window.toast?.success(data.message || 'Addon aprovado.');
        window.location.reload();
    });
});

document.querySelectorAll('[data-reject]').forEach((button) => {
    button.addEventListener('click', async () => {
        const reason = window.prompt('Informe o motivo da rejeicao:', 'Pacote fora do padrao esperado.');
        if (reason === null) return;
        const response = await fetch(`{{ url('/admin/marketplace/submissions') }}/${button.dataset.reject}/reject`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reason })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.toast?.error(data.message || 'Falha ao rejeitar o addon.');
            return;
        }
        window.toast?.success(data.message || 'Submissao rejeitada.');
        window.location.reload();
    });
});
</script>
@endpush
