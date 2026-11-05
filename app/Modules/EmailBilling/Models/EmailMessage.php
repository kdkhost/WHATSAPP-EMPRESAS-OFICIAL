<?php

namespace App\Modules\EmailBilling\Models;

use App\Models\BillingDispatch;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $fillable = [
        'tenant_id', 'dispatch_id', 'to', 'subject', 'body_html',
        'status', 'message_id', 'error', 'sent_at', 'opened_at', 'headers',
        'attempts', 'max_attempts', 'next_attempt_at', 'locked_at', 'locked_by',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'opened_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'locked_at' => 'datetime',
        'headers'   => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(BillingDispatch::class, 'dispatch_id');
    }
}
