<?php

namespace App\Services;

use App\Models\WhatsAppInstance;
use App\Models\WhatsAppSyncLog;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Repositories\WhatsAppInstanceRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EvolutionGoSyncService
{
    public function __construct(
        protected InstanceManager $instances,
        protected WhatsAppInstanceRepository $repository,
        protected StructuredLogSanitizer $sanitizer,
    ) {}

    public function syncAll(?int $tenantId = null): array
    {
        $startedAt = now();
        $remoteRows = $this->remoteRows();
        $synced = 0;
        $errors = 0;

        $query = TenantInstance::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('tenant');

        foreach ($query->lazyById(100) as $tenantInstance) {
            $row = $this->findRemoteRow($remoteRows, $tenantInstance->instance_name);
            $log = $this->createLog($tenantInstance, 'sync_profile', $startedAt, ['remote_row' => $row]);

            try {
                $result = $this->instances->syncProfile($tenantInstance);
                $tenantInstance->refresh();
                $mirror = $this->repository->mirrorTenantInstance($tenantInstance, $row ?: ($result['profile'] ?? []));

                $log->forceFill([
                    'whatsapp_instance_id' => $mirror->id,
                    'status' => empty($result['errors']) ? 'ok' : 'warning',
                    'remote_payload' => $this->sanitizer->sanitize($result),
                    'finished_at' => now(),
                ])->save();

                $synced++;
            } catch (\Throwable $e) {
                $errors++;
                $log->forceFill([
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ])->save();

                $this->repository->mirrorTenantInstance($tenantInstance);
                WhatsAppInstance::where('tenant_instance_id', $tenantInstance->id)->update([
                    'sync_status' => 'sync_error',
                    'sync_error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->markRemoteOnly($remoteRows);

        return [
            'synced' => $synced,
            'errors' => $errors,
            'remote_total' => count($remoteRows),
        ];
    }

    public function mirror(TenantInstance $instance, array $payload = []): WhatsAppInstance
    {
        return $this->repository->mirrorTenantInstance($instance, $payload);
    }

    protected function remoteRows(): array
    {
        try {
            $response = $this->client()->get('/instance/all');
            if (! $response->successful()) {
                return [];
            }

            $payload = $response->json() ?: [];
            $data = data_get($payload, 'data', $payload);
            $rows = data_get($data, 'instances') ?: data_get($data, 'response') ?: $data;

            if (is_array($rows) && array_is_list($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }

            return is_array($rows) && $rows !== [] ? [$rows] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function markRemoteOnly(array $remoteRows): void
    {
        foreach ($remoteRows as $row) {
            $name = $this->remoteName($row);
            if (! $name || TenantInstance::where('instance_name', $name)->exists()) {
                continue;
            }

            $existing = WhatsAppInstance::query()
                ->whereNull('tenant_id')
                ->where('instance_name', $name)
                ->first();

            $normalized = $this->normalizeRemoteState($row);
            $payload = $this->remoteOnlyPayload($existing, $row, $name, $normalized);

            WhatsAppInstance::updateOrCreate(
                ['tenant_id' => null, 'instance_name' => $name],
                [
                    'phone' => $payload['phone'],
                    'jid' => $payload['jid'],
                    'profile_name' => $payload['profile_name'],
                    'profile_picture' => $payload['profile_picture'],
                    'client_name' => $payload['client_name'],
                    'connected' => $payload['connected'],
                    'logged_in' => $payload['connected'],
                    'server_payload' => $payload['server_payload'],
                    'remote_hash' => hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE)),
                    'sync_status' => 'remote_only',
                    'server_status' => $payload['server_status'],
                    'last_sync_at' => now(),
                ]
            );
        }
    }

    protected function remoteOnlyPayload(?WhatsAppInstance $existing, array $row, string $instanceName, array $normalized): array
    {
        $existingPayload = is_array($existing?->server_payload) ? $existing->server_payload : [];
        $remoteQrCode = $this->extractRemoteQrCode($row);
        $display = array_merge(
            is_array(data_get($existingPayload, 'display')) ? data_get($existingPayload, 'display') : [],
            [
                'state' => $normalized['state'],
                'status' => $normalized['status'],
                'status_label' => $normalized['status_label'],
                'connected' => $normalized['connected'],
                'raw_state' => (string) (data_get($row, 'state') ?: data_get($row, 'status') ?: $normalized['state']),
                'number' => $this->firstFilled(
                    (string) data_get($row, 'number'),
                    (string) data_get($row, 'phone'),
                    $existing?->phone
                ),
                'name' => $this->firstFilled(
                    (string) data_get($row, 'profileName'),
                    (string) data_get($row, 'profile_name'),
                    $existing?->profile_name
                ),
                'client_name' => $this->firstFilled(
                    (string) data_get($row, 'clientName'),
                    (string) data_get($row, 'client_name'),
                    $existing?->client_name
                ),
                'jid' => $this->firstFilled(
                    (string) data_get($row, 'jid'),
                    (string) data_get($row, 'owner'),
                    (string) data_get($row, 'ownerJid'),
                    $existing?->jid
                ),
                'avatar' => $this->firstFilled(
                    (string) data_get($row, 'profilePictureUrl'),
                    (string) data_get($row, 'profile_picture'),
                    $existing?->profile_picture
                ),
                'created_at' => $this->firstFilled(
                    (string) data_get($row, 'createdAt'),
                    (string) data_get($row, 'created_at'),
                    (string) data_get($existingPayload, 'display.created_at')
                ),
                'instance' => $instanceName,
                'last_check' => now()->toIso8601String(),
                'qrcode' => $normalized['state'] === 'connecting' ? $this->firstFilled(
                    $remoteQrCode,
                    (string) data_get($existingPayload, 'display.qrcode')
                ) : null,
                '_source' => 'evolution_sync_all',
                '_api_error' => null,
            ]
        );

        return [
            'phone' => $display['number'] ?: $existing?->phone,
            'jid' => $display['jid'] ?: $existing?->jid,
            'profile_name' => $display['name'] ?: $existing?->profile_name,
            'profile_picture' => $display['avatar'] ?: $existing?->profile_picture,
            'client_name' => $display['client_name'] ?: $existing?->client_name,
            'connected' => $normalized['connected'],
            'server_status' => $normalized['state'],
            'server_payload' => [
                'display' => $display,
                'remote' => $this->sanitizer->sanitize($row),
                'synced_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected function normalizeRemoteState(array $row): array
    {
        foreach ([
            data_get($row, 'connected'),
            data_get($row, 'instance.connected'),
            data_get($row, 'data.connected'),
        ] as $connectedValue) {
            if (filter_var($connectedValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true || $connectedValue === 1 || $connectedValue === '1') {
                return ['state' => 'open', 'status' => 'connected', 'status_label' => 'Conectado', 'connected' => true];
            }
        }

        if ($this->extractRemoteQrCode($row) !== '') {
            return ['state' => 'connecting', 'status' => 'connecting', 'status_label' => 'Aguardando QR Code', 'connected' => false];
        }

        $state = mb_strtolower(trim((string) (data_get($row, 'state') ?: data_get($row, 'status') ?: '')));

        return match (true) {
            in_array($state, ['open', 'connected', 'connect', 'online', 'authenticated', 'logged', 'logged_in', 'ready', 'active'], true)
                => ['state' => 'open', 'status' => 'connected', 'status_label' => 'Conectado', 'connected' => true],
            in_array($state, ['connecting', 'syncing', 'init', 'initializing', 'loading', 'qr', 'qrcode', 'pairing', 'waiting_qr', 'scan_qr'], true)
                => ['state' => 'connecting', 'status' => 'connecting', 'status_label' => 'Aguardando QR Code', 'connected' => false],
            in_array($state, ['error', 'failed', 'timeout', 'invalid', 'exception'], true)
                => ['state' => 'error', 'status' => 'error', 'status_label' => 'Erro de comunicação', 'connected' => false],
            default
                => ['state' => 'close', 'status' => 'disconnected', 'status_label' => 'Desconectado', 'connected' => false],
        };
    }

    protected function extractRemoteQrCode(array $row): string
    {
        $qr = $this->firstFilled(
            (string) data_get($row, 'qrcode.base64'),
            (string) data_get($row, 'qrcode.qrcode'),
            (string) data_get($row, 'qrcode.Qrcode'),
            (string) data_get($row, 'qrcode.Code'),
            (string) data_get($row, 'Qrcode'),
            (string) data_get($row, 'Code'),
            is_string(data_get($row, 'qrcode')) ? (string) data_get($row, 'qrcode') : null
        );

        if ($qr === '') {
            return '';
        }

        if (str_starts_with($qr, 'data:image')) {
            return $qr;
        }

        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $qr) && mb_strlen($qr) > 250) {
            return 'data:image/png;base64,'.preg_replace('/\s+/', '', $qr);
        }

        return $qr;
    }

    protected function firstFilled(?string ...$values): string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    protected function findRemoteRow(array $rows, string $instanceName): array
    {
        foreach ($rows as $row) {
            if ($this->remoteName($row) === $instanceName) {
                return $row;
            }
        }

        return [];
    }

    protected function remoteName(array $row): ?string
    {
        $name = data_get($row, 'name')
            ?: data_get($row, 'instanceName')
            ?: data_get($row, 'instance.name')
            ?: data_get($row, 'data.instanceName');

        return is_string($name) && $name !== '' ? $name : null;
    }

    protected function createLog(TenantInstance $instance, string $action, mixed $startedAt, array $remote = []): WhatsAppSyncLog
    {
        return WhatsAppSyncLog::create([
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'instance_name' => $instance->instance_name,
            'action' => $action,
            'status' => 'running',
            'local_payload' => $this->sanitizer->sanitize($instance->toArray()),
            'remote_payload' => $this->sanitizer->sanitize($remote),
            'started_at' => $startedAt,
        ]);
    }

    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) $this->setting('whatsapp.base_url', config('whatsapp.base_url')), '/');
        $apiKey = (string) $this->setting('whatsapp.global_api_key', config('whatsapp.api_key'));

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('whatsapp.timeout', 15))
            ->acceptJson()
            ->withHeaders(['apikey' => $apiKey]);
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        try {
            return app(SettingsManager::class)->get($key, $default) ?: $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
