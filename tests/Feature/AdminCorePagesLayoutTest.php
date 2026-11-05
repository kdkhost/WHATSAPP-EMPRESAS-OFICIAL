<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Modules\Backup\Services\BackupService;

it('renderiza as paginas principais reorganizadas do administrativo', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $admin = AdminUser::create([
        'name' => 'Admin Layout',
        'email' => 'admin-layout@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    app()->bind(BackupService::class, fn () => new class extends BackupService {
        public function list(): array
        {
            return [[
                'name' => 'db_2026-05-23_120000.sql.gz',
                'size_mb' => 12.5,
                'date' => '23/05/2026 12:00',
            ]];
        }
    });

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('.admin-premium-hero + .row', false)
        ->assertSee('margin-top: 0 !important', false)
        ->assertSee('Painel operacional')
        ->assertSee('Saude financeira')
        ->assertSee('Ultimas faturas');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Catalogo de relatorios')
        ->assertSee('Areas disponiveis')
        ->assertSee('Mensagens');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.backups.index'))
        ->assertOk()
        ->assertSee('Geracao imediata de backup')
        ->assertSee('Arquivos disponiveis')
        ->assertSee('db_2026-05-23_120000.sql.gz');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.pwa.index'))
        ->assertOk()
        ->assertSee('Aplicativo instalavel')
        ->assertSee('Preview do app')
        ->assertSee('Arquivos do PWA');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.system.cron'))
        ->assertOk()
        ->assertSee('Automacao interna')
        ->assertSee('Jobs registrados')
        ->assertSee('Cron externo');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.file-editor.index'))
        ->assertOk()
        ->assertSee('Editor interno para ajustes controlados em arquivos do sistema')
        ->assertSee('Explorador de arquivos')
        ->assertSee('Salvar e compilar');
});
