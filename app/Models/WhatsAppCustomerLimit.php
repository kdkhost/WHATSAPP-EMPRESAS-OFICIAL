<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCustomerLimit extends Model
{
    protected $table = 'whatsapp_customer_limits';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'phone',
        'action_type',
        'total_requests',
        'limit_date',
    ];

    protected $casts = [
        'limit_date' => 'date:Y-m-d',
        'total_requests' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
