<?php

namespace App\Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['contract_id', 'event', 'ip', 'user_agent', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
