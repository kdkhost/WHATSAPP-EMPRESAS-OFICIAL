@extends('admin.layouts.app')
@section('title', 'Segurança - Auditoria')
@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Auditoria de segurança"
        subtitle="Histórico administrativo de ações sensíveis, IPs e assuntos auditados pelo módulo de segurança."
        eyebrow="WAF Enterprise"
        class="mb-4"
    />

    <x-ui.premium-card title="Registros de auditoria">
        <div class="p-0">
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-security-audit" data-export-title="Auditoria de Segurança" style="width: 100% !important;">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Data</th>
                        <th class="all" data-priority="1">Admin</th>
                        <th class="min-tablet" data-priority="2">Ação</th>
                        <th class="desktop">Assunto</th>
                        <th class="desktop">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $l)
                        <tr>
                            <td class="small">{{ \Carbon\Carbon::parse($l->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $l->admin_name ?? '-' }} <span class="text-muted small">({{ $l->admin_email ?? '' }})</span></td>
                            <td><code>{{ $l->action }}</code></td>
                            <td class="small">{{ $l->subject_type }}#{{ $l->subject_id }}</td>
                            <td><code>{{ $l->ip }}</code></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted p-5">
                                <i class="bi bi-shield fs-1 d-block mb-2 opacity-50"></i>
                                Nenhum registro de auditoria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator && $logs->hasPages())
            <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
        @endif
    </x-ui.premium-card>
</div>
@endsection
