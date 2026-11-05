<?php

namespace App\Modules\Contracts\Models;

use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contract extends Model
{
    protected $fillable = [
        'tenant_id', 'template_id', 'customer_id', 'invoice_id', 'number', 'title',
        'content_html', 'background_type', 'background_path', 'layout_json', 'access_token', 'status',
        'sent_at', 'viewed_at', 'signed_at', 'expires_at',
        'signer_name', 'signer_email', 'signer_document', 'signer_ip', 'signer_user_agent',
        'signature_image', 'signed_pdf_path', 'signed_html_path', 'signed_file_mime', 'return_channel',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'viewed_at'  => 'datetime',
        'signed_at'  => 'datetime',
        'expires_at' => 'datetime',
        'layout_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contract $c) {
            $c->access_token ??= Str::random(64);
            $c->number ??= 'CTR-' . str_pad((string) (self::max('id') + 1), 6, '0', STR_PAD_LEFT);
        });
    }

    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function template(): BelongsTo { return $this->belongsTo(ContractTemplate::class, 'template_id'); }
    public function events(): HasMany     { return $this->hasMany(ContractEvent::class); }

    public function getPublicUrlAttribute(): string
    {
        return url('/contrato/' . $this->access_token);
    }

    public function isSigned(): bool { return $this->status === 'signed'; }
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }

    public function recordEvent(string $event, ?string $ip = null, ?string $ua = null, array $meta = []): void
    {
        $this->events()->create([
            'event'      => $event,
            'ip'         => $ip,
            'user_agent' => $ua,
            'metadata'   => $meta,
            'created_at' => now(),
        ]);
    }
}
