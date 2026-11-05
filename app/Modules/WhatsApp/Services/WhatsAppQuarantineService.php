<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\WhatsAppInstanceQuarantine;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSafetyControl;
use App\Models\WhatsAppSafetyEvent;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Support\Facades\Schema;

class WhatsAppQuarantineService
{
    public function shouldQuarantine(TenantInstance $instance, array $context = []): bool
    {
        $control = $this->controlFor($instance);

        if (! $control) {
            return false;
        }

        $dailySent = $this->dailySentCount($instance);
        $dailyFailed = $this->dailyFailedCount($instance);
        $failureRate = $dailySent > 0 ? ($dailyFailed / $dailySent) * 100 : 0;

        if ($failureRate > 40) {
            return true;
        }

        $riskScore = $this->recentRiskScore($instance);
        if ($riskScore >= 90) {
            return true;
        }

        $contextStatus = $context['status'] ?? null;

        if ($contextStatus === 429) {
            return $dailySent > 10;
        }

        if (in_array($contextStatus, [401, 403], true)) {
            return true;
        }

        return false;
    }

    public function quarantine(TenantInstance $instance, string $reason, ?string $mode = null, ?int $durationMinutes = null): WhatsAppInstanceQuarantine
    {
        $mode = $mode ?: $this->determineMode($reason);
        $durationMinutes = $durationMinutes ?: $this->durationForMode($mode);

        $record = WhatsAppInstanceQuarantine::query()->create([
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'mode' => $mode,
            'reason' => $reason,
            'quarantine_until' => now()->addMinutes($durationMinutes),
            'active' => true,
        ]);

        $control = $this->controlFor($instance);
        if ($control) {
            $control->forceFill([
                'sending_paused' => true,
                'paused_until' => $record->quarantine_until,
                'quarantine_reason' => $reason,
                'quarantine_until' => $record->quarantine_until,
            ])->save();
        }

        WhatsAppSafetyEvent::query()->create([
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'event_type' => 'quarantine',
            'reason' => $reason,
            'risk_score' => 95,
            'risk_level' => 'critico',
            'meta' => ['mode' => $mode, 'duration_minutes' => $durationMinutes],
        ]);

        return $record;
    }

    public function release(TenantInstance $instance, ?int $releasedBy = null, string $reason = 'manual_release'): void
    {
        WhatsAppInstanceQuarantine::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('active', true)
            ->update([
                'active' => false,
                'released_by' => $releasedBy,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);

        $control = $this->controlFor($instance);
        if ($control) {
            $control->forceFill([
                'sending_paused' => false,
                'paused_until' => null,
                'quarantine_until' => null,
                'quarantine_reason' => null,
            ])->save();
        }

        WhatsAppSafetyEvent::query()->create([
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'event_type' => 'quarantine_released',
            'reason' => $reason,
            'risk_score' => 0,
            'risk_level' => 'baixo',
            'meta' => ['released_by' => $releasedBy],
        ]);
    }

    public function isQuarantined(TenantInstance $instance): bool
    {
        return WhatsAppInstanceQuarantine::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('quarantine_until')
                  ->orWhere('quarantine_until', '>', now());
            })
            ->exists();
    }

    public function reason(TenantInstance $instance): ?string
    {
        $record = WhatsAppInstanceQuarantine::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('quarantine_until')
                  ->orWhere('quarantine_until', '>', now());
            })
            ->latest()
            ->first();

        return $record?->reason;
    }

    public function activeQuarantines(): array
    {
        return WhatsAppInstanceQuarantine::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('quarantine_until')
                  ->orWhere('quarantine_until', '>', now());
            })
            ->with('tenantInstance')
            ->get()
            ->toArray();
    }

    protected function controlFor(TenantInstance $instance): ?WhatsAppSafetyControl
    {
        if (! Schema::hasTable('whatsapp_safety_controls')) {
            return null;
        }

        return WhatsAppSafetyControl::query()->firstOrCreate(
            ['tenant_instance_id' => $instance->id],
            ['tenant_id' => $instance->tenant_id]
        );
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

    protected function recentRiskScore(TenantInstance $instance): int
    {
        if (! Schema::hasTable('whatsapp_safety_events')) {
            return 0;
        }

        return (int) WhatsAppSafetyEvent::query()
            ->where('tenant_instance_id', $instance->id)
            ->where('created_at', '>=', now()->subHour())
            ->avg('risk_score') ?: 0;
    }

    protected function determineMode(string $reason): string
    {
        return match (true) {
            str_contains($reason, '429') || str_contains($reason, 'rate_limit') => 'rate_limit_pause',
            str_contains($reason, '403') || str_contains($reason, 'forbidden') => 'forbidden_lock',
            str_contains($reason, '401') || str_contains($reason, 'unauthorized') => 'token_lock',
            str_contains($reason, 'quality') || str_contains($reason, 'failure') => 'quality_lock',
            default => 'soft_pause',
        };
    }

    protected function durationForMode(string $mode): int
    {
        return match ($mode) {
            'rate_limit_pause' => 30,
            'forbidden_lock' => 120,
            'token_lock' => 1440,
            'quality_lock' => 240,
            default => 60,
        };
    }
}
