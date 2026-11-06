<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Modules\Gateways\Core\GatewayConfigSchema;
use App\Modules\Gateways\Core\GatewayValidationService;
use App\Modules\Gateways\Core\GatewayWebhookManager;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantBillingConfig;
use App\Modules\Tenancy\Models\TenantGateway;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Models\TenantPackage;
use App\Modules\Tenancy\Models\TenantInvoice;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\Tenancy\Services\TenantBillingService;
use App\Modules\Tenancy\Services\TenantPackageService;
use App\Modules\Tenancy\Services\TenantTemplateResolver;
use App\Modules\Billing\Services\InvoicePaymentService;
use App\Modules\Billing\Services\BillingDispatcher;
use App\Support\ManagedAsset;
use App\Services\TwoFactorService;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\WhatsAppMessage;
use App\Modules\Templates\Services\TemplateRenderer;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;

/**
 * Painel do tenant (cliente logado) - gerencia instancias, templates, gateways, cobranca.
 */
class TenantPanelController extends Controller
{
    protected function tenant(): Tenant
    {
        $user = Auth::user();
        $tenant = Tenant::where('user_id', $user->id)->first();
        if (! $tenant) {
            abort(403, 'Voce nao possui uma assinatura ativa.');
        }
        if (! $tenant->isActive() && $tenant->isTrialExpired()) {
            abort(403, 'Sua assinatura expirou. Renove para continuar.');
        }

        return $tenant;
    }

