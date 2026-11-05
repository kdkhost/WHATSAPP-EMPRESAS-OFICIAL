<?php

namespace App\Modules\WhmcsIntegration\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

class WhmcsTenantController extends Controller
{
    public function logs()
    {
        return view('whmcs::tenant.logs', ['currentLabel' => 'Logs']);
    }

    public function settings()
    {
        return view('whmcs::tenant.settings', ['currentLabel' => 'Configurações']);
    }

    public function wipeData()
    {
        $tenantId = app(\App\Modules\Tenancy\Services\TenantContext::class)->currentTenantId() ?: auth()->user()?->tenant_id;
        if (!$tenantId) {
            abort(403, 'Acesso negado.');
        }

        try {
            $invoices = \App\Models\Invoice::where('tenant_id', $tenantId)->whereNotNull('meta->whmcs_invoice_id')->delete();
            $customers = \App\Models\Customer::where('tenant_id', $tenantId)->whereNotNull('meta->whmcs_id')->delete();
            $services = \App\Modules\WhmcsIntegration\Models\WhmcsService::where('tenant_id', $tenantId)->delete();
            
            // Também apaga conexões se quiser, mas como o usuário quer só limpar, vamos limpar tudo relacionado
            \App\Modules\WhmcsIntegration\Models\WhmcsConnection::where('tenant_id', $tenantId)->delete();

            return response()->json(['ok' => true, 'message' => "Limpeza concluída! Foram removidos: $invoices faturas, $customers clientes e $services serviços."]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Erro ao limpar dados: ' . $e->getMessage()], 500);
        }
    }
}
