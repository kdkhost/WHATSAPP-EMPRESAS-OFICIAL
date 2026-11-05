@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Configurações WHMCS')

@section('content')
<div class="container-fluid">
    <x-addons.tenant-shell
        slug="whmcs"
        title="Integração WHMCS"
        description="Configurações da integração WHMCS."
        icon="bi bi-box-seam"
        :items="\App\Support\TenantAddonNavigation::whmcs()"
        :current-label="$currentLabel ?? 'Configurações'"
    >
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear me-2"></i>Configurações</h5></div>
            <div class="card-body">
                <p class="text-muted mb-4">Gerencie as configurações da sua integração com o WHMCS. Caso precise refazer a sincronização ou queira excluir todos os dados importados, utilize o botão abaixo.</p>
                <button type="button" class="btn btn-outline-danger" id="btn-wipe-whmcs">
                    <i class="bi bi-trash me-1"></i> Limpar Dados e Desconectar
                </button>
            </div>
        </div>
    </x-addons.tenant-shell>
</div>
@endsection

@section('scripts')
<script>
(function(){
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const base = '/painel/addons/whmcs';

    const toast = (msg, type='success') => {
        if (window.toastr) { window.toastr[type](msg); return; }
        if (window.Swal) {
            Swal.fire({ icon: type === 'error' ? 'error' : 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        } else {
            console.log(msg);
        }
    };

    const btnWipe = document.getElementById('btn-wipe-whmcs');
    if (btnWipe) {
        btnWipe.addEventListener('click', async () => {
            const ok = await Swal.fire({
                title: 'Limpeza de Dados WHMCS',
                text: 'Isso irá desconectar o WHMCS e apagar todos os clientes, faturas e serviços que foram importados dele. Esta ação é irreversível. Deseja continuar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Sim, limpar tudo',
                cancelButtonText: 'Cancelar'
            });

            if (ok.isConfirmed) {
                const originalHtml = btnWipe.innerHTML;
                btnWipe.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Limpando...';
                btnWipe.disabled = true;

                try {
                    const r = await fetch(`${base}/limpar`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                        body: JSON.stringify({_method: 'DELETE'})
                    });
                    const d = await r.json();
                    if (d.ok || r.ok) {
                        toast(d.message || 'Dados limpos com sucesso!');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        toast(d.message || 'Erro ao limpar dados.', 'error');
                        btnWipe.innerHTML = originalHtml;
                        btnWipe.disabled = false;
                    }
                } catch (e) {
                    toast('Erro de rede ao processar.', 'error');
                    btnWipe.innerHTML = originalHtml;
                    btnWipe.disabled = false;
                }
            }
        });
    }
})();
</script>
@endsection
