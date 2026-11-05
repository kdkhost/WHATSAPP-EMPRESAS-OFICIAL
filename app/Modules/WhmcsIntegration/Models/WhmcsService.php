<?php

namespace App\Modules\WhmcsIntegration\Models;

use App\Models\Customer;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WhmcsService extends Model
{
    protected $fillable = [
        'connection_id',
        'tenant_id',
        'customer_id',
        'whmcs_client_id',
        'whmcs_service_id',
        'product_id',
        'product_name',
        'group_name',
        'domain',
        'username',
        'dedicated_ip',
        'server_id',
        'status',
        'billing_cycle',
        'amount',
        'registration_date',
        'next_due_date',
        'next_invoice_date',
        'suspended_at',
        'reactivated_at',
        'last_synced_at',
        'raw',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'registration_date' => 'date',
        'next_due_date' => 'date',
        'next_invoice_date' => 'date',
        'suspended_at' => 'datetime',
        'reactivated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhmcsConnection::class, 'connection_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isActive(): bool
    {
        return Str::lower((string) $this->status) === 'active';
    }

    public function isSuspended(): bool
    {
        return Str::lower((string) $this->status) === 'suspended';
    }

    public function isOverdue(int $graceDays = 0): bool
    {
        if (! $this->next_due_date) {
            return false;
        }

        $limit = Carbon::parse($this->next_due_date)->addDays(max(0, $graceDays))->endOfDay();

        return $this->isActive() && $limit->isPast();
    }
}
