<?php

namespace App\Modules\Tenancy\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPackageSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'tenant_package_id',
        'status',
        'payment_mode',
        'installments_count',
        'total_amount',
        'fee_amount',
        'interest_amount',
        'final_amount',
        'start_date',
        'next_due_date',
        'meta',
        'edits_count',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'start_date' => 'date',
        'next_due_date' => 'date',
        'meta' => 'array',
        'edits_count' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TenantPackage::class, 'tenant_package_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CustomerPackageInstallment::class, 'subscription_id');
    }
}
