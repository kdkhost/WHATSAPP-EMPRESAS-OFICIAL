<?php

namespace App\Modules\Pwa\Models;

use Illuminate\Database\Eloquent\Model;

class PwaFooterMenuItem extends Model
{
    protected $fillable = [
        'label',
        'icon',
        'url',
        'route_name',
        'position',
        'is_active',
        'match_patterns',
        'role',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'position' => 'integer',
        'match_patterns' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position', 'asc');
    }

    public function isVisibleFor(?string $guard): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->role === null || $this->role === '') {
            return true;
        }

        if ($guard === 'web' && $this->role === 'tenant') {
            return true;
        }

        if ($guard === 'admin' && $this->role === 'admin') {
            return true;
        }

        if ($this->role === 'both') {
            return true;
        }

        return false;
    }

    public function resolvedUrl(): string
    {
        if (! empty($this->url)) {
            return $this->url;
        }

        if (! empty($this->route_name) && \Illuminate\Support\Facades\Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return url('/painel');
    }

    public function isActive(): bool
    {
        $patterns = $this->match_patterns ?? [];

        foreach ($patterns as $pattern) {
            if (empty($pattern)) {
                continue;
            }

            if (request()->routeIs($pattern)) {
                return true;
            }

            if (request()->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
