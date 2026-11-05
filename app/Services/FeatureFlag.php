<?php

namespace App\Services;

use App\Modules\Tenancy\Models\TenantInstance;

class FeatureFlag
{
    public function enabled(string $flag, ?string $context = null, ?TenantInstance $instance = null): bool
    {
        $global = config("whatsapp.feature_flags.{$flag}");

        if ($global === false) {
            return false;
        }

        if ($instance !== null && $instance->id) {
            $overrides = (array) data_get($instance->meta ?? [], 'feature_flags', []);
            if (array_key_exists($flag, $overrides)) {
                return (bool) $overrides[$flag];
            }
        }

        return $global ?? true;
    }

    public function assertEnabled(string $flag, ?string $context = null, ?TenantInstance $instance = null): void
    {
        if (! $this->enabled($flag, $context, $instance)) {
            throw new \RuntimeException("Feature '{$flag}' nao esta habilitada" . ($context ? " para {$context}" : '') . '.');
        }
    }

    public function all(): array
    {
        return config('whatsapp.feature_flags', []);
    }

    public function setInstanceFlag(TenantInstance $instance, string $flag, bool $value): void
    {
        $meta = $instance->meta ?? [];
        $meta['feature_flags'] = array_merge((array) ($meta['feature_flags'] ?? []), [$flag => $value]);
        $instance->forceFill(['meta' => $meta])->save();
    }
}
