<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraInstancePrice extends Model
{
    protected $fillable = [
        'plan_id',
        'name',
        'min_quantity',
        'max_quantity',
        'price',
        'billing_cycle',
        'active',
        'sort',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function resolveForTenant(Tenant $tenant, int $extraQuantity = 1): ?self
    {
        $tenant->loadMissing('plan');
        $planId = $tenant->plan_id;
        $extraQuantity = max(1, $extraQuantity);

        $query = static::query()
            ->where('active', true)
            ->where('min_quantity', '<=', $extraQuantity)
            ->where(function ($q) use ($extraQuantity) {
                $q->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $extraQuantity);
            });

        $planPrice = (clone $query)
            ->where('plan_id', $planId)
            ->orderByDesc('min_quantity')
            ->orderBy('sort')
            ->first();

        if ($planPrice) {
            return $planPrice;
        }

        return $query
            ->whereNull('plan_id')
            ->orderByDesc('min_quantity')
            ->orderBy('sort')
            ->first();
    }
}
