<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Marketplace\Models\MarketplaceAddon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAddon extends Model
{
    protected $fillable = [
        'tenant_id',
        'marketplace_addon_id',
        'tenant_invoice_id',
        'status',
        'billing_cycle',
        'recurring_amount',
        'activated_at',
        'suspended_at',
        'cancelled_at',
        'settings',
        'meta',
    ];

    protected $casts = [
        'recurring_amount' => 'decimal:2',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'settings' => 'array',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAddon::class, 'marketplace_addon_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class, 'tenant_invoice_id');
    }
}
