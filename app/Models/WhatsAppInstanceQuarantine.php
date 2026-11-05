<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInstanceQuarantine extends Model
{
    protected $table = 'whatsapp_instance_quarantines';

    protected $fillable = [
        'tenant_id',
        'tenant_instance_id',
        'mode',
        'reason',
        'quarantine_until',
        'active',
        'released_by',
        'released_at',
        'release_reason',
        'meta',
    ];

    protected $casts = [
        'active' => 'boolean',
        'quarantine_until' => 'datetime',
        'released_at' => 'datetime',
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
