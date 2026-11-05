<?php

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'price',
        'status',
        'started_at',
        'next_billing',
        'canceled_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'started_at' => 'date',
        'next_billing' => 'date',
        'canceled_at' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
