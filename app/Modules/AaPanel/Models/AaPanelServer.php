<?php

namespace App\Modules\AaPanel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AaPanelServer extends Model
{
    protected $table = 'aapanel_servers';

    protected $fillable = [
        'tenant_id',
        'name',
        'host',
        'port',
        'security_entry',
        'use_ssl',
        'api_key_encrypted',
        'status',
        'last_check_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'use_ssl' => 'boolean',
        'last_check_at' => 'datetime',
        'meta' => 'array',
    ];

    public function setApiKeyAttribute(string $value): void
    {
        if ($value !== '') {
            $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
        } else {
            $this->attributes['api_key_encrypted'] = null;
        }
    }

    public function getApiKeyAttribute(): string
    {
        if (empty($this->api_key_encrypted)) {
            return '';
        }

        return Crypt::decryptString($this->api_key_encrypted);
    }

    public function baseUrl(): string
    {
        $scheme = $this->use_ssl ? 'https' : 'http';
        $host = rtrim($this->host, '/');

        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            $host = preg_replace('#^https?://#', '', $host);
        }
        
        $host = preg_replace('#:\d+$#', '', $host);

        $entry = $this->security_entry ? '/'.trim($this->security_entry, '/') : '';

        return "{$scheme}://{$host}:{$this->port}{$entry}";
    }

    public function apiUrl(): string
    {
        $scheme = $this->use_ssl ? 'https' : 'http';
        $host = rtrim($this->host, '/');

        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            $host = preg_replace('#^https?://#', '', $host);
        }
        
        $host = preg_replace('#:\d+$#', '', $host);

        return "{$scheme}://{$host}:{$this->port}";
    }

    public function plans()
    {
        return $this->hasMany(AaPanelPlan::class, 'server_id');
    }

    public function accounts()
    {
        return $this->hasMany(AaPanelAccount::class, 'server_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class, 'tenant_id');
    }
}
