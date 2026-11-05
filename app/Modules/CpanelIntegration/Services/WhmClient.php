<?php

namespace App\Modules\CpanelIntegration\Services;

use App\Modules\CpanelIntegration\Models\CpanelServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente WHM/cPanel via JSON-API.
 * Documentacao: https://api.docs.cpanel.net/whm/introduction/
 *
 * Autenticacao: WHM API Token (criado em WHM > Manage API Tokens)
 *   Header: Authorization: whm root:TOKEN
 */
class WhmClient
{
    public function __construct(protected CpanelServer $server) {}

    /** Chamada generica a API WHM. */
    public function call(string $function, array $params = []): array
    {
        $url = $this->server->base_url . '/json-api/' . $function;

        try {
            $resp = Http::withHeaders([
                'Authorization' => "whm {$this->server->username}:{$this->server->api_token}",
            ])
                ->withOptions(['verify' => $this->server->verify_ssl])
                ->timeout(30)
                ->acceptJson()
                ->get($url, array_merge(['api.version' => 1], $params));

            $data = $resp->json() ?: [];
            if (! $resp->successful()) {
                throw new \RuntimeException("WHM API HTTP {$resp->status()}");
            }
            $status = $data['metadata']['result'] ?? $data['status'] ?? 1;
            if ((int) $status === 0) {
                throw new \RuntimeException($data['metadata']['reason'] ?? 'WHM API error');
            }
            return $data;
        } catch (\Throwable $e) {
            Log::error("[WHM] {$function} falhou: " . $e->getMessage());
            $this->log($function, null, 'error', null, $e->getMessage());
            throw $e;
        }
    }

    public function callUapi(string $module, string $function, array $params = []): array
    {
        $url = $this->server->base_url . '/execute/' . $module . '/' . $function;

        try {
            $resp = Http::withHeaders([
                'Authorization' => "cpanel {$this->server->username}:{$this->server->api_token}",
            ])
                ->withOptions(['verify' => $this->server->verify_ssl])
                ->timeout(30)
                ->acceptJson()
                ->get($url, $params);

            $data = $resp->json() ?: [];
            if (! $resp->successful()) {
                throw new \RuntimeException("cPanel API HTTP {$resp->status()}");
            }
            $status = $data['status'] ?? 0;
            if ((int) $status === 0) {
                $reason = $data['errors'][0] ?? 'cPanel API error';
                throw new \RuntimeException($reason);
            }
            return $data;
        } catch (\Throwable $e) {
            Log::error("[cPanel] {$module}/{$function} falhou: " . $e->getMessage());
            $this->log("{$module}/{$function}", null, 'error', null, $e->getMessage());
            throw $e;
        }
    }

    /** Testa conexao via version. */
    public function testConnection(): array
    {
        if ($this->server->type === 'cpanel') {
            try {
                $this->callUapi('CustInfo', 'displaycontactinfo');
                $this->server->update([
                    'last_check_at'     => now(),
                    'last_check_status' => 'ok',
                    'meta'              => ['cpanel_user' => $this->server->username],
                ]);
                return ['ok' => true, 'version' => 'cPanel'];
            } catch (\Throwable $e) {
                $this->server->update(['last_check_at' => now(), 'last_check_status' => 'error']);
                $msg = $e->getMessage();
                if (str_contains($msg, '403')) {
                    $msg = 'Acesso negado (403): Token inválido ou sem permissão no cPanel.';
                } elseif (str_contains($msg, '401')) {
                    $msg = 'Não autorizado (401): Usuário ou token cPanel incorretos.';
                } elseif (str_contains($msg, 'Connection refused') || str_contains($msg, 'timed out') || str_contains($msg, 'Failed to connect')) {
                    $msg = 'Não foi possível conectar. Verifique hostname, porta (ex: 2083) e firewall.';
                } elseif (str_contains(strtolower($msg), 'ssl')) {
                    $msg = 'Falha de SSL: O IP ou domínio não possui SSL válido. Desmarque a opção "Verificar certificado SSL".';
                }
                return ['ok' => false, 'error' => $msg];
            }
        }

        try {
            // Tenta version primeiro
            $data = $this->call('version');
            $version = $data['data']['version'] ?? $data['version'] ?? 'unknown';
            $this->server->update([
                'last_check_at'     => now(),
                'last_check_status' => 'ok',
                'meta'              => ['whm_version' => $version],
            ]);
            return ['ok' => true, 'version' => $version];
        } catch (\Throwable $e1) {
            // Fallback: tenta listaccts (permissão mais comum)
            try {
                $this->call('listaccts', ['searchtype' => 'user', 'search' => '', 'searchmethod' => 'exact', 'limit' => 1]);
                $this->server->update(['last_check_at' => now(), 'last_check_status' => 'ok']);
                return ['ok' => true, 'version' => 'unknown'];
            } catch (\Throwable $e2) {
                $this->server->update(['last_check_at' => now(), 'last_check_status' => 'error']);
                $msg = $e1->getMessage();
                if (str_contains($msg, '403')) {
                    $msg = 'Acesso negado (403): Token inválido ou sem permissão. Verifique o token e as permissões no WHM.';
                } elseif (str_contains($msg, '401')) {
                    $msg = 'Não autorizado (401): Usuário ou token incorretos.';
                } elseif (str_contains($msg, 'Connection refused') || str_contains($msg, 'timed out') || str_contains($msg, 'Failed to connect')) {
                    $msg = 'Não foi possível conectar ao servidor. Verifique o hostname, porta e firewall.';
                } elseif (str_contains(strtolower($msg), 'ssl')) {
                    $msg = 'Falha de SSL: O IP ou domínio não possui SSL válido. Desmarque a opção "Verificar certificado SSL".';
                }
                return ['ok' => false, 'error' => $msg];
            }
        }
    }

