@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Logs do Servidor: ' . $server->name)

@section('content')
<div class="container-fluid">
    @php
        $isTenant = request()->is('painel*');
        $backUrl = $isTenant ? route('tenant.cpanel.index') : route('admin.cpanel.index');
    @endphp

    <div class="d-flex align-items-center mb-4">
        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <div>
            <h4 class="mb-0">Logs de Ações</h4>
            <div class="text-muted small">Servidor: <strong>{{ $server->name }}</strong> ({{ $server->hostname }})</div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header py-3">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-info"></i> Histórico de ações</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data/Hora</th>
                        <th>Ação</th>
                        <th>Alvo</th>
                        <th>Detalhes</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="small text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td><code class="text-primary">{{ $log->action }}</code></td>
                            <td>
                                @if($log->target)
                                    <span class="badge bg-light text-dark border">{{ $log->target }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small">{{ $log->response ?: '-' }}</td>
                            <td>
                                @if($log->status === 'success')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2-circle me-1"></i> OK</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i> Erro</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary"></i>
                                Nenhum log registrado neste servidor ainda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer border-top-0 pt-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
