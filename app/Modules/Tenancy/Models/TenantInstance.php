<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInstance extends Model
{
    protected $fillable = [
        'tenant_id', 'instance_name', 'display_name', 'phone',
        'status', 'meta_phone_number_id', 'meta_waba_id', 'meta_access_token', 'meta', 'is_default',
        'is_extra_billable', 'recurring_amount', 'billing_started_at', 'cancelled_at',
        'api_base_url', 'api_key', 'webhook_url', 'webhook_enabled',
        'webhook_by_events', 'webhook_base64', 'webhook_events', 'webhook_secret',
        'profile_name', 'profile_push_name', 'profile_full_name', 'profile_first_name',
        'profile_business_name', 'profile_is_business', 'profile_status',
        'profile_avatar_url', 'profile_picture_id', 'profile_number', 'profile_jid',
        'profile_devices', 'profile_lid', 'profile_contact_found', 'profile_raw',
        'last_connection_state', 'last_profile_sync_at',
        'last_error',
    ];

    protected $hidden = [
        'api_key',
        'meta_access_token',
        'webhook_secret',
    ];

    protected $casts = [
        'meta'                 => 'array',
        'is_default'           => 'boolean',
        'is_extra_billable'    => 'boolean',
        'recurring_amount'     => 'decimal:2',
        'billing_started_at'   => 'date',
        'cancelled_at'         => 'datetime',
        'api_key'              => 'encrypted',
        'meta_access_token'    => 'encrypted',
        'webhook_enabled'      => 'boolean',
        'webhook_by_events'    => 'boolean',
        'webhook_base64'       => 'boolean',
        'webhook_events'       => 'array',
        'profile_is_business'  => 'boolean',
        'profile_devices'      => 'array',
        'profile_contact_found'=> 'boolean',
        'profile_raw'          => 'array',
        'last_profile_sync_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function getBaseUrlAttribute(): string
    {
        return rtrim((string) ($this->api_base_url ?: config('whatsapp.meta_base_url')), '/');
    }

    public function getWhatsappProfileNameAttribute(): string
    {
        return (string) (
            $this->profile_business_name
            ?: $this->profile_full_name
            ?: $this->profile_name
            ?: $this->profile_push_name
            ?: $this->display_name
            ?: $this->instance_name
        );
    }

    public function getSecureWebhookUrlAttribute(): string
    {
        if ($this->webhook_url) {
            return $this->webhook_url;
        }

        $query = http_build_query([
            'tenant_instance' => $this->id,
            'token' => $this->webhook_secret,
        ]);

        return url('/api/v1/webhook').'?'.$query;
    }

    /**
     * Gera o identificador da instancia do tenant.
     * Regras: lowercase, sem espacos, sem caracteres especiais, sem maiuscula.
     * Formato: username_cliente_id_slot (ex.: joao_42_1)
     */
    public static function generateInstanceName(Tenant $tenant, ?string $customName = null): string
    {
        $tenant->loadMissing('user');

        $base = self::sanitizeName(
            $customName
                ?: $tenant->user?->name
                ?: str($tenant->user?->email ?? '')->before('@')->toString()
                ?: $tenant->slug
                ?: $tenant->company_name
        );

        if ($customName) {
            return $base . '_' . $tenant->id . '_1';
        }

        return $base . '_' . $tenant->id . '_1';
    }

    /**
     * Sanitiza nome da instancia.
     * Remove: espacos, caracteres especiais, letras maiusculas.
     */
    public static function sanitizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // Remove acentos
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        // Remove tudo que nao for letra, numero ou underscore
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        // Colapsa underscores multiplos
        $name = preg_replace('/_+/', '_', trim($name, '_'));
        return $name ?: 'instancia';
    }
}
