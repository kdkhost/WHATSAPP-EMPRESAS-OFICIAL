<?php

namespace App\Modules\EmailBilling\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantEmailConfig extends Model
{
    protected $table = 'tenant_email_configs';

    protected $attributes = [
        'driver'  => 'smtp',
        'enabled' => false,
    ];

    protected $fillable = [
        'tenant_id', 'enabled', 'driver', 'host', 'port', 'encryption',
        'username', 'password', 'from_email', 'from_name', 'reply_to',
        'api_key', 'settings', 'last_test_at', 'last_test_status', 'last_test_error',
    ];

    protected $casts = [
        'enabled'      => 'boolean',
        'password'     => 'encrypted',
        'api_key'      => 'encrypted',
        'settings'     => 'array',
        'last_test_at' => 'datetime',
    ];

    protected $hidden = ['password', 'api_key'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    /** Configuracao para uso pelo SmtpMailer. */
    public function asMailerConfig(): array
    {
        return [
            'driver'     => $this->driver,
            'host'       => $this->host,
            'port'       => $this->port,
            'encryption' => $this->encryption === 'none' ? null : $this->encryption,
            'username'   => $this->username,
            'password'   => $this->password,
            'from'       => ['address' => $this->from_email, 'name' => $this->from_name],
            'reply_to'   => $this->reply_to,
            'api_key'    => $this->api_key,
        ];
    }
}
