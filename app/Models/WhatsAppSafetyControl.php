<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSafetyControl extends Model
{
    protected $table = 'whatsapp_safety_controls';

    protected $fillable = [
        'tenant_id',
        'tenant_instance_id',
        'sending_paused',
        'paused_until',
        'sending_disabled',
        'campaigns_blocked',
        'warmup_enabled',
        'limit_per_minute',
        'limit_per_hour',
        'limit_per_day',
        'human_delay_min_seconds',
        'human_delay_max_seconds',
        'meta',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sending_paused' => 'boolean',
        'sending_disabled' => 'boolean',
        'campaigns_blocked' => 'boolean',
        'warmup_enabled' => 'boolean',
        'paused_until' => 'datetime',
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
