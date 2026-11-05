<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'company_name', 'slug', 'status',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'gateway', 'gateway_subscription_id', 'settings',
        'custom_domain', 'custom_domain_dns_target', 'custom_domain_status',
        'custom_domain_verified_at', 'custom_domain_last_checked_at',
        'custom_domain_last_error',
    ];

    protected $casts = [
        'trial_ends_at'        => 'date',
        'current_period_start' => 'date',
        'current_period_end'   => 'date',
        'settings'             => 'array',
        'custom_domain_verified_at' => 'datetime',
        'custom_domain_last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo     { return $this->belongsTo(Plan::class); }
    public function instances(): HasMany  { return $this->hasMany(TenantInstance::class); }
    public function gateways(): HasMany   { return $this->hasMany(TenantGateway::class); }
    public function billingConfig(): HasOne { return $this->hasOne(TenantBillingConfig::class); }
    public function tenantInvoices(): HasMany { return $this->hasMany(TenantInvoice::class); }
    public function packages(): HasMany { return $this->hasMany(TenantPackage::class); }
    public function packageSubscriptions(): HasMany { return $this->hasMany(CustomerPackageSubscription::class); }
    public function addons(): HasMany { return $this->hasMany(TenantAddon::class); }
    public function affiliate(): HasOne { return $this->hasOne(\App\Modules\Affiliates\Models\Affiliate::class, 'tenant_id'); }
    public function referral(): HasOne { return $this->hasOne(\App\Modules\Affiliates\Models\AffiliateReferral::class, 'tenant_id'); }
    public function emailConfig(): HasOne { return $this->hasOne(\App\Modules\EmailBilling\Models\TenantEmailConfig::class, 'tenant_id'); }

    public function getAvatarUrlAttribute(): ?string
    {
        // 1. Instância WhatsApp conectada com foto de perfil
        $connected = $this->instances
            ->first(fn ($i) => !empty($i->profile_avatar_url) && in_array($i->status, ['connected', 'open']));
        if ($connected?->profile_avatar_url) {
            return $connected->profile_avatar_url;
        }

        // 2. Qualquer instância vinculada que possua avatar
        $anyInstance = $this->instances
            ->first(fn ($i) => !empty($i->profile_avatar_url));
        if ($anyInstance?->profile_avatar_url) {
            return $anyInstance->profile_avatar_url;
        }

        // 3. Logo do WhiteLabel / Tenant settings
        $logo = data_get($this->settings, 'whitelabel.logo_url')
            ?: (data_get($this->settings, 'logo_url') ?: data_get($this->settings, 'logo'));
        if ($logo) {
            return $logo;
        }

        // 4. Logo das configurações de e-mail do tenant
        $emailLogo = data_get($this->emailConfig?->settings, 'logo_url');
        if ($emailLogo) {
            return $emailLogo;
        }

        // 5. Avatar do usuário admin do tenant
        if ($this->user?->avatar_url || $this->user?->avatar) {
            return $this->user->avatar_url ?: $this->user->avatar;
        }

        return null;
    }

    public function getHasConnectedWhatsAppAttribute(): bool
    {
        return (bool) $this->instances
            ->contains(fn ($i) => in_array($i->status, ['connected', 'open']));
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']);
    }

    public function isTrialExpired(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isPast();
    }

    public function canCreateInstance(): bool
    {
        return $this->canCreateIncludedInstance();
    }

    public function canCreateIncludedInstance(): bool
    {
        return $this->currentInstanceCount() < $this->includedInstanceLimit();
    }

    public function currentInstanceCount(): int
    {
        return $this->instances()->count();
    }

    public function includedInstanceLimit(): int
    {
        return max(0, (int) ($this->plan?->max_instances ?? 0));
    }

    public function extraBillableInstances(): HasMany
    {
        return $this->instances()
            ->where('is_extra_billable', true)
            ->whereNull('cancelled_at');
    }

    public function defaultInstance(): ?TenantInstance
    {
        return $this->instances()->where('is_default', true)->first()
            ?? $this->instances()->first();
    }

    public function whiteLabelSettings(): array
    {
        $settings = $this->settings ?? [];
        $whiteLabel = $settings['whitelabel'] ?? [];

        return is_array($whiteLabel) ? $whiteLabel : [];
    }

    public function whiteLabelValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->whiteLabelSettings(), $key, $default);
    }

    public function whiteLabelEnabled(): bool
    {
        return (bool) $this->whiteLabelValue('enabled', false);
    }

    public function customDomainActive(): bool
    {
        return $this->custom_domain && $this->custom_domain_status === 'active';
    }

    public function publicApiSettings(): array
    {
        $settings = $this->settings ?? [];
        $api = $settings['public_api'] ?? [];

        return is_array($api) ? $api : [];
    }

    public function publicApiEnabled(): bool
    {
        $settings = $this->publicApiSettings();

        if (array_key_exists('enabled', $settings)) {
            return (bool) $settings['enabled'];
        }

        $features = collect($this->plan?->features ?? [])->map(fn ($feature) => mb_strtolower((string) $feature));

        return $features->contains(fn ($feature) => str_contains($feature, 'api publica') || str_contains($feature, 'api publica completa'));
    }

    public function publicApiMonthlyFee(): float
    {
        return (float) ($this->publicApiSettings()['monthly_fee'] ?? 0);
    }
}
