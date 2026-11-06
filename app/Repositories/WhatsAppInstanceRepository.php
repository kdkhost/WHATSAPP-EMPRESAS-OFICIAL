<?php

namespace App\Repositories;

use App\Models\WhatsAppInstance;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WhatsAppInstanceRepository
{
    public function findByInstanceName(string $instanceName): ?WhatsAppInstance
    {
        return WhatsAppInstance::where('instance_name', $instanceName)->first();
    }

    public function mirrorTenantInstance(TenantInstance $tenantInstance, array $remotePayload = []): WhatsAppInstance
    {
        return DB::transaction(function () use ($tenantInstance, $remotePayload) {
            $local = $this->localPayload($tenantInstance);
            $remoteHash = $remotePayload === [] ? null : $this->hash($remotePayload);

            return WhatsAppInstance::updateOrCreate(
                [
                    'tenant_id' => $tenantInstance->tenant_id,
                    'instance_name' => $tenantInstance->instance_name,
                ],
                [
                    'tenant_instance_id' => $tenantInstance->id,
                    'instance_token' => $tenantInstance->api_key ?: null,
                    'global_api_key_ref' => $tenantInstance->api_key ? 'instance' : 'global',
                    'phone' => $tenantInstance->profile_number ?: $tenantInstance->phone,
                    'jid' => $tenantInstance->profile_jid,
                    'profile_name' => $tenantInstance->whatsapp_profile_name,
                    'profile_picture' => $tenantInstance->profile_avatar_url,
                    'client_name' => $tenantInstance->display_name,
                    'connected' => in_array((string) $tenantInstance->status, ['connected', 'active'], true),
                    'logged_in' => (string) $tenantInstance->last_connection_state === 'open',
                    'server_status' => $tenantInstance->last_connection_state ?: $tenantInstance->status,
                    'last_sync_at' => now(),
                    'server_payload' => $remotePayload ?: ($tenantInstance->profile_raw ?? $tenantInstance->meta),
                    'local_hash' => $this->hash($local),
                    'remote_hash' => $remoteHash,
                    'sync_status' => $this->syncStatus($tenantInstance, $remotePayload),
                    'sync_error' => $tenantInstance->last_error,
                ]
            );
        });
    }

    public function markWebhookReceived(TenantInstance $tenantInstance, array $payload = []): ?WhatsAppInstance
    {
        $instance = $this->mirrorTenantInstance($tenantInstance, $payload);
        $instance->forceFill([
            'last_webhook_at' => now(),
            'server_payload' => $payload ?: $instance->server_payload,
        ])->save();

        return $instance;
    }

    public function markDeletedRemote(TenantInstance $tenantInstance, ?string $error = null): void
    {
        WhatsAppInstance::where('tenant_instance_id', $tenantInstance->id)
            ->orWhere(function ($query) use ($tenantInstance) {
                $query->where('tenant_id', $tenantInstance->tenant_id)
                    ->where('instance_name', $tenantInstance->instance_name);
            })
            ->update([
                'sync_status' => 'deleted_remote',
                'server_status' => 'deleted',
                'sync_error' => $error,
                'updated_at' => now(),
            ]);
    }

    protected function syncStatus(TenantInstance $tenantInstance, array $remotePayload): string
    {
        if ($tenantInstance->last_error) {
            return 'sync_error';
        }

        if ((string) $tenantInstance->status === 'connected') {
            return 'connected';
        }

        if ((string) $tenantInstance->status === 'disconnected') {
            return 'disconnected';
        }

        if (! $tenantInstance->meta_phone_number_id) {
            return 'pending_credentials';
        }

        return $remotePayload === [] ? 'local_only' : 'synced';
    }

    protected function localPayload(TenantInstance $tenantInstance): array
    {
        return Arr::only($tenantInstance->toArray(), [
            'tenant_id',
            'instance_name',
            'display_name',
            'phone',
            'status',
            'profile_name',
            'profile_push_name',
            'profile_full_name',
            'profile_first_name',
            'profile_business_name',
            'profile_is_business',
            'profile_status',
            'profile_avatar_url',
            'profile_picture_id',
            'profile_number',
            'profile_jid',
            'profile_devices',
            'profile_lid',
            'profile_contact_found',
            'last_connection_state',
        ]);
    }

    protected function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
