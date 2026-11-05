<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WhatsAppCustomerLimit;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class WhatsAppDailyLimitService
{
    protected array $defaults = [
        'debt_query' => 8,
        'second_copy' => 6,
        'pix_code' => 6,
        'installments' => 8,
        'due_date' => 8,
        'payment_link' => 6,
        'profile_update' => 4,
        'human_support' => 3,
        'plan_query' => 8,
        'status' => 8,
        'fallback' => 12,
    ];

    public function hit(Tenant $tenant, ?Customer $customer, string $phone, string $actionType): array
    {
        $limit = $this->limitFor($tenant, $actionType);
        $date = now('America/Sao_Paulo')->toDateString();
        $phone = preg_replace('/\D/', '', $phone) ?: $phone;

        $row = DB::transaction(function () use ($tenant, $customer, $phone, $actionType, $date) {
            $keys = [
                'tenant_id' => $tenant->id,
                'customer_id' => $customer?->id,
                'phone' => $phone,
                'action_type' => $actionType,
                'limit_date' => $date,
            ];

            $record = WhatsAppCustomerLimit::query()->where($keys)->first();
            if (! $record) {
                $record = WhatsAppCustomerLimit::create($keys + ['total_requests' => 0]);
            }

            $record->increment('total_requests');

            return $record->refresh();
        });

        return [
            'allowed' => $row->total_requests <= $limit,
            'limit' => $limit,
            'used' => $row->total_requests,
            'message' => $row->total_requests <= $limit
                ? null
                : 'Limite diario atingido para esta consulta. Tente novamente amanha ou digite *6* para falar com atendente.',
        ];
    }

    public function limitFor(Tenant $tenant, string $actionType): int
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $configured = data_get($settings, "whatsapp_daily_limits.{$actionType}");

        return max(1, (int) ($configured ?: ($this->defaults[$actionType] ?? 8)));
    }
}
