<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInstance extends Model
{
    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'tenant_id',
        'tenant_instance_id',
        'instance_name',
        'instance_token',
        'global_api_key_ref',
        'phone',
        'jid',
        'profile_name',
        'profile_picture',
        'client_name',
        'connected',
        'logged_in',
        'server_status',
        'last_sync_at',
        'last_qr_at',
        'last_webhook_at',
        'server_payload',
        'local_hash',
        'remote_hash',
        'sync_status',
        'sync_error',
    ];

    protected $hidden = [
        'instance_token',
    ];

    protected $casts = [
        'connected' => 'boolean',
        'logged_in' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_qr_at' => 'datetime',
        'last_webhook_at' => 'datetime',
        'server_payload' => 'array',
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
