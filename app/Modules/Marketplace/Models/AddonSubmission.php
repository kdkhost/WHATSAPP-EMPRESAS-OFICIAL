<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonSubmission extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'marketplace_addon_id',
        'name',
        'slug',
        'version',
        'zip_path',
        'extract_path',
        'installed_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'manifest',
        'security_report',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'manifest' => 'array',
        'security_report' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
