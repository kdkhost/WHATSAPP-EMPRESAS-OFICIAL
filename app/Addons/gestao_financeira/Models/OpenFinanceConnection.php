<?php

namespace App\Addons\gestao_financeira\Models;

use App\Addons\gestao_financeira\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenFinanceConnection extends Model
{
    use TenantScoped;

    protected $table = 'addon_gestao_financeira_open_finance_connections';

    protected $fillable = [
        'tenant_id',
        'account_id',
        'provider',
        'item_id',
        'connector_id',
        'connector_name',
        'connector_image_url',
        'status',
        'error_message',
        'last_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
