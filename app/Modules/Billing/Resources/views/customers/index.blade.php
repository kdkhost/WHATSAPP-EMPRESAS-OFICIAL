@extends('admin.layouts.app')

@section('title', 'Clientes')

@push('head')
<style>
.billing-customers-page .form-control {
    min-height: 46px;
}
.billing-customers-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    text-transform: uppercase;
}
.billing-customers-avatar {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .78rem;
    font-weight: 700;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--bs-primary) 15%, var(--bs-body-bg));
    color: var(--bs-primary);
}
@media (max-width: 767.98px) {
    .billing-customers-avatar {
        width: 30px;
        height: 30px;
        font-size: .7rem;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-customers-page">
    <x-ui.page-header
        title="Clientes"
        subtitle="Base operacional de pessoas e empresas usadas nas cobranças, notificações e vínculo das faturas."
        eyebrow="Cadastro financeiro">
        <x-slot:actions>
            <button type="button" class="btn btn-outline-secondary" onclick="syncWhmcs()">
                <i class="bi bi-arrow-repeat me-1"></i> Sincronizar WHMCS
            </button>
            <a href="{{ route('admin.billing.customers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Novo cliente
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total cadastrado</span>
                <span class="dashboard-note__value">{{ number_format($stats['total'], 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Novos em 30 dias</span>
                <span class="dashboard-note__value text-info">{{ number_format($stats['new_30d'], 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total de clientes" :value="number_format($stats['total'], 0, ',', '.')" icon="bi-people" color="primary">
                <x-slot:meta>Cadastro geral disponível para faturamento.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Com WhatsApp" :value="number_format($stats['with_whatsapp'], 0, ',', '.')" icon="bi-whatsapp" color="success">
                <x-slot:meta>Clientes aptos para notificação direta no mensageiro.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Com e-mail" :value="number_format($stats['with_email'], 0, ',', '.')" icon="bi-envelope" color="info">
                <x-slot:meta>Contatos com canal de e-mail configurado.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Novos em 30 dias" :value="number_format($stats['new_30d'], 0, ',', '.')" icon="bi-person-plus" color="warning">
                <x-slot:meta>Evolução recente da sua base de clientes.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card title="Busca de clientes" subtitle="Pesquise por nome, e-mail, documento ou WhatsApp." class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-10">
                <label class="form-label">Buscar</label>
                <input type="search" name="q" class="form-control" placeholder="Nome, e-mail, CPF/CNPJ ou WhatsApp" value="{{ request('q') }}">
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i> Buscar
                </button>
                @if(request('q'))
                    <a href="{{ route('admin.billing.customers.index') }}" class="btn btn-outline-secondary" title="Limpar busca">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    <x-ui.premium-card title="Lista de clientes" :subtitle="$customers->count() . ' registro(s) encontrados.'">
        <div class="billing-customers-table p-0">
            <div>
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-customers" data-export-title="Clientes Cadastrados" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Cliente</th>
                            <th class="min-tablet">E-mail</th>
                            <th class="desktop">Documento</th>
                            <th class="min-tablet">WhatsApp</th>
                            <th class="desktop">Tenant</th>
                            <th class="desktop">Parcelas restantes</th>
                            <th class="desktop">Cidade / UF</th>
                            <th class="all text-end no-export" data-priority="1" style="width: 80px; min-width: 75px; max-width: 85px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($customers as $c)
                        @php
                            $initials = collect(explode(' ', $c->name))->take(2)->map(fn($p) => mb_substr($p, 0, 1))->implode('');
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if(!empty($c->meta['avatar']))
                                        <img src="{{ Storage::url($c->meta['avatar']) }}" alt="Avatar" class="billing-customers-avatar border" style="object-fit:cover;">
                                    @else
                                        <span class="billing-customers-avatar">{{ strtoupper($initials ?: '?') }}</span>
                                    @endif
                                    <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                                        <a href="{{ route('admin.billing.customers.edit', $c->id) }}" class="fw-semibold text-decoration-none text-truncate d-block" title="{{ $c->name }}">{{ $c->name }}</a>
                                        <div class="small text-muted text-truncate d-flex align-items-center gap-1">
                                            <span>{{ $c->phone ? \App\Support\Format::phone($c->phone) : 'Sem telefone' }}</span>
                                            @if(!empty($c->meta['whmcs_id']))
                                                <span class="badge bg-secondary" style="font-size: 0.6em; padding: 0.15em 0.35em;">WHMCS</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $c->email ?: 'Não informado' }}</td>
                            <td>
                                @if($c->cpf)
                                    <div><span class="badge bg-light text-dark border">CPF</span> <code>{{ $c->formatted_cpf }}</code></div>
                                @elseif($c->cnpj)
                                    <div><span class="badge bg-light text-dark border">CNPJ</span> <code>{{ $c->formatted_cnpj }}</code></div>
                                @elseif($c->document)
                                    <code>{{ $c->document }}</code>
                                @else
                                    <span class="text-muted">Não informado</span>
                                @endif
                                
                                @if($c->birth_date)
                                    <div class="small text-muted mt-1" title="Data de Nascimento">
                                        <i class="bi bi-cake2 me-1 text-primary"></i>{{ $c->formatted_birth_date }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($c->whatsapp)
                                    <div class="text-success"><i class="bi bi-whatsapp me-1"></i>{{ \App\Support\Format::phone($c->whatsapp) }}</div>
                                    @if($c->whatsapp_optin)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68em;"><i class="bi bi-check-circle me-1"></i>Recebe WhatsApp</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.68em;"><i class="bi bi-slash-circle me-1"></i>Não recebe WhatsApp</span>
                                    @endif
                                @else
                                    <span class="text-muted">Não informado</span>
                                @endif
                            </td>
                            <td>
                                @if($c->tenant)
                                    @php
                                        $tenantColor = match($c->tenant->id % 10) {
                                            0 => 'bg-primary',
                                            1 => 'bg-success',
                                            2 => 'bg-danger',
                                            3 => 'bg-warning',
                                            4 => 'bg-info',
                                            5 => 'bg-secondary',
                                            6 => 'bg-dark',
                                            7 => 'bg-purple',
                                            8 => 'bg-pink',
                                            9 => 'bg-teal',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $tenantColor }} fs-6"><i class="bi bi-building me-1"></i>{{ $c->tenant->company_name ?? $c->tenant->slug }}</span>
                                @else
                                    <span class="text-muted">Não vinculado</span>
                                @endif
                            </td>
                            <td>
                                @if($c->installments_count > 0)
                                    <span class="badge bg-warning fs-6">{{ $c->installments_count }} restantes</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $c->cidade && $c->uf ? "{$c->cidade}/{$c->uf}" : 'Não informado' }}</td>
                            <td class="text-end no-export" style="width: 80px; min-width: 75px; max-width: 85px;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                    <a href="{{ route('admin.billing.customers.edit', $c->id) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Editar cliente">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger p-1 px-2" data-del="{{ $c->id }}" title="Excluir cliente">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-ui.empty-state icon="bi-people" title="Nenhum cliente cadastrado" message="Cadastre a primeira pessoa ou empresa para começar a emitir cobranças.">
                                    <x-slot:actions>
                                        <a href="{{ route('admin.billing.customers.create') }}" class="btn btn-primary">
                                            <i class="bi bi-plus-lg me-1"></i> Cadastrar primeiro cliente
                                        </a>
                                    </x-slot:actions>
                                </x-ui.empty-state>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($customers instanceof \Illuminate\Pagination\LengthAwarePaginator && $customers->hasPages())
            <div class="mt-3">{{ $customers->links() }}</div>
        @endif
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

document.querySelectorAll('[data-del]').forEach((btn) => {
    btn.onclick = async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Excluir cliente?',
            text: 'Clientes com faturas vinculadas não podem ser excluídos.',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });

        if (!ok.isConfirmed) {
            return;
        }

        try {
            const r = await fetch(`{{ url('/admin/billing/customers') }}/${btn.dataset.del}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const d = await r.json().catch(() => ({}));

            if (r.ok) {
                btn.closest('tr').remove();
                window.toast.success('Cliente excluído.');
            } else {
                window.toast.error(d.error || 'Erro ao excluir.');
            }
        } catch (err) {
            window.toast.error('Erro de rede.');
        }
    };
});

function syncWhmcs() {
    Swal.fire({
        title: 'Sincronizar com WHMCS?',
        text: 'Isso importará e atualizará seus clientes a partir do seu painel WHMCS.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Sim, sincronizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sincronizando...',
                text: 'Aguarde enquanto os clientes são importados.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('{{ route('admin.billing.customers.sync-whmcs') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.error || 'Erro desconhecido', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Ocorreu um erro na requisição.', 'error');
            });
        }
    });
}
</script>
@endpush
