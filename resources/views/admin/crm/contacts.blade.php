@extends('admin.layouts.app')
@section('title', 'CRM - Contatos')

@push('head')
<style>
.crm-contacts-page .crm-contact-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--app-surface-soft, rgba(13, 110, 253, 0.08));
    color: var(--bs-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.crm-contacts-page .crm-contacts-table {
    margin: 0;
}
</style>
@endpush

@section('content')
<div class="container-fluid crm-contacts-page">

    {{-- Cabeçalho da Página --}}
    <x-ui.page-header
        title="Contatos do CRM"
        subtitle="Base unificada de contatos do CRM com segmentação, canais de comunicação e status."
        eyebrow="Relacionamento e CRM">
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.billing.customers.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Novo Contato
                </a>
                <a href="{{ url('/admin/import/customers') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-upload me-1"></i> Importar CSV
                </a>
            </div>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Ativos</span>
                <span class="dashboard-note__value text-success">{{ number_format($stats['active'], 0, ',', '.') }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total contatos</span>
                <span class="dashboard-note__value">{{ number_format($stats['total'], 0, ',', '.') }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    {{-- KPIs em Cards Padronizados --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Total Contatos" :value="number_format($stats['total'], 0, ',', '.')" icon="bi-people" color="primary">
                <x-slot:meta>Base geral de contatos cadastrados.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Contatos Ativos" :value="number_format($stats['active'], 0, ',', '.')" icon="bi-person-check" color="success">
                <x-slot:meta>Contatos ativos para relacionamento e cobrança.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Com WhatsApp" :value="number_format($stats['with_whatsapp'], 0, ',', '.')" icon="bi-whatsapp" color="info">
                <x-slot:meta>Contatos aptos para notificação no WhatsApp.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Novos em 30 dias" :value="number_format($stats['new_30d'], 0, ',', '.')" icon="bi-person-plus" color="warning">
                <x-slot:meta>Novos contatos registrados nos últimos 30 dias.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    {{-- Filtros e Busca no Padrão do Sistema --}}
    <x-ui.premium-card title="Filtros e Busca" subtitle="Pesquise por nome, e-mail, documento, WhatsApp ou cidade." class="mb-4">
        <form method="GET" action="{{ route('admin.crm.contacts') }}" class="row g-3 align-items-end">
            <div class="col-lg-10">
                <label class="form-label small fw-semibold text-muted">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-body-tertiary"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Buscar nome, email, documento, telefone..." value="{{ request('q') }}">
                </div>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" title="Buscar">
                    <i class="bi bi-search me-1"></i> Buscar
                </button>
                @if(request('q'))
                    <a href="{{ route('admin.crm.contacts') }}" class="btn btn-outline-secondary" title="Limpar busca">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </x-ui.premium-card>

    {{-- Tabela no Padrão Premium de Templates e Billing --}}
    <x-ui.premium-card title="Lista de Contatos" :subtitle="$customers->count() . ' contato(s) listado(s).'">
        <div class="crm-contacts-table p-0">
            <div>
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-crm-contacts" data-export-title="Lista de Contatos CRM" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Contato</th>
                            <th class="min-tablet" data-priority="2">E-mail</th>
                            <th class="min-tablet" data-priority="3">WhatsApp</th>
                            <th class="desktop">Documento</th>
                            <th class="desktop">Cidade / UF</th>
                            <th class="desktop">Status</th>
                            <th class="all text-end no-export col-actions" data-priority="1" style="width: 90px; min-width: 85px; max-width: 100px; white-space: nowrap;">Ações</th>
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
                                    <span class="crm-contact-avatar">{{ strtoupper($initials ?: '?') }}</span>
                                    <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                                        <a href="{{ route('admin.billing.customers.edit', $c->id) }}" class="fw-semibold text-decoration-none text-truncate d-block" title="{{ $c->name }}">
                                            {{ $c->name }}
                                        </a>
                                        <div class="small text-muted text-truncate d-flex align-items-center gap-1">
                                            @if($c->created_at && $c->created_at->gt(now()->subDays(7)))
                                                <span class="badge bg-info-subtle text-info py-0 px-1" style="font-size:0.62rem;">NOVO</span>
                                            @endif
                                            <span>Cadastrado {{ $c->created_at ? $c->created_at->format('d/m/Y') : '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($c->email)
                                    <span class="small">{{ $c->email }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($c->whatsapp)
                                    <div class="text-success small fw-semibold"><i class="bi bi-whatsapp me-1"></i>{{ \App\Support\Format::phone($c->whatsapp) }}</div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($c->cpf)
                                    <div><span class="badge bg-light text-dark border">CPF</span> <code>{{ $c->formatted_cpf }}</code></div>
                                @elseif($c->cnpj)
                                    <div><span class="badge bg-light text-dark border">CNPJ</span> <code>{{ $c->formatted_cnpj }}</code></div>
                                @elseif($c->document)
                                    <code>{{ $c->document }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $c->cidade ? $c->cidade . ($c->uf ? '/' . strtoupper($c->uf) : '') : '—' }}
                            </td>
                            <td>
                                <x-ui.status-badge :status="$c->active ? 'active' : 'inactive'" :label="$c->active ? 'Ativo' : 'Inativo'" />
                            </td>
                            <td class="all text-end no-export col-actions" style="width: 90px; min-width: 85px; max-width: 100px; white-space: nowrap;">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                    <a href="{{ route('admin.billing.customers.edit', $c->id) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <x-ui.empty-state icon="bi-people" title="Nenhum contato encontrado" message="Nenhum contato cadastrado na base do CRM.">
                                    <x-slot:actions>
                                        <a href="{{ route('admin.billing.customers.create') }}" class="btn btn-primary">
                                            <i class="bi bi-plus-lg me-1"></i> Adicionar primeiro contato
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