    /** Lista todas as contas cPanel do servidor. */
    public function listAccounts(): array
    {
        $data = $this->call('listaccts');
        return $data['data']['acct'] ?? [];
    }

    /** Resumo de uma conta especifica. */
    public function accountSummary(string $username): ?array
    {
        try {
            $data = $this->call('accountsummary', ['user' => $username]);
            return $data['data']['acct'][0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Suspende uma conta cPanel. */
    public function suspendAccount(string $username, string $reason = 'Inadimplencia'): bool
    {
        try {
            $this->call('suspendacct', ['user' => $username, 'reason' => $reason]);
            $this->log('suspendacct', $username, 'success', ['reason' => $reason]);
            return true;
        } catch (\Throwable $e) {
            $this->log('suspendacct', $username, 'error', null, $e->getMessage());
            return false;
        }
    }

    /** Reativa conta cPanel. */
    public function unsuspendAccount(string $username): bool
    {
        try {
            $this->call('unsuspendacct', ['user' => $username]);
            $this->log('unsuspendacct', $username, 'success');
            return true;
        } catch (\Throwable $e) {
            $this->log('unsuspendacct', $username, 'error', null, $e->getMessage());
            return false;
        }
    }

    /** Cria nova conta cPanel. */
    public function createAccount(array $data): array
    {
        $required = ['username', 'domain', 'password', 'plan'];
        foreach ($required as $k) {
            if (empty($data[$k])) {
                throw new \InvalidArgumentException("Campo obrigatorio: {$k}");
            }
        }

        try {
            $resp = $this->call('createacct', [
                'username'    => $data['username'],
                'domain'      => $data['domain'],
                'password'    => $data['password'],
                'plan'        => $data['plan'],
                'contactemail'=> $data['email'] ?? '',
                'cpmod'       => $data['cpmod'] ?? 'jupiter',
            ]);
            $this->log('createacct', $data['username'], 'success', $data);
            return $resp;
        } catch (\Throwable $e) {
            $this->log('createacct', $data['username'] ?? null, 'error', $data, $e->getMessage());
            throw $e;
        }
    }

    /** Remove conta cPanel. */
    public function terminateAccount(string $username): bool
    {
        try {
            $this->call('removeacct', ['user' => $username]);
            $this->log('removeacct', $username, 'success');
            return true;
        } catch (\Throwable $e) {
            $this->log('removeacct', $username, 'error', null, $e->getMessage());
            return false;
        }
    }

    /** Lista pacotes disponiveis. */
    public function listPackages(): array
    {
        try {
            $data = $this->call('listpkgs');
            return $data['data']['pkg'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function log(string $action, ?string $target, string $status, $payload = null, ?string $response = null): void
    {
        try {
            DB::table('cpanel_actions_log')->insert([
                'server_id'     => $this->server->id,
                'admin_user_id' => auth('admin')->id(),
                'action'        => $action,
                'target'        => $target,
                'status'        => $status,
                'payload'       => $payload ? json_encode($payload) : null,
                'response'      => $response,
                'created_at'    => now(),
            ]);
        } catch (\Throwable) {}
    }
}
