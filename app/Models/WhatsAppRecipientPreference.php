<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppRecipientPreference extends Model
{
    protected $table = 'whatsapp_recipient_preferences';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'phone',
        'phone_hash',
        'opt_in',
        'opt_in_at',
        'opt_out',
        'opt_out_at',
        'opt_out_reason',
        'blacklisted',
        'whitelisted',
        'lgpd_consented',
        'lgpd_consented_at',
        'meta',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'opt_in' => 'boolean',
        'opt_out' => 'boolean',
        'blacklisted' => 'boolean',
        'whitelisted' => 'boolean',
        'lgpd_consented' => 'boolean',
        'opt_in_at' => 'datetime',
        'opt_out_at' => 'datetime',
        'lgpd_consented_at' => 'datetime',
        'meta' => 'array',
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
