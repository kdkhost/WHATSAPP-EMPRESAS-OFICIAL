<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppRecipientPreference;
use App\Models\WhatsAppReputationScore;
use App\Models\WhatsAppSafetyControl;
use App\Models\WhatsAppSafetyEvent;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppSafetyService
{
    public function inspect(?TenantInstance $instance, string $to, string $message, array $context = []): array
    {
        if (! config('whatsapp_anti_ban.enabled', true)) {
            return $this->allowed('anti_ban_disabled', $instance, $to, $message, $context);
        }

        $message = trim($message);
        $normalizedTo = Format::whatsappNumber($to);
        $context['media_type'] = $this->normalizeMediaType($context['media_type'] ?? $context['type'] ?? 'text');
        $tenantId = $instance?->tenant_id ?: ($context['tenant_id'] ?? null);
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $control = $this->controlFor($instance, $tenantId ? (int) $tenantId : null);
        $customer = $this->resolveCustomer($context, $tenantId ? (int) $tenantId : null, $normalizedTo);

        $context['normalized_message_hash'] = $this->normalizedMessageHash($message);
        $context['template_hash'] = $this->templateHash($message);
        $context['campaign_hash'] = ($context['campaign'] ?? null) ? hash('sha256', $context['campaign']) : null;

        if ($this->isStopKeyword($message)) {
            $this->registerOptOut($tenantId ? (int) $tenantId : null, $customer?->id, $normalizedTo, 'stop_keyword');

            return $this->blocked('stop_keyword_detected', 100, $instance, $normalizedTo, $message, $context, [
                'tenant_id' => $tenantId,
                'customer_id' => $customer?->id,
            ]);
        }

        if ($instance && app(\App\Modules\WhatsApp\Services\WhatsAppQuarantineService::class)->isQuarantined($instance)) {
            return $this->blocked('instance_quarantined', 100, $instance, $normalizedTo, $message, $context);
        }

        if (mb_strlen($message) < (int) config('whatsapp_anti_ban.minimum_message_chars', 2)) {
            return $this->blocked('message_too_short', 100, $instance, $normalizedTo, $message, $context);
        }

        if ((bool) config('whatsapp_anti_ban.block_groups', true) && str_contains($to, '@g.us')) {
            return $this->blocked('group_send_blocked', 90, $instance, $normalizedTo, $message, $context);
        }

        $isManual = $this->isManualSend($context);

        if ($control && $control->sending_disabled) {
            return $this->blocked('instance_sending_disabled', 100, $instance, $normalizedTo, $message, $context);
        }

        if ($control && $control->campaigns_blocked && ! $isManual && ! empty($context['campaign'])) {
            return $this->blocked('campaigns_blocked', 90, $instance, $normalizedTo, $message, $context);
        }

        if ($control && $control->sending_paused && (! $control->paused_until || $control->paused_until->isFuture())) {
            return $this->deferred(
                'instance_paused',
                max(60, now()->diffInSeconds($control->paused_until ?: now()->addMinutes(10), false) * -1),
                85,
                $instance,
                $normalizedTo,
                $message,
                $context
            );
        }

        $preference = $this->preferenceFor($tenantId ? (int) $tenantId : null, $normalizedTo);
        if ($preference && ($preference->opt_out || $preference->blacklisted) && ! $preference->whitelisted) {
            return $this->blocked('recipient_opted_out', 100, $instance, $normalizedTo, $message, $context, [
                'tenant_id' => $tenantId,
                'customer_id' => $customer?->id,
            ]);
        }

        $timezone = (string) config('whatsapp_anti_ban.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($timezone);

        $respectBusinessHours = (bool) config('whatsapp_anti_ban.business_hours.enabled', true);
        if ($control && is_array($control->meta) && array_key_exists('respect_business_hours', $control->meta)) {
            $respectBusinessHours = (bool) $control->meta['respect_business_hours'];
        }

        // Envios manuais ou contas com janela de horário desativada não bloqueiam fora do horário
        if (! $isManual && $respectBusinessHours && ! $this->insideBusinessHours($now)) {
            $nextWindow = $this->nextBusinessWindow($now);

            return $this->deferred(
                'outside_business_hours',
                max(60, $now->diffInSeconds($nextWindow)),
                50,
                $instance,
                $normalizedTo,
                $message,
                $context,
                ['scheduled_for' => $nextWindow]
            );
        }

        $limits = $this->resolveLimits($instance, $control);

        if ($instance && $this->sentCount($instance, now()->subMinute()) >= $limits['per_minute']) {
            return $this->deferred('minute_limit_reached', $this->releaseSeconds(), 72, $instance, $normalizedTo, $message, $context);
        }

        if ($instance && $this->sentCount($instance, now()->subHour()) >= $limits['per_hour']) {
            return $this->deferred('hour_limit_reached', max(600, $this->releaseSeconds()), 82, $instance, $normalizedTo, $message, $context);
        }

        if ($instance && $this->sentCount($instance, now()->startOfDay()) >= $limits['per_day']) {
            return $this->deferred('day_limit_reached', 3600, 96, $instance, $normalizedTo, $message, $context);
        }

        if ($instance && $tenant && $this->tenantSentCount($tenant, now()->subDay()) >= ($limits['per_day'] * max(1, $tenant->instances()->count()))) {
            return $this->deferred('tenant_day_limit_reached', 3600, 93, $instance, $normalizedTo, $message, $context);
        }

        // Mensagens deliberadamente manuais e sequenciais de billing (QR Code, PIX) bypassam cooldowns de repetição
        $isBillingSequence = ($context['skip_safety'] ?? false) === true
            || str_starts_with((string) ($context['origin'] ?? ''), 'billing_dispatcher_')
            || $isManual;

        $cooldowns = $this->resolveCooldowns($control);

        $recipientCooldown = (int) ($cooldowns['recipient_minutes'] ?? 30);
        if (! $isBillingSequence && $recipientCooldown > 0 && $this->recentRecipientMessage($instance, $normalizedTo, now()->subMinutes($recipientCooldown))) {
            return $this->deferred('recipient_cooldown_active', $recipientCooldown * 60, 68, $instance, $normalizedTo, $message, $context);
        }

        $messageCooldown = (int) ($cooldowns['message_minutes'] ?? 45);
        if (! $isBillingSequence && $messageCooldown > 0 && $this->recentDuplicatedMessage($instance, $normalizedTo, $message, now()->subMinutes($messageCooldown))) {
            return $this->deferred('duplicated_message_cooldown', $messageCooldown * 60, 74, $instance, $normalizedTo, $message, $context);
        }

        if (! $isManual) {
            $this->checkRepeatDetection($instance, $normalizedTo, $message, $context);
        }

        $templateCooldown = (int) ($cooldowns['template_minutes'] ?? 120);
        if (! $isBillingSequence && $templateCooldown > 0 && ! empty($context['template_hash']) && $this->recentTemplateSend($instance, (string) $context['template_hash'], now()->subMinutes($templateCooldown))) {
            return $this->deferred('template_cooldown_active', $templateCooldown * 60, 72, $instance, $normalizedTo, $message, $context);
        }

        $campaignCooldown = (int) ($cooldowns['campaign_minutes'] ?? 90);
        if (! $isManual && $campaignCooldown > 0 && ! empty($context['campaign']) && $this->recentCampaignSend($instance, (string) $context['campaign'], $normalizedTo, now()->subMinutes($campaignCooldown))) {
            return $this->deferred('campaign_cooldown_active', $campaignCooldown * 60, 66, $instance, $normalizedTo, $message, $context);
        }

        $mediaCooldown = (int) ($cooldowns['media_minutes'] ?? 20);
        if (! $isManual && $mediaCooldown > 0 && ($context['media_type'] ?? 'text') !== 'text' && $this->recentMediaSend($instance, $normalizedTo, (string) $context['media_type'], now()->subMinutes($mediaCooldown))) {
            return $this->deferred('media_cooldown_active', $mediaCooldown * 60, 70, $instance, $normalizedTo, $message, $context);
        }

        $typeLimit = $this->typeLimitPerHour($context['media_type'] ?? 'text');
        if (! $isManual && $typeLimit > 0 && $instance && $this->typeSentCount($instance, $context['media_type'] ?? 'text', now()->subHour()) >= $typeLimit) {
            return $this->deferred('type_hour_limit_reached', max(600, $this->releaseSeconds()), 78, $instance, $normalizedTo, $message, $context);
        }

        $riskScore = $this->calculateRiskScore($instance, $limits['per_day'], $normalizedTo, $message, $context, $control);
        if (! $isManual && $riskScore >= (int) config('whatsapp_anti_ban.risk_thresholds.block_above', 95)) {
            return $this->blocked('risk_score_blocked', $riskScore, $instance, $normalizedTo, $message, $context);
        }

        return $this->allowed('safe_to_send', $instance, $normalizedTo, $message, $context, [
            'tenant_id' => $tenantId,
            'customer_id' => $customer?->id,
            'risk_score' => $riskScore,
            'risk_level' => $this->riskLevel($riskScore),
            'message_hash' => $this->messageHash($message),
            'recipient_hash' => $this->recipientHash($normalizedTo),
        ]);
    }

    public function humanDelay(?TenantInstance $instance = null): int
    {
        if (! config('whatsapp_anti_ban.human_delay.enabled', true)) {
            return 0;
        }

        $control = $this->controlFor($instance, $instance?->tenant_id);
        $min = (int) ($control?->human_delay_min_seconds ?: config('whatsapp_anti_ban.human_delay.min_seconds', 8));
        $max = (int) ($control?->human_delay_max_seconds ?: config('whatsapp_anti_ban.human_delay.max_seconds', 28));

        if ($max < $min) {
            $max = $min;
        }

        return random_int($min, $max);
    }

    public function markOptOut(?int $tenantId, ?int $customerId, string $phone, string $reason = 'manual_opt_out'): WhatsAppRecipientPreference
    {
        return $this->registerOptOut($tenantId, $customerId, $phone, $reason);
    }

    public function handleInboundOptOut(?TenantInstance $instance, string $phone, string $message, array $context = []): ?WhatsAppRecipientPreference
    {
        if (! $this->isStopKeyword($message)) {
            return null;
        }

        $tenantId = $instance?->tenant_id ?: ($context['tenant_id'] ?? null);
        $normalizedPhone = Format::whatsappNumber($phone);
        $customer = $this->resolveCustomer($context, $tenantId ? (int) $tenantId : null, $normalizedPhone);

        return $this->registerOptOut(
            $tenantId ? (int) $tenantId : null,
            $customer?->id,
            $normalizedPhone,
            (string) ($context['reason'] ?? 'inbound_stop_keyword')
        );
    }

    public function controlFor(?TenantInstance $instance, ?int $tenantId = null): ?WhatsAppSafetyControl
    {
        if (! Schema::hasTable('whatsapp_safety_controls')) {
            return null;
        }

        if ($instance?->id) {
            $control = WhatsAppSafetyControl::where('tenant_instance_id', $instance->id)->first();
            if ($control) {
                return $control;
            }
            if ($instance->tenant_id) {
                return WhatsAppSafetyControl::where('tenant_id', $instance->tenant_id)->whereNull('tenant_instance_id')->first();
            }
        } elseif ($tenantId) {
            return WhatsAppSafetyControl::where('tenant_id', $tenantId)->whereNull('tenant_instance_id')->first();
        }

        return null;
    }

    public function recordOutcome(array $inspection, array $context = []): void
    {
        if (! Schema::hasTable('whatsapp_safety_events')) {
            return;
        }

        $data = [
            'tenant_id' => $inspection['tenant_id'] ?? $context['tenant_id'] ?? null,
            'tenant_instance_id' => $inspection['tenant_instance_id'] ?? $context['tenant_instance_id'] ?? null,
            'message_id' => $context['message_id'] ?? null,
            'event_type' => $inspection['event_type'] ?? ($inspection['allowed'] ? 'allowed' : ($inspection['defer'] ? 'deferred' : 'blocked')),
            'reason' => $inspection['reason'] ?? null,
            'risk_score' => $inspection['risk_score'] ?? 0,
            'risk_level' => $inspection['risk_level'] ?? $this->riskLevel((int) ($inspection['risk_score'] ?? 0)),
            'recipient' => $context['to'] ?? null,
            'recipient_hash' => $inspection['recipient_hash'] ?? null,
            'message_hash' => $inspection['message_hash'] ?? null,
            'scheduled_for' => $inspection['scheduled_for'] ?? null,
            'meta' => $context['meta'] ?? null,
        ];

        foreach (['normalized_message_hash', 'template_hash', 'campaign_hash'] as $hashField) {
            if (Schema::hasColumn('whatsapp_safety_events', $hashField)) {
                $data[$hashField] = $inspection[$hashField] ?? $context[$hashField] ?? null;
            }
        }

        foreach (['media_type', 'trust_score', 'quality_score'] as $column) {
            if (Schema::hasColumn('whatsapp_safety_events', $column)) {
                $data[$column] = $inspection[$column] ?? ($column === 'media_type' ? data_get($context, 'meta.media_type') : null);
            }
        }

        WhatsAppSafetyEvent::create($data);
        $this->updateReputation($inspection, $context);
    }

    protected function allowed(string $reason, ?TenantInstance $instance, string $to, string $message, array $context, array $extra = []): array
    {
        return $this->withReputationScores(array_merge([
            'allowed' => true,
            'defer' => false,
            'event_type' => 'allowed',
            'reason' => $reason,
            'release_seconds' => 0,
            'risk_score' => $extra['risk_score'] ?? 0,
            'risk_level' => $extra['risk_level'] ?? $this->riskLevel((int) ($extra['risk_score'] ?? 0)),
            'tenant_id' => $extra['tenant_id'] ?? $instance?->tenant_id ?? $context['tenant_id'] ?? null,
            'tenant_instance_id' => $instance?->id,
            'message_hash' => $extra['message_hash'] ?? $this->messageHash($message),
            'recipient_hash' => $extra['recipient_hash'] ?? $this->recipientHash($to),
            'normalized_message_hash' => $context['normalized_message_hash'] ?? $this->normalizedMessageHash($message),
            'template_hash' => $context['template_hash'] ?? $this->templateHash($message),
            'campaign_hash' => $context['campaign_hash'] ?? null,
        ], $extra), $context);
    }

    protected function blocked(string $reason, int $risk, ?TenantInstance $instance, string $to, string $message, array $context, array $extra = []): array
    {
        return $this->withReputationScores(array_merge([
            'allowed' => false,
            'defer' => false,
            'event_type' => 'blocked',
            'reason' => $reason,
            'release_seconds' => 0,
            'risk_score' => $risk,
            'risk_level' => $this->riskLevel($risk),
            'tenant_id' => $extra['tenant_id'] ?? $instance?->tenant_id ?? $context['tenant_id'] ?? null,
            'tenant_instance_id' => $instance?->id,
            'message_hash' => $this->messageHash($message),
            'recipient_hash' => $this->recipientHash($to),
        ], $extra), $context);
    }

    protected function deferred(string $reason, int $seconds, int $risk, ?TenantInstance $instance, string $to, string $message, array $context, array $extra = []): array
    {
        $seconds = max(60, $seconds);

        return $this->withReputationScores(array_merge([
            'allowed' => false,
            'defer' => true,
            'event_type' => 'deferred',
            'reason' => $reason,
            'release_seconds' => $seconds,
            'risk_score' => $risk,
            'risk_level' => $this->riskLevel($risk),
            'tenant_id' => $instance?->tenant_id ?? $context['tenant_id'] ?? null,
            'tenant_instance_id' => $instance?->id,
            'message_hash' => $this->messageHash($message),
            'recipient_hash' => $this->recipientHash($to),
            'scheduled_for' => $extra['scheduled_for'] ?? now()->addSeconds($seconds),
        ], $extra), $context);
    }

    protected function resolveLimits(?TenantInstance $instance, ?WhatsAppSafetyControl $control = null): array
    {
        $maxAllowedDaily = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_day', 500);
        $dailyLimit = (int) ($control?->limit_per_day ?: config('whatsapp_anti_ban.limits.per_day', 250));

        if ($maxAllowedDaily > 0 && $dailyLimit > $maxAllowedDaily) {
            $dailyLimit = $maxAllowedDaily;
        }

        if ($instance && $this->warmupEnabled($control) && $instance->created_at) {
            $warmupLimit = $this->warmupLimit($instance);
            if ($warmupLimit > 0) {
                $dailyLimit = min($dailyLimit, $warmupLimit);
            }
        }

        $maxAllowedPerMinute = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_minute', 6);
        $perMinute = (int) ($control?->limit_per_minute ?: config('whatsapp_anti_ban.limits.per_minute', 2));
        if ($maxAllowedPerMinute > 0 && $perMinute > $maxAllowedPerMinute) {
            $perMinute = $maxAllowedPerMinute;
        }

        $maxAllowedPerHour = (int) config('whatsapp_anti_ban.tenant_settings.max_limit_per_hour', 90);
        $perHour = (int) ($control?->limit_per_hour ?: config('whatsapp_anti_ban.limits.per_hour', 35));
        if ($maxAllowedPerHour > 0 && $perHour > $maxAllowedPerHour) {
            $perHour = $maxAllowedPerHour;
        }

        return [
            'per_minute' => $perMinute,
            'per_hour' => $perHour,
            'per_day' => $dailyLimit,
        ];
    }

    protected function warmupEnabled(?WhatsAppSafetyControl $control): bool
    {
        if (! config('whatsapp_anti_ban.warmup.enabled', true)) {
            return false;
        }

        return $control?->warmup_enabled ?? true;
    }

    protected function warmupLimit(TenantInstance $instance): int
    {
        $days = max(1, $instance->created_at?->startOfDay()->diffInDays(now()->startOfDay()) + 1);
        $limits = config('whatsapp_anti_ban.warmup.daily_limits', [
            1 => 10, 2 => 20, 3 => 30, 7 => 50, 15 => 100, 30 => 150,
        ]);

        ksort($limits);

        foreach ($limits as $untilDay => $limit) {
            if ($days <= (int) $untilDay) {
                return (int) $limit;
            }
        }

        return (int) config('whatsapp_anti_ban.limits.per_day', 250);
    }

    public function isManualSend(array $context): bool
    {
        if (($context['is_manual'] ?? false) === true) {
            return true;
        }

        $origin = (string) ($context['origin'] ?? '');
        if (in_array($origin, [
            'tenant_manual_send',
            'tenant_manual',
            'tenant_manual_qrcode',
            'admin_manual_send',
            'admin_send_test',
            'billing_dispatcher_manual',
            'invoice_manual_send',
            'manual_send',
        ], true)) {
            return true;
        }

        return str_contains($origin, 'manual') || str_contains($origin, '_test');
    }

    public function resolveCooldowns(?WhatsAppSafetyControl $control): array
    {
        $meta = is_array($control?->meta) ? $control->meta : [];
        $riskMode = (string) ($meta['risk_mode'] ?? 'safe');

        if ($riskMode === 'accelerated') {
            return [
                'campaign_minutes' => 0,
                'recipient_minutes' => 0,
                'message_minutes' => 0,
                'template_minutes' => 0,
                'media_minutes' => 0,
            ];
        }

        if ($riskMode === 'moderate') {
            return [
                'campaign_minutes' => 15,
                'recipient_minutes' => 5,
                'message_minutes' => 10,
                'template_minutes' => 15,
                'media_minutes' => 5,
            ];
        }

        return [
            'campaign_minutes' => (int) config('whatsapp_anti_ban.cooldowns.campaign_minutes', 90),
            'recipient_minutes' => (int) config('whatsapp_anti_ban.recipient_cooldown_minutes', 30),
            'message_minutes' => (int) config('whatsapp_anti_ban.cooldowns.message_minutes', 45),
            'template_minutes' => (int) config('whatsapp_anti_ban.repeat_detection.cooldown_template_minutes', 120),
            'media_minutes' => (int) config('whatsapp_anti_ban.cooldowns.media_minutes', 20),
        ];
    }

    protected function calculateRiskScore(?TenantInstance $instance, int $dailyLimit, string $to, string $message, array $context, ?WhatsAppSafetyControl $control): int
    {
        if ($this->isManualSend($context)) {
            return 5;
        }

        $score = 0;

        if ($instance) {
            $score += min(55, (int) round(($this->sentCount($instance, now()->startOfDay()) / max(1, $dailyLimit)) * 55));
            $score += min(15, $this->sentCount($instance, now()->subHour()));
        }

        if ($this->recentDuplicatedMessage($instance, $to, $message, now()->subHours(6))) {
            $score += 18;
        }

        if (($context['campaign'] ?? null) !== null) {
            $score += 5;
        }

        $mediaType = $this->normalizeMediaType($context['media_type'] ?? 'text');
        if ($mediaType !== 'text') {
            $score += in_array($mediaType, ['audio', 'video', 'document'], true) ? 14 : 8;
        }

        if ($control?->campaigns_blocked) {
            $score += 30;
        }

        return min(100, $score);
    }

    protected function riskLevel(int $score): string
    {
        $medium = (int) config('whatsapp_anti_ban.risk_thresholds.medium', 21);
        $high = (int) config('whatsapp_anti_ban.risk_thresholds.high', 51);
        $critical = (int) config('whatsapp_anti_ban.risk_thresholds.critical', 81);

        return match (true) {
            $score >= $critical => 'critico',
            $score >= $high => 'alto',
            $score >= $medium => 'medio',
            default => 'baixo',
        };
    }

    protected function releaseSeconds(): int
    {
        return max(60, (int) config('whatsapp_anti_ban.release_when_limited_seconds', 300));
    }

    protected function insideBusinessHours(Carbon $now): bool
    {
        if (! config('whatsapp_anti_ban.business_hours.enabled', true)) {
            return true;
        }

        [$sh, $sm] = array_map('intval', explode(':', (string) config('whatsapp_anti_ban.business_hours.start', '08:00')));
        [$eh, $em] = array_map('intval', explode(':', (string) config('whatsapp_anti_ban.business_hours.end', '20:00')));

        $startAt = $now->copy()->setTime($sh, $sm);
        $endAt = $now->copy()->setTime($eh, $em);

        return $now->betweenIncluded($startAt, $endAt);
    }

    protected function nextBusinessWindow(Carbon $now): Carbon
    {
        [$sh, $sm] = array_map('intval', explode(':', (string) config('whatsapp_anti_ban.business_hours.start', '08:00')));

        $candidate = $now->copy()->setTime($sh, $sm);

        if ($now->greaterThanOrEqualTo($candidate)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    protected function sentCount(TenantInstance $instance, Carbon $from): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        return (int) WhatsAppMessage::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from)
            ->count();
    }

    protected function tenantSentCount(Tenant $tenant, Carbon $from): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        return (int) WhatsAppMessage::query()
            ->whereIn('tenant_instance_id', $tenant->instances()->pluck('id'))
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from)
            ->count();
    }

    protected function recentRecipientMessage(?TenantInstance $instance, string $to, Carbon $from): bool
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return false;
        }

        $query = WhatsAppMessage::query()
            ->where('recipient_hash', $this->recipientHash($to))
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from);

        if ($instance?->id) {
            $query->where('tenant_instance_id', $instance->id);
        }

        return $query->exists();
    }

    protected function recentDuplicatedMessage(?TenantInstance $instance, string $to, string $message, Carbon $from): bool
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return false;
        }

        $query = WhatsAppMessage::query()
            ->where('recipient_hash', $this->recipientHash($to))
            ->where('message_hash', $this->messageHash($message))
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from);

        if ($instance?->id) {
            $query->where('tenant_instance_id', $instance->id);
        }

        return $query->exists();
    }

    protected function recentCampaignSend(?TenantInstance $instance, string $campaign, string $to, Carbon $from): bool
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return false;
        }

        $query = WhatsAppMessage::query()
            ->where('direction', 'out')
            ->where('created_at', '>=', $from)
            ->where('meta->campaign', $campaign)
            ->where('recipient_hash', $this->recipientHash($to));

        if ($instance?->id) {
            $query->where('tenant_instance_id', $instance->id);
        }

        return $query->exists();
    }

    protected function recentMediaSend(?TenantInstance $instance, string $to, string $mediaType, Carbon $from): bool
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return false;
        }

        $query = WhatsAppMessage::query()
            ->where('recipient_hash', $this->recipientHash($to))
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from);

        if (Schema::hasColumn('whatsapp_messages', 'media_type')) {
            $query->where('media_type', $mediaType);
        } else {
            $query->where('meta->media_type', $mediaType);
        }

        if ($instance?->id) {
            $query->where('tenant_instance_id', $instance->id);
        }

        return $query->exists();
    }

    protected function preferenceFor(?int $tenantId, string $phone): ?WhatsAppRecipientPreference
    {
        if (! Schema::hasTable('whatsapp_recipient_preferences') || ! $tenantId) {
            return null;
        }

        return WhatsAppRecipientPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('phone_hash', $this->recipientHash($phone))
            ->first();
    }

    protected function registerOptOut(?int $tenantId, ?int $customerId, string $phone, string $reason): WhatsAppRecipientPreference
    {
        return WhatsAppRecipientPreference::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'phone_hash' => $this->recipientHash($phone),
            ],
            [
                'customer_id' => $customerId,
                'phone' => $phone,
                'opt_in' => false,
                'opt_out' => true,
                'opt_out_at' => now(),
                'opt_out_reason' => $reason,
            ]
        );
    }

    protected function resolveCustomer(array $context, ?int $tenantId, string $phone): ?Customer
    {
        $customerId = $context['customer_id'] ?? null;
        if ($customerId) {
            return Customer::find($customerId);
        }

        if (! $tenantId || ! Schema::hasColumn('customers', 'tenant_id')) {
            return null;
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->first(function (Customer $customer) use ($phone) {
                $digits = preg_replace('/\D/', '', (string) $customer->whatsapp) ?? '';

                return $digits === $phone;
            });
    }

    protected function checkRepeatDetection(?TenantInstance $instance, string $to, string $message, array $context): void
    {
        $windowMinutes = (int) config('whatsapp_anti_ban.repeat_detection.identical_window_minutes', 30);
        $maxIdentical = (int) config('whatsapp_anti_ban.repeat_detection.max_identical_per_window', 5);

        if ($windowMinutes <= 0 || $maxIdentical <= 0) {
            return;
        }

        $identicalCount = $this->identicalMessagesCount($instance, $message, now()->subMinutes($windowMinutes));
        if ($identicalCount >= $maxIdentical) {
            \Illuminate\Support\Facades\Log::warning('[WhatsAppSafety] Mensagem repetida detectada', [
                'tenant_instance_id' => $instance?->id,
                'message_hash' => $this->messageHash($message),
                'count' => $identicalCount,
                'window_minutes' => $windowMinutes,
            ]);
        }
    }

    protected function identicalMessagesCount(?TenantInstance $instance, string $message, $from): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        $query = WhatsAppMessage::query()
            ->where('message_hash', $this->messageHash($message))
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from);

        if ($instance?->id) {
            $query->where('tenant_instance_id', $instance->id);
        }

        return (int) $query->count();
    }

    protected function recentTemplateSend(?TenantInstance $instance, ?string $templateHash, $from): bool
    {
        if (! $templateHash || ! Schema::hasTable('whatsapp_messages')) {
            return false;
        }

        $column = Schema::hasColumn('whatsapp_messages', 'template_hash') ? 'template_hash' : null;
        if (! $column) {
            return false;
        }

        $query = WhatsAppMessage::query()
            ->where($column, $templateHash)
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from);

        if ($instance?->id) {
            $query->where('tenant_instance_id', $instance->id);
        }

        return $query->exists();
    }

    protected function typeLimitPerHour(string $mediaType): int
    {
        return (int) config("whatsapp_anti_ban.type_limits.{$mediaType}", 0);
    }

    protected function typeSentCount(TenantInstance $instance, string $mediaType, $from): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        $query = WhatsAppMessage::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from);

        if (Schema::hasColumn('whatsapp_messages', 'media_type')) {
            $query->where('media_type', $mediaType);
        }

        return (int) $query->count();
    }

    protected function normalizedMessageHash(string $message): string
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($message)));
        $normalized = preg_replace('/[^\w\s]/u', '', $normalized ?? '');

        return hash('sha256', $normalized ?? mb_strtolower(trim($message)));
    }

    protected function templateHash(string $message): string
    {
        $cleaned = preg_replace('/\d+/', '0', mb_strtolower(trim($message)));
        $cleaned = preg_replace('/\{[^}]+\}/', '{}', $cleaned ?? '');
        $cleaned = preg_replace('/\s+/', ' ', $cleaned ?? '');

        return hash('sha256', $cleaned ?? mb_strtolower(trim($message)));
    }

    protected function messageHash(string $message): string
    {
        return hash('sha256', mb_strtolower(trim($message)));
    }

    protected function recipientHash(string $recipient): string
    {
        return hash('sha256', Format::whatsappNumber($recipient));
    }

    protected function isStopKeyword(string $message): bool
    {
        return in_array(mb_strtolower(trim($message)), config('whatsapp_anti_ban.stop_keywords', []), true);
    }

    protected function withReputationScores(array $inspection, array $context): array
    {
        $risk = (int) ($inspection['risk_score'] ?? 0);
        $inspection['media_type'] = $this->normalizeMediaType($inspection['media_type'] ?? $context['media_type'] ?? 'text');
        $inspection['trust_score'] = (int) ($inspection['trust_score'] ?? max(0, 100 - $risk));
        $inspection['quality_score'] = (int) ($inspection['quality_score'] ?? match ($inspection['event_type'] ?? 'allowed') {
            'blocked' => max(0, 35 - (int) floor($risk / 4)),
            'deferred' => max(25, 70 - (int) floor($risk / 5)),
            default => max(45, 100 - (int) floor($risk / 3)),
        });

        return $inspection;
    }

    protected function normalizeMediaType(mixed $mediaType): string
    {
        $mediaType = mb_strtolower(trim((string) $mediaType));

        return in_array($mediaType, ['text', 'image', 'audio', 'document', 'video', 'contact', 'location', 'buttons', 'list', 'poll'], true)
            ? $mediaType
            : 'text';
    }

    protected function updateReputation(array $inspection, array $context): void
    {
        if (! Schema::hasTable('whatsapp_reputation_scores')) {
            return;
        }

        $tenantId = $inspection['tenant_id'] ?? $context['tenant_id'] ?? null;
        $instanceId = $inspection['tenant_instance_id'] ?? $context['tenant_instance_id'] ?? null;
        $recipientHash = $inspection['recipient_hash'] ?? null;
        $eventType = $inspection['event_type'] ?? 'allowed';

        foreach ([
            ['scope' => 'tenant', 'tenant_instance_id' => null, 'recipient_hash' => null],
            ['scope' => 'instance', 'tenant_instance_id' => $instanceId, 'recipient_hash' => null],
            ['scope' => 'recipient', 'tenant_instance_id' => $instanceId, 'recipient_hash' => $recipientHash],
        ] as $scope) {
            if ($scope['scope'] !== 'tenant' && ! $scope['tenant_instance_id']) {
                continue;
            }

            if ($scope['scope'] === 'recipient' && ! $scope['recipient_hash']) {
                continue;
            }

            $row = WhatsAppReputationScore::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'tenant_instance_id' => $scope['tenant_instance_id'],
                'scope' => $scope['scope'],
                'recipient_hash' => $scope['recipient_hash'],
            ]);

            if ($row->exists) {
                $row->risk_score = (int) round(((int) $row->risk_score + (int) ($inspection['risk_score'] ?? 0)) / 2);
                $row->trust_score = (int) round(((int) $row->trust_score + (int) ($inspection['trust_score'] ?? 100)) / 2);
                $row->quality_score = (int) round(((int) $row->quality_score + (int) ($inspection['quality_score'] ?? 100)) / 2);
            } else {
                $row->risk_score = (int) ($inspection['risk_score'] ?? 0);
                $row->trust_score = (int) ($inspection['trust_score'] ?? 100);
                $row->quality_score = (int) ($inspection['quality_score'] ?? 100);
            }
            $row->messages_sent = (int) ($row->messages_sent ?? 0) + ($eventType === 'allowed' ? 1 : 0);
            $row->messages_blocked = (int) ($row->messages_blocked ?? 0) + ($eventType === 'blocked' ? 1 : 0);
            $row->messages_deferred = (int) ($row->messages_deferred ?? 0) + ($eventType === 'deferred' ? 1 : 0);
            $row->last_event_at = now();
            $row->meta = array_filter([
                'last_reason' => $inspection['reason'] ?? null,
                'last_media_type' => $inspection['media_type'] ?? null,
            ]);
            $row->save();
        }

        $this->updateInstanceReputation($inspection, $context, $eventType);
    }

    protected function updateInstanceReputation(array $inspection, array $context, string $eventType): void
    {
        if (! Schema::hasTable('whatsapp_instance_reputations')) {
            return;
        }

        $instanceId = $inspection['tenant_instance_id'] ?? $context['tenant_instance_id'] ?? null;
        if (! $instanceId) {
            return;
        }

        $reputation = \App\Models\WhatsAppInstanceReputation::query()->firstOrNew([
            'tenant_instance_id' => $instanceId,
        ], [
            'tenant_id' => $inspection['tenant_id'] ?? $context['tenant_id'] ?? null,
        ]);

        if ($reputation->trust_score === null) {
            $reputation->trust_score = (int) ($inspection['trust_score'] ?? 100);
        }

        if ($eventType === 'allowed') {
            $reputation->daily_sent = ($reputation->daily_sent ?? 0) + 1;
            $reputation->daily_delivered = ($reputation->daily_delivered ?? 0) + (($context['delivered'] ?? false) ? 1 : 0);
            $reputation->trust_score = min(100, $reputation->trust_score + 1);
        } else {
            $reputation->daily_failed = ($reputation->daily_failed ?? 0) + 1;
            $reputation->trust_score = max(0, $reputation->trust_score - 5);
        }

        $reputation->risk_score = min(100, ($reputation->risk_score ?? 0) + ($inspection['risk_score'] ?? 0) / 2);
        $reputation->quality_score = max(0, min(100, (int) round(
            (($reputation->trust_score ?? 100) * 0.4)
            + (100 - $reputation->daily_failed / max(1, $reputation->daily_sent + $reputation->daily_failed) * 100) * 0.3
            + (100 - $reputation->risk_score) * 0.3
        )));

        $reputation->save();
    }
}
