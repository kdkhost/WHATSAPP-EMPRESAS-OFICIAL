<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Tenancy\Models\TenantAddon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceAddon extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'version',
        'author',
        'category',
        'icon',
        'color',
        'price',
        'billing_cycle',
        'commercial',
        'is_active',
        'is_installed',
        'source',
        'submission_id',
        'approved_by',
        'approved_at',
        'package_path',
        'description',
        'manifest',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'commercial' => 'boolean',
        'is_active' => 'boolean',
        'is_installed' => 'boolean',
        'approved_at' => 'datetime',
        'manifest' => 'array',
    ];

    public function tenantAddons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }
}
