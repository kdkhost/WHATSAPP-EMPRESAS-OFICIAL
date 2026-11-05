<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppReputationScore extends Model
{
    protected $table = 'whatsapp_reputation_scores';

    protected $fillable = [
        'tenant_id',
        'tenant_instance_id',
        'scope',
        'recipient_hash',
        'risk_score',
        'trust_score',
        'quality_score',
        'messages_sent',
        'messages_blocked',
        'messages_deferred',
        'last_event_at',
        'meta',
    ];

    protected $casts = [
        'last_event_at' => 'datetime',
        'meta' => 'array',
    ];
}
