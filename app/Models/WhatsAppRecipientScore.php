<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppRecipientScore extends Model
{
    protected $table = 'whatsapp_recipient_scores';

    protected $fillable = [
        'tenant_id',
        'phone_hash',
        'phone',
        'customer_id',
        'opt_in',
        'opt_out',
        'blacklisted',
        'last_interaction_at',
        'last_reply_at',
        'total_sent',
        'total_failed',
        'total_replied',
        'quality_score',
        'block_reason',
        'meta',
    ];

    protected $casts = [
        'opt_in' => 'boolean',
        'opt_out' => 'boolean',
        'blacklisted' => 'boolean',
        'last_interaction_at' => 'datetime',
        'last_reply_at' => 'datetime',
        'total_sent' => 'integer',
        'total_failed' => 'integer',
        'total_replied' => 'integer',
        'quality_score' => 'integer',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
