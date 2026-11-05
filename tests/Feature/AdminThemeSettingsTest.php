<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

function adminThemePayload(): array
{
    return [
        'primary' => '#123456',
        'secondary' => '#654321',
        'success' => '#198754',
        'danger' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#0dcaf0',
        'dark' => '#1f2937',
        'light' => '#f8fafc',
        'sidebar_bg' => '#0f172a',
        'sidebar_active' => '#123456',
        'navbar_bg' => '#fef3c7',
        'body_bg' => '#ecfeff',
    ];
}

it('persiste as cores do tema pelo painel administrativo', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Theme Admin',
        'email' => 'theme-admin@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $payload = adminThemePayload();

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.settings.theme.update'), $payload)
        ->assertOk()
        ->assertJson(['ok' => true]);

    foreach ($payload as $key => $value) {
        $this->assertDatabaseHas('settings', [
            'group' => 'theme',
            'key' => $key,
            'value' => $value,
        ]);
    }
});

it('renderiza o layout admin com as cores salvas inclusive no modo escuro', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Theme Viewer',
        'email' => 'theme-viewer@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    foreach (adminThemePayload() as $key => $value) {
        DB::table('settings')->updateOrInsert(
            ['group' => 'theme', 'key' => $key],
            ['value' => $value, 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    $this->actingAs($admin, 'admin')
        ->withCookie('admin_theme', 'dark')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('--bs-primary: #123456;', false)
        ->assertSee('--bs-secondary: #654321;', false)
        ->assertSee('--bs-primary-rgb: 18, 52, 86;', false)
        ->assertSee('--app-sidebar-bg: #111827;', false)
        ->assertSee('--app-sidebar-color: #f8fafc;', false)
        ->assertSee('--admin-dark-surface: #0b1020;', false)
        ->assertDontSee('--bs-primary: #5b4df6;', false);

    $this->actingAs($admin, 'admin')
        ->withCookie('admin_theme', 'light')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('--app-navbar-bg: #fef3c7;', false)
        ->assertSee('--app-navbar-color: #0f172a;', false)
        ->assertSee('--app-body-bg: #ecfeff;', false)
        ->assertSee('--app-body-color: #111827;', false)
        ->assertSee('window.__adminThemeVariables =', false)
        ->assertSee('[data-bs-theme="light"] body { background-color: var(--app-body-bg) !important; }', false);
});

it('retorna headers sem cache ao persistir preferencia de tema', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Theme Header Admin',
        'email' => 'theme-header@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.preferences.theme'), ['theme' => 'dark'])
        ->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Vary', 'Cookie');

    $expires = $response->headers->get('Expires');
    expect($expires)->toContain('1990');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
});
