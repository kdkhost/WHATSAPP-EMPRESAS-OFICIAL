<?php

namespace App\Models;

use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppDeliveryMetric extends Model
{
    protected $table = 'whatsapp_delivery_metrics';

    protected $fillable = [
        'tenant_instance_id',
        'date',
        'sent',
        'delivered',
        'read',
        'failed',
        'replied',
        'text_count',
        'image_count',
        'audio_count',
        'video_count',
        'document_count',
        'buttons_count',
        'list_count',
        'poll_count',
        'delivery_rate',
        'reply_rate',
        'failure_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'sent' => 'integer',
        'delivered' => 'integer',
        'read' => 'integer',
        'failed' => 'integer',
        'replied' => 'integer',
        'delivery_rate' => 'decimal:2',
        'reply_rate' => 'decimal:2',
        'failure_rate' => 'decimal:2',
    ];

    public function tenantInstance(): BelongsTo
    {
        return $this->belongsTo(TenantInstance::class);
    }
}
