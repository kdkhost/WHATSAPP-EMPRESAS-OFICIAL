<?php

namespace App\Support\Addons;

class AddonLayout
{
    public function resolve(array $manifest): string
    {
        return (string) ($manifest['layout'] ?? 'admin.layouts.app');
    }

    public function viewNamespace(array $manifest): string
    {
        return (string) ($manifest['view_namespace'] ?? ($manifest['slug'] ?? 'addon'));
    }
}
