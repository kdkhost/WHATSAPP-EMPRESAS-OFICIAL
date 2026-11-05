<?php

namespace App\Support\Addons;

use App\Support\Theme\ThemeManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class AddonTheme
{
    public function __construct(protected ThemeManager $themes)
    {
    }

    public function resolve(?Authenticatable $user, Request $request): array
    {
        return $this->themes->forAdmin($user, $request);
    }
}
