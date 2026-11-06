<?php

namespace App\Models;

use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'dispatch_id',
        'tenant_instance_id',
        'to',
        'message',
        'meta_message_id',
        'external_id',
        'direction',
        'media_type',
        'status',
        'scheduled_for',
        'sent_at',
        'blocked_at',
        'blocked_reason',
        'risk_score',
        'trust_score',
        'quality_score',
        'message_hash',
        'normalized_message_hash',
        'template_hash',
        'campaign_hash',
        'recipient_hash',
        'response',
        'meta',
        'delivered_at',
        'read_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'response' => 'array',
        'meta' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'blocked_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function tenantInstance(): BelongsTo
    {
        return $this->belongsTo(TenantInstance::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(BillingDispatch::class, 'dispatch_id');
    }
}
