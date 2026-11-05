<?php

namespace App\Modules\Affiliates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateWithdrawal extends Model
{
    protected $fillable = ['affiliate_id', 'amount', 'status', 'payment_info', 'admin_note', 'paid_at'];
    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_info' => 'array',
        'paid_at'      => 'datetime',
    ];

    public function getPaymentInfoAttribute($value): array
    {
        if (empty($value)) return [];
        if (is_array($value)) return $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return $decoded;
            try {
                $decrypted = decrypt($value, false);
                if (is_array($decrypted)) return $decrypted;
                if (is_string($decrypted)) {
                    $json = json_decode($decrypted, true);
                    if (is_array($json)) return $json;
                }
            } catch (\Throwable) {}
        }
        return [];
    }

    public function setPaymentInfoAttribute($value): void
    {
        $arr = is_array($value) ? $value : (json_decode($value, true) ?: []);
        $this->attributes['payment_info'] = json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
}
