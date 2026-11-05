<?php

namespace App\Modules\AaPanel\Models;

use Illuminate\Database\Eloquent\Model;

class AaPanelPlan extends Model
{
    protected $table = 'aapanel_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'server_id',
        'disk_mb',
        'bandwidth_mb',
        'database_limit',
        'ftp_limit',
        'domain_limit',
        'php_version',
        'ssl_enabled',
        'active',
        'features',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'active' => 'boolean',
        'features' => 'array',
    ];

    public function server()
    {
        return $this->belongsTo(AaPanelServer::class, 'server_id');
    }

    public function accounts()
    {
        return $this->hasMany(AaPanelAccount::class, 'plan_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class, 'tenant_id');
    }
}
