<?php

namespace App\Modules\Contracts\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'preset_key',
        'name',
        'slug',
        'category',
        'description',
        'content_html',
        'variables',
        'background_type',
        'background_path',
        'layout_json',
        'require_witness',
        'active',
    ];

    protected $casts = [
        'variables' => 'array',
        'layout_json' => 'array',
        'require_witness' => 'boolean',
        'active' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
