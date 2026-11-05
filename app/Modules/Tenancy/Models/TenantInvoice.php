<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantInvoice extends Model
{
    protected $fillable = [
        'tenant_id', 'number', 'amount', 'description', 'due_date', 'status',
        'gateway_charge_id', 'payment_url', 'webhook_token', 'meta', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'due_date' => 'date',
        'meta'     => 'array',
        'paid_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function tenantAddons(): HasMany
    {
        return $this->hasMany(TenantAddon::class, 'tenant_invoice_id');
    }
}
