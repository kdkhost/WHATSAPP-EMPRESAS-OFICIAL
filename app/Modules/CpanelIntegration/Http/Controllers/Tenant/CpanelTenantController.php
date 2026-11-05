<?php

namespace App\Modules\CpanelIntegration\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

class CpanelTenantController extends Controller
{
    public function logs()
    {
        return view('cpanel::tenant.logs', ['currentLabel' => 'Logs']);
    }

    public function settings()
    {
        return view('cpanel::tenant.settings', ['currentLabel' => 'Configurações']);
    }
}
