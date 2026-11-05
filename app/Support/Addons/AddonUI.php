<?php

namespace App\Support\Addons;

class AddonUI
{
    public function __construct(
        protected AddonLayout $layout,
        protected AddonTheme $theme,
        protected AddonMenu $menu,
        protected AddonAssets $assets,
        protected AddonPermissions $permissions,
    ) {
    }

    public function blueprint(array $manifest): array
    {
        return [
            'layout' => $this->layout->resolve($manifest),
            'view_namespace' => $this->layout->viewNamespace($manifest),
            'menu' => $this->menu->normalize($manifest),
            'assets' => $this->assets->normalize($manifest),
            'permissions' => $this->permissions->normalize($manifest),
        ];
    }
}
