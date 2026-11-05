<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppWebhookLog extends Model
{
    protected $table = 'whatsapp_webhook_logs';

    protected $fillable = [
        'tenant_id',
        'whatsapp_instance_id',
        'tenant_instance_id',
        'instance_name',
        'event_name',
        'event_uid',
        'source_ip',
        'headers',
        'raw_payload',
        'normalized_payload',
        'status',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'processed_at' => 'datetime',
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
