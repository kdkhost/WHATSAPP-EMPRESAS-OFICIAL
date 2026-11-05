<?php

namespace App\Support\Addons;

class AddonPermissions
{
    public function normalize(array $manifest): array
    {
        return collect($manifest['permissions'] ?? [])
            ->filter(fn ($item) => is_array($item) && ! empty($item['action']))
            ->map(fn (array $item) => [
                'action' => (string) $item['action'],
                'label' => (string) ($item['label'] ?? $item['action']),
            ])
            ->values()
            ->all();
    }
}
