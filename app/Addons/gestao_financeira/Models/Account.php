<?php

namespace App\Addons\gestao_financeira\Models;

use App\Addons\gestao_financeira\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use TenantScoped;
    /**
     * Tabela associada ao modelo.
     */
    protected $table = 'addon_gestao_financeira_accounts';

    /**
     * Atributos atribuíveis em massa.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'initial_balance',
        'current_balance',
        'description',
    ];

    /**
     * Casts de tipos de atributos.
     */
    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    /**
     * Transações associadas a esta conta.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function openFinanceConnection()
    {
        return $this->hasOne(OpenFinanceConnection::class, 'account_id');
    }
}
