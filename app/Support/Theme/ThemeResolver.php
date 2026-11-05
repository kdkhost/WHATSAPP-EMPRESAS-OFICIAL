<?php

namespace App\Support\Theme;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class ThemeResolver
{
    public function resolveMode(Request $request, ?Authenticatable $user = null): string
    {
        $cookie = $request->cookie('admin_theme');
        if (in_array($cookie, ['dark', 'light'], true)) {
            return $cookie;
        }

        if ($request->hasSession()) {
            $session = $request->session()->get('theme_mode');
            if (in_array($session, ['dark', 'light'], true)) {
                return $session;
            }
        }

        return (bool) ($user?->theme_dark ?? false) ? 'dark' : 'light';
    }

    public function themeColor(string $mode, array $variables): string
    {
        if ($mode === 'dark') {
            return (string) ($variables['bs-body-bg'] ?? '#020617');
        }

        return (string) ($variables['app-navbar-bg'] ?? '#ffffff');
    }
}
