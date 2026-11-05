<?php

namespace App\Support\Addons;

class AddonMenu
{
    public function normalize(array $manifest): ?array
    {
        $menu = $manifest['menu'] ?? null;

        if (! is_array($menu) || empty($menu['label'])) {
            return null;
        }

        return [
            'group' => (string) ($menu['group'] ?? 'addons'),
            'label' => (string) $menu['label'],
            'icon' => (string) ($menu['icon'] ?? 'bi-puzzle'),
            'route' => (string) ($menu['route'] ?? ''),
            'order' => (int) ($menu['order'] ?? 0),
        ];
    }
}
