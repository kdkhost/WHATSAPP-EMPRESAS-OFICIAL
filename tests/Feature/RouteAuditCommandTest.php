<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Services\RouteAuditService;

it('gera relatorio de auditoria de rotas sem quebrar a execucao padrao', function () {
    $this->artisan('app:audit-routes')
        ->expectsOutput('Auditoria de rotas concluida.')
        ->assertExitCode(0);

    expect(File::glob(storage_path('app/audits/routes-audit-*.json')))->not->toBeEmpty();
});

it('ignora menus de addons vinculados a modulo desativado', function () {
    DB::table('registered_modules')->insert([
        'slug' => 'addon-desativado',
        'name' => 'Addon Desativado',
        'type' => 'addon',
        'version' => '1.0.0',
        'enabled' => false,
        'installed' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('addon_menus')->insert([
        'module_slug' => 'addon-desativado',
        'title' => 'Menu invalido',
        'icon' => 'bi-puzzle',
        'route' => 'admin.addons.generated.addon_desativado.index',
        'menu_order' => 10,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = app(RouteAuditService::class)->run();

    expect(collect($report['addon_menus_invalid'])->pluck('route')->all())
        ->not->toContain('admin.addons.generated.addon_desativado.index');
});
