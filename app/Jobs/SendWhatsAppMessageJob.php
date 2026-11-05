<?php

namespace App\Jobs;

use App\Models\WhatsAppSafetyEvent;
use App\Modules\WhatsApp\Services\WhatsAppQueuePlanner;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public int $tenantInstanceId,
        public string $to,
        public string $message,
    ) {
        $this->onQueue('whatsapp');
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(InstanceManager $instances, WhatsAppSafetyService $safety, WhatsAppQueuePlanner $planner): void
    {
        $instance = TenantInstance::findOrFail($this->tenantInstanceId);

        $plan = $planner->plan($instance, [
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'media_type' => 'text',
            'origin' => 'queue_job',
        ]);

        if (! $plan['allowed']) {
            $this->logPlannerDecision($instance, $plan);

            if ($plan['delay_seconds'] && $this->attempts() < $this->tries) {
                $this->release((int) $plan['delay_seconds']);
                return;
            }

            return;
        }

        $inspection = $safety->inspect($instance, $this->to, $this->message, [
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'origin' => 'queue_job',
            'delay_seconds' => $plan['delay_seconds'],
            'risk_score' => $plan['risk_score'],
        ]);

        if (! $inspection['allowed']) {
            $safety->recordOutcome($inspection, [
                'tenant_id' => $instance->tenant_id,
                'tenant_instance_id' => $instance->id,
                'to' => $this->to,
                'meta' => [
                    'origin' => 'queue_job',
                    'attempts' => $this->attempts(),
                    'planner_delay' => $plan['delay_seconds'],
                ],
            ]);

            if ($inspection['defer'] && $this->attempts() < $this->tries) {
                $this->release((int) ($inspection['release_seconds'] ?? 300));
                return;
            }

            return;
        }

        $delayKey = $this->humanDelayCacheKey($instance);
        $releasedDelay = (int) (Cache::pull($delayKey) ?: 0);

        if ($releasedDelay <= 0 && $this->attempts() < $this->tries) {
            $delay = max((int) ($plan['delay_seconds'] ?? 0), $safety->humanDelay($instance));

            if ($delay > 0) {
                Cache::put($delayKey, $delay, now()->addHours(6));
                $this->release($delay);
                return;
            }
        }

        $instances->sendText($instance, $this->to, $this->message, [
            'skip_safety' => true,
            'inspection' => $inspection,
            'delay_seconds' => max((int) ($plan['delay_seconds'] ?? 0), $releasedDelay),
            'media_type' => 'text',
            'origin' => 'queue_job',
        ]);
    }

    protected function logPlannerDecision(TenantInstance $instance, array $plan): void
    {
        if (! Schema::hasTable('whatsapp_safety_events')) {
            return;
        }

        WhatsAppSafetyEvent::create([
            'tenant_id' => $instance->tenant_id,
            'tenant_instance_id' => $instance->id,
            'event_type' => 'planner_blocked',
            'reason' => $plan['reason'] ?? 'unknown',
            'risk_score' => $plan['risk_score'] ?? 0,
            'risk_level' => $plan['risk_score'] >= 80 ? 'critical' : ($plan['risk_score'] >= 50 ? 'high' : 'medium'),
            'to' => $this->maskPhone($this->to),
            'meta' => [
                'origin' => 'queue_job',
                'planner_delay' => $plan['delay_seconds'],
                'concurrency' => $plan['concurrency'] ?? null,
            ],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao enviar WhatsApp pela fila', [
            'tenant_instance_id' => $this->tenantInstanceId,
            'to' => $this->maskPhone($this->to),
            'error' => $exception->getMessage(),
        ]);
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? $phone;
        $length = mb_strlen($digits);

        if ($length <= 6) {
            return '[telefone]';
        }

        return mb_substr($digits, 0, 2).str_repeat('*', max(0, $length - 6)).mb_substr($digits, -4);
    }

    protected function humanDelayCacheKey(TenantInstance $instance): string
    {
        return 'whatsapp:human-delay:'.$instance->id.':'.sha1($this->to.'|'.$this->message);
    }
}
