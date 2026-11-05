<?php

namespace App\Modules\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    public $timestamps = false;
    protected $fillable = ['affiliate_id', 'tenant_id', 'ip', 'user_agent', 'referer', 'country', 'converted', 'created_at'];
    protected $casts = ['converted' => 'boolean', 'created_at' => 'datetime'];

    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
}
