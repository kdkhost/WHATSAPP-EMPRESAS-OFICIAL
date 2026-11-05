<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantPackage extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'total_amount',
        'allow_cash',
        'allow_installments',
        'max_installments',
        'gateway_fee_mode',
        'gateway_fee_percent',
        'gateway_fixed_fee',
        'installment_interest_percent',
        'active',
        'settings',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'allow_cash' => 'boolean',
        'allow_installments' => 'boolean',
        'gateway_fee_percent' => 'decimal:3',
        'gateway_fixed_fee' => 'decimal:2',
        'installment_interest_percent' => 'decimal:3',
        'active' => 'boolean',
        'settings' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerPackageSubscription::class);
    }
}
