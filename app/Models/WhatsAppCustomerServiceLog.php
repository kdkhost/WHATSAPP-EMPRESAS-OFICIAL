<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCustomerServiceLog extends Model
{
    protected $table = 'whatsapp_customer_service_logs';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'whatsapp_instance_id',
        'tenant_instance_id',
        'phone',
        'action',
        'direction',
        'message',
        'response',
        'status',
        'payload',
        'ip',
        'user_agent',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
