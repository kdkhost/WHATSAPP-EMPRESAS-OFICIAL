<?php

use App\Models\AdminUser;
use App\Http\Middleware\Ensure2FA;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('ativa limpeza automatica de cache usando schema real de cron_jobs', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Super Admin Cache',
        'email' => 'cache-superadmin@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');

    $this->postJson('/admin/cache/auto', ['enabled' => true])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(DB::table('cron_jobs')->where('name', 'cache:auto-clear')->where('enabled', true)->exists())->toBeTrue();
});

it('mantem botao de limpeza automatica marcado apos limpar cache da aplicacao', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Super Admin Cache Reload',
        'email' => 'cache-reload-superadmin@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');

    DB::table('cron_jobs')->updateOrInsert(
        ['name' => 'cache:auto-clear'],
        [
            'description' => 'Limpa caches do Laravel automaticamente',
            'interval_min' => 1440,
            'enabled' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );
    Cache::forget('cache_manager.auto_enabled');

    $this->get('/admin/cache')
        ->assertOk()
        ->assertSee('id="auto-clear" checked', false);
});
