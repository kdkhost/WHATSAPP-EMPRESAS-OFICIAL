<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDispatch extends Model
{
    protected $table = 'billing_dispatches';

    protected $fillable = [
        'invoice_id','template_id','email_template_id','channel','schedule_offset','scheduled_at',
        'status','attempts','sent_at','last_error','payload',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
        'payload'      => 'array',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
