<?php

namespace App\Modules\CpanelIntegration\Models;

use Illuminate\Database\Eloquent\Model;

class CpanelServer extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'hostname', 'port', 'type', 'username', 'api_token',
        'verify_ssl', 'active', 'last_check_at', 'last_check_status', 'meta',
    ];

    protected $casts = [
        'api_token'     => 'encrypted',
        'verify_ssl'    => 'boolean',
        'active'        => 'boolean',
        'last_check_at' => 'datetime',
        'meta'          => 'array',
    ];

    protected $hidden = ['api_token'];

    public function getBaseUrlAttribute(): string
    {
        return "https://{$this->hostname}:{$this->port}";
    }
}
