<?php

namespace App\Modules\WhmcsIntegration\Services;

use App\Models\Invoice;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhmcsServiceAutomation
{
    public function run(?WhmcsConnection $target = null): array
    {
        $connections = $target
            ? collect([$target])
            : WhmcsConnection::query()->where('active', true)->get();

        $summary = [
            'suspended' => 0,
            'unsuspended' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($connections as $connection) {
            $suspension = $this->suspendOverdueServices($connection);

            foreach ($summary as $key => $value) {
                $summary[$key] += $suspension[$key] ?? 0;
            }
        }

        return $summary;
    }

    public function suspendOverdueServices(WhmcsConnection $connection, int $limit = 50): array
    {
        $stats = ['suspended' => 0, 'unsuspended' => 0, 'skipped' => 0, 'errors' => 0];

        if (! $connection->active || ! $connection->auto_suspend_overdue_services) {
            return $stats;
        }

        $holidays = app(\App\Modules\Holidays\Services\HolidayService::class);
        $today = now('America/Sao_Paulo');
        $skipHolidays = $this->shouldSkipWeekendsHolidays($connection);

        // NUNCA suspende serviços em finais de semana ou feriados bancários se a opção estiver ativa
        if ($skipHolidays && $holidays->isNonBusinessDay($today)) {
            $this->log($connection, 'push', 'service', null, null, 'skipped',
                'Suspensão automática de serviços WHMCS ignorada: hoje (' . $today->format('d/m/Y') . ') é final de semana ou feriado bancário nacional conforme regras de proteção do sistema.');
            return $stats;
        }

        $graceDays = max(0, (int) $connection->suspend_after_days);
        $threshold = $today->copy()->startOfDay()->subDays($graceDays)->toDateString();

        $services = WhmcsService::query()
            ->where('connection_id', $connection->id)
            ->where('status', 'Active')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<', $threshold)
            ->orderBy('next_due_date')
            ->limit($limit)
            ->get();

        $client = new WhmcsClient($connection);

        foreach ($services as $service) {
            if ($skipHolidays && $service->next_due_date) {
                $serviceDue = Carbon::parse($service->next_due_date, 'America/Sao_Paulo')->startOfDay();
                $effectiveDue = $holidays->nextBusinessDay($serviceDue);
                $effectiveGraceThreshold = $effectiveDue->copy()->addDays($graceDays);
                // Se a carência contada a partir do próximo dia útil ainda não expirou, pula
                if ($effectiveGraceThreshold->isFuture() || $effectiveGraceThreshold->isToday()) {
                    $stats['skipped']++;
                    continue;
                }
            }
            $reason = 'Suspensao automatica por vencimento em '.$this->formatDate($service->next_due_date);
            $ok = $client->suspendService((int) $service->whmcs_service_id, $reason);

            if ($ok) {
                $service->forceFill([
                    'status' => 'Suspended',
                    'suspended_at' => now(),
                    'last_synced_at' => now(),
                ])->save();

                $stats['suspended']++;
                $this->log($connection, 'push', 'service', $service->whmcs_service_id, $service->id, 'ok', $reason);
            } else {
                $stats['errors']++;
                $this->log($connection, 'push', 'service', $service->whmcs_service_id, $service->id, 'error', 'Falha ao suspender servico vencido.');
            }
        }

        return $stats;
    }

    public function unsuspendPaidServicesForInvoice(Invoice|int $invoice): array
    {
        $invoice = $this->resolveInvoice($invoice);
        $stats = ['suspended' => 0, 'unsuspended' => 0, 'skipped' => 0, 'errors' => 0];

        if (! $invoice || $invoice->status !== 'paid') {
            $stats['skipped']++;
            return $stats;
        }

        $meta = $this->meta($invoice->meta ?? null);
        $connectionId = (int) ($meta['whmcs_connection_id'] ?? 0);

        if ($connectionId <= 0) {
            $stats['skipped']++;
            return $stats;
        }

        $connection = WhmcsConnection::query()
            ->where('active', true)
            ->find($connectionId);

        if (! $connection || ! $connection->auto_unsuspend_paid_services) {
            $stats['skipped']++;
            return $stats;
        }

        $services = $this->servicesLinkedToInvoice($invoice, $connection, $meta);
        if ($services->isEmpty()) {
            $stats['skipped']++;
            return $stats;
        }

        $client = new WhmcsClient($connection);

        foreach ($services as $service) {
            $ok = $client->unsuspendService((int) $service->whmcs_service_id);

            if ($ok) {
                $service->forceFill([
                    'status' => 'Active',
                    'suspended_at' => null,
                    'reactivated_at' => now(),
                    'last_synced_at' => now(),
                ])->save();

                $stats['unsuspended']++;
                $this->log($connection, 'push', 'service', $service->whmcs_service_id, $service->id, 'ok', 'Servico reativado apos pagamento da fatura '.$invoice->number);
            } else {
                $stats['errors']++;
                $this->log($connection, 'push', 'service', $service->whmcs_service_id, $service->id, 'error', 'Falha ao reativar servico apos pagamento.');
            }
        }

        return $stats;
    }

    protected function servicesLinkedToInvoice(Invoice $invoice, WhmcsConnection $connection, array $meta)
    {
        $rawServiceIds = $meta['whmcs_service_ids'] ?? [];
        if (is_string($rawServiceIds)) {
            $rawServiceIds = explode(',', $rawServiceIds);
        }

        $serviceIds = collect($rawServiceIds)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        $query = WhmcsService::query()
            ->where('connection_id', $connection->id)
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'Suspended');

        if ($serviceIds->isNotEmpty()) {
            $query->whereIn('whmcs_service_id', $serviceIds->all());
        } elseif ($invoice->due_date) {
            $query->where(function ($nested) use ($invoice) {
                $nested->whereNull('next_due_date')
                    ->orWhereDate('next_due_date', '<=', Carbon::parse($invoice->due_date)->toDateString());
            });
        }

        return $query->orderBy('next_due_date')->limit(20)->get();
    }

    protected function resolveInvoice(Invoice|int $invoice): ?Invoice
    {
        if ($invoice instanceof Invoice) {
            return $invoice;
        }

        return Invoice::find((int) $invoice);
    }

    protected function meta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function formatDate(mixed $date): string
    {
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    protected function log(
        WhmcsConnection $connection,
        string $direction,
        string $entity,
        mixed $whmcsId,
        mixed $localId,
        string $status,
        ?string $message = null,
        ?array $payload = null
    ): void {
        try {
            DB::table('whmcs_sync_log')->insert([
                'connection_id' => $connection->id,
                'direction' => $direction,
                'entity' => $entity,
                'whmcs_id' => is_numeric($whmcsId) ? (int) $whmcsId : null,
                'local_id' => is_numeric($localId) ? (int) $localId : null,
                'status' => $status,
                'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('[WHMCS] Log operacional ignorado', ['error' => $e->getMessage()]);
        }
    }

    public function shouldSkipWeekendsHolidays(WhmcsConnection $connection): bool
    {
        if ($connection->tenant_id) {
            $config = DB::table('tenant_billing_config')
                ->where('tenant_id', $connection->tenant_id)
                ->value('skip_weekends_holidays');
            if ($config !== null) {
                return (bool) $config;
            }
        }
        if (! empty($connection->prevent_weekend_holiday_suspension)) {
            return true;
        }
        return (bool) data_get($connection->settings ?: [], 'skip_weekends_holidays', false);
    }
}
