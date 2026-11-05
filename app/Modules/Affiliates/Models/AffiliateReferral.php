<?php

namespace App\Modules\Affiliates\Models;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferral extends Model
{
    protected $fillable = ['affiliate_id', 'user_id', 'tenant_id', 'cookie_expires_at', 'first_payment_done'];
    protected $casts = [
        'cookie_expires_at'  => 'date',
        'first_payment_done' => 'boolean',
    ];

    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo    { return $this->belongsTo(Tenant::class); }
}
