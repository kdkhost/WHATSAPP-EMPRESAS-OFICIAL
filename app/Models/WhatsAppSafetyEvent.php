<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSafetyEvent extends Model
{
    protected $table = 'whatsapp_safety_events';

    protected $fillable = [
        'tenant_id',
        'tenant_instance_id',
        'message_id',
        'event_type',
        'media_type',
        'reason',
        'risk_score',
        'trust_score',
        'quality_score',
        'risk_level',
        'recipient',
        'recipient_hash',
        'message_hash',
        'scheduled_for',
        'meta',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantInstance(): BelongsTo
    {
        return $this->belongsTo(TenantInstance::class);
    }
}
