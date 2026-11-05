<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBillingConfig extends Model
{
    protected $table = 'tenant_billing_config';

    protected $fillable = [
        'tenant_id', 'cron_time', 'days_before_due', 'days_after_due',
        'invoice_creation_days', 'fee_passthrough', 'fee_percentage',
        'installment_enabled', 'max_installments', 'charge_type', 'skip_weekends_holidays',
    ];

    protected $casts = [
        'days_before_due'     => 'array',
        'days_after_due'      => 'array',
        'fee_passthrough'     => 'boolean',
        'fee_percentage'      => 'decimal:2',
        'installment_enabled' => 'boolean',
        'skip_weekends_holidays' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
