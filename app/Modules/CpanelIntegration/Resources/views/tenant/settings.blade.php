@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Configurações cPanel')

@section('content')
<div class="container-fluid">
    <x-addons.tenant-shell
        slug="cpanel"
        title="Integração cPanel"
        description="Configurações da integração cPanel."
        icon="bi bi-hdd-stack"
        :items="\App\Support\TenantAddonNavigation::cpanel()"
        :current-label="$currentLabel ?? 'Configurações'"
    >
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear me-2"></i>Configurações</h5></div>
            <div class="card-body">
                <p class="text-muted mb-0">Nenhuma configuração adicional disponível no momento.</p>
            </div>
        </div>
    </x-addons.tenant-shell>
</div>
@endsection
