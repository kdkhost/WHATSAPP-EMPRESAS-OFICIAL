<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSafetyControl;
use App\Models\WhatsAppSafetyEvent;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\WhatsApp\Services\WhatsAppQuarantineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class WhatsAppQueuePlanner
{
    public function __construct(
        protected WhatsAppQuarantineService $quarantineService,
    ) {}

    public function plan(TenantInstance $instance, array $context = []): array
    {
        $control = $this->controlFor($instance);

        if ($this->quarantineService->isQuarantined($instance)) {
            return [
                'allowed' => false,
                'reason' => 'instance_quarantined',
                'delay_seconds' => 3600,
                'risk_score' => 100,
            ];
        }

        if ($control && $control->sending_disabled) {
            return [
                'allowed' => false,
                'reason' => 'sending_disabled',
                'delay_seconds' => null,
                'risk_score' => 100,
            ];
        }

        $concurrency = $this->resolveConcurrency($instance, $control);
        $activeJobs = $this->activeJobsCount($instance);

        if ($activeJobs >= $concurrency) {
            return [
                'allowed' => false,
                'reason' => 'concurrency_limit',
                'delay_seconds' => 15,
                'risk_score' => 60,
            ];
        }

        $baseDelay = $this->calculateBaseDelay($instance, $control, $context);
        $riskDelay = $this->riskAdjustedDelay($instance, $control);

        $delay = max($baseDelay, $riskDelay);

        $limits = $this->resolveTypeLimits($context);
        if ($limits['per_hour'] > 0 && $this->typeSentCount($instance, $context['media_type'] ?? 'text', now()->subHour()) >= $limits['per_hour']) {
            return [
                'allowed' => false,
                'reason' => 'type_hour_limit_reached',
                'delay_seconds' => max(600, $delay),
                'risk_score' => 75,
            ];
        }

        return [
            'allowed' => true,
            'delay_seconds' => $delay,
            'concurrency' => $concurrency,
            'risk_score' => $this->calculateRisk($instance, $control),
        ];
    }

    public function calculateDelay(TenantInstance $instance, array $context = []): int
    {
        $control = $this->controlFor($instance);
        $baseDelay = $this->calculateBaseDelay($instance, $control, $context);
        $riskDelay = $this->riskAdjustedDelay($instance, $control);

        return max($baseDelay, $riskDelay);
    }

    protected function calculateBaseDelay(TenantInstance $instance, ?WhatsAppSafetyControl $control, array $context): int
    {
        $min = (int) ($control?->human_delay_min_seconds ?: config('whatsapp_anti_ban.human_delay.min_seconds', 8));
        $max = (int) ($control?->human_delay_max_seconds ?: config('whatsapp_anti_ban.human_delay.max_seconds', 28));

        $mediaType = $context['media_type'] ?? 'text';
        if ($mediaType !== 'text') {
            $min += 5;
            $max += 15;
        }

        $isCampaign = ! empty($context['campaign']);
        if ($isCampaign) {
            $min += 3;
            $max += 10;
        }

        $volume = $this->dailySentCount($instance);
        if ($volume > 100) {
            $additionalDelay = (int) floor($volume / 50);
            $min += $additionalDelay;
            $max += $additionalDelay * 2;
        }

        if ($max < $min) {
            $max = $min + 5;
        }

        return random_int($min, $max);
    }

    protected function riskAdjustedDelay(TenantInstance $instance, ?WhatsAppSafetyControl $control): int
    {
        $riskScore = $this->calculateRisk($instance, $control);

        return match (true) {
            $riskScore >= 80 => 60,
            $riskScore >= 60 => 30,
            $riskScore >= 40 => 15,
            $riskScore >= 20 => 8,
            default => 0,
        };
    }

    protected function calculateRisk(TenantInstance $instance, ?WhatsAppSafetyControl $control): int
    {
        if (! $control) {
            return 0;
        }

        $dailySent = $this->dailySentCount($instance);
        $dailyLimit = (int) ($control->limit_per_day ?: config('whatsapp_anti_ban.limits.per_day', 250));

        $usageRatio = $dailyLimit > 0 ? $dailySent / $dailyLimit : 0;
        $risk = (int) round($usageRatio * 60);

        $dailyFailed = $this->dailyFailedCount($instance);
        if ($dailySent > 0 && $dailyFailed > 0) {
            $failureRate = ($dailyFailed / max(1, $dailySent)) * 100;
            if ($failureRate > 20) {
                $risk += 25;
            }
        }

        if ($control->last_rate_limit_at && $control->last_rate_limit_at->gt(now()->subDay())) {
            $risk += 15;
        }

        if ($control->last_forbidden_at && $control->last_forbidden_at->gt(now()->subDays(3))) {
            $risk += 30;
        }

        return min(100, $risk);
    }

    protected function resolveConcurrency(TenantInstance $instance, ?WhatsAppSafetyControl $control): int
    {
        if (! $control) {
            return 1;
        }

        $riskScore = $this->calculateRisk($instance, $control);

        return match (true) {
            $riskScore >= 80 => 1,
            $riskScore >= 50 => 2,
            $riskScore >= 20 => 3,
            default => 5,
        };
    }

    protected function resolveTypeLimits(array $context): array
    {
        $mediaType = $context['media_type'] ?? 'text';
        $typeConfig = config('whatsapp_anti_ban.type_limits', []);

        $limits = [];
        foreach (['text', 'image', 'audio', 'video', 'document', 'buttons', 'list', 'poll'] as $type) {
            $limits[$type] = ['per_hour' => (int) ($typeConfig[$type] ?? 35)];
        }

        return $limits[$mediaType] ?? $limits['text'];
    }

    protected function typeSentCount(TenantInstance $instance, string $mediaType, Carbon $from): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        return (int) WhatsAppMessage::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $from)
            ->where(function ($q) use ($mediaType) {
                if (Schema::hasColumn('whatsapp_messages', 'media_type')) {
                    $q->where('media_type', $mediaType);
                }
            })
            ->count();
    }

    protected function activeJobsCount(TenantInstance $instance): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        return (int) WhatsAppMessage::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('direction', 'out')
            ->where('status', 'sent')
            ->where(function ($q) {
                if (Schema::hasColumn('whatsapp_messages', 'delivered_at')) {
                    $q->whereNull('delivered_at');
                }
                if (Schema::hasColumn('whatsapp_messages', 'failed_at')) {
                    $q->whereNull('failed_at');
                }
            })
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
    }

    protected function dailySentCount(TenantInstance $instance): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        return (int) WhatsAppMessage::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    protected function dailyFailedCount(TenantInstance $instance): int
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }

        return (int) WhatsAppMessage::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('direction', 'out')
            ->where('status', 'failed')
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    protected function controlFor(TenantInstance $instance): ?WhatsAppSafetyControl
    {
        if (! Schema::hasTable('whatsapp_safety_controls')) {
            return null;
        }

        return WhatsAppSafetyControl::query()->where('tenant_instance_id', $instance->id)->first();
    }
}