    public function dashboard()
    {
        $tenant = $this->tenant();

        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            // 1. KPIs Financeiros da carteira do tenant
            $paidInvoicesThisMonth = DB::table('invoices')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$startOfMonth, $endOfMonth]);

            $monthlyRevenue = (float) $paidInvoicesThisMonth->sum('amount');
            $monthlyInvoicesPaidCount = $paidInvoicesThisMonth->count();

            $totalInvoicesThisMonth = DB::table('invoices')
                ->where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

            $monthlyInvoicesTotalCount = $totalInvoicesThisMonth->count();
            $monthlyInvoicesTotalAmount = (float) $totalInvoicesThisMonth->sum('amount');

            $collectionRate = $monthlyInvoicesTotalCount > 0
                ? round(($monthlyInvoicesPaidCount / $monthlyInvoicesTotalCount) * 100, 1)
                : 100.0;

            // 2. Clientes ativos
            $activeCustomersCount = DB::table('customers')
                ->where('tenant_id', $tenant->id)
                ->where('active', true)
                ->count();

            $customersWithWhatsappCount = DB::table('customers')
                ->where('tenant_id', $tenant->id)
                ->where('active', true)
                ->whereNotNull('whatsapp')
                ->where('whatsapp', '!=', '')
                ->count();

            // 3. Métricas de WhatsApp no mês
            $tenantFilter = function ($q) use ($tenant) {
                $q->whereHas('dispatch.invoice', fn ($iq) => $iq->where('tenant_id', $tenant->id))
                  ->orWhereHas('tenantInstance', fn ($tiq) => $tiq->where('tenant_id', $tenant->id))
                  ->orWhereJsonContains('meta->tenant_id', $tenant->id);
            };

            $messagesThisMonthQuery = WhatsAppMessage::where($tenantFilter)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

            $monthlyMessagesCount = $messagesThisMonthQuery->count();
            $deliveredCount = (clone $messagesThisMonthQuery)->whereIn('status', ['delivered', 'read', 'sent'])->count();
            $deliveryRate = $monthlyMessagesCount > 0
                ? round(($deliveredCount / $monthlyMessagesCount) * 100, 1)
                : 100.0;

            // 4. Indicador de Saúde do Usuário / Saúde da Conta (Health Score 0-100)
            $hasConnectedWa = TenantInstance::where('tenant_id', $tenant->id)
                ->where('status', 'connected')
                ->exists();

            $hasActiveGateway = DB::table('tenant_gateways')
                ->where('tenant_id', $tenant->id)
                ->where('active', true)
                ->exists();

            $waScore = $hasConnectedWa ? 30 : 0;
            $gatewayScore = $hasActiveGateway ? 25 : 0;
            $adimplenciaScore = $collectionRate >= 70 ? 25 : (int) round(($collectionRate / 70) * 25);
            $deliveryScore = $deliveryRate >= 80 ? 20 : (int) round(($deliveryRate / 80) * 20);

            $healthScore = min(100, max(15, (int) ($waScore + $gatewayScore + $adimplenciaScore + $deliveryScore)));

            if ($healthScore >= 80) {
                $healthStatus = 'Excelente';
                $healthBadgeClass = 'success';
            } elseif ($healthScore >= 60) {
                $healthStatus = 'Bom';
                $healthBadgeClass = 'info';
            } elseif ($healthScore >= 40) {
                $healthStatus = 'Regular';
                $healthBadgeClass = 'warning';
            } else {
                $healthStatus = 'Atenção';
                $healthBadgeClass = 'danger';
            }

            $healthChecklist = [
                [
                    'title' => 'Conexão do WhatsApp',
                    'status' => $hasConnectedWa,
                    'weight' => '30%',
                    'url' => route('tenant.instances'),
                    'desc' => $hasConnectedWa ? 'Instância de WhatsApp conectada e ativa.' : 'Nenhuma instância conectada. Clique para escanear o QR Code.',
                    'action' => 'Conectar WhatsApp',
                ],
                [
                    'title' => 'Gateway de Cobrança',
                    'status' => $hasActiveGateway,
                    'weight' => '25%',
                    'url' => route('tenant.gateways'),
                    'desc' => $hasActiveGateway ? 'Gateway de pagamento próprio ativo para PIX e cartões.' : 'Configure Mercado Pago, Asaas ou Efi para receber automaticamente.',
                    'action' => 'Configurar Gateway',
                ],
                [
                    'title' => 'Saúde Financeira da Carteira',
                    'status' => $collectionRate >= 70,
                    'weight' => '25%',
                    'url' => route('tenant.invoices'),
                    'desc' => "Taxa de recebimento atual em {$collectionRate}% das faturas emitidas.",
                    'action' => 'Ver Faturas',
                ],
                [
                    'title' => 'Qualidade das Mensagens',
                    'status' => $deliveryRate >= 80,
                    'weight' => '20%',
                    'url' => route('tenant.history.whatsapp'),
                    'desc' => "{$deliveryRate}% das mensagens foram entregues com sucesso.",
                    'action' => 'Histórico',
                ],
            ];

            // 5. Dados para Gráficos Dinâmicos (últimos 14 dias)
            $chartLabels = [];
            $chartMessages = [];
            $chartRevenue = [];

            for ($i = 13; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $dateStr = $day->format('Y-m-d');
                $chartLabels[] = $day->format('d/m');

                $dayMsgCount = WhatsAppMessage::where($tenantFilter)
                    ->whereDate('created_at', $dateStr)
                    ->whereIn('status', ['sent', 'delivered', 'read'])
                    ->count();
                $chartMessages[] = $dayMsgCount;

                $dayRevenue = (float) DB::table('invoices')
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'paid')
                    ->whereDate('paid_at', $dateStr)
                    ->sum('amount');
                $chartRevenue[] = round($dayRevenue, 2);
            }

            $analytics = [
                'monthlyRevenue' => $monthlyRevenue,
                'monthlyInvoicesPaidCount' => $monthlyInvoicesPaidCount,
                'monthlyInvoicesTotalCount' => $monthlyInvoicesTotalCount,
                'monthlyInvoicesTotalAmount' => $monthlyInvoicesTotalAmount,
                'collectionRate' => $collectionRate,
                'activeCustomersCount' => $activeCustomersCount,
                'customersWithWhatsappCount' => $customersWithWhatsappCount,
                'monthlyMessagesCount' => $monthlyMessagesCount,
                'deliveredCount' => $deliveredCount,
                'deliveryRate' => $deliveryRate,
                'healthScore' => $healthScore,
                'healthStatus' => $healthStatus,
                'healthBadgeClass' => $healthBadgeClass,
                'healthChecklist' => $healthChecklist,
                'chartLabels' => $chartLabels,
                'chartMessages' => $chartMessages,
                'chartRevenue' => $chartRevenue,
            ];

        } catch (\Throwable $e) {
            Log::error("[Tenant Dashboard] Erro ao carregar métricas de /painel: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $analytics = [
                'monthlyRevenue' => 0.0,
                'monthlyInvoicesPaidCount' => 0,
                'monthlyInvoicesTotalCount' => 0,
                'monthlyInvoicesTotalAmount' => 0.0,
                'collectionRate' => 100.0,
                'activeCustomersCount' => 0,
                'customersWithWhatsappCount' => 0,
                'monthlyMessagesCount' => 0,
                'deliveredCount' => 0,
                'deliveryRate' => 100.0,
                'healthScore' => 100,
                'healthStatus' => 'Excelente',
                'healthBadgeClass' => 'success',
                'healthChecklist' => [],
                'chartLabels' => [],
                'chartMessages' => [],
                'chartRevenue' => [],
            ];
        }

        return view('tenancy::tenant.dashboard', compact('tenant', 'analytics'));
    }

    public function getSafetyLimits(): JsonResponse
    {
        $tenant = $this->tenant();
        $control = \App\Models\WhatsAppSafetyControl::where('tenant_id', $tenant->id)->whereNull('tenant_instance_id')->first()
            ?: \App\Models\WhatsAppSafetyControl::where('tenant_id', $tenant->id)->first();

        $meta = is_array($control?->meta) ? $control->meta : [];
        $currentMode = $meta['risk_mode'] ?? 'safe';

        $allowCustom = (bool) config('whatsapp_anti_ban.tenant_settings.allow_custom_limits', true);
        $maxAllowedDaily = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_day', 500);
        $maxAllowedMinute = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_minute', 6);
        $maxAllowedHour = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_hour', 90);

        return response()->json([
            'ok' => true,
            'mode' => $currentMode,
            'limit_per_day' => (int) ($control?->limit_per_day ?: config('whatsapp_anti_ban.limits.per_day', 250)),
            'limit_per_minute' => (int) ($control?->limit_per_minute ?: config('whatsapp_anti_ban.limits.per_minute', 2)),
            'limit_per_hour' => (int) ($control?->limit_per_hour ?: config('whatsapp_anti_ban.limits.per_hour', 35)),
            'human_delay_min_seconds' => (int) ($control?->human_delay_min_seconds ?: config('whatsapp_anti_ban.human_delay.min_seconds', 12)),
            'human_delay_max_seconds' => (int) ($control?->human_delay_max_seconds ?: config('whatsapp_anti_ban.human_delay.max_seconds', 32)),
            'warmup_enabled' => (bool) ($control?->warmup_enabled ?? config('whatsapp_anti_ban.warmup.enabled', true)),
            'respect_business_hours' => (bool) ($meta['respect_business_hours'] ?? config('whatsapp_anti_ban.business_hours.enabled', true)),
            'risk_accepted' => ! empty($meta['risk_accepted_at']),
            'risk_accepted_at' => $meta['risk_accepted_at'] ?? null,
            'admin_policy' => [
                'allow_custom_limits' => $allowCustom,
                'max_limit_per_day' => $maxAllowedDaily,
                'max_limit_per_minute' => $maxAllowedMinute,
                'max_limit_per_hour' => $maxAllowedHour,
                'default_daily_safe' => (int) config('whatsapp_anti_ban.limits.per_day', 250),
            ],
        ]);
    }

    public function updateSafetyLimits(Request $request): JsonResponse
    {
        $tenant = $this->tenant();

        $allowCustom = (bool) config('whatsapp_anti_ban.tenant_settings.allow_custom_limits', true);
        if (! $allowCustom) {
            return response()->json([
                'ok' => false,
                'message' => 'O administrador do sistema bloqueou a customização de limites pelos inquilinos.',
            ], 403);
        }

        $maxAllowedDaily = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_day', 500);
        $maxAllowedMinute = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_minute', 6);
        $maxAllowedHour = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_hour', 90);

        $data = $request->validate([
            'mode' => 'required|string|in:safe,moderate,accelerated',
            'limit_per_day' => "nullable|integer|min:10|max:{$maxAllowedDaily}",
            'warmup_enabled' => 'nullable|boolean',
            'respect_business_hours' => 'nullable|boolean',
            'risk_acknowledged' => 'nullable|boolean',
        ]);

        $mode = $data['mode'];
        $respectBusinessHours = $request->boolean('respect_business_hours', true);
        $warmupEnabled = $request->boolean('warmup_enabled', true);

        if ($mode === 'accelerated') {
            if (! $request->boolean('risk_acknowledged')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Para ativar o modo acelerado, você deve aceitar expressamente o termo de responsabilidade por sua conta e risco.',
                ], 422);
            }
        }

        if ($mode === 'safe') {
            $limitPerDay = min(200, $maxAllowedDaily);
            $limitPerMinute = 2;
            $limitPerHour = 30;
            $delayMin = 15;
            $delayMax = 35;
            $warmupEnabled = true;
            $respectBusinessHours = true;
        } elseif ($mode === 'moderate') {
            $limitPerDay = min(300, $maxAllowedDaily);
            $limitPerMinute = 4;
            $limitPerHour = 60;
            $delayMin = 10;
            $delayMax = 22;
            $respectBusinessHours = true;
        } else {
            $requestedDaily = (int) ($data['limit_per_day'] ?? $maxAllowedDaily);
            $limitPerDay = min($maxAllowedDaily, max(50, $requestedDaily));
            $limitPerMinute = $maxAllowedMinute;
            $limitPerHour = $maxAllowedHour;
            $delayMin = 5;
            $delayMax = 12;
        }

        $tenantControl = \App\Models\WhatsAppSafetyControl::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tenant_instance_id' => null]
        );

        $meta = is_array($tenantControl->meta) ? $tenantControl->meta : [];
        $meta['risk_mode'] = $mode;
        $meta['respect_business_hours'] = $respectBusinessHours;
        if ($mode === 'accelerated') {
            $meta['risk_accepted_at'] = now()->toIso8601String();
            $meta['risk_accepted_by_user_id'] = Auth::id();
        } else {
            unset($meta['risk_accepted_at'], $meta['risk_accepted_by_user_id']);
        }

        $attributes = [
            'limit_per_day' => $limitPerDay,
            'limit_per_minute' => $limitPerMinute,
            'limit_per_hour' => $limitPerHour,
            'human_delay_min_seconds' => $delayMin,
            'human_delay_max_seconds' => $delayMax,
            'warmup_enabled' => $warmupEnabled,
            'meta' => $meta,
        ];

        $tenantControl->update($attributes);

        foreach ($tenant->instances as $inst) {
            $instControl = \App\Models\WhatsAppSafetyControl::firstOrCreate(
                ['tenant_instance_id' => $inst->id],
                ['tenant_id' => $tenant->id]
            );
            $instMeta = is_array($instControl->meta) ? $instControl->meta : [];
            $instMeta['risk_mode'] = $mode;
            $instMeta['respect_business_hours'] = $respectBusinessHours;
            if ($mode === 'accelerated') {
                $instMeta['risk_accepted_at'] = now()->toIso8601String();
                $instMeta['risk_accepted_by_user_id'] = Auth::id();
            } else {
                unset($instMeta['risk_accepted_at'], $instMeta['risk_accepted_by_user_id']);
            }

            $instControl->update(array_merge($attributes, ['meta' => $instMeta]));
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('whatsapp_safety_events')) {
            \App\Models\WhatsAppSafetyEvent::create([
                'tenant_id' => $tenant->id,
                'event_type' => 'policy_change',
                'reason' => "Cadência alterada pelo tenant para o modo: {$mode}" . ($mode === 'accelerated' ? ' (por sua conta em risco)' : ''),
                'risk_score' => $mode === 'accelerated' ? 65 : ($mode === 'moderate' ? 25 : 5),
                'risk_level' => $mode === 'accelerated' ? 'alto' : ($mode === 'moderate' ? 'medio' : 'baixo'),
                'meta' => [
                    'mode' => $mode,
                    'limit_per_day' => $limitPerDay,
                    'user_id' => Auth::id(),
                ],
            ]);
        }

        $modeLabels = [
            'safe' => 'Modo Ultra Seguro (Recomendado Meta)',
            'moderate' => 'Modo Moderado',
            'accelerated' => 'Modo Acelerado (Por Sua Conta e Risco)',
        ];

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'mode_label' => $modeLabels[$mode] ?? $mode,
            'message' => 'Configurações de cadência e segurança salvas com sucesso.',
            'limit_per_day' => $limitPerDay,
            'limit_per_minute' => $limitPerMinute,
            'limit_per_hour' => $limitPerHour,
            'human_delay_min_seconds' => $delayMin,
            'human_delay_max_seconds' => $delayMax,
        ]);
    }

    public function profile()
    {
        $tenant = $this->tenant();
        $user = Auth::user()?->refresh();

        return view('tenancy::tenant.profile', compact('tenant', 'user'));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $this->tenant();
        $user = Auth::user();
        $currentAvatar = $user->avatar_url;

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'document' => 'nullable|string|max:20',
            'avatar_url' => 'nullable|string|max:500',
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
        ]);

        ManagedAsset::replaceIfChanged($currentAvatar, $data['avatar_url'] ?? null);
        $user->update($data);

        return response()->json(['ok' => true]);
    }

    public function updateProfilePassword(Request $request): JsonResponse
    {
        $this->tenant();
        $user = Auth::user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['ok' => false, 'message' => 'Senha atual incorreta.'], 422);
        }

        $user->update(['password' => $data['password']]);

        return response()->json(['ok' => true]);
    }

    public function showTwoFactorVerify()
    {
        $tenant = $this->tenant();
        $user = Auth::user()?->refresh();

        return view('tenancy::tenant.two-factor-verify', compact('tenant', 'user'));
    }

    public function verifyTwoFactor(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate(['code' => 'required|string|min:6|max:15']);
        $this->tenant();
        $user = Auth::user();
        $code = $request->input('code');
        $secret = $twoFactor->getSecret($user);

        if (! $secret) {
            return response()->json(['ok' => false, 'error' => '2FA nao configurado.'], 422);
        }

        $valid = $twoFactor->verify($secret, $code)
            || $twoFactor->verifyRecoveryCode($user, $code);

        if (! $valid) {
            return response()->json(['ok' => false, 'error' => 'Codigo invalido.'], 422);
        }

        session(['tenant_2fa_confirmed' => true]);

        return response()->json(['ok' => true, 'redirect' => '/painel']);
    }

    public function setupTwoFactor(TwoFactorService $twoFactor): JsonResponse
    {
        $this->tenant();
        $user = Auth::user();
        $secret = $twoFactor->generateSecret();

        session(['tenant_2fa_setup_secret' => $secret]);

        return response()->json([
            'ok' => true,
            'secret' => $secret,
            'qr_url' => $twoFactor->getQrUrl($user, $secret),
        ]);
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $this->tenant();
        $user = Auth::user();
        $secret = session('tenant_2fa_setup_secret');

        if (! $secret) {
            return response()->json(['ok' => false, 'error' => 'Inicie o setup novamente.'], 422);
        }

        if (! $twoFactor->verify($secret, $request->input('code'))) {
            return response()->json(['ok' => false, 'error' => 'Codigo invalido. Tente novamente.'], 422);
        }

        $recoveryCodes = $twoFactor->enable($user, $secret);
        session()->forget('tenant_2fa_setup_secret');
        session(['tenant_2fa_confirmed' => true]);

        return response()->json([
            'ok' => true,
            'recovery_codes' => $recoveryCodes,
            'message' => '2FA ativado com sucesso.',
        ]);
    }

    public function disableTwoFactor(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $this->tenant();
        $user = Auth::user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['ok' => false, 'error' => 'Senha incorreta.'], 422);
        }

        $twoFactor->disable($user);
        session()->forget('tenant_2fa_confirmed');

        return response()->json(['ok' => true, 'message' => '2FA desativado.']);
    }

    // === INSTANCIAS ===
    public function instances()
    {
        $tenant = $this->tenant();
        $instances = $tenant->instances()->latest()->get();

        return view('tenancy::tenant.instances', compact('tenant', 'instances'));
    }

    public function createInstance(Request $request, InstanceManager $mgr, TenantBillingService $billing): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'purchase_extra' => 'nullable|boolean',
        ]);

        try {
            $requiresPurchase = ! $tenant->canCreateIncludedInstance();
            $quote = $requiresPurchase ? $billing->quoteExtraInstance($tenant) : null;

            if ($requiresPurchase && ! $request->boolean('purchase_extra')) {
                return response()->json([
                    'ok' => false,
                    'requires_purchase' => true,
                    'error' => 'Limite de instancias inclusas atingido. Confirme a compra recorrente da instancia extra.',
                    'quote' => $quote,
                ], 422);
            }

            $instance = $mgr->createExtraInstance(
                $tenant,
                $data['name'],
                billableExtra: $requiresPurchase,
                recurringAmount: $quote['recurring_amount'] ?? null
            );

            $invoice = $requiresPurchase
                ? $billing->createExtraInstanceCharge($tenant->fresh(), $instance, $quote)
                : null;

            return response()->json([
                'ok' => true,
                'instance' => $this->instancePayload($instance->refresh()),
                'html' => $this->instanceCardHtml($instance),
                'config' => $this->instanceConfigPayload($instance),
                'counts' => $this->instanceCounts($tenant->fresh()),
                'qr' => null,
                'requires_purchase' => $requiresPurchase,
                'invoice' => $invoice,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function deleteInstance(int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::where('tenant_id', $this->tenant()->id)->findOrFail($id);

        try {
            $mgr->deleteInstance($instance);

            return response()->json([
                'ok' => true,
                'counts' => $this->instanceCounts($this->tenant()->fresh()),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function renameInstance(Request $request, int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::where('tenant_id', $this->tenant()->id)->findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:60']);
        try {
            $mgr->renameInstance($instance, $data['name']);

            return response()->json([
                'ok' => true,
                'instance' => $this->instancePayload($instance->refresh()),
                'html' => $this->instanceCardHtml($instance),
                'config' => $this->instanceConfigPayload($instance),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function qrCode(int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::where('tenant_id', $this->tenant()->id)->findOrFail($id);

        return response()->json([
            'ok' => false,
            'qr' => null,
            'message' => 'A WhatsApp Cloud API Oficial da Meta conecta diretamente por Token e Phone Number ID sem necessidade de QR Code.',
        ]);
    }

    public function syncInstance(int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::where('tenant_id', $this->tenant()->id)->findOrFail($id);

        try {
            $result = $mgr->syncProfile($instance);
            $instance->refresh();
            $connected = (bool) ($result['connected'] ?? false);

            if (! $connected) {
                return response()->json([
                    'ok' => false,
                    'connected' => false,
                    'state' => $result['state'] ?? 'close',
                    'instance' => $this->instancePayload($instance),
                    'errors' => $result['errors'] ?? [],
                    'error' => $result['error'] ?? 'Nao foi possivel conectar com a Meta WhatsApp API. Verifique Phone Number ID e Token.',
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'connected' => true,
                'state' => 'open',
                'instance' => $this->instancePayload($instance),
                'profile' => $result['profile'] ?? [],
                'errors' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function disconnectInstance(int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::where('tenant_id', $this->tenant()->id)->findOrFail($id);
        $result = $mgr->disconnectInstance($instance);
        if ($result['ok']) {
            return response()->json(['ok' => true]);
        }
        return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Erro ao desconectar.'], 422);
    }

    public function saveInstanceConfig(Request $request, int $id, InstanceManager $mgr): JsonResponse
    {
        $instance = TenantInstance::where('tenant_id', $this->tenant()->id)->findOrFail($id);

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
                'message' => $result['message'] ?? 'Configuracao da Meta salva com sucesso.',
                'webhook_url' => $instance->secure_webhook_url,
                'instance' => $this->instancePayload($instance),
                'config' => $this->instanceConfigPayload($instance),
                'sync' => $result['sync'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function instancePayload(TenantInstance $instance): array
    {
        return [
            'id' => $instance->id,
            'instance_name' => $instance->instance_name,
            'display_name' => $instance->display_name,
            'status' => $instance->status,
            'phone' => $instance->phone,
            'meta_phone_number_id' => $instance->meta_phone_number_id,
            'meta_waba_id' => $instance->meta_waba_id,
            'has_meta_token' => filled($instance->meta_access_token),
            'quality_rating' => data_get($instance->meta, 'quality_rating', 'GREEN'),
            'profile_name' => $instance->profile_name,
            'profile_push_name' => $instance->profile_push_name,
            'profile_full_name' => $instance->profile_full_name,
            'profile_first_name' => $instance->profile_first_name,
            'profile_business_name' => $instance->profile_business_name,
            'profile_is_business' => $instance->profile_is_business,
            'profile_status' => $instance->profile_status,
            'profile_avatar_url' => $instance->profile_avatar_url,
            'profile_picture_id' => $instance->profile_picture_id,
            'profile_number' => $instance->profile_number,
            'profile_jid' => $instance->profile_jid,
            'profile_devices' => $instance->profile_devices,
            'profile_lid' => $instance->profile_lid,
            'profile_contact_found' => $instance->profile_contact_found,
            'last_connection_state' => $instance->last_connection_state,
            'last_profile_sync_at' => $instance->last_profile_sync_at?->format('d/m/Y H:i'),
            'webhook_url' => $instance->secure_webhook_url,
            'last_error' => $instance->last_error,
        ];
    }

    private function instanceCounts(Tenant $tenant): array
    {
        $instances = $tenant->instances()->get();

        return [
            'total' => $instances->count(),
            'connected' => $instances->where('status', 'connected')->count(),
            'included_limit' => $tenant->includedInstanceLimit(),
            'billable_extra' => $instances->where('is_extra_billable', true)->count(),
        ];
    }

    private function instanceCardHtml(TenantInstance $instance): string
    {
        return view('tenancy::tenant.partials.instance-card', [
            'inst' => $instance,
            'statusLabels' => $this->instanceStatusLabels(),
            'statusColors' => $this->instanceStatusColors(),
        ])->render();
    }

    private function instanceConfigPayload(TenantInstance $instance): array
    {
        return [
            'id' => $instance->id,
            'display_name' => $instance->display_name,
            'phone' => $instance->phone,
            'meta_phone_number_id' => $instance->meta_phone_number_id,
            'meta_waba_id' => $instance->meta_waba_id,
            'has_meta_token' => filled($instance->meta_access_token),
            'status' => $instance->status,
            'webhook_url' => $instance->secure_webhook_url,
        ];
    }

    private function instanceStatusLabels(): array
    {
        return [
            'creating' => 'Criando',
            'active' => 'Ativa',
            'connected' => 'Conectada',
            'disconnected' => 'Desconectada',
            'suspended' => 'Suspensa',
            'banned' => 'Banida',
        ];
    }

    private function instanceStatusColors(): array
    {
        return [
            'creating' => 'info',
            'active' => 'primary',
            'connected' => 'success',
            'disconnected' => 'warning',
            'suspended' => 'secondary',
            'banned' => 'danger',
        ];
    }

    private function defaultWebhookEvents(): array
    {
        return [
            'APPLICATION_STARTUP',
            'QRCODE_UPDATED',
            'CONNECTION_UPDATE',
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'PRESENCE_UPDATE',
            'CHATS_UPDATE',
            'GROUPS_UPSERT',
            'GROUP_UPDATE',
            'GROUP_PARTICIPANTS_UPDATE',
            'CALL',
            'NEW_JWT_TOKEN',
        ];
    }

    // === TEMPLATES (WHITE LABEL COMPARTILHADO & INDIVIDUALIZADO) ===
    public function templates(TenantTemplateResolver $resolver)
    {
        $tenant = $this->tenant();

        // 1. Templates padrão do sistema (base compartilhada para todos os perfis)
        $systemTemplates = WhatsAppTemplate::query()
            ->whereNull('tenant_id')
            ->orderBy('category')
            ->orderBy('schedule_offset')
            ->orderBy('name')
            ->get();

        // 2. Personalizações já feitas por este tenant específico
        $tenantCustomizations = WhatsAppTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy('parent_id');

        $preferredTemplateIds = $resolver->preferenceIds($tenant, 'whatsapp');

        // 3. Monta coleção unificada sem duplicatas, com status visual claro
        $templates = collect();

        foreach ($systemTemplates as $sysTpl) {
            $custom = $tenantCustomizations->get($sysTpl->id);

            if ($custom) {
                // O tenant personalizou este template!
                $templates->push((object) [
                    'id' => $custom->id,
                    'system_id' => $sysTpl->id,
                    'tenant_id' => $tenant->id,
                    'parent_id' => $sysTpl->id,
                    'name' => $custom->name,
                    'slug' => $custom->slug,
                    'category' => $custom->category,
                    'category_label' => $custom->categoryLabel(),
                    'category_badge' => $custom->categoryBadgeClass(),
                    'schedule_offset' => $custom->schedule_offset,
                    'content_html' => $custom->content_html,
                    'content_text' => $custom->content_text,
                    'active' => (bool) $custom->active,
                    'locked' => false,
                    'is_system' => false,
                    'is_customized' => true,
                    'is_preferred' => in_array((int) $custom->id, $preferredTemplateIds, true),
                ]);
            } else {
                // O tenant utiliza o modelo compartilhado global do sistema
                $templates->push((object) [
                    'id' => $sysTpl->id,
                    'system_id' => $sysTpl->id,
                    'tenant_id' => null,
                    'parent_id' => null,
                    'name' => $sysTpl->name,
                    'slug' => $sysTpl->slug,
                    'category' => $sysTpl->category,
                    'category_label' => $sysTpl->categoryLabel(),
                    'category_badge' => $sysTpl->categoryBadgeClass(),
                    'schedule_offset' => $sysTpl->schedule_offset,
                    'content_html' => $sysTpl->content_html,
                    'content_text' => $sysTpl->content_text,
                    'active' => (bool) $sysTpl->active,
                    'locked' => false,
                    'is_system' => true,
                    'is_customized' => false,
                    'is_preferred' => in_array((int) $sysTpl->id, $preferredTemplateIds, true),
                ]);
            }
        }

        // 4. Templates adicionais/avulsos criados diretamente pelo tenant (sem parent_id)
        $standaloneTemplates = WhatsAppTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        foreach ($standaloneTemplates as $st) {
            $templates->push((object) [
                'id' => $st->id,
                'system_id' => null,
                'tenant_id' => $tenant->id,
                'parent_id' => null,
                'name' => $st->name,
                'slug' => $st->slug,
                'category' => $st->category,
                'category_label' => $st->categoryLabel(),
                'category_badge' => $st->categoryBadgeClass(),
                'schedule_offset' => $st->schedule_offset,
                'content_html' => $st->content_html,
                'content_text' => $st->content_text,
                'active' => (bool) $st->active,
                'locked' => (bool) $st->locked,
                'is_system' => false,
                'is_customized' => true,
                'is_preferred' => in_array((int) $st->id, $preferredTemplateIds, true),
            ]);
        }

        return view('tenancy::tenant.templates', compact('tenant', 'templates', 'preferredTemplateIds'));
    }

    public function storeTemplate(Request $request, TenantTemplateResolver $resolver): JsonResponse
    {
        $tenant = $this->tenant();
        $validCategories = implode(',', array_keys(WhatsAppTemplate::categories()));
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'category' => 'required|in:' . $validCategories,
            'schedule_offset' => 'nullable|string|max:30',
            'content_html' => 'required|string',
            'content_text' => 'nullable|string',
            'set_default' => 'nullable|boolean',
        ]);

        if (empty($data['content_text'])) {
            $data['content_text'] = WhatsAppTemplate::htmlToWhatsApp($data['content_html']);
        }

        $template = WhatsAppTemplate::create([
            'tenant_id' => $tenant->id,
            'parent_id' => null,
            'slug' => Str::slug($data['name']).'-t'.$tenant->id.'-'.Str::random(3),
            'name' => $data['name'],
            'category' => $data['category'],
            'schedule_offset' => $data['schedule_offset'] ?? null,
            'content_html' => $data['content_html'],
            'content_text' => $data['content_text'],
            'active' => true,
            'scope' => 'tenant',
            'is_system' => false,
            'locked' => false,
            'is_default' => false,
        ]);

        if ($request->boolean('set_default')) {
            $resolver->setDefault($tenant, 'whatsapp', $template);
        }

        return response()->json(['ok' => true, 'id' => $template->id, 'message' => 'Template criado com sucesso!']);
    }

    public function updateTemplate(Request $request, int $id, TenantTemplateResolver $resolver): JsonResponse
    {
        $tenant = $this->tenant();
        $target = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->findOrFail($id);

        $validCategories = implode(',', array_keys(WhatsAppTemplate::categories()));
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'category' => 'sometimes|required|in:' . $validCategories,
            'schedule_offset' => 'nullable|string|max:30',
            'content_html' => 'sometimes|required|string',
            'content_text' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        if (! empty($data['content_html']) && empty($data['content_text'])) {
            $data['content_text'] = WhatsAppTemplate::htmlToWhatsApp($data['content_html']);
        }

        // Se o template alvo é compartilhado do sistema, executa o Fork Copy-On-Write exclusivo para este tenant:
        if ($target->tenant_id === null || $target->is_system) {
            $custom = WhatsAppTemplate::where('tenant_id', $tenant->id)
                ->where('parent_id', $target->id)
                ->first();

            if (! $custom) {
                $custom = new WhatsAppTemplate();
                $custom->tenant_id = $tenant->id;
                $custom->parent_id = $target->id;
                $custom->slug = Str::slug($data['name'] ?? $target->name) . '-t' . $tenant->id;
                $custom->scope = 'tenant';
                $custom->is_system = false;
                $custom->locked = false;
                $custom->is_default = true;
                $custom->variables = $target->variables;
            }

            $custom->name = $data['name'] ?? $target->name;
            $custom->category = $data['category'] ?? $target->category;
            $custom->schedule_offset = array_key_exists('schedule_offset', $data) ? $data['schedule_offset'] : $target->schedule_offset;
            $custom->content_html = $data['content_html'] ?? $target->content_html;
            $custom->content_text = $data['content_text'] ?? $target->content_text;
            $custom->active = array_key_exists('active', $data) ? (bool) $data['active'] : true;
            $custom->save();

            $resolver->setDefault($tenant, 'whatsapp', $custom);

            return response()->json([
                'ok' => true,
                'is_fork' => true,
                'id' => $custom->id,
                'message' => 'Modelo personalizado exclusivamente para a sua empresa! O modelo original permanece intacto para os outros usuários.',
            ]);
        }

        // Se já é um template pertencente ao próprio tenant:
        if ($target->locked) {
            return response()->json(['ok' => false, 'error' => 'Templates bloqueados não podem ser alterados.'], 403);
        }

        $target->update($data);

        return response()->json([
            'ok' => true,
            'id' => $target->id,
            'message' => 'Template personalizado atualizado com sucesso!',
        ]);
    }

    public function resetTemplateToDefault(int $id, TenantTemplateResolver $resolver): JsonResponse
    {
        $tenant = $this->tenant();
        $target = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->findOrFail($id);

        if ($target->tenant_id === $tenant->id && $target->parent_id) {
            $parentId = $target->parent_id;
            $target->delete();

            $parent = WhatsAppTemplate::whereNull('tenant_id')->find($parentId);
            if ($parent) {
                $resolver->setDefault($tenant, 'whatsapp', $parent);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Personalização revertida com sucesso! Seu perfil voltou a usar o modelo compartilhado original.',
            ]);
        }

        if ($target->tenant_id === null) {
            $custom = WhatsAppTemplate::where('tenant_id', $tenant->id)
                ->where('parent_id', $target->id)
                ->first();

            if ($custom) {
                $custom->delete();
            }

            $resolver->setDefault($tenant, 'whatsapp', $target);

            return response()->json([
                'ok' => true,
                'message' => 'Personalização revertida com sucesso! Seu perfil voltou a usar o modelo compartilhado original.',
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => 'Este template não possui um modelo padrão compartilhado para ser restaurado.',
        ], 400);
    }

    public function setDefaultTemplate(int $id, TenantTemplateResolver $resolver): JsonResponse
    {
        $tenant = $this->tenant();
        $template = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->where('active', true)
            ->findOrFail($id);

        $resolver->setDefault($tenant, 'whatsapp', $template);

        return response()->json(['ok' => true, 'message' => 'Template definido como padrão com sucesso!']);
    }

    public function deleteTemplate(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $template = WhatsAppTemplate::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($template->locked || $template->is_system) {
            return response()->json(['ok' => false, 'error' => 'Modelos do sistema não podem ser excluídos.'], 403);
        }

        $template->delete();

        return response()->json(['ok' => true, 'message' => 'Template excluído com sucesso!']);
    }

    public function cloneTemplate(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $source = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->findOrFail($id);

        $clone = WhatsAppTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cópia de ' . $source->name,
            'slug' => Str::slug($source->name) . '-copy-' . Str::random(5),
            'category' => $source->category,
            'schedule_offset' => $source->schedule_offset,
            'content_html' => $source->content_html,
            'content_text' => $source->content_text,
            'variables' => $source->variables,
            'active' => true,
            'scope' => 'tenant',
            'is_system' => false,
            'locked' => false,
            'is_default' => false,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Template clonado com sucesso para a sua conta!',
            'template' => $clone,
        ]);
    }

    public function previewTemplate(Request $request, int $id, \App\Modules\Templates\Services\TemplateRenderer $renderer): JsonResponse
    {
        $tenant = $this->tenant();
        $template = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->findOrFail($id);

        $contentHtml = $request->input('content_html', $template->content_html);
        $contentText = $request->input('content_text', $template->content_text);

        $renderedText = $renderer->renderText($contentText ?: WhatsAppTemplate::htmlToWhatsApp($contentHtml), [
            'nome' => 'Carlos Alberto Silva',
            'primeiro_nome' => 'Carlos',
            'empresa' => $tenant->company_name ?? $tenant->name ?? 'Minha Empresa',
            'valor' => 'R$ 149,90',
            'vencimento' => now()->addDays(3)->format('d/m/Y'),
            'dias_atraso' => 3,
            'numero_fatura' => 'FAT-2026-0042',
            'link_pagamento' => 'https://cobranca.meudominio.com/fatura/pay-sample-123',
            'boleto_url' => 'https://cobranca.meudominio.com/boleto/sample.pdf',
            'linha_digitavel' => '34191.79001 01043.510047 91020.150008 8 98760000014990',
            'pix_copia_cola' => '00020126580014BR.GOV.BCB.PIX0136123e4567-e89b-12d3-a456-4266141740005204000053039865405149.905802BR5913Minha Empresa6009Sao Paulo62070503***6304ABCD',
            'qrcode_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=sample-pix',
            'contrato_numero' => 'CTR-2026/001',
            'contrato_titulo' => 'Prestação de Serviços Digitais',
            'link_assinatura' => 'https://cobranca.meudominio.com/contrato/assinar-demo',
            'link_contrato' => 'https://cobranca.meudominio.com/contrato/visualizar-demo',
            'data_assinatura' => now()->format('d/m/Y H:i'),
            'pacote_nome' => 'Plano Premium WhatsApp',
            'parcela_numero' => 1,
            'total_parcelas' => 12,
            'motivo' => 'Solicitação do cliente',
            'data_cancelamento' => now()->format('d/m/Y'),
            'titulo' => 'Aviso do Sistema',
            'mensagem' => 'Manutenção programada finalizada com sucesso.',
            'whatsapp_suporte' => '(11) 98765-4321',
        ]);

        return response()->json([
            'ok' => true,
            'name' => $template->name,
            'category' => $template->category,
            'schedule_offset' => $template->schedule_offset,
            'rendered_text' => $renderedText,
        ]);
    }

    // === PACOTES DO TENANT ===
    public function packages()
    {
        $tenant = $this->tenant();
        $packages = TenantPackage::withCount('subscriptions')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->get();
        $customers = Customer::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'whatsapp', 'document']);
        $subscriptions = $tenant->packageSubscriptions()
            ->with(['customer', 'package', 'installments'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('tenancy::tenant.packages', compact('tenant', 'packages', 'customers', 'subscriptions'));
    }

    public function storePackage(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $request->merge([
            'total_amount' => Format::decimal($request->input('total_amount')),
            'gateway_fee_percent' => Format::decimal($request->input('gateway_fee_percent')),
            'gateway_fixed_fee' => Format::decimal($request->input('gateway_fixed_fee')),
            'installment_interest_percent' => Format::decimal($request->input('installment_interest_percent')),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:20000',
            'total_amount' => 'required|numeric|min:0.01',
            'allow_cash' => 'required|boolean',
            'allow_installments' => 'required|boolean',
            'max_installments' => 'required|integer|min:1|max:24',
            'gateway_fee_mode' => 'required|in:tenant,customer',
            'gateway_fee_percent' => 'nullable|numeric|min:0|max:100',
            'gateway_fixed_fee' => 'nullable|numeric|min:0',
            'installment_interest_percent' => 'nullable|numeric|min:0|max:100',
            'active' => 'required|boolean',
            'billing_interval_days' => 'nullable|integer|in:-1,0,7,15,30,60,90,180,365,730,1095',
        ]);

        $settings = [];
        $settings['billing_interval_days'] = (int) $request->input('billing_interval_days', 30);
        unset($data['billing_interval_days']);
        $data['settings'] = $settings;

        $package = TenantPackage::create($data + ['tenant_id' => $tenant->id]);

        return response()->json(['ok' => true, 'package' => $package]);
    }

    public function assignPackage(Request $request, TenantPackageService $service): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tenant_package_id' => 'required|exists:tenant_packages,id',
            'payment_mode' => 'required|in:cash,installment',
            'installments_count' => 'required|integer|min:1|max:24',
            'first_due_date' => 'required|date',
        ]);

        try {
            $subscription = $service->assignToCustomer(
                $tenant,
                Customer::where('tenant_id', $tenant->id)->findOrFail($data['customer_id']),
                TenantPackage::where('tenant_id', $tenant->id)->findOrFail($data['tenant_package_id']),
                $data['payment_mode'],
                (int) $data['installments_count'],
                $data['first_due_date']
            );

            return response()->json(['ok' => true, 'subscription' => $subscription]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $package = TenantPackage::where('tenant_id', $tenant->id)->findOrFail($id);

        $request->merge([
            'total_amount' => Format::decimal($request->input('total_amount')),
            'gateway_fee_percent' => Format::decimal($request->input('gateway_fee_percent')),
            'gateway_fixed_fee' => Format::decimal($request->input('gateway_fixed_fee')),
            'installment_interest_percent' => Format::decimal($request->input('installment_interest_percent')),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:20000',
            'total_amount' => 'required|numeric|min:0.01',
            'allow_cash' => 'required|boolean',
            'allow_installments' => 'required|boolean',
            'max_installments' => 'required|integer|min:1|max:24',
            'gateway_fee_mode' => 'required|in:tenant,customer',
            'gateway_fee_percent' => 'nullable|numeric|min:0|max:100',
            'gateway_fixed_fee' => 'nullable|numeric|min:0',
            'installment_interest_percent' => 'nullable|numeric|min:0|max:100',
            'active' => 'required|boolean',
            'billing_interval_days' => 'nullable|integer|in:-1,0,7,15,30,60,90,180,365,730,1095',
        ]);

        $settings = $package->settings ?? [];
        $settings['billing_interval_days'] = (int) $request->input('billing_interval_days', 30);
        unset($data['billing_interval_days']);
        $data['settings'] = $settings;

        $package->update($data);

        return response()->json(['ok' => true, 'package' => $package->fresh()]);
    }

    public function destroyPackage(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $package = TenantPackage::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($package->subscriptions()->exists()) {
            return response()->json([
                'ok' => false,
                'error' => 'Este pacote possui contratacoes ativas e nao pode ser excluido.',
            ], 422);
        }

        $package->delete();

        return response()->json(['ok' => true]);
    }

    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $subscription = \App\Modules\Tenancy\Models\CustomerPackageSubscription::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($subscription->edits_count >= 2) {
            return response()->json([
                'ok' => false,
                'error' => 'Limite de 2 edicoes atingido para esta contratacao.',
            ], 422);
        }

        $data = $request->validate([
            'status' => 'required|in:active,completed,cancelled,overdue',
            'next_due_date' => 'nullable|date',
            'installments_count' => 'nullable|integer|min:1|max:24',
        ]);

        $subscription->update($data + ['edits_count' => $subscription->edits_count + 1]);

        return response()->json([
            'ok' => true,
            'subscription' => $subscription->fresh(),
            'edits_remaining' => max(0, 2 - $subscription->edits_count),
        ]);
    }

    public function cancelSubscription(Request $request, int $id, TenantPackageService $service): JsonResponse
    {
        $tenant = $this->tenant();
        $subscription = \App\Modules\Tenancy\Models\CustomerPackageSubscription::with(['customer', 'package'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $notify = (bool) $request->input('notify_customer', false);

        DB::transaction(function () use ($subscription) {
            $subscription->update(['status' => 'cancelled']);
            $subscription->installments()->whereIn('status', ['pending', 'overdue'])->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
            Invoice::whereIn('id', $subscription->installments()->pluck('invoice_id'))
                ->whereIn('status', ['pending', 'sent', 'overdue'])
                ->update([
                    'status' => 'canceled',
                    'updated_at' => now(),
                ]);
        });

        if ($notify) {
            $service->notifyCancellation($subscription);
        }

        return response()->json(['ok' => true]);
    }

    // === GATEWAYS ===
    public function gateways()
    {
        $tenant = $this->tenant();
        $gateways = $tenant->gateways()->orderByDesc('is_default')->orderBy('priority')->orderBy('gateway')->get();
        $gatewaySchema = app(GatewayConfigSchema::class)->all();

        return view('tenancy::tenant.gateways', compact('tenant', 'gateways', 'gatewaySchema'));
    }

    public function saveGateway(
        Request $request,
        GatewayValidationService $validation,
        GatewayWebhookManager $webhooks
    ): JsonResponse
    {
        $tenant = $this->tenant();
        if (! $tenant->plan->custom_gateway) {
            return response()->json(['ok' => false, 'error' => 'Seu plano não permite gateway próprio.'], 403);
        }
        $data = $request->validate([
            'gateway' => 'required|in:mercadopago,efipro,sumup',
            'credentials' => 'required|array',
            'is_default' => 'nullable|boolean',
            'settings' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'fees' => 'nullable|array',
            'split_rules' => 'nullable|array',
            'installment_rules' => 'nullable|array',
            'environment' => 'nullable|in:sandbox,production',
            'priority' => 'nullable|integer|min:0|max:999',
        ]);
        $data = $validation->validate($data['gateway'], $data);

        // Se for Efi, preserva o certificado já gravado se não tiver sido reenviado agora
        if ($data['gateway'] === 'efipro') {
            $existingTg = TenantGateway::where('tenant_id', $tenant->id)->where('gateway', 'efipro')->first();
            if (empty($data['credentials']['certificate_path']) && !empty($existingTg?->credentials['certificate_path'])) {
                $data['credentials']['certificate_path'] = $existingTg->credentials['certificate_path'];
            }
            if (!empty($existingTg?->settings['certificate_original_name']) && empty($data['settings']['certificate_original_name'])) {
                $data['settings']['certificate_original_name'] = $existingTg->settings['certificate_original_name'];
                $data['settings']['certificate_uploaded_at'] = $existingTg->settings['certificate_uploaded_at'] ?? now()->toISOString();
            }
        }

        if ($request->boolean('is_default')) {
            TenantGateway::query()
                ->where('tenant_id', $tenant->id)
                ->update(['is_default' => false]);
        }

        $gateway = TenantGateway::updateOrCreate(
            ['tenant_id' => $tenant->id, 'gateway' => $data['gateway']],
            [
                'credentials' => $data['credentials'],
                'settings' => $data['settings'] ?? [],
                'payment_methods' => $data['payment_methods'] ?? [],
                'fees' => $data['fees'] ?? [],
                'split_rules' => $data['split_rules'] ?? [],
                'installment_rules' => $data['installment_rules'] ?? [],
                'environment' => $data['environment'] ?? 'production',
                'priority' => (int) ($data['priority'] ?? 0),
                'active' => true,
                'is_default' => $data['is_default'] ?? false,
            ]
        );
        $webhook = $webhooks->buildDynamicWebhook($gateway->gateway, $gateway);
        $gateway->forceFill([
            'webhook_token' => $gateway->webhook_token ?: $webhook['token'],
            'webhook_secret' => $gateway->webhook_secret ?: $webhook['secret'],
        ])->save();

        return response()->json(['ok' => true, 'webhook' => $webhook, 'message' => 'Configurações do gateway salvas com sucesso!']);
    }

    public function uploadCertificate(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        if (! $tenant->plan->custom_gateway) {
            return response()->json(['ok' => false, 'error' => 'Seu plano não permite gateway próprio.'], 403);
        }

        $request->validate([
            'certificate' => 'required|file|max:4096',
            'passphrase'  => 'nullable|string',
            'gateway'     => 'nullable|string|in:efipro',
        ]);

        $file = $request->file('certificate');
        $ext = strtolower($file->getClientOriginalExtension());
        $origName = $file->getClientOriginalName();

        if (! in_array($ext, ['pem', 'p12', 'crt', 'cer'])) {
            return response()->json(['ok' => false, 'error' => 'Formato inválido. O certificado deve ser .pem, .p12 ou .crt.'], 422);
        }

        $dir = storage_path('app/tenants/' . $tenant->id . '/certificates');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir, 0755, true);
        $destPath = $dir . '/efi_certificate.pem';

        $content = file_get_contents($file->getRealPath());

        if ($ext === 'p12') {
            $passphrase = (string) $request->input('passphrase', '');
            $certs = [];
            if (openssl_pkcs12_read($content, $certs, $passphrase)) {
                $pemContent = ($certs['cert'] ?? '') . "\n" . ($certs['pkey'] ?? '');
                if (!empty($certs['extracerts'])) {
                    foreach ((array)$certs['extracerts'] as $extra) {
                        $pemContent .= "\n" . $extra;
                    }
                }
                file_put_contents($destPath, $pemContent);
            } else {
                return response()->json([
                    'ok' => false,
                    'error' => 'Falha ao ler o certificado .p12. Se o arquivo possui senha, informe-a para conversão.'
                ], 422);
            }
        } else {
            file_put_contents($destPath, $content);
        }

        @chmod($destPath, 0600);

        // Atualiza as credenciais do TenantGateway efipro se existir
        $tg = TenantGateway::where('tenant_id', $tenant->id)->where('gateway', 'efipro')->first();
        if ($tg) {
            $creds = $tg->credentials;
            $creds['certificate_path'] = $destPath;
            $settings = $tg->settings ?: [];
            $settings['certificate_original_name'] = $origName;
            $settings['certificate_uploaded_at'] = now()->toISOString();
            $tg->credentials = $creds;
            $tg->settings = $settings;
            $tg->save();
        }

        return response()->json([
            'ok'             => true,
            'message'        => "Certificado {$origName} carregado e configurado com sucesso!",
            'path'           => $destPath,
            'original_name'  => $origName,
            'uploaded_at'    => now()->format('d/m/Y H:i'),
        ]);
    }

    public function deleteCertificate(string $gateway): JsonResponse
    {
        $tenant = $this->tenant();
        $tg = TenantGateway::where('tenant_id', $tenant->id)->where('gateway', $gateway)->first();
        if ($tg) {
            $creds = $tg->credentials;
            if (!empty($creds['certificate_path']) && file_exists($creds['certificate_path'])) {
                @unlink($creds['certificate_path']);
            }
            unset($creds['certificate_path']);
            $settings = $tg->settings ?: [];
            unset($settings['certificate_original_name'], $settings['certificate_uploaded_at']);
            $tg->credentials = $creds;
            $tg->settings = $settings;
            $tg->save();
        }

        return response()->json(['ok' => true, 'message' => 'Certificado removido com sucesso.']);
    }

    public function testGateway(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $gatewayName = (string) $request->input('gateway', 'mercadopago');

        $tg = TenantGateway::where('tenant_id', $tenant->id)
            ->where('gateway', $gatewayName)
            ->first();

        $credentials = $request->input('credentials', []);
        if (empty($credentials) && $tg) {
            $credentials = $tg->credentials;
        }

        $environment = $request->input('environment', $tg?->environment ?? 'production');

        if ($gatewayName === 'mercadopago') {
            $token = $credentials['access_token'] ?? null;
            if (! $token) {
                return response()->json(['ok' => false, 'error' => 'Informe o Access Token do Mercado Pago para testar.'], 422);
            }

            try {
                $resp = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(10)
                    ->get('https://api.mercadopago.com/users/me');

                if ($resp->successful()) {
                    $userData = $resp->json();
                    $name = $userData['nickname'] ?? $userData['first_name'] ?? 'Conta Válida';
                    $id = $userData['id'] ?? '';
                    return response()->json([
                        'ok' => true,
                        'message' => "Conexão com Mercado Pago bem-sucedida! Conta conectada: {$name} (ID: {$id})",
                    ]);
                }

                $err = $resp->json();
                $msg = $err['message'] ?? 'Token inválido ou expirado.';
                return response()->json(['ok' => false, 'error' => 'Falha no Mercado Pago: ' . $msg], 422);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'error' => 'Erro de conexão com Mercado Pago: ' . $e->getMessage()], 500);
            }
        }

        if ($gatewayName === 'efipro') {
            $clientId = $credentials['client_id'] ?? null;
            $clientSecret = $credentials['client_secret'] ?? null;
            $certPath = $credentials['certificate_path'] ?? ($tg?->credentials['certificate_path'] ?? null);

            if (! $clientId || ! $clientSecret) {
                return response()->json(['ok' => false, 'error' => 'Client ID e Client Secret são obrigatórios para testar a Efi.'], 422);
            }

            if (! $certPath || ! file_exists($certPath)) {
                return response()->json(['ok' => false, 'error' => 'Faça o upload do certificado (.pem ou .p12) antes de testar a Efi.'], 422);
            }

            $baseUrl = $environment === 'sandbox' ? 'https://pix-h.api.efipay.com.br' : 'https://pix.api.efipay.com.br';

            try {
                $req = Http::acceptJson()
                    ->asForm()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->withOptions(['cert' => $certPath])
                    ->timeout(12);

                $resp = $req->post("{$baseUrl}/oauth/token", ['grant_type' => 'client_credentials']);

                if ($resp->successful()) {
                    return response()->json([
                        'ok' => true,
                        'message' => 'Conexão com Efi Pro estabelecida com sucesso! Certificado mTLS e OAuth2 validados.',
                    ]);
                }

                $err = $resp->json();
                $msg = $err['error_description'] ?? $err['mensagem'] ?? 'Credenciais ou certificado inválidos.';
                return response()->json(['ok' => false, 'error' => "Falha na Efi Pro: {$msg}"], 422);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'error' => 'Erro ao conectar na Efi: ' . $e->getMessage()], 500);
            }
        }

        return response()->json(['ok' => true, 'message' => 'Gateway configurado.']);
    }

    public function deleteGateway(string $gateway): JsonResponse
    {
        $tenant = $this->tenant();

        TenantGateway::query()
            ->where('tenant_id', $tenant->id)
            ->where('gateway', $gateway)
            ->delete();

        return response()->json(['ok' => true, 'message' => 'Gateway removido com sucesso.']);
    }

    // === CONFIGURACAO DE COBRANCA ===
    public function billingConfig()
    {
        $tenant = $this->tenant();
        $config = $tenant->billingConfig ?? new TenantBillingConfig;

        return view('tenancy::tenant.billing-config', compact('tenant', 'config'));
    }

    public function saveBillingConfig(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $request->merge([
            'fee_percentage' => $request->filled('fee_percentage') ? Format::decimal($request->input('fee_percentage')) : null,
        ]);

        $data = $request->validate([
            'cron_time' => 'required|date_format:H:i',
            'days_before_due' => 'nullable|array',
            'days_before_due.*' => 'integer|min:1|max:30',
            'days_after_due' => 'nullable|array',
            'days_after_due.*' => 'integer|min:1|max:90',
            'invoice_creation_days' => 'required|integer|min:1|max:30',
            'fee_passthrough' => 'required|boolean',
            'fee_percentage' => 'nullable|numeric|min:0|max:100',
            'installment_enabled' => 'required|boolean',
            'max_installments' => 'nullable|integer|min:2|max:24',
            'charge_type' => 'required|in:single,installment,both',
            'skip_weekends_holidays' => 'required|boolean',
        ]);
        TenantBillingConfig::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $data
        );

        $movedCount = 0;
        if (!empty($data['skip_weekends_holidays'])) {
            $movedCount = Invoice::adjustPendingInvoicesToBusinessDays($tenant->id);
        }

        $msg = 'Configurações de cobrança salvas com sucesso.';
        if ($movedCount > 0) {
            $msg .= " {$movedCount} fatura(s) pendente(s) com vencimento em finais de semana ou feriados foram movidas para o próximo dia útil.";
        }

        return response()->json([
            'ok' => true,
            'message' => $msg,
            'moved_invoices' => $movedCount,
        ]);
    }

    // === FATURAS & COBRANÇAS ===
    public function myInvoices(Request $request)
    {
        $tenant = $this->tenant();

        // Query das faturas de clientes
        $query = \App\Models\Invoice::with('customer')
            ->where('tenant_id', $tenant->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('whatsapp', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'canceled') {
                $query->whereIn('status', ['canceled', 'cancelled']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('due_date', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('due_date', '<=', $endDate);
        }

        if ($tipo = $request->input('tipo')) {
            if ($tipo === 'avulsa') {
                $query->where('meta->is_standalone', true);
            } elseif ($tipo === 'recorrente') {
                $query->where(function ($q) {
                    $q->whereNull('meta->is_standalone')
                      ->orWhere('meta->is_standalone', false);
                });
            }
        }

        $invoices = $query->orderByDesc('due_date')->orderByDesc('id')->paginate(25)->withQueryString();

        // Estatísticas gerais das faturas do tenant
        $allTenantInvoices = \App\Models\Invoice::where('tenant_id', $tenant->id);
        $stats = [
            'total'      => (clone $allTenantInvoices)->count(),
            'standalone' => (clone $allTenantInvoices)->where('meta->is_standalone', true)->count(),
            'carnes'     => (clone $allTenantInvoices)->where('meta->is_standalone', true)->where('meta->total_installments', '>', 1)->distinct('meta->batch_id')->count('meta->batch_id'),
            'paid'       => (clone $allTenantInvoices)->where('status', 'paid')->sum('amount'),
            'pending'    => (clone $allTenantInvoices)->whereIn('status', ['pending', 'sent'])->sum('amount'),
            'overdue'    => (clone $allTenantInvoices)->where('status', 'overdue')->sum('amount'),
            'canceled'   => (clone $allTenantInvoices)->whereIn('status', ['canceled', 'cancelled'])->sum('amount'),
        ];

        // Faturas da assinatura do plano (mensalidades pagas pelo tenant)
        $subscriptionInvoices = $tenant->tenantInvoices()->orderByDesc('due_date')->paginate(10, ['*'], 'sub_page');

        // Carrega clientes e gateways ativos do tenant para criação de faturas avulsas
        $customers = Customer::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'whatsapp', 'phone', 'email', 'document']);

        $gateways = TenantGateway::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->get(['id', 'gateway', 'is_default']);

                // Modo de visualização: agrupado por carnê (padrão) ou individual
        $viewMode = $request->input('view_mode', 'grouped');
        $displayRows = [];

        if ($viewMode === 'grouped') {
            $batchIds = $invoices->getCollection()
                ->filter(fn ($i) => !empty($i->meta['batch_id']) && (int) ($i->meta['total_installments'] ?? 1) > 1)
                ->map(fn ($i) => $i->meta['batch_id'])
                ->unique()
                ->values()
                ->all();

            $carnesMap = [];
            if (!empty($batchIds)) {
                $carnesMap = \App\Models\Invoice::with('customer')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('meta->batch_id', $batchIds)
                    ->get()
                    ->sortBy(fn ($i) => (int) ($i->meta['installment'] ?? 1))
                    ->groupBy(fn ($i) => $i->meta['batch_id']);
            }

            $processedBatches = [];

            foreach ($invoices as $inv) {
                $batchId = $inv->meta['batch_id'] ?? null;
                $totalInst = (int) ($inv->meta['total_installments'] ?? 1);

                if ($batchId && $totalInst > 1) {
                    if (isset($processedBatches[$batchId])) {
                        continue;
                    }
                    $processedBatches[$batchId] = true;

                    $items = $carnesMap[$batchId] ?? collect([$inv]);
                    $paidCount = $items->where('status', 'paid')->count();
                    $overdueCount = $items->where('status', 'overdue')->count();
                    $pendingCount = $items->whereIn('status', ['pending', 'sent'])->count();
                    $canceledCount = $items->whereIn('status', ['canceled', 'cancelled'])->count();

                    $totalAmount = $items->sum('amount');
                    $paidAmount = $items->where('status', 'paid')->sum('amount');
                    $overdueAmount = $items->where('status', 'overdue')->sum('amount');
                    $pendingAmount = $items->whereIn('status', ['pending', 'sent'])->sum('amount');

                    // Descrição limpa do carnê (sem o sufixo " (Parcela X/Y)")
                    $cleanDesc = preg_replace('/\s*\(Parcela\s+\d+\/\d+\)/i', '', $inv->description ?: 'Carnê de Cobrança');

                    // Próxima parcela a vencer ou mais atrasada
                    $nextDue = $items->where('status', 'overdue')->sortBy('due_date')->first()
                        ?: $items->whereIn('status', ['pending', 'sent'])->sortBy('due_date')->first()
                        ?: $items->last();

                    $progressPercent = $totalInst > 0 ? round(($paidCount / $totalInst) * 100) : 0;

                    $displayRows[] = (object) [
                        'type'               => 'carne',
                        'batch_id'           => $batchId,
                        'customer'           => $inv->customer,
                        'description'        => $cleanDesc,
                        'total_installments' => $totalInst,
                        'installment_amount' => (float) $inv->amount,
                        'total_amount'       => (float) $totalAmount,
                        'paid_count'         => $paidCount,
                        'paid_amount'        => (float) $paidAmount,
                        'overdue_count'      => $overdueCount,
                        'overdue_amount'     => (float) $overdueAmount,
                        'pending_count'      => $pendingCount,
                        'pending_amount'     => (float) $pendingAmount,
                        'canceled_count'     => $canceledCount,
                        'progress_percent'   => $progressPercent,
                        'next_due_invoice'   => $nextDue,
                        'items'              => $items,
                    ];
                } else {
                    $displayRows[] = (object) [
                        'type'    => 'single',
                        'invoice' => $inv,
                    ];
                }
            }
        } else {
            foreach ($invoices as $inv) {
                $displayRows[] = (object) [
                    'type'    => 'single',
                    'invoice' => $inv,
                ];
            }
        }

        return view('tenancy::tenant.invoices', compact('tenant', 'invoices', 'displayRows', 'viewMode', 'stats', 'subscriptionInvoices', 'customers', 'gateways'));
    }

    public function storeStandaloneInvoice(
        Request $request,
        BillingDispatcher $dispatcher,
        InvoicePaymentService $paymentService
    ): JsonResponse
    {
        $tenant = $this->tenant();

        $validated = $request->validate([
            'customer_id'    => 'required|integer',
            'description'    => 'required|string|max:255',
            'amount'         => 'required|string',
            'installments'   => 'required|integer|min:1|max:24',
            'interest_rate'  => 'nullable|numeric|min:0',
            'first_due_date' => 'required|date',
            'gateway'        => 'nullable|string',
            'send_now'       => 'nullable',
        ]);

        // Garante isolamento: o cliente deve pertencer obrigatoriamente a este tenant
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($validated['customer_id']);

        // Converte valor monetário com segurança (ex: 1.250,50 -> 1250.50)
        $amountStr = str_replace('.', '', (string) $validated['amount']);
        $amountStr = str_replace(',', '.', $amountStr);
        $baseAmount = (float) $amountStr;

        if ($baseAmount <= 0) {
            return response()->json(['ok' => false, 'error' => 'O valor da cobrança deve ser maior que zero.'], 422);
        }

        $installments = (int) $validated['installments'];
        $interestRate = (float) ($validated['interest_rate'] ?? 0);

        // Fórmula de juros simples mensal
        $interestAmount = $baseAmount * ($interestRate / 100) * $installments;
        $totalAmount = $baseAmount + $interestAmount;
        $portionAmount = round($totalAmount / $installments, 2);

        $firstDueDate = Carbon::parse($validated['first_due_date']);
        $batchId = (string) Str::uuid();

        DB::beginTransaction();
        try {
            $createdInvoices = [];

            for ($i = 1; $i <= $installments; $i++) {
                $dueDate = clone $firstDueDate;
                $dueDate->addMonthsNoOverflow($i - 1);

                $desc = $validated['description'];
                if ($installments > 1) {
                    $desc .= " (Parcela {$i}/{$installments})";
                }

                $invoice = Invoice::create([
                    'tenant_id'     => $tenant->id,
                    'customer_id'   => $customer->id,
                    'number'        => 'AV-' . strtoupper(Str::random(8)),
                    'description'   => $desc,
                    'amount'        => $portionAmount,
                    'due_date'      => $dueDate->toDateString(),
                    'status'        => 'pending',
                    'gateway'       => !empty($validated['gateway']) ? $validated['gateway'] : null,
                    'webhook_token' => Str::random(48),
                    'meta'          => [
                        'is_standalone'      => true,
                        'installment'        => $i,
                        'total_installments' => $installments,
                        'batch_id'           => $batchId,
                        'base_amount'        => $baseAmount,
                        'interest_rate'      => $interestRate,
                    ],
                ]);

                // Hidratação de pagamento no gateway configurado (Pix, Boleto, Link)
                try {
                    $paymentService->hydrateGatewayPayment($invoice);
                } catch (\Throwable $e) {
                    Log::warning("[Tenant Standalone Invoice] Falha ao hidratar gateway para fatura #{$invoice->id}: " . $e->getMessage());
                }

                // Envio imediato da primeira parcela via WhatsApp se solicitado
                $scheduledForNextBusinessDay = false;
                if ($i === 1 && !empty($validated['send_now']) && !empty($customer->whatsapp)) {
                    try {
                        $template = $this->tenantStandaloneTemplate($invoice);
                        if ($template) {
                            $holidays = app(\App\Modules\Holidays\Services\HolidayService::class);
                            $today = now('America/Sao_Paulo');
                            $bConfig = DB::table('tenant_billing_config')
                                ->where('tenant_id', $tenant->id)
                                ->first(['cron_time', 'skip_weekends_holidays']);
                            $skipWeekends = (bool) ($bConfig->skip_weekends_holidays ?? true);

                            if ($skipWeekends && $holidays->isNonBusinessDay($today)) {
                                $cronTime = (string) ($bConfig->cron_time ?: '09:00');
                                $parts = explode(':', $cronTime);
                                $hour = (int) ($parts[0] ?? 9);
                                $minute = (int) ($parts[1] ?? 0);
                                $nextScheduled = $holidays->nextBusinessDay($today)->setTime($hour, $minute);

                                $invoice->dispatches()->create([
                                    'template_id'     => $template->id,
                                    'channel'         => 'whatsapp',
                                    'schedule_offset' => 'manual',
                                    'scheduled_at'    => $nextScheduled,
                                    'status'          => 'scheduled',
                                ]);
                                $scheduledForNextBusinessDay = true;
                            } else {
                                $dispatch = $invoice->dispatches()->create([
                                    'template_id'     => $template->id,
                                    'channel'         => 'whatsapp',
                                    'schedule_offset' => 'manual',
                                    'scheduled_at'    => now(),
                                    'status'          => 'scheduled',
                                ]);
                                $dispatcher->dispatchSpecific($dispatch->id);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning("[Tenant Standalone Invoice] Falha no disparo imediato WhatsApp fatura #{$invoice->id}: " . $e->getMessage());
                    }
                }

                $createdInvoices[] = $invoice;
            }

            DB::commit();

            // Agenda a régua de cobrança automática para as novas faturas
            try {
                $dispatcher->scheduleNewInvoices();
            } catch (\Throwable $e) {
                Log::warning("[Tenant Standalone Invoice] Falha ao agendar régua automática: " . $e->getMessage());
            }

            try {
                $formattedTotal = number_format($totalAmount, 2, ',', '.');
                $title = $installments > 1 ? "Carnê Gerado ({$installments}x)" : "Nova Fatura Avulsa Gerada";
                $message = "Cobrança para {$customer->name} no valor total de R$ {$formattedTotal} gerada com sucesso.";

                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels'   => ['in_app', 'email'],
                    'group'      => 'billing',
                    'type'       => 'new_invoice',
                    'title'      => $title,
                    'message'    => $message,
                    'url'        => route('tenant.invoices'),
                    'icon'       => 'bi-receipt',
                    'color'      => 'primary',
                    'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
                ]);
            } catch (\Throwable $e) {
                Log::warning("[Tenant Standalone Invoice] Falha ao notificar sininho/email: " . $e->getMessage());
            }

            $msg = $installments > 1
                ? "Carnê de {$installments} parcelas gerado com sucesso!"
                : "Fatura avulsa gerada com sucesso!";

            if ($scheduledForNextBusinessDay) {
                $msg .= " Como hoje é final de semana ou feriado bancário, o disparo no WhatsApp foi programado para o próximo dia útil conforme as regras de conformidade.";
            }

            return response()->json([
                'ok'      => true,
                'message' => $msg,
                'count'   => count($createdInvoices),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[Tenant Standalone Invoice] Erro crítico ao gerar faturas avulsas: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'Não foi possível gerar a fatura avulsa: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function tenantStandaloneTemplate(Invoice $invoice): ?WhatsAppTemplate
    {
        $resolver = app(TenantTemplateResolver::class);
        $tenantId = $invoice->tenant_id ? (int) $invoice->tenant_id : null;

        // Se a fatura for exclusivamente boleto bancário (com linha digitável cadastrada), permite o template de boleto
        $metodo = strtolower((string) ($invoice->gateway ?? data_get($invoice->meta, 'metodo_pagamento', '')));
        $isBoleto = str_contains($metodo, 'boleto') && filled(data_get($invoice->meta, 'linha_digitavel'));
        if ($isBoleto) {
            $boletoTpl = $resolver->whatsapp($tenantId, null, 'cobranca', 'boleto-fatura');
            if ($boletoTpl) {
                return $boletoTpl;
            }
        }

        // 1. Se o tenant possui preferência ativa gravada para manual/cobrança
        $preferred = $resolver->preferredTemplate('whatsapp', WhatsAppTemplate::class, $tenantId, 'cobranca', 'manual');
        if ($preferred instanceof WhatsAppTemplate && $preferred->slug !== 'boleto-fatura') {
            return $preferred;
        }

        // 2. Se o próprio tenant criou um template customizado manual no seu tenant
        if ($tenantId) {
            $customTenantTpl = WhatsAppTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('active', true)
                ->where(function ($q) {
                    $q->whereIn('schedule_offset', ['manual', 'send_now', 'custom'])
                      ->orWhere('slug', 'cobranca-gerada');
                })
                ->where('slug', '!=', 'boleto-fatura')
                ->first();

            if ($customTenantTpl) {
                return $customTenantTpl;
            }
        }

        // 3. Resolve com base na data de vencimento real cadastrada na fatura (idêntico ao Administrador Geral)
        $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date, 'America/Sao_Paulo')->startOfDay() : null;
        $today = now('America/Sao_Paulo')->startOfDay();

        if ($dueDate) {
            if ($dueDate->equalTo($today)) {
                // Vencimento cadastrado é HOJE: usa template 0d (vencimento-hoje)
                return $resolver->whatsapp($tenantId, '0d', 'cobranca', 'vencimento-hoje')
                    ?: $resolver->whatsapp($tenantId, '0d', 'cobranca')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }

            $diffDays = (int) $today->diffInDays($dueDate, false); // > 0 futuro, < 0 passado

            if ($diffDays === 1) {
                // Vencimento cadastrado é AMANHÃ: usa template de véspera (-1d) (lembrete-1d-antes)
                return $resolver->whatsapp($tenantId, '-1d', 'lembrete', 'lembrete-1d-antes')
                    ?: $resolver->whatsapp($tenantId, '-1d', 'lembrete')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }

            if ($diffDays > 1) {
                // Vencimento cadastrado no FUTURO (2 ou mais dias): usa lembrete-3d-antes ou cobranca-gerada
                return $resolver->whatsapp($tenantId, '-3d', 'lembrete', 'lembrete-3d-antes')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada')
                    ?: $resolver->whatsapp($tenantId, '-3d', 'lembrete');
            }

            if ($diffDays < 0) {
                // Fatura já criada com data de vencimento retroativa (em atraso)
                $offset = abs($diffDays) >= 7 ? '+7d' : '+1d';
                $slug = abs($diffDays) >= 7 ? 'atraso-7d' : 'atraso-1d';
                return $resolver->whatsapp($tenantId, $offset, 'atraso', $slug)
                    ?: $resolver->whatsapp($tenantId, '+1d', 'atraso')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }
        }

        // 4. Fallback padrão: template de cobrança oficial do dia ou cobrança gerada
        return $resolver->whatsapp($tenantId, '0d', 'cobranca', 'vencimento-hoje')
            ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada')
            ?: WhatsAppTemplate::query()
                ->where(function ($q) use ($tenantId) {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                })
                ->where('active', true)
                ->where('slug', '!=', 'boleto-fatura')
                ->whereIn('slug', ['vencimento-hoje', 'cobranca-gerada', 'lembrete-1d-antes', 'lembrete-3d-antes', 'pix-copia-cola'])
                ->orderByDesc('is_default')
                ->first();
    }

    public function showInvoice(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::with('customer')
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        return response()->json([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'description' => $invoice->description,
            'amount' => (float) $invoice->amount,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => $invoice->paid_at?->format('d/m/Y H:i'),
            'customer_name' => $invoice->customer?->name ?? 'Sem cliente',
            'customer_whatsapp' => $invoice->customer?->whatsapp,
            'customer_whatsapp_formatted' => \App\Support\Format::phone($invoice->customer?->whatsapp),
            'payment_url' => $invoice->payment_url,
            'gateway' => $invoice->gateway,
        ]);
    }

    public function showPlanInvoice(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = TenantInvoice::where('tenant_id', $tenant->id)->findOrFail($id);

        return response()->json([
            'ok' => true,
            'id' => $invoice->id,
            'number' => $invoice->number ?? 'TI-' . str_pad((string)$invoice->id, 6, '0', STR_PAD_LEFT),
            'description' => $invoice->description ?: 'Assinatura Plataforma',
            'amount' => (float) $invoice->amount,
            'formatted_amount' => 'R$ ' . number_format((float)$invoice->amount, 2, ',', '.'),
            'due_date' => $invoice->due_date?->format('d/m/Y'),
            'due_date_raw' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at?->format('d/m/Y H:i'),
            'payment_url' => $invoice->payment_url,
            'meta' => $invoice->meta ?: [],
            'created_at' => $invoice->created_at?->format('d/m/Y H:i'),
        ]);
    }

    public function payPlanInvoice(int $id, InvoicePaymentService $paymentService): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = TenantInvoice::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'ok' => true,
                'status' => 'paid',
                'message' => 'Esta fatura já se encontra quitada.',
                'number' => $invoice->number,
                'amount' => (float) $invoice->amount,
            ]);
        }

        $meta = $invoice->meta ?: [];
        $existingPix = data_get($meta, 'gateway_payment.pix_copia_cola');
        $existingQr = data_get($meta, 'gateway_payment.qrcode_url');

        // Se já tem URL de pagamento e pix gravados, retorna direto
        if (!empty($invoice->payment_url) && !empty($existingPix)) {
            return response()->json([
                'ok' => true,
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount' => (float) $invoice->amount,
                'formatted_amount' => 'R$ ' . number_format((float)$invoice->amount, 2, ',', '.'),
                'payment_url' => $invoice->payment_url,
                'pix_copia_cola' => $existingPix,
                'qrcode_url' => $existingQr,
                'due_date' => $invoice->due_date?->format('d/m/Y'),
                'description' => $invoice->description,
            ]);
        }

        // Tenta gerar cobrança via gateway da plataforma (Mercado Pago ou fallback)
        $user = Auth::user();
        $webhookToken = $invoice->webhook_token ?: Str::random(48);

        $invoicePayload = [
            'id' => $invoice->id,
            'amount' => (float) $invoice->amount,
            'description' => $invoice->description ?: 'Assinatura Plataforma',
            'webhook_token' => $webhookToken,
            'customer' => [
                'name' => $tenant->company_name ?: $user->name,
                'email' => $user->email,
                'document' => $tenant->document ?? '',
            ],
        ];

        try {
            $mpGateway = app(\App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway::class);
            $charge = $mpGateway->createCharge($invoicePayload, 'all');

            $paymentUrl = $charge->paymentUrl ?: $invoice->payment_url;
            $pixCopiaCola = data_get($charge->raw, 'point_of_interaction.transaction_data.qr_code')
                ?: data_get($charge->raw, 'qrcode.qrcode')
                ?: data_get($charge->raw, 'qrcode.qrcodeTexto');
            $qrCodeUrl = data_get($charge->raw, 'point_of_interaction.transaction_data.qr_code_base64')
                ?: data_get($charge->raw, 'qrcode.imagemQrcode');

            $meta['gateway_payment'] = [
                'provider' => 'mercadopago',
                'charge_id' => $charge->chargeId,
                'payment_url' => $paymentUrl,
                'pix_copia_cola' => $pixCopiaCola,
                'qrcode_url' => $qrCodeUrl,
                'generated_at' => now()->toISOString(),
            ];

            $invoice->forceFill([
                'payment_url' => $paymentUrl,
                'gateway_charge_id' => $charge->chargeId,
                'webhook_token' => $webhookToken,
                'meta' => $meta,
            ])->save();

            return response()->json([
                'ok' => true,
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount' => (float) $invoice->amount,
                'formatted_amount' => 'R$ ' . number_format((float)$invoice->amount, 2, ',', '.'),
                'payment_url' => $paymentUrl,
                'pix_copia_cola' => $pixCopiaCola,
                'qrcode_url' => $qrCodeUrl,
                'due_date' => $invoice->due_date?->format('d/m/Y'),
                'description' => $invoice->description,
            ]);

        } catch (\Throwable $e) {
            Log::warning("[Tenant Plan Payment] Gateway não gerou Pix automático: " . $e->getMessage());

            if (!empty($invoice->payment_url)) {
                return response()->json([
                    'ok' => true,
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount' => (float) $invoice->amount,
                    'formatted_amount' => 'R$ ' . number_format((float)$invoice->amount, 2, ',', '.'),
                    'payment_url' => $invoice->payment_url,
                    'pix_copia_cola' => $existingPix,
                    'qrcode_url' => $existingQr,
                    'due_date' => $invoice->due_date?->format('d/m/Y'),
                    'description' => $invoice->description,
                ]);
            }

            // Fallback de URL
            $publicUrl = url("/fatura/plano/{$invoice->id}?token={$webhookToken}");
            $invoice->forceFill([
                'payment_url' => $publicUrl,
                'webhook_token' => $webhookToken,
            ])->save();

            return response()->json([
                'ok' => true,
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount' => (float) $invoice->amount,
                'formatted_amount' => 'R$ ' . number_format((float)$invoice->amount, 2, ',', '.'),
                'payment_url' => $publicUrl,
                'pix_copia_cola' => null,
                'qrcode_url' => null,
                'due_date' => $invoice->due_date?->format('d/m/Y'),
                'description' => $invoice->description,
                'message' => 'Fatura pronta para pagamento.',
            ]);
        }
    }

    public function cancelPlanInvoice(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = TenantInvoice::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => false, 'error' => 'Faturas já quitadas não podem ser canceladas.'], 422);
        }

        $reason = trim((string) $request->input('reason', 'Solicitado pelo cliente'));
        if (empty($reason)) {
            $reason = 'Cancelado via painel pelo tenant';
        }

        $meta = $invoice->meta ?: [];
        $meta['cancellation_requested_by'] = Auth::user()->name;
        $meta['cancellation_reason'] = $reason;
        $meta['cancelled_at'] = now()->toISOString();

        $invoice->status = 'canceled';
        $invoice->meta = $meta;
        $invoice->save();

        Log::info("[Tenant Plan Invoice] Fatura #{$invoice->id} ({$invoice->number}) cancelada pelo tenant #{$tenant->id}. Motivo: {$reason}");

        try {
            $dispatcher = app(\App\Modules\Notifications\Services\NotificationDispatcher::class);
            
            // Notifica Administradores
            $dispatcher->send([
                'channels'   => ['in_app', 'email'],
                'group'      => 'cancellation',
                'type'       => 'plan_invoice_cancelled',
                'title'      => 'Cancelamento Solicitado por Tenant',
                'message'    => "Tenant {$tenant->company_name} cancelou a fatura {$invoice->number} (R$ " . number_format($invoice->amount, 2, ',', '.') . "). Motivo: {$reason}",
                'url'        => route('admin.tenants.detail', $tenant->id),
                'icon'       => 'bi-x-circle-fill',
                'color'      => 'danger',
                'recipients' => ['all_admins' => true],
            ]);

            // Notifica o Tenant
            $dispatcher->send([
                'channels'   => ['in_app', 'email'],
                'group'      => 'cancellation',
                'type'       => 'plan_invoice_cancelled',
                'title'      => 'Cancelamento Confirmado',
                'message'    => "Sua fatura {$invoice->number} foi cancelada com sucesso.",
                'url'        => route('tenant.invoices'),
                'icon'       => 'bi-x-circle-fill',
                'color'      => 'warning',
                'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
            ]);
        } catch (\Throwable $e) {
            Log::warning("[Tenant Plan Invoice] Falha ao notificar cancelamento: " . $e->getMessage());
        }

        return response()->json([
            'ok' => true,
            'message' => "Fatura {$invoice->number} cancelada com sucesso.",
        ]);
    }

    public function updateInvoice(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::where('tenant_id', $tenant->id)->findOrFail($id);

        $data = $request->validate([
            'status' => 'nullable|in:pending,paid,overdue,canceled,cancelled',
            'due_date' => 'nullable|date',
        ]);

        $updateData = [];
        if (isset($data['status'])) {
            $status = $data['status'] === 'cancelled' ? 'canceled' : $data['status'];
            $updateData['status'] = $status;
            
            if ($status === 'paid') {
                $updateData['paid_at'] = now();
            } elseif ($status !== 'paid' && $invoice->status === 'paid') {
                $updateData['paid_at'] = null;
            }

            // Cancelamento em cascata e cancelamento de dispatches pendentes
            if ($status === 'canceled') {
                $canceledIds = [$invoice->id];

                if (isset($invoice->meta['installment']) && isset($invoice->meta['total_installments']) && $invoice->meta['total_installments'] > 1) {
                    $cascadeQuery = \App\Models\Invoice::where('tenant_id', $tenant->id)
                        ->where('customer_id', $invoice->customer_id)
                        ->where('id', '!=', $invoice->id)
                        ->whereIn('status', ['pending', 'sent', 'overdue']);
                    
                    if (isset($invoice->meta['batch_id'])) {
                        $cascadeQuery->whereJsonContains('meta->batch_id', $invoice->meta['batch_id']);
                    } else {
                        $cascadeQuery->where('created_at', $invoice->created_at);
                    }
                    
                    $extraIds = (clone $cascadeQuery)->pluck('id')->all();
                    $canceledIds = array_merge($canceledIds, $extraIds);
                    $cascadeQuery->update(['status' => 'canceled', 'updated_at' => now()]);
                } else {
                    $installment = \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('invoice_id', $invoice->id)->first();
                    if ($installment && $installment->subscription_id) {
                        $relatedInvoiceIds = \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('subscription_id', $installment->subscription_id)
                            ->where('id', '!=', $installment->id)
                            ->pluck('invoice_id');
                        
                        if ($relatedInvoiceIds->isNotEmpty()) {
                            $canceledIds = array_merge($canceledIds, $relatedInvoiceIds->all());
                            \App\Models\Invoice::whereIn('id', $relatedInvoiceIds)
                                ->whereIn('status', ['pending', 'sent', 'overdue'])
                                ->update(['status' => 'canceled', 'updated_at' => now()]);
                        }
                        
                        $installment->subscription()->update(['status' => 'cancelled']);
                    }
                }

                // Cancela todos os envios agendados pendentes para evitar disparos de faturas canceladas
                \App\Models\BillingDispatch::whereIn('invoice_id', array_unique($canceledIds))
                    ->whereIn('status', ['scheduled', 'pending'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
        }
        if (isset($data['due_date'])) {
            $updateData['due_date'] = $data['due_date'];
        }

        $oldStatus = $invoice->status;
        $invoice->update($updateData);

        if (isset($updateData['status']) && $updateData['status'] !== $oldStatus) {
            try {
                $dispatcher = app(\App\Modules\Notifications\Services\NotificationDispatcher::class);
                if ($updateData['status'] === 'paid') {
                    $dispatcher->send([
                        'channels'   => ['in_app', 'email'],
                        'group'      => 'settlement',
                        'type'       => 'invoice_paid',
                        'title'      => 'Fatura Quitada!',
                        'message'    => "Fatura #{$invoice->number} de R$ " . number_format($invoice->amount, 2, ',', '.') . " foi baixada como paga.",
                        'url'        => route('tenant.invoices'),
                        'icon'       => 'bi-check-circle-fill',
                        'color'      => 'success',
                        'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
                    ]);
                } elseif ($updateData['status'] === 'canceled') {
                    $dispatcher->send([
                        'channels'   => ['in_app', 'email'],
                        'group'      => 'cancellation',
                        'type'       => 'invoice_canceled',
                        'title'      => 'Fatura de Cliente Cancelada',
                        'message'    => "Fatura #{$invoice->number} foi cancelada no painel.",
                        'url'        => route('tenant.invoices'),
                        'icon'       => 'bi-x-circle-fill',
                        'color'      => 'danger',
                        'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[Invoice Update] Falha ao notificar status: ' . $e->getMessage());
            }
        }

        return response()->json([
            'ok' => true,
            'invoice' => [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->toDateString(),
            ],
        ]);
    }

    public function anticipateInvoice(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => false, 'error' => 'Faturas já quitadas não podem ser antecipadas.'], 422);
        }

        $data = $request->validate([
            'due_date' => 'required|date',
        ]);

        $newDueDate = \Carbon\Carbon::parse($data['due_date'], 'America/Sao_Paulo')->startOfDay();
        $invMeta = is_array($invoice->meta) ? $invoice->meta : [];
        $invMeta['is_antecipated'] = true;
        $invMeta['original_due_date'] = $invoice->due_date?->toDateString();
        $invMeta['antecipated_at'] = now()->toIso8601String();

        $updateData = [
            'due_date' => $newDueDate->toDateString(),
            'meta'     => $invMeta,
        ];

        if ($invoice->status === 'overdue' && ($newDueDate->isFuture() || $newDueDate->isToday())) {
            $updateData['status'] = 'pending';
        }

        $invoice->update($updateData);

        return response()->json([
            'ok' => true,
            'message' => "Vencimento da fatura #{$invoice->number} alterado para {$newDueDate->format('d/m/Y')}.",
        ]);
    }

    public function cancelInvoice(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => false, 'error' => 'Faturas já quitadas não podem ser canceladas diretamente.'], 422);
        }

        $req = new Request(['status' => 'canceled']);
        $res = $this->updateInvoice($req, $id);
        
        return response()->json([
            'ok' => true,
            'message' => "Fatura #{$invoice->number} cancelada com sucesso. Todos os envios agendados foram suspensos.",
        ]);
    }

    public function resendInvoice(
        Request $request,
        int $id,
        BillingDispatcher $dispatcher,
        InvoicePaymentService $paymentService
    ): JsonResponse {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::with('customer')
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        if (in_array($invoice->status, ['canceled', 'cancelled'], true)) {
            return response()->json([
                'ok' => false,
                'error' => "A fatura #{$invoice->number} está cancelada e não pode ser reenviada.",
            ], 422);
        }

        $customer = $invoice->customer;
        if (! $customer || empty($customer->whatsapp)) {
            return response()->json([
                'ok' => false,
                'error' => "O cliente não possui número de WhatsApp cadastrado para receber o reenvio.",
            ], 422);
        }

        // Garante hidratação dos dados de pagamento / Pix / Link Seguro
        try {
            $paymentService->hydrateGatewayPayment($invoice);
        } catch (\Throwable $e) {
            Log::warning("[Tenant Invoice Resend] Falha na hidratação de gateway para fatura #{$invoice->id}: " . $e->getMessage());
        }

        // Localiza o melhor template para a fatura respeitando a data real de vencimento e conformidade
        $template = $this->tenantStandaloneTemplate($invoice);
        if (! $template) {
            return response()->json([
                'ok' => false,
                'error' => 'Nenhum template ativo de cobrança foi localizado para efetuar o disparo.',
            ], 422);
        }

        try {
            // Cria dispatch manual com disparo imediato
            $dispatch = $invoice->dispatches()->create([
                'template_id'     => $template->id,
                'channel'         => 'whatsapp',
                'schedule_offset' => 'manual',
                'scheduled_at'    => now(),
                'status'          => 'scheduled',
            ]);

            $dispatcher->dispatchSpecific($dispatch->id);

            // Notifica in-app via central de alertas
            try {
                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels'   => ['in_app'],
                    'group'      => 'billing',
                    'type'       => 'invoice_resent',
                    'title'      => 'Cobrança Reenviada Manualmente',
                    'message'    => "Fatura #{$invoice->number} reenviada com sucesso para {$customer->name} no WhatsApp.",
                    'url'        => route('tenant.invoices'),
                    'icon'       => 'bi-send-check',
                    'color'      => 'success',
                    'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
                ]);
            } catch (\Throwable $e) {
                Log::warning("[Tenant Invoice Resend] Falha ao criar notificação de reenvio: " . $e->getMessage());
            }

            return response()->json([
                'ok'      => true,
                'message' => "Cobrança da fatura #{$invoice->number} reenviada com sucesso para {$customer->name} via WhatsApp!",
            ]);
        } catch (\Throwable $e) {
            Log::error("[Tenant Invoice Resend] Erro ao reenviar fatura #{$invoice->id}: " . $e->getMessage());
            return response()->json([
                'ok'    => false,
                'error' => 'Falha ao reenviar cobrança: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function invoiceInstallmentContext(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::with('customer')
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $context = app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->getInstallmentContext($invoice);

        return response()->json([
            'ok'      => true,
            'context' => $context,
        ]);
    }

    public function settleInvoice(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::with('customer')
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => false, 'error' => 'Esta fatura já está quitada.'], 422);
        }

        $data = $request->validate([
            'gateway'         => 'nullable|string|in:manual,pix,dinheiro,cartao,transferencia,boleto,outro',
            'paid_at'         => 'nullable|date',
            'notify_customer' => 'nullable|boolean',
            'notes'           => 'nullable|string|max:500',
            'settle_mode'     => 'nullable|string|in:single,antecipated,all_open,selected',
            'selected_ids'    => 'nullable|array',
            'selected_ids.*'  => 'integer',
        ]);

        $gateway = $data['gateway'] ?: 'manual';
        $paidAt = ! empty($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now();
        $notifyCustomer = $request->boolean('notify_customer', true);
        $notes = $data['notes'] ?? null;
        $settleMode = $data['settle_mode'] ?? 'single';

        $service = app(\App\Modules\Billing\Services\InvoiceSettlementService::class);
        $targetIds = [$invoice->id];

        if ($settleMode !== 'single') {
            $context = $service->getInstallmentContext($invoice);

            if ($settleMode === 'antecipated') {
                foreach ($context['antecipated_installments'] as $ant) {
                    $targetIds[] = $ant['id'];
                }
            } elseif ($settleMode === 'all_open') {
                foreach ($context['open_installments'] as $op) {
                    $targetIds[] = $op['id'];
                }
            } elseif ($settleMode === 'selected' && ! empty($data['selected_ids'])) {
                $allowedIds = collect($context['open_installments'])->pluck('id')->all();
                foreach ($data['selected_ids'] as $selId) {
                    if (in_array($selId, $allowedIds, true)) {
                        $targetIds[] = $selId;
                    }
                }
            }
        }

        $targetIds = array_values(array_unique($targetIds));

        $res = $service->settleMultiple(
            $targetIds,
            $gateway,
            null,
            $notifyCustomer,
            $paidAt,
            $notes,
            $tenant->id
        );

        $count = $res['count'];
        $totalFormatted = number_format($res['total_amount'], 2, ',', '.');
        $msg = $count > 1
            ? "Baixa realizada com sucesso em {$count} parcelas vinculadas (Total: R$ {$totalFormatted})."
            : "Fatura #{$invoice->number} baixada como paga com sucesso (R$ {$totalFormatted}).";

        return response()->json([
            'ok'           => true,
            'message'      => $msg,
            'settled_ids'  => $res['settled_ids'],
            'count'        => $count,
            'total_amount' => $res['total_amount'],
        ]);
    }

    public function destroyInvoice(int $id): JsonResponse
    {
        $tenant = $this->tenant();
        $invoice = \App\Models\Invoice::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['ok' => false, 'error' => 'Faturas pagas não podem ser excluídas.'], 422);
        }

        \App\Models\BillingDispatch::where('invoice_id', $invoice->id)->delete();
        $number = $invoice->number;
        $invoice->delete();

        return response()->json(['ok' => true, 'message' => "Fatura #{$number} excluída com sucesso."]);
    }

    public function mergeInvoices(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'ids'         => 'required|array|min:2',
            'ids.*'       => 'integer',
            'due_date'    => 'nullable|date',
            'description' => 'nullable|string|max:255',
        ]);

        $ids = $data['ids'];
        $invoices = \App\Models\Invoice::with('customer')
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->get();

        if ($invoices->count() < 2) {
            return response()->json(['ok' => false, 'error' => 'Selecione ao menos 2 faturas válidas para mesclar.'], 422);
        }

        // Valida mesmo cliente
        $customerIds = $invoices->pluck('customer_id')->unique();
        if ($customerIds->count() > 1) {
            return response()->json([
                'ok' => false,
                'error' => 'Não é possível mesclar faturas de clientes diferentes. Todas as faturas devem pertencer ao mesmo cliente.',
            ], 422);
        }

        // Valida status não pago
        if ($invoices->where('status', 'paid')->isNotEmpty()) {
            return response()->json([
                'ok' => false,
                'error' => 'Uma ou mais faturas selecionadas já estão pagas e não podem ser mescladas.',
            ], 422);
        }

        $totalAmount = (float) $invoices->sum('amount');
        $customer = $invoices->first()->customer;
        $mergedNumbers = $invoices->pluck('number')->implode(', ');
        
        $desc = $data['description'] ?: 'Fatura unificada (mesclada de: ' . $mergedNumbers . ')';
        $dueDate = !empty($data['due_date']) 
            ? \Carbon\Carbon::parse($data['due_date'])->toDateString()
            : ($invoices->min('due_date')?->toDateString() ?: now()->toDateString());

        $maxId = (int) \App\Models\Invoice::where('tenant_id', $tenant->id)->max('id') + 1;
        $newNumber = 'FAT-' . date('Ym') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);

        $newInvoice = \App\Models\Invoice::create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer ? $customer->id : null,
            'number'      => $newNumber,
            'description' => $desc,
            'amount'      => $totalAmount,
            'due_date'    => $dueDate,
            'status'      => 'pending',
            'gateway'     => $invoices->first()->gateway,
            'meta'        => [
                'merged_from_ids'     => $invoices->pluck('id')->all(),
                'merged_from_numbers' => $invoices->pluck('number')->all(),
                'merged_at'           => now()->toIso8601String(),
            ],
        ]);

        foreach ($invoices as $inv) {
            $invMeta = is_array($inv->meta) ? $inv->meta : [];
            $invMeta['merged_into_id'] = $newInvoice->id;
            $invMeta['merged_into_number'] = $newNumber;

            $inv->update([
                'status'      => 'canceled',
                'meta'        => $invMeta,
                'description' => $inv->description . ' [Mesclada na fatura #' . $newNumber . ']',
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => "Faturas mescladas com sucesso! Nova fatura gerada: #{$newNumber} no valor de R$ " . number_format($totalAmount, 2, ',', '.'),
            'new_invoice_id' => $newInvoice->id,
        ]);
    }

    public function bulkInvoices(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'action'          => 'required|in:cancel,delete,anticipate,settle',
            'ids'             => 'required|array|min:1',
            'ids.*'           => 'integer',
            'due_date'        => 'nullable|date',
            'gateway'         => 'nullable|string|in:manual,pix,dinheiro,cartao,transferencia,boleto,outro',
            'paid_at'         => 'nullable|date',
            'notify_customer' => 'nullable|boolean',
            'notes'           => 'nullable|string|max:500',
        ]);

        $ids = $data['ids'];
        $action = $data['action'];

        $invoices = \App\Models\Invoice::where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'Nenhuma fatura encontrada.'], 404);
        }

        if ($action === 'cancel') {
            $count = 0;
            foreach ($invoices as $inv) {
                if ($inv->status !== 'paid' && $inv->status !== 'canceled') {
                    $inv->update(['status' => 'canceled']);
                    $count++;
                }
            }
            return response()->json([
                'ok' => true,
                'message' => "{$count} fatura(s) cancelada(s) com sucesso.",
            ]);
        }

        if ($action === 'delete') {
            $count = 0;
            $skipped = 0;
            foreach ($invoices as $inv) {
                if ($inv->status === 'paid') {
                    $skipped++;
                    continue;
                }
                \App\Models\BillingDispatch::where('invoice_id', $inv->id)->delete();
                $inv->delete();
                $count++;
            }
            $msg = "{$count} fatura(s) excluída(s) com sucesso.";
            if ($skipped > 0) {
                $msg .= " ({$skipped} fatura(s) paga(s) foram preservadas).";
            }
            return response()->json(['ok' => true, 'message' => $msg]);
        }

        if ($action === 'anticipate') {
            if (empty($data['due_date'])) {
                return response()->json(['ok' => false, 'error' => 'Informe a nova data de vencimento.'], 422);
            }
            $newDue = \Carbon\Carbon::parse($data['due_date'], 'America/Sao_Paulo')->startOfDay();
            $count = 0;
            foreach ($invoices as $inv) {
                if ($inv->status !== 'paid') {
                    $invMeta = is_array($inv->meta) ? $inv->meta : [];
                    $invMeta['is_antecipated'] = true;
                    $invMeta['original_due_date'] = $inv->due_date?->toDateString();
                    $invMeta['antecipated_at'] = now()->toIso8601String();

                    $up = [
                        'due_date' => $newDue->toDateString(),
                        'meta'     => $invMeta,
                    ];
                    if ($inv->status === 'overdue' && ($newDue->isFuture() || $newDue->isToday())) {
                        $up['status'] = 'pending';
                    }
                    $inv->update($up);
                    $count++;
                }
            }
            return response()->json([
                'ok' => true,
                'message' => "Vencimento de {$count} fatura(s) atualizado para {$newDue->format('d/m/Y')}.",
            ]);
        }

        if ($action === 'settle') {
            $gateway = $data['gateway'] ?? 'manual';
            $paidAt = ! empty($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now();
            $notifyCustomer = $request->boolean('notify_customer', true);
            $notes = $data['notes'] ?? null;

            $res = app(\App\Modules\Billing\Services\InvoiceSettlementService::class)->settleMultiple(
                $ids,
                $gateway,
                null,
                $notifyCustomer,
                $paidAt,
                $notes,
                $tenant->id
            );

            $totalFormatted = number_format($res['total_amount'], 2, ',', '.');
            return response()->json([
                'ok'           => true,
                'message'      => "Baixa manual realizada com sucesso em {$res['count']} fatura(s) (Total: R$ {$totalFormatted}).",
                'settled_ids'  => $res['settled_ids'],
                'count'        => $res['count'],
                'total_amount' => $res['total_amount'],
            ]);
        }

        return response()->json(['ok' => false, 'error' => 'Ação não reconhecida.'], 400);
    }

    // === RELATORIO DE COBRANCAS E MENSAGENS ===
    public function reports(Request $request)
    {
        $tenant = $this->tenant();

        $query = \App\Models\Invoice::with(['customer'])
            ->where('tenant_id', $tenant->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('number', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$s}%"));
            });
        }

        $invoices = $query->orderByDesc('created_at')->paginate(25);

        // Busca dispatch ids destas invoices
        $messageStats = [];
        if ($invoices->isNotEmpty()) {
            $invoiceIds = $invoices->pluck('id');
            $dispatchIds = \App\Models\BillingDispatch::whereIn('invoice_id', $invoiceIds)->pluck('id', 'invoice_id');
            $dispatchIdList = $dispatchIds->values();

            if ($dispatchIdList->isNotEmpty()) {
                $waMsgCounts = \App\Models\WhatsAppMessage::whereIn('dispatch_id', $dispatchIdList)
                    ->selectRaw('dispatch_id, status, count(*) as total')
                    ->groupBy('dispatch_id', 'status')
                    ->get()
                    ->groupBy('dispatch_id');

                $emailMsgCounts = \App\Modules\EmailBilling\Models\EmailMessage::whereIn('dispatch_id', $dispatchIdList)
                    ->selectRaw('dispatch_id, status, count(*) as total')
                    ->groupBy('dispatch_id', 'status')
                    ->get()
                    ->groupBy('dispatch_id');
            } else {
                $waMsgCounts = collect();
                $emailMsgCounts = collect();
            }

            foreach ($invoiceIds as $iid) {
                $did = $dispatchIds[$iid] ?? null;
                $waRows = $did ? ($waMsgCounts[$did] ?? collect()) : collect();
                $emailRows = $did ? ($emailMsgCounts[$did] ?? collect()) : collect();
                $messageStats[$iid] = [
                    'wa_sent' => $waRows->whereIn('status', ['sent','delivered','read'])->sum('total'),
                    'wa_failed' => $waRows->where('status', 'failed')->sum('total'),
                    'email_sent' => $emailRows->whereIn('status', ['sent','delivered','opened','clicked'])->sum('total'),
                    'email_failed' => $emailRows->where('status', 'failed')->sum('total'),
                ];
            }
        }

        // Stats globais
        $stats = [
            'total_invoices' => \App\Models\Invoice::where('tenant_id', $tenant->id)->count(),
            'total_dispatches' => \App\Models\BillingDispatch::whereHas('invoice', fn ($q) => $q->where('tenant_id', $tenant->id))->count(),
            'whatsapp_sent' => \App\Models\WhatsAppMessage::whereHas('dispatch.invoice', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->whereIn('status', ['sent','delivered','read'])->count(),
            'whatsapp_failed' => \App\Models\WhatsAppMessage::whereHas('dispatch.invoice', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->where('status', 'failed')->count(),
            'email_sent' => \App\Modules\EmailBilling\Models\EmailMessage::where('tenant_id', $tenant->id)
                ->whereIn('status', ['sent','delivered','opened','clicked'])->count(),
            'email_failed' => \App\Modules\EmailBilling\Models\EmailMessage::where('tenant_id', $tenant->id)
                ->where('status', 'failed')->count(),
        ];

        return view('tenancy::tenant.reports', compact('tenant', 'invoices', 'stats', 'messageStats'));
    }

    // === ENVIO MANUAL WHATSAPP ===
    public function waManual(Request $request)
    {
        $tenant = $this->tenant();

        $templates = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $instances = TenantInstance::where('tenant_id', $tenant->id)
            ->orderByDesc('is_default')
            ->get();

        $hasConnectedInstance = $instances->where('status', 'connected')->isNotEmpty();
        $connectedInstance = $instances->where('status', 'connected')->first();

        $recentCustomers = Customer::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('tenancy::tenant.manual-whatsapp', compact(
            'tenant',
            'templates',
            'instances',
            'hasConnectedInstance',
            'connectedInstance',
            'recentCustomers'
        ));
    }

    // === HISTORICO WHATSAPP ===
    public function waHistory(Request $request)
    {
        $tenant = $this->tenant();

        $tenantFilter = function ($q) use ($tenant) {
            $q->whereHas('dispatch.invoice', fn ($iq) => $iq->where('tenant_id', $tenant->id))
              ->orWhereHas('tenantInstance', fn ($tiq) => $tiq->where('tenant_id', $tenant->id))
              ->orWhereJsonContains('meta->tenant_id', $tenant->id);
        };

        $query = WhatsAppMessage::with(['dispatch.invoice.customer', 'tenantInstance'])
            ->where($tenantFilter)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('to', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%")
                  ->orWhereHas('dispatch.invoice', fn ($iq) => $iq->where('number', 'like', "%{$s}%"))
                  ->orWhereHas('dispatch.invoice.customer', fn ($cq) => $cq->where('name', 'like', "%{$s}%"))
                  ->orWhere('meta->customer_name', 'like', "%{$s}%");
            });
        }

        $messages = $query->paginate(30);

        $baseCountQuery = fn () => WhatsAppMessage::where($tenantFilter);

        $stats = [
            'total' => $baseCountQuery()->count(),
            'sent' => $baseCountQuery()->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'failed' => $baseCountQuery()->where('status', 'failed')->count(),
            'queued' => $baseCountQuery()->where('status', 'queued')->count(),
        ];

        $templates = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $hasConnectedInstance = TenantInstance::where('tenant_id', $tenant->id)
            ->where('status', 'connected')
            ->exists();

        return view('tenancy::tenant.history-whatsapp', compact('tenant', 'messages', 'stats', 'templates', 'hasConnectedInstance'));
    }

    public function waCustomerSearch(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $q = trim((string) $request->input('q', ''));

        $query = Customer::query()
            ->where('tenant_id', $tenant->id)
            ->where('active', true);

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

        $items = $customers->map(function (Customer $customer) use ($tenant) {
            $invoice = Invoice::query()
                ->where('tenant_id', $tenant->id)
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
                    '{empresa}' => $tenant->company_name ?: config('app.name'),
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

    public function waRenderTemplate(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $templateId = (int) $request->input('template_id');
        $customerId = (int) $request->input('customer_id');

        $template = WhatsAppTemplate::query()
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->find($templateId);

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

        $customer = Customer::where('tenant_id', $tenant->id)->find($customerId);
        if (! $customer) {
            return response()->json([
                'success' => true,
                'template_name' => $template->name,
                'rendered_text' => $rawText,
            ]);
        }

        $invoice = Invoice::query()
            ->where('tenant_id', $tenant->id)
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
            'empresa' => $tenant->company_name ?: config('app.name'),
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

    public function waSendManual(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
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
            $customer = ! empty($data['customer_id'])
                ? Customer::where('tenant_id', $tenant->id)->find($data['customer_id'])
                : null;

            $template = WhatsAppTemplate::where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where('active', true)
                ->find($data['template_id']);

            if (! $template) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Nenhuma mensagem pode ser enviada sem que tenha um template válido ativo selecionado.',
                ], 422);
            }

            // Busca instância conectada do tenant
            $instance = TenantInstance::where('tenant_id', $tenant->id)
                ->where('status', 'connected')
                ->orderByDesc('is_default')
                ->first();

            if (! $instance) {
                $anyInstance = TenantInstance::where('tenant_id', $tenant->id)->first();
                if (! $anyInstance) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Você não possui uma instância de WhatsApp cadastrada. Acesse "Minhas Instâncias" para criar e conectar seu WhatsApp.',
                    ], 422);
                }

                return response()->json([
                    'ok' => false,
                    'message' => 'Sua instância do WhatsApp (' . ($anyInstance->display_name ?: $anyInstance->instance_name) . ') não está conectada. Conecte-a em "Minhas Instâncias" para realizar envios.',
                ], 422);
            }

            $context = [
                'origin' => 'tenant_manual_send',
                'is_manual' => true,
                'campaign' => null,
                'tenant_id' => $tenant->id,
                'tenant_instance_id' => $instance->id,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'template_id' => $template?->id,
                'template_slug' => $template?->slug,
            ];

            $safety = app(WhatsAppSafetyService::class);
            $inspection = $safety->inspect($instance, $cleanTo, $data['message'], $context);

            if (! $inspection['allowed']) {
                $safety->recordOutcome($inspection, [
                    'tenant_id' => $tenant->id,
                    'tenant_instance_id' => $instance->id,
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

            $id = app(InstanceManager::class)->sendText($instance, $cleanTo, $data['message'], array_merge($context, $inspection, [
                'skip_safety' => true,
                'is_manual' => true,
                'delay_seconds' => 0,
            ]));

            if (! $id) {
                return response()->json(['ok' => false, 'message' => 'Não foi possível enviar a mensagem. Verifique a conexão da sua instância de WhatsApp.'], 500);
            }

            // Se for cobrança ou template com Pix e a opção de enviar imagem estiver ativa, envia a imagem do QR Code
            $sendQrCode = (bool) (config('whatsapp.send_pix_qrcode_image', true) || config('whatsapp.send_separate_media', false));
            if ($sendQrCode && $customer) {
                $invoice = Invoice::query()
                    ->where('tenant_id', $tenant->id)
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
                                app(InstanceManager::class)->sendImage($instance, $cleanTo, $qrImage, $qrCaption, array_merge($context, [
                                    'media_type' => 'image',
                                    'origin' => 'tenant_manual_qrcode',
                                    'skip_safety' => true,
                                    'is_manual' => true,
                                    'delay_seconds' => 0,
                                ]));
                            } catch (\Throwable $e) {
                                Log::warning('Erro ao enviar imagem do QR Code em envio manual tenant: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'message_id' => $id,
                'meta_message_id' => $id,
                'to' => $cleanTo,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar mensagem manual WhatsApp no tenant: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()], 422);
        }
    }

    // === HISTORICO EMAIL ===
    public function emailHistory(Request $request)
    {
        $tenant = $this->tenant();

        $query = \App\Modules\EmailBilling\Models\EmailMessage::with(['dispatch.invoice.customer'])
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('to', 'like', "%{$s}%")
                  ->orWhere('subject', 'like', "%{$s}%");
            });
        }

        $messages = $query->paginate(30);

        $stats = [
            'total' => \App\Modules\EmailBilling\Models\EmailMessage::where('tenant_id', $tenant->id)->count(),
            'sent' => \App\Modules\EmailBilling\Models\EmailMessage::where('tenant_id', $tenant->id)
                ->whereIn('status', ['sent','delivered','opened','clicked'])->count(),
            'failed' => \App\Modules\EmailBilling\Models\EmailMessage::where('tenant_id', $tenant->id)
                ->where('status', 'failed')->count(),
            'queued' => \App\Modules\EmailBilling\Models\EmailMessage::where('tenant_id', $tenant->id)
                ->where('status', 'queued')->count(),
        ];

        $tenantBranding = [];
        if (class_exists(\App\Modules\EmailBilling\Services\EmailLayoutRenderer::class)) {
            try {
                $tenantBranding = app(\App\Modules\EmailBilling\Services\EmailLayoutRenderer::class)->brandingFromTenantConfig($tenant->emailConfig, $tenant);
            } catch (\Throwable) {}
        }

        return view('tenancy::tenant.history-email', compact('tenant', 'messages', 'stats', 'tenantBranding'));
    }

    // === SELECAO DE PLANOS ===
    public function plans()
    {
        $tenant = $this->tenant();
        $currentPlan = $tenant->plan;
        $plans = Plan::where('active', true)
            ->where('max_instances', '<=', 10)
            ->orderBy('sort')
            ->orderBy('price')
            ->get();

        return view('tenancy::tenant.plans', compact('tenant', 'currentPlan', 'plans'));
    }

    public function subscribePlan(Request $request, InvoicePaymentService $paymentService)
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        // Criar fatura para o novo plano
        $invoice = TenantInvoice::create([
            'tenant_id' => $tenant->id,
            'number' => 'PLN-' . time(),
            'amount' => $plan->price,
            'description' => 'Assinatura do plano ' . $plan->name,
            'due_date' => now()->addDays(7),
            'status' => 'pending',
            'meta' => [
                'line_items' => [[
                    'type' => 'plan_subscription',
                    'description' => 'Assinatura do plano ' . $plan->name,
                    'amount' => (float) $plan->price,
                ]],
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
            ],
        ]);

        // Criar cobrança via gateway de pagamento
        $paymentResult = $paymentService->hydrateGatewayPayment($invoice, force: true);

        if ($paymentResult['ok'] && $paymentResult['payment_url']) {
            // Atualizar o tenant com o novo plano (será ativado após pagamento)
            $tenant->update([
                'plan_id' => $plan->id,
            ]);

            return redirect()->away($paymentResult['payment_url']);
        }

        return redirect()->route('tenant.plans')->with('error', 'Erro ao criar cobranca. Tente novamente.');
    }

    // === GERENCIAMENTO DE CLIENTES ===
    public function customers()
    {
        $tenant = $this->tenant();
        $customers = Customer::withCount(['installments' => fn ($q) => $q->whereIn('status', ['pending', 'overdue'])])
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('tenancy::tenant.customers', compact('tenant', 'customers'));
    }

    public function syncWhmcs(): JsonResponse
    {
        $tenant = $this->tenant();

        $connection = \App\Modules\WhmcsIntegration\Models\WhmcsConnection::query()
            ->where('tenant_id', $tenant->id)
            ->where('active', true)
            ->first();

        if (!$connection) {
            return response()->json([
                'ok' => false,
                'error' => 'Nenhuma conexão WHMCS ativa encontrada.',
            ], 422);
        }

        try {
            $client = new \App\Modules\WhmcsIntegration\Services\WhmcsClient($connection);
            $stats = $client->syncCustomers();

            return response()->json([
                'ok' => true,
                'message' => "Sincronização concluída! Foram importados {$stats['imported']} e atualizados {$stats['updated']} clientes.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erro ao sincronizar com WHMCS: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeCustomer(Request $request)
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'whatsapp'       => 'nullable|string|max:255',
            'document'       => 'nullable|string|max:255',
            'cpf'            => 'nullable|string|max:30',
            'cnpj'           => 'nullable|string|max:30',
            'birth_date'     => 'nullable|string|max:30',
            'person_type'    => 'nullable|string|max:20',
            'phone'          => 'nullable|string|max:255',
            'cep'            => 'nullable|string|max:20',
            'logradouro'     => 'nullable|string|max:255',
            'numero'         => 'nullable|string|max:50',
            'complemento'    => 'nullable|string|max:255',
            'bairro'         => 'nullable|string|max:255',
            'cidade'         => 'nullable|string|max:255',
            'uf'             => 'nullable|string|max:2',
            'whatsapp_optin' => 'nullable',
        ]);

        if (isset($data['whatsapp'])) {
            $data['whatsapp'] = preg_replace('/\D/', '', (string) $data['whatsapp']) ?: null;
        }
        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/\D/', '', (string) $data['phone']) ?: null;
        }
        if (isset($data['cep'])) {
            $data['cep'] = preg_replace('/\D/', '', (string) $data['cep']) ?: null;
        }
        if (!empty($data['cpf'])) {
            $data['cpf'] = preg_replace('/\D/', '', (string) $data['cpf']) ?: null;
        }
        if (!empty($data['cnpj'])) {
            $data['cnpj'] = preg_replace('/\D/', '', (string) $data['cnpj']) ?: null;
        }
        if (!empty($data['document'])) {
            $docDigits = preg_replace('/\D/', '', (string) $data['document']);
            if (strlen($docDigits) === 11 && empty($data['cpf'])) {
                $data['cpf'] = $docDigits;
            } elseif (strlen($docDigits) === 14 && empty($data['cnpj'])) {
                $data['cnpj'] = $docDigits;
            }
            $data['document'] = $docDigits ?: $data['document'];
        } elseif (!empty($data['cnpj'])) {
            $data['document'] = $data['cnpj'];
        } elseif (!empty($data['cpf'])) {
            $data['document'] = $data['cpf'];
        }

        if (!empty($data['birth_date'])) {
            if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', trim($data['birth_date']), $m)) {
                $data['birth_date'] = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
        } else {
            $data['birth_date'] = null;
        }

        $data['whatsapp_optin'] = $request->has('whatsapp_optin') ? $request->boolean('whatsapp_optin') : true;

        $meta = [
            'birth_date'     => $data['birth_date'],
            'cpf'            => $data['cpf'] ?? null,
            'cnpj'           => $data['cnpj'] ?? null,
            'person_type'    => $data['person_type'] ?? null,
            'whatsapp_optin' => $data['whatsapp_optin'],
        ];

        Customer::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'meta'      => $meta,
            'active'    => $request->boolean('active', true),
        ]));

        return response()->json(['ok' => true, 'message' => 'Cliente cadastrado com sucesso.']);
    }

    public function showCustomer($id)
    {
        $tenant = $this->tenant();
        $customer = Customer::with(['installments' => fn ($q) => $q->orderBy('installment_number')])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $installmentStats = [
            'total' => $customer->installments->count(),
            'paid' => $customer->installments->where('status', 'paid')->count(),
            'pending' => $customer->installments->where('status', 'pending')->count(),
            'overdue' => $customer->installments->where('status', 'overdue')->count(),
        ];

        return response()->json([
            'customer' => $customer,
            'installments' => $customer->installments,
            'installment_stats' => $installmentStats,
        ]);
    }

    public function updateCustomer(Request $request, $id)
    {
        $tenant = $this->tenant();
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($id);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'whatsapp'       => 'nullable|string|max:255',
            'document'       => 'nullable|string|max:255',
            'cpf'            => 'nullable|string|max:30',
            'cnpj'           => 'nullable|string|max:30',
            'birth_date'     => 'nullable|string|max:30',
            'person_type'    => 'nullable|string|max:20',
            'phone'          => 'nullable|string|max:255',
            'cep'            => 'nullable|string|max:20',
            'logradouro'     => 'nullable|string|max:255',
            'numero'         => 'nullable|string|max:50',
            'complemento'    => 'nullable|string|max:255',
            'bairro'         => 'nullable|string|max:255',
            'cidade'         => 'nullable|string|max:255',
            'uf'             => 'nullable|string|max:2',
            'whatsapp_optin' => 'nullable',
        ]);

        if (isset($data['whatsapp'])) {
            $data['whatsapp'] = preg_replace('/\D/', '', (string) $data['whatsapp']) ?: null;
        }
        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/\D/', '', (string) $data['phone']) ?: null;
        }
        if (isset($data['cep'])) {
            $data['cep'] = preg_replace('/\D/', '', (string) $data['cep']) ?: null;
        }
        if (isset($data['cpf'])) {
            $data['cpf'] = preg_replace('/\D/', '', (string) $data['cpf']) ?: null;
        }
        if (isset($data['cnpj'])) {
            $data['cnpj'] = preg_replace('/\D/', '', (string) $data['cnpj']) ?: null;
        }
        if (!empty($data['document'])) {
            $docDigits = preg_replace('/\D/', '', (string) $data['document']);
            if (strlen($docDigits) === 11 && empty($data['cpf'])) {
                $data['cpf'] = $docDigits;
            } elseif (strlen($docDigits) === 14 && empty($data['cnpj'])) {
                $data['cnpj'] = $docDigits;
            }
            $data['document'] = $docDigits ?: $data['document'];
        } elseif (!empty($data['cnpj'])) {
            $data['document'] = $data['cnpj'];
        } elseif (!empty($data['cpf'])) {
            $data['document'] = $data['cpf'];
        }

        if (isset($data['birth_date'])) {
            if (!empty($data['birth_date']) && preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', trim($data['birth_date']), $m)) {
                $data['birth_date'] = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            } elseif (empty($data['birth_date'])) {
                $data['birth_date'] = null;
            }
        }

        $data['whatsapp_optin'] = $request->has('whatsapp_optin') ? $request->boolean('whatsapp_optin') : true;

        $meta = array_merge($customer->meta ?: [], [
            'birth_date'     => $data['birth_date'] ?? $customer->birth_date,
            'cpf'            => $data['cpf'] ?? $customer->cpf,
            'cnpj'           => $data['cnpj'] ?? $customer->cnpj,
            'person_type'    => $data['person_type'] ?? $customer->person_type,
            'whatsapp_optin' => $data['whatsapp_optin'],
        ]);

        $customer->update(array_merge($data, [
            'meta'   => $meta,
            'active' => $request->boolean('active', true),
        ]));

        return response()->json(['ok' => true, 'message' => 'Cliente atualizado com sucesso.']);
    }

    public function destroyCustomer($id)
    {
        $tenant = $this->tenant();
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($id);
        $customer->delete();

        return response()->json(['ok' => true, 'message' => 'Cliente excluído com sucesso.']);
    }

    // Envios agendados
    public function dispatches(Request $request)
    {
        $tenant = $this->tenant();
        $q = \App\Models\BillingDispatch::with('invoice.customer')
            ->whereHas('invoice', fn ($w) => $w->where('tenant_id', $tenant->id))
            ->orderByDesc('scheduled_at');
        if ($s = $request->query('status')) $q->where('status', $s);
        if ($search = $request->query('q')) {
            $q->whereHas('invoice', fn ($w) => $w->where('number', 'like', "%$search%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$search%")));
        }
        $dispatches = $q->paginate(50)->withQueryString();

        $stats = \Illuminate\Support\Facades\DB::table('billing_dispatches')
            ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
            ->where('invoices.tenant_id', $tenant->id)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN billing_dispatches.status='sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN billing_dispatches.status='scheduled' THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN billing_dispatches.status='failed' THEN 1 ELSE 0 END) AS failed
            ")->first();

        return view('tenancy::tenant.dispatches', compact('dispatches', 'stats'));
    }

    // Calendário de faturas e feriados
    public function calendar(Request $request)
    {
        $tenant = $this->tenant();
        $holidays = DB::table('holidays')
            ->where(function($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->where('active', true)
            ->orderBy('date')
            ->get();

        return view('tenancy::tenant.calendar', compact('tenant', 'holidays'));
    }

    public function calendarEvents(Request $request)
    {
        $tenant = $this->tenant();
        $start = $request->query('start');
        $end = $request->query('end');

        $invoices = \App\Models\Invoice::where('tenant_id', $tenant->id)
            ->whereBetween('due_date', [$start, $end])
            ->get()
            ->map(fn ($invoice) => [
                'id' => 'inv-' . $invoice->id,
                'title' => $invoice->number . ' - ' . ($invoice->customer->name ?? 'Sem cliente'),
                'start' => $invoice->due_date->format('Y-m-d'),
                'backgroundColor' => match($invoice->status) {
                    'paid' => '#198754',
                    'pending' => '#ffc107',
                    'overdue' => '#dc3545',
                    'cancelled' => '#6c757d',
                    default => '#0d6efd',
                },
                'borderColor' => match($invoice->status) {
                    'paid' => '#198754',
                    'pending' => '#ffc107',
                    'overdue' => '#dc3545',
                    'cancelled' => '#6c757d',
                    default => '#0d6efd',
                },
                'url' => route('tenant.invoices') . '?id=' . $invoice->id,
                'extendedProps' => [
                    'kind' => 'invoice',
                    'invoice_id' => $invoice->id,
                ],
            ]);

        $startYear = $start ? \Carbon\Carbon::parse($start)->year : now()->year;
        $endYear   = $end ? \Carbon\Carbon::parse($end)->year : $startYear;

        $holidaysSvc = app(\App\Modules\Holidays\Services\HolidayService::class);
        $holidayEvents = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            foreach ($holidaysSvc->calendarEvents($y, $tenant->id) as $ev) {
                $ev['editable'] = false;
                $isGlobal = (bool) ($ev['extendedProps']['is_global'] ?? true);
                $color = $isGlobal ? '#6f42c1' : '#0dcaf0';
                $ev['color'] = $color;
                $ev['backgroundColor'] = $color;
                $ev['borderColor'] = $color;
                $ev['textColor'] = $isGlobal ? '#ffffff' : '#000000';
                $holidayEvents[] = $ev;
            }
        }

        $allEvents = array_merge($invoices->all(), $holidayEvents);

        return response()->json($allEvents);
    }

    public function moveCalendarEvent(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        
        $rawId = $request->input('id');
        $invoiceId = is_string($rawId) && str_starts_with($rawId, 'inv-') ? (int) substr($rawId, 4) : (int) $rawId;
        
        $data = $request->validate([
            'start' => 'required|date',
        ]);

        $invoice = \App\Models\Invoice::where('tenant_id', $tenant->id)->findOrFail($invoiceId);

        if (! in_array($invoice->status, ['pending', 'sent'])) {
            return response()->json(['ok' => false, 'message' => 'Fatura nao pode ser movida neste status.'], 422);
        }

        $dueDate = \Carbon\Carbon::parse($data['start'], 'America/Sao_Paulo')->startOfDay();

        $invoice->update(['due_date' => $dueDate->toDateString()]);

        return response()->json(['ok' => true]);
    }

    // Histórico de mensagens de cobrança
    public function billingMessageHistory(Request $request)
    {
        $tenant = $this->tenant();
        $history = app(\App\Modules\Billing\Services\BillingMessageHistoryService::class);
        $retentionDays = $history->retentionDays();
        $cutoff = now('America/Sao_Paulo')->subDays($retentionDays)->startOfDay();

        $query = $history->baseQuery()
            ->where('created_at', '>=', $cutoff)
            ->where('tenant_id', $tenant->id);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_whatsapp', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhere('provider_id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $statsQuery = clone $query;
        $statsRows = (clone $statsQuery)
            ->selectRaw('channel, status, COUNT(*) as total')
            ->groupBy('channel', 'status')
            ->get();

        $messages = $query
            ->orderByDesc('created_at')
            ->orderByDesc('source_id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => $statsRows->sum('total'),
            'whatsapp' => $statsRows->where('channel', 'whatsapp')->sum('total'),
            'email' => $statsRows->where('channel', 'email')->sum('total'),
            'sent' => $statsRows->whereIn('status', ['sent', 'delivered', 'read', 'opened', 'clicked'])->sum('total'),
            'failed' => $statsRows->whereIn('status', ['failed', 'bounced'])->sum('total'),
        ];

        $tenantBranding = [];
        if (class_exists(\App\Modules\EmailBilling\Services\EmailLayoutRenderer::class)) {
            try {
                $tenantBranding = app(\App\Modules\EmailBilling\Services\EmailLayoutRenderer::class)->brandingFromTenantConfig($tenant->emailConfig, $tenant);
            } catch (\Throwable) {}
        }

        return view('tenancy::tenant.billing-history', compact('messages', 'stats', 'retentionDays', 'cutoff', 'tenant', 'tenantBranding'));
    }
}
