<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantGateway extends Model
{
    protected $fillable = [
        'tenant_id',
        'gateway',
        'credentials',
        'sandbox_credentials',
        'production_credentials',
        'settings',
        'payment_methods',
        'fees',
        'split_rules',
        'installment_rules',
        'webhook_token',
        'webhook_secret',
        'environment',
        'priority',
        'last_test_status',
        'last_test_at',
        'last_error',
        'active',
        'is_default',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'sandbox_credentials' => 'encrypted:array',
        'production_credentials' => 'encrypted:array',
        'settings' => 'array',
        'payment_methods' => 'array',
        'fees' => 'array',
        'split_rules' => 'array',
        'installment_rules' => 'array',
        'active'      => 'boolean',
        'is_default'  => 'boolean',
        'priority' => 'integer',
        'last_test_at' => 'datetime',
    ];

    protected $hidden = ['credentials', 'sandbox_credentials', 'production_credentials', 'webhook_secret'];

    /**
     * Retorna as credenciais baseadas no ambiente atual.
     */
    public function getCredentialsAttribute($value): array
    {
        $environment = $this->environment ?? 'production';

        $creds = null;
        if ($environment === 'sandbox' && !empty($this->sandbox_credentials)) {
            $creds = $this->sandbox_credentials;
        } elseif ($environment === 'production' && !empty($this->production_credentials)) {
            $creds = $this->production_credentials;
        } else {
            $creds = $value;
        }

        if (is_string($creds) && !empty($creds)) {
            try {
                $decrypted = decrypt($creds, false);
                $creds = $decrypted;
            } catch (\Throwable) {}

            if (is_string($creds)) {
                $decoded = json_decode($creds, true);
                if (is_array($decoded)) {
                    $creds = $decoded;
                }
            }
        }

        return is_array($creds) ? $creds : [];
    }

    /**
     * Define as credenciais baseadas no ambiente atual.
     */
    public function setCredentialsAttribute($value): void
    {
        $environment = $this->attributes['environment'] ?? $this->environment ?? 'production';
        $arrayValue = is_array($value) ? $value : (json_decode((string)$value, true) ?: []);

        $encryptedValue = encrypt(json_encode($arrayValue), false);

        if ($environment === 'sandbox') {
            $this->attributes['sandbox_credentials'] = $encryptedValue;
        } else {
            $this->attributes['production_credentials'] = $encryptedValue;
        }

        $this->attributes['credentials'] = $encryptedValue;
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
