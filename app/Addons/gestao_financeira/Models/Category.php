<?php

namespace App\Addons\gestao_financeira\Models;

use App\Addons\gestao_financeira\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use TenantScoped;
    /**
     * Tabela associada ao modelo.
     */
    protected $table = 'addon_gestao_financeira_categories';

    /**
     * Atributos atribuíveis em massa.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'color',
    ];

    /**
     * Transações associadas a esta categoria.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'category_id');
    }
}
