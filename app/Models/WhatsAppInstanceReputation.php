<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInstanceReputation extends Model
{
    protected $table = 'whatsapp_instance_reputations';

    protected $fillable = [
        'tenant_id',
        'tenant_instance_id',
        'trust_score',
        'risk_score',
        'quality_score',
        'daily_sent',
        'daily_failed',
        'daily_delivered',
        'daily_replied',
        'last_rate_limit_at',
        'last_forbidden_at',
        'last_unauthorized_at',
        'quarantine_until',
        'quarantine_reason',
        'sending_paused',
        'meta',
    ];

    protected $casts = [
        'trust_score' => 'integer',
        'risk_score' => 'integer',
        'quality_score' => 'integer',
        'daily_sent' => 'integer',
        'daily_failed' => 'integer',
        'daily_delivered' => 'integer',
        'daily_replied' => 'integer',
        'sending_paused' => 'boolean',
        'last_rate_limit_at' => 'datetime',
        'last_forbidden_at' => 'datetime',
        'last_unauthorized_at' => 'datetime',
        'quarantine_until' => 'datetime',
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
