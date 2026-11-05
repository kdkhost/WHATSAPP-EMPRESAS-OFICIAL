<?php

namespace App\Modules\AaPanel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AaPanelAccount extends Model
{
    protected $table = 'aapanel_accounts';

    protected $fillable = [
        'tenant_id',
        'server_id',
        'plan_id',
        'customer_id',
        'domain',
        'username',
        'password_encrypted',
        'ftp_username',
        'ftp_password_encrypted',
        'database_name',
        'database_username',
        'database_password_encrypted',
        'site_id',
        'status',
        'ssl_enabled',
        'provisioned_at',
        'suspended_at',
        'terminated_at',
        'meta',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'provisioned_at' => 'datetime',
        'suspended_at' => 'datetime',
        'terminated_at' => 'datetime',
        'meta' => 'array',
    ];

    public function server()
    {
        return $this->belongsTo(AaPanelServer::class, 'server_id');
    }

    public function plan()
    {
        return $this->belongsTo(AaPanelPlan::class, 'plan_id');
    }

    public function logs()
    {
        return $this->hasMany(AaPanelOperationLog::class, 'account_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class, 'tenant_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->where($field ?? 'id', $value);

        // Se for rota de painel, obrigatoriamente aplica escopo de tenant
        if (request()->is('painel') || request()->is('painel/*')) {
            $user = auth('web')->user();
            $tenant = $user ? \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first() : null;
            if ($tenant) {
                $query->where('tenant_id', $tenant->id);
            } else {
                $query->where('id', -1); // forca not found
            }
        } elseif (auth('web')->check() && !auth('admin')->check()) {
            $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', auth('web')->id())->first();
            if ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }
        }

        return $query->first();
    }

    public function setPasswordAttribute(string $value): void
    {
        if ($value !== '') {
            $this->attributes['password_encrypted'] = Crypt::encryptString($value);
        }
    }

    public function getPasswordAttribute(): string
    {
        return empty($this->password_encrypted) ? '' : Crypt::decryptString($this->password_encrypted);
    }

    public function setFtpPasswordAttribute(string $value): void
    {
        if ($value !== '') {
            $this->attributes['ftp_password_encrypted'] = Crypt::encryptString($value);
        }
    }

    public function getFtpPasswordAttribute(): string
    {
        return empty($this->ftp_password_encrypted) ? '' : Crypt::decryptString($this->ftp_password_encrypted);
    }

    public function setDatabasePasswordAttribute(string $value): void
    {
        if ($value !== '') {
            $this->attributes['database_password_encrypted'] = Crypt::encryptString($value);
        }
    }

    public function getDatabasePasswordAttribute(): string
    {
        return empty($this->database_password_encrypted) ? '' : Crypt::decryptString($this->database_password_encrypted);
    }
}
