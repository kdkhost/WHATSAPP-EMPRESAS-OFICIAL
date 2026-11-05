@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'aaPanel - Dashboard')

@section('content')
<div class="container-fluid py-3">

    @include('aapanel::partials.header', [
        'title' => 'Dashboard aaPanel',
        'subtitle' => 'Visão geral do ecossistema de hospedagem, servidores e contas ativas.'
    ])

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary rounded-3 p-3 fs-3"><i class="bi bi-server"></i></div>
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Servidores</div>
                        <div class="fs-3 fw-bold">{{ $servers }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info rounded-3 p-3 fs-3"><i class="bi bi-layers"></i></div>
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Planos</div>
                        <div class="fs-3 fw-bold">{{ $plans }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning rounded-3 p-3 fs-3"><i class="bi bi-globe"></i></div>
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Contas</div>
                        <div class="fs-3 fw-bold">{{ $accounts }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success rounded-3 p-3 fs-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Ativas</div>
                        <div class="fs-3 fw-bold">{{ $activeAccounts }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border">
        <div class="card-header border-bottom py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Últimas Operações Registradas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Operação</th>
                            <th>Status</th>
                            <th>Mensagem</th>
                            <th class="pe-3 text-end">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-3"><span class="badge bg-secondary font-monospace">{{ $log->operation }}</span></td>
                            <td><span class="badge bg-{{ $log->status === 'success' ? 'success' : 'danger' }}">{{ ucfirst($log->status) }}</span></td>
                            <td class="small">{{ Str::limit($log->message, 80) }}</td>
                            <td class="pe-3 text-end small text-muted">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Nenhuma operação registrada no sistema.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
