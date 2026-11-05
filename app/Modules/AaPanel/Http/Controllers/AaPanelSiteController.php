<?php

namespace App\Modules\AaPanel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AaPanel\Models\AaPanelAccount;
use App\Modules\AaPanel\Models\AaPanelServer;
use App\Modules\AaPanel\Services\AaPanelClient;

class AaPanelSiteController extends Controller
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

    public function index(AaPanelServer $server)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            abort(403, 'Acesso negado.');
        }

        $client = new AaPanelClient($server);
        $sitesResponse = $client->listSites();

        $sites = [];
        if ($sitesResponse['ok'] && isset($sitesResponse['data']['data'])) {
            $sites = $sitesResponse['data']['data'];
        }

        return view('aapanel::sites.index', [
            'server' => $server,
            'sites' => $sites,
            'apiOk' => $sitesResponse['ok'],
            'apiMessage' => $sitesResponse['message'] ?? null,
        ]);
    }

    public function start(AaPanelServer $server, string $siteId)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }
        $client = new AaPanelClient($server);
        $info = $client->getSiteInfo($siteId);
        $webName = $info['data']['name'] ?? $info['data']['domain'] ?? '';
        $response = $client->startSite($siteId, $webName);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function stop(AaPanelServer $server, string $siteId)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }
        $client = new AaPanelClient($server);
        $info = $client->getSiteInfo($siteId);
        $webName = $info['data']['name'] ?? $info['data']['domain'] ?? '';
        $response = $client->stopSite($siteId, $webName);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function destroy(AaPanelServer $server, string $siteId)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }
        $client = new AaPanelClient($server);

        $info = $client->getSiteInfo($siteId);
        $webName = $info['data']['name'] ?? $info['data']['domain'] ?? '';

        $response = $client->deleteSite($siteId, $webName);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function sync(AaPanelServer $server)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }
        $client = new AaPanelClient($server);
        $sitesResponse = $client->listSites();

        if (!$sitesResponse['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $sitesResponse['message'] ?? 'Falha ao listar sites do aaPanel.',
            ], 422);
        }

        $sites = $sitesResponse['data']['data'] ?? [];
        $imported = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            $domain = $site['name'] ?? $site['domain'] ?? null;
            $siteId = $site['id'] ?? null;

            if (!$domain || !$siteId) {
                continue;
            }

            $exists = AaPanelAccount::where('server_id', $server->id)
                ->where('domain', $domain)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            AaPanelAccount::create([
                'tenant_id' => $this->currentTenantId(),
                'server_id' => $server->id,
                'plan_id' => null,
                'customer_id' => null,
                'domain' => $domain,
                'username' => $site['ftp_username'] ?? null,
                'site_id' => $siteId,
                'status' => ($site['status'] ?? 1) == 1 ? 'active' : 'suspended',
                'ssl_enabled' => (bool) ($site['ssl'] ?? false),
                'provisioned_at' => now(),
                'meta' => [
                    'path' => $site['path'] ?? null,
                    'php_version' => $site['php_version'] ?? null,
                    'synced_from_aapanel' => true,
                ],
            ]);

            $imported++;
        }

        return response()->json([
            'ok' => true,
            'message' => "Sincronizacao concluida. {$imported} importados, {$skipped} ja existiam.",
            'data' => ['imported' => $imported, 'skipped' => $skipped],
        ]);
    }

    public function import(AaPanelServer $server, string $siteId)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }
        $client = new AaPanelClient($server);
        $info = $client->getSiteInfo($siteId);

        if (!$info['ok']) {
            return response()->json([
                'ok' => false,
                'message' => 'Nao foi possivel obter info do site: ' . ($info['message'] ?? 'erro'),
            ], 422);
        }

        $site = $info['data'] ?? [];
        $domain = $site['name'] ?? $site['domain'] ?? null;

        if (!$domain) {
            return response()->json([
                'ok' => false,
                'message' => 'Dominio nao encontrado nos dados do site.',
            ], 422);
        }

        $exists = AaPanelAccount::where('server_id', $server->id)
            ->where('domain', $domain)
            ->exists();

        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'Conta com este dominio ja existe no sistema.',
            ], 422);
        }

        $account = AaPanelAccount::create([
            'tenant_id' => $this->currentTenantId(),
            'server_id' => $server->id,
            'plan_id' => null,
            'customer_id' => null,
            'domain' => $domain,
            'username' => $site['ftp_username'] ?? null,
            'site_id' => $siteId,
            'status' => ($site['status'] ?? 1) == 1 ? 'active' : 'suspended',
            'ssl_enabled' => (bool) ($site['ssl'] ?? false),
            'provisioned_at' => now(),
            'meta' => [
                'path' => $site['path'] ?? null,
                'php_version' => $site['php_version'] ?? null,
                'synced_from_aapanel' => true,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Conta '{$domain}' importada com sucesso.",
            'data' => $account,
        ]);
    }

    public function phpVersions(AaPanelServer $server)
    {
        if ($this->currentTenantId() !== null && $server->tenant_id !== $this->currentTenantId()) {
            return response()->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }
        $client = new AaPanelClient($server);
        $response = $client->getPhpVersions();

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }
}
