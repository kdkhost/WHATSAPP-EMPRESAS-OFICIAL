<?php

namespace App\Modules\WhmcsIntegration\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhmcsConnection extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'api_url', 'identifier', 'secret', 'webhook_token',
        'webhook_secret', 'webhook_require_signature', 'sync_customers', 'sync_invoices',
        'sync_services', 'auto_send_invoices', 'auto_suspend_overdue_services',
        'auto_unsuspend_paid_services', 'suspend_after_days', 'default_gateway',
        'prevent_weekend_holiday_suspension',
        'sync_statuses', 'sync_service_statuses', 'sync_paid_invoices',
        'sync_cancelled_invoices', 'settings', 'active', 'last_sync_at',
        'last_sync_status', 'last_sync_error', 'last_test_at', 'last_test_status',
        'last_error', 'last_sync_stats', 'last_webhook_at',
    ];

    protected $casts = [
        'secret'                    => 'encrypted',
        'webhook_require_signature' => 'boolean',
        'sync_customers'            => 'boolean',
        'sync_invoices'             => 'boolean',
        'sync_services'             => 'boolean',
        'auto_send_invoices'        => 'boolean',
        'auto_suspend_overdue_services' => 'boolean',
        'auto_unsuspend_paid_services'  => 'boolean',
        'prevent_weekend_holiday_suspension' => 'boolean',
        'suspend_after_days'        => 'integer',
        'sync_statuses'             => 'array',
        'sync_service_statuses'     => 'array',
        'sync_paid_invoices'        => 'boolean',
        'sync_cancelled_invoices'   => 'boolean',
        'settings'                  => 'array',
        'active'                    => 'boolean',
        'last_sync_at'              => 'datetime',
        'last_test_at'              => 'datetime',
        'last_sync_stats'           => 'array',
        'last_webhook_at'           => 'datetime',
    ];

    protected $hidden = ['secret', 'webhook_token', 'webhook_secret'];

    protected static function booted(): void
    {
        static::creating(function (WhmcsConnection $c) {
            $c->webhook_token ??= Str::random(64);
            $c->webhook_secret ??= Str::random(64);
            $c->default_gateway ??= 'mercadopago';
            $c->sync_statuses ??= ['Unpaid', 'Paid', 'Cancelled', 'Refunded'];
            $c->sync_service_statuses ??= ['Active', 'Suspended', 'Pending'];
            $c->sync_services ??= true;
            $c->auto_suspend_overdue_services ??= false;
            $c->auto_unsuspend_paid_services ??= true;
            $c->suspend_after_days ??= 0;
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function services(): HasMany { return $this->hasMany(WhmcsService::class, 'connection_id'); }

    public function getWebhookUrlAttribute(): string
    {
        return url('/webhooks/whmcs/'.$this->webhook_token);
    }

    public function syncStatuses(): array
    {
        $statuses = $this->sync_statuses ?: ['Unpaid', 'Paid'];

        // Garante que 'Paid', 'Cancelled' e 'Refunded' sempre sejam sincronizados do WHMCS
        // para identificar pagamentos, cancelamentos e estornos e bloquear cobranças por WhatsApp e e-mail
        foreach (['Paid', 'Cancelled', 'Refunded'] as $status) {
            if (! in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return array_values(array_unique(array_filter($statuses)));
    }

    public function syncServiceStatuses(): array
    {
        $statuses = $this->sync_service_statuses ?: ['Active', 'Suspended', 'Pending'];

        return array_values(array_unique(array_filter($statuses)));
    }
}
