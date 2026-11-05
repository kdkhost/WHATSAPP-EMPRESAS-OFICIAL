<?php

namespace App\Modules\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'affiliate_id', 'referral_id', 'tenant_invoice_id',
        'base_amount', 'commission_pct', 'commission_amount',
        'commission_type', 'status', 'approved_at', 'notes',
    ];

    protected $casts = [
        'base_amount'       => 'decimal:2',
        'commission_pct'    => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at'       => 'datetime',
    ];

    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
    public function referral(): BelongsTo  { return $this->belongsTo(AffiliateReferral::class, 'referral_id'); }
}
