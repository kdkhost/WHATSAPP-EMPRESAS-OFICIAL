<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhatsApp\Services\MetaCloudApiClient;
use App\Modules\WhatsApp\Services\WhatsAppMetaClient;
use App\Modules\WhatsApp\Services\WhatsAppQueuePlanner;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use App\Repositories\WhatsAppInstanceRepository;
use App\Support\Format;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gerenciador Oficial de Instancias e Numeros WhatsApp do Tenant
 * Baseado estritamente na WhatsApp Cloud API Oficial da Meta (Graph API v21.0).
 */
class InstanceManager
{
    protected MetaCloudApiClient $metaClient;

    public function __construct(?MetaCloudApiClient $metaClient = null)
    {
        $this->metaClient = $metaClient ?? app(MetaCloudApiClient::class);
    }

    /**
     * Cria a instancia padrao do tenant ao ativar plano.
     */
    public function createDefaultInstance(Tenant $tenant): TenantInstance
    {
        $name = $this->nextManagedInstanceName($tenant);

        return $this->createInstance($tenant, $name, isDefault: true);
    }

    /**
     * Cria instancia extra comprada individualmente.
     */
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

    /**
     * Renomeia a exibicao local da instancia.
     */
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

    /**
     * Deleta uma instancia localmente.
     */
    public function deleteInstance(TenantInstance $instance): bool
    {
        app(WhatsAppInstanceRepository::class)->markDeletedRemote($instance);
        $instance->delete();

        return true;
    }

    /**
     * Na Meta Cloud API Oficial, a conexao e direta por Token e Phone Number ID (sem QR Code).
     * Retorna null sempre para manter compatibilidade de assinatura.
     */
    public function getQrCode(TenantInstance $instance): ?string
    {
        return null;
    }

    /**
     * Verifica se a instancia esta conectada e valida na Meta Cloud API.
     */
    public function isConnectedByStatus(TenantInstance $instance): bool
    {
        if ($instance->status === 'connected' && filled($instance->profile_number)) {
            return true;
        }

        $meta = $this->metaClient->forInstance($instance);

        return $meta->isConfigured();
    }

    /**
     * Desconecta a instancia localmente.
     */
    public function disconnectInstance(TenantInstance $instance): array
    {
        $instance->forceFill([
            'status' => 'disconnected',
            'last_connection_state' => 'close',
        ])->save();

        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance->refresh());

