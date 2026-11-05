<?php

namespace App\Support\Addons;

class AddonAssets
{
    public function normalize(array $manifest): array
    {
        $assets = $manifest['assets'] ?? [];

        return [
            'css' => array_values(array_filter((array) ($assets['css'] ?? []))),
            'js' => array_values(array_filter((array) ($assets['js'] ?? []))),
        ];
    }
}
