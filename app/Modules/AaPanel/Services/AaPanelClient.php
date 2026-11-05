<?php

namespace App\Modules\AaPanel\Services;

use App\Modules\AaPanel\Models\AaPanelServer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AaPanelClient
{
    protected array $cache = [];
    protected int $cacheTtl = 30; // segundos

    public function __construct(
        protected AaPanelServer $server
    ) {}

    protected function endpoint(string $path): string
    {
        return $this->server->apiUrl().'/'.ltrim($path, '/');
    }

    protected function authPayload(array $payload = []): array
    {
        $time = time();
        return array_merge([
            'request_time' => $time,
            'request_token' => md5($time.md5($this->server->api_key)),
        ], $payload);
    }

    public function post(string $path, array $payload = []): array
    {
        $url = $this->endpoint($path);
        $safePath = '/'.ltrim($path, '/');

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

        try {
            $response = Http::asForm()
                ->withHeaders(['User-Agent' => $ua])
                ->timeout(30)
                ->withoutVerifying()
                ->post($url, $this->authPayload($payload));

            $body = $response->body();
            Log::info('[aaPanel] resposta raw', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 500),
                'json' => $response->json(),
            ]);

            // Se 404 ou parametro invalido pela entrada de seguranca, tentar URLs alternativas
            if ($response->status() === 404 || str_contains($response->body(), 'Specific parameters are invalid')) {
                $urlsParaTentar = [];

                $host = rtrim($this->server->host, '/');
                if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
                    $host = preg_replace('#^https?://#', '', $host);
                }

                if ($this->server->security_entry) {
                    $urlsParaTentar[] = $this->server->baseUrl().'/'.ltrim($path, '/');
                }

                if ($this->server->port != 443) {
                    $urlsParaTentar[] = "https://{$host}:443/".ltrim($path, '/');
                }
                // Tentar variacoes da entrada de seguranca
                $entry = $this->server->security_entry;
                if ($entry) {
                    $entryVariations = array_unique(array_filter([
                        trim($entry, '/'),
                        '/'.trim($entry, '/'),
                        trim($entry, '/').'/',
                        ltrim($entry, '/'),
                        rtrim($entry, '/'),
                    ]));
                    foreach ($entryVariations as $entryVar) {
                        if ($entryVar === $entry) continue;
                        $urlsParaTentar[] = "https://{$host}:{$this->server->port}/{$entryVar}/".ltrim($path, '/');
                    }
                }

                foreach ($urlsParaTentar as $tentativaUrl) {
                    Log::info('[aaPanel] tentando URL alternativa', ['url' => $tentativaUrl]);
                    $response = Http::asForm()
                        ->withHeaders(['User-Agent' => $ua])
                        ->timeout(30)
                        ->withoutVerifying()
                        ->post($tentativaUrl, $this->authPayload($payload));
                    $body = $response->body();
                    Log::info('[aaPanel] resposta URL alternativa', [
                        'url' => $tentativaUrl,
                        'status' => $response->status(),
                        'body' => mb_substr($body, 0, 200),
                    ]);
                    if ($response->status() !== 404) {
                        break;
                    }
                }
            }

            return $this->formatResponse($response, $safePath);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $isConnectError = str_contains($errorMsg, 'Connection refused')
                || str_contains($errorMsg, 'Could not resolve')
                || str_contains($errorMsg, 'timed out')
                || str_contains($errorMsg, 'cURL error 7')
                || str_contains($errorMsg, 'cURL error 6');

            // Fallback: se Connection refused na porta customizada, tentar 443
            if ($isConnectError && $this->server->port != 443) {
                return $this->postFallback($path, $payload, $e);
            }

            $friendlyMessage = $isConnectError
                ? 'Nao foi possivel conectar ao servidor aaPanel. O IP deste servidor pode estar bloqueado no firewall do aaPanel. Verifique: (1) se a porta esta correta, (2) se o IP ' . request()->server('SERVER_ADDR', 'desconhecido') . ' esta liberado no firewall do aaPanel (Security -> Firewall), (3) tente usar porta 443.'
                : 'Erro de comunicacao com aaPanel: '.mb_substr($errorMsg, 0, 120);

            Log::warning('[aaPanel] erro de comunicacao', [
                'server_id' => $this->server->id,
                'url' => $url,
                'endpoint' => $safePath,
                'message' => mb_substr($errorMsg, 0, 180),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'message' => $friendlyMessage,
                'data' => null,
            ];
        }
    }

    public function get(string $path, array $payload = []): array
    {
        $url = $this->endpoint($path);
        $safePath = '/'.ltrim($path, '/');

        // Adicionar auth como query params (formato da documentacao oficial)
        $auth = $this->authPayload($payload);
        $query = http_build_query($auth);
        $urlWithQuery = $url . (str_contains($url, '?') ? '&' : '?') . $query;

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

        try {
            $response = Http::withHeaders(['User-Agent' => $ua])
                ->timeout(30)
                ->withoutVerifying()
                ->get($urlWithQuery);

            Log::info('[aaPanel] GET resposta', [
                'url' => $urlWithQuery,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return $this->formatResponse($response, $safePath);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $isConnectError = str_contains($errorMsg, 'Connection refused')
                || str_contains($errorMsg, 'Could not resolve')
                || str_contains($errorMsg, 'timed out')
                || str_contains($errorMsg, 'cURL error 7')
                || str_contains($errorMsg, 'cURL error 6');

            $friendlyMessage = $isConnectError
                ? 'Falha ao conectar no servidor aaPanel em ' . $this->server->host . ':' . $this->server->port . '. Verifique se a porta está correta e liberada no firewall do servidor (Security -> Firewall).'
                : 'Erro de comunicação com o aaPanel: ' . mb_substr($errorMsg, 0, 120);

            return [
                'ok' => false,
                'status' => null,
                'message' => $friendlyMessage,
                'data' => null,
            ];
        }
    }

    protected function postFallback(string $path, array $payload, \Throwable $originalError): array
    {
        $safePath = '/'.ltrim($path, '/');
        $host = rtrim($this->server->host, '/');
        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            $host = preg_replace('#^https?://#', '', $host);
        }

        $fallbackUrl = "https://{$host}:443/".ltrim($path, '/');

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

        try {
            $response = Http::asForm()
                ->withHeaders(['User-Agent' => $ua])
                ->timeout(30)
                ->withoutVerifying()
                ->post($fallbackUrl, $this->authPayload($payload));

            return $this->formatResponse($response, $safePath);
        } catch (\Throwable $e) {
            Log::warning('[aaPanel] fallback 443 tambem falhou', [
                'server_id' => $this->server->id,
                'fallback_url' => $fallbackUrl,
                'original_error' => mb_substr($originalError->getMessage(), 0, 120),
                'fallback_error' => mb_substr($e->getMessage(), 0, 120),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'message' => 'Nao foi possivel conectar ao aaPanel na porta configurada nem na porta 443. Verifique host, porta e firewall.',
                'data' => null,
            ];
        }
    }

    protected function formatResponse(Response $response, string $endpoint): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            $json = [];
        }

        $ok = $response->successful();

        if (array_key_exists('status', $json)) {
            $ok = $ok && (bool) $json['status'];
        }

        $rawMsg = $json['msg'] ?? $json['message'] ?? ($ok ? 'Operação realizada com sucesso.' : 'Falha na operação aaPanel.');
        $friendlyMsg = $rawMsg;

        if (str_contains($rawMsg, 'IP validation failed') || str_contains($rawMsg, 'access IP')) {
            $ip = 'desconhecido';
            if (preg_match('/is\[(.*?)\]/', $rawMsg, $matches)) {
                $ip = $matches[1];
            }
            $friendlyMsg = "IP não autorizado no aaPanel: O IP [{$ip}] tentou se conectar mas não está na lista de permissões do aaPanel. Acesse seu aaPanel -> Settings -> API e adicione o IP {$ip} nos IPs permitidos (IP Limit/Whitelist).";
        } elseif (str_contains($rawMsg, 'Specific parameters are invalid') || str_contains($rawMsg, 'does not exist')) {
            $friendlyMsg = "O diretório ou recurso solicitado não foi encontrado no servidor aaPanel. Verifique se o diretório do site foi criado no servidor.";
        } elseif (str_contains($rawMsg, 'token') || str_contains($rawMsg, 'Secret key')) {
            $friendlyMsg = "Chave de API (API Key) incorreta ou inválida. Verifique a chave de API em aaPanel -> Settings -> API.";
        }

        return [
            'ok' => $ok,
            'status' => $response->status(),
            'message' => $friendlyMsg,
            'raw_message' => $rawMsg,
            'data' => $json,
            'endpoint' => $endpoint,
        ];
    }

    public function test(): array
    {
        $endpoints = [
            '/system?action=GetSystemTotal',
            '/data?action=getData',
            '/site?action=GetSiteTypes',
            '/config?action=GetConfig',
            '/ajax?action=GetTaskCount',
            '/api/system?action=GetSystemTotal',
            '/api/data?action=getData',
            '/api/site?action=GetSiteTypes',
            '/system?action=GetNetWork',
            '/data?action=getData&table=sites',
            '/panel/system?action=GetSystemTotal',
            '/panel/data?action=getData',
            '/config?action=get_token',
            '/v2/data?action=getData',
            '/v2/data?action=getData&table=sites',
        ];

        $lastResponse = null;
        foreach ($endpoints as $ep) {
            // Tentar POST primeiro (form data)
            $response = $this->post($ep);
            $lastResponse = $response;
            if ($response['ok']) {
                return $response;
            }
            // Se 404, tentar GET com query params (documentacao oficial usa query params)
            if ($response['status'] === 404) {
                $getResponse = $this->get($ep);
                if ($getResponse['ok']) {
                    return $getResponse;
                }
                $lastResponse = $getResponse['status'] !== 404 ? $getResponse : $lastResponse;
            }
            // Se nao for 404, para aqui (erro de autenticacao, por exemplo)
            if ($lastResponse['status'] !== 404) {
                break;
            }
        }

        // Fingerprinting: tenta identificar qual painel esta rodando
        $fingerprint = $this->fingerprintPanel();

        $message = $lastResponse['message'] ?? 'Falha na operacao aaPanel.';
        if ($lastResponse['status'] == 404) {
            $suggestions = [];
            if ($fingerprint['detected'] && $fingerprint['detected'] !== 'aaPanel') {
                $suggestions[] = 'O servidor parece rodar "' . $fingerprint['detected'] . '", nao aaPanel.';
            } else {
                $suggestions[] = 'Verifique se a entrada de seguranca esta correta (atual: ' . ($this->server->security_entry ?: 'vazio') . ').';
                $suggestions[] = 'Verifique se a API esta habilitada no aaPanel (Painel -> API).';
                $suggestions[] = 'Verifique se a API key esta correta.';
            }
            $suggestions[] = 'Porta testada: ' . $this->server->port . '.';
            $suggestions[] = 'URL base: ' . $this->server->apiUrl() . '.';

            $message = 'O servidor respondeu, mas nao reconhece os endpoints da API aaPanel. ' . implode(' ', $suggestions);
        }

        return [
            'ok' => false,
            'status' => $lastResponse['status'] ?? 0,
            'message' => $message,
            'data' => $lastResponse['data'] ?? null,
            'endpoint' => $lastResponse['endpoint'] ?? null,
            'fingerprint' => $fingerprint,
        ];
    }

    protected function fingerprintPanel(): array
    {
        $urls = [
            'with_entry' => $this->server->baseUrl(),
            'without_entry' => $this->server->apiUrl(),
            'with_entry_login' => $this->server->baseUrl() . '/login',
            'with_entry_bind' => $this->server->baseUrl() . '/bind',
        ];

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

        foreach ($urls as $key => $url) {
            try {
                $r = Http::timeout(15)
                    ->withHeaders(['User-Agent' => $ua, 'Accept' => 'text/html,application/xhtml+xml'])
                    ->withOptions([
                        'verify' => false,
                        'allow_redirects' => ['track_redirects' => true, 'max' => 5],
                    ])
                    ->retry(1, 500)
                    ->get($url);

                $status = $r->status();
                $body = $r->body();
                $lowerBody = mb_strtolower($body);

                Log::info('[aaPanel] fingerprint response', [
                    'url' => $url,
                    'status' => $status,
                    'body_preview' => mb_substr($body, 0, 300),
                    'headers' => $r->headers(),
                ]);

                $title = '';
                if (preg_match('/<title>(.*?)<\/title>/si', $body, $m)) {
                    $title = trim($m[1]);
                }

                $detected = 'desconhecido';
                if (str_contains($lowerBody, 'aapanel')) {
                    $detected = 'aaPanel';
                } elseif (str_contains($lowerBody, 'bt.cn')) {
                    $detected = 'aaPanel (bt.cn)';
                } elseif (str_contains($lowerBody, 'cyberpanel') || str_contains($lowerBody, 'cyber panel')) {
                    $detected = 'CyberPanel';
                } elseif (str_contains($lowerBody, 'hestia') || str_contains($lowerBody, 'hestiacp')) {
                    $detected = 'HestiaCP';
                } elseif (str_contains($lowerBody, 'cpanel')) {
                    $detected = 'cPanel';
                } elseif (str_contains($lowerBody, 'webmin')) {
                    $detected = 'Webmin';
                } elseif (str_contains($lowerBody, 'plesk')) {
                    $detected = 'Plesk';
                } elseif (str_contains($lowerBody, 'directadmin')) {
                    $detected = 'DirectAdmin';
                } elseif (str_contains($lowerBody, 'nginx')) {
                    $detected = 'nginx (possivel proxy)';
                }

                return [
                    'detected' => $detected,
                    'title' => $title,
                    'url_tested' => $url,
                    'http_status' => $status,
                    'body_preview' => mb_substr($body, 0, 400),
                ];
            } catch (\Throwable $e) {
                Log::warning('[aaPanel] fingerprint falhou para url', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }

        return [
            'detected' => null,
            'title' => null,
            'url_tested' => null,
            'http_status' => null,
            'body_preview' => 'Nao foi possivel conectar para fingerprinting em nenhuma URL.',
        ];
    }

    public function listSites(): array
    {
        return $this->post('/data?action=getData', [
            'table' => 'sites',
            'limit' => 100,
            'p' => 1,
        ]);
    }

    public function createSite(string $domain, string $phpVersion = '83', string $path = ''): array
    {
        $webPath = $path ?: '/www/wwwroot/'.$domain;

        return $this->post('/site?action=AddSite', [
            'webname' => json_encode([
                'domain' => $domain,
                'domainlist' => [],
                'count' => 0,
            ], JSON_UNESCAPED_SLASHES),
            'path' => $webPath,
            'type_id' => 0,
            'type' => 'PHP',
            'version' => $phpVersion,
            'port' => 80,
            'ps' => $domain,
            'ftp' => false,
            'sql' => false,
            'codeing' => 'utf8mb4',
        ]);
    }

    public function deleteSite(string $siteId, string $webName): array
    {
        $payloads = [
            ['id' => $siteId, 'webname' => $webName, 'ftp' => 1, 'database' => 1, 'path' => 1],
            ['id' => (int) $siteId, 'webname' => $webName, 'ftp' => 1, 'database' => 1, 'path' => 1],
        ];

        foreach (['/site?action=DeleteSite', '/v2/site?action=DeleteSite'] as $ep) {
            foreach ($payloads as $payload) {
                $r = $this->post($ep, $payload);
                Log::info('[aaPanel] deleteSite tentativa', ['endpoint' => $ep, 'payload' => $payload, 'response' => $r]);
                if ($r['ok'] || ($r['status'] ?? 0) !== 404) {
                    return $r;
                }
            }
        }

        return $r ?? ['ok' => false, 'status' => 404, 'message' => 'Nenhum endpoint de delete funcionou.', 'data' => null];
    }

    public function stopSite(string $siteId, string $webName): array
    {
        $payloads = [
            ['id' => $siteId, 'name' => $webName],
            ['id' => (int) $siteId, 'name' => $webName],
        ];

        foreach (['/site?action=SiteStop', '/v2/site?action=SiteStop'] as $ep) {
            foreach ($payloads as $payload) {
                $r = $this->post($ep, $payload);
                Log::info('[aaPanel] stopSite tentativa', ['endpoint' => $ep, 'payload' => $payload, 'response' => $r]);
                if ($r['ok'] || ($r['status'] ?? 0) !== 404) {
                    return $r;
                }
            }
        }

        return $r ?? ['ok' => false, 'status' => 404, 'message' => 'Nenhum endpoint de stop funcionou.', 'data' => null];
    }

    public function startSite(string $siteId, string $webName): array
    {
        $payloads = [
            ['id' => $siteId, 'name' => $webName],
            ['id' => (int) $siteId, 'name' => $webName],
        ];

        foreach (['/site?action=SiteStart', '/v2/site?action=SiteStart'] as $ep) {
            foreach ($payloads as $payload) {
                $r = $this->post($ep, $payload);
                Log::info('[aaPanel] startSite tentativa', ['endpoint' => $ep, 'payload' => $payload, 'response' => $r]);
                if ($r['ok'] || ($r['status'] ?? 0) !== 404) {
                    return $r;
                }
            }
        }

        return $r ?? ['ok' => false, 'status' => 404, 'message' => 'Nenhum endpoint de start funcionou.', 'data' => null];
    }

    public function createFtp(string $username, string $password, string $path): array
    {
        return $this->post('/ftp?action=AddUser', [
            'ftp_username' => $username,
            'ftp_password' => $password,
            'path' => $path,
            'ps' => $username,
        ]);
    }

    public function createDatabase(string $name, string $username, string $password): array
    {
        return $this->post('/database?action=AddDatabase', [
            'name' => $name,
            'db_user' => $username,
            'password' => $password,
            'dtype' => 'MySQL',
            'dataAccess' => '127.0.0.1',
            'address' => '127.0.0.1',
            'ps' => $name,
            'codeing' => 'utf8mb4',
        ]);
    }

    public function applyLetsEncrypt(string $domain, string $siteName = ''): array
    {
        $payload = [
            'domains' => json_encode([$domain]),
            'auth_type' => 'http',
            'auth_to' => $domain,
        ];
        if ($siteName) {
            $payload['siteName'] = $siteName;
        }
        return $this->post('/site?action=CreateLet', $payload);
    }

    public function getPhpVersions(): array
    {
        return $this->post('/site?action=GetPHPVersion');
    }

    public function getSiteInfo(string $siteId): array
    {
        // Tentar endpoint direto primeiro (mais rapido)
        $direct = $this->post('/site?action=GetSite', ['id' => $siteId]);
        if ($direct['ok'] && !empty($direct['data'])) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Site encontrado.',
                'data' => $direct['data'],
                'endpoint' => '/site?action=GetSite',
            ];
        }

        // Fallback: buscar na lista
        $list = $this->listSites();
        if (!$list['ok']) {
            return $list;
        }

        $sites = $list['data']['data'] ?? [];
        foreach ($sites as $site) {
            if ((string) ($site['id'] ?? '') === (string) $siteId) {
                return [
                    'ok' => true,
                    'status' => 200,
                    'message' => 'Site encontrado.',
                    'data' => $site,
                    'endpoint' => '/data?action=getData&table=sites',
                ];
            }
        }

        return [
            'ok' => false,
            'status' => 404,
            'message' => 'Site nao encontrado na lista.',
            'data' => null,
            'endpoint' => '/data?action=getData&table=sites',
        ];
    }

    public function getDatabases(): array
    {
        return $this->post('/data?action=getData', [
            'table' => 'databases',
            'limit' => 100,
            'p' => 1,
        ]);
    }

    public function getFtps(): array
    {
        return $this->post('/data?action=getData', [
            'table' => 'ftps',
            'limit' => 100,
            'p' => 1,
        ]);
    }

    public function getSiteTypes(): array
    {
        return $this->post('/site?action=GetSiteTypes');
    }

    // ===================== SUBDOMINIOS =====================
    public function listSubdomains(string $siteId): array
    {
        return $this->post('/site?action=GetDirBinding', ['id' => $siteId]);
    }

    public function createSubdomain(string $siteId, string $domain, string $dirName): array
    {
        return $this->post('/site?action=AddDirBinding', [
            'id' => $siteId,
            'domain' => $domain,
            'dirName' => $dirName,
        ]);
    }

    public function deleteSubdomain(string $siteId, string $domain, string $dirName): array
    {
        return $this->post('/site?action=DelDirBinding', [
            'id' => $siteId,
            'domain' => $domain,
            'dirName' => $dirName,
        ]);
    }

    // ===================== BANCOS DE DADOS =====================
    public function listDatabases(): array
    {
        return $this->cached('/data?action=getData&table=databases', fn() => $this->post('/data?action=getData', [
            'table' => 'databases',
            'limit' => 100,
            'p' => 1,
        ]));
    }

    public function deleteDatabase(string $name, string $dbUser): array
    {
        return $this->post('/database?action=DeleteDatabase', [
            'name' => $name,
            'db_user' => $dbUser,
        ]);
    }

    public function getDatabaseAccess(string $name): array
    {
        return $this->post('/database?action=GetDatabaseAccess', ['name' => $name]);
    }

    public function getPhpMyAdminUrl(string $name): array
    {
        return $this->post('/database?action=GetPhpMyAdmin', ['name' => $name]);
    }

    public function setDatabaseAccess(string $name, string $access): array
    {
        return $this->post('/database?action=SetDatabaseAccess', [
            'name' => $name,
            'dataAccess' => $access,
        ]);
    }

    // ===================== FTP =====================
    public function listFtps(): array
    {
        return $this->cached('/data?action=getData&table=ftps', fn() => $this->post('/data?action=getData', [
            'table' => 'ftps',
            'limit' => 100,
            'p' => 1,
        ]));
    }

    public function deleteFtp(string $id): array
    {
        return $this->post('/ftp?action=DeleteUser', ['id' => $id]);
    }

    public function changeFtpPassword(string $id, string $password): array
    {
        return $this->post('/ftp?action=SetUserPassword', [
            'id' => $id,
            'ftp_password' => $password,
        ]);
    }

    // ===================== SSL / HTTPS =====================
    public function getSsl(string $siteName): array
    {
        return $this->post('/site?action=GetSSL', ['siteName' => $siteName]);
    }

    public function setForceHttps(string $siteName, bool $force): array
    {
        return $this->post('/site?action=HttpToHttps', [
            'siteName' => $siteName,
            'toHttps' => $force ? 1 : 0,
        ]);
    }

    public function renewSsl(string $siteName): array
    {
        return $this->post('/site?action=RenewSSL', ['siteName' => $siteName]);
    }

    public function deleteSsl(int $siteId, string $siteName): array
    {
        return $this->post('/site?action=CloseSSLConf', [
            'updateOf' => $siteId,
            'siteName' => $siteName,
        ]);
    }

    // ===================== CONFIGURACOES PHP =====================
    public function getSitePhpVersion(string $siteName): array
    {
        return $this->post('/site?action=GetSitePHPVersion', ['siteName' => $siteName]);
    }

    public function setSitePhpVersion(string $siteName, string $version): array
    {
        return $this->post('/site?action=SetPHPVersion', [
            'siteName' => $siteName,
            'version' => $version,
        ]);
    }

    public function getPhpConfig(string $siteName): array
    {
        return $this->post('/site?action=GetPHPConfig', ['siteName' => $siteName]);
    }

    public function setPhpConfig(string $siteName, string $key, string $value): array
    {
        return $this->post('/site?action=SetPHPConfig', [
            'siteName' => $siteName,
            'key' => $key,
            'value' => $value,
        ]);
    }

    // ===================== PHP-FPM SERVICES & LOGS =====================
    public function restartPhpFpm(string $version): array
    {
        $cleanVer = preg_replace('/[^0-9]/', '', $version);
        return $this->post('/system?action=ServiceAdmin', [
            'name' => "php-fpm-{$cleanVer}",
            'type' => 'restart',
        ]);
    }

    public function reloadPhpFpm(string $version): array
    {
        $cleanVer = preg_replace('/[^0-9]/', '', $version);
        return $this->post('/system?action=ServiceAdmin', [
            'name' => "php-fpm-{$cleanVer}",
            'type' => 'reload',
        ]);
    }

    public function getPhpFpmLogs(string $version): array
    {
        $cleanVer = preg_replace('/[^0-9]/', '', $version);
        return $this->post('/files?action=GetFileBody', [
            'path' => "/www/server/php/{$cleanVer}/var/log/php-fpm.log",
        ]);
    }

    public function getPhpSlowLogs(string $version): array
    {
        $cleanVer = preg_replace('/[^0-9]/', '', $version);
        return $this->post('/files?action=GetFileBody', [
            'path' => "/www/server/php/{$cleanVer}/var/log/slow.log",
        ]);
    }

    public function getPhpIni(string $version): array
    {
        $cleanVer = preg_replace('/[^0-9]/', '', $version);
        return $this->post('/files?action=GetFileBody', [
            'path' => "/www/server/php/{$cleanVer}/etc/php.ini",
        ]);
    }

    // ===================== BACKUPS =====================
    public function listBackups(string $siteId = '', int $limit = 100, int $page = 1): array
    {
        $payload = [
            'table' => 'backup',
            'limit' => $limit,
            'p' => $page,
        ];
        return $this->post('/data?action=getData', $payload);
    }

    public function createBackup(string $siteId): array
    {
        return $this->post('/site?action=ToBackup', ['id' => $siteId]);
    }

    public function createDatabaseBackup(int $databaseId): array
    {
        return $this->post('/database?action=ToBackup', ['id' => $databaseId]);
    }

    public function deleteBackup(int $backupId): array
    {
        return $this->post('/site?action=DelBackup', ['id' => $backupId]);
    }

    public function deleteDatabaseBackup(int $backupId): array
    {
        return $this->post('/database?action=DelBackup', ['id' => $backupId]);
    }

    // ===================== LOGS =====================
    public function getAccessLog(string $siteName, int $lines = 100): array
    {
        return $this->post('/site?action=GetSiteLogs', [
            'siteName' => $siteName,
            'rows' => $lines,
        ]);
    }

    public function getErrorLog(string $siteName): array
    {
        return $this->post('/site?action=GetErrLog', [
            'siteName' => $siteName,
        ]);
    }

    public function listFiles(string $path, int $page = 1, int $showRow = 200): array
    {
        $cleanPath = rtrim($path, '/');
        $payload = [
            'path' => $cleanPath,
            'p' => $page,
            'showRow' => $showRow,
        ];

        $response = $this->post('/files?action=GetDir', $payload);

        if (! $response['ok']) {
            $altRes = $this->post('/files?action=get_dir', $payload);
            if ($altRes['ok']) {
                return $altRes;
            }
        }

        return $response;
    }

    public function deleteFile(string $path): array
    {
        return $this->post('/files?action=DeleteFile', ['path' => $path]);
    }

    public function deleteDirectory(string $path): array
    {
        return $this->post('/files?action=DeleteDir', ['path' => $path]);
    }

    public function createFile(string $path): array
    {
        return $this->post('/files?action=CreateFile', ['path' => $path]);
    }

    public function createDirectory(string $path): array
    {
        return $this->post('/files?action=CreateDir', ['path' => $path]);
    }

    public function getFileBody(string $path): array
    {
        return $this->post('/files?action=GetFileBody', ['path' => $path]);
    }

    public function saveFileBody(string $path, string $data, string $encoding = 'utf-8'): array
    {
        return $this->post('/files?action=SaveFileBody', [
            'path' => $path,
            'data' => $data,
            'encoding' => $encoding,
        ]);
    }

    // ===================== CRONS =====================
    public function listCrons(): array
    {
        return $this->cached('/crontab?action=GetCrontab', fn() => $this->post('/crontab?action=GetCrontab'));
    }

    public function createCron(string $name, string $type, string $where1, string $where2, string $where3, string $where4, string $where5, string $sBody, string $sType = 'shell', string $sName = '', string $backupTo = 'localhost'): array
    {
        return $this->post('/crontab?action=AddCrontab', [
            'name' => $name,
            'type' => $type,
            'where1' => $where1,
            'hour' => $where2,
            'minute' => $where3,
            'week' => $where4,
            'sType' => $sType,
            'sBody' => $sBody,
            'sName' => $sName,
            'backupTo' => $backupTo,
        ]);
    }

    public function updateCron(string $id, string $name, string $type, string $where1, string $where2, string $where3, string $where4, string $where5, string $sBody, string $sType = 'shell', string $sName = '', string $backupTo = 'localhost'): array
    {
        return $this->post('/crontab?action=ModCrontab', [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'where1' => $where1,
            'hour' => $where2,
            'minute' => $where3,
            'week' => $where4,
            'sType' => $sType,
            'sBody' => $sBody,
            'sName' => $sName,
            'backupTo' => $backupTo,
        ]);
    }

    public function deleteCron(string $id): array
    {
        return $this->post('/crontab?action=DelCrontab', ['id' => $id]);
    }

    // ===================== CACHE HELPER =====================
    protected function cached(string $key, callable $fn): array
    {
        $cacheKey = $this->server->id . ':' . $key;
        $now = time();
        if (isset($this->cache[$cacheKey]) && ($now - $this->cache[$cacheKey]['ts']) < $this->cacheTtl) {
            return $this->cache[$cacheKey]['data'];
        }
        $data = $fn();
        $this->cache[$cacheKey] = ['ts' => $now, 'data' => $data];
        return $data;
    }

    public function flushLocalCache(): void
    {
        $this->cache = [];
    }

    // ===================== REDIRECIONAMENTOS =====================
    public function listRedirects(string $siteName): array
    {
        return $this->cached('redirects:'.$siteName, fn() => $this->post('/site?action=GetRedirectList', ['siteName' => $siteName]));
    }

    public function createRedirect(string $siteName, string $type, string $redirectDomain, string $toUrl, int $redirectCode = 301, int $holdPath = 1): array
    {
        return $this->post('/site?action=CreateRedirect', [
            'siteName' => $siteName,
            'type' => $type,
            'domainorpath' => $redirectDomain,
            'toUrl' => $toUrl,
            'redirecttype' => $redirectCode,
            'holdpath' => $holdPath,
        ]);
    }

    public function deleteRedirect(string $siteName, string $redirectName): array
    {
        return $this->post('/site?action=DeleteRedirect', [
            'siteName' => $siteName,
            'name' => $redirectName,
        ]);
    }

    // ===================== HOTLINK PROTECTION =====================
    public function getHotlink(int $siteId, string $siteName): array
    {
        return $this->post('/site?action=GetSecurity', [
            'id' => $siteId,
            'name' => $siteName,
        ]);
    }

    public function setHotlink(int $siteId, string $siteName, string $domains, string $extensions, bool $status = true): array
    {
        return $this->post('/site?action=SetSecurity', [
            'id' => $siteId,
            'name' => $siteName,
            'fix' => $extensions,
            'domains' => $domains,
            'status' => $status ? '1' : '0',
        ]);
    }

    // ===================== CACHE DO SITE =====================
    public function clearSiteCache(string $siteName, string $siteId = ''): array
    {
        $payload = ['siteName' => $siteName];
        if ($siteId) {
            $payload['id'] = $siteId;
        }
        return $this->post('/site?action=ClearCache', $payload);
    }

    // ===================== DOMINIOS DO SITE =====================
    public function getSiteDomains(string $siteId): array
    {
        return $this->post('/site?action=GetSiteDomains', ['id' => $siteId]);
    }

    // ===================== FIREWALL =====================
    public function getFirewallRules(): array
    {
        return $this->post('/firewall?action=GetList');
    }

    // ===================== SYSTEM INFO =====================
    public function getSystemTotal(): array
    {
        return $this->cached('/system?action=GetSystemTotal', fn() => $this->post('/system?action=GetSystemTotal'));
    }

    public function getDiskInfo(): array
    {
        return $this->cached('/system?action=GetDiskInfo', fn() => $this->post('/system?action=GetDiskInfo'));
    }

    // ===================== NET WORK =====================
    public function getNetwork(): array
    {
        return $this->cached('/system?action=GetNetWork', fn() => $this->post('/system?action=GetNetWork'));
    }
}
