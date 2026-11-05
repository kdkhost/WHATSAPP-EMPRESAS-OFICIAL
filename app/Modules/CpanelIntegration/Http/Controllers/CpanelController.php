<?php

namespace App\Modules\CpanelIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CpanelIntegration\Models\CpanelServer;
use App\Modules\CpanelIntegration\Services\WhmClient;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CpanelController extends Controller
{
    public function index()
    {
        $query = CpanelServer::query();
        $tenantId = null;
        if (auth('web')->check() && request()->is('painel*')) {
            $tenantId = Tenant::where('user_id', auth('web')->id())->value('id');
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        }
        $servers = $query->orderByDesc('id')->get();
        return view('cpanel::index', compact('servers'));
    }

    private function getServerScoped(int $id): CpanelServer
    {
        $query = CpanelServer::query();
        if (auth('web')->check() && request()->is('painel*')) {
            $tenantId = Tenant::where('user_id', auth('web')->id())->value('id');
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        }
        return $query->findOrFail($id);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:80',
            'hostname'   => 'required|string|max:120',
            'port'       => 'required|integer|min:1|max:65535',
            'type'       => 'required|in:whm,cpanel',
            'username'   => 'required|string|max:60',
            'api_token'  => 'required|string',
            'verify_ssl' => 'nullable|boolean',
        ]);

        if (auth('web')->check() && request()->is('painel*')) {
            $tenantId = Tenant::where('user_id', auth('web')->id())->value('id');
            if ($tenantId) {
                $data['tenant_id'] = $tenantId;
            }
        }

        $server = CpanelServer::create($data + ['active' => true]);
        $client = new WhmClient($server);
        $result = $client->testConnection();
        if (!$result['ok']) {
            $server->update(['active' => false]);
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Falha ao conectar. Verifique credenciais e firewall.'], 422);
        }

        return response()->json(['ok' => true, 'server' => $server]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->getServerScoped($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function listAccounts(int $id)
    {
        $server = $this->getServerScoped($id);
        
        if (request()->wantsJson()) {
            try {
                $accounts = (new WhmClient($server))->listAccounts();
                return response()->json(['ok' => true, 'accounts' => $accounts]);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
        }
        
        try {
            $accounts = (new WhmClient($server))->listAccounts();
        } catch (\Throwable $e) {
            $accounts = [];
            session()->flash('error', 'Falha ao conectar no servidor WHM: ' . $e->getMessage());
        }

        return view('cpanel::accounts', compact('server', 'accounts'));
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:60',
            'reason'   => 'nullable|string|max:255',
        ]);
        $server = $this->getServerScoped($id);
        $ok = (new WhmClient($server))->suspendAccount($data['username'], $data['reason'] ?? 'Inadimplencia');
        return response()->json(['ok' => $ok]);
    }

    public function unsuspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['username' => 'required|string|max:60']);
        $server = $this->getServerScoped($id);
        $ok = (new WhmClient($server))->unsuspendAccount($data['username']);
        return response()->json(['ok' => $ok]);
    }

    public function createAccount(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|alpha_num|max:16',
            'domain'   => 'required|string|max:120',
            'password' => 'required|string|min:8',
            'plan'     => 'required|string|max:60',
            'email'    => 'nullable|email',
        ]);
        $server = $this->getServerScoped($id);
        try {
            $resp = (new WhmClient($server))->createAccount($data);
            return response()->json(['ok' => true, 'response' => $resp]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function logs(int $id)
    {
        $server = $this->getServerScoped($id);
        $logs = DB::table('cpanel_actions_log')
            ->where('server_id', $id)
            ->orderByDesc('id')
            ->paginate(50);
            
        return view('cpanel::logs', compact('server', 'logs'));
    }
}
