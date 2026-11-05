<?php

namespace App\Modules\Affiliates\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id', 'tenant_id', 'slug', 'referral_code', 'promotion_channel', 'commission_pct', 'commission_mode',
        'commission_fixed', 'commission_type', 'discount_pct', 'discount_fixed',
        'discount_mode', 'discount_type', 'balance', 'total_earned', 'total_paid',
        'min_withdrawal', 'payment_info', 'custom_rules', 'status', 'rejection_reason',
        'admin_notes', 'mp_collector_id', 'mp_public_key', 'mp_access_token', 'split_enabled',
        'split_pct', 'split_mode', 'applied_at', 'approved_at', 'clicks', 'conversions',
    ];

    protected $casts = [
        'commission_pct'   => 'decimal:2',
        'commission_fixed' => 'decimal:2',
        'discount_pct'     => 'decimal:2',
        'discount_fixed'   => 'decimal:2',
        'balance'          => 'decimal:2',
        'total_earned'     => 'decimal:2',
        'total_paid'       => 'decimal:2',
        'min_withdrawal'   => 'decimal:2',
        'split_enabled'    => 'boolean',
        'split_pct'        => 'decimal:2',
        'payment_info'     => 'array',
        'custom_rules'     => 'array',
        'applied_at'       => 'datetime',
        'approved_at'      => 'datetime',
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

    protected static function booted(): void
    {
        static::creating(function (Affiliate $a) {
            $a->slug ??= Str::slug($a->user?->name ?? 'afiliado') . '-' . Str::lower(Str::random(6));
            $a->referral_code ??= strtoupper(Str::random(8));
            $a->commission_mode ??= 'percentage';
            $a->commission_type ??= 'recurring';
            $a->discount_mode ??= 'percentage';
            $a->discount_type ??= 'first_payment';
        });
    }

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo          { return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class); }
    public function clicks(): HasMany            { return $this->hasMany(AffiliateClick::class); }
    public function referrals(): HasMany         { return $this->hasMany(AffiliateReferral::class); }
    public function commissions(): HasMany       { return $this->hasMany(AffiliateCommission::class); }
    public function withdrawals(): HasMany       { return $this->hasMany(AffiliateWithdrawal::class); }

    public function getReferralUrlAttribute(): string
    {
        return url('/a/' . $this->slug);
    }

    public function canWithdraw(): bool
    {
        return $this->balance >= $this->min_withdrawal && $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'    => 'Ativo / Aprovado',
            'pending'   => 'Pendente de Aprovação',
            'rejected'  => 'Rejeitado / Pendência',
            'suspended' => 'Suspenso',
            default     => 'Não Iniciado',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'active'    => 'bg-success',
            'pending'   => 'bg-warning text-dark',
            'rejected'  => 'bg-danger',
            'suspended' => 'bg-secondary',
            default     => 'bg-secondary',
        };
    }

    public function getCommissionFormattedAttribute(): string
    {
        if ($this->commission_mode === 'fixed') {
            return 'R$ ' . number_format((float) ($this->commission_fixed ?? 0), 2, ',', '.');
        }
        return number_format((float) ($this->commission_pct ?? 20), 2, ',', '.') . '%';
    }

    public function getDiscountFormattedAttribute(): string
    {
        if ($this->discount_mode === 'fixed') {
            return 'R$ ' . number_format((float) ($this->discount_fixed ?? 0), 2, ',', '.');
        }
        return number_format((float) ($this->discount_pct ?? 0), 2, ',', '.') . '%';
    }

    public function getCommissionTypeLabelAttribute(): string
    {
        return match ($this->commission_type) {
            'first_payment' => 'Única Vez (1º Pagamento)',
            'lifetime'      => 'Vitalícia',
            default         => 'Recorrente',
        };
    }

    public function getDiscountTypeLabelAttribute(): string
    {
        return match ($this->discount_type) {
            'recurring' => 'Recorrente (Todas as Mensalidades)',
            default     => '1ª Mensalidade',
        };
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->clicks <= 0) return 0.0;
        return round(($this->conversions / $this->clicks) * 100, 1);
    }
}
