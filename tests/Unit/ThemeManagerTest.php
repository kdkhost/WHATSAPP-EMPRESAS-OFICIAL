<?php

use App\Models\User;
use App\Modules\Settings\Services\SettingsManager;
use App\Support\Theme\ThemeManager;
use App\Support\Theme\ThemeResolver;
use App\Support\Theme\ThemeVariables;
use Illuminate\Http\Request;

it('resolve tema do admin por cookie e gera css variables', function () {
    $user = new User([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'theme_dark' => false,
    ]);

    $settings = Mockery::mock(SettingsManager::class);
    $settings->shouldReceive('get')->andReturnUsing(function (string $key, mixed $default = null) {
        return match ($key) {
            'theme.primary' => '#123456',
            'theme.secondary' => '#654321',
            'theme.sidebar_bg' => '#101820',
            default => $default,
        };
    });

    $request = Request::create('/admin');
    $request->cookies->set('admin_theme', 'dark');
    $request->setLaravelSession(app('session')->driver());

    $theme = new ThemeManager(new ThemeVariables($settings), new ThemeResolver());
    $payload = $theme->forAdmin($user, $request);
    $lightVariables = $theme->variablesForMode('light');
    $darkVariables = $theme->variablesForMode('dark');

    expect($payload['is_dark'])->toBeTrue()
        ->and($payload['theme_color'])->toBe($payload['variables']['bs-body-bg'])
        ->and($payload['variables']['app-sidebar-bg'])->toBe('#111827')
        ->and($payload['variables']['app-sidebar-color'])->toBe('#f8fafc')
        ->and($payload['variables']['app-sidebar-active-color'])->toBe('#f8fafc')
        ->and($lightVariables['app-navbar-bg'])->toBe('#f8fafc')
        ->and($darkVariables['admin-dark-bg'])->toBe('#020617')
        ->and($payload['css_variables'])->toContain('--bs-primary: #123456;')
        ->and($payload['css_variables'])->toContain('--bs-secondary: #654321;')
        ->and($payload['css_variables'])->toContain('--bs-primary-rgb: 18, 52, 86;')
        ->and($payload['css_variables'])->toContain('--app-sidebar-bg: #111827;')
        ->and($payload['css_variables'])->toContain('--app-sidebar-color: #f8fafc;');
});
