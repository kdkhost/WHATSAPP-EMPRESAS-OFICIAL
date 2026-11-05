@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Logs cPanel')

@section('content')
<div class="container-fluid">
    <x-addons.tenant-shell
        slug="cpanel"
        title="Integração cPanel"
        description="Logs de operações do cPanel."
        icon="bi bi-hdd-stack"
        :items="\App\Support\TenantAddonNavigation::cpanel()"
        :current-label="$currentLabel ?? 'Logs'"
    >
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Logs</h5></div>
            <div class="card-body">
                <p class="text-muted mb-0">Nenhum log registrado no momento.</p>
            </div>
        </div>
    </x-addons.tenant-shell>
</div>
@endsection
