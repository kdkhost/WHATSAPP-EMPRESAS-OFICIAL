<?php

namespace App\Modules\AaPanel\Services;

use App\Modules\AaPanel\Models\AaPanelAccount;
use App\Modules\AaPanel\Models\AaPanelOperationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AaPanelProvisioningService
{
    public function __construct(
        protected AaPanelPasswordService $passwordService
    ) {}

    public function provision(AaPanelAccount $account): array
    {
        return DB::transaction(function () use ($account) {
            $account->loadMissing(['server', 'plan']);

            $client = new AaPanelClient($account->server);

            $username = $account->username ?: $this->passwordService->usernameFromDomain($account->domain);
            $password = $account->password ?: $this->passwordService->generate();
            $ftpUser = $account->ftp_username ?: $username;
            $ftpPass = $account->ftp_password ?: $this->passwordService->generate();
            $dbName = $account->database_name ?: $this->passwordService->databaseName($username);
            $dbUser = $account->database_username ?: $dbName;
            $dbPass = $account->database_password ?: $this->passwordService->generate();
            $phpVersion = $account->plan?->php_version ?: '83';
            $path = '/www/wwwroot/'.$account->domain;

            $site = $client->createSite($account->domain, $phpVersion, $path);
            $this->log($account, 'create_site', $site);

            if (! $site['ok']) {
                $account->update(['status' => 'failed']);

                return $site;
            }

            $siteId = $site['data']['id'] ?? $site['data']['site_id'] ?? null;

            $ftp = $client->createFtp($ftpUser, $ftpPass, $path);
            $this->log($account, 'create_ftp', $ftp);

            $db = $client->createDatabase($dbName, $dbUser, $dbPass);
            $this->log($account, 'create_database', $db);

            $ssl = null;

            if ($account->plan?->ssl_enabled) {
                $ssl = $client->applyLetsEncrypt($account->domain);
                $this->log($account, 'apply_ssl', $ssl);
            }

            $account->username = $username;
            $account->password = $password;
            $account->ftp_username = $ftpUser;
            $account->ftp_password = $ftpPass;
            $account->database_name = $dbName;
            $account->database_username = $dbUser;
            $account->database_password = $dbPass;
            $account->site_id = $siteId;
            $account->status = 'active';
            $account->ssl_enabled = (bool) ($ssl['ok'] ?? false);
            $account->provisioned_at = now();
            $account->meta = array_merge($account->meta ?? [], [
                'path' => $path,
                'php_version' => $phpVersion,
            ]);
            $account->save();

            return [
                'ok' => true,
                'message' => 'Conta aaPanel provisionada com sucesso.',
                'data' => [
                    'account_id' => $account->id,
                    'domain' => $account->domain,
                ],
            ];
        });
    }

    public function suspend(AaPanelAccount $account): array
    {
        $client = new AaPanelClient($account->server);
        $response = $client->stopSite((string) $account->site_id, $account->domain);
        $this->log($account, 'suspend_site', $response);

        if ($response['ok']) {
            $account->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);
        }

        return $response;
    }

    public function unsuspend(AaPanelAccount $account): array
    {
        $client = new AaPanelClient($account->server);
        $response = $client->startSite((string) $account->site_id, $account->domain);
        $this->log($account, 'unsuspend_site', $response);

        if ($response['ok']) {
            $account->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);
        }

        return $response;
    }

    public function terminate(AaPanelAccount $account): array
    {
        $client = new AaPanelClient($account->server);
        $response = $client->deleteSite((string) $account->site_id, $account->domain);
        $this->log($account, 'terminate_site', $response);

        if ($response['ok']) {
            $account->update([
                'status' => 'terminated',
                'terminated_at' => now(),
            ]);
        }

        return $response;
    }

    protected function log(AaPanelAccount $account, string $operation, array $response): void
    {
        AaPanelOperationLog::create([
            'tenant_id' => $account->tenant_id ?? $account->server?->tenant_id,
            'server_id' => $account->server_id,
            'account_id' => $account->id,
            'admin_id' => Auth::guard('admin')->id(),
            'operation' => $operation,
            'status' => ($response['ok'] ?? false) ? 'success' : 'failed',
            'http_status' => $response['status'] ?? null,
            'endpoint' => $response['endpoint'] ?? null,
            'message' => $response['message'] ?? null,
            'context' => [
                'domain' => $account->domain,
            ],
        ]);
    }
}
