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

        return view('whatsapp::instance', compact('config', 'persistentStatus', 'instances', 'instanceSummary'));
    }

    protected function whatsappRuntimeConfig(?SettingsManager $settings = null): array
    {
        $settings ??= app(SettingsManager::class);
        $baseUrl = $this->firstFilled(
            $settings->get('whatsapp.base_url'),
            config('whatsapp.meta_base_url'),
            config('whatsapp.base_url'),
            'https://graph.facebook.com/v21.0'
        );
        $apiKey = $this->firstFilled(
            $settings->get('whatsapp.meta_access_token'),
            config('whatsapp.meta_access_token'),
            $settings->get('whatsapp.instance_api_key'),
            $settings->get('whatsapp.apikey'),
            $settings->get('whatsapp.api_key'),
            config('whatsapp.instance_api_key'),
            config('whatsapp.api_key')
        );
        $globalApiKey = $this->firstFilled(
            $settings->get('whatsapp.meta_access_token'),
            config('whatsapp.meta_access_token'),
            $settings->get('whatsapp.global_api_key'),
            $settings->get('whatsapp.global_apikey'),
            config('whatsapp.global_api_key'),
            config('whatsapp.api_key')
        );

        return [
            'base_url'               => $baseUrl ? rtrim($baseUrl, '/') : 'https://graph.facebook.com/v21.0',
            'global_api_key'         => (string) ($globalApiKey ?? ''),
            'api_key'                => (string) ($apiKey ?? ''),
            'meta_access_token'      => (string) ($apiKey ?? ''),
            'meta_phone_number_id'   => (string) ($this->firstFilled($settings->get('whatsapp.meta_phone_number_id'), config('whatsapp.meta_phone_number_id')) ?? ''),
            'meta_waba_id'           => (string) ($this->firstFilled($settings->get('whatsapp.meta_waba_id'), config('whatsapp.meta_waba_id')) ?? ''),
            'instance'               => (string) ($this->firstFilled($settings->get('whatsapp.instance'), config('whatsapp.instance')) ?? 'meta_official'),
            'timeout'                => max(5, (int) ($this->firstFilled($settings->get('whatsapp.timeout'), config('whatsapp.timeout', 15)) ?? 15)),
        ];
    }

    public function messages(Request $request)
    {
        $query = DB::table('whatsapp_messages')->orderByDesc('created_at');

        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('to', 'like', "%{$q}%")
                   ->orWhere('message', 'like', "%{$q}%")
                   ->orWhere('meta_message_id', 'like', "%{$q}%");
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

            $cachedPayload = $this->mainInstanceStatusPayload($config, $normalized['_api_error'] ?? null);

            if ($this->shouldPreserveMainConnectedStatus($cachedPayload, $normalized)) {
                $preserveMessage = $normalized['_api_error'] ?? 'Status remoto instável; sessão conectada preservada.';
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
            'meta_phone_number_id'      => 'nullable|string|max:64',
            'meta_waba_id'              => 'nullable|string|max:64',
            'meta_access_token'         => 'nullable|string|max:1000',
            'meta_app_secret'           => 'nullable|string|max:128',
            'meta_webhook_verify_token' => 'nullable|string|max:128',
            'base_url'                  => 'nullable|url',
            'global_api_key'            => 'nullable|string|min:5',
            'api_key'                   => 'nullable|string|min:5',
            'instance'                  => 'nullable|string|min:1',
            'timeout'                   => 'nullable|integer|min:5|max:120',
        ]);

        if (filled($data['meta_phone_number_id'] ?? null)) {
            $s->set('whatsapp.meta_phone_number_id', $data['meta_phone_number_id']);
        }
        if (filled($data['meta_waba_id'] ?? null)) {
            $s->set('whatsapp.meta_waba_id', $data['meta_waba_id']);
        }
        if (filled($data['meta_access_token'] ?? null)) {
            $s->set('whatsapp.meta_access_token', $data['meta_access_token']);
            $s->set('whatsapp.api_key', $data['meta_access_token']);
            $s->set('whatsapp.instance_api_key', $data['meta_access_token']);
            $s->set('whatsapp.global_api_key', $data['meta_access_token']);
        }
        if (filled($data['meta_app_secret'] ?? null)) {
            $s->set('whatsapp.meta_app_secret', $data['meta_app_secret']);
        }
        if (filled($data['meta_webhook_verify_token'] ?? null)) {
            $s->set('whatsapp.meta_webhook_verify_token', $data['meta_webhook_verify_token']);
            $s->set('whatsapp.webhook_token', $data['meta_webhook_verify_token']);
        }

        if (filled($data['base_url'] ?? null)) {
            $s->set('whatsapp.base_url', $data['base_url']);
        }
        if (filled($data['instance'] ?? null)) {
            $s->set('whatsapp.instance', $data['instance']);
        }
        if (isset($data['timeout'])) {
            $s->set('whatsapp.timeout', (int) $data['timeout'], 'int');
        }

        // Recria os singletons com as novas configuracoes
        app()->forgetInstance(WhatsAppMetaClient::class);
        app()->forgetInstance(\App\Modules\WhatsApp\Services\MetaCloudApiClient::class);

        $runtime = $this->whatsappRuntimeConfig($s);
        $this->persistMainInstanceStatus($runtime, [
            'state' => 'nao_verificado',
            'instance' => $runtime['instance'] ?: 'meta_official',
            'last_check' => now()->toIso8601String(),
            '_source' => 'admin_config',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Configurações da Meta WhatsApp Cloud API salvas com sucesso.',
            'data' => $this->mainInstanceStatusPayload($runtime),
            'webhook_url' => url('/api/v1/webhook'),
        ]);
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
        return $this->testConnection();
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
            $after = [
                'state' => 'close',
                'status' => 'disconnected',
                'status_label' => 'Desconectado',
                'connected' => false,
                'instance' => $config['instance'] ?: 'meta_official',
                'source' => 'disconnect',
                '_source' => 'disconnect',
            ];

            $this->persistMainInstanceStatus($config, $after, [
                'disconnect' => $result['data'] ?? [],
                'status_after_disconnect' => $after,
            ]);

            $message = 'Instância desconectada.';

            return response()->json(
                $this->mainInstanceAjaxPayload($this->mainInstanceStatusPayload($config, $message), $message, true),
                200
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

    /** Salva configuracao da Meta Cloud API em uma instancia de tenant (admin). */
    public function saveInstanceConfig(Request $request, int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::findOrFail($id);

        $data = $request->validate([
            'meta_phone_number_id' => 'nullable|string|max:64',
            'meta_waba_id' => 'nullable|string|max:64',
            'meta_access_token' => 'nullable|string|max:1000',
            'display_name' => 'nullable|string|max:80',
            'phone' => 'nullable|string|max:30',
            'api_base_url' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:1000',
            'webhook_enabled' => 'nullable|boolean',
            'webhook_events' => 'nullable|array',
        ]);

        try {
            $result = $mgr->saveInstanceConfig($instance, $data);
            $instance->refresh();

            return response()->json([
                'ok' => true,
                'applied' => (bool) ($result['applied'] ?? true),
                'message' => $result['message'] ?? 'Configuracao salva com sucesso.',
                'webhook_url' => $result['webhook_url'] ?? $instance->secure_webhook_url,
                'instance' => $instance,
                'sync' => $result['sync'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function restart(): JsonResponse
    {
        return $this->testConnection();
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
            return response()->json(['ok' => (bool) $id, 'meta_message_id' => $id, 'message_id' => $id]);
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

            // Seleciona instância ativa do tenant ou cliente Meta Cloud padrão
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
                'meta_message_id' => $id,
                'message_id' => $id,
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

            return response()->json(['ok' => (bool) $newId, 'meta_message_id' => $newId, 'message_id' => $newId]);
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
                    'meta_message_id' => $msg->meta_message_id ?? 'Sem ID',
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
            'meta_graph_api', 'meta_api', 'meta_cloud', 'instance_status', 'instance_info', 'instance_all' => 'meta_graph_api',
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
            'meta_graph_api',
            'local_fallback',
            'error',
        ], true);
    }

    protected function persistMainInstanceStatus(array $config, array $payload, array $serverPayload = []): ?WhatsAppInstance
    {
        $instanceName = $this->firstPersistentValue($payload['instance'] ?? null, $config['instance'] ?? null) ?: 'meta_official';

        $existing = $this->mainWhatsAppInstanceRow(['instance' => $instanceName]);
        $state = $this->firstPersistentValue($payload['state'] ?? null, $existing?->server_status) ?: 'nao_verificado';
        $connected = ($state === 'open' || (bool) ($payload['connected'] ?? false));
        if ($connected) {
            $state = 'open';
        } else {
            $state = in_array($state, ['connecting', 'nao_verificado', 'nao_configurado', 'error'], true) ? $state : 'close';
            $payload['connected'] = false;
            $payload['status'] = $state === 'connecting' ? 'connecting' : ($state === 'close' ? 'disconnected' : $state);
            $payload['status_label'] = $this->statusLabel($state);
        }

        $display = [
            'state' => $state,
            'status' => $this->firstPersistentValue($payload['status'] ?? null, $existing?->server_status, $state),
            'status_label' => $this->firstPersistentValue($payload['status_label'] ?? null, data_get($existing?->server_payload ?? [], 'display.status_label'), $this->statusLabel($state)),
            'connected' => (bool) ($payload['connected'] ?? $connected),
            'raw_state' => $this->firstPersistentValue($payload['raw_state'] ?? null, data_get($existing?->server_payload ?? [], 'display.raw_state'), $state),
            'number' => $this->firstPersistentValue($payload['number'] ?? null, $payload['phone'] ?? null, $existing?->phone),
            'name' => $this->firstPersistentValue($payload['name'] ?? null, $payload['profile_name'] ?? null, $existing?->profile_name),
            'client_name' => $this->firstPersistentValue($payload['client_name'] ?? null, $existing?->client_name),
            'jid' => $this->firstPersistentValue($payload['jid'] ?? null, $existing?->jid),
            'avatar' => $this->firstPersistentValue($payload['avatar'] ?? null, $payload['profile_picture'] ?? null, $existing?->profile_picture),
            'created_at' => $this->firstPersistentValue($payload['created_at'] ?? null, data_get($existing?->server_payload ?? [], 'display.created_at'), $existing?->created_at?->toIso8601String()),
            'instance' => $instanceName,
            'last_check' => $this->firstPersistentValue($payload['last_check'] ?? null, now()->toIso8601String()),
            'qrcode' => null,
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
                'last_qr_at' => null,
                'server_payload' => $snapshot,
                'local_hash' => sha1(json_encode($display, JSON_UNESCAPED_UNICODE)),
                'remote_hash' => $serverPayload ? sha1(json_encode($serverPayload, JSON_UNESCAPED_UNICODE)) : $existing?->remote_hash,
                'sync_status' => $payload['_source'] ?? 'local',
                'sync_error' => null,
            ]
        );
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
            'open', 'connected', 'active' => 'Conectado',
            'connecting' => 'Conectando',
            'error' => 'Erro de comunicação',
            'nao_configurado' => 'Não configurado',
            'nao_verificado' => 'Não verificado',
            default => 'Desconectado',
        };
    }

    protected function statusBadgeClass(string $state): string
    {
        return match ($state) {
            'open', 'connected', 'active' => 'text-bg-success',
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

    protected function normalizePhoneNumber(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = preg_replace('/[:@].*$/', '', $value) ?: $value;
        $digits = preg_replace('/\D+/', '', $value) ?: '';

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
            'send_delay_min_ms' => (int) $s->get('whatsapp_anti_ban.send_delay_ms.min', config('whatsapp_anti_ban.send_delay_ms.min', 1500)),
            'send_delay_max_ms' => (int) $s->get('whatsapp_anti_ban.send_delay_ms.max', config('whatsapp_anti_ban.send_delay_ms.max', 4500)),
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
