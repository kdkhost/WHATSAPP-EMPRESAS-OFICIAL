@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Configurações aaPanel')

@section('content')
<div class="container-fluid">
    <x-addons.tenant-shell
        slug="aapanel"
        title="aaPanel"
        description="Configurações do addon aaPanel para sua conta."
        icon="bi bi-server"
        :items="\App\Support\TenantAddonNavigation::aapanel()"
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
