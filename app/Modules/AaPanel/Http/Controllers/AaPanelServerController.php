<?php

namespace App\Modules\AaPanel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AaPanel\Http\Requests\StoreAaPanelServerRequest;
use App\Modules\AaPanel\Models\AaPanelServer;
use App\Modules\AaPanel\Services\AaPanelClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AaPanelServerController extends Controller
{
    protected function currentTenantId(): ?int
    {
        if (request()->is('painel') || request()->is('painel/*')) {
            $user = auth('web')->user();
            if (! $user) return null;
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            return $tenant?->id;
        }
        if (auth('admin')->check()) { return null; }
        $user = auth('web')->user();
        if (! $user) { return null; }
        $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
        return $tenant?->id;
    }

    protected function scopeByTenant($query)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) { $query->where('tenant_id', $tenantId); }
        return $query;
    }

    public function index()
    {
        $servers = AaPanelServer::query();
        $this->scopeByTenant($servers);
        return view('aapanel::servers.index', [
            'servers' => $servers->latest()->paginate(20),
        ]);
    }

    public function store(StoreAaPanelServerRequest $request)
    {
        $data = $request->validated();

        $server = new AaPanelServer();
        $server->tenant_id = $this->currentTenantId();
        $server->name = $data['name'];
        $server->host = $data['host'];
        $server->port = $data['port'];
        $server->security_entry = $data['security_entry'] ?? null;
        $server->use_ssl = (bool) ($data['use_ssl'] ?? true);
        $server->api_key = (string) ($data['api_key'] ?? '');
        $server->status = 'unknown';
        $server->save();

        return response()->json([
            'ok' => true,
            'message' => 'Servidor aaPanel cadastrado com sucesso.',
            'data' => $server,
            'server_id' => $server->id,
        ]);
    }

    public function update(StoreAaPanelServerRequest $request, AaPanelServer $server)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }

        $data = $request->validated();

        $server->name = $data['name'];
        $server->host = $data['host'];
        $server->port = $data['port'];
        $server->security_entry = $data['security_entry'] ?? null;
        $server->use_ssl = (bool) ($data['use_ssl'] ?? true);
        $server->api_key = (string) ($data['api_key'] ?? '');

        $server->save();

        return response()->json([
            'ok' => true,
            'message' => 'Servidor aaPanel atualizado com sucesso.',
        ]);
    }

    public function destroy(AaPanelServer $server)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }

        if ($server->accounts()->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'Nao e possivel remover servidor com contas vinculadas.',
            ], 422);
        }

        $server->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Servidor removido com sucesso.',
        ]);
    }

    public function testConnection(Request $request)
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'security_entry' => ['nullable', 'string', 'max:60'],
            'use_ssl' => ['nullable', 'boolean'],
            'api_key' => ['required', 'string', 'max:500'],
        ]);

        $server = new AaPanelServer();
        $server->host = $data['host'];
        $server->port = $data['port'];
        $server->security_entry = $data['security_entry'] ?? null;
        $server->use_ssl = (bool) ($data['use_ssl'] ?? true);
        $server->api_key = $data['api_key'];

        $client = new AaPanelClient($server);
        $response = $client->test();

        $metrics = null;
        if ($response['ok']) {
            try {
                $sys = $client->getSystemTotal();
                if ($sys['ok'] && !empty($sys['data'])) {
                    $metrics = $this->parseMetrics($sys['data']);
                }
            } catch (\Throwable $e) {}
        }

        $response['metrics'] = $metrics;
        $response['debug'] = [
            'url_base' => $server->baseUrl(),
            'url_api' => $server->apiUrl(),
            'api_status' => $response['status'],
            'aaPanel_response' => $response['data'] ?? null,
            'endpoint' => $response['endpoint'] ?? null,
            'full_url' => $server->baseUrl() . '/system?action=GetSystemTotal',
        ];

        $response['url_tested'] = $server->baseUrl();

        return response()->json($response, $response['ok'] ? 200 : 422);
    }

    public function test(AaPanelServer $server)
    {
        return $this->syncServerMetrics($server);
    }

    public function metrics(AaPanelServer $server)
    {
        return $this->syncServerMetrics($server);
    }

    public function syncAll()
    {
        $servers = AaPanelServer::query();
        $this->scopeByTenant($servers);
        $list = $servers->get();
        $results = [];

        foreach ($list as $server) {
            $client = new AaPanelClient($server);
            $client->flushLocalCache();
            $testRes = $client->test();

            $metrics = null;
            if ($testRes['ok']) {
                try {
                    $sys = $client->getSystemTotal();
                    $disk = $client->getDiskInfo();
                    if ($sys['ok'] && !empty($sys['data'])) {
                        $sysData = $sys['data'];
                        if ($disk['ok'] && !empty($disk['data'])) {
                            $sysData['disk'] = $disk['data'];
                        }
                        $metrics = $this->parseMetrics($sysData);
                    }
                } catch (\Throwable $e) {}
            }

            $server->status = $testRes['ok'] ? 'online' : 'offline';
            $server->last_check_at = now();
            $server->last_error = $testRes['ok'] ? null : $testRes['message'];

            $meta = $server->meta ?? [];
            if ($metrics) {
                $meta['metrics'] = $metrics;
                $meta['last_synced_at'] = now()->toIso8601String();
            }
            $server->meta = $meta;
            $server->save();

            $results[] = [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'status' => $server->status,
                'last_check_at' => $server->last_check_at->diffForHumans(),
                'last_error' => $server->last_error,
                'metrics' => $metrics,
            ];
        }

        return response()->json([
            'ok' => true,
            'message' => 'Sincronização em tempo real concluída para todos os servidores.',
            'servers' => $results,
        ]);
    }

    protected function syncServerMetrics(AaPanelServer $server)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }

        $client = new AaPanelClient($server);
        $client->flushLocalCache();
        $response = $client->test();

        $metrics = null;
        if ($response['ok']) {
            try {
                $sys = $client->getSystemTotal();
                $disk = $client->getDiskInfo();
                if ($sys['ok'] && !empty($sys['data'])) {
                    $sysData = $sys['data'];
                    if ($disk['ok'] && !empty($disk['data'])) {
                        $sysData['disk'] = $disk['data'];
                    }
                    $metrics = $this->parseMetrics($sysData);
                }
            } catch (\Throwable $e) {}
        }

        $meta = $server->meta ?? [];
        if ($metrics) {
            $meta['metrics'] = $metrics;
            $meta['last_synced_at'] = now()->toIso8601String();
        }

        $server->update([
            'status' => $response['ok'] ? 'online' : 'offline',
            'last_check_at' => now(),
            'last_error' => $response['ok'] ? null : $response['message'],
            'meta' => $meta,
        ]);

        $response['metrics'] = $metrics;
        $response['last_check_at'] = $server->last_check_at->diffForHumans();
        $response['url_tested'] = $server->apiUrl();

        return response()->json($response, $response['ok'] ? 200 : 422);
    }

    protected function parseMetrics(array $raw): array
    {
        $cpu = 0;
        $cpuCores = 1;
        if (isset($raw['cpu']) && is_array($raw['cpu'])) {
            $cpu = floatval($raw['cpu'][0] ?? 0);
            $cpuCores = intval($raw['cpu'][1] ?? 1);
        } elseif (isset($raw['cpu'])) {
            $cpu = floatval($raw['cpu']);
        } elseif (isset($raw['cpuRealUsed'])) {
            $cpu = floatval($raw['cpuRealUsed']);
            $cpuCores = intval($raw['cpuNum'] ?? 1);
        }

        $memPercent = 0;
        $memReal = '0 MB';
        $memTotal = '0 MB';
        if (isset($raw['mem']) && is_array($raw['mem'])) {
            $memData = $raw['mem'];
            $t = floatval($memData['memTotal'] ?? 0);
            $u = floatval($memData['memRealUsed'] ?? $memData['memUsed'] ?? 0);
        } else {
            $t = floatval($raw['memTotal'] ?? 0);
            $u = floatval($raw['memRealUsed'] ?? $raw['memUsed'] ?? 0);
        }
        
        if ($t > 0) {
            $memPercent = round(($u / $t) * 100, 1);
            $memReal = round($u / 1024, 1) . ' GB';
            $memTotal = round($t / 1024, 1) . ' GB';
        }

        $diskPercent = 0;
        $diskUsed = '0 GB';
        $diskTotal = '0 GB';
        if (isset($raw['disk']) && is_array($raw['disk']) && !empty($raw['disk'])) {
            $d = $raw['disk'][0] ?? [];
            if (isset($d['size']) && is_array($d['size'])) {
                $diskUsed = $d['size'][0] ?? '0 GB';
                $diskTotal = $d['size'][1] ?? '0 GB';
                $diskPercent = intval(rtrim($d['size'][2] ?? '0', '%'));
            }
        }

        return [
            'cpu_percent' => $cpu,
            'cpu_cores' => $cpuCores,
            'mem_percent' => $memPercent,
            'mem_used' => $memReal,
            'mem_total' => $memTotal,
            'disk_percent' => $diskPercent,
            'disk_used' => $diskUsed,
            'disk_total' => $diskTotal,
            'system' => $raw['system'] ?? 'Linux',
            'uptime' => $raw['time'] ?? 'Ativo',
            'site_count' => intval($raw['site_count'] ?? 0),
            'database_count' => intval($raw['database_count'] ?? 0),
            'ftp_count' => intval($raw['ftp_count'] ?? 0),
        ];
    }
}

