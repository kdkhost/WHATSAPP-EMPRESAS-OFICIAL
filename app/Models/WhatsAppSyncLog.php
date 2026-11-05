<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSyncLog extends Model
{
    protected $table = 'whatsapp_sync_logs';

    protected $fillable = [
        'tenant_id',
        'whatsapp_instance_id',
        'tenant_instance_id',
        'instance_name',
        'action',
        'status',
        'local_payload',
        'remote_payload',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'local_payload' => 'array',
        'remote_payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class);
    }

    public function tenantInstance(): BelongsTo
    {
        return $this->belongsTo(TenantInstance::class);
    }
}
