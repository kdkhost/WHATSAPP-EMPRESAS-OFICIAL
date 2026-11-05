<?php

namespace App\Modules\AaPanel\Models;

use Illuminate\Database\Eloquent\Model;

class AaPanelOperationLog extends Model
{
    protected $table = 'aapanel_operation_logs';

    protected $fillable = [
        'tenant_id',
        'server_id',
        'account_id',
        'admin_id',
        'operation',
        'status',
        'http_status',
        'endpoint',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function server()
    {
        return $this->belongsTo(AaPanelServer::class, 'server_id');
    }

    public function account()
    {
        return $this->belongsTo(AaPanelAccount::class, 'account_id');
    }
}
