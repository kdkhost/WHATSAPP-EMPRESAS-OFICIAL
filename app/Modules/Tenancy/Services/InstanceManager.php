<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use App\Modules\WhatsApp\Services\EvolutionClient;
use App\Repositories\WhatsAppInstanceRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gerencia instancias Evolution Go do tenant.
 */
class InstanceManager
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) $this->firstFilled(
            $this->setting('whatsapp.base_url'),
            config('whatsapp.base_url')
        ), '/');
        $this->apiKey = (string) $this->firstFilled(
            $this->setting('whatsapp.global_api_key'),
            $this->setting('whatsapp.global_apikey'),
            config('whatsapp.global_api_key'),
            config('whatsapp.global_apikey'),
            config('whatsapp.api_key')
        );
        $this->timeout = (int) $this->setting('whatsapp.timeout', config('whatsapp.timeout', 30));
    }

    /** Cria a instancia padrao do tenant ao ativar plano. */
    public function createDefaultInstance(Tenant $tenant): TenantInstance
    {
        $name = $this->nextManagedInstanceName($tenant);

        return $this->createInstance($tenant, $name, isDefault: true);
    }

    /** Cria instancia extra comprada individualmente. */
    public function createExtraInstance(
        Tenant $tenant,
        string $displayName,
        bool $billableExtra = false,
        ?float $recurringAmount = null
    ): TenantInstance {
        if (! $tenant->canCreateIncludedInstance() && ! $billableExtra) {
            throw new \RuntimeException('Limite de instancias inclusas do plano atingido. Confirme a compra de uma instancia extra para continuar.');
        }

        if ($billableExtra && (float) $recurringAmount <= 0) {
            throw new \RuntimeException('O valor recorrente da instancia extra nao esta configurado para este plano.');
        }

        $sanitized = $this->nextManagedInstanceName($tenant);

        return $this->createInstance(
            $tenant,
            $sanitized,
            isDefault: false,
            displayName: $displayName,
            isExtraBillable: $billableExtra,
            recurringAmount: (float) ($recurringAmount ?? 0)
        );
    }

    /** Renomeia a exibicao local da instancia sem alterar o identificador tecnico da Evolution. */
    public function renameInstance(TenantInstance $instance, string $newName): bool
    {
        if ($instance->is_default) {
            throw new \RuntimeException('A instancia padrao nao pode ser renomeada.');
        }

        $displayName = trim($newName);
        if ($displayName === '') {
            throw new \RuntimeException('Informe um nome para exibir no sistema.');
        }

        $instance->update(['display_name' => $displayName]);
        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance->refresh());

        return true;
    }

    /** Deleta uma instancia do servidor Evolution. */
    public function deleteInstance(TenantInstance $instance): bool
    {
        $remoteState = $this->remoteInstanceState($instance);

        if (($remoteState['known'] ?? false) && ! ($remoteState['exists'] ?? false)) {
            app(WhatsAppInstanceRepository::class)->markDeletedRemote($instance, 'Instancia ausente na Evolution durante sincronizacao de exclusao.');
            $instance->delete();

            return true;
        }

        if (($remoteState['connected'] ?? false) || (! ($remoteState['known'] ?? false) && $this->isConnectedByStatus($instance))) {
            throw new \RuntimeException('Desconecte a instancia antes de exclui-la.');
        }

        try {
            $this->ensureGlobalConnection();
        } catch (\RuntimeException $e) {
            if ($this->canRemoveLocalOrphan($instance, [$e->getMessage()])) {
                app(WhatsAppInstanceRepository::class)->markDeletedRemote($instance, $e->getMessage());
                $instance->delete();

                return true;
            }

            throw $e;
        }

        $errors = [];
        $statuses = [];

        foreach ($this->deleteIdentifiers($instance) as $identifier) {
            $resp = $this->client($instance, useGlobalKey: true)->delete('/instance/delete/'.rawurlencode($identifier));
            $statuses[] = $resp->status();

            if ($resp->successful() || $resp->status() === 404) {
                app(WhatsAppInstanceRepository::class)->markDeletedRemote($instance);
                $instance->delete();

                return true;
            }

            $errors[] = $this->responseMessage($resp);
        }

        if ($this->canRemoveLocalOrphan($instance, $errors, $statuses)) {
            app(WhatsAppInstanceRepository::class)->markDeletedRemote($instance, implode(' | ', array_unique($errors)));
            $instance->delete();

            return true;
        }

        throw new \RuntimeException('Falha ao deletar no servidor Evolution: '.($errors ? implode(' | ', array_unique($errors)) : 'erro desconhecido'));
    }

    /** Obtem QR Code para conectar WhatsApp. Retorna null se ja estiver conectado. */
    public function getQrCode(TenantInstance $instance): ?string
    {
        // Se ja estiver conectado, nao gera QR novamente
        if ($this->isConnectedByStatus($instance)) {
            return null;
        }

        // Fallback por /instance/all: evita gerar QR quando o servidor ja esta open.
        $remoteState = $this->remoteInstanceState($instance);
        if (($remoteState['known'] ?? false) && ($remoteState['connected'] ?? false)) {
            $instance->forceFill([
                'status' => 'connected',
                'last_connection_state' => 'open',
                'evolution_qr_code' => null,
                'last_error' => null,
            ])->save();

            return null;
        }

        $knownNumber = $this->normalizeWhatsAppNumber((string) ($instance->profile_number ?: $instance->phone));
        $duplicateConflict = $knownNumber ? $this->duplicateNumberConflict($instance, $knownNumber) : null;

        if ($duplicateConflict && ! $duplicateConflict['allow_shared']) {
            $instance->forceFill([
                'last_error' => $duplicateConflict['message'],
                'evolution_qr_code' => null,
            ])->save();

            throw new \RuntimeException($duplicateConflict['message']);
        }

        $instance = $this->ensureRemoteInstanceReady($instance, createIfMissing: true);

        $attempts = [
            fn () => $this->client($instance)->post('/instance/connect', $this->connectPayload($instance, ['number' => ''])),
            fn () => $this->client($instance)->post('/instance/connect', ['number' => '']),
            fn () => $this->client($instance)->get('/instance/qr'),
        ];

        $errors = [];
        $qr = $this->attemptQrCode($instance, $attempts, $errors);

        if (! $qr && $this->hasRecoverableRemoteError($errors)) {
            $instance = $this->recreateRemoteInstance($instance);
            $attempts = [
                fn () => $this->client($instance)->post('/instance/connect', $this->connectPayload($instance, ['number' => ''])),
                fn () => $this->client($instance)->post('/instance/connect', ['number' => '']),
                fn () => $this->client($instance)->get('/instance/qr'),
            ];
            $qr = $this->attemptQrCode($instance, $attempts, $errors);
        }

        if ($qr) {
            return $qr;
        }

        if ($instance->evolution_qr_code) {
            return $instance->evolution_qr_code;
        }

        $instance->forceFill([
            'last_error' => $errors
                ? mb_substr(implode(' | ', array_unique($errors)), 0, 2000)
                : 'QR Code nao retornado pela Evolution.',
        ])->save();

        return null;
    }

    /** Verifica se a instancia esta conectada via status da Evolution API. */
    public function isConnectedByStatus(TenantInstance $instance): bool
    {
        [$ok, $data] = $this->requestJson(
            fn () => $this->client($instance)->get('/instance/status')
        );
        if (! $ok) {
            return in_array($instance->status, ['connected', 'active'], true) && filled($instance->profile_number);
        }
        $stateRaw = data_get($data, 'instance.state', data_get($data, 'data.instance.state', data_get($data, 'data.state', data_get($data, 'state', ''))));
        $phone = data_get($data, 'instance.phone.phoneNumber', data_get($data, 'data.instance.phone.phoneNumber', data_get($data, 'data.Number', '')));
        $jid = $this->firstFilled(
            data_get($data, 'instance.jid'),
            data_get($data, 'data.instance.jid'),
            data_get($data, 'data.Jid'),
            data_get($data, 'data.jid')
        );
        $connected = $this->isVerifiedConnectedState(strtolower((string) $stateRaw), $data, $this->normalizeWhatsAppNumber((string) $phone), $jid);
        if ($connected && $phone) {
            $instance->forceFill(['profile_number' => $phone])->save();
        }
        return $connected;
    }

    /** Desconecta a instancia do WhatsApp via Evolution API. */
    public function disconnectInstance(TenantInstance $instance): array
    {
        [$ok, $data, $error] = $this->requestJson(
            fn () => $this->client($instance)->post('/instance/disconnect')
        );
        if ($ok) {
            $instance->forceFill([
                'status' => 'disconnected',
                'last_connection_state' => 'close',
                'evolution_qr_code' => null,
            ])->save();
        }
        return ['ok' => $ok, 'error' => $error];
    }

    /** Verifica se o numero de telefone ja esta em uso por outra instancia ativa. */
    public function isNumberInUse(string $number, ?int $excludeInstanceId = null): ?TenantInstance
    {
        if (! $number) {
            return null;
        }
        $normalized = $this->normalizeWhatsAppNumber($number);
        if (! $normalized) {
            return null;
        }
        return TenantInstance::query()
            ->whereIn('status', ['connected', 'active'])
            ->where(function ($q) use ($normalized) {
                $q->where('profile_number', $normalized)
                  ->orWhere('phone', $normalized);
            })
            ->when($excludeInstanceId, fn ($q) => $q->where('id', '!=', $excludeInstanceId))
            ->first();
    }

    protected function duplicateNumberConflict(TenantInstance $instance, string $number): ?array
    {
        $duplicate = $this->isNumberInUse($number, $instance->id);

        if (! $duplicate) {
            return null;
        }

        $allowShared = $this->canShareNumberAcrossInstances($instance, $duplicate);

        return [
            'duplicate' => $duplicate,
            'allow_shared' => $allowShared,
            'message' => $allowShared
                ? null
                : "Numero {$number} ja esta em uso pela instancia #{$duplicate->id} ({$duplicate->instance_name}) do tenant #{$duplicate->tenant_id}.",
        ];
    }

    protected function canShareNumberAcrossInstances(TenantInstance $instance, ?TenantInstance $duplicate = null): bool
    {
        return (bool) ($instance->profile_is_business || $duplicate?->profile_is_business);
    }

    /** Verifica status de conexao e atualiza o perfil quando possivel. */
    public function checkStatus(TenantInstance $instance): array
    {
        return $this->syncProfile($instance);
    }

    /**
     * Sincroniza dados visiveis no WhatsApp: avatar, nome, slogan/about,
     * numero, JID e estado da conexao.
     */
    public function syncProfile(TenantInstance $instance): array
    {
        $instance = $this->ensureRemoteInstanceReady($instance, createIfMissing: true);
        $raw = [];
        $errors = [];

        [$statusOk, $statusData, $statusError] = $this->requestJson(
            fn () => $this->client($instance)->get('/instance/status')
        );
        $raw['status'] = $statusData;
        if (! $statusOk && $statusError) {
            $errors[] = $statusError;
        }

        [$allOk, $allData, $allError] = $this->requestJson(
            fn () => $this->client($instance, useGlobalKey: true)->get('/instance/all')
        );
        $raw['all_instances'] = $allData;
        if (! $allOk && $allError) {
            $errors[] = $allError;
        }

        $row = $this->findInstanceRow($allData, $instance->instance_name);

        if (! $statusOk) {
            $connected = in_array($instance->status, ['connected', 'active'], true) && filled($instance->profile_number);
            $state = $instance->last_connection_state ?: ($connected ? 'open' : 'close');
        } else {
            $state = $this->extractStateFromStatus($statusData);
            $connected = $this->isConnectedState($state, $statusData);
        }

        $jid = $this->firstFilled(
            data_get($row, 'jid'),
            data_get($row, 'ownerJid'),
            data_get($row, 'owner'),
            data_get($row, 'data.jid'),
            data_get($statusData, 'instance.jid'),
            data_get($statusData, 'data.instance.jid'),
            data_get($statusData, 'data.Jid'),
            data_get($statusData, 'data.jid')
        );
        $number = $this->normalizeWhatsAppNumber($this->firstFilled(
            data_get($row, 'number'),
            data_get($row, 'owner'),
            data_get($row, 'ownerJid'),
            data_get($row, 'jid'),
            data_get($statusData, 'instance.phone.phoneNumber'),
            data_get($statusData, 'instance.phone.number'),
            data_get($statusData, 'data.instance.phone.phoneNumber'),
            data_get($statusData, 'data.instance.phone.number'),
            data_get($statusData, 'data.Number'),
            data_get($statusData, 'data.number')
        ) ?: $jid);

        $profileUser = $this->extractProfileUser($instance, [$statusData, $row ?? [], $allData], $number, $jid);
        if ($profileUser) {
            $jid = $this->firstFilled(data_get($profileUser, 'jid'), $jid);
            $number = $this->normalizeWhatsAppNumber($this->firstFilled(
                data_get($profileUser, 'number'),
                data_get($profileUser, 'jid'),
                $number
            ));
        }

        $userInfoData = $number ? $this->fetchUserInfo($instance, $number, $raw, $errors) : [];
        $profileData = $userInfoData;

        $profileUser = $this->extractProfileUser($instance, [$userInfoData, $profileData, $statusData, $row ?? [], $allData], $number, $jid) ?: $profileUser;
        if ($profileUser) {
            $jid = $this->firstFilled(data_get($profileUser, 'jid'), $jid);
            $number = $this->normalizeWhatsAppNumber($this->firstFilled(
                data_get($profileUser, 'number'),
                data_get($profileUser, 'jid'),
                $number
            ));
            $raw['profile_user'] = $profileUser;
        }

        $connected = $this->isVerifiedConnectedState($state, $statusData, $number, $jid, $row, $profileUser);
        if (! $connected && in_array($state, ['open', 'connected', 'active'], true) && ! $number && ! $jid) {
            $errors[] = 'A Evolution informou estado aberto, mas nao retornou numero/JID do WhatsApp. Sincronizacao bloqueada ate conectar um WhatsApp real.';
        }

        // Verifica se o numero ja esta em uso por outra instancia ativa
        $duplicateConflictBlocked = false;

        if ($number && $connected) {
            $duplicateConflict = $this->duplicateNumberConflict($instance, $number);
            if ($duplicateConflict && ! $duplicateConflict['allow_shared']) {
                $errors[] = $duplicateConflict['message'];
                $connected = false;
                $duplicateConflictBlocked = true;
            }
        }

        $profileName = $this->extractProfileName($statusData, $row, $profileData, $instance, $profileUser);
        $profileStatus = $this->extractProfileStatus($statusData, $row, $profileData, $profileUser);
        $avatar = $this->firstFilled(
            data_get($profileUser, 'profilePicUrl'),
            data_get($profileUser, 'profilePictureUrl'),
            data_get($profileUser, 'ProfilePicUrl'),
            data_get($profileUser, 'ProfilePictureUrl'),
            data_get($profileData, 'profilePicUrl'),
            data_get($profileData, 'profilePictureUrl'),
            data_get($profileData, 'data.profilePicUrl'),
            data_get($profileData, 'data.profilePictureUrl')
        ) ?: ($number ? $this->fetchAvatarUrl($instance, $number, $raw, $errors) : null);
        $apiKey = $this->findApiKey([$statusData, $row, $allData]);

        $meta = array_merge($instance->meta ?? [], [
            'evolution_sync' => $raw,
        ]);

        $updates = [
            'status' => $connected ? 'connected' : ($state ? 'disconnected' : $instance->status),
            'phone' => $number ?: $instance->phone,
            'profile_number' => $number ?: $instance->profile_number,
            'profile_jid' => $jid ?: $instance->profile_jid,
            'profile_name' => $profileName ?: $instance->profile_name,
            'profile_push_name' => $this->firstFilled(data_get($profileUser, 'pushName'), data_get($profileUser, 'PushName')) ?: $instance->profile_push_name,
            'profile_full_name' => $this->firstFilled(data_get($profileUser, 'fullName'), data_get($profileUser, 'FullName')) ?: $instance->profile_full_name,
            'profile_first_name' => $this->firstFilled(data_get($profileUser, 'firstName'), data_get($profileUser, 'FirstName')) ?: $instance->profile_first_name,
            'profile_business_name' => $this->firstFilled(data_get($profileUser, 'businessName'), data_get($profileUser, 'BusinessName'), data_get($profileUser, 'VerifiedName')) ?: $instance->profile_business_name,
            'profile_is_business' => data_get($profileUser, 'isBusiness', data_get($profileUser, 'IsBusiness', $instance->profile_is_business)),
            'profile_status' => $profileStatus ?: $instance->profile_status,
            'profile_avatar_url' => $avatar ?: $instance->profile_avatar_url,
            'profile_picture_id' => $this->firstFilled(data_get($profileUser, 'pictureId'), data_get($profileUser, 'PictureID')) ?: $instance->profile_picture_id,
            'profile_devices' => is_array(data_get($profileUser, 'devices')) ? data_get($profileUser, 'devices') : (is_array(data_get($profileUser, 'Devices')) ? data_get($profileUser, 'Devices') : $instance->profile_devices),
            'profile_lid' => $this->firstFilled(data_get($profileUser, 'lid'), data_get($profileUser, 'LID')) ?: $instance->profile_lid,
            'profile_contact_found' => data_get($profileUser, 'contactFound', data_get($profileUser, 'ContactFound', $instance->profile_contact_found)),
            'profile_raw' => $this->compactArray([
                'status' => data_get($statusData, 'data'),
                'instance' => $row,
                'profile' => $profileData,
                'user' => $profileUser,
            ]),
            'last_connection_state' => $state ?: null,
            'last_profile_sync_at' => now(),
            'last_error' => $errors ? mb_substr(implode(' | ', array_unique($errors)), 0, 2000) : null,
            'evolution_qr_code' => ($connected || $duplicateConflictBlocked) ? null : $instance->evolution_qr_code,
            'meta' => $meta,
        ];

        if (! $instance->api_key && $apiKey) {
            $updates['api_key'] = $apiKey;
        }

        $persisted = $this->compactArray($updates);
        $persisted['last_error'] = $updates['last_error'];

        $instance->forceFill($persisted)->save();
        $instance = $connected
            ? $this->ensureAutomaticProvisioningState($instance->fresh(), $apiKey)
            : $instance->fresh();
        $instance->refresh();
        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance, $raw);

        return [
            'ok' => true,
            'connected' => $connected,
            'state' => $state,
            'profile' => [
                'name' => $instance->profile_name,
                'status' => $instance->profile_status,
                'avatar' => $instance->profile_avatar_url,
                'number' => $instance->profile_number,
                'jid' => $instance->profile_jid,
            ],
            'errors' => $errors,
        ];
    }

    /** Salva e aplica configuracoes da Evolution para uma instancia. */
    public function saveEvolutionConfig(TenantInstance $instance, array $data): array
    {
        $events = array_values(array_filter($data['webhook_events'] ?? []));
        if ($events === []) {
            $events = $this->defaultWebhookEvents();
        }

        $ignoreStatus = array_key_exists('ignore_status', $data)
            ? (bool) $data['ignore_status']
            : ! (bool) ($data['read_status'] ?? false);

        $settings = [
            'rejectCalls' => (bool) ($data['reject_call'] ?? false),
            'rejectCallsMessage' => (string) ($data['msg_call'] ?? ''),
            'groupsIgnore' => (bool) ($data['groups_ignore'] ?? false),
            'alwaysOnline' => (bool) ($data['always_online'] ?? false),
            'readMessages' => (bool) ($data['read_messages'] ?? false),
            'readStatus' => ! $ignoreStatus,
            'ignoreStatus' => $ignoreStatus,
            'syncFullHistory' => (bool) ($data['sync_full_history'] ?? false),
            // aliases legados para manter compatibilidade com leituras existentes
            'rejectCall' => (bool) ($data['reject_call'] ?? false),
            'msgCall' => (string) ($data['msg_call'] ?? ''),
        ];
        $eventProducers = $this->normalizeEventProducers($data['event_producers'] ?? [
            'rabbitmq' => $data['event_producer_rabbitmq'] ?? null,
            'websocket' => $data['event_producer_websocket'] ?? null,
            'nats' => $data['event_producer_nats'] ?? null,
        ]);

        $updates = [
            'api_base_url' => $data['api_base_url'] ?: null,
            'webhook_enabled' => (bool) ($data['webhook_enabled'] ?? false),
            'webhook_by_events' => (bool) ($data['webhook_by_events'] ?? true),
            'webhook_base64' => (bool) ($data['webhook_base64'] ?? false),
            'webhook_events' => $events,
            'webhook_secret' => $instance->webhook_secret ?: Str::random(48),
            'last_error' => null,
        ];

        if (! empty($data['api_key'])) {
            $updates['api_key'] = $data['api_key'];
        }

        $instance->forceFill($updates)->save();
        $instance->refresh();

        $meta = array_merge($instance->meta ?? [], [
            'evolution_settings' => $settings,
            'evolution_event_producers' => $eventProducers,
        ]);

        $instance->forceFill(['meta' => $meta])->save();
        $instance->refresh();

        $remote = [
            'settings' => $this->applySettings($instance, $settings),
            'webhook' => $this->applyWebhook($instance),
        ];

        $errors = collect($remote)
            ->filter(fn ($item) => is_array($item) && ($item['ok'] ?? true) === false)
            ->map(fn ($item) => $item['message'] ?? 'Falha desconhecida')
            ->values()
            ->all();

        $instance->forceFill([
            'webhook_url' => $instance->webhook_url ?: null,
            'meta' => array_merge($instance->meta ?? [], ['evolution_config_apply' => $remote]),
            'last_error' => $errors ? mb_substr(implode(' | ', $errors), 0, 2000) : null,
        ])->save();
        $instance->refresh();
        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance, $remote);

        return [
            'ok' => $errors === [],
            'webhook_url' => $instance->secure_webhook_url,
            'settings' => $settings,
            'event_producers' => $eventProducers,
            'remote' => $remote,
            'errors' => $errors,
        ];
    }

    public function sendText(TenantInstance $instance, string $to, string $message, array $context = []): ?string
    {
        $safety = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);
        $planner = app(\App\Modules\WhatsApp\Services\WhatsAppQueuePlanner::class);
        $inspection = $context['inspection'] ?? null;

        if (! ($context['skip_safety'] ?? false)) {
            $plan = $planner->plan($instance, array_merge($context, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'media_type' => $context['media_type'] ?? 'text',
            ]));

            if (! $plan['allowed']) {
                return null;
            }

            $inspection = $safety->inspect($instance, $to, $message, array_merge($context, $plan, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
            ]));

            if (! $inspection['allowed']) {
                $safety->recordOutcome($inspection, [
                    'tenant_id' => $instance->tenant_id,
                    'tenant_instance_id' => $instance->id,
                    'to' => $to,
                    'meta' => [
                        'origin' => $context['origin'] ?? 'instance_manager',
                        'message_preview' => mb_substr($message, 0, 160),
                        'planner_delay' => $plan['delay_seconds'],
                    ],
                ]);

                return null;
            }
        }

        $delay = (int) ($context['delay_seconds'] ?? 0);

        $apiKey = (string) ($instance->api_key ?: $this->apiKey);
        $client = new EvolutionClient($instance->evolution_base_url, $apiKey, $instance->instance_name, $this->timeout);

        $context = array_merge($context, $inspection ?: [], [
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'delay_seconds' => $delay,
            'media_type' => $context['media_type'] ?? 'text',
            'origin' => $context['origin'] ?? 'instance_manager',
        ]);

        $id = $client->sendText($to, $message, $context);

        if (is_array($inspection)) {
            $safety->recordOutcome($inspection, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'to' => $to,
                'meta' => [
                    'origin' => $context['origin'],
                    'delay_seconds' => $delay,
                    'evolution_id' => $id,
                ],
            ]);
        }

        return $id;
    }

    public function sendImage(TenantInstance $instance, string $to, string $image, ?string $caption = null, array $context = []): ?string
    {
        $safety = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);
        $preview = $caption ?: '[imagem]';
        $inspection = $context['inspection'] ?? null;

        if (! ($context['skip_safety'] ?? false)) {
            $inspection = $safety->inspect($instance, $to, $preview, array_merge($context, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'media_type' => 'image',
            ]));

            if (! $inspection['allowed']) {
                $safety->recordOutcome($inspection, [
                    'tenant_id' => $instance->tenant_id,
                    'tenant_instance_id' => $instance->id,
                    'to' => $to,
                    'meta' => [
                        'origin' => $context['origin'] ?? 'instance_manager',
                        'media_type' => 'image',
                        'message_preview' => mb_substr($preview, 0, 160),
                    ],
                ]);

                return null;
            }
        }

        $apiKey = (string) ($instance->api_key ?: $this->apiKey);
        $client = new EvolutionClient($instance->evolution_base_url, $apiKey, $instance->instance_name, $this->timeout);

        $context = array_merge($context, $inspection ?: [], [
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'media_type' => 'image',
            'origin' => $context['origin'] ?? 'instance_manager',
        ]);

        $id = $client->sendImage($to, $image, $caption, $context);

        if (is_array($inspection)) {
            $safety->recordOutcome($inspection, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'to' => $to,
                'meta' => [
                    'origin' => $context['origin'],
                    'media_type' => 'image',
                    'evolution_id' => $id,
                ],
            ]);
        }

        return $id;
    }

    public function sendAudio(TenantInstance $instance, string $to, string $audio, bool $ptt = true, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            '[audio]',
            'audio',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendAudio($to, $audio, $ptt, $safeContext)
        );
    }

    public function sendDocument(TenantInstance $instance, string $to, string $file, string $filename = 'documento.pdf', ?string $caption = null, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            '[documento: '.$filename.']',
            'document',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendDocument($to, $file, $filename, $caption, $safeContext)
        );
    }

    public function sendVideo(TenantInstance $instance, string $to, string $video, ?string $caption = null, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            $caption ?: '[video]',
            'video',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendVideo($to, $video, $caption, $safeContext)
        );
    }

    public function sendContact(TenantInstance $instance, string $to, string $contactName, string $contactPhone, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            '[contato: '.$contactName.']',
            'contact',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendContact($to, $contactName, $contactPhone, $safeContext)
        );
    }

    public function sendLocation(TenantInstance $instance, string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            '[localizacao: '.($name ?: $latitude.','.$longitude).']',
            'location',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendLocation($to, $latitude, $longitude, $name, $address, $safeContext)
        );
    }

    public function sendButtons(TenantInstance $instance, string $to, string $title, string $description, array $buttons, ?string $footer = null, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            $title."\n".$description,
            'buttons',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendButtons($to, $title, $description, $buttons, $footer, $safeContext)
        );
    }

    public function sendList(TenantInstance $instance, string $to, string $title, string $description, string $buttonText, array $sections, ?string $footer = null, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            $title."\n".$description,
            'list',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendList($to, $title, $description, $buttonText, $sections, $footer, $safeContext)
        );
    }

    public function sendPoll(TenantInstance $instance, string $to, string $name, array $options, int $selectableCount = 1, array $context = []): ?string
    {
        return $this->sendWithSafety(
            $instance,
            $to,
            '[enquete: '.$name.']',
            'poll',
            $context,
            fn (EvolutionClient $client, array $safeContext) => $client->sendPoll($to, $name, $options, $selectableCount, $safeContext)
        );
    }

    protected function sendWithSafety(TenantInstance $instance, string $to, string $preview, string $mediaType, array $context, callable $sender): ?string
    {
        $safety = app(\App\Modules\WhatsApp\Services\WhatsAppSafetyService::class);
        $planner = app(\App\Modules\WhatsApp\Services\WhatsAppQueuePlanner::class);
        $inspection = $context['inspection'] ?? null;

        if (! ($context['skip_safety'] ?? false)) {
            $plan = $planner->plan($instance, array_merge($context, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'media_type' => $mediaType,
            ]));

            if (! $plan['allowed']) {
                return null;
            }

            $inspection = $safety->inspect($instance, $to, $preview, array_merge($context, $plan, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'media_type' => $mediaType,
            ]));

            if (! $inspection['allowed']) {
                $safety->recordOutcome($inspection, [
                    'tenant_id' => $instance->tenant_id,
                    'tenant_instance_id' => $instance->id,
                    'to' => $to,
                    'meta' => [
                        'origin' => $context['origin'] ?? 'instance_manager',
                        'media_type' => $mediaType,
                        'message_preview' => mb_substr($preview, 0, 160),
                        'planner_delay' => $plan['delay_seconds'],
                    ],
                ]);

                return null;
            }
        }

        $apiKey = (string) ($instance->api_key ?: $this->apiKey);
        $client = new EvolutionClient($instance->evolution_base_url, $apiKey, $instance->instance_name, $this->timeout);

        $safeContext = array_merge($context, $inspection ?: [], [
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'media_type' => $mediaType,
            'origin' => $context['origin'] ?? 'instance_manager',
        ]);

        $id = $sender($client, $safeContext);

        if (is_array($inspection)) {
            $safety->recordOutcome($inspection, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'to' => $to,
                'meta' => [
                    'origin' => $safeContext['origin'],
                    'media_type' => $mediaType,
                    'evolution_id' => $id,
                ],
            ]);
        }

        return $id;
    }

    protected function createInstance(
        Tenant $tenant,
        string $name,
        bool $isDefault,
        ?string $displayName = null,
        bool $isExtraBillable = false,
        float $recurringAmount = 0
    ): TenantInstance {
        $this->ensureGlobalConnection();

        $secret = Str::random(48);
        $instanceToken = Str::random(48);
        $settings = $this->defaultInstanceSettings();
        $eventProducers = $this->defaultEventProducers();
        $events = $this->defaultWebhookEvents();
        $webhookUrl = $this->buildEvolutionWebhookUrl(instanceName: $name, secret: $secret);

        $resp = $this->client()->post('/instance/create', [
            'name' => $name,
            'instanceName' => $name,
            'token' => $instanceToken,
            'number' => '',
            'qrcode' => true,
            'advancedSettings' => $this->buildRemoteInstanceSettingsPayload($settings),
        ]);

        if (! $resp->successful()) {
            Log::notice('Falha ao criar instancia Evolution com advancedSettings, tentando payload reduzido.', [
                'instance_name' => $name,
                'status' => $resp->status(),
                'message' => $this->responseMessage($resp),
            ]);

            $resp = $this->client()->post('/instance/create', [
                'name' => $name,
                'instanceName' => $name,
                'token' => $instanceToken,
                'number' => '',
                'qrcode' => true,
            ]);
        }

        if (! $resp->successful()) {
            throw new \RuntimeException('Falha ao criar instancia no servidor Evolution: '.$this->responseMessage($resp));
        }

        $data = $resp->json() ?? [];
        $remoteId = $this->extractRemoteId($data);
        $qr = $this->extractQrCode($data);

        $instance = TenantInstance::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $name,
            'display_name' => $displayName ?? $tenant->company_name,
            'status' => 'creating',
            'evolution_qr_code' => $qr,
            'is_default' => $isDefault,
            'is_extra_billable' => $isExtraBillable,
            'recurring_amount' => $isExtraBillable ? $recurringAmount : 0,
            'billing_started_at' => $isExtraBillable ? now('America/Sao_Paulo')->toDateString() : null,
            'api_base_url' => $this->baseUrl,
            'api_key' => $this->findApiKey($data) ?: $instanceToken,
            'webhook_enabled' => true,
            'webhook_by_events' => true,
            'webhook_base64' => false,
            'webhook_secret' => $secret,
            'webhook_events' => $events,
            'meta' => array_merge($data, [
                'evolution_remote' => $this->compactArray([
                    'id' => $remoteId,
                    'name' => $name,
                    'token_provided' => true,
                    'webhook_url' => $webhookUrl,
                ]),
                'evolution_event_producers' => $eventProducers,
            ]),
        ]);

        $this->applyAutomaticProvisioning($instance);
        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance, $data);

        return $instance;
    }

    protected function deleteIdentifiers(TenantInstance $instance): array
    {
        $identifiers = [
            $this->extractRemoteId($instance->meta ?? []),
            $this->extractRemoteId($instance->profile_raw ?? []),
            $this->discoverRemoteId($instance),
            $instance->instance_name,
        ];

        return array_values(array_unique(array_filter($identifiers, fn ($value) => is_string($value) && trim($value) !== '')));
    }

    protected function attemptQrCode(TenantInstance $instance, array $attempts, array &$errors): ?string
    {
        foreach ($attempts as $attempt) {
            [$ok, $data, $error, $status] = $this->requestJsonWithStatus($attempt);
            if (! $ok) {
                if ($error) {
                    $errors[] = "HTTP {$status}: {$error}";
                }

                continue;
            }

            $qr = $this->extractQrCode($data);
            if ($qr) {
                $this->rememberQrCode($instance, $qr, $data);

                return $qr;
            }
        }

        return null;
    }

    protected function ensureRemoteInstanceReady(TenantInstance $instance, bool $createIfMissing = false): TenantInstance
    {
        try {
            $this->ensureGlobalConnection();
        } catch (\Throwable) {
            return $instance;
        }

        [$ok, $data] = $this->requestJson(
            fn () => $this->client($instance, useGlobalKey: true)->get('/instance/all')
        );

        if (! $ok) {
            return $instance;
        }

        $row = $this->findInstanceRow($data, $instance->instance_name);

        if (! $row) {
            return $createIfMissing && ! $this->hasLocalConnectedEvidence($instance)
                ? $this->recreateRemoteInstance($instance)
                : $instance;
        }

        $apiKey = $this->findApiKey($row);
        $remoteId = $this->extractRemoteId($row);
        $meta = array_merge($instance->meta ?? [], [
            'evolution_remote' => $this->compactArray(array_merge(
                (array) data_get($instance->meta ?? [], 'evolution_remote', []),
                [
                    'id' => $remoteId,
                    'name' => $instance->instance_name,
                    'discovered_at' => now()->toIso8601String(),
                ]
            )),
        ]);

        $updates = [
            'meta' => $meta,
            'last_error' => null,
        ];

        if ($apiKey && (! $instance->api_key || $this->hasAuthError($instance->last_error))) {
            $updates['api_key'] = $apiKey;
        }

        $instance->forceFill($updates)->save();

        return $this->ensureAutomaticProvisioningState($instance->fresh(), $apiKey);
    }

    protected function recreateRemoteInstance(TenantInstance $instance): TenantInstance
    {
        $this->ensureGlobalConnection();

        $token = $this->firstFilled($instance->api_key, Str::random(48));
        $settings = data_get($instance->meta ?? [], 'evolution_settings', $this->defaultInstanceSettings());

        $resp = $this->client($instance, useGlobalKey: true)->post('/instance/create', [
            'name' => $instance->instance_name,
            'instanceName' => $instance->instance_name,
            'token' => $token,
            'number' => '',
            'qrcode' => true,
            'advancedSettings' => $this->buildRemoteInstanceSettingsPayload($settings),
        ]);

        if (! $resp->successful()) {
            $message = $this->responseMessage($resp);

            if (! str_contains(mb_strtolower($message), 'already') && ! str_contains(mb_strtolower($message), 'existe')) {
                $instance->forceFill(['last_error' => mb_substr('Falha ao recuperar instancia na Evolution: '.$message, 0, 2000)])->save();

                return $instance;
            }
        }

        $data = $resp->json() ?? [];
        $apiKey = $this->findApiKey($data) ?: $token;
        $qr = $this->extractQrCode($data);
        $remoteId = $this->extractRemoteId($data);
        $meta = array_merge($instance->meta ?? [], [
            'evolution_remote' => $this->compactArray([
                'id' => $remoteId,
                'name' => $instance->instance_name,
                'token_provided' => true,
                'recovered_at' => now()->toIso8601String(),
            ]),
            'evolution_recovered_at' => now()->toIso8601String(),
        ]);

        $instance->forceFill([
            'api_key' => $apiKey,
            'status' => 'creating',
            'last_connection_state' => 'connecting',
            'evolution_qr_code' => $qr ?: $instance->evolution_qr_code,
            'meta' => $meta,
            'last_error' => null,
        ])->save();

        $instance = $this->ensureAutomaticProvisioningState($instance->fresh(), $apiKey);
        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance, $data);

        return $instance;
    }

    protected function discoverRemoteId(TenantInstance $instance): ?string
    {
        [$ok, $data] = $this->requestJson(
            fn () => $this->client($instance, useGlobalKey: true)->get('/instance/all')
        );

        if (! $ok) {
            return null;
        }

        $row = $this->findInstanceRow($data, $instance->instance_name);

        return $row ? $this->extractRemoteId($row) : null;
    }

    protected function remoteInstanceState(TenantInstance $instance): array
    {
        try {
            $this->ensureGlobalConnection();
        } catch (\Throwable $e) {
            return ['known' => false, 'exists' => null, 'connected' => null, 'error' => $e->getMessage()];
        }

        [$ok, $data, $error] = $this->requestJson(
            fn () => $this->client($instance, useGlobalKey: true)->get('/instance/all')
        );

        if (! $ok) {
            return ['known' => false, 'exists' => null, 'connected' => null, 'error' => $error];
        }

        $row = $this->findInstanceRow($data, $instance->instance_name);

        return [
            'known' => true,
            'exists' => (bool) $row,
            'connected' => $row ? $this->isConnectedInstanceRow($row) : false,
            'row' => $row,
        ];
    }

    protected function hasRecoverableRemoteError(array $errors): bool
    {
        return $this->hasAuthError(implode(' | ', array_unique($errors)))
            || str_contains(mb_strtolower(implode(' | ', array_unique($errors))), 'not found')
            || str_contains(mb_strtolower(implode(' | ', array_unique($errors))), 'invalid uuid');
    }

    protected function hasAuthError(?string $message): bool
    {
        $message = mb_strtolower((string) $message);

        return str_contains($message, 'not authorized')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'forbidden')
            || str_contains($message, '401')
            || str_contains($message, '403');
    }

    protected function canRemoveLocalOrphan(TenantInstance $instance, array $errors = [], array $statuses = []): bool
    {
        $hasRemoteId = $this->isValidRemoteDeleteId($this->extractRemoteId($instance->meta ?? []))
            || $this->isValidRemoteDeleteId($this->extractRemoteId($instance->profile_raw ?? []));

        $message = mb_strtolower(implode(' | ', array_unique($errors)));
        $hasRecoverableRemoteError = str_contains($message, 'invalid uuid')
            || str_contains($message, 'not found')
            || str_contains($message, 'not authorized')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'forbidden')
            || in_array(404, $statuses, true);

        if ($hasRemoteId && $hasRecoverableRemoteError) {
            return true;
        }

        if ($hasRecoverableRemoteError && ! $this->hasLocalConnectedEvidence($instance)) {
            return true;
        }

        $localOnlyStatuses = [
            'creating',
            'active',
            'disconnected',
            'disconnect',
            'close',
            'closed',
            'error',
            'failed',
            'not_authorized',
            'unauthorized',
            'not_authenticated',
        ];

        $looksLocalOnly = in_array((string) $instance->status, $localOnlyStatuses, true)
            && $this->blankProfileValue($instance->profile_jid)
            && $this->blankProfileValue($instance->profile_number)
            && $this->blankProfileValue($instance->phone);

        if (! $looksLocalOnly) {
            return false;
        }

        return $hasRecoverableRemoteError;
    }

    protected function hasLocalConnectedEvidence(TenantInstance $instance): bool
    {
        $states = [
            (string) $instance->status,
            (string) $instance->last_connection_state,
        ];

        foreach ($states as $state) {
            if (in_array(mb_strtolower(trim($state)), ['connected', 'open'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function isValidRemoteDeleteId(?string $value): bool
    {
        return is_string($value) && Str::isUuid(trim($value));
    }

    protected function blankProfileValue(mixed $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-';
    }

    protected function isConnectedInstanceRow(array $row): bool
    {
        $state = $this->firstFilled(
            data_get($row, 'state'),
            data_get($row, 'connectionStatus'),
            data_get($row, 'connection_state'),
            data_get($row, 'instance.state'),
            data_get($row, 'data.state')
        );

        return $this->truthy(data_get($row, 'connected'))
            || $this->truthy(data_get($row, 'online'))
            || $this->truthy(data_get($row, 'loggedIn'))
            || $this->truthy(data_get($row, 'LoggedIn'))
            || in_array(mb_strtolower((string) $state), ['open', 'connected', 'logged', 'logged_in', 'online'], true);
    }

    protected function applySettings(TenantInstance $instance, array $settings): array
    {
        $payload = $this->buildRemoteInstanceSettingsPayload($settings);
        $attempts = [
            'advanced_settings' => fn () => $this->client($instance)->put("/instance/{$instance->instance_name}/advanced-settings", $payload),
            'settings_set' => fn () => $this->client($instance)->post("/settings/set/{$instance->instance_name}", $payload),
        ];

        $last = ['ok' => false, 'data' => [], 'error' => null, 'status' => 0, 'endpoint' => null];

        foreach ($attempts as $endpoint => $attempt) {
            [$ok, $data, $error, $status] = $this->requestJsonWithStatus($attempt);
            $last = compact('ok', 'data', 'error', 'status') + ['endpoint' => $endpoint];

            if ($ok) {
                return [
                    'ok' => true,
                    'status' => $status,
                    'data' => $data,
                    'message' => null,
                    'endpoint' => $endpoint,
                ];
            }

            if ($status === 404) {
                Log::notice('Endpoint de configuracao da Evolution indisponivel, tentando fallback.', [
                    'instance_name' => $instance->instance_name,
                    'endpoint' => $endpoint,
                    'status' => $status,
                ]);

                continue;
            }
        }

        if (($last['status'] ?? 0) === 404) {
            return ['ok' => true, 'status' => $last['status'], 'data' => $last['data'], 'message' => null, 'skipped' => 'settings_endpoint_not_available'];
        }

        return [
            'ok' => false,
            'status' => $last['status'],
            'data' => $last['data'],
            'message' => $last['error'],
            'endpoint' => $last['endpoint'],
        ];
    }

    protected function applyWebhook(TenantInstance $instance): array
    {
        $connectPayload = $this->connectPayload($instance);

        [$connectOk, $connectData, $connectError, $connectStatus] = $this->requestJsonWithStatus(
            fn () => $this->client($instance)->post('/instance/connect', $connectPayload)
        );

        if ($connectOk) {
            if ($qr = $this->extractQrCode($connectData)) {
                $this->rememberQrCode($instance, $qr, $connectData);
            }

            return [
                'ok' => true,
                'status' => $connectStatus,
                'data' => $connectData,
                'message' => null,
                'endpoint' => 'instance_connect',
            ];
        }
        // Em v3.0, o próprio /instance/connect atualiza webhook e subscribe em tempo real.
        // O /webhook/set é mantido apenas como fallback legado quando existir.
        $payload = [
            'webhookUrl' => (bool) $instance->webhook_enabled ? $instance->secure_webhook_url : '',
            'subscribe' => $this->resolveWebhookSubscribe($instance),
        ];

        [$ok, $data, $error, $status] = $this->requestJsonWithStatus(
            fn () => $this->client($instance)->post("/webhook/set/{$instance->instance_name}", $payload)
        );

        if ($status === 404) {
            Log::notice('Endpoint de webhook da Evolution v3.0 nao disponivel (configurado na criacao). Mantendo configuracao local.', [
                'instance_name' => $instance->instance_name,
                'status' => $status,
                'connect_status' => $connectStatus,
                'connect_error' => $connectError,
            ]);

            return ['ok' => true, 'status' => $status, 'data' => $data, 'message' => null, 'skipped' => 'webhook_set_unavailable', 'connect_error' => $connectError];
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'message' => $ok ? null : ($error ?: $connectError),
            'endpoint' => 'webhook_set',
        ];
    }

    protected function fetchAvatarUrl(TenantInstance $instance, string $number, array &$raw, array &$errors): ?string
    {
        $cleanNumber = preg_replace('/\D+/', '', $number);
        $numberJid = str_contains($number, '@') ? $number : ($cleanNumber . '@s.whatsapp.net');

        // 1. Tenta endpoint padrão Evolution v1/v2 (/chat/fetchProfilePictureUrl/{instance})
        if ($instance->instance_name && $cleanNumber) {
            [$picOk, $picData, $picError, $picStatus] = $this->requestJsonWithStatus(
                fn () => $this->client($instance, timeoutOverride: min($this->timeout, 6))->post("/chat/fetchProfilePictureUrl/{$instance->instance_name}", ['number' => $cleanNumber])
            );
            if ($picOk) {
                $url = $this->firstFilled(
                    data_get($picData, 'profilePictureUrl'),
                    data_get($picData, 'data.profilePictureUrl'),
                    data_get($picData, 'data.url'),
                    data_get($picData, 'url')
                );
                if ($url) {
                    $raw['profile_picture'] = $picData;
                    return $url;
                }
            }
        }

        // 2. Tenta /user/avatar (Evolution Go / Baileys com preview)
        [$ok, $data, $error, $status] = $this->requestJsonWithStatus(
            fn () => $this->client($instance, timeoutOverride: min($this->timeout, 6))->post('/user/avatar', ['number' => $cleanNumber ?: $number, 'preview' => true])
        );
        $raw['profile_picture'] = $data;
        if ($ok) {
            $url = $this->firstFilled(
                data_get($data, 'data.url'),
                data_get($data, 'url'),
                data_get($data, 'data.image'),
                data_get($data, 'image'),
                data_get($data, 'data.profilePictureUrl'),
                data_get($data, 'profilePictureUrl')
            );
            if ($url) {
                return $url;
            }
        }

        // 3. Fallback adicional com JID se diferente
        if ($numberJid && $numberJid !== $cleanNumber) {
            [$jidOk, $jidData] = $this->requestJson(
                fn () => $this->client($instance, timeoutOverride: min($this->timeout, 5))->post('/user/avatar', ['number' => $numberJid, 'preview' => true])
            );
            if ($jidOk) {
                $url = $this->firstFilled(
                    data_get($jidData, 'data.url'),
                    data_get($jidData, 'url'),
                    data_get($jidData, 'data.image'),
                    data_get($jidData, 'image'),
                    data_get($jidData, 'data.profilePictureUrl'),
                    data_get($jidData, 'profilePictureUrl')
                );
                if ($url) {
                    $raw['profile_picture'] = $jidData;
                    return $url;
                }
            }
        }

        if ($error && ! $this->isIgnorableAvatarError($error) && ! $this->isIgnorableEvolutionError('profile_picture', $status, $error)) {
            $errors[] = $error;
        }

        return null;
    }

    protected function fetchUserInfo(TenantInstance $instance, string $number, array &$raw, array &$errors): array
    {
        [$ok, $data, $error, $status] = $this->requestJsonWithStatus(
            fn () => $this->client($instance, timeoutOverride: min($this->timeout, 10))->post('/user/info', [
                'number' => [$number],
                'formatJid' => true,
            ])
        );

        $raw['user_info'] = $data;

        if ($ok) {
            return $data;
        }

        if ($error && ! $this->isIgnorableEvolutionError('user_info', $status, $error)) {
            $errors[] = $error;
        }

        return [];
    }

    protected function extractConnectionState(array $connectionData, array $statusData, ?array $row): string
    {
        return strtolower((string) $this->firstFilled(
            data_get($connectionData, 'state'),
            data_get($connectionData, 'instance.state'),
            data_get($connectionData, 'connectionState.state'),
            data_get($connectionData, 'data.state'),
            data_get($connectionData, 'data.instance.state'),
            data_get($row, 'connectionStatus'),
            data_get($row, 'state'),
            data_get($row, 'status'),
            data_get($statusData, 'data.LoggedIn') ? 'open' : null,
            data_get($statusData, 'data.Connected') ? 'connecting' : null
        ));
    }

    protected function isConnectedState(string $state, array $statusData): bool
    {
        if (in_array($state, ['open', 'connected', 'active'], true)) {
            return true;
        }

        if ($this->truthy(data_get($statusData, 'data.LoggedIn'))
            || $this->truthy(data_get($statusData, 'data.Connected'))
            || $this->truthy(data_get($statusData, 'LoggedIn'))
            || $this->truthy(data_get($statusData, 'Connected'))) {
            return true;
        }

        if (data_get($statusData, 'instance.state') === 'open') {
            return true;
        }

        if (data_get($statusData, 'data.instance.state') === 'open') {
            return true;
        }

        if ((bool) data_get($statusData, 'instance.phone.phoneNumber', false)) {
            return true;
        }

        return (bool) (
            data_get($statusData, 'data.instance.phone.phoneNumber')
            ?: data_get($statusData, 'data.Number')
            ?: data_get($statusData, 'data.number')
            ?: data_get($statusData, 'number')
            ?: data_get($statusData, 'phone')
        );
    }

    protected function isVerifiedConnectedState(
        string $state,
        array $statusData,
        ?string $number = null,
        ?string $jid = null,
        ?array $row = null,
        ?array $profileUser = null
    ): bool {
        $state = mb_strtolower(trim($state));
        $hasConnectedState = in_array($state, ['open', 'connected', 'active'], true)
            || $this->truthy(data_get($statusData, 'data.LoggedIn'))
            || $this->truthy(data_get($statusData, 'data.Connected'))
            || $this->truthy(data_get($statusData, 'LoggedIn'))
            || $this->truthy(data_get($statusData, 'Connected'))
            || data_get($statusData, 'instance.state') === 'open'
            || data_get($statusData, 'data.instance.state') === 'open'
            || ($row && $this->isConnectedInstanceRow($row));

        if (! $hasConnectedState) {
            return false;
        }

        $verifiedNumber = $this->normalizeWhatsAppNumber($this->firstFilled(
            $number,
            $jid,
            data_get($profileUser, 'number'),
            data_get($profileUser, 'jid'),
            data_get($row, 'number'),
            data_get($row, 'owner'),
            data_get($row, 'ownerJid'),
            data_get($row, 'jid'),
            data_get($statusData, 'instance.phone.phoneNumber'),
            data_get($statusData, 'instance.phone.number'),
            data_get($statusData, 'data.instance.phone.phoneNumber'),
            data_get($statusData, 'data.instance.phone.number'),
            data_get($statusData, 'data.Number'),
            data_get($statusData, 'data.number'),
            data_get($statusData, 'number'),
            data_get($statusData, 'phone')
        ));

        return is_string($verifiedNumber) && mb_strlen($verifiedNumber) >= 10;
    }

    protected function extractStateFromStatus(array $statusData): string
    {
        return strtolower((string) $this->firstFilled(
            data_get($statusData, 'instance.state'),
            data_get($statusData, 'data.instance.state'),
            data_get($statusData, 'state'),
            data_get($statusData, 'data.state'),
            $this->truthy(data_get($statusData, 'data.LoggedIn')) ? 'open' : null,
            $this->truthy(data_get($statusData, 'data.Connected')) ? 'open' : null,
            $this->truthy(data_get($statusData, 'LoggedIn')) ? 'open' : null,
            $this->truthy(data_get($statusData, 'Connected')) ? 'open' : null,
        ));
    }

    protected function extractProfileName(array $statusData, ?array $row, array $profileData, TenantInstance $instance, ?array $profileUser = null): ?string
    {
        $name = $this->firstFilled(
            data_get($profileUser, 'businessName'),
            data_get($profileUser, 'BusinessName'),
            data_get($profileUser, 'fullName'),
            data_get($profileUser, 'FullName'),
            data_get($profileUser, 'pushName'),
            data_get($profileUser, 'PushName'),
            data_get($profileUser, 'firstName'),
            data_get($profileUser, 'FirstName'),
            data_get($profileUser, 'VerifiedName'),
            data_get($statusData, 'data.Name'),
            data_get($statusData, 'data.name'),
            data_get($statusData, 'data.ProfileName'),
            data_get($row, 'profileName'),
            data_get($row, 'profile_name'),
            data_get($row, 'client_name'),
            data_get($row, 'clientName'),
            data_get($profileData, 'name'),
            data_get($profileData, 'profile.name'),
            data_get($profileData, 'data.name'),
            data_get($profileData, 'businessProfile.name'),
            data_get($profileData, 'data.businessProfile.name')
        );

        if (! $name || $name === $instance->instance_name || preg_match('/^\d+$/', $name)) {
            return null;
        }

        return $name;
    }

    protected function extractProfileStatus(array $statusData, ?array $row, array $profileData, ?array $profileUser = null): ?string
    {
        return $this->firstFilled(
            data_get($profileUser, 'status'),
            data_get($profileUser, 'Status'),
            data_get($profileUser, 'about'),
            data_get($profileUser, 'About'),
            data_get($statusData, 'data.Status'),
            data_get($statusData, 'data.About'),
            data_get($statusData, 'data.profileStatus'),
            data_get($row, 'profileStatus'),
            data_get($row, 'about'),
            data_get($row, 'description'),
            data_get($profileData, 'status'),
            data_get($profileData, 'about'),
            data_get($profileData, 'description'),
            data_get($profileData, 'businessProfile.description'),
            data_get($profileData, 'data.status'),
            data_get($profileData, 'data.about'),
            data_get($profileData, 'data.description'),
            data_get($profileData, 'data.businessProfile.description')
        );
    }

    protected function extractProfileUser(TenantInstance $instance, array $payloads, ?string $number = null, ?string $jid = null): ?array
    {
        $fallback = null;
        $expectedNumber = $this->normalizeWhatsAppNumber($number ?: $jid);
        $expectedJid = $jid ?: ($expectedNumber ? "{$expectedNumber}@s.whatsapp.net" : null);

        foreach ($payloads as $payload) {
            if (! is_array($payload) || $payload === []) {
                continue;
            }

            foreach ($this->profileUserRows($payload) as $user) {
                if (! $fallback) {
                    $fallback = $user;
                }

                $userJid = $this->firstFilled(data_get($user, 'jid'));
                $userNumber = $this->normalizeWhatsAppNumber($this->firstFilled(
                    data_get($user, 'number'),
                    $userJid
                ));

                if ($expectedNumber && $userNumber === $expectedNumber) {
                    return $user;
                }

                if ($expectedJid && $userJid && $userJid === $expectedJid) {
                    return $user;
                }
            }
        }

        return $fallback;
    }

    protected function profileUserRows(array $payload): array
    {
        $rows = [];
        $paths = [
            'data.users',
            'data.Users',
            'users',
            'Users',
            'profile.users',
            'data.profile.users',
            'response.users',
            'data.response.users',
        ];

        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $row) {
                    if (is_array($row) && $this->looksLikeProfileUser($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }

        foreach (['data.Users', 'data.users', 'Users', 'users'] as $path) {
            $value = data_get($payload, $path);
            if (is_array($value) && ! array_is_list($value)) {
                foreach ($value as $jid => $row) {
                    if (is_array($row)) {
                        $row['jid'] = $this->firstFilled(data_get($row, 'jid'), (string) $jid);
                        $row['number'] = $this->firstFilled(data_get($row, 'number'), $this->normalizeWhatsAppNumber((string) $jid));
                        if ($this->looksLikeProfileUser($row)) {
                            $rows[] = $row;
                        }
                    }
                }
            }
        }

        if ($this->looksLikeProfileUser($payload)) {
            $rows[] = $payload;
        }

        foreach ($this->instanceRows($payload) as $row) {
            if ($this->looksLikeProfileUser($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function looksLikeProfileUser(array $row): bool
    {
        foreach (['pushName', 'PushName', 'fullName', 'FullName', 'businessName', 'BusinessName', 'VerifiedName', 'profilePicUrl', 'profilePictureUrl', 'ProfilePicUrl', 'ProfilePictureUrl', 'pictureId', 'PictureID', 'lid', 'LID', 'devices', 'Devices', 'contactFound', 'ContactFound', 'status', 'Status'] as $key) {
            if (filled(data_get($row, $key))) {
                return true;
            }
        }

        return false;
    }

    protected function findInstanceRow(array $payload, string $instanceName): ?array
    {
        foreach ($this->instanceRows($payload) as $row) {
            $name = $this->firstFilled(
                data_get($row, 'name'),
                data_get($row, 'instanceName'),
                data_get($row, 'instance.name'),
                data_get($row, 'data.name')
            );

            if ($name === $instanceName) {
                return $row;
            }
        }

        return null;
    }

    protected function extractRemoteId(array $payload): ?string
    {
        return $this->firstFilled(
            data_get($payload, 'evolution_remote.id'),
            data_get($payload, 'id'),
            data_get($payload, 'uuid'),
            data_get($payload, '_id'),
            data_get($payload, 'instanceId'),
            data_get($payload, 'instance_id'),
            data_get($payload, 'instance.id'),
            data_get($payload, 'instance.uuid'),
            data_get($payload, 'instance._id'),
            data_get($payload, 'instance.instanceId'),
            data_get($payload, 'data.id'),
            data_get($payload, 'data.uuid'),
            data_get($payload, 'data._id'),
            data_get($payload, 'data.instanceId'),
            data_get($payload, 'data.instance.id'),
            data_get($payload, 'data.instance.uuid'),
            data_get($payload, 'response.id'),
            data_get($payload, 'response.uuid'),
            data_get($payload, 'response.instance.id'),
            data_get($payload, 'response.instance.uuid')
        );
    }

    protected function instanceRows(array $payload): array
    {
        $data = data_get($payload, 'data', $payload);
        $rows = data_get($data, 'instances')
            ?: data_get($data, 'response')
            ?: data_get($payload, 'instances')
            ?: $data;

        if (is_array($rows) && array_is_list($rows)) {
            return array_filter($rows, 'is_array');
        }

        return is_array($rows) && $rows !== [] ? [$rows] : [];
    }

    protected function normalizeWhatsAppNumber(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $clean = preg_replace('/[:@].+$/', '', $value) ?? $value;
        $digits = preg_replace('/\D/', '', $clean) ?? '';

        return $digits !== '' ? $digits : null;
    }

    protected function findApiKey(mixed $payload): ?string
    {
        $stack = is_array($payload) ? [$payload] : [];
        $keys = ['apikey', 'apiKey', 'api_key', 'token', 'instanceToken', 'instance_token', 'hash'];

        while ($stack) {
            $current = array_pop($stack);
            foreach ($current as $key => $value) {
                if (is_array($value)) {
                    $stack[] = $value;

                    continue;
                }

                if (in_array((string) $key, $keys, true) && is_string($value) && mb_strlen($value) >= 5) {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function extractQrCode(array $data): ?string
    {
        $qr = $this->firstFilled(
            data_get($data, 'qrcode.base64'),
            data_get($data, 'qrcode.qrcode'),
            data_get($data, 'qrcode.Qrcode'),
            data_get($data, 'data.qrcode.base64'),
            data_get($data, 'data.qrcode'),
            data_get($data, 'data.Qrcode'),
            data_get($data, 'data.base64'),
            data_get($data, 'base64'),
            data_get($data, 'qrcode')
        );

        if ($qr && str_starts_with($qr, 'data:image')) {
            return $qr;
        }

        if ($qr && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $qr) && mb_strlen($qr) > 250) {
            return 'data:image/png;base64,'.preg_replace('/\s+/', '', $qr);
        }

        return $qr;
    }

    protected function rememberQrCode(TenantInstance $instance, string $qr, array $payload = []): void
    {
        try {
            $instance->forceFill([
                'evolution_qr_code' => $qr,
                'last_error' => null,
            ])->save();
        } catch (\Throwable) {
            TenantInstance::whereKey($instance->id)->update(['last_error' => null]);
        }

        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance->refresh(), $payload);
    }

    protected function applyAutomaticProvisioning(TenantInstance $instance): void
    {
        $settings = $this->defaultInstanceSettings();
        $eventProducers = $this->defaultEventProducers();
        $meta = array_merge($instance->meta ?? [], [
            'evolution_settings' => $settings,
            'evolution_event_producers' => $eventProducers,
            'auto_provisioned_at' => now()->toIso8601String(),
        ]);

        $instance->forceFill([
            'meta' => $meta,
            'last_error' => null,
        ])->save();

        $remote = [
            'settings' => $this->applySettings($instance, $settings),
            'webhook' => $this->applyWebhook($instance),
        ];

        $errors = collect($remote)
            ->filter(fn ($item) => is_array($item) && ($item['ok'] ?? true) === false)
            ->map(fn ($item) => $item['message'] ?? 'Falha desconhecida')
            ->filter()
            ->values()
            ->all();

        $instance->forceFill([
            'meta' => array_merge($instance->meta ?? [], ['evolution_config_apply' => $remote]),
            'last_error' => $errors ? mb_substr(implode(' | ', array_unique($errors)), 0, 2000) : null,
        ])->save();
    }

    protected function ensureAutomaticProvisioningState(TenantInstance $instance, ?string $apiKey = null): TenantInstance
    {
        $needsWebhookSetup = ! $instance->webhook_enabled || ! $instance->webhook_secret || empty($instance->webhook_events);
        $needsSettingsSetup = empty(data_get($instance->meta ?? [], 'evolution_settings'));
        $needsProducerSetup = empty(data_get($instance->meta ?? [], 'evolution_event_producers'));

        $updates = [];

        if (! $instance->api_key && $apiKey) {
            $updates['api_key'] = $apiKey;
        }

        if (! $instance->webhook_secret) {
            $updates['webhook_secret'] = Str::random(48);
        }

        if (! $instance->webhook_enabled) {
            $updates['webhook_enabled'] = true;
        }

        if (empty($instance->webhook_events)) {
            $updates['webhook_events'] = $this->defaultWebhookEvents();
        }

        if (is_null($instance->webhook_by_events)) {
            $updates['webhook_by_events'] = true;
        }

        if (is_null($instance->webhook_base64)) {
            $updates['webhook_base64'] = false;
        }

        if ($needsSettingsSetup) {
            $updates['meta'] = array_merge($instance->meta ?? [], [
                'evolution_settings' => $this->defaultInstanceSettings(),
                'evolution_event_producers' => $this->defaultEventProducers(),
            ]);
        } elseif ($needsProducerSetup) {
            $updates['meta'] = array_merge($instance->meta ?? [], [
                'evolution_event_producers' => $this->defaultEventProducers(),
            ]);
        }

        if ($updates !== []) {
            $instance->forceFill($updates)->save();
            $instance->refresh();
        }

        if (! $needsWebhookSetup && ! $needsSettingsSetup && ! $needsProducerSetup) {
            return $instance;
        }

        $remote = [];

        if ($needsSettingsSetup) {
            $remote['settings'] = $this->applySettings($instance, data_get($instance->meta ?? [], 'evolution_settings', $this->defaultInstanceSettings()));
        }

        if ($needsWebhookSetup) {
            $remote['webhook'] = $this->applyWebhook($instance);
        }

        $errors = collect($remote)
            ->filter(fn ($item) => is_array($item) && ($item['ok'] ?? true) === false)
            ->map(fn ($item) => $item['message'] ?? 'Falha desconhecida')
            ->filter()
            ->values()
            ->all();

        $instance->forceFill([
            'meta' => array_merge($instance->meta ?? [], ['evolution_config_apply' => array_merge(data_get($instance->meta ?? [], 'evolution_config_apply', []), $remote)]),
            'last_error' => $errors ? mb_substr(implode(' | ', array_unique($errors)), 0, 2000) : null,
        ])->save();

        return $instance->fresh();
    }

    protected function defaultInstanceSettings(): array
    {
        return [
            'rejectCalls' => true,
            'rejectCallsMessage' => 'Atendimento somente por mensagem.',
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
            'ignoreStatus' => true,
            'syncFullHistory' => false,
            'rejectCall' => true,
            'msgCall' => 'Atendimento somente por mensagem.',
        ];
    }

    protected function defaultEventProducers(): array
    {
        return [
            'rabbitmq' => 'default',
            'websocket' => 'default',
            'nats' => 'default',
        ];
    }

    protected function normalizeEventProducers(?array $producers): array
    {
        $allowed = ['default', 'enabled', 'disabled'];
        $normalized = $this->defaultEventProducers();

        foreach (array_keys($normalized) as $key) {
            $value = strtolower(trim((string) data_get($producers, $key, 'default')));
            $normalized[$key] = in_array($value, $allowed, true) ? $value : 'default';
        }

        return $normalized;
    }

    protected function eventProducerEnabled(array $producers, string $key): string
    {
        return ($this->normalizeEventProducers($producers)[$key] ?? 'default') === 'enabled' ? 'true' : 'false';
    }

    protected function buildEvolutionWebhookUrl(?TenantInstance $instance = null, ?string $instanceName = null, ?string $secret = null): string
    {
        $query = [
            'token' => $secret ?: $instance?->webhook_secret,
            'instance' => $instanceName ?: $instance?->instance_name,
        ];

        if ($instance?->id) {
            $query['tenant_instance'] = $instance->id;
        }

        return url('/webhooks/evolution/messages').'?'.http_build_query(array_filter($query, fn ($value) => filled($value)));
    }

    protected function connectPayload(TenantInstance $instance, array $extra = []): array
    {
        return array_merge([
            'webhookUrl' => (bool) $instance->webhook_enabled ? $instance->secure_webhook_url : '',
            'subscribe' => $this->resolveWebhookSubscribe($instance),
            'rabbitmqEnable' => $this->eventProducerEnabled(data_get($instance->meta ?? [], 'evolution_event_producers', []), 'rabbitmq'),
            'websocketEnable' => $this->eventProducerEnabled(data_get($instance->meta ?? [], 'evolution_event_producers', []), 'websocket'),
            'natsEnable' => $this->eventProducerEnabled(data_get($instance->meta ?? [], 'evolution_event_producers', []), 'nats'),
        ], $extra);
    }

    protected function resolveWebhookSubscribe(TenantInstance $instance): array
    {
        if (! $instance->webhook_enabled) {
            return [];
        }

        if (! $instance->webhook_by_events) {
            return ['ALL'];
        }

        return $this->evolutionSubscribeEvents($instance->webhook_events ?: $this->defaultWebhookEvents());
    }

    protected function buildRemoteInstanceSettingsPayload(array $settings): array
    {
        $ignoreStatus = array_key_exists('ignoreStatus', $settings)
            ? (bool) $settings['ignoreStatus']
            : ! (bool) ($settings['readStatus'] ?? false);

        return [
            'rejectCalls' => (bool) ($settings['rejectCalls'] ?? $settings['rejectCall'] ?? false),
            'rejectCallsMessage' => trim((string) ($settings['rejectCallsMessage'] ?? $settings['msgCall'] ?? '')),
            'groupsIgnore' => (bool) ($settings['groupsIgnore'] ?? false),
            'alwaysOnline' => (bool) ($settings['alwaysOnline'] ?? false),
            'readMessages' => (bool) ($settings['readMessages'] ?? false),
            'readStatus' => ! $ignoreStatus,
            'syncFullHistory' => (bool) ($settings['syncFullHistory'] ?? false),
        ];
    }

    protected function evolutionSubscribeEvents(array $events): array
    {
        $map = [
            'QRCODE_UPDATED' => 'QRCODE',
            'QRCODE' => 'QRCODE',
            'CONNECTION_UPDATE' => 'CONNECTION',
            'CONNECTION' => 'CONNECTION',
            'MESSAGES_UPSERT' => 'MESSAGE',
            'MESSAGE_UPSERT' => 'MESSAGE',
            'MESSAGE' => 'MESSAGE',
            'MESSAGES_UPDATE' => 'MESSAGE',
            'MESSAGES_DELETE' => 'MESSAGE',
            'SEND_MESSAGE' => 'SEND_MESSAGE',
            'CALL' => 'CALL',
            'CONTACTS_UPSERT' => 'CONTACT',
            'CONTACTS_UPDATE' => 'CONTACT',
            'CONTACT' => 'CONTACT',
            'GROUPS_UPSERT' => 'GROUP',
            'GROUP_UPDATE' => 'GROUP',
            'GROUP_PARTICIPANTS_UPDATE' => 'GROUP',
            'GROUP' => 'GROUP',
            'PRESENCE_UPDATE' => 'PRESENCE',
            'PRESENCE' => 'PRESENCE',
            'CHATS_UPDATE' => 'CHAT_PRESENCE',
            'CHAT_PRESENCE' => 'CHAT_PRESENCE',
            'ALL' => 'ALL',
        ];

        $subscribe = [];

        foreach ($events as $event) {
            $normalized = strtoupper(str_replace(['.', '-'], '_', (string) $event));
            $subscribe[] = $map[$normalized] ?? $normalized;
        }

        $subscribe = array_values(array_unique(array_filter($subscribe)));

        return $subscribe ?: ['MESSAGE'];
    }

    protected function nextManagedInstanceName(Tenant $tenant): string
    {
        $tenant->loadMissing('user');

        $base = $this->tenantUsernameBase($tenant);
        $prefix = $base.'_'.$tenant->id.'_';
        $names = $tenant->instances()->pluck('instance_name')->all();
        $usedSlots = [];
        $legacyCount = 0;

        foreach ($names as $name) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $name, $matches)) {
                $usedSlots[] = (int) $matches[1];
            } else {
                $legacyCount++;
            }
        }

        $limit = max($tenant->includedInstanceLimit(), count($names) + 1, 1) + 25;

        for ($slot = 1; $slot <= $limit; $slot++) {
            if ($slot <= $legacyCount || in_array($slot, $usedSlots, true)) {
                continue;
            }

            return $this->uniqueInstanceName($prefix.$slot);
        }

        return $this->uniqueInstanceName($prefix.(count($names) + 1));
    }

    protected function tenantUsernameBase(Tenant $tenant): string
    {
        $username = null;

        try {
            if (Schema::hasTable('whmcs_services')) {
                $username = WhmcsService::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull('username')
                    ->orderByRaw("CASE WHEN LOWER(status) = 'active' THEN 0 ELSE 1 END")
                    ->latest('id')
                    ->value('username');
            }
        } catch (\Throwable) {
            $username = null;
        }

        $username = $this->firstFilled(
            $tenant->user?->name,
            $username,
            Str::before((string) $tenant->user?->email, '@'),
            $tenant->slug,
            $tenant->company_name,
            'cliente'
        );

        return TenantInstance::sanitizeName((string) $username);
    }

    protected function requestJson(callable $callback): array
    {
        [$ok, $data, $error] = $this->requestJsonWithStatus($callback);

        return [$ok, $data, $error];
    }

    protected function requestJsonWithStatus(callable $callback): array
    {
        try {
            /** @var Response $resp */
            $resp = $callback();
            $data = $resp->json() ?? [];

            return [
                $resp->successful(),
                is_array($data) ? $data : [],
                $resp->successful() ? null : $this->responseMessage($resp),
                $resp->status(),
            ];
        } catch (\Throwable $e) {
            return [false, [], $e->getMessage(), 0];
        }
    }

    protected function responseMessage(Response $resp): string
    {
        $data = $resp->json() ?? [];

        return (string) (data_get($data, 'message')
            ?: data_get($data, 'error')
            ?: data_get($data, 'response.message')
            ?: 'erro desconhecido');
    }

    protected function client(?TenantInstance $instance = null, bool $useGlobalKey = false, ?int $timeoutOverride = null): PendingRequest
    {
        $baseUrl = rtrim((string) ($instance?->api_base_url ?: $this->baseUrl), '/');
        $apiKey = $useGlobalKey ? $this->apiKey : (string) ($instance?->api_key ?: $this->apiKey);

        return Http::baseUrl($baseUrl)
            ->timeout($timeoutOverride ?: $this->timeout)
            ->acceptJson()
            ->withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ]);
    }

    protected function ensureGlobalConnection(): void
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new \RuntimeException('Configure a URL base e a chave global da Evolution Go antes de criar ou excluir instancias.');
        }
    }

    protected function uniqueInstanceName(string $base): string
    {
        $base = TenantInstance::sanitizeName($base);
        $candidate = $base;
        $suffix = 2;

        while (TenantInstance::where('instance_name', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        try {
            return app(SettingsManager::class)->get($key, $default) ?: $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(mb_strtolower(trim($value)), ['1', 'true', 'sim', 'yes', 'open', 'connected', 'logged', 'logged_in', 'online', 'success'], true);
        }

        return false;
    }

    protected function compactArray(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    protected function isIgnorableAvatarError(string $error): bool
    {
        $normalized = mb_strtolower($error);

        if (! str_contains($normalized, 'curl error 28') && ! str_contains($normalized, 'operation timed out')) {
            return false;
        }

        Log::notice('Timeout ignorado ao buscar avatar legado na Evolution', [
            'error' => $error,
        ]);

        return true;
    }

    protected function isIgnorableEvolutionError(string $operation, int $status, string $error): bool
    {
        $normalized = mb_strtolower($error);

        if ($status === 404 && in_array($operation, ['connection_state', 'fetch_profile', 'profile_picture'], true)) {
            Log::notice('Endpoint opcional da Evolution nao disponivel para a instancia', [
                'operation' => $operation,
                'status' => $status,
                'error' => $error,
            ]);

            return true;
        }

        if ($operation === 'profile_picture' && $this->isIgnorableAvatarError($normalized)) {
            return true;
        }

        return false;
    }

    protected function defaultWebhookEvents(): array
    {
        return [
            'QRCODE_UPDATED',
            'CONNECTION_UPDATE',
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CALL',
        ];
    }
}
