<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppInstanceQuarantine;
use App\Models\WhatsAppInstanceReputation;
use App\Models\WhatsAppSafetyControl;
use App\Models\WhatsAppSafetyEvent;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Templates\Services\TemplateRenderer;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\WhatsApp\Services\WhatsAppMetaClient;
use App\Modules\WhatsApp\Services\WhatsAppQuarantineService;
use App\Modules\WhatsApp\Services\WhatsAppQueuePlanner;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WhatsAppAdminController extends Controller
{
    public function instance(SettingsManager $s)
    {
        $config = $this->whatsappRuntimeConfig($s);
        $persistentStatus = $this->mainInstanceStatusPayload($config);
        $evolutionConfig = $this->mainEvolutionConfig($s);

        $instances = TenantInstance::with('tenant')
            ->orderByDesc('is_default')
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get();

        $instanceSummary = [
            'total' => $instances->count(),
            'connected' => $instances->where('status', 'connected')->count(),
            'business' => $instances->where('profile_is_business', true)->count(),
            'with_avatar' => $instances->filter(fn (TenantInstance $instance) => filled($instance->profile_avatar_url))->count(),
        ];

        return view('whatsapp::instance', compact('config', 'persistentStatus', 'instances', 'instanceSummary', 'evolutionConfig'));
    }

    protected function whatsappRuntimeConfig(?SettingsManager $settings = null): array
    {
        $settings ??= app(SettingsManager::class);
        $baseUrl = $this->firstFilled($settings->get('whatsapp.base_url'), config('whatsapp.base_url'));
        $globalApiKey = $this->firstFilled(
            $settings->get('whatsapp.global_api_key'),
            $settings->get('whatsapp.global_apikey'),
            config('whatsapp.global_api_key'),
            config('whatsapp.global_apikey'),
            env('EVOLUTION_GLOBAL_API_KEY'),
            env('EVOLUTION_GLOBAL_APIKEY'),
            config('whatsapp.api_key')
        );
        $apiKey = $this->firstFilled(
            $settings->get('whatsapp.instance_api_key'),
            $settings->get('whatsapp.apikey'),
            $settings->get('whatsapp.api_key'),
            config('whatsapp.instance_api_key'),
            config('whatsapp.apikey'),
            env('EVOLUTION_INSTANCE_API_KEY'),
            env('EVOLUTION_API_KEY'),
            config('whatsapp.api_key')
        );

        return [
            'base_url'       => $baseUrl ? rtrim($baseUrl, '/') : '',
            'global_api_key' => (string) ($globalApiKey ?? ''),
            'api_key'        => (string) ($apiKey ?? ''),
            'instance'       => (string) ($this->firstFilled($settings->get('whatsapp.instance'), config('whatsapp.instance')) ?? ''),
            'timeout'        => max(5, (int) ($this->firstFilled($settings->get('whatsapp.timeout'), config('whatsapp.timeout', 15)) ?? 15)),
        ];
    }

    protected function mainEvolutionConfig(?SettingsManager $settings = null): array
    {
        $settings ??= app(SettingsManager::class);
        $advanced = $settings->get('whatsapp.advanced_settings', []);
        $events = $settings->get('whatsapp.webhook_events', []);
        $producers = $settings->get('whatsapp.event_producers', []);

        if (! is_array($advanced)) {
            $advanced = [];
        }

        if (! is_array($events)) {
            $events = [];
        }

        if (! is_array($producers)) {
            $producers = [];
        }

        return [
            'event_options' => $this->mainWebhookEventOptions(),
            'recommended_events' => $this->mainRecommendedWebhookEvents(),
            'disabled_events' => $this->mainDisabledWebhookEvents(),
            'current_events' => $events ?: $this->mainRecommendedWebhookEvents(),
            'webhook' => [
                'enabled' => (bool) $settings->get('whatsapp.webhook_enabled', true),
                'byEvents' => (bool) $settings->get('whatsapp.webhook_by_events', true),
                'base64' => (bool) $settings->get('whatsapp.webhook_base64', false),
                'url' => $this->buildMainEvolutionWebhookUrl($settings, $this->whatsappRuntimeConfig($settings)),
                'token' => (string) $this->firstFilled($settings->get('whatsapp.webhook_token'), config('whatsapp.webhook_token')),
                'producers' => $this->normalizeMainEventProducers($producers),
            ],
            'advanced' => array_merge($this->mainRecommendedAdvancedSettings(), $advanced),
        ];
    }

    protected function mainRecommendedWebhookEvents(): array
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

    protected function mainDisabledWebhookEvents(): array
    {
        return [
            'APPLICATION_STARTUP',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'PRESENCE_UPDATE',
            'CHATS_UPDATE',
            'GROUPS_UPSERT',
            'GROUP_UPDATE',
            'GROUP_PARTICIPANTS_UPDATE',
            'NEW_JWT_TOKEN',
        ];
    }

    protected function mainWebhookEventOptions(): array
    {
        return [
            'QRCODE_UPDATED' => ['label' => 'QR Code atualizado', 'description' => 'Mantem a tela de conexao com o QR Code mais recente.'],
            'CONNECTION_UPDATE' => ['label' => 'Status de conexao', 'description' => 'Atualiza conectado, aguardando QR Code, erro ou desconectado.'],
            'MESSAGES_UPSERT' => ['label' => 'Mensagens recebidas', 'description' => 'Registra novas mensagens para cobrancas, historico e automacoes.'],
            'MESSAGES_UPDATE' => ['label' => 'Atualizacao de mensagens', 'description' => 'Recebe entrega, leitura e mudancas relevantes da mensagem.'],
            'SEND_MESSAGE' => ['label' => 'Mensagens enviadas', 'description' => 'Audita envios feitos pela Evolution e pelo sistema.'],
            'CALL' => ['label' => 'Chamadas recebidas', 'description' => 'Permite rejeitar chamadas e registrar tentativas de ligacao.'],
            'APPLICATION_STARTUP' => ['label' => 'Inicializacao da aplicacao', 'description' => 'Evento tecnico do servidor Evolution, normalmente desnecessario.'],
            'MESSAGES_DELETE' => ['label' => 'Exclusao de mensagens', 'description' => 'Permite registrar remocoes e manter o historico coerente com a Evolution.'],
            'CONTACTS_UPSERT' => ['label' => 'Novos contatos', 'description' => 'Desligado para reduzir sincronizacao fora do fluxo de cobranca.'],
            'CONTACTS_UPDATE' => ['label' => 'Atualizacao de contatos', 'description' => 'Desligado para evitar volume sem uso operacional direto.'],
            'PRESENCE_UPDATE' => ['label' => 'Presenca online', 'description' => 'Desligado para reduzir eventos de digitando e online.'],
            'CHATS_UPDATE' => ['label' => 'Atualizacao de conversas', 'description' => 'Desligado para nao sincronizar alteracoes de chat sem necessidade.'],
            'GROUPS_UPSERT' => ['label' => 'Novos grupos', 'description' => 'Desligado porque o sistema trabalha com atendimento individual.'],
            'GROUP_UPDATE' => ['label' => 'Atualizacao de grupos', 'description' => 'Desligado para evitar trafego de grupos.'],
            'GROUP_PARTICIPANTS_UPDATE' => ['label' => 'Participantes de grupos', 'description' => 'Desligado por seguranca e volume de eventos.'],
            'NEW_JWT_TOKEN' => ['label' => 'Novo token JWT', 'description' => 'Evento tecnico interno da Evolution, nao usado pelo sistema.'],
        ];
    }

    protected function mainRecommendedAdvancedSettings(): array
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
        ];
    }

    protected function mainRemoteAdvancedSettings(array $evolutionConfig): array
    {
        $advanced = array_merge(
            $this->mainRecommendedAdvancedSettings(),
            is_array($evolutionConfig['advanced'] ?? null) ? $evolutionConfig['advanced'] : []
        );

        return [
            'rejectCalls' => (bool) ($advanced['rejectCalls'] ?? false),
            'rejectCallsMessage' => trim((string) ($advanced['rejectCallsMessage'] ?? '')),
            'groupsIgnore' => (bool) ($advanced['groupsIgnore'] ?? false),
            'alwaysOnline' => (bool) ($advanced['alwaysOnline'] ?? false),
            'readMessages' => (bool) ($advanced['readMessages'] ?? false),
            'readStatus' => ! (bool) ($advanced['ignoreStatus'] ?? ! (bool) ($advanced['readStatus'] ?? false)),
            'syncFullHistory' => (bool) ($advanced['syncFullHistory'] ?? false),
        ];
    }

    public function messages(Request $request)
    {
        $query = DB::table('whatsapp_messages')->orderByDesc('created_at');

        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('to', 'like', "%{$q}%")
                   ->orWhere('message', 'like', "%{$q}%")
                   ->orWhere('meta_message_id', 'like', "%{$q}%")
                   ->orWhere('evolution_id', 'like', "%{$q}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($direction = $request->input('direction')) {
            $query->where('direction', $direction);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $messages = $query->paginate(25)->withQueryString();

        $stats = [
            'sent'      => DB::table('whatsapp_messages')->where('status', 'sent')->where('created_at', '>=', now()->subDay())->count(),
            'failed'    => DB::table('whatsapp_messages')->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'delivered' => DB::table('whatsapp_messages')->where('status', 'delivered')->where('created_at', '>=', now()->subDay())->count(),
            'total'     => DB::table('whatsapp_messages')->count(),
        ];

        $templates = WhatsAppTemplate::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category', 'content_text', 'content_html']);

        return view('whatsapp::messages', compact('messages', 'stats', 'templates'));
    }

    public function safety()
    {
        $today = now()->startOfDay();
        $hourAgo = now()->subHour();
        $minuteAgo = now()->subMinute();

        $stats = [
            'today_sent' => DB::table('whatsapp_messages')->where('direction', 'out')->where('status', 'sent')->where('created_at', '>=', $today)->count(),
            'last_hour_sent' => DB::table('whatsapp_messages')->where('direction', 'out')->where('status', 'sent')->where('created_at', '>=', $hourAgo)->count(),
            'last_minute_sent' => DB::table('whatsapp_messages')->where('direction', 'out')->where('status', 'sent')->where('created_at', '>=', $minuteAgo)->count(),
            'blocked' => DB::table('whatsapp_safety_events')->where('event_type', 'blocked')->where('created_at', '>=', $today)->count(),
            'deferred' => DB::table('whatsapp_safety_events')->where('event_type', 'deferred')->where('created_at', '>=', $today)->count(),
            'failed' => DB::table('whatsapp_messages')->where('direction', 'out')->where('status', 'failed')->where('created_at', '>=', $today)->count(),
            'delivered' => DB::table('whatsapp_messages')->where('direction', 'out')->where('status', 'delivered')->where('created_at', '>=', $today)->count(),
            'replied' => DB::table('whatsapp_messages')->where('direction', 'in')->where('created_at', '>=', $today)->count(),
            'retries' => DB::table('billing_dispatches')->where('attempts', '>', 1)->where('updated_at', '>=', $today)->count(),
            'warmup_instances' => DB::table('whatsapp_safety_controls')->where('warmup_enabled', true)->count(),
            'paused_instances' => DB::table('whatsapp_safety_controls')->where(function ($query) {
                $query->where('sending_paused', true)->orWhere('sending_disabled', true);
            })->count(),
            'quarantined' => DB::table('whatsapp_instance_quarantines')->where('active', true)->count(),
            'avg_risk' => round((float) (DB::table('whatsapp_messages')->where('created_at', '>=', $today)->avg('risk_score') ?: 0), 1),
            'alerts' => $this->generateAlerts(),
        ];

        $instances = TenantInstance::query()
            ->with('tenant')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(function (TenantInstance $instance) {
                $control = WhatsAppSafetyControl::firstOrCreate(
                    ['tenant_instance_id' => $instance->id],
                    ['tenant_id' => $instance->tenant_id]
                );

                $reputation = \App\Models\WhatsAppInstanceReputation::query()
                    ->where('tenant_instance_id', $instance->id)
                    ->first();

                $quarantine = \App\Models\WhatsAppInstanceQuarantine::query()
                    ->where('tenant_instance_id', $instance->id)
                    ->where('active', true)
                    ->latest()
                    ->first();

                $instance->setRelation('safetyControl', $control);
                $instance->setRelation('reputation', $reputation);
                $instance->setRelation('quarantine', $quarantine);

                return $instance;
            });

        $topRisks = DB::table('whatsapp_safety_events')
            ->selectRaw('tenant_instance_id, MAX(risk_score) as peak_risk, COUNT(*) as events')
            ->where('created_at', '>=', $today)
            ->groupBy('tenant_instance_id')
            ->orderByDesc('peak_risk')
            ->limit(10)
            ->get();

        $settings = $this->safetySettings();

        return view('whatsapp::safety', compact('stats', 'instances', 'topRisks', 'settings'));
    }

    protected function generateAlerts(): array
    {
        $alerts = [];
        $today = now()->startOfDay();

        $dailyLimit = (int) config('whatsapp_anti_ban.limits.per_day', 250);
        $todaySent = DB::table('whatsapp_messages')
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $today)
            ->count();

        $pct = $dailyLimit > 0 ? round(($todaySent / $dailyLimit) * 100) : 0;

        $thresholds = config('whatsapp_anti_ban.alerts', []);
        foreach ([70, 85, 95] as $threshold) {
            if ($pct >= $threshold && $pct < ($threshold + 10)) {
                $alerts[] = [
                    'type' => 'daily_limit',
                    'severity' => $threshold >= 95 ? 'critical' : ($threshold >= 85 ? 'warning' : 'info'),
                    'message' => "Limite diario em {$pct}% ({$todaySent}/{$dailyLimit})",
                    'pct' => $pct,
                ];
            }
        }

        $failedToday = DB::table('whatsapp_messages')
            ->where('direction', 'out')
            ->where('status', 'failed')
            ->where('created_at', '>=', $today)
            ->count();

        $failureThreshold = (int) ($thresholds['failure_threshold'] ?? 20);
        if ($todaySent > 0 && $failedToday > 0) {
            $failureRate = round(($failedToday / $todaySent) * 100);
            if ($failureRate >= $failureThreshold) {
                $alerts[] = [
                    'type' => 'failure_rate',
                    'severity' => 'critical',
                    'message' => "Taxa de falha em {$failureRate}% ({$failedToday}/{$todaySent})",
                    'pct' => $failureRate,
                ];
            }
        }

        $quarantined = DB::table('whatsapp_instance_quarantines')->where('active', true)->count();
        if ($quarantined > 0) {
            $alerts[] = [
                'type' => 'quarantine',
                'severity' => 'critical',
                'message' => "{$quarantined} instancia(s) em quarentena",
            ];
        }

        $rateLimited = DB::table('whatsapp_safety_controls')
            ->whereNotNull('last_rate_limit_at')
            ->where('last_rate_limit_at', '>=', now()->subHour())
            ->count();
        if ($rateLimited > 0) {
            $alerts[] = [
                'type' => 'rate_limit',
                'severity' => 'warning',
                'message' => "{$rateLimited} instancia(s) com rate limit na ultima hora",
            ];
        }

        $criticalRisks = \App\Models\WhatsAppSafetyEvent::query()
            ->where('risk_level', 'critico')
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($criticalRisks > 0) {
            $alerts[] = [
                'type' => 'critical_risk',
                'severity' => 'critical',
                'message' => "{$criticalRisks} eventos de risco critico na ultima hora",
            ];
        }

        return $alerts;
    }

    public function status(Request $request, InstanceManager $manager): JsonResponse
    {
        try {
            if ($request->filled('tenant_instance')) {
                $tenantInstance = TenantInstance::with('tenant')->findOrFail((int) $request->query('tenant_instance'));
                $result = $manager->syncProfile($tenantInstance);
                $tenantInstance->refresh()->load('tenant');

                return response()->json($this->tenantInstanceStatusPayload($tenantInstance, $result));
            }

            $config = $this->whatsappRuntimeConfig();

            if (! $config['api_key'] || ! $config['base_url']) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config, 'Meta WhatsApp Cloud API não configurada.'),
                    'Meta WhatsApp Cloud API não configurada.',
                    false
                ), 422);
            }

            $client = app(WhatsAppMetaClient::class);
            $normalized = $client->getNormalizedInstanceStatus();
            $normalized['_source'] = $this->normalizeMainSourceLabel($normalized['_source'] ?? $normalized['source'] ?? '');
            $normalized['source'] = $normalized['_source'];
            $directStatus = $client->status();
            $directState = [];
            if (($directStatus['ok'] ?? false) === true) {
                $directState = $this->normalizeMainConnectionState(
                    data_get($directStatus, 'data.state'),
                    (array) data_get($directStatus, 'data.raw', [])
                );

                if (($directState['state'] ?? 'close') !== 'open') {
                    $normalized = array_merge($normalized, [
                        'state' => 'connecting',
                        'status' => 'connecting',
                        'status_label' => 'Aguardando QR Code',
                        'connected' => false,
                        '_source' => 'instance_status_direct',
                        'source' => 'instance_status_direct',
                    ]);
                }
            }
            $cachedPayload = $this->mainInstanceStatusPayload($config, $normalized['_api_error'] ?? null);

            $allowConnectedPreserve = ! (($directStatus['ok'] ?? false) === true && (($directState['state'] ?? 'close') !== 'open'));
            if ($allowConnectedPreserve && $this->shouldPreserveMainConnectedStatus($cachedPayload, $normalized)) {
                $preserveMessage = $normalized['_api_error'] ?? 'Status remoto instavel; sessao conectada preservada.';
                $cachedPayload['source'] = 'local_cache';
                $cachedPayload['_source'] = 'local_cache';
                $cachedPayload['message'] = $preserveMessage;

                return response()->json($this->mainInstanceAjaxPayload(
                    $cachedPayload,
                    $preserveMessage,
                    true
                ));
            }

            if (
                ($cachedPayload['persisted'] ?? false)
                && (
                    ($normalized['_source'] ?? '') === 'local_fallback'
                    || (
                        ($normalized['_api_error'] ?? null)
                        && ! ($normalized['connected'] ?? false)
                        && blank($normalized['number'] ?? null)
                        && blank($normalized['name'] ?? null)
                    )
                )
            ) {
                $cachedPayload['source'] = 'local_cache';
                $cachedPayload['_source'] = 'local_cache';

                return response()->json($this->mainInstanceAjaxPayload(
                    $cachedPayload,
                    $normalized['_api_error'] ?? null,
                    true
                ));
            }

            $this->persistMainInstanceStatus($config, $normalized, ['status' => $normalized]);

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $normalized['_api_error'] ?? null),
                $normalized['_api_error'] ?? null,
                true
            ));
        } catch (\Throwable $e) {
            $config ??= $this->whatsappRuntimeConfig();

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $e->getMessage()),
                $e->getMessage(),
                false
            ), 422);
        }
    }

    protected function tenantInstanceStatusPayload(TenantInstance $instance, array $result = []): array
    {
        $devices = is_array($instance->profile_devices) ? array_values($instance->profile_devices) : [];
        $state = (string) ($result['state'] ?? $instance->last_connection_state ?: $instance->status);
        $connected = (bool) ($result['connected'] ?? in_array($instance->status, ['connected', 'active'], true));

        return [
            'ok' => true,
            'id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'tenant_name' => $instance->tenant?->company_name,
            'state' => $state,
            'status' => $instance->status,
            'connected' => $connected,
            'instance' => $instance->instance_name,
            'display_name' => $instance->display_name,
            'name' => $instance->whatsapp_profile_name,
            'profile_name' => $instance->profile_name,
            'push_name' => $instance->profile_push_name,
            'full_name' => $instance->profile_full_name,
            'first_name' => $instance->profile_first_name,
            'business_name' => $instance->profile_business_name,
            'is_business' => $instance->profile_is_business,
            'account_type' => $instance->profile_is_business ? 'Business' : 'Pessoal',
            'status_text' => $instance->profile_status,
            'number' => $instance->profile_number ?: $instance->phone,
            'jid' => $instance->profile_jid,
            'lid' => $instance->profile_lid,
            'avatar' => $instance->profile_avatar_url,
            'picture_id' => $instance->profile_picture_id,
            'contact_found' => $instance->profile_contact_found,
            'devices' => $devices,
            'devices_count' => count($devices),
            'client_name' => $instance->tenant?->company_name ?: $instance->display_name,
            'created_at' => $instance->created_at?->toIso8601String(),
            'last_profile_sync_at' => $instance->last_profile_sync_at?->toIso8601String(),
            'last_connection_state' => $instance->last_connection_state,
            'last_error' => $instance->last_error,
            'errors' => $result['errors'] ?? [],
        ];
    }

    public function saveConfig(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'base_url'       => 'required|url',
            'global_api_key' => 'nullable|string|min:5',
            'api_key'        => 'nullable|string|min:5',
            'instance'       => 'required|string|min:1',
            'timeout'        => 'nullable|integer|min:5|max:120',
            'webhook_enabled' => 'nullable|boolean',
            'webhook_by_events' => 'nullable|boolean',
            'webhook_base64' => 'nullable|boolean',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|max:80',
            'event_producer_rabbitmq' => 'nullable|in:default,enabled,disabled',
            'event_producer_websocket' => 'nullable|in:default,enabled,disabled',
            'event_producer_nats' => 'nullable|in:default,enabled,disabled',
            'rejectCalls' => 'nullable|boolean',
            'groupsIgnore' => 'nullable|boolean',
            'alwaysOnline' => 'nullable|boolean',
            'readMessages' => 'nullable|boolean',
            'readStatus' => 'nullable|boolean',
            'syncFullHistory' => 'nullable|boolean',
            'ignoreStatus' => 'nullable|boolean',
            'rejectCallsMessage' => 'nullable|string|max:500',
        ]);

        $existingConfig = $this->whatsappRuntimeConfig($s);
        $globalApiKey = $this->firstFilled($data['global_api_key'] ?? null, $existingConfig['global_api_key'] ?? null);
        $instanceApiKey = $this->firstFilled($data['api_key'] ?? null, $existingConfig['api_key'] ?? null);

        if (! $globalApiKey || ! $instanceApiKey) {
            return response()->json([
                'ok' => false,
                'message' => 'Informe ou preserve as credenciais da Meta WhatsApp API antes de salvar.',
            ], 422);
        }

        $s->set('whatsapp.base_url', $data['base_url']);
        $s->set('whatsapp.global_api_key', $globalApiKey);
        $s->set('whatsapp.global_apikey', $globalApiKey);
        $s->set('whatsapp.instance_api_key', $instanceApiKey);
        $s->set('whatsapp.apikey', $instanceApiKey);
        $s->set('whatsapp.api_key', $instanceApiKey);
        $s->set('whatsapp.instance', $data['instance']);
        $s->set('whatsapp.timeout', $data['timeout'] ?? 15, 'int');

        $remoteApply = [];
        $remoteErrors = [];
        $shouldApplyEvolution = $request->hasAny([
            'webhook_enabled',
            'webhook_by_events',
            'webhook_base64',
            'webhook_events',
            'event_producer_rabbitmq',
            'event_producer_websocket',
            'event_producer_nats',
            'rejectCalls',
            'groupsIgnore',
            'alwaysOnline',
            'readMessages',
            'readStatus',
            'ignoreStatus',
            'syncFullHistory',
            'rejectCallsMessage',
        ]);

        if ($shouldApplyEvolution) {
            $advancedSettings = $this->normalizeMainAdvancedSettings($request);
            $webhookConfig = [
                'enabled' => $request->boolean('webhook_enabled'),
                'byEvents' => $request->boolean('webhook_by_events', true),
                'base64' => $request->boolean('webhook_base64'),
                'events' => $this->normalizeMainWebhookEvents($data['webhook_events'] ?? []),
                'producers' => $this->normalizeMainEventProducers($data),
            ];

            $s->set('whatsapp.webhook_enabled', $webhookConfig['enabled'], 'bool');
            $s->set('whatsapp.webhook_by_events', $webhookConfig['byEvents'], 'bool');
            $s->set('whatsapp.webhook_base64', $webhookConfig['base64'], 'bool');
            $s->set('whatsapp.webhook_events', $webhookConfig['events'], 'json');
            $s->set('whatsapp.event_producers', $webhookConfig['producers'], 'json');
            $s->set('whatsapp.advanced_settings', $advancedSettings, 'json');

            app()->forgetInstance(WhatsAppMetaClient::class);
            $remoteApply = $this->applyMainEvolutionConfig(app(WhatsAppMetaClient::class), $s, $this->whatsappRuntimeConfig($s), $webhookConfig, $advancedSettings);
            $remoteErrors = collect($remoteApply)
                ->filter(fn ($item) => is_array($item) && ($item['ok'] ?? true) === false)
                ->map(fn ($item) => $item['message'] ?? 'Falha desconhecida na Evolution.')
                ->filter()
                ->values()
                ->all();
        }

        // Recria o singleton com as novas configuracoes
        app()->forgetInstance(WhatsAppMetaClient::class);

        $this->persistMainInstanceStatus($this->whatsappRuntimeConfig($s), [
            'state' => 'nao_verificado',
            'instance' => $data['instance'],
            'last_check' => now()->toIso8601String(),
            '_source' => 'admin_config',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Configuração salva com sucesso.',
            'data' => $this->mainInstanceStatusPayload($this->whatsappRuntimeConfig($s)),
            'remote' => $remoteApply,
            'webhook_url' => $this->buildMainEvolutionWebhookUrl($s, $this->whatsappRuntimeConfig($s)),
            'config' => $this->mainEvolutionConfig($s),
            'errors' => $remoteErrors,
        ]);
    }

    protected function normalizeMainAdvancedSettings(Request $request): array
    {
        $defaults = $this->mainRecommendedAdvancedSettings();
        $ignoreStatus = $request->has('ignoreStatus')
            ? $request->boolean('ignoreStatus')
            : ! $request->boolean('readStatus', ! (bool) $defaults['ignoreStatus']);

        return [
            'rejectCalls' => $request->boolean('rejectCalls', (bool) $defaults['rejectCalls']),
            'rejectCallsMessage' => trim((string) $request->input('rejectCallsMessage', $defaults['rejectCallsMessage'])),
            'groupsIgnore' => $request->boolean('groupsIgnore', (bool) $defaults['groupsIgnore']),
            'alwaysOnline' => $request->boolean('alwaysOnline', (bool) $defaults['alwaysOnline']),
            'readMessages' => $request->boolean('readMessages', (bool) $defaults['readMessages']),
            'ignoreStatus' => $ignoreStatus,
            'readStatus' => ! $ignoreStatus,
            'syncFullHistory' => $request->boolean('syncFullHistory', (bool) $defaults['syncFullHistory']),
        ];
    }

    protected function normalizeMainWebhookEvents(array $events): array
    {
        $allowed = array_keys($this->mainWebhookEventOptions());
        $normalized = [];

        foreach ($events as $event) {
            $event = strtoupper(str_replace(['.', '-'], '_', trim((string) $event)));

            if (in_array($event, $allowed, true)) {
                $normalized[] = $event;
            }
        }

        return array_values(array_unique($normalized)) ?: $this->mainRecommendedWebhookEvents();
    }

    protected function normalizeMainEventProducers(array $data): array
    {
        $allowed = ['default', 'enabled', 'disabled'];
        $source = [
            'rabbitmq' => $data['event_producer_rabbitmq'] ?? data_get($data, 'rabbitmq', data_get($data, 'producers.rabbitmq', 'default')),
            'websocket' => $data['event_producer_websocket'] ?? data_get($data, 'websocket', data_get($data, 'producers.websocket', 'default')),
            'nats' => $data['event_producer_nats'] ?? data_get($data, 'nats', data_get($data, 'producers.nats', 'default')),
        ];

        return collect($source)
            ->map(function ($value) use ($allowed) {
                $value = strtolower(trim((string) $value));

                return in_array($value, $allowed, true) ? $value : 'default';
            })
            ->all();
    }

    protected function mainEventProducerPayload(array $producers): array
    {
        $producers = $this->normalizeMainEventProducers($producers);

        return [
            'rabbitmqEnable' => ($producers['rabbitmq'] ?? 'default') === 'enabled' ? 'true' : 'false',
            'websocketEnable' => ($producers['websocket'] ?? 'default') === 'enabled' ? 'true' : 'false',
            'natsEnable' => ($producers['nats'] ?? 'default') === 'enabled' ? 'true' : 'false',
        ];
    }

    protected function applyMainEvolutionConfig(
        WhatsAppMetaClient $client,
        SettingsManager $settings,
        array $config,
        array $webhookConfig,
        array $advancedSettings
    ): array {
        $result = [
            'settings' => ['ok' => true, 'message' => null],
            'webhook' => ['ok' => true, 'message' => null],
        ];

        try {
            $response = $client->updateAdvancedSettings($advancedSettings);
            $result['settings'] = [
                'ok' => (bool) ($response['success'] ?? $response['ok'] ?? true),
                'message' => $response['error'] ?? null,
                'data' => $response['data'] ?? null,
            ];
        } catch (\Throwable $e) {
            $result['settings'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            $payload = $webhookConfig['enabled']
                ? [
                    'webhookUrl' => $this->buildMainEvolutionWebhookUrl($settings, $config),
                    'subscribe' => $this->resolveMainWebhookSubscribe($webhookConfig),
                ]
                : ['webhookUrl' => '', 'subscribe' => []];
            $payload = array_merge($payload, $this->mainEventProducerPayload($webhookConfig['producers'] ?? []));

            $response = $client->connect('', $payload);
            $result['webhook'] = [
                'ok' => (bool) ($response['success'] ?? $response['ok'] ?? true),
                'message' => $response['error'] ?? null,
                'data' => $response['data'] ?? null,
            ];
        } catch (\Throwable $e) {
            $result['webhook'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        return $result;
    }

    protected function buildMainEvolutionWebhookUrl(SettingsManager $settings, array $config): string
    {
        $token = (string) $this->firstFilled($settings->get('whatsapp.webhook_token'), config('whatsapp.webhook_token'));

        if ($token === '') {
            $token = Str::random(48);
            $settings->set('whatsapp.webhook_token', $token);
        }

        return url('/webhooks/whatsapp/messages').'?'.http_build_query(array_filter([
            'token' => $token,
            'instance' => $config['instance'] ?? '',
            'scope' => 'admin',
        ], fn ($value) => filled($value)));
    }

    protected function mainConnectPayload(SettingsManager $settings, array $config): array
    {
        $evolutionConfig = $this->mainEvolutionConfig($settings);
        $producerPayload = $this->mainEventProducerPayload(data_get($evolutionConfig, 'webhook.producers', []));

        if (! (bool) data_get($evolutionConfig, 'webhook.enabled', true)) {
            return ['webhookUrl' => '', 'subscribe' => []] + $producerPayload;
        }

        return [
            'webhookUrl' => $this->buildMainEvolutionWebhookUrl($settings, $config),
            'subscribe' => $this->resolveMainWebhookSubscribe([
                'enabled' => true,
                'byEvents' => (bool) data_get($evolutionConfig, 'webhook.byEvents', true),
                'events' => $evolutionConfig['current_events'] ?? $this->mainRecommendedWebhookEvents(),
            ]),
        ] + $producerPayload;
    }

    protected function resolveMainWebhookSubscribe(array $webhookConfig): array
    {
        if (! ($webhookConfig['enabled'] ?? true)) {
            return [];
        }

        if (! ($webhookConfig['byEvents'] ?? true)) {
            return ['ALL'];
        }

        return $this->evolutionSubscribeEvents($webhookConfig['events'] ?? $this->mainRecommendedWebhookEvents());
    }

    protected function ensureMainRemoteInstanceReady(SettingsManager $settings, array $config): array
    {
        $instanceName = trim((string) ($config['instance'] ?? ''));
        $result = ['config' => $config, 'qrcode' => null, 'remote' => null];

        $hasGlobalKey = filled($settings->get('whatsapp.global_api_key'))
            || filled($settings->get('whatsapp.global_apikey'))
            || filled(config('whatsapp.global_api_key'))
            || filled(config('whatsapp.global_apikey'))
            || filled(env('EVOLUTION_GLOBAL_API_KEY'))
            || filled(env('EVOLUTION_GLOBAL_APIKEY'));

        if ($instanceName === '' || ! $hasGlobalKey || empty($config['base_url'])) {
            return $result;
        }

        app()->forgetInstance(WhatsAppMetaClient::class);
        $client = app(WhatsAppMetaClient::class);
        $remote = $client->listInstances();
        $row = null;

        if ($remote['success'] ?? false) {
            foreach ($this->instanceRows($remote['data'] ?? []) as $remoteRow) {
                $remoteName = (string) $this->firstPersistentValue(
                    data_get($remoteRow, 'name'),
                    data_get($remoteRow, 'instanceName'),
                    data_get($remoteRow, 'instance_name')
                );

                if ($remoteName === $instanceName) {
                    $row = $remoteRow;
                    break;
                }
            }
        }

        if (! $row) {
            $token = Str::random(48);
            $created = $client->createInstance([
                'name' => $instanceName,
                'token' => $token,
                'number' => '',
                'qrcode' => true,
                'advancedSettings' => $this->mainRemoteAdvancedSettings($this->mainEvolutionConfig($settings)),
            ]);

            if ($created['success'] ?? false) {
                $row = $created['data'] ?? [];
                $result['qrcode'] = $this->extractQrCode($row);
                $this->storeMainInstanceApiKey($settings, $token);
                $result['config'] = $this->whatsappRuntimeConfig($settings);
                app()->forgetInstance(WhatsAppMetaClient::class);

                return $result;
            }

            Log::warning('Falha ao recuperar/criar instancia principal Evolution.', [
                'instance' => $instanceName,
                'status' => $created['status'] ?? null,
                'error' => $created['error'] ?? $created['message'] ?? null,
            ]);

            return $result;
        }

        if ($this->mainRemoteInstanceNeedsQrRecovery($row)) {
            $recovered = $this->recoverMainRemoteInstanceQr($client, $settings, $config, $row);
            if ($recovered['remote'] ?? null) {
                return $recovered;
            }
        }

        $remoteKey = $this->extractMainInstanceApiKey($row);
        if ($remoteKey !== '' && $remoteKey !== ($config['api_key'] ?? '')) {
            $this->storeMainInstanceApiKey($settings, $remoteKey);
            $result['config'] = $this->whatsappRuntimeConfig($settings);
            app()->forgetInstance(WhatsAppMetaClient::class);
        }

        $result['remote'] = $row;
        $result['qrcode'] = $this->extractQrCode($row);

        return $result;
    }

    protected function mainRemoteInstanceNeedsQrRecovery(array $row): bool
    {
        $connected = (bool) data_get($row, 'connected', false);
        $qrCode = $this->extractQrCode($row);
        $jid = (string) $this->firstPersistentValue(
            data_get($row, 'jid'),
            data_get($row, 'ownerJid'),
            data_get($row, 'owner'),
            data_get($row, 'instance.jid'),
            data_get($row, 'instance.ownerJid')
        );

        return ! $connected && ! $qrCode && $jid !== '';
    }

    protected function recoverMainRemoteInstanceQr(WhatsAppMetaClient $client, SettingsManager $settings, array $config, array $row): array
    {
        $instanceName = trim((string) ($config['instance'] ?? ''));
        $result = ['config' => $config, 'qrcode' => null, 'remote' => null];
        $remoteId = $this->extractMainInstanceId($row);
        $deleteTarget = $remoteId ?: $instanceName;

        if ($deleteTarget === '') {
            return $result;
        }

        $deleted = $client->deleteInstance($deleteTarget);
        if (! ($deleted['success'] ?? false)) {
            Log::warning('Falha ao resetar instancia principal Evolution para recuperar QR.', [
                'instance' => $instanceName,
                'has_uuid' => $remoteId !== '',
                'status' => $deleted['status'] ?? null,
                'error' => $deleted['error'] ?? null,
            ]);

            return $result;
        }

        $token = (string) ($config['api_key'] ?? '');
        $row = null;

        for ($attempt = 0; $attempt < 6; $attempt++) {
            usleep(1000 * 1000);
            $remote = $client->listInstances();

            if ($remote['success'] ?? false) {
                foreach ($this->instanceRows($remote['data'] ?? []) as $remoteRow) {
                    $remoteName = (string) $this->firstPersistentValue(
                        data_get($remoteRow, 'name'),
                        data_get($remoteRow, 'instanceName'),
                        data_get($remoteRow, 'instance_name')
                    );

                    if ($remoteName === $instanceName) {
                        $row = $remoteRow;
                        break 2;
                    }
                }
            }
        }

        if (! $row) {
            $token = $token !== '' ? $token : Str::random(48);
            $created = $client->createInstance([
                'name' => $instanceName,
                'token' => $token,
                'number' => '',
                'qrcode' => true,
                'advancedSettings' => $this->mainRemoteAdvancedSettings($this->mainEvolutionConfig($settings)),
            ]);

            if (! ($created['success'] ?? false)) {
                Log::warning('Falha ao recriar instancia principal Evolution para recuperar QR.', [
                    'instance' => $instanceName,
                    'status' => $created['status'] ?? null,
                    'error' => $created['error'] ?? null,
                ]);

                return $result;
            }

            $row = $created['data'] ?? [];
        }

        $remoteKey = $this->extractMainInstanceApiKey($row);
        if ($remoteKey !== '') {
            $this->storeMainInstanceApiKey($settings, $remoteKey);
            $result['config'] = $this->whatsappRuntimeConfig($settings);
            app()->forgetInstance(WhatsAppMetaClient::class);
        } elseif ($token !== '') {
            $this->storeMainInstanceApiKey($settings, $token);
            $result['config'] = $this->whatsappRuntimeConfig($settings);
            app()->forgetInstance(WhatsAppMetaClient::class);
        }

        $result['remote'] = $row;
        $result['qrcode'] = $this->extractQrCode($row);

        return $result;
    }

    protected function extractMainInstanceId(array $payload): string
    {
        return (string) $this->firstPersistentValue(
            data_get($payload, 'id'),
            data_get($payload, 'uuid'),
            data_get($payload, '_id'),
            data_get($payload, 'instanceId'),
            data_get($payload, 'instance.id'),
            data_get($payload, 'instance.uuid'),
            data_get($payload, 'data.id'),
            data_get($payload, 'data.uuid'),
            data_get($payload, 'data.instance.id')
        );
    }

    protected function extractMainInstanceApiKey(array $payload): string
    {
        return (string) $this->firstPersistentValue(
            data_get($payload, 'token'),
            data_get($payload, 'hash'),
            data_get($payload, 'apikey'),
            data_get($payload, 'apiKey'),
            data_get($payload, 'api_key'),
            data_get($payload, 'instance.token'),
            data_get($payload, 'instance.hash'),
            data_get($payload, 'data.token'),
            data_get($payload, 'data.hash'),
            data_get($payload, 'data.apikey'),
            data_get($payload, 'data.apiKey')
        );
    }

    protected function storeMainInstanceApiKey(SettingsManager $settings, string $apiKey): void
    {
        if ($apiKey === '') {
            return;
        }

        $settings->set('whatsapp.instance_api_key', $apiKey);
        $settings->set('whatsapp.apikey', $apiKey);
        $settings->set('whatsapp.api_key', $apiKey);
    }

    protected function evolutionSubscribeEvents(array $events): array
    {
        $map = [
            'QRCODE_UPDATED' => 'QRCODE',
            'CONNECTION_UPDATE' => 'CONNECTION',
            'MESSAGES_UPSERT' => 'MESSAGE',
            'MESSAGES_UPDATE' => 'MESSAGE',
            'MESSAGES_DELETE' => 'MESSAGE',
            'SEND_MESSAGE' => 'SEND_MESSAGE',
            'CALL' => 'CALL',
            'CONTACTS_UPSERT' => 'CONTACT',
            'CONTACTS_UPDATE' => 'CONTACT',
            'PRESENCE_UPDATE' => 'PRESENCE',
            'CHATS_UPDATE' => 'CHAT_PRESENCE',
            'GROUPS_UPSERT' => 'GROUP',
            'GROUP_UPDATE' => 'GROUP',
            'GROUP_PARTICIPANTS_UPDATE' => 'GROUP',
            'APPLICATION_STARTUP' => 'APPLICATION_STARTUP',
            'NEW_JWT_TOKEN' => 'NEW_JWT_TOKEN',
        ];

        $subscribe = [];

        foreach ($events as $event) {
            $event = strtoupper(str_replace(['.', '-'], '_', (string) $event));
            $subscribe[] = $map[$event] ?? $event;
        }

        return array_values(array_unique(array_filter($subscribe))) ?: ['MESSAGE'];
    }

    public function diagnose(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => app(WhatsAppMetaClient::class)->diagnoseConnection(),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        try {
            $config = $this->whatsappRuntimeConfig();

            if (! $config['api_key'] || ! $config['base_url']) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config, 'Meta WhatsApp Cloud API não configurada.'),
                    'Meta WhatsApp Cloud API não configurada.',
                    false
                ), 422);
            }

            $normalized = app(WhatsAppMetaClient::class)->getNormalizedInstanceStatus();
            $normalized['_source'] = $this->normalizeMainSourceLabel($normalized['_source'] ?? $normalized['source'] ?? '');
            $normalized['source'] = $normalized['_source'];
            $this->persistMainInstanceStatus($config, $normalized, ['test' => $normalized]);

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $normalized['_api_error'] ?? null),
                'Teste de conexão realizado.',
                (bool) ($normalized['ok'] ?? true)
            ));
        } catch (\Throwable $e) {
            $config ??= $this->whatsappRuntimeConfig();

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $e->getMessage()),
                $e->getMessage(),
                false
            ), 422);
        }
    }

    public function connect(): JsonResponse
    {
        try {
            $config = $this->whatsappRuntimeConfig();

            if (! $config['api_key'] || ! $config['base_url']) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config, 'Token da instância não configurado.'),
                    'Token da instância não configurado.',
                    false
                ), 422);
            }

            $settings = app(SettingsManager::class);
            $prepared = $this->ensureMainRemoteInstanceReady($settings, $config);
            $config = $prepared['config'];
            $preparedQrCode = $prepared['qrcode'];

            $client = app(WhatsAppMetaClient::class);
            $before = $client->getNormalizedInstanceStatus();
            $directStatusBefore = $client->status();
            $directStateBefore = [];
            if (($directStatusBefore['ok'] ?? false) === true) {
                $directStateBefore = $this->normalizeMainConnectionState(
                    data_get($directStatusBefore, 'data.state'),
                    (array) data_get($directStatusBefore, 'data.raw', [])
                );

                if (($directStateBefore['state'] ?? 'close') !== 'open') {
                    $before = array_merge($before, [
                        'state' => 'connecting',
                        'status' => 'connecting',
                        'status_label' => 'Aguardando QR Code',
                        'connected' => false,
                        'source' => 'instance_status_direct',
                        '_source' => 'instance_status_direct',
                    ]);
                }
            }
            $directConfirmsOpen = (($directStatusBefore['ok'] ?? false) === true)
                && (($directStateBefore['state'] ?? 'close') === 'open');
            if (! $directConfirmsOpen && ($before['connected'] ?? false)) {
                $before = array_merge($before, [
                    'state' => 'connecting',
                    'status' => 'connecting',
                    'status_label' => 'Aguardando QR Code',
                    'connected' => false,
                    'source' => 'instance_status_untrusted',
                    '_source' => 'instance_status_untrusted',
                ]);
            }
            $cachedBefore = $this->mainInstanceStatusPayload($config, $before['_api_error'] ?? null);

            $allowConnectedPreserve = ! (($directStatusBefore['ok'] ?? false) === true && (($directStateBefore['state'] ?? 'close') !== 'open'));
            if ($allowConnectedPreserve && $this->shouldPreserveMainConnectedStatus($cachedBefore, $before)) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $cachedBefore,
                    'Instancia ja conectada. Status remoto instavel foi ignorado.',
                    true
                ));
            }

            $this->persistMainInstanceStatus($config, $before, ['status_before_connect' => $before]);

            if ($directConfirmsOpen && ($before['connected'] ?? false)) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config),
                    'Instância já conectada.',
                    true
                ));
            }

            $connectNumber = $this->mainInstanceConnectionNumber($config, $before);
            $duplicate = $this->connectedNumberInAnotherInstance($client, $config, $connectNumber);

            if ($duplicate !== null) {
                $message = 'Este número já está conectado na instância '.$duplicate['instance'].'.';

                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config, $message),
                    $message,
                    false
                ), 422);
            }

            $connect = $client->connect($connectNumber, $this->mainConnectPayload(app(SettingsManager::class), $config));
            $connectPayload = $connect['data'] ?? [];
            $connectMessage = $connect['error'] ?? null;
            $qrCode = $this->extractQrCode($connectPayload) ?: $preparedQrCode;

            if (! ($connect['success'] ?? false)) {
                $fallbackConnect = $client->connect($connectNumber);
                $fallbackPayload = $fallbackConnect['data'] ?? [];
                $fallbackQrCode = $this->extractQrCode($fallbackPayload);

                if (($fallbackConnect['success'] ?? false) || $fallbackQrCode) {
                    $connect = $fallbackConnect;
                    $connectPayload = $fallbackPayload;
                    $connectMessage = $fallbackConnect['error'] ?? $connectMessage;
                    $qrCode = $fallbackQrCode ?: $qrCode;
                }
            }

            $after = $client->getNormalizedInstanceStatus();
            $directStatus = $client->status();
            if (($directStatus['ok'] ?? false) === true) {
                $directState = $this->normalizeMainConnectionState(
                    data_get($directStatus, 'data.state'),
                    (array) data_get($directStatus, 'data.raw', [])
                );

                // Pos-reinicio: o estado direto da instancia tem precedencia para decidir QR.
                if (($directState['state'] ?? 'close') !== 'open') {
                    $after = array_merge($after, [
                        'state' => 'connecting',
                        'status' => 'connecting',
                        'status_label' => 'Aguardando QR Code',
                        'connected' => false,
                        'source' => 'instance_status_direct',
                        '_source' => 'instance_status_direct',
                    ]);
                } else {
                    $after = array_merge($after, [
                        'state' => 'open',
                        'status' => 'connected',
                        'status_label' => 'Conectado',
                        'connected' => true,
                        'qrcode' => null,
                    ]);
                }
            }
            $persistPayload = $after;

            if (! ($after['connected'] ?? false) && ! $qrCode) {
                ['status' => $after, 'qrcode' => $qrCode] = $this->pollMainInstanceQrCode($client, $after, 8, 1000, true);
                $persistPayload = $after;
            }

            if ($qrCode && ! ($after['connected'] ?? false)) {
                $persistPayload['state'] = 'connecting';
                $persistPayload['status'] = 'connecting';
                $persistPayload['status_label'] = 'Aguardando QR Code';
                $persistPayload['qrcode'] = $qrCode;
                $persistPayload['source'] = 'instance_qr';
                $persistPayload['_source'] = 'instance_qr';
            }

            $this->persistMainInstanceStatus($config, $persistPayload, [
                'connect' => $connectPayload,
                'status_after_connect' => $after,
            ], (bool) $qrCode);

            $responsePayload = $this->mainInstanceStatusPayload($config, $connectMessage);
            $responsePayload['qrcode'] = ($after['connected'] ?? false) ? null : $qrCode;

            if ($after['connected'] ?? false) {
                return response()->json($this->mainInstanceAjaxPayload($responsePayload, 'Instância conectada com sucesso.', true));
            }

            if ($qrCode) {
                return response()->json($this->mainInstanceAjaxPayload($responsePayload, 'QR Code gerado. Escaneie para conectar.', true));
            }

            if (($connect['success'] ?? false)) {
                $responsePayload['state'] = 'connecting';
                $responsePayload['status'] = 'connecting';
                $responsePayload['status_label'] = 'Aguardando QR Code';
                $responsePayload['connected'] = false;

                return response()->json($this->mainInstanceAjaxPayload(
                    $responsePayload,
                    'Solicitação enviada. Aguarde alguns segundos.',
                    true
                ));
            }

            $message = $connectMessage ?: 'Não foi possível iniciar a conexão da instância.';

            return response()->json($this->mainInstanceAjaxPayload($responsePayload, $message, false), 422);
        } catch (\Throwable $e) {
            $config ??= $this->whatsappRuntimeConfig();

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $e->getMessage()),
                $e->getMessage(),
                false
            ), 422);
        }
    }

    public function disconnect(): JsonResponse
    {
        try {
            $config = $this->whatsappRuntimeConfig();

            if (! $config['api_key'] || ! $config['base_url']) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config, 'Meta WhatsApp Cloud API não configurada.'),
                    'Meta WhatsApp Cloud API não configurada.',
                    false
                ), 422);
            }

            $client = app(WhatsAppMetaClient::class);
            $result = $client->disconnect();
            $after = $client->getNormalizedInstanceStatus();

            if (! ($result['success'] ?? false) && ! array_key_exists('state', $after)) {
                $after['state'] = 'close';
                $after['status'] = 'disconnected';
                $after['status_label'] = 'Desconectado';
            }

            $effectiveSuccess = (bool) ($result['success'] ?? false);
            if (! $effectiveSuccess && (($after['connected'] ?? false) === false)) {
                $effectiveSuccess = true;
            }

            $this->persistMainInstanceStatus($config, $after, [
                'disconnect' => $result['data'] ?? [],
                'status_after_disconnect' => $after,
            ]);

            $message = $effectiveSuccess
                ? (($result['success'] ?? false) ? 'Instância desconectada.' : 'Instância já estava desconectada.')
                : ($result['error'] ?? 'Erro ao desconectar instância.');
            $statusCode = $effectiveSuccess ? 200 : 422;

            return response()->json(
                $this->mainInstanceAjaxPayload($this->mainInstanceStatusPayload($config, $message), $message, $effectiveSuccess),
                $statusCode
            );
        } catch (\Throwable $e) {
            $config ??= $this->whatsappRuntimeConfig();

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $e->getMessage()),
                $e->getMessage(),
                false
            ), 422);
        }
    }

    /** Desconecta uma instancia de tenant especifica via InstanceManager. */
    public function disconnectTenantInstance(int $id, InstanceManager $manager): JsonResponse
    {
        try {
            $instance = TenantInstance::findOrFail($id);
            $result = $manager->disconnectInstance($instance);
            if ($result['ok']) {
                return response()->json(['ok' => true]);
            }
            return response()->json(['ok' => false, 'message' => $result['error'] ?? 'Erro ao desconectar instância.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Salva configuracao da Evolution em uma instancia de tenant (admin). */
    public function saveInstanceConfig(Request $request, int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::findOrFail($id);

        $data = $request->validate([
            'api_base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|min:5|max:1000',
            'webhook_enabled' => 'required|boolean',
            'webhook_by_events' => 'required|boolean',
            'webhook_base64' => 'required|boolean',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|max:80',
            'event_producer_rabbitmq' => 'nullable|in:default,enabled,disabled',
            'event_producer_websocket' => 'nullable|in:default,enabled,disabled',
            'event_producer_nats' => 'nullable|in:default,enabled,disabled',
            'reject_call' => 'required|boolean',
            'msg_call' => 'nullable|string|max:500',
            'groups_ignore' => 'required|boolean',
            'always_online' => 'required|boolean',
            'read_messages' => 'required|boolean',
            'read_status' => 'required|boolean',
            'sync_full_history' => 'required|boolean',
        ]);

        try {
            $result = $mgr->saveEvolutionConfig($instance, $data);
            $instance->refresh();

            return response()->json([
                'ok' => true,
                'applied' => (bool) ($result['ok'] ?? true),
                'webhook_url' => $result['webhook_url'] ?? $instance->secure_webhook_url,
                'event_producers' => $result['event_producers'] ?? data_get($instance->meta ?? [], 'evolution_event_producers', []),
                'settings' => $result['settings'] ?? [],
                'instance' => $instance,
                'remote' => $result['remote'] ?? [],
                'errors' => $result['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function restart(): JsonResponse
    {
        try {
            $config = $this->whatsappRuntimeConfig();

            if (! $config['api_key'] || ! $config['base_url']) {
                return response()->json($this->mainInstanceAjaxPayload(
                    $this->mainInstanceStatusPayload($config, 'Meta WhatsApp Cloud API não configurada.'),
                    'Meta WhatsApp Cloud API não configurada.',
                    false
                ), 422);
            }

            $settings = app(SettingsManager::class);
            $prepared = $this->ensureMainRemoteInstanceReady($settings, $config);
            $config = $prepared['config'];
            $qrCode = $prepared['qrcode'];

            $client = app(WhatsAppMetaClient::class);
            $result = $client->reconnect();
            $restartError = mb_strtolower((string) ($result['error'] ?? ''));
            $softDisconnected = str_contains($restartError, 'client disconnected');
            if (! ($result['success'] ?? false) && $softDisconnected) {
                $result['success'] = true;
            }
            $after = $client->getNormalizedInstanceStatus();
            $directStatus = $client->status();
            if (($directStatus['ok'] ?? false) === true) {
                $directState = $this->normalizeMainConnectionState(
                    data_get($directStatus, 'data.state'),
                    (array) data_get($directStatus, 'data.raw', [])
                );

                if (($directState['state'] ?? 'close') !== 'open') {
                    $after = array_merge($after, [
                        'state' => 'connecting',
                        'status' => 'connecting',
                        'status_label' => 'Aguardando QR Code',
                        'connected' => false,
                        'source' => 'instance_status_direct',
                        '_source' => 'instance_status_direct',
                    ]);
                } else {
                    $after = array_merge($after, [
                        'state' => 'open',
                        'status' => 'connected',
                        'status_label' => 'Conectado',
                        'connected' => true,
                        'qrcode' => null,
                    ]);
                }
            }

            if (($result['success'] ?? false) && ! ($after['connected'] ?? false)) {
                $after['state'] = 'connecting';
                $after['status'] = 'connecting';
                $after['status_label'] = 'Aguardando QR Code';

                try {
                    $connectNumber = $this->mainInstanceConnectionNumber($config, $after);
                    $connectPayload = $this->mainConnectPayload($settings, $config);
                    $connectResult = $client->connect($connectNumber, $connectPayload);
                    $qrCode = $this->extractQrCode($connectResult['data'] ?? []) ?: $qrCode;

                    if (! ($connectResult['success'] ?? false) && ! $qrCode) {
                        $connectFallback = $client->connect($connectNumber);
                        $qrCode = $this->extractQrCode($connectFallback['data'] ?? []) ?: $qrCode;
                    }
                } catch (\Throwable) {
                    // polling continua abaixo
                }

                if (! $qrCode) {
                    ['status' => $after, 'qrcode' => $qrCode] = $this->pollMainInstanceQrCode($client, $after, 10, 1200, true);
                }

                if (! $qrCode && ! ($after['connected'] ?? false)) {
                    ['status' => $after, 'qrcode' => $qrCode] = $this->pollMainInstanceQrCode($client, $after, 4, 1200, false);
                }

                if ($qrCode && ! ($after['connected'] ?? false)) {
                    $after['state'] = 'connecting';
                    $after['status'] = 'connecting';
                    $after['status_label'] = 'Aguardando QR Code';
                    $after['qrcode'] = $qrCode;
                    $after['source'] = 'instance_restart_qr';
                    $after['_source'] = 'instance_restart_qr';
                }
            }

            $effectiveSuccess = (bool) ($result['success'] ?? false);
            if (! $effectiveSuccess && in_array(($after['state'] ?? ''), ['open', 'connecting'], true)) {
                $effectiveSuccess = true;
            }

            $this->persistMainInstanceStatus($config, $after, [
                'restart' => $result['data'] ?? [],
                'status_after_restart' => $after,
            ], (bool) ($qrCode ?? false));

            $message = $effectiveSuccess
                ? (($result['success'] ?? false) ? 'Instância reiniciada.' : 'Solicitação de reinício enviada.')
                : ($result['error'] ?? 'Erro ao reiniciar instância.');
            $statusCode = $effectiveSuccess ? 200 : 422;

            $responsePayload = $this->mainInstanceStatusPayload($config, $message);
            $responsePayload['qrcode'] = ($after['connected'] ?? false) ? null : ($qrCode ?: data_get($responsePayload, 'qrcode'));

            if (($after['connected'] ?? false) === true) {
                $message = 'InstÃ¢ncia conectada apÃ³s reinÃ­cio.';
            } elseif ($responsePayload['qrcode']) {
                $message = 'InstÃ¢ncia reiniciada. QR Code pronto para leitura.';
            }

            return response()->json($this->mainInstanceAjaxPayload($responsePayload, $message, $effectiveSuccess), $statusCode);
        } catch (\Throwable $e) {
            $config ??= $this->whatsappRuntimeConfig();

            return response()->json($this->mainInstanceAjaxPayload(
                $this->mainInstanceStatusPayload($config, $e->getMessage()),
                $e->getMessage(),
                false
            ), 422);
        }
    }

    public function sendTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to'      => 'required|string|min:10',
            'message' => 'required|string|min:1',
        ]);

        try {
            $safety = app(WhatsAppSafetyService::class);
            $inspection = $safety->inspect(null, $data['to'], $data['message'], [
                'origin' => 'admin_send_test',
                'is_manual' => true,
                'campaign' => null,
            ]);

            if (! $inspection['allowed']) {
                $safety->recordOutcome($inspection, [
                    'to' => $data['to'],
                    'meta' => ['origin' => 'admin_send_test'],
                ]);

                return response()->json(['ok' => false, 'message' => 'Envio bloqueado: '.$inspection['reason']], 422);
            }

            $client = app(WhatsAppMetaClient::class);
            $id = $client->sendText($data['to'], $data['message'], array_merge($inspection, [
                'origin' => 'admin_send_test',
                'is_manual' => true,
                'skip_safety' => true,
                'delay_seconds' => 0,
            ]));
            return response()->json(['ok' => (bool) $id, 'evolution_id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function sendManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to'          => 'required|string|min:10',
            'message'     => 'required|string|min:1',
            'customer_id' => 'nullable|integer',
            'template_id' => 'required|integer',
        ]);

        $rawTo = $data['to'];
        $cleanTo = preg_replace('/\D+/', '', $rawTo);
        if (strlen($cleanTo) < 10) {
            return response()->json(['ok' => false, 'message' => 'O número do destinatário deve conter pelo menos 10 dígitos (DDD + número).'], 422);
        }

        try {
            $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
            $template = WhatsAppTemplate::where('active', true)->find($data['template_id']);

            if (! $template) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Nenhuma mensagem pode ser enviada sem que tenha um template válido ativo selecionado.',
                ], 422);
            }

            $context = [
                'origin' => 'admin_manual_send',
                'is_manual' => true,
                'campaign' => null,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'template_id' => $template?->id,
                'template_slug' => $template?->slug,
            ];

            $safety = app(WhatsAppSafetyService::class);
            $inspection = $safety->inspect(null, $cleanTo, $data['message'], $context);

            if (! $inspection['allowed']) {
                $safety->recordOutcome($inspection, [
                    'to' => $cleanTo,
                    'meta' => $context,
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => 'Envio bloqueado pelas regras anti-ban: ' . $inspection['reason'],
                    'blocked' => true,
                    'reason' => $inspection['reason'],
                ], 422);
            }

            // Seleciona instância ativa de preferência ou Evolution padrão
            $instance = TenantInstance::where('status', 'connected')->orderByDesc('is_default')->first();
            if ($instance) {
                $id = app(InstanceManager::class)->sendText($instance, $cleanTo, $data['message'], array_merge($context, $inspection, [
                    'skip_safety' => true,
                    'is_manual' => true,
                    'delay_seconds' => 0,
                ]));
            } else {
                $client = app(WhatsAppMetaClient::class);
                $id = $client->sendText($cleanTo, $data['message'], array_merge($context, $inspection, [
                    'skip_safety' => true,
                    'is_manual' => true,
                    'delay_seconds' => 0,
                ]));
            }

            if (! $id) {
                return response()->json(['ok' => false, 'message' => 'Não foi possível enviar a mensagem. Verifique se a instância do WhatsApp está conectada.'], 500);
            }

            // Se for cobrança ou template com Pix e a opção de enviar imagem estiver ativa, envia a imagem do QR Code
            $sendQrCode = (bool) (config('whatsapp.send_pix_qrcode_image', true) || config('whatsapp.send_separate_media', false));
            if ($sendQrCode && $customer) {
                $invoice = Invoice::query()
                    ->where('customer_id', $customer->id)
                    ->orderByRaw("CASE WHEN status IN ('pending', 'overdue', 'sent') THEN 0 ELSE 1 END")
                    ->orderByDesc('id')
                    ->first();

                if ($invoice) {
                    $meta = is_array($invoice->meta) ? $invoice->meta : (json_decode($invoice->meta ?? '[]', true) ?: []);
                    $pixCopy = $meta['pix_copia_cola'] ?? $meta['pix_payload'] ?? $meta['pix_qr_code'] ?? '';
                    $qrImage = $meta['pix_qrcode'] ?? $meta['qrcode_url'] ?? $meta['qr_code_url'] ?? '';

                    $isPixContext = ($template->category === 'pix')
                        || str_contains($template->slug, 'pix')
                        || ($pixCopy !== '' && str_contains($data['message'], $pixCopy));

                    if ($isPixContext) {
                        if ($qrImage === '' && $pixCopy !== '') {
                            $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data='.rawurlencode($pixCopy);
                        }

                        if ($qrImage !== '') {
                            $qrCaption = 'QR Code PIX - Fatura ' . ($invoice->number ?: $invoice->id);
                            try {
                                usleep(300000);
                                if ($instance) {
                                    app(InstanceManager::class)->sendImage($instance, $cleanTo, $qrImage, $qrCaption, array_merge($context, ['media_type' => 'image', 'origin' => 'admin_manual_qrcode']));
                                } else {
                                    app(WhatsAppMetaClient::class)->sendImage($cleanTo, $qrImage, $qrCaption, array_merge($context, ['media_type' => 'image', 'origin' => 'admin_manual_qrcode']));
                                }
                            } catch (\Throwable $e) {
                                Log::warning('Erro ao enviar imagem do QR Code em envio manual admin: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'evolution_id' => $id,
                'to' => $cleanTo,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar mensagem manual WhatsApp: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()], 422);
        }
    }

    public function customerSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = Customer::query()->where('active', true);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('whatsapp', 'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%")
                   ->orWhere('document', 'like', "%{$q}%");
            });
        }

        $customers = $query->orderBy('name')->limit(20)->get();

        $items = $customers->map(function (Customer $customer) {
            // Busca última fatura pendente/aberta ou a última fatura registrada
            $invoice = Invoice::query()
                ->where('customer_id', $customer->id)
                ->orderByRaw("CASE WHEN status IN ('pending', 'overdue', 'sent') THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->first();

            $due = $invoice?->due_date;
            $daysLate = 0;
            if ($due) {
                try {
                    $diff = Carbon::parse($due)->setTimezone('America/Sao_Paulo')->startOfDay()->diffInDays(now('America/Sao_Paulo')->startOfDay(), false);
                    $daysLate = max(0, -$diff);
                } catch (\Throwable) {
                    $daysLate = 0;
                }
            }

            $meta = is_array($invoice?->meta) ? $invoice->meta : (json_decode($invoice?->meta ?? '[]', true) ?: []);
            $pixCopiaCola = $meta['pix_copia_cola'] ?? $meta['pix_payload'] ?? $meta['pix_qr_code'] ?? '';
            $linhaDigitavel = $meta['linha_digitavel'] ?? $meta['bank_slip_digitable'] ?? '';
            $boletoUrl = $meta['boleto_url'] ?? $meta['bank_slip_url'] ?? '';

            $whmcsId = $customer->meta['whmcs_id'] ?? $customer->meta['whmcs_client_id'] ?? '';
            $safePaymentUrl = $invoice ? url("/fatura/{$invoice->webhook_token}") : '';

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => trim(explode(' ', $customer->name)[0] ?? ''),
                'whatsapp' => $customer->whatsapp ?: $customer->phone ?: '',
                'formatted_whatsapp' => $customer->formatted_whatsapp ?: ($customer->formatted_phone ?: ''),
                'email' => $customer->email,
                'document' => $customer->formatted_document ?: $customer->document,
                'has_invoice' => (bool) $invoice,
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'number' => $invoice->number ?: (string) $invoice->id,
                    'amount' => (float) $invoice->amount,
                    'formatted_amount' => Format::money($invoice->amount),
                    'due_date' => $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '',
                    'status' => $invoice->status,
                    'status_label' => match($invoice->status) {
                        'paid' => 'Paga',
                        'pending', 'sent' => 'Pendente',
                        'overdue' => 'Vencida',
                        'cancelled' => 'Cancelada',
                        default => ucfirst($invoice->status ?? '')
                    },
                    'payment_url' => $safePaymentUrl,
                    'pix_copia_cola' => $pixCopiaCola,
                    'linha_digitavel' => $linhaDigitavel,
                    'boleto_url' => $boletoUrl,
                    'description' => $invoice->description ?: 'Serviços Prestados',
                ] : null,
                'variables' => [
                    '{nome}' => $customer->name,
                    '{primeiro_nome}' => trim(explode(' ', $customer->name)[0] ?? ''),
                    '{whatsapp}' => $customer->whatsapp ?: $customer->phone ?: '',
                    '{empresa}' => config('app.name'),
                    '{whmcs_cliente_id}' => (string) $whmcsId,
                    '{valor}' => $invoice ? Format::money($invoice->amount) : 'R$ 0,00',
                    '{valor_original}' => $invoice ? Format::money($invoice->amount) : 'R$ 0,00',
                    '{valor_atualizado}' => $invoice ? Format::money($invoice->amount) : 'R$ 0,00',
                    '{vencimento}' => $invoice?->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '',
                    '{dias_atraso}' => (string) $daysLate,
                    '{numero_fatura}' => $invoice ? ($invoice->number ?: (string) $invoice->id) : '',
                    '{descricao}' => $invoice?->description ?: 'Cobrança de Serviços',
                    '{link_pagamento}' => $safePaymentUrl,
                    '{pix_copia_cola}' => (string) $pixCopiaCola,
                    '{linha_digitavel}' => (string) $linhaDigitavel,
                    '{boleto_url}' => (string) $boletoUrl,
                ],
            ];
        });

        return response()->json(['success' => true, 'customers' => $items]);
    }

    public function renderTemplate(Request $request): JsonResponse
    {
        $templateId = (int) $request->input('template_id');
        $customerId = (int) $request->input('customer_id');

        $template = WhatsAppTemplate::find($templateId);
        if (! $template) {
            return response()->json(['success' => false, 'message' => 'Template não encontrado.'], 404);
        }

        $rawText = $template->content_text ?: strip_tags($template->content_html);

        if (! $customerId) {
            return response()->json([
                'success' => true,
                'template_name' => $template->name,
                'rendered_text' => $rawText,
            ]);
        }

        $customer = Customer::find($customerId);
        if (! $customer) {
            return response()->json([
                'success' => true,
                'template_name' => $template->name,
                'rendered_text' => $rawText,
            ]);
        }

        $invoice = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw("CASE WHEN status IN ('pending', 'overdue', 'sent') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        $meta = is_array($invoice?->meta) ? $invoice->meta : (json_decode($invoice?->meta ?? '[]', true) ?: []);
        $pixCopy = $meta['pix_copia_cola'] ?? $meta['pix_payload'] ?? $meta['pix_qr_code'] ?? '';
        $linha = $meta['linha_digitavel'] ?? $meta['bank_slip_digitable'] ?? '';
        $boleto = $meta['boleto_url'] ?? $meta['bank_slip_url'] ?? '';

            $safePaymentUrl = $invoice ? url("/fatura/{$invoice->webhook_token}") : '';

            $context = [
                'nome' => $customer->name,
                'customer_name' => $customer->name,
                'primeiro_nome' => trim(explode(' ', $customer->name)[0] ?? ''),
                'email' => $customer->email,
                'whatsapp' => $customer->whatsapp ?: $customer->phone ?: '',
                'valor' => $invoice?->amount ?? 0,
                'amount' => $invoice?->amount ?? 0,
                'valor_original' => $invoice?->amount ?? 0,
                'valor_atualizado' => $invoice?->amount ?? 0,
                'vencimento' => $invoice?->due_date,
                'due_date' => $invoice?->due_date,
                'numero_fatura' => $invoice ? ($invoice->number ?: (string) $invoice->id) : '',
                'invoice_number' => $invoice ? ($invoice->number ?: (string) $invoice->id) : '',
                'descricao' => $invoice?->description ?: '',
                'empresa' => config('app.name'),
                'link_pagamento' => $safePaymentUrl,
                'payment_url' => $safePaymentUrl,
            'pix_copia_cola' => $pixCopy,
            'linha_digitavel' => $linha,
            'boleto_url' => $boleto,
            'whmcs_cliente_id' => $customer->meta['whmcs_id'] ?? '',
        ];

        $rendered = app(TemplateRenderer::class)->render($template, $context);

        return response()->json([
            'success' => true,
            'template_name' => $template->name,
            'rendered_text' => $rendered,
        ]);
    }

    public function resend(int $id): JsonResponse
    {
        $msg = DB::table('whatsapp_messages')->find($id);
        if (!$msg) {
            return response()->json(['message' => 'Mensagem nao encontrada.'], 404);
        }

        try {
            $instance = ! empty($msg->tenant_instance_id) ? TenantInstance::find($msg->tenant_instance_id) : null;
            $context = [
                'tenant_id' => $instance?->tenant_id,
                'tenant_instance_id' => $instance?->id,
                'origin' => 'admin_resend',
                'campaign' => 'admin_resend',
            ];

            if ($instance) {
                $newId = app(InstanceManager::class)->sendText($instance, $msg->to, $msg->message, $context);
            } else {
                $safety = app(WhatsAppSafetyService::class);
                $inspection = $safety->inspect(null, $msg->to, $msg->message, $context);
                if (! $inspection['allowed']) {
                    $safety->recordOutcome($inspection, [
                        'to' => $msg->to,
                        'meta' => ['origin' => 'admin_resend'],
                    ]);

                    return response()->json(['ok' => false, 'message' => 'Reenvio bloqueado: '.$inspection['reason']], 422);
                }

                $client = app(WhatsAppMetaClient::class);
                $newId = $client->sendText($msg->to, $msg->message, array_merge($context, $inspection));
            }

            return response()->json(['ok' => (bool) $newId, 'evolution_id' => $newId]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function messageDetail(int $id): JsonResponse
    {
        try {
            $msg = DB::table('whatsapp_messages')->find($id);
            if (! $msg) {
                return response()->json(['ok' => false, 'message' => 'Mensagem não encontrada.'], 404);
            }

            $response = null;
            if (! empty($msg->response)) {
                $response = json_decode($msg->response, true) ?: $msg->response;
            }

            $instance = null;
            if (! empty($msg->tenant_instance_id)) {
                $instance = TenantInstance::with('tenant')->find($msg->tenant_instance_id);
            }

            $dispatch = null;
            $customer = null;
            $invoice = null;
            if (! empty($msg->dispatch_id) && Schema::hasTable('billing_dispatches')) {
                $dispatch = DB::table('billing_dispatches')->find($msg->dispatch_id);
                if ($dispatch && ! empty($dispatch->invoice_id) && Schema::hasTable('invoices')) {
                    $invoice = DB::table('invoices')->find($dispatch->invoice_id);
                    if ($invoice && ! empty($invoice->customer_id) && Schema::hasTable('customers')) {
                        $customer = DB::table('customers')->find($invoice->customer_id);
                    }
                }
            }

            if (! $customer && ! empty($msg->to) && Schema::hasTable('customers')) {
                $cleanPhone = preg_replace('/\D+/', '', $msg->to);
                $digits = substr($cleanPhone, -8);
                if (! empty($digits)) {
                    $customer = DB::table('customers')
                        ->where('whatsapp', 'like', "%{$digits}%")
                        ->orWhere('phone', 'like', "%{$digits}%")
                        ->first();
                }
            }

            $failureReason = null;
            if ($msg->status === 'failed') {
                if (is_array($response)) {
                    $failureReason = data_get($response, 'response.message')
                        ?: (data_get($response, 'message')
                        ?: (data_get($response, 'error')
                        ?: (data_get($response, 'reason')
                        ?: (data_get($response, 'statusText')
                        ?: null))));
                } elseif (is_string($response)) {
                    $failureReason = $response;
                }

                if (! $failureReason && ! empty($dispatch?->last_error)) {
                    $failureReason = $dispatch->last_error;
                }

                if (! $failureReason) {
                    $failureReason = 'Falha operacional na entrega: instância desconectada, timeout da API ou destinatário inválido/sem WhatsApp.';
                }
            }

            $createdAt = ! empty($msg->created_at) ? \Carbon\Carbon::parse($msg->created_at) : null;
            $deliveredAt = ! empty($msg->delivered_at) ? \Carbon\Carbon::parse($msg->delivered_at) : null;
            $readAt = ! empty($msg->read_at) ? \Carbon\Carbon::parse($msg->read_at) : null;

            $rawTo = preg_replace('/\D+/', '', (string) $msg->to);
            $formattedTo = $msg->to;
            if (strlen($rawTo) === 13 && str_starts_with($rawTo, '55')) {
                $formattedTo = '+55 (' . substr($rawTo, 2, 2) . ') ' . substr($rawTo, 4, 5) . '-' . substr($rawTo, 9);
            } elseif (strlen($rawTo) === 12 && str_starts_with($rawTo, '55')) {
                $formattedTo = '+55 (' . substr($rawTo, 2, 2) . ') ' . substr($rawTo, 4, 4) . '-' . substr($rawTo, 8);
            } elseif (strlen($rawTo) === 11) {
                $formattedTo = '(' . substr($rawTo, 0, 2) . ') ' . substr($rawTo, 2, 5) . '-' . substr($rawTo, 7);
            }

            return response()->json([
                'ok' => true,
                'message' => [
                    'id' => $msg->id,
                    'to' => $msg->to,
                    'formatted_to' => $formattedTo,
                    'direction' => $msg->direction,
                    'status' => $msg->status,
                    'meta_message_id' => $msg->meta_message_id ?? $msg->evolution_id ?? 'Sem ID',
                    'evolution_id' => $msg->meta_message_id ?? $msg->evolution_id ?? 'Sem ID',
                    'content' => $msg->message,
                    'created_at' => $createdAt?->format('d/m/Y H:i:s'),
                    'created_at_time' => $createdAt?->format('H:i'),
                    'created_at_relative' => $createdAt?->diffForHumans(),
                    'delivered_at' => $deliveredAt?->format('d/m/Y H:i:s'),
                    'read_at' => $readAt?->format('d/m/Y H:i:s'),
                    'response_raw' => $msg->response,
                    'response' => $response,
                    'failure_reason' => $failureReason,
                    'instance_name' => $instance?->name,
                    'tenant_name' => $instance?->tenant?->company_name ?? $instance?->tenant?->name,
                    'customer_name' => $customer?->name,
                    'customer_document' => $customer?->document ?? null,
                    'invoice_number' => $invoice?->number ?? null,
                    'invoice_amount' => $invoice ? number_format((float) $invoice->amount, 2, ',', '.') : null,
                    'media_url' => $msg->media_url ?? null,
                    'media_type' => $msg->media_type ?? (isset($response['type']) ? $response['type'] : null),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar detalhes da mensagem: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok' => false,
                'message' => 'Erro interno ao consultar detalhes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveSafetyConfig(Request $request, SettingsManager $settings): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'timezone' => 'required|string|max:80',
            'business_hours_enabled' => 'nullable|boolean',
            'business_hours_start' => 'required|string|max:5',
            'business_hours_end' => 'required|string|max:5',
            'limit_per_minute' => 'required|integer|min:1|max:60',
            'limit_per_hour' => 'required|integer|min:1|max:2000',
            'limit_per_day' => 'required|integer|min:1|max:20000',
            'text_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'image_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'audio_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'video_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'document_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'buttons_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'list_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'poll_limit_per_hour' => 'nullable|integer|min:0|max:2000',
            'recipient_cooldown_minutes' => 'required|integer|min:0|max:1440',
            'message_cooldown_minutes' => 'required|integer|min:0|max:1440',
            'campaign_cooldown_minutes' => 'required|integer|min:0|max:1440',
            'human_delay_enabled' => 'nullable|boolean',
            'human_delay_min_seconds' => 'required|integer|min:0|max:300',
            'human_delay_max_seconds' => 'required|integer|min:0|max:600',
            'send_delay_min_ms' => 'nullable|integer|min:0|max:20000',
            'send_delay_max_ms' => 'nullable|integer|min:0|max:30000',
            'evolution_delay_min_ms' => 'nullable|integer|min:0|max:20000',
            'evolution_delay_max_ms' => 'nullable|integer|min:0|max:30000',
            'release_when_limited_seconds' => 'required|integer|min:60|max:86400',
            'block_groups' => 'nullable|boolean',
            'minimum_message_chars' => 'required|integer|min:1|max:100',
            'stop_keywords' => 'nullable|string|max:500',
            'warmup_enabled' => 'nullable|boolean',
            'warmup_limit_days_1_3' => 'required|integer|min:1|max:1000',
            'warmup_limit_days_4_7' => 'required|integer|min:1|max:5000',
            'warmup_limit_days_8_15' => 'required|integer|min:1|max:10000',
            'risk_medium' => 'required|integer|min:1|max:100',
            'risk_high' => 'required|integer|min:1|max:100',
            'risk_critical' => 'required|integer|min:1|max:100',
            'risk_block_above' => 'required|integer|min:1|max:100',
            'webhook_secret_required' => 'nullable|boolean',
            'webhook_max_age_seconds' => 'required|integer|min:30|max:3600',
            'api_rate_limit_per_minute' => 'required|integer|min:5|max:1000',
            'tenant_allow_custom_limits' => 'nullable|boolean',
            'tenant_max_limit_per_day' => 'nullable|integer|min:20|max:5000',
            'tenant_max_limit_per_minute' => 'nullable|integer|min:1|max:30',
            'tenant_max_limit_per_hour' => 'nullable|integer|min:5|max:500',
            'send_pix_qrcode_image' => 'nullable|boolean',
        ]);

        $boolKeys = [
            'enabled' => 'enabled',
            'business_hours_enabled' => 'business_hours.enabled',
            'human_delay_enabled' => 'human_delay.enabled',
            'block_groups' => 'block_groups',
            'warmup_enabled' => 'warmup.enabled',
            'webhook_secret_required' => 'security.webhook_secret_required',
            'tenant_allow_custom_limits' => 'tenant_settings.allow_custom_limits',
        ];

        foreach ($boolKeys as $input => $key) {
            $settings->set('whatsapp_anti_ban.'.$key, (bool) ($data[$input] ?? false), 'bool');
        }

        $sendPixQrcode = (bool) ($data['send_pix_qrcode_image'] ?? false);
        $settings->set('whatsapp.send_pix_qrcode_image', $sendPixQrcode, 'bool');
        config(['whatsapp.send_pix_qrcode_image' => $sendPixQrcode]);

        $typeLimits = [
            'text' => 'text_limit_per_hour',
            'image' => 'image_limit_per_hour',
            'audio' => 'audio_limit_per_hour',
            'video' => 'video_limit_per_hour',
            'document' => 'document_limit_per_hour',
            'buttons' => 'buttons_limit_per_hour',
            'list' => 'list_limit_per_hour',
            'poll' => 'poll_limit_per_hour',
        ];

        foreach ($typeLimits as $type => $input) {
            if (array_key_exists($input, $data)) {
                $settings->set("whatsapp_anti_ban.type_limits.{$type}", (int) $data[$input], 'int');
            }
        }

        $map = [
            'timezone' => ['timezone', 'string'],
            'business_hours_start' => ['business_hours.start', 'string'],
            'business_hours_end' => ['business_hours.end', 'string'],
            'limit_per_minute' => ['limits.per_minute', 'int'],
            'limit_per_hour' => ['limits.per_hour', 'int'],
            'limit_per_day' => ['limits.per_day', 'int'],
            'recipient_cooldown_minutes' => ['recipient_cooldown_minutes', 'int'],
            'message_cooldown_minutes' => ['cooldowns.message_minutes', 'int'],
            'campaign_cooldown_minutes' => ['cooldowns.campaign_minutes', 'int'],
            'human_delay_min_seconds' => ['human_delay.min_seconds', 'int'],
            'human_delay_max_seconds' => ['human_delay.max_seconds', 'int'],
            'send_delay_min_ms' => ['send_delay_ms.min', 'int'],
            'send_delay_max_ms' => ['send_delay_ms.max', 'int'],
            'evolution_delay_min_ms' => ['evolution_delay_ms.min', 'int'],
            'evolution_delay_max_ms' => ['evolution_delay_ms.max', 'int'],
            'release_when_limited_seconds' => ['release_when_limited_seconds', 'int'],
            'minimum_message_chars' => ['minimum_message_chars', 'int'],
            'risk_medium' => ['risk_thresholds.medium', 'int'],
            'risk_high' => ['risk_thresholds.high', 'int'],
            'risk_critical' => ['risk_thresholds.critical', 'int'],
            'risk_block_above' => ['risk_thresholds.block_above', 'int'],
            'webhook_max_age_seconds' => ['security.webhook_max_age_seconds', 'int'],
            'api_rate_limit_per_minute' => ['security.api_rate_limit_per_minute', 'int'],
            'tenant_max_limit_per_day' => ['tenant_settings.max_limit_per_day', 'int'],
            'tenant_max_limit_per_minute' => ['tenant_settings.max_limit_per_minute', 'int'],
            'tenant_max_limit_per_hour' => ['tenant_settings.max_limit_per_hour', 'int'],
        ];

        foreach ($map as $input => [$key, $type]) {
            if (array_key_exists($input, $data)) {
                $settings->set('whatsapp_anti_ban.'.$key, $data[$input], $type);
            }
        }

        $settings->set('whatsapp_anti_ban.stop_keywords', $this->normalizeStopKeywords((string) ($data['stop_keywords'] ?? '')), 'json');
        $settings->set('whatsapp_anti_ban.warmup.daily_limits', [
            3 => (int) $data['warmup_limit_days_1_3'],
            7 => (int) $data['warmup_limit_days_4_7'],
            15 => (int) $data['warmup_limit_days_8_15'],
        ], 'json');

        return response()->json(['ok' => true]);
    }

    public function updateSafetyControls(Request $request, TenantInstance $instance): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:pause,warmup,reduce_limit,increase_delay,block_campaigns,disable_sends,resume',
        ]);

        $control = WhatsAppSafetyControl::firstOrCreate(
            ['tenant_instance_id' => $instance->id],
            ['tenant_id' => $instance->tenant_id]
        );

        match ($data['action']) {
            'pause' => $control->forceFill([
                'sending_paused' => true,
                'paused_until' => now()->addHours(2),
            ])->save(),
            'warmup' => $control->forceFill([
                'warmup_enabled' => ! $control->warmup_enabled,
            ])->save(),
            'reduce_limit' => $control->forceFill([
                'limit_per_minute' => max(1, (int) ($control->limit_per_minute ?: config('whatsapp_anti_ban.limits.per_minute', 2)) - 1),
                'limit_per_hour' => max(5, (int) ($control->limit_per_hour ?: config('whatsapp_anti_ban.limits.per_hour', 35)) - 5),
                'limit_per_day' => max(30, (int) ($control->limit_per_day ?: config('whatsapp_anti_ban.limits.per_day', 250)) - 20),
            ])->save(),
            'increase_delay' => $control->forceFill([
                'human_delay_min_seconds' => max(3, (int) ($control->human_delay_min_seconds ?: config('whatsapp_anti_ban.human_delay.min_seconds', 8)) + 2),
                'human_delay_max_seconds' => max(8, (int) ($control->human_delay_max_seconds ?: config('whatsapp_anti_ban.human_delay.max_seconds', 28)) + 4),
            ])->save(),
            'block_campaigns' => $control->forceFill([
                'campaigns_blocked' => ! $control->campaigns_blocked,
            ])->save(),
            'disable_sends' => $control->forceFill([
                'sending_disabled' => ! $control->sending_disabled,
            ])->save(),
            'resume' => $control->forceFill([
                'sending_paused' => false,
                'paused_until' => null,
                'sending_disabled' => false,
            ])->save(),
        };

        return response()->json([
            'ok' => true,
            'control' => $control->fresh(),
        ]);
    }

    public function safetyDetail(TenantInstance $instance): JsonResponse
    {
        $control = WhatsAppSafetyControl::firstOrCreate(
            ['tenant_instance_id' => $instance->id],
            ['tenant_id' => $instance->tenant_id]
        );

        $reputation = WhatsAppInstanceReputation::query()
            ->where('tenant_instance_id', $instance->id)
            ->first();

        $recentEvents = WhatsAppSafetyEvent::query()
            ->where('tenant_instance_id', $instance->id)
            ->latest()
            ->limit(50)
            ->get();

        $quarantine = WhatsAppInstanceQuarantine::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('active', true)
            ->latest()
            ->first();

        $today = now()->startOfDay();
        $dailyStats = [
            'sent' => DB::table('whatsapp_messages')
                ->where('tenant_instance_id', $instance->id)
                ->where('direction', 'out')
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->where('created_at', '>=', $today)
                ->count(),
            'failed' => DB::table('whatsapp_messages')
                ->where('tenant_instance_id', $instance->id)
                ->where('direction', 'out')
                ->where('status', 'failed')
                ->where('created_at', '>=', $today)
                ->count(),
            'delivered' => DB::table('whatsapp_messages')
                ->where('tenant_instance_id', $instance->id)
                ->where('direction', 'out')
                ->where('status', 'delivered')
                ->where('created_at', '>=', $today)
                ->count(),
        ];

        return response()->json([
            'ok' => true,
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->display_name ?: $instance->instance_name,
                'status' => $instance->status,
                'created_at' => $instance->created_at,
            ],
            'control' => $control,
            'reputation' => $reputation,
            'quarantine' => $quarantine,
            'recent_events' => $recentEvents,
            'daily_stats' => $dailyStats,
        ]);
    }

    public function releaseQuarantine(TenantInstance $instance): JsonResponse
    {
        try {
            $service = app(WhatsAppQuarantineService::class);
            $service->release($instance, auth('admin')->id(), 'manual_release');

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function resetWarmup(TenantInstance $instance): JsonResponse
    {
        try {
            $instance->forceFill(['created_at' => now()])->save();

            $control = WhatsAppSafetyControl::firstOrCreate(
                ['tenant_instance_id' => $instance->id],
                ['tenant_id' => $instance->tenant_id]
            );
            $control->forceFill(['warmup_enabled' => true])->save();

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function exportReport(): View
    {
        $report = $this->buildExportReport();

        return view('whatsapp::export-report', compact('report'));
    }

    public function downloadExportReport(): JsonResponse
    {
        return response()->json($this->buildExportReport());
    }

    protected function buildExportReport(): array
    {
        $instances = TenantInstance::query()
            ->with('tenant')
            ->get()
            ->map(function (TenantInstance $instance) {
                $control = WhatsAppSafetyControl::where('tenant_instance_id', $instance->id)->first();
                $reputation = WhatsAppInstanceReputation::where('tenant_instance_id', $instance->id)->first();
                $quarantine = WhatsAppInstanceQuarantine::where('tenant_instance_id', $instance->id)
                    ->where('active', true)->latest()->first();

                return [
                    'id' => $instance->id,
                    'name' => $instance->display_name ?: $instance->instance_name,
                    'tenant' => $instance->tenant?->company_name,
                    'status' => $instance->status,
                    'sending_paused' => $control?->sending_paused ?? false,
                    'sending_disabled' => $control?->sending_disabled ?? false,
                    'warmup_enabled' => $control?->warmup_enabled ?? false,
                    'campaigns_blocked' => $control?->campaigns_blocked ?? false,
                    'quarantined' => $quarantine !== null,
                    'quarantine_reason' => $quarantine?->reason,
                    'trust_score' => $reputation?->trust_score ?? 100,
                    'risk_score' => $reputation?->risk_score ?? 0,
                    'quality_score' => $reputation?->quality_score ?? 100,
                    'daily_sent' => $reputation?->daily_sent ?? 0,
                    'daily_failed' => $reputation?->daily_failed ?? 0,
                    'last_rate_limit_at' => $reputation?->last_rate_limit_at,
                    'last_forbidden_at' => $reputation?->last_forbidden_at,
                ];
            });

        return [
            'generated_at' => now()->toIso8601String(),
            'period' => now()->toDateString(),
            'instances' => $instances,
            'summary' => [
                'total' => $instances->count(),
                'paused' => $instances->where('sending_paused', true)->count(),
                'disabled' => $instances->where('sending_disabled', true)->count(),
                'quarantined' => $instances->where('quarantined', true)->count(),
                'warmup' => $instances->where('warmup_enabled', true)->count(),
                'total_sent' => $instances->sum('daily_sent'),
                'total_failed' => $instances->sum('daily_failed'),
            ],
        ];
    }

    protected function fetchMainInstanceSnapshot(array $config): array
    {
        $baseUrl = $config['base_url'];
        $apiKey = $config['api_key'];
        $globalKey = $config['global_api_key'];
        $instance = $config['instance'];
        $timeout = $config['timeout'];
        $keys = array_values(array_unique(array_filter([$globalKey, $apiKey])));

        $message = null;
        $statusRaw = [];
        $statusHttp = 0;
        $infoRaw = [];
        $allRaw = [];
        $avatarRaw = [];

        $statusOk = false;
        try {
            $statusResp = Http::baseUrl($baseUrl)->timeout($timeout)
                ->acceptJson()
                ->withHeaders(['apikey' => $apiKey])
                ->get('/instance/status');

            $statusHttp = $statusResp->status();
            $statusRaw = $statusResp->json() ?? [];
            $statusOk = $statusResp->successful();
            if (! $statusOk) {
                $message = $this->responseMessage($statusResp);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        $infoBody = [];
        foreach ($keys as $key) {
            try {
                $infoResp = Http::baseUrl($baseUrl)->timeout($timeout)
                    ->acceptJson()
                    ->withHeaders(['apikey' => $key])
                    ->get("/instance/info/{$instance}");

                if ($infoResp->successful()) {
                    $infoRaw = $infoResp->json() ?? [];
                    $infoBody = $this->instanceInfoBody($infoRaw);
                    break;
                }
            } catch (\Throwable) {
            }
        }

        $allRow = [];
        foreach ($keys as $key) {
            try {
                $allResp = Http::baseUrl($baseUrl)->timeout($timeout)
                    ->acceptJson()
                    ->withHeaders(['apikey' => $key])
                    ->get('/instance/all');

                if ($allResp->successful()) {
                    $allRaw = $allResp->json() ?? [];
                    $allRow = $this->findInstanceRow($allRaw, $instance);
                    if ($allRow) {
                        break;
                    }
                }
            } catch (\Throwable) {
            }
        }

        $statusBody = $this->instanceInfoBody($statusRaw);
        $stateRaw = $this->firstFilled(
            data_get($statusRaw, 'instance.state'),
            data_get($statusRaw, 'data.instance.state'),
            data_get($statusRaw, 'data.state'),
            data_get($statusRaw, 'state'),
            data_get($statusRaw, 'status'),
            data_get($infoBody, 'state'),
            data_get($infoBody, 'status'),
            data_get($allRow, 'state'),
            data_get($allRow, 'status')
        );
        $stateInfo = ($statusOk || $stateRaw)
            ? $this->normalizeMainConnectionState($stateRaw, $statusRaw)
            : ['state' => null, 'status' => null, 'status_label' => null, 'connected' => false];

        $jid = $this->firstFilled(
            data_get($statusBody, 'jid'),
            data_get($statusRaw, 'jid'),
            data_get($infoBody, 'ownerJid'),
            data_get($infoBody, 'ownerJID'),
            data_get($infoBody, 'jid'),
            data_get($infoBody, 'owner'),
            data_get($allRow, 'ownerJid'),
            data_get($allRow, 'ownerJID'),
            data_get($allRow, 'jid'),
            data_get($allRow, 'owner')
        );

        $number = $this->normalizeEvolutionNumber($this->firstFilled(
            data_get($statusBody, 'phone.phoneNumber'),
            data_get($statusBody, 'phone.number'),
            data_get($statusBody, 'Number'),
            data_get($statusBody, 'number'),
            data_get($statusRaw, 'data.Number'),
            data_get($statusRaw, 'data.number'),
            data_get($infoBody, 'phone.phoneNumber'),
            data_get($infoBody, 'phone.number'),
            data_get($infoBody, 'number'),
            data_get($infoBody, 'phone'),
            data_get($allRow, 'number'),
            data_get($allRow, 'phone'),
            $jid
        ));

        $profileName = $this->firstProfileName($instance, $statusBody, $statusRaw, $infoBody, $allRow);
        $clientName = $this->firstFilled(
            data_get($infoBody, 'clientName'),
            data_get($infoBody, 'client_name'),
            data_get($infoBody, 'displayName'),
            data_get($allRow, 'clientName'),
            data_get($allRow, 'client_name'),
            data_get($allRow, 'displayName'),
            $profileName
        );
        $createdAt = $this->firstFilled(
            data_get($infoBody, 'createdAt'),
            data_get($infoBody, 'created_at'),
            data_get($allRow, 'createdAt'),
            data_get($allRow, 'created_at'),
            data_get($statusBody, 'createdAt'),
            data_get($statusBody, 'created_at')
        );
        $avatar = $this->firstFilled(
            data_get($infoBody, 'profilePictureUrl'),
            data_get($infoBody, 'profilePicUrl'),
            data_get($allRow, 'profilePictureUrl'),
            data_get($allRow, 'profilePicUrl')
        );

        if ($number && $stateInfo['state'] === 'open') {
            try {
                $cleanDigits = preg_replace('/\D+/', '', (string) $number);
                if ($instance && $cleanDigits) {
                    $avatarResp = Http::baseUrl($baseUrl)->timeout(min($timeout, 6))
                        ->acceptJson()
                        ->withHeaders(['apikey' => $apiKey, 'Content-Type' => 'application/json'])
                        ->post('/chat/fetchProfilePictureUrl/' . $instance, ['number' => $cleanDigits]);
                    if ($avatarResp->successful()) {
                        $avatarRaw = $avatarResp->json() ?? [];
                        $avatar = $this->firstFilled(
                            data_get($avatarRaw, 'profilePictureUrl'),
                            data_get($avatarRaw, 'data.profilePictureUrl'),
                            data_get($avatarRaw, 'data.url'),
                            data_get($avatarRaw, 'url'),
                            $avatar
                        );
                    }
                }

                if (! $avatar) {
                    $avatarResp = Http::baseUrl($baseUrl)->timeout(min($timeout, 8))
                        ->acceptJson()
                        ->withHeaders(['apikey' => $apiKey, 'Content-Type' => 'application/json'])
                        ->post('/user/avatar', ['number' => $cleanDigits ?: $number, 'preview' => true]);
                    if ($avatarResp->successful()) {
                        $avatarRaw = $avatarResp->json() ?? [];
                        $avatar = $this->firstFilled(
                            data_get($avatarRaw, 'data.url'),
                            data_get($avatarRaw, 'url'),
                            data_get($avatarRaw, 'base64'),
                            data_get($avatarRaw, 'data.base64'),
                            data_get($avatarRaw, 'profilePictureUrl'),
                            data_get($avatarRaw, 'data.profilePictureUrl'),
                            $avatar
                        );
                    }
                }
            } catch (\Throwable) {
            }
        }

        $hasRemoteData = $statusOk || $infoBody !== [] || $allRow !== [];
        if (! $hasRemoteData) {
            return [
                'payload' => null,
                'server_payload' => [
                    'status_http' => $statusHttp,
                    'status' => $statusRaw,
                ],
                'message' => $message,
            ];
        }

        $sources = array_filter([
            $statusOk ? 'status' : null,
            $infoBody ? 'info' : null,
            $allRow ? 'all' : null,
            $avatar ? 'avatar' : null,
        ]);

        return [
            'payload' => [
                'state' => $stateInfo['state'],
                'status' => $stateInfo['status'],
                'status_label' => $stateInfo['status_label'],
                'connected' => $stateInfo['connected'],
                'raw_state' => $stateRaw ?: $stateInfo['state'],
                'number' => $number,
                'name' => $profileName,
                'client_name' => $clientName,
                'jid' => $jid,
                'avatar' => $avatar,
                'created_at' => $createdAt,
                'instance' => $instance,
                'last_check' => now()->toIso8601String(),
                'persisted' => true,
                '_source' => 'evolution_'.implode('_', $sources ?: ['api']),
                '_api_error' => $message,
            ],
            'server_payload' => [
                'status_http' => $statusHttp,
                'status' => $statusRaw,
                'info' => $infoRaw,
                'all' => $allRaw,
                'avatar' => $avatarRaw,
            ],
            'message' => $message,
        ];
    }

    protected function instanceInfoBody(array $payload): array
    {
        $body = data_get($payload, 'instance')
            ?: data_get($payload, 'data.instance')
            ?: data_get($payload, 'data')
            ?: $payload;

        return is_array($body) && ! array_is_list($body) ? $body : [];
    }

    protected function findInstanceRow(array $payload, string $instance): array
    {
        foreach ($this->instanceRows($payload) as $row) {
            $name = $this->firstFilled(
                data_get($row, 'name'),
                data_get($row, 'instanceName'),
                data_get($row, 'instance_name'),
                data_get($row, 'instance')
            );

            if ($name === $instance) {
                return $row;
            }
        }

        return [];
    }

    protected function firstProfileName(string $instance, array ...$payloads): ?string
    {
        foreach (['profileName', 'profile_name', 'pushName', 'PushName', 'businessName', 'BusinessName', 'verifiedName', 'VerifiedName', 'Name', 'name'] as $path) {
            foreach ($payloads as $payload) {
                $value = $this->firstFilled(data_get($payload, $path));
                if ($value && $value !== $instance) {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function normalizeEvolutionNumber(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = preg_replace('/[:@].*$/', '', $value) ?: $value;
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? $digits : null;
    }

    protected function normalizeMainConnectionState(mixed $stateRaw, array $payload = []): array
    {
        if (is_array($stateRaw)) {
            $stateRaw = data_get($stateRaw, 'state') ?: data_get($stateRaw, 'status') ?: 'close';
        }

        if (is_bool($stateRaw)) {
            $stateRaw = $stateRaw ? 'open' : 'close';
        }

        if (is_numeric($stateRaw)) {
            $stateRaw = (int) $stateRaw === 1 ? 'open' : 'close';
        }

        $raw = mb_strtolower(trim((string) ($stateRaw ?: '')));

        if (in_array($raw, ['open', 'connected', 'connect', 'active', 'online', 'authenticated', 'logged', 'logged_in', 'ready', 'success', 'true', '1'], true)) {
            return ['state' => 'open', 'status' => 'connected', 'status_label' => 'Conectado', 'connected' => true];
        }

        if (in_array($raw, ['connecting', 'syncing', 'init', 'initializing', 'loading', 'qr', 'qrcode', 'pairing', 'waiting_qr', 'scan_qr'], true)) {
            return ['state' => 'connecting', 'status' => 'connecting', 'status_label' => 'Aguardando QR Code', 'connected' => false];
        }

        if (in_array($raw, ['error', 'failed', 'timeout', 'invalid', 'exception'], true)) {
            return ['state' => 'error', 'status' => 'error', 'status_label' => 'Erro de comunicação', 'connected' => false];
        }

        if (! $raw && $this->payloadIsConnected($payload)) {
            return ['state' => 'open', 'status' => 'connected', 'status_label' => 'Conectado', 'connected' => true];
        }

        return ['state' => 'close', 'status' => 'disconnected', 'status_label' => 'Desconectado', 'connected' => false];
    }

    protected function mainInstanceStatusPayload(array $config, ?string $message = null): array
    {
        $row = $this->mainWhatsAppInstanceRow($config);

        if (! $row) {
            $state = $config['api_key'] && $config['base_url'] ? 'nao_verificado' : 'nao_configurado';

            return [
                'state' => $state,
                'status_label' => $this->statusLabel($state),
                'connected' => false,
                'number' => '',
                'name' => '',
                'client_name' => '',
                'jid' => '',
                'avatar' => '',
                'status' => $state,
                'raw_state' => $state,
                'created_at' => '',
                'created_at_display' => '-',
                'instance' => $config['instance'] ?: '',
                'last_check' => '',
                'last_check_display' => '-',
                'persisted' => false,
                'qrcode' => null,
                'source' => 'local_empty',
                '_source' => 'local_empty',
                'message' => $message,
            ];
        }

        $state = $row->connected ? 'open' : ($row->server_status ?: 'close');
        $state = in_array($state, ['open', 'connecting', 'error', 'nao_verificado', 'nao_configurado'], true) ? $state : 'close';
        $payload = is_array($row->server_payload) ? $row->server_payload : [];
        $display = data_get($payload, 'display', []);
        $createdAt = $this->firstPersistentValue(data_get($display, 'created_at'), $row->created_at?->toIso8601String());
        $lastCheck = $row->last_sync_at?->toIso8601String();
        $apiMessage = $message ?: data_get($display, '_api_error');

        return [
            'state' => $state,
            'status' => (string) (data_get($display, 'status') ?: $row->server_status ?: $state),
            'status_label' => (string) (data_get($display, 'status_label') ?: $this->statusLabel($state)),
            'connected' => $state === 'open',
            'number' => (string) ($row->phone ?: ''),
            'name' => (string) ($row->profile_name ?: ''),
            'client_name' => (string) ($row->client_name ?: ''),
            'jid' => (string) ($row->jid ?: ''),
            'avatar' => (string) ($row->profile_picture ?: ''),
            'raw_state' => (string) (data_get($display, 'raw_state') ?: $state),
            'created_at' => $createdAt ?: '',
            'created_at_display' => $createdAt ? $this->formatStatusDate($createdAt) : '-',
            'instance' => $row->instance_name,
            'last_check' => $lastCheck ?: '',
            'last_check_display' => $lastCheck ? $this->formatStatusDate($lastCheck) : '-',
            'persisted' => true,
            'qrcode' => $state === 'open' ? null : data_get($display, 'qrcode'),
            'source' => $message ? 'local_cache' : (data_get($payload, 'display._source') ?: 'local_cache'),
            '_source' => $message ? 'local_cache' : (data_get($payload, 'display._source') ?: 'local_cache'),
            'message' => $apiMessage,
        ];
    }


    protected function mainInstanceAjaxPayload(array $payload, ?string $message = null, bool $ok = true): array
    {
        $state = (string) ($payload['state'] ?? 'close');
        $connected = array_key_exists('connected', $payload)
            ? (bool) $payload['connected']
            : $state === 'open';
        $source = (string) ($payload['source'] ?? $payload['_source'] ?? '');
        $response = [
            'ok' => $ok,
            'connected' => $connected,
            'state' => $state,
            'status' => (string) ($payload['status'] ?? ($connected ? 'connected' : 'disconnected')),
            'status_label' => (string) ($payload['status_label'] ?? $this->statusLabel($state)),
            'raw_state' => (string) ($payload['raw_state'] ?? $state),
            'number' => (string) ($payload['number'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'instance' => (string) ($payload['instance'] ?? ''),
            'client_name' => (string) ($payload['client_name'] ?? ''),
            'jid' => (string) ($payload['jid'] ?? ''),
            'avatar' => (string) ($payload['avatar'] ?? ''),
            'created_at' => (string) ($payload['created_at'] ?? ''),
            'created_at_display' => (string) ($payload['created_at_display'] ?? $this->formatStatusDateOrEmpty($payload['created_at'] ?? '')),
            'last_check' => (string) ($payload['last_check'] ?? ''),
            'last_check_display' => (string) ($payload['last_check_display'] ?? $this->formatStatusDateOrEmpty($payload['last_check'] ?? '')),
            'qrcode' => $connected ? null : ($payload['qrcode'] ?? null),
            'source' => $source,
            '_source' => $source,
            'message' => $this->normalizeApiMessage($message ?? ($payload['message'] ?? null)),
        ];

        $response['data'] = array_merge($payload, $response);

        return $response;
    }

    protected function formatStatusDateOrEmpty(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $this->formatStatusDate($value) : '';
    }

    protected function normalizeMainSourceLabel(string $source): string
    {
        return match ($source) {
            'instance_status' => 'evolution_status',
            'instance_info' => 'evolution_status_info',
            'instance_all' => 'evolution_status_all',
            'instance_qr' => 'evolution_qr',
            '' => 'local_cache',
            default => $source,
        };
    }

    protected function normalizeApiMessage(?string $message): ?string
    {
        if (! $message) {
            return $message;
        }

        return preg_replace('/^HTTP\\s+\\d+\\s*:\\s*/i', '', $message) ?: $message;
    }

    protected function shouldPreserveMainConnectedStatus(array $cachedPayload, array $remotePayload): bool
    {
        if (! ($cachedPayload['persisted'] ?? false) || ! ($cachedPayload['connected'] ?? false)) {
            return false;
        }

        if ($remotePayload['connected'] ?? false) {
            return false;
        }

        $source = (string) ($remotePayload['_source'] ?? $remotePayload['source'] ?? '');

        return in_array($source, [
            'evolution_status_all',
            'evolution_status',
            'evolution_status_info',
            'instance_all',
            'instance_status',
            'instance_info',
            'local_fallback',
            'error',
        ], true);
    }

    protected function persistMainInstanceStatus(array $config, array $payload, array $serverPayload = [], bool $qrReturned = false): ?WhatsAppInstance
    {
        $instanceName = $this->firstPersistentValue($payload['instance'] ?? null, $config['instance'] ?? null);
        if (! $instanceName) {
            return null;
        }

        $existing = $this->mainWhatsAppInstanceRow(['instance' => $instanceName]);
        $state = $this->firstPersistentValue($payload['state'] ?? null, $existing?->server_status) ?: 'nao_verificado';
        $currentQrCode = $this->firstPersistentValue($payload['qrcode'] ?? null);
        $existingQrCode = $this->firstPersistentValue(data_get($existing?->server_payload ?? [], 'display.qrcode'));
        $hasIdentity = $this->payloadHasWhatsAppIdentity($payload);
        $connected = ($state === 'open' || (bool) ($payload['connected'] ?? false)) && $hasIdentity;
        if ($connected) {
            $state = 'open';
        } elseif ($state === 'open') {
            $state = $currentQrCode || $existingQrCode ? 'connecting' : 'close';
            $payload['connected'] = false;
            $payload['status'] = $state === 'connecting' ? 'connecting' : 'disconnected';
            $payload['status_label'] = $state === 'connecting' ? 'Aguardando QR Code' : 'Desconectado';
        }

        if (! $currentQrCode && $state === 'connecting') {
            $currentQrCode = $existingQrCode;
        }

        if (! $currentQrCode && $this->shouldPreserveExistingQrCode($existing, $state, $serverPayload)) {
            $state = 'connecting';
            $payload['status'] = 'connecting';
            $payload['status_label'] = 'Aguardando QR Code';
            $payload['connected'] = false;
            $currentQrCode = $existingQrCode;
        }

        $display = [
            'state' => $state,
            'status' => $this->firstPersistentValue($payload['status'] ?? null, $existing?->server_status, $state),
            'status_label' => $this->firstPersistentValue($payload['status_label'] ?? null, data_get($existing?->server_payload ?? [], 'display.status_label'), $this->statusLabel($state)),
            'connected' => (bool) ($payload['connected'] ?? $connected),
            'raw_state' => $this->firstPersistentValue($payload['raw_state'] ?? null, data_get($existing?->server_payload ?? [], 'display.raw_state'), $state),
            'number' => $this->firstPersistentValue($payload['number'] ?? null, $existing?->phone),
            'name' => $this->firstPersistentValue($payload['name'] ?? null, $existing?->profile_name),
            'client_name' => $this->firstPersistentValue($payload['client_name'] ?? null, $existing?->client_name),
            'jid' => $this->firstPersistentValue($payload['jid'] ?? null, $existing?->jid),
            'avatar' => $this->firstPersistentValue($payload['avatar'] ?? null, $existing?->profile_picture),
            'created_at' => $this->firstPersistentValue($payload['created_at'] ?? null, data_get($existing?->server_payload ?? [], 'display.created_at'), $existing?->created_at?->toIso8601String()),
            'instance' => $instanceName,
            'last_check' => $this->firstPersistentValue($payload['last_check'] ?? null, now()->toIso8601String()),
            'qrcode' => $connected ? null : $currentQrCode,
            '_source' => $payload['_source'] ?? 'local',
            '_api_error' => $payload['_api_error'] ?? null,
        ];

        $snapshot = [
            'display' => $display,
            'server' => array_filter($serverPayload, fn ($value) => $value !== null),
            'synced_at' => now()->toIso8601String(),
        ];

        return WhatsAppInstance::updateOrCreate(
            [
                'tenant_id' => null,
                'tenant_instance_id' => null,
                'instance_name' => $instanceName,
            ],
            [
                'instance_token' => null,
                'global_api_key_ref' => filled($config['global_api_key'] ?? null) ? 'global' : 'instance',
                'phone' => $display['number'],
                'jid' => $display['jid'],
                'profile_name' => $display['name'],
                'profile_picture' => $display['avatar'],
                'client_name' => $display['client_name'],
                'connected' => $connected,
                'logged_in' => $connected,
                'server_status' => $state,
                'last_sync_at' => now(),
                'last_qr_at' => ($qrReturned || ($currentQrCode && $currentQrCode !== $existingQrCode)) ? now() : $existing?->last_qr_at,
                'server_payload' => $snapshot,
                'local_hash' => sha1(json_encode($display, JSON_UNESCAPED_UNICODE)),
                'remote_hash' => $serverPayload ? sha1(json_encode($serverPayload, JSON_UNESCAPED_UNICODE)) : $existing?->remote_hash,
                'sync_status' => $payload['_source'] ?? 'local',
                'sync_error' => null,
            ]
        );
    }

    protected function shouldPreserveExistingQrCode(?WhatsAppInstance $existing, string $state, array $serverPayload = []): bool
    {
        if (! $existing || $existing->connected || $state === 'open') {
            return false;
        }

        if (array_key_exists('disconnect', $serverPayload)) {
            return false;
        }

        $existingQrCode = $this->firstPersistentValue(data_get($existing->server_payload ?? [], 'display.qrcode'));
        if (! $existingQrCode) {
            return false;
        }

        if (! in_array((string) ($existing->server_status ?: ''), ['connecting', 'qr', 'qrcode', 'pairing', 'close', 'disconnected'], true)) {
            return false;
        }

        return ! $existing->last_qr_at || $existing->last_qr_at->greaterThanOrEqualTo(now()->subMinutes(5));
    }

    protected function mainWhatsAppInstanceRow(array $config): ?WhatsAppInstance
    {
        $instanceName = $config['instance'] ?? null;
        if (! $instanceName) {
            return null;
        }

        return WhatsAppInstance::query()
            ->whereNull('tenant_id')
            ->whereNull('tenant_instance_id')
            ->where('instance_name', $instanceName)
            ->latest('id')
            ->first();
    }

    protected function firstPersistentValue(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value !== '' && $value !== '-') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function statusLabel(string $state): string
    {
        return match ($state) {
            'open' => 'Conectado',
            'connecting' => 'Aguardando QR Code',
            'error' => 'Erro de comunicação',
            'nao_configurado' => 'Não configurado',
            'nao_verificado' => 'Não verificado',
            default => 'Desconectado',
        };
    }

    protected function statusBadgeClass(string $state): string
    {
        return match ($state) {
            'open' => 'text-bg-success',
            'connecting' => 'text-bg-warning',
            'error' => 'text-bg-secondary',
            'nao_configurado', 'nao_verificado' => 'text-bg-secondary',
            default => 'text-bg-danger',
        };
    }

    protected function formatStatusDate(string $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function payloadIsConnected(array $payload): bool
    {
        if (! $this->payloadHasWhatsAppIdentity($payload)) {
            return false;
        }

        foreach ([
            data_get($payload, 'data.LoggedIn'),
            data_get($payload, 'data.Connected'),
            data_get($payload, 'LoggedIn'),
            data_get($payload, 'Connected'),
            data_get($payload, 'connected'),
            data_get($payload, 'instance.connected'),
            data_get($payload, 'data.instance.connected'),
            data_get($payload, 'data.connected'),
            data_get($payload, 'data.data.connected'),
            data_get($payload, 'data.data.LoggedIn'),
            data_get($payload, 'data.data.Connected'),
        ] as $value) {
            if ($this->truthy($value)) {
                return true;
            }
        }

        return false;
    }

    protected function payloadHasWhatsAppIdentity(array $payload): bool
    {
        foreach ([
            data_get($payload, 'jid'),
            data_get($payload, 'ownerJid'),
            data_get($payload, 'owner'),
            data_get($payload, 'instance.jid'),
            data_get($payload, 'instance.ownerJid'),
            data_get($payload, 'data.jid'),
            data_get($payload, 'data.ownerJid'),
            data_get($payload, 'data.instance.jid'),
            data_get($payload, 'number'),
            data_get($payload, 'phone'),
            data_get($payload, 'Number'),
            data_get($payload, 'instance.number'),
            data_get($payload, 'instance.phone.phoneNumber'),
            data_get($payload, 'instance.phone.number'),
            data_get($payload, 'data.number'),
            data_get($payload, 'data.Number'),
            data_get($payload, 'data.instance.number'),
            data_get($payload, 'data.instance.phone.phoneNumber'),
            data_get($payload, 'data.instance.phone.number'),
        ] as $value) {
            $value = trim((string) $value);
            $digits = preg_replace('/\D+/', '', preg_replace('/[:@].*$/', '', $value) ?: $value) ?: '';

            if ($digits !== '' || str_contains($value, '@s.whatsapp.net') || str_contains($value, '@lid')) {
                return true;
            }
        }

        return false;
    }

    protected function extractQrCode(array $payload): ?string
    {
        $qr = $this->firstFilled(
            data_get($payload, 'qrcode.Code'),
            data_get($payload, 'qrcode.code'),
            data_get($payload, 'qrcode.pairingCode'),
            data_get($payload, 'data.qrcode.Code'),
            data_get($payload, 'data.qrcode.code'),
            data_get($payload, 'data.qrcode.pairingCode'),
            data_get($payload, 'data.Code'),
            data_get($payload, 'data.code'),
            data_get($payload, 'data.pairingCode'),
            data_get($payload, 'data.data.qrcode.Code'),
            data_get($payload, 'data.data.qrcode.code'),
            data_get($payload, 'data.data.qrcode.pairingCode'),
            data_get($payload, 'data.data.Code'),
            data_get($payload, 'data.data.code'),
            data_get($payload, 'data.data.pairingCode'),
            data_get($payload, 'Code'),
            data_get($payload, 'code'),
            data_get($payload, 'pairingCode'),
            data_get($payload, 'qrcode.base64'),
            data_get($payload, 'qrcode.qrcode'),
            data_get($payload, 'qrcode.Qrcode'),
            data_get($payload, 'data.qrcode.base64'),
            data_get($payload, 'data.qrcode'),
            data_get($payload, 'data.Qrcode'),
            data_get($payload, 'data.data.qrcode.base64'),
            data_get($payload, 'data.data.qrcode'),
            data_get($payload, 'data.data.Qrcode'),
            data_get($payload, 'data.base64'),
            data_get($payload, 'data.data.base64'),
            data_get($payload, 'base64'),
            data_get($payload, 'Qrcode'),
            is_string(data_get($payload, 'qrcode')) ? data_get($payload, 'qrcode') : null
        );

        if (! $qr) {
            return null;
        }

        return $this->normalizeQrCodeValue($qr);
    }

    protected function normalizeQrCodeValue(string $qr): string
    {
        $qr = trim($qr);

        if (str_starts_with($qr, 'data:image')) {
            return $qr;
        }

        $base64 = preg_replace('/\s+/', '', $qr) ?: $qr;

        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $base64) && $this->isImageBase64($base64)) {
            return 'data:image/png;base64,'.$base64;
        }

        return $qr;
    }

    protected function isImageBase64(string $value): bool
    {
        $bytes = base64_decode(mb_substr($value, 0, 64), true);

        if ($bytes === false) {
            return false;
        }

        return str_starts_with($bytes, "\x89PNG")
            || str_starts_with($bytes, "\xFF\xD8\xFF")
            || str_starts_with($bytes, 'GIF8')
            || str_starts_with($bytes, 'RIFF');
    }

    protected function mainInstanceConnectionNumber(array $config, array $statusPayload = []): string
    {
        $row = $this->mainWhatsAppInstanceRow($config);

        return preg_replace('/\D+/', '', (string) $this->firstPersistentValue(
            $statusPayload['number'] ?? null,
            $row?->phone,
            data_get($row?->server_payload ?? [], 'display.number')
        )) ?: '';
    }

    protected function connectedNumberInAnotherInstance(WhatsAppMetaClient $client, array $config, string $number): ?array
    {
        if ($number === '') {
            return null;
        }

        $remote = $client->listInstances();

        if (! ($remote['success'] ?? false)) {
            return null;
        }

        foreach ($this->instanceRows($remote['data'] ?? []) as $row) {
            $instanceName = (string) $this->firstPersistentValue(
                data_get($row, 'name'),
                data_get($row, 'instanceName'),
                data_get($row, 'instance_name')
            );

            if ($instanceName === '' || $instanceName === ($config['instance'] ?? '')) {
                continue;
            }

            $rowNumber = $this->normalizeEvolutionNumber($this->firstPersistentValue(
                data_get($row, 'number'),
                data_get($row, 'phone'),
                data_get($row, 'jid'),
                data_get($row, 'owner'),
                data_get($row, 'ownerJid')
            ));

            if ($rowNumber !== $number) {
                continue;
            }

            $remoteState = $this->normalizeMainConnectionState(
                data_get($row, 'state') ?: data_get($row, 'status') ?: data_get($row, 'connected'),
                $row
            );

            if ($remoteState['connected'] ?? false) {
                return [
                    'instance' => $instanceName,
                    'number' => $rowNumber,
                ];
            }
        }

        return null;
    }

    protected function pollMainInstanceConnectionState(WhatsAppMetaClient $client, array $statusPayload, int $attempts = 4, int $delayMs = 500): array
    {
        ['status' => $status, 'qrcode' => $qrCode] = $this->pollMainInstanceQrCode($client, $statusPayload, $attempts, $delayMs);

        return ['status' => $status, 'qrcode' => $qrCode];
    }

    protected function pollMainInstanceQrCode(WhatsAppMetaClient $client, array $statusPayload, int $attempts, int $delayMs, bool $preferDirectStatus = false): array
    {
        $currentStatus = $statusPayload;
        $qrCode = null;
        $server = [];

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) {
                usleep($delayMs * 1000);
                if ($preferDirectStatus) {
                    $direct = $client->status();
                    if (($direct['ok'] ?? false) === true) {
                        $normalizedDirect = $this->normalizeMainConnectionState(
                            data_get($direct, 'data.state'),
                            (array) data_get($direct, 'data.raw', [])
                        );
                        $currentStatus = array_merge($currentStatus, $normalizedDirect, [
                            'connected' => (bool) ($normalizedDirect['connected'] ?? false),
                            'source' => 'instance_status_direct',
                            '_source' => 'instance_status_direct',
                        ]);
                    } else {
                        $currentStatus = $client->getNormalizedInstanceStatus();
                    }
                } else {
                    $currentStatus = $client->getNormalizedInstanceStatus();
                }
            }

            if ($currentStatus['connected'] ?? false) {
                return ['status' => $currentStatus, 'qrcode' => null];
            }

            $qrResponse = $client->getQrCode();
            $server[] = [
                'success' => (bool) ($qrResponse['success'] ?? false),
                'status' => (int) ($qrResponse['status'] ?? 0),
                'error' => $qrResponse['error'] ?? null,
            ];
            $qrCode = $this->extractQrCode($qrResponse['data'] ?? []);

            if ($qrCode) {
                $currentStatus['state'] = 'connecting';
                $currentStatus['status'] = 'connecting';
                $currentStatus['status_label'] = 'Aguardando QR Code';
                $currentStatus['connected'] = false;

                return ['status' => $currentStatus, 'qrcode' => $qrCode, 'server' => $server];
            }
        }

        return ['status' => $currentStatus, 'qrcode' => null, 'server' => $server];
    }

    protected function instanceRows(array $payload): array
    {
        $data = data_get($payload, 'data', $payload);
        $rows = data_get($payload, 'instances')
            ?: data_get($data, 'instances')
            ?: data_get($data, 'data.instances')
            ?: data_get($data, 'data')
            ?: data_get($data, 'response')
            ?: $data;

        if (is_array($rows) && array_is_list($rows)) {
            return array_values(array_filter($rows, 'is_array'));
        }

        return is_array($rows) && $rows !== [] ? [$rows] : [];
    }

    protected function responseMessage(\Illuminate\Http\Client\Response $response): string
    {
        $data = $response->json() ?? [];

        return (string) (data_get($data, 'message')
            ?: data_get($data, 'error')
            ?: data_get($data, 'response.message')
            ?: "HTTP {$response->status()}");
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
            return in_array(mb_strtolower(trim($value)), ['1', 'true', 'sim', 'yes', 'open', 'connected', 'active', 'online', 'logged', 'logged_in', 'success'], true);
        }

        return false;
    }

    protected function safetySettings(): array
    {
        $s = app(SettingsManager::class);

        return [
            'enabled' => (bool) $s->get('whatsapp_anti_ban.enabled', config('whatsapp_anti_ban.enabled', true)),
            'timezone' => (string) $s->get('whatsapp_anti_ban.timezone', config('whatsapp_anti_ban.timezone', 'America/Sao_Paulo')),
            'business_hours_enabled' => (bool) $s->get('whatsapp_anti_ban.business_hours.enabled', config('whatsapp_anti_ban.business_hours.enabled', true)),
            'business_hours_start' => (string) $s->get('whatsapp_anti_ban.business_hours.start', config('whatsapp_anti_ban.business_hours.start', '08:00')),
            'business_hours_end' => (string) $s->get('whatsapp_anti_ban.business_hours.end', config('whatsapp_anti_ban.business_hours.end', '20:00')),
            'limit_per_minute' => (int) $s->get('whatsapp_anti_ban.limits.per_minute', config('whatsapp_anti_ban.limits.per_minute', 2)),
            'limit_per_hour' => (int) $s->get('whatsapp_anti_ban.limits.per_hour', config('whatsapp_anti_ban.limits.per_hour', 35)),
            'limit_per_day' => (int) $s->get('whatsapp_anti_ban.limits.per_day', config('whatsapp_anti_ban.limits.per_day', 250)),
            'recipient_cooldown_minutes' => (int) $s->get('whatsapp_anti_ban.recipient_cooldown_minutes', config('whatsapp_anti_ban.recipient_cooldown_minutes', 30)),
            'message_cooldown_minutes' => (int) $s->get('whatsapp_anti_ban.cooldowns.message_minutes', config('whatsapp_anti_ban.cooldowns.message_minutes', 45)),
            'campaign_cooldown_minutes' => (int) $s->get('whatsapp_anti_ban.cooldowns.campaign_minutes', config('whatsapp_anti_ban.cooldowns.campaign_minutes', 90)),
            'human_delay_enabled' => (bool) $s->get('whatsapp_anti_ban.human_delay.enabled', config('whatsapp_anti_ban.human_delay.enabled', true)),
            'human_delay_min_seconds' => (int) $s->get('whatsapp_anti_ban.human_delay.min_seconds', config('whatsapp_anti_ban.human_delay.min_seconds', 8)),
            'human_delay_max_seconds' => (int) $s->get('whatsapp_anti_ban.human_delay.max_seconds', config('whatsapp_anti_ban.human_delay.max_seconds', 28)),
            'evolution_delay_min_ms' => (int) $s->get('whatsapp_anti_ban.evolution_delay_ms.min', config('whatsapp_anti_ban.evolution_delay_ms.min', 1500)),
            'evolution_delay_max_ms' => (int) $s->get('whatsapp_anti_ban.evolution_delay_ms.max', config('whatsapp_anti_ban.evolution_delay_ms.max', 4500)),
            'release_when_limited_seconds' => (int) $s->get('whatsapp_anti_ban.release_when_limited_seconds', config('whatsapp_anti_ban.release_when_limited_seconds', 300)),
            'block_groups' => (bool) $s->get('whatsapp_anti_ban.block_groups', config('whatsapp_anti_ban.block_groups', true)),
            'minimum_message_chars' => (int) $s->get('whatsapp_anti_ban.minimum_message_chars', config('whatsapp_anti_ban.minimum_message_chars', 2)),
            'stop_keywords' => implode(', ', $s->get('whatsapp_anti_ban.stop_keywords', config('whatsapp_anti_ban.stop_keywords', []))),
            'warmup_enabled' => (bool) $s->get('whatsapp_anti_ban.warmup.enabled', config('whatsapp_anti_ban.warmup.enabled', true)),
            'warmup_daily_limits' => $s->get('whatsapp_anti_ban.warmup.daily_limits', config('whatsapp_anti_ban.warmup.daily_limits', [3 => 30, 7 => 80, 15 => 150])),
            'risk_medium' => (int) $s->get('whatsapp_anti_ban.risk_thresholds.medium', config('whatsapp_anti_ban.risk_thresholds.medium', 21)),
            'risk_high' => (int) $s->get('whatsapp_anti_ban.risk_thresholds.high', config('whatsapp_anti_ban.risk_thresholds.high', 51)),
            'risk_critical' => (int) $s->get('whatsapp_anti_ban.risk_thresholds.critical', config('whatsapp_anti_ban.risk_thresholds.critical', 81)),
            'risk_block_above' => (int) $s->get('whatsapp_anti_ban.risk_thresholds.block_above', config('whatsapp_anti_ban.risk_thresholds.block_above', 95)),
            'webhook_secret_required' => (bool) $s->get('whatsapp_anti_ban.security.webhook_secret_required', config('whatsapp_anti_ban.security.webhook_secret_required', true)),
            'webhook_max_age_seconds' => (int) $s->get('whatsapp_anti_ban.security.webhook_max_age_seconds', config('whatsapp_anti_ban.security.webhook_max_age_seconds', 300)),
            'api_rate_limit_per_minute' => (int) $s->get('whatsapp_anti_ban.security.api_rate_limit_per_minute', config('whatsapp_anti_ban.security.api_rate_limit_per_minute', 30)),
            'tenant_allow_custom_limits' => (bool) $s->get('whatsapp_anti_ban.tenant_settings.allow_custom_limits', config('whatsapp_anti_ban.tenant_settings.allow_custom_limits', true)),
            'tenant_max_limit_per_day' => (int) $s->get('whatsapp_anti_ban.tenant_settings.max_limit_per_day', config('whatsapp_anti_ban.tenant_settings.max_limit_per_day', 500)),
            'tenant_max_limit_per_minute' => (int) $s->get('whatsapp_anti_ban.tenant_settings.max_limit_per_minute', config('whatsapp_anti_ban.tenant_settings.max_limit_per_minute', 6)),
            'tenant_max_limit_per_hour' => (int) $s->get('whatsapp_anti_ban.tenant_settings.max_limit_per_hour', config('whatsapp_anti_ban.tenant_settings.max_limit_per_hour', 90)),
            'send_pix_qrcode_image' => (bool) $s->get('whatsapp.send_pix_qrcode_image', config('whatsapp.send_pix_qrcode_image', true)),
        ];
    }

    protected function normalizeStopKeywords(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $keyword) => trim(mb_strtolower($keyword)),
            explode(',', $value)
        )));
    }
}
