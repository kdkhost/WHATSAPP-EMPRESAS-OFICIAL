@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Logs WHMCS')

@section('content')
<div class="container-fluid">
    <x-addons.tenant-shell
        slug="whmcs"
        title="Integração WHMCS"
        description="Logs de sincronização e eventos do WHMCS."
        icon="bi bi-box-seam"
        :items="\App\Support\TenantAddonNavigation::whmcs()"
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
