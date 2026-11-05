<?php

namespace App\Modules\AaPanel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AaPanel\Http\Requests\StoreAaPanelAccountRequest;
use App\Modules\AaPanel\Models\AaPanelAccount;
use App\Modules\AaPanel\Models\AaPanelPlan;
use App\Modules\AaPanel\Models\AaPanelServer;
use App\Modules\AaPanel\Services\AaPanelClient;
use App\Modules\AaPanel\Services\AaPanelProvisioningService;

class AaPanelAccountController extends Controller
{
    /**
     * Retorna o tenant_id do usuario autenticado (guard web/tenant),
     * ou null se for admin (guard admin) — admin ve tudo.
     */
    protected function currentTenantId(): ?int
    {
        if (request()->is('painel') || request()->is('painel/*')) {
            $user = auth('web')->user();
            if (! $user) {
                return null;
            }
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
            return $tenant?->id;
        }

        if (auth('admin')->check()) {
            return null;
        }

        $user = auth('web')->user();
        if (! $user) {
            return null;
        }

        $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
        return $tenant?->id;
    }

    protected function scopeByTenant($query)
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        return $query;
    }

    /**
     * Aborta com 403 se a conta nao pertencer ao tenant atual.
     * Superadmin (tenant_id = null) tem acesso irrestrito.
     */
    protected function guardAccount(AaPanelAccount $account): void
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId !== null && (int) $account->tenant_id !== $tenantId) {
            abort(403, 'Acesso negado.');
        }
    }

    public function index()
    {
        $accounts = AaPanelAccount::with(['server', 'plan']);
        $this->scopeByTenant($accounts);

        $servers = AaPanelServer::query();
        $this->scopeByTenant($servers);

        $plans = AaPanelPlan::where('active', true);
        $this->scopeByTenant($plans);

        return view('aapanel::accounts.index', [
            'accounts' => $accounts->latest()->paginate(20),
            'servers' => $servers->orderBy('name')->get(),
            'plans' => $plans->orderBy('name')->get(),
        ]);
    }

    public function show(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        return view('aapanel::accounts.show', [
            'account' => $account->load(['server', 'plan', 'logs']),
        ]);
    }

    /**
     * Normaliza um path absoluto removendo . e .. para prevencao de path traversal.
     */
    protected function normalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $result = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($result);
            } else {
                $result[] = $part;
            }
        }
        return '/' . implode('/', $result);
    }

    /**
     * Valida se o path solicitado esta dentro do diretorio permitido da conta.
     */
    protected function validateFilePath(AaPanelAccount $account, string $path): ?\Illuminate\Http\JsonResponse
    {
        $allowedBase = '/www/wwwroot/' . strtolower(trim($account->domain));
        $normalizedAllowed = strtolower($this->normalizePath($allowedBase));
        $normalizedPath = strtolower($this->normalizePath($path));

        if ($normalizedPath !== $normalizedAllowed && !str_starts_with($normalizedPath, $normalizedAllowed . '/')) {
            if (!str_starts_with($normalizedPath, '/www/wwwroot/')) {
                return response()->json(['ok' => false, 'message' => 'Acesso negado: path fora do diretorio permitido.'], 403);
            }
        }
        return null;
    }

    // ===================== CICLO DE VIDA E PROVISIONAMENTO =====================
    public function store(StoreAaPanelAccountRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->currentTenantId();
        $account = AaPanelAccount::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Conta criada no sistema. Execute o provisionamento para criar no aaPanel.',
            'data' => $account,
        ]);
    }

    public function provision(AaPanelAccount $account, AaPanelProvisioningService $service)
    {
        $this->guardAccount($account);
        $response = $service->provision($account);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function suspend(AaPanelAccount $account, AaPanelProvisioningService $service)
    {
        $this->guardAccount($account);
        $response = $service->suspend($account);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function unsuspend(AaPanelAccount $account, AaPanelProvisioningService $service)
    {
        $this->guardAccount($account);
        $response = $service->unsuspend($account);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function terminate(AaPanelAccount $account, AaPanelProvisioningService $service)
    {
        $this->guardAccount($account);
        $response = $service->terminate($account);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function sync(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listSites();
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function ssl(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->applyLetsEncrypt($account->domain, $account->domain);

        if ($response['ok']) {
            $account->update(['ssl_enabled' => true]);
        }

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== SUBDOMINIOS =====================
    public function subdomains(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listSubdomains((string) $account->site_id);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function storeSubdomain(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->createSubdomain(
            (string) $account->site_id,
            request('domain', $account->domain),
            request('dir_name', 'subdomain')
        );
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function destroySubdomain(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->deleteSubdomain(
            (string) $account->site_id,
            request('domain', $account->domain),
            request('dir_name')
        );
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== BANCOS DE DADOS =====================
    public function databases(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listDatabases();

        if ($response['ok']) {
            $raw = $response['data'] ?? [];
            $rows = null;

            if (isset($raw['data']['data']) && is_array($raw['data']['data'])) {
                $rows = $raw['data']['data'];
            } elseif (isset($raw['data']) && is_array($raw['data'])) {
                $rows = $raw['data'];
            } elseif (is_array($raw)) {
                $rows = $raw;
            }

            if ($rows !== null) {
                $dbName = (string) $account->database_name;
                $dbUser = (string) $account->database_username;
                $domain = (string) $account->domain;
                $siteId = (string) ($account->site_id ?: $account->aapanel_site_id);
                $domainParts = explode('.', $domain);
                $subdomainPrefix = $domainParts[0] ?? '';

                $filtered = array_values(array_filter($rows, function ($row) use ($dbName, $dbUser, $domain, $siteId, $subdomainPrefix) {
                    $rowName = $row['name'] ?? '';
                    $rowUser = $row['username'] ?? '';
                    $rowPs   = $row['ps'] ?? '';
                    $rowPid  = (string) ($row['pid'] ?? '');

                    // 1. Coincide com os dados cadastrados na conta
                    if (!empty($dbName) && ($rowName === $dbName || $rowUser === $dbUser)) {
                        return true;
                    }
                    // 2. Remark (ps) coincide ou contém o domínio da conta
                    if (!empty($domain) && (!empty($rowPs) && (stripos($rowPs, $domain) !== false || stripos($domain, $rowPs) !== false))) {
                        return true;
                    }
                    // 3. PID coincide com o ID do site no aaPanel
                    if (!empty($siteId) && $rowPid === $siteId) {
                        return true;
                    }
                    // 4. Nome do banco contém o prefixo do subdomínio (ex: sql_pesquisa para pesquisa.towerc.com.br)
                    if (!empty($subdomainPrefix) && strlen($subdomainPrefix) >= 3 && stripos($rowName, $subdomainPrefix) !== false) {
                        return true;
                    }
                    return false;
                }));

                // Auto-sincroniza o banco encontrado no modelo local se database_name estiver vazio
                if (!empty($filtered)) {
                    $first = $filtered[0];
                    if (empty($account->database_name) || empty($account->database_username)) {
                        $account->update([
                            'database_name'     => $first['name'] ?? $account->database_name,
                            'database_username' => $first['username'] ?? $account->database_username,
                        ]);
                    }
                }

                $response['data'] = ['data' => $filtered, 'page' => $raw['page'] ?? $raw['data']['page'] ?? '1/1'];
            }
        }

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function syncDatabases(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listDatabases();

        if ($response['ok']) {
            $raw = $response['data'] ?? [];
            $rows = $raw['data']['data'] ?? $raw['data'] ?? $raw;
            if (is_array($rows)) {
                $domain = (string) $account->domain;
                $siteId = (string) ($account->site_id ?: $account->aapanel_site_id);
                $domainParts = explode('.', $domain);
                $subdomainPrefix = $domainParts[0] ?? '';

                foreach ($rows as $row) {
                    $rowName = $row['name'] ?? '';
                    $rowUser = $row['username'] ?? '';
                    $rowPs   = $row['ps'] ?? '';
                    $rowPid  = (string) ($row['pid'] ?? '');

                    if ((!empty($domain) && stripos($rowPs, $domain) !== false) ||
                        (!empty($siteId) && $rowPid === $siteId) ||
                        (!empty($subdomainPrefix) && strlen($subdomainPrefix) >= 3 && stripos($rowName, $subdomainPrefix) !== false)) {
                        $account->update([
                            'database_name'     => $rowName,
                            'database_username' => $rowUser,
                        ]);
                        return response()->json([
                            'ok' => true,
                            'message' => "Banco de dados {$rowName} sincronizado com sucesso!",
                            'data' => $row,
                        ]);
                    }
                }
            }
        }

        return response()->json(['ok' => false, 'message' => 'Nenhum banco de dados correspondente foi encontrado no servidor aaPanel.'], 404);
    }

    public function phpMyAdmin(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $server = $account->server;

        $hostOnly = preg_replace('#^https?://#', '', rtrim($server->host, '/'));
        $hostOnly = preg_replace('#:\d+$#', '', $hostOnly);

        $pmaUrl = "https://{$hostOnly}:887";
        $panelUrl = $server->baseUrl() . '/database';

        return response()->json([
            'ok' => true,
            'data' => [
                'pma_url'       => $pmaUrl,
                'panel_url'     => $panelUrl,
                'db_name'       => $account->database_name ?? '',
                'db_user'       => $account->database_username ?? '',
                'db_password'   => $account->database_password ?? '',
                'server_host'   => $hostOnly,
            ],
            'url' => $pmaUrl,
        ]);
    }

    public function destroyDatabase(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $name = request('name');
        $dbUser = request('db_user');

        if ($name !== $account->database_name && !str_contains($name, explode('.', $account->domain)[0])) {
            return response()->json(['ok' => false, 'message' => 'Banco nao pertence a esta conta.'], 403);
        }

        $client = new AaPanelClient($account->server);
        $response = $client->deleteDatabase($name, $dbUser);
        if ($response['ok'] && $name === $account->database_name) {
            $account->update(['database_name' => null, 'database_username' => null]);
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== FTP =====================
    public function ftps(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listFtps();

        if ($response['ok'] && is_array($response['data']['data']['data'] ?? null)) {
            $ftpUser = $account->ftp_username;
            $domain = $account->domain;
            $response['data']['data']['data'] = array_values(array_filter($response['data']['data']['data'], function ($row) use ($ftpUser, $domain) {
                return ($row['name'] ?? '') === $ftpUser || str_contains($row['path'] ?? '', $domain);
            }));
            $response['data']['data']['page'] = null;
            $response['data'] = $response['data']['data'];
        }

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function storeFtp(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $username = request('username');
        $password = request('password');
        if (strlen($password) < 6) {
            return response()->json(['ok' => false, 'message' => 'Senha deve ter no minimo 6 caracteres.'], 422);
        }
        $path = '/www/wwwroot/'.$account->domain;
        $client = new AaPanelClient($account->server);
        $response = $client->createFtp($username, $password, $path);
        if ($response['ok']) {
            $account->update(['ftp_username' => $username, 'ftp_password' => $password]);
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function destroyFtp(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $id = request('id');
        $client = new AaPanelClient($account->server);

        $list = $client->listFtps();
        if ($list['ok'] && is_array($list['data']['data']['data'] ?? null)) {
            $target = collect($list['data']['data']['data'])->first(fn($r) => ($r['id'] ?? '') === $id);
            $domain = $account->domain;
            if (!$target || (($target['name'] ?? '') !== $account->ftp_username && !str_contains($target['path'] ?? '', $domain))) {
                return response()->json(['ok' => false, 'message' => 'FTP nao pertence a esta conta.'], 403);
            }
        }

        $response = $client->deleteFtp($id);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function changeFtpPassword(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $id = request('id');
        $password = request('password');

        if (strlen($password) < 6) {
            return response()->json(['ok' => false, 'message' => 'Senha deve ter no minimo 6 caracteres.'], 422);
        }

        $client = new AaPanelClient($account->server);

        $list = $client->listFtps();
        if ($list['ok'] && is_array($list['data']['data']['data'] ?? null)) {
            $target = collect($list['data']['data']['data'])->first(fn($r) => ($r['id'] ?? '') === $id);
            $domain = $account->domain;
            if (!$target || (($target['name'] ?? '') !== $account->ftp_username && !str_contains($target['path'] ?? '', $domain))) {
                return response()->json(['ok' => false, 'message' => 'FTP nao pertence a esta conta.'], 403);
            }
        }

        $response = $client->changeFtpPassword($id, $password);
        if ($response['ok']) {
            $account->update(['ftp_password' => $password]);
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== SSL DETALHADO =====================
    public function sslStatus(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->getSsl($account->domain);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function forceHttps(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->setForceHttps($account->domain, (bool) request('force', true));
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function renewSsl(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->renewSsl($account->domain);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function deleteSsl(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->deleteSsl((int) $account->site_id, $account->domain);
        if ($response['ok']) {
            $account->update(['ssl_enabled' => false]);
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== PHP =====================
    public function phpVersion(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->getSitePhpVersion($account->domain);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function setPhpVersion(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->setSitePhpVersion($account->domain, request('version'));
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function phpConfig(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->getPhpConfig($account->domain);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function restartPhp(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);

        $vRes = $client->getSitePhpVersion($account->domain);
        $version = $vRes['data']['version'] ?? $vRes['data'] ?? '82';
        if (is_array($version)) {
            $version = $version['version'] ?? '82';
        }
        $version = preg_replace('/[^0-9]/', '', (string) $version);
        if (empty($version)) {
            $version = '82';
        }

        $response = $client->restartPhpFpm($version);
        if ($response['ok']) {
            $this->logOperation($account, 'restart_php', 'success', '/system?action=ServiceAdmin', "Serviço PHP-FPM {$version} reiniciado.");
            $response['message'] = "Serviço PHP-FPM {$version} reiniciado com sucesso!";
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function phpLogs(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $type = request('type', 'fpm');

        $vRes = $client->getSitePhpVersion($account->domain);
        $version = $vRes['data']['version'] ?? $vRes['data'] ?? '82';
        if (is_array($version)) {
            $version = $version['version'] ?? '82';
        }
        $version = preg_replace('/[^0-9]/', '', (string) $version);
        if (empty($version)) {
            $version = '82';
        }

        $response = ($type === 'slow')
            ? $client->getPhpSlowLogs($version)
            : $client->getPhpFpmLogs($version);

        if ($response['ok']) {
            $rawContent = is_array($response['data']) ? ($response['data']['data'] ?? '') : $response['data'];
            $lines = explode("\n", (string) $rawContent);
            $lastLines = array_slice($lines, -120);
            return response()->json([
                'ok' => true,
                'version' => $version,
                'type' => $type,
                'data' => implode("\n", $lastLines),
            ]);
        }

        return response()->json(['ok' => false, 'message' => 'Nenhum log encontrado para esta versão do PHP.'], 404);
    }

    public function phpExtensions(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);

        $vRes = $client->getSitePhpVersion($account->domain);
        $version = $vRes['data']['version'] ?? $vRes['data'] ?? '82';
        if (is_array($version)) {
            $version = $version['version'] ?? '82';
        }
        $version = preg_replace('/[^0-9]/', '', (string) $version);
        if (empty($version)) {
            $version = '82';
        }

        $iniRes = $client->getPhpIni($version);
        $content = $iniRes['ok'] ? (is_array($iniRes['data']) ? ($iniRes['data']['data'] ?? '') : $iniRes['data']) : '';

        // Lista das principais extensões auditadas
        $targetExts = [
            'curl'       => ['name' => 'cURL', 'desc' => 'Comunicação HTTP/API', 'default' => true],
            'pdo_mysql'  => ['name' => 'PDO MySQL', 'desc' => 'Conexão segura com banco', 'default' => true],
            'mysqli'     => ['name' => 'MySQLi', 'desc' => 'Driver nativo MySQL', 'default' => true],
            'gd'         => ['name' => 'GD', 'desc' => 'Processamento de imagens', 'default' => true],
            'openssl'    => ['name' => 'OpenSSL', 'desc' => 'Criptografia e SSL', 'default' => true],
            'zip'        => ['name' => 'Zip', 'desc' => 'Compressão de arquivos', 'default' => true],
            'mbstring'   => ['name' => 'Mbstring', 'desc' => 'Strings multibyte e UTF-8', 'default' => true],
            'fileinfo'   => ['name' => 'FileInfo', 'desc' => 'Detecção de MIME type', 'default' => true],
            'opcache'    => ['name' => 'Zend OPcache', 'desc' => 'Aceleração de bytecode', 'default' => true],
            'redis'      => ['name' => 'Redis', 'desc' => 'Cache de alta performance', 'default' => false],
            'bcmath'     => ['name' => 'BCMath', 'desc' => 'Precisão arbitrária financeira', 'default' => true],
            'intl'       => ['name' => 'Intl', 'desc' => 'Internacionalização', 'default' => true],
        ];

        $results = [];
        foreach ($targetExts as $key => $info) {
            $isActive = $info['default'];
            if (!empty($content)) {
                if (preg_match('/^\s*(?:extension|zend_extension)\s*=\s*["\']?.*' . preg_quote($key, '/') . '/im', $content)) {
                    $isActive = true;
                }
            }
            $results[] = [
                'key'    => $key,
                'name'   => $info['name'],
                'desc'   => $info['desc'],
                'active' => $isActive,
            ];
        }

        // Funções desabilitadas
        $disabledFunctions = [];
        if (preg_match('/^\s*disable_functions\s*=\s*(.+)$/m', $content, $matches)) {
            $disabledFunctions = array_filter(array_map('trim', explode(',', $matches[1])));
        }

        return response()->json([
            'ok' => true,
            'version' => $version,
            'extensions' => $results,
            'disabled_functions' => array_values($disabledFunctions),
        ]);
    }

    // ===================== BACKUPS =====================
    public function backups(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listBackups();

        if ($response['ok']) {
            $raw = $response['data'] ?? [];
            $rows = $raw['data'] ?? (isset($raw['data']['data']) ? $raw['data']['data'] : $raw);
            if (!is_array($rows)) {
                $rows = [];
            }

            $siteId = (string) ($account->site_id ?: $account->aapanel_site_id);
            $domain = (string) $account->domain;
            $dbName = (string) $account->database_name;
            $domainPrefix = explode('.', $domain)[0] ?? '';

            $filtered = [];
            foreach ($rows as $row) {
                $rowPid = (string) ($row['pid'] ?? '');
                $rowName = $row['name'] ?? '';

                $isSiteBackup = ($rowPid !== '' && $rowPid === $siteId) || (!empty($domain) && stripos($rowName, $domain) !== false);
                $isDbBackup = (!empty($dbName) && stripos($rowName, $dbName) !== false) || (!empty($domainPrefix) && strlen($domainPrefix) >= 3 && stripos($rowName, 'sql_' . $domainPrefix) !== false);

                if ($isSiteBackup || $isDbBackup) {
                    $sizeNum = (int) ($row['size'] ?? 0);
                    $formattedSize = $this->formatSizeUnits($sizeNum);

                    $filtered[] = [
                        'id'             => $row['id'] ?? null,
                        'name'           => $rowName,
                        'type'           => $isDbBackup ? 'database' : 'site',
                        'type_label'     => $isDbBackup ? 'Banco de Dados' : 'Website',
                        'size'           => $formattedSize,
                        'raw_size'       => $sizeNum,
                        'addtime'        => $row['addtime'] ?? '-',
                        'filename'       => $row['filename'] ?? '',
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'data' => array_values($filtered),
            ]);
        }

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    protected function formatSizeUnits(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function storeBackup(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $type = request('type', 'site');

        if ($type === 'database') {
            // Busca o id do banco de dados no aaPanel
            $dbList = $client->listDatabases();
            $dbRows = $dbList['data']['data'] ?? $dbList['data'] ?? [];
            $dbId = null;

            if (is_array($dbRows)) {
                $dbName = (string) $account->database_name;
                $domain = (string) $account->domain;
                foreach ($dbRows as $r) {
                    if (($dbName && ($r['name'] ?? '') === $dbName) || ($domain && stripos($r['ps'] ?? '', $domain) !== false)) {
                        $dbId = $r['id'] ?? null;
                        break;
                    }
                }
            }

            if (!$dbId) {
                return response()->json(['ok' => false, 'message' => 'Nenhum banco de dados vinculado para gerar backup.'], 422);
            }

            $response = $client->createDatabaseBackup((int) $dbId);
            if ($response['ok']) {
                $this->logOperation($account, 'backup_db', 'success', '/database?action=ToBackup', 'Backup do banco de dados gerado.');
                $response['message'] = 'Backup do banco de dados gerado com sucesso!';
            }
            return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
        }

        // Backup do site
        $siteId = (string) ($account->site_id ?: $account->aapanel_site_id);
        $response = $client->createBackup($siteId);
        if ($response['ok']) {
            $this->logOperation($account, 'backup_site', 'success', '/site?action=ToBackup', 'Backup do website gerado.');
            $response['message'] = 'Backup do site gerado com sucesso!';
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function destroyBackup(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $id = (int) request('id');
        $type = request('type');

        if ($type === 'database') {
            $response = $client->deleteDatabaseBackup($id);
        } else {
            $response = $client->deleteBackup($id);
        }

        if ($response['ok']) {
            $this->logOperation($account, 'delete_backup', 'success', '/site?action=DelBackup', "Backup ID {$id} excluído.");
            $response['message'] = 'Arquivo de backup excluído com sucesso!';
        }
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function downloadBackup(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $fileName = request('file_name');

        if (empty($fileName)) {
            return response()->json(['ok' => false, 'message' => 'Nome do arquivo não fornecido.'], 422);
        }

        $response = $client->getFileBody($fileName);
        if ($response['ok'] && isset($response['data'])) {
            $content = is_array($response['data']) ? ($response['data']['data'] ?? '') : $response['data'];
            return response($content, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . basename($fileName) . '"',
            ]);
        }
        return response()->json($response, 422);
    }

    // ===================== LOGS =====================
    public function accessLog(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->getAccessLog($account->domain, request('lines', 100));
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function errorLog(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->getErrorLog($account->domain);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== FILE MANAGER =====================
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    protected function parseFileItem($key, $val, bool $isDirDefault = false): ?array
    {
        if ($key === '...' || (is_string($val) && str_starts_with($val, 'Over '))) {
            return null;
        }

        $name = '';
        $size = $isDirDefault ? 'DIR' : '0 B';
        $mtime = '-';
        $type = $isDirDefault ? 'dir' : 'file';
        $isdir = $isDirDefault;

        if (is_array($val)) {
            $name = $val['name'] ?? $val['filename'] ?? (is_string($key) && !is_numeric($key) ? $key : '');
            $sizeRaw = $val['size'] ?? null;
            if (is_numeric($sizeRaw)) {
                $size = (int)$sizeRaw > 0 ? $this->formatBytes((int)$sizeRaw) : ($isDirDefault ? 'DIR' : '0 B');
            } elseif (is_string($sizeRaw) && strlen($sizeRaw) > 0) {
                $size = $sizeRaw;
            }

            $mtimeRaw = $val['mtime'] ?? $val['time'] ?? $val['addtime'] ?? null;
            if (is_numeric($mtimeRaw) && (int)$mtimeRaw > 100000000) {
                $mtime = date('d/m/Y H:i:s', (int)$mtimeRaw);
            } elseif (is_string($mtimeRaw) && strlen($mtimeRaw) > 0) {
                $mtime = $mtimeRaw;
            }

            if (isset($val['type'])) {
                $type = $val['type'];
                $isdir = ($type === 'dir' || !empty($val['isdir']));
            }
        } elseif (is_string($val)) {
            $parts = explode(';', $val);
            // Estrutura nativa aaPanel: [0] => nome/caminho, [1] => tamanho, [2] => mtime, [3] => chmod, [4] => owner
            if (is_string($key) && !is_numeric($key) && strlen($key) > 0 && $key !== '...') {
                $name = $key;
                $sizeRaw = $parts[0] ?? null;
                $mtimeRaw = $parts[1] ?? null;
            } else {
                $name = $parts[0] ?? '';
                $sizeRaw = $parts[1] ?? null;
                $mtimeRaw = $parts[2] ?? null;
            }

            if (is_numeric($sizeRaw)) {
                $size = (int)$sizeRaw > 0 ? $this->formatBytes((int)$sizeRaw) : ($isDirDefault ? 'DIR' : '0 B');
            } elseif ($isDirDefault) {
                $size = 'DIR';
            }

            if (is_numeric($mtimeRaw) && (int)$mtimeRaw > 100000000) {
                $mtime = date('d/m/Y H:i:s', (int)$mtimeRaw);
            } elseif (isset($parts[1]) && is_numeric($parts[1]) && (int)$parts[1] > 1000000000) {
                $mtime = date('d/m/Y H:i:s', (int)$parts[1]);
            }
        }

        $name = trim(rtrim((string)$name, '/'));
        // Limpar reticencias ou barras duplas
        while (str_starts_with($name, '.')) {
            if ($name === '.' || $name === '..') return null;
            if (str_starts_with($name, '...')) {
                $name = ltrim($name, '.');
            } else {
                break; // Manter arquivos ocultos normais como .htaccess e .well-known
            }
        }

        if (empty($name)) return null;

        // Se contiver barra no meio (subpasta vinda de retorno recursivo), pegar apenas o primeiro nivel da pasta
        if (str_contains($name, '/')) {
            $segments = explode('/', $name);
            $name = $segments[0];
            $type = 'dir';
            $isdir = true;
            $size = 'DIR';
        }

        return [
            'name' => $name,
            'size' => $size,
            'mtime' => $mtime,
            'type' => $type,
            'isdir' => $isdir,
        ];
    }

    public function files(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $path = request('path', '/www/wwwroot/'.$account->domain);

        if ($error = $this->validateFilePath($account, $path)) return $error;

        $client = new AaPanelClient($account->server);
        $response = $client->listFiles($path);

        if ($response['ok']) {
            $raw = $response['data'] ?? [];
            $dirData = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : $raw;

            $itemsMap = [];
            $dirs = $dirData['DIR'] ?? $dirData['dir'] ?? [];
            $filesList = $dirData['FILES'] ?? $dirData['files'] ?? [];

            if (!empty($dirs) || !empty($filesList)) {
                if (is_array($dirs)) {
                    foreach ($dirs as $k => $v) {
                        $parsed = $this->parseFileItem($k, $v, true);
                        if ($parsed && !empty($parsed['name']) && !isset($itemsMap[$parsed['name']])) {
                            $itemsMap[$parsed['name']] = $parsed;
                        }
                    }
                }
                if (is_array($filesList)) {
                    foreach ($filesList as $k => $v) {
                        $parsed = $this->parseFileItem($k, $v, false);
                        if ($parsed && !empty($parsed['name']) && !isset($itemsMap[$parsed['name']])) {
                            $itemsMap[$parsed['name']] = $parsed;
                        }
                    }
                }
            } else {
                $rawList = $dirData['DATA'] ?? $dirData['data'] ?? (is_array($dirData) && isset($dirData[0]) ? $dirData : []);
                if (is_array($rawList)) {
                    foreach ($rawList as $k => $v) {
                        $parsed = $this->parseFileItem($k, $v, false);
                        if ($parsed && !empty($parsed['name']) && !isset($itemsMap[$parsed['name']])) {
                            $itemsMap[$parsed['name']] = $parsed;
                        }
                    }
                }
            }

            // Ordenar: primeiro diretorios (A-Z), depois arquivos (A-Z)
            $sortedItems = array_values($itemsMap);
            usort($sortedItems, function ($a, $b) {
                if ($a['isdir'] !== $b['isdir']) {
                    return $a['isdir'] ? -1 : 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });

            $response['data'] = [
                'DATA' => $sortedItems,
                'PATH' => $dirData['PATH'] ?? $path,
                'PAGE' => $dirData['PAGE'] ?? '1/1',
            ];
        }

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function destroyFile(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $path = request('path');

        if ($error = $this->validateFilePath($account, $path)) return $error;

        $client = new AaPanelClient($account->server);
        $response = $client->deleteFile($path);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function createFile(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $path = request('path');

        if ($error = $this->validateFilePath($account, $path)) return $error;

        $client = new AaPanelClient($account->server);
        $response = $client->createFile($path);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function createDirectory(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $path = request('path');

        if ($error = $this->validateFilePath($account, $path)) return $error;

        $client = new AaPanelClient($account->server);
        $response = $client->createDirectory($path);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function getFile(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $path = request('path');

        if ($error = $this->validateFilePath($account, $path)) return $error;

        $client = new AaPanelClient($account->server);
        $response = $client->getFileBody($path);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function saveFile(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $path = request('path');
        $content = request('content', '');

        if ($error = $this->validateFilePath($account, $path)) return $error;

        $client = new AaPanelClient($account->server);
        $response = $client->saveFileBody($path, $content);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== CRONS =====================
    public function crons(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listCrons();

        if ($response['ok'] && is_array($response['data']['data']['data'] ?? null)) {
            $domain = $account->domain;
            $response['data']['data']['data'] = array_values(array_filter($response['data']['data']['data'], function ($row) use ($domain) {
                return str_contains($row['name'] ?? '', $domain) || str_contains($row['sName'] ?? '', $domain);
            }));
            $response['data']['data']['page'] = null;
            $response['data'] = $response['data']['data'];
        }

        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function destroyCron(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $id = request('id');
        $client = new AaPanelClient($account->server);

        // Validar que o cron pertence a esta conta
        $list = $client->listCrons();
        if ($list['ok'] && is_array($list['data']['data']['data'] ?? null)) {
            $target = collect($list['data']['data']['data'])->first(fn($r) => ($r['id'] ?? '') === $id);
            $domain = $account->domain;
            if (!$target || (!str_contains($target['name'] ?? '', $domain) && !str_contains($target['sName'] ?? '', $domain))) {
                return response()->json(['ok' => false, 'message' => 'Cron nao pertence a esta conta.'], 403);
            }
        }

        $response = $client->deleteCron($id);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function updateCron(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $id = request('id');
        $client = new AaPanelClient($account->server);

        // Validar que o cron pertence a esta conta
        $list = $client->listCrons();
        if ($list['ok'] && is_array($list['data']['data']['data'] ?? null)) {
            $target = collect($list['data']['data']['data'])->first(fn($r) => ($r['id'] ?? '') === $id);
            $domain = $account->domain;
            if (!$target || (!str_contains($target['name'] ?? '', $domain) && !str_contains($target['sName'] ?? '', $domain))) {
                return response()->json(['ok' => false, 'message' => 'Cron nao pertence a esta conta.'], 403);
            }
        }

        $response = $client->updateCron(
            $id,
            request('name'),
            request('type'),
            request('where1', ''),
            request('where2', ''),
            request('where3', ''),
            request('where4', ''),
            request('where5', ''),
            request('sBody'),
            request('sType', 'shell'),
            request('sName', ''),
            request('backupTo', 'localhost')
        );
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== REDIRECIONAMENTOS =====================
    public function redirects(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->listRedirects($account->domain);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    public function destroyRedirect(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->deleteRedirect($account->domain, request('redirect_name'));
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }

    // ===================== CACHE =====================
    public function clearCache(AaPanelAccount $account)
    {
        $this->guardAccount($account);
        $client = new AaPanelClient($account->server);
        $response = $client->clearSiteCache($account->domain, (string) $account->site_id);
        return response()->json($response, ($response['ok'] ?? false) ? 200 : 422);
    }
}
