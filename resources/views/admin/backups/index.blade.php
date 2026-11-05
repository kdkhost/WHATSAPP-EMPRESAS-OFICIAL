@extends('admin.layouts.app')
@section('title', 'Backups')

@php
    $totalSize = collect($backups)->sum('size_mb');
    $stats = [
        'total'  => count($backups),
        'size'   => $totalSize,
        'newest' => count($backups) ? $backups[0]['date'] ?? null : null,
        'oldest' => count($backups) ? end($backups)['date'] ?? null : null,
    ];
@endphp

@push('head')
<style>
.backups-page {
    --backups-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --backups-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.backups-kpi {
    height: 100%;
}
.backups-kpi .card-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.backups-kpi__label {
    display: block;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .45rem;
}
.backups-kpi__value {
    display: block;
    font-size: 1.4rem;
    font-weight: 700;
    line-height: 1.05;
}
.backups-kpi__hint {
    display: block;
    margin-top: .5rem;
    color: var(--bs-secondary-color);
    font-size: .82rem;
}
.backups-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    vertical-align: middle;
}
.backups-table tbody td {
    vertical-align: middle;
}
.backups-file {
    display: flex;
    align-items: center;
    gap: .85rem;
}
.backups-file__meta {
    min-width: 0;
}
.backups-file__name {
    display: block;
    font-weight: 700;
}
.backups-empty {
    display: grid;
    place-items: center;
    min-height: 220px;
    color: var(--bs-secondary-color);
    text-align: center;
}
</style>
@endpush

@section('content')
<div class="container-fluid backups-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Recuperacao e seguranca</span>
            <h1 class="admin-premium-hero__title">Backups do banco com leitura mais clara de volume, janela e acao manual.</h1>
            <p class="admin-premium-hero__text">
                Esta tela agora separa melhor a situacao da rotina de backup, o espaco ocupado e o inventario dos arquivos disponiveis para download ou limpeza.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Rotina padrao</span>
                <span class="dashboard-note__value">SQL.GZ diario com retencao de 30 dias</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Espaco usado</span>
                <span class="dashboard-note__value">{{ number_format($stats['size'], 1, ',', '.') }} MB</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Mais recente</span>
                <span class="dashboard-note__value">{{ $stats['newest'] ?? '-' }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Mais antigo</span>
                <span class="dashboard-note__value">{{ $stats['oldest'] ?? '-' }}</span>
            </div>
        </div>
    </section>

    <section class="premium-panel mb-4">
        <div class="premium-panel__header">
            <div>
                <span class="premium-panel__kicker">Acao manual</span>
                <h2 class="premium-panel__title">Geracao imediata de backup</h2>
                <p class="premium-panel__subtitle mb-0">Crie um snapshot manual quando precisar proteger uma alteracao importante antes de operar no sistema.</p>
            </div>
            <button type="button" class="btn btn-primary" id="btn-create">
                <i class="bi bi-database-add me-1"></i> Criar backup agora
            </button>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card backups-kpi">
                <div class="card-body">
                    <div>
                        <span class="backups-kpi__label">Total de backups</span>
                        <span class="backups-kpi__value">{{ $stats['total'] }}</span>
                        <span class="backups-kpi__hint">Arquivos atualmente disponiveis.</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-archive"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card backups-kpi">
                <div class="card-body">
                    <div>
                        <span class="backups-kpi__label">Espaco usado</span>
                        <span class="backups-kpi__value">{{ number_format($stats['size'], 1, ',', '.') }} MB</span>
                        <span class="backups-kpi__hint">Consumo acumulado da pasta de backup.</span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-hdd"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card backups-kpi">
                <div class="card-body">
                    <div>
                        <span class="backups-kpi__label">Mais recente</span>
                        <span class="backups-kpi__value fs-6">{{ $stats['newest'] ?? '-' }}</span>
                        <span class="backups-kpi__hint">Ultima geracao registrada.</span>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-arrow-up-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card backups-kpi">
                <div class="card-body">
                    <div>
                        <span class="backups-kpi__label">Mais antigo</span>
                        <span class="backups-kpi__value fs-6">{{ $stats['oldest'] ?? '-' }}</span>
                        <span class="backups-kpi__hint">Limite inferior do inventario atual.</span>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-arrow-down-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="dashboard-panel__title mb-0">Arquivos disponiveis</h3>
                <span class="dashboard-panel__subtitle">Inventario completo de snapshots SQL.GZ compactados.</span>
            </div>
            <small class="text-muted">Download e limpeza manual</small>
        </div>
        <div class="card-body p-0">
            <div>
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-backups" data-export-title="Backups" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Arquivo</th>
                            <th class="min-tablet text-end" data-priority="2">Tamanho</th>
                            <th class="desktop">Data</th>
                            <th class="all text-end no-export" data-priority="1" style="width: 100px; min-width: 90px; max-width: 110px; white-space: nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="backups-body">
                        @forelse($backups as $backup)
                            <tr id="bk-{{ $backup['name'] }}">
                                <td>
                                    <div class="backups-file">
                                        <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-file-earmark-zip"></i></div>
                                        <div class="backups-file__meta">
                                            <span class="backups-file__name">{{ $backup['name'] }}</span>
                                            <small class="text-muted">Snapshot do banco em formato compactado.</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $backup['size_mb'] }} MB</span>
                                </td>
                                <td>{{ $backup['date'] }}</td>
                                <td class="all text-end no-export" style="width: 100px; min-width: 90px; max-width: 110px; white-space: nowrap;">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                        <a href="{{ route('admin.backups.download', $backup['name']) }}" class="btn btn-sm btn-outline-primary p-1 px-2" title="Baixar Backup">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" data-del="{{ $backup['name'] }}" title="Excluir Backup">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-0">
                                    <div class="backups-empty">
                                        <div>
                                            <i class="bi bi-archive fs-1 d-block mb-2 opacity-50"></i>
                                            Nenhum backup criado ainda.
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-primary" onclick="document.getElementById('btn-create').click()">
                                                    <i class="bi bi-database-add me-1"></i> Criar primeiro backup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
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

document.getElementById('btn-create').addEventListener('click', async () => {
    const ok = await Swal.fire({
        icon: 'question',
        title: 'Criar backup agora?',
        text: 'Pode demorar alguns segundos dependendo do tamanho do banco.',
        showCancelButton: true,
        confirmButtonText: 'Criar',
        cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;

    const button = document.getElementById('btn-create');
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Gerando...';

    try {
        const response = await fetch('{{ route("admin.backups.create") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (data.ok) {
            window.toast.success('Backup criado!');
            setTimeout(() => location.reload(), 800);
        } else {
            window.toast.error(data.error || 'Falha');
        }
    } catch (error) {
        window.toast.error('Erro de rede.');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});

document.querySelectorAll('[data-del]').forEach(button => button.onclick = async () => {
    const ok = await Swal.fire({
        icon: 'warning',
        title: 'Excluir backup?',
        text: 'Esta acao nao pode ser desfeita.',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;

    try {
        const response = await fetch(`{{ url('/admin/backups') }}/${button.dataset.del}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf }
        });
        if (response.ok) {
            document.getElementById(`bk-${button.dataset.del}`)?.remove();
            window.toast.success('Excluido!');
        } else {
            window.toast.error('Erro.');
        }
    } catch (error) {
        window.toast.error('Erro de rede.');
    }
});
</script>
@endpush
