<?php

namespace App\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemVisit extends Model
{
    protected $table = 'system_visits';

    protected $guarded = ['id'];

    protected $casts = [
        'is_authenticated' => 'boolean',
        'is_unique_today'  => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Scope para visitantes online nos últimos N minutos (padrão: 5 min) */
    public function scopeOnline(Builder $query, int $minutes = 5): Builder
    {
        return $query->where('last_activity_at', '>=', now()->subMinutes($minutes));
    }

    /** Scope para usuários logados */
    public function scopeAuthenticated(Builder $query): Builder
    {
        return $query->where('is_authenticated', true);
    }

    /** Scope para visitantes anônimos (apenas visitando o sistema) */
    public function scopeAnonymous(Builder $query): Builder
    {
        return $query->where('is_authenticated', false);
    }

    /** Scope para acessos do dia atual */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }
}
