<?php

namespace App\Services;

use App\Events\EvolutionWebhookReceived;
use App\Jobs\ProcessEvolutionWebhookJob;
use App\Models\SecurityAuditLog;
use App\Models\WhatsAppWebhookLog;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Repositories\WhatsAppInstanceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EvolutionGoWebhookService
{
    public function __construct(
        protected StructuredLogSanitizer $sanitizer,
        protected WhatsAppInstanceRepository $repository,
        protected WhatsAppAutoService $autoService,
        protected InstanceManager $instances,
    ) {}

    public function receive(Request $request): array
    {
        $payload = $request->all();
        $eventName = $this->eventName($payload, $request);
        $instanceName = $this->instanceName($payload, $request);
        $tenantInstance = $this->resolveTenantInstance($request, $payload, $instanceName);
        $tokenCheck = $this->validateToken($request, $tenantInstance);
        $signatureCheck = $this->validateReplayAndSignature($request, $tenantInstance);
        $eventUid = $this->eventUid($payload, $request, $eventName, $instanceName);

        if (! $tokenCheck['ok']) {
            $this->log($request, $payload, $eventName, $instanceName, $tenantInstance, $eventUid, 'rejected', $tokenCheck['message']);
            $this->securityAudit($request, 'evolution_webhook_rejected', $tenantInstance?->tenant_id, $payload, $tokenCheck['message']);

            return ['ok' => false, 'status' => 403, 'error' => $tokenCheck['message']];
        }

        if (! $signatureCheck['ok']) {
            $this->log($request, $payload, $eventName, $instanceName, $tenantInstance, $eventUid, 'rejected', $signatureCheck['message']);
            $this->securityAudit($request, 'evolution_webhook_replay_or_signature_invalid', $tenantInstance?->tenant_id, $payload, $signatureCheck['message']);

            return ['ok' => false, 'status' => 403, 'error' => $signatureCheck['message']];
        }

        if ($eventUid && WhatsAppWebhookLog::where('event_uid', $eventUid)->whereIn('status', ['received', 'processing', 'processed'])->exists()) {
            return ['ok' => true, 'status' => 200, 'duplicate' => true];
        }

        if (! $tenantInstance && $this->requiresKnownInstance($eventName)) {
            $this->log($request, $payload, $eventName, $instanceName, null, $eventUid, 'unbound_instance', 'Instancia local nao encontrada.');
            $this->securityAudit($request, 'evolution_webhook_unbound_instance', null, $payload, 'Instancia local nao encontrada.');

            return ['ok' => false, 'status' => 422, 'error' => 'Instancia local nao encontrada.'];
        }

        $log = $this->log($request, $payload, $eventName, $instanceName, $tenantInstance, $eventUid, 'received');
        event(new EvolutionWebhookReceived($log));
        ProcessEvolutionWebhookJob::dispatch($log->id);

        return ['ok' => true, 'status' => 202, 'log_id' => $log->id];
    }

    public function processLog(int $logId): void
    {
        $log = WhatsAppWebhookLog::find($logId);
        if (! $log) {
            return;
        }

        $log->forceFill(['status' => 'processing'])->save();

        try {
            $tenantInstance = $log->tenantInstance ?: $this->resolveTenantInstanceFromLog($log);
            $payload = $log->raw_payload ?: [];

            if ($tenantInstance) {
                $this->repository->markWebhookReceived($tenantInstance, $payload);
                $this->applyInstanceState($tenantInstance, $log->event_name, $payload);
                $this->processMessages($tenantInstance, $log, $payload);
            }

            $log->forceFill([
                'tenant_id' => $tenantInstance?->tenant_id ?: $log->tenant_id,
                'tenant_instance_id' => $tenantInstance?->id ?: $log->tenant_instance_id,
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $log->forceFill([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ])->save();

            throw $e;
        }
    }

    protected function processMessages(TenantInstance $tenantInstance, WhatsAppWebhookLog $log, array $payload): void
    {
        if (! in_array($log->event_name, ['message.upsert', 'message.received', 'messages.upsert', 'messages_update', 'messages.upsert'], true)) {
            return;
        }

        foreach ($this->extractMessages($payload) as $message) {
            if ((bool) data_get($message, 'key.fromMe')) {
                continue;
            }

            $phone = (string) (data_get($message, 'key.remoteJid') ?: data_get($message, 'remoteJid') ?: '');
            $phone = preg_replace('/@.*$/', '', $phone);
            $text = data_get($message, 'message.conversation')
                ?: data_get($message, 'message.extendedTextMessage.text')
                ?: data_get($message, 'text')
                ?: data_get($message, 'body');

            if (! $phone || ! is_string($text) || trim($text) === '' || ! $tenantInstance->tenant) {
                continue;
            }

            $pushName = (string) data_get($message, 'pushName', '');
            if ($pushName !== '') {
                $this->autoService->syncCustomerPushName($tenantInstance->tenant, $phone, $pushName);
            }

            $optOut = app(WhatsAppSafetyService::class)->handleInboundOptOut($tenantInstance, $phone, $text, [
                'tenant_id' => $tenantInstance->tenant_id,
                'origin' => 'evolution_webhook_inbound',
            ]);
            if ($optOut) {
                continue;
            }

            $reply = $this->autoService->reply($tenantInstance->tenant, $tenantInstance, $phone, $text, $payload);
            if ($reply) {
                $this->instances->sendText($tenantInstance, $phone, $reply);
            }
        }
    }

    protected function applyInstanceState(TenantInstance $tenantInstance, ?string $event, array $payload): void
    {
        $event = (string) $event;
        $state = strtolower((string) (data_get($payload, 'data.state') ?: data_get($payload, 'state') ?: data_get($payload, 'status') ?: ''));
        $updates = [];

        if (str_contains($event, 'qrcode')) {
            $qr = data_get($payload, 'data.qrcode.base64') ?: data_get($payload, 'data.qrcode') ?: data_get($payload, 'qrcode');
            if ($qr) {
                $updates['evolution_qr_code'] = $qr;
            }
        }

        if (str_contains($event, 'connection') || str_contains($event, 'connected') || str_contains($event, 'disconnected')) {
            $connected = in_array($state, ['open', 'connected', 'active'], true) || str_contains($event, 'connected');
            $updates['status'] = $connected ? 'connected' : 'disconnected';
            $updates['last_connection_state'] = $state ?: ($connected ? 'open' : 'close');
        }

        if (str_contains($event, 'profile') || str_contains($event, 'contacts')) {
            $updates = array_merge($updates, $this->profileUpdates($payload));
        }

        if (str_contains($event, 'deleted')) {
            $updates['status'] = 'disconnected';
            $updates['last_error'] = 'Instancia removida no servidor Evolution.';
        }

        if ($updates !== []) {
            $tenantInstance->forceFill(Arr::where($updates, fn ($value) => $value !== null && $value !== ''))->save();
        }
    }

    protected function profileUpdates(array $payload): array
    {
        $user = $this->profileUserFromPayload($payload);
        $data = is_array(data_get($payload, 'data')) ? data_get($payload, 'data') : [];
        $devices = data_get($user, 'devices');

        return [
            'profile_name' => $this->firstFilled(data_get($user, 'businessName'), data_get($user, 'fullName'), data_get($user, 'pushName'), data_get($user, 'firstName'), data_get($data, 'profileName'), data_get($data, 'name'), data_get($payload, 'profileName')),
            'profile_push_name' => $this->firstFilled(data_get($user, 'pushName')),
            'profile_full_name' => $this->firstFilled(data_get($user, 'fullName')),
            'profile_first_name' => $this->firstFilled(data_get($user, 'firstName')),
            'profile_business_name' => $this->firstFilled(data_get($user, 'businessName')),
            'profile_is_business' => data_get($user, 'isBusiness'),
            'profile_status' => $this->firstFilled(data_get($user, 'status'), data_get($user, 'about'), data_get($data, 'status'), data_get($data, 'about'), data_get($payload, 'about')),
            'profile_avatar_url' => $this->firstFilled(data_get($user, 'profilePicUrl'), data_get($user, 'profilePictureUrl'), data_get($data, 'profilePicUrl'), data_get($data, 'profilePictureUrl'), data_get($payload, 'profilePictureUrl')),
            'profile_picture_id' => $this->firstFilled(data_get($user, 'pictureId')),
            'profile_number' => $this->normalizeWhatsAppNumber($this->firstFilled(data_get($user, 'number'), data_get($user, 'jid'), data_get($data, 'number'), data_get($payload, 'number'))),
            'profile_jid' => $this->firstFilled(data_get($user, 'jid'), data_get($data, 'jid'), data_get($payload, 'jid')),
            'profile_devices' => is_array($devices) ? $devices : null,
            'profile_lid' => $this->firstFilled(data_get($user, 'lid')),
            'profile_contact_found' => data_get($user, 'contactFound'),
            'profile_raw' => $this->sanitizer->sanitize($payload),
            'last_profile_sync_at' => now(),
        ];
    }

    protected function profileUserFromPayload(array $payload): ?array
    {
        foreach (['data.users', 'users', 'data.profile.users', 'profile.users'] as $path) {
            $rows = data_get($payload, $path);
            if (is_array($rows) && array_is_list($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        return $row;
                    }
                }
            }
        }

        $data = data_get($payload, 'data');
        if (is_array($data) && ($this->firstFilled(data_get($data, 'jid'), data_get($data, 'number'), data_get($data, 'profilePicUrl')))) {
            return $data;
        }

        return null;
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

    protected function normalizeWhatsAppNumber(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $clean = preg_replace('/[:@].+$/', '', $value) ?? $value;
        $digits = preg_replace('/\D/', '', $clean) ?? '';

        return $digits !== '' ? $digits : null;
    }

    protected function log(
        Request $request,
        array $payload,
        ?string $eventName,
        ?string $instanceName,
        ?TenantInstance $tenantInstance,
        ?string $eventUid,
        string $status,
        ?string $error = null
    ): WhatsAppWebhookLog {
        $mirror = $tenantInstance ? $this->repository->mirrorTenantInstance($tenantInstance, $payload) : null;
        $headers = collect($request->headers->all())
            ->map(fn ($values) => is_array($values) ? implode('; ', $values) : $values)
            ->all();

        return WhatsAppWebhookLog::create([
            'tenant_id' => $tenantInstance?->tenant_id,
            'whatsapp_instance_id' => $mirror?->id,
            'tenant_instance_id' => $tenantInstance?->id,
            'instance_name' => $instanceName,
            'event_name' => $eventName,
            'event_uid' => $eventUid,
            'source_ip' => $request->ip(),
            'headers' => $this->sanitizer->sanitize($headers),
            'raw_payload' => $this->sanitizer->sanitize($payload),
            'normalized_payload' => $this->sanitizer->sanitize($this->normalize($payload)),
            'status' => $status,
            'error' => $error,
        ]);
    }

    protected function validateToken(Request $request, ?TenantInstance $tenantInstance): array
    {
        $token = (string) ($request->bearerToken()
            ?: $request->query('token')
            ?: $request->header('X-Webhook-Token')
            ?: $request->header('X-Evolution-Token')
            ?: $request->header('apikey'));

        if ($tenantInstance?->webhook_secret) {
            return hash_equals((string) $tenantInstance->webhook_secret, $token)
                ? ['ok' => true]
                : ['ok' => false, 'message' => 'webhook_token_invalid'];
        }

        $globalToken = (string) $this->setting('whatsapp.webhook_token', env('EVOLUTION_WEBHOOK_TOKEN', ''));
        if ($globalToken !== '') {
            return hash_equals($globalToken, $token)
                ? ['ok' => true]
                : ['ok' => false, 'message' => 'webhook_token_invalid'];
        }

        if ((bool) config('whatsapp_anti_ban.security.webhook_secret_required', true)) {
            return ['ok' => false, 'message' => 'webhook_token_not_configured'];
        }

        return ['ok' => true, 'message' => 'webhook_token_not_configured'];
    }

    protected function validateReplayAndSignature(Request $request, ?TenantInstance $tenantInstance): array
    {
        $maxAge = max(30, (int) config('whatsapp_anti_ban.security.webhook_max_age_seconds', 300));
        $signatureRequired = (bool) config('whatsapp_anti_ban.security.webhook_signature_required', true);
        $timestampRequired = (bool) config('whatsapp_anti_ban.security.webhook_timestamp_required', true);
        $timestamp = $request->header('X-Webhook-Timestamp') ?: $request->query('ts');

        if (! $timestamp && $timestampRequired) {
            return ['ok' => false, 'message' => 'webhook_timestamp_missing'];
        }

        if ($timestamp) {
            $age = abs(now()->timestamp - (int) $timestamp);
            if ($age > $maxAge) {
                return ['ok' => false, 'message' => 'webhook_replay_window_expired'];
            }
        }

        $signature = (string) ($request->header('X-Webhook-Signature') ?: $request->header('X-Evolution-Signature') ?: '');
        if ($signature === '') {
            return $signatureRequired
                ? ['ok' => false, 'message' => 'webhook_signature_missing']
                : ['ok' => true];
        }

        $secret = (string) ($tenantInstance?->webhook_secret ?: $this->setting('whatsapp.webhook_token', env('EVOLUTION_WEBHOOK_TOKEN', '')));
        if ($secret === '') {
            return ['ok' => false, 'message' => 'webhook_signature_secret_missing'];
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature)
            ? ['ok' => true]
            : ['ok' => false, 'message' => 'webhook_signature_invalid'];
    }

    protected function resolveTenantInstance(Request $request, array $payload, ?string $instanceName): ?TenantInstance
    {
        $id = $request->query('tenant_instance') ?: data_get($payload, 'tenant_instance_id');
        if ($id) {
            return TenantInstance::with('tenant')->find((int) $id);
        }

        return $instanceName ? TenantInstance::with('tenant')->where('instance_name', $instanceName)->first() : null;
    }

    protected function resolveTenantInstanceFromLog(WhatsAppWebhookLog $log): ?TenantInstance
    {
        if ($log->tenant_instance_id) {
            return TenantInstance::with('tenant')->find($log->tenant_instance_id);
        }

        return $log->instance_name
            ? TenantInstance::with('tenant')->where('instance_name', $log->instance_name)->first()
            : null;
    }

    protected function eventName(array $payload, Request $request): string
    {
        $event = (string) (data_get($payload, 'event')
            ?: data_get($payload, 'eventName')
            ?: $request->header('X-Evolution-Event')
            ?: 'unknown');

        return Str::of($event)->lower()->replace(['_', '-'], '.')->toString();
    }

    protected function instanceName(array $payload, Request $request): ?string
    {
        $name = $request->query('instance')
            ?: data_get($payload, 'instance')
            ?: data_get($payload, 'instanceName')
            ?: data_get($payload, 'data.instance')
            ?: data_get($payload, 'data.instanceName');

        return is_string($name) && $name !== '' ? $name : null;
    }

    protected function eventUid(array $payload, Request $request, string $eventName, ?string $instanceName): string
    {
        $explicit = $request->header('X-Evolution-Event-Id')
            ?: data_get($payload, 'id')
            ?: data_get($payload, 'data.id')
            ?: data_get($payload, 'data.key.id')
            ?: data_get($payload, 'data.messages.0.key.id');

        if ($explicit) {
            return (string) $explicit;
        }

        return hash('sha256', $eventName.'|'.($instanceName ?: '').'|'.json_encode([
            data_get($payload, 'data.key.remoteJid'),
            data_get($payload, 'data.messageTimestamp'),
            data_get($payload, 'date_time'),
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function requiresKnownInstance(string $eventName): bool
    {
        return ! in_array($eventName, ['instance.created'], true);
    }

    protected function normalize(array $payload): array
    {
        return [
            'event' => data_get($payload, 'event') ?: data_get($payload, 'eventName'),
            'instance' => data_get($payload, 'instance') ?: data_get($payload, 'instanceName') ?: data_get($payload, 'data.instance'),
            'message_id' => data_get($payload, 'data.key.id') ?: data_get($payload, 'data.messages.0.key.id'),
            'from' => data_get($payload, 'data.key.remoteJid') ?: data_get($payload, 'data.messages.0.key.remoteJid'),
            'from_me' => data_get($payload, 'data.key.fromMe') ?: data_get($payload, 'data.messages.0.key.fromMe'),
        ];
    }

    protected function extractMessages(array $payload): array
    {
        $messages = data_get($payload, 'data.messages')
            ?: data_get($payload, 'data.message')
            ?: data_get($payload, 'data')
            ?: [];

        if (is_array($messages) && isset($messages['key'])) {
            return [$messages];
        }

        if (is_array($messages) && array_is_list($messages)) {
            return array_values(array_filter($messages, 'is_array'));
        }

        return [];
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        try {
            return app(SettingsManager::class)->get($key, $default) ?: $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function securityAudit(Request $request, string $action, ?int $tenantId, array $payload, ?string $error = null): void
    {
        try {
            SecurityAuditLog::create([
                'tenant_id' => $tenantId,
                'guard' => 'api',
                'action' => $action,
                'subject_type' => 'evolution_webhook',
                'subject_id' => $this->instanceName($payload, $request),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => $this->sanitizer->sanitize($payload),
                'status' => 'blocked',
                'error' => $error,
            ]);
        } catch (\Throwable) {
            // Auditoria de seguranca nao pode derrubar o webhook.
        }
    }
}
