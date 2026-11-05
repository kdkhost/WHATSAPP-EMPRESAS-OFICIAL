@extends('admin.layouts.app')
@section('title', 'Tentativas de Login')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="m-0"><i class="bi bi-shield-exclamation me-2"></i>Tentativas de Login</h4>
    </div>

    <div class="card">
        <div class="card-body">
            @if(isset($attempts) && count($attempts) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-security-login-attempts" data-export-title="Tentativas de Login" style="width: 100% !important;">
                        <thead class="table-light">
                            <tr>
                                <th class="all" data-priority="1">IP</th>
                                <th class="all" data-priority="2">Email</th>
                                <th class="min-tablet" data-priority="3">Sucesso</th>
                                <th class="desktop">User Agent</th>
                                <th class="min-tablet" data-priority="4">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attempts as $attempt)
                                <tr class="{{ ($attempt->success ?? false) ? '' : 'table-danger' }}">
                                    <td><code>{{ $attempt->ip ?? '-' }}</code></td>
                                    <td>{{ $attempt->email ?? '-' }}</td>
                                    <td>
                                        @if($attempt->success ?? false)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Sucesso</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Falhou</span>
                                        @endif
                                    </td>
                                    <td class="text-truncate" style="max-width:200px;">{{ $attempt->user_agent ?? '-' }}</td>
                                    <td>{{ $attempt->created_at ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-shield-check display-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">Nenhuma tentativa de login registrada</h5>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