        return ['ok' => true];
    }

    /**
     * Verifica se o numero de telefone ja esta em uso por outra instancia ativa.
     */
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

    /**
     * Verifica status de conexao e atualiza dados da Meta.
     */
    public function checkStatus(TenantInstance $instance): array
    {
        return $this->syncProfile($instance);
    }

    /**
     * Sincroniza dados da Conta Oficial da Meta: Nome Verificado, Numero de Exibicao,
     * Classificacao de Qualidade (GREEN, YELLOW, RED) e Conectividade.
     */
    public function syncProfile(TenantInstance $instance): array
    {
        $meta = $this->metaClient->forInstance($instance);

        if (! $meta->isConfigured()) {
            $instance->forceFill([
                'status' => 'disconnected',
                'last_connection_state' => 'close',
                'last_error' => 'Credenciais Meta (Phone Number ID ou Token) nao configuradas.',
                'last_profile_sync_at' => now(),
            ])->save();

            return [
                'ok' => false,
                'connected' => false,
                'state' => 'close',
                'error' => 'Credenciais Meta nao configuradas para esta instancia.',
                'errors' => ['Credenciais Meta nao configuradas.'],
            ];
        }

        $test = $meta->testConnection();
        $connected = ! empty($test['ok']);
        $errors = [];

        if (! $connected) {
            $errors[] = $test['message'] ?? 'Falha na conexao com a Meta Graph API.';
        }

        $displayPhone = $test['display_phone_number'] ?? $instance->phone;
        $verifiedName = $test['verified_name'] ?? $instance->profile_name;
        $quality = $test['quality_rating'] ?? 'GREEN';

        $instance->forceFill([
            'status' => $connected ? 'connected' : 'disconnected',
            'last_connection_state' => $connected ? 'open' : 'close',
            'phone' => $displayPhone ?: $instance->phone,
            'profile_number' => $displayPhone ?: $instance->profile_number,
            'profile_name' => $verifiedName ?: $instance->profile_name,
            'profile_business_name' => $verifiedName ?: $instance->profile_business_name,
            'profile_status' => "Qualidade Meta: {$quality}",
            'last_error' => $errors ? mb_substr(implode(' | ', $errors), 0, 2000) : null,
            'last_profile_sync_at' => now(),
            'meta' => array_merge($instance->meta ?? [], [
                'meta_test' => $test,
                'quality_rating' => $quality,
            ]),
        ])->save();

        $instance->refresh();
        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance);

        return [
            'ok' => $connected,
            'connected' => $connected,
            'state' => $connected ? 'open' : 'close',
            'profile' => [
                'name' => $instance->profile_name,
                'status' => $instance->profile_status,
                'avatar' => $instance->profile_avatar_url,
                'number' => $instance->profile_number,
                'quality' => $quality,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Salva as credenciais da Meta Cloud API para esta instancia.
     */
    public function saveInstanceConfig(TenantInstance $instance, array $data): array
    {
        $updates = [];

        if (array_key_exists('meta_phone_number_id', $data)) {
            $updates['meta_phone_number_id'] = trim((string) $data['meta_phone_number_id']) ?: null;
        }
        if (array_key_exists('meta_waba_id', $data)) {
            $updates['meta_waba_id'] = trim((string) $data['meta_waba_id']) ?: null;
        }
        if (array_key_exists('meta_access_token', $data) && filled($data['meta_access_token'])) {
            $updates['meta_access_token'] = trim((string) $data['meta_access_token']);
        }
        if (array_key_exists('display_name', $data) && filled($data['display_name'])) {
            $updates['display_name'] = trim((string) $data['display_name']);
        }
        if (array_key_exists('phone', $data) && filled($data['phone'])) {
            $updates['phone'] = trim((string) $data['phone']);
            $updates['profile_number'] = trim((string) $data['phone']);
        }

        if ($updates !== []) {
            $instance->forceFill($updates)->save();
            $instance->refresh();
        }

        // Testa e sincroniza
        $sync = $this->syncProfile($instance);

        return [
            'ok' => true,
            'applied' => $sync['connected'],
            'message' => $sync['connected']
                ? 'Credenciais Meta validadas e conectadas com sucesso!'
                : 'Credenciais salvas. Atencao: teste de conexao com a Meta retornou pendente.',
            'webhook_url' => $instance->secure_webhook_url,
            'sync' => $sync,
        ];
    }

    public function sendText(TenantInstance $instance, string $to, string $message, array $context = []): ?string
    {
        $safety = app(WhatsAppSafetyService::class);
        $planner = app(WhatsAppQueuePlanner::class);
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
        $client = app(WhatsAppMetaClient::class);

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
                    'meta_message_id' => $id,
                ],
            ]);
        }

        return $id;
    }

    public function sendImage(TenantInstance $instance, string $to, string $image, ?string $caption = null, array $context = []): ?string
    {
        $safety = app(WhatsAppSafetyService::class);
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

        $client = app(WhatsAppMetaClient::class);

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
                    'meta_message_id' => $id,
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendAudio($to, $audio, $ptt, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendDocument($to, $file, $filename, $caption, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendVideo($to, $video, $caption, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendContact($to, $contactName, $contactPhone, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendLocation($to, $latitude, $longitude, $name, $address, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendButtons($to, $title, $description, $buttons, $footer, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendList($to, $title, $description, $buttonText, $sections, $footer, $safeContext)
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
            fn (WhatsAppMetaClient $client, array $safeContext) => $client->sendPoll($to, $name, $options, $selectableCount, $safeContext)
        );
    }

    protected function sendWithSafety(TenantInstance $instance, string $to, string $preview, string $mediaType, array $context, callable $sender): ?string
    {
        $safety = app(WhatsAppSafetyService::class);
        $planner = app(WhatsAppQueuePlanner::class);
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

        $client = app(WhatsAppMetaClient::class);

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
                    'meta_message_id' => $id,
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
        $secret = Str::random(48);

        $instance = TenantInstance::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $name,
            'display_name' => $displayName ?? $tenant->company_name,
            'status' => 'disconnected',
            'is_default' => $isDefault,
            'is_extra_billable' => $isExtraBillable,
            'recurring_amount' => $isExtraBillable ? $recurringAmount : 0,
            'billing_started_at' => $isExtraBillable ? now('America/Sao_Paulo')->toDateString() : null,
            'api_base_url' => 'https://graph.facebook.com/v21.0',
            'api_key' => $secret,
            'webhook_enabled' => true,
            'webhook_by_events' => true,
            'webhook_base64' => false,
            'webhook_secret' => $secret,
            'meta' => [],
        ]);

        app(WhatsAppInstanceRepository::class)->mirrorTenantInstance($instance);

        return $instance;
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

    protected function normalizeWhatsAppNumber(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $clean = preg_replace('/[:@].+$/', '', $value) ?? $value;
        $digits = preg_replace('/\D/', '', $clean) ?? '';

        return $digits !== '' ? $digits : null;
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

    protected function compactArray(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }
}
