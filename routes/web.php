<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Cron\CronController;
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;

/*
 * Rotas publicas (frontend) - sao consumidas tambem pelo React via API.
 * Mantemos rotas SSR como fallback caso o build do React nao esteja presente.
 */

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('/manutencao', [PublicSiteController::class, 'maintenance'])->name('public.maintenance');

// Checkout público tokenizado de Faturas
Route::get('/fatura/{token}',         [\App\Modules\Billing\Http\Controllers\PublicInvoiceController::class, 'show'])->name('public.invoice.show');
Route::post('/fatura/{token}/pix',    [\App\Modules\Billing\Http\Controllers\PublicInvoiceController::class, 'generatePix'])->name('public.invoice.pix');
Route::post('/fatura/{token}/cartao', [\App\Modules\Billing\Http\Controllers\PublicInvoiceController::class, 'processCard'])->name('public.invoice.card');
Route::get('/fatura/{token}/status',  [\App\Modules\Billing\Http\Controllers\PublicInvoiceController::class, 'status'])->name('public.invoice.status');

// Redirecionamentos para rotas canônicas do frontend
Route::redirect('/politica-de-privacidade', '/privacidade', 301);
Route::redirect('/termos-de-uso', '/termos', 301);

// Fallback para React Router (MPA/SPA)
Route::get('/{any}', [PublicSiteController::class, 'home'])
    ->where('any', '^(?!api|admin|painel|pwa|cron|promocao|oferta|login|logout|cadastro|register|esqueci-senha|redefinir-senha|password|manutencao|webhooks|fatura|contrato|manifest\.json|sw\.js|up|storage|uploads).*$');

// API publica para o React/Vue do frontend
Route::get('/api/site', [PublicSiteController::class, 'siteData'])->name('public.site-data');

// Auth publica (login, cadastro, reset) - layout 2 colunas em telas grandes
Route::get('/login',         [LoginController::class, 'show'])->name('login');
Route::post('/login',        [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout',       [LoginController::class, 'logout'])->name('logout');
Route::get('/cadastro',      [RegisterController::class, 'show'])->name('register');
Route::post('/cadastro',     [RegisterController::class, 'register'])->name('register.attempt');
Route::get('/esqueci-senha', [PasswordResetController::class, 'show'])->name('password.request');
Route::post('/esqueci-senha',[PasswordResetController::class, 'send'])->name('password.send');
Route::get('/redefinir-senha/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/redefinir-senha', [PasswordResetController::class, 'reset'])->name('password.update');

// Cron externo (opcional - cPanel)
Route::match(['GET', 'POST'], '/cron', [CronController::class, 'run'])
    ->middleware('cron.token')
    ->name('cron.external');




/*
 * Painel administrativo (AdminLTE 4)
 */
Route::prefix('admin')->name('admin.')->group(function () {
    // Auth admin
    Route::get('/login',  [AdminAuthController::class, 'show'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'attempt'])->name('login.attempt');
    Route::post('/logout',[AdminAuthController::class, 'logout'])->name('logout');

    // Area autenticada
    Route::middleware('admin')->group(function () {
        // 2FA - verificacao pos-login (antes de acessar o painel)
        Route::get('/2fa/verify',  [\App\Http\Controllers\Admin\TwoFactorController::class, 'showVerify'])->name('2fa.verify');
        Route::post('/2fa/verify', [\App\Http\Controllers\Admin\TwoFactorController::class, 'verify'])->name('2fa.verify.check');

        // Todas as rotas abaixo exigem 2FA confirmado (se ativo)
        Route::middleware(['2fa', 'admin.cache'])->group(function () {
            Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
            Route::get('/dashboard/kpis', [DashboardController::class, 'kpis'])->name('dashboard.kpis');

            // 2FA setup/disable (dentro do painel, apos confirmar)
            Route::get('/2fa',          [\App\Http\Controllers\Admin\TwoFactorController::class, 'showSetup'])->name('2fa.index');
            Route::post('/2fa/setup',   [\App\Http\Controllers\Admin\TwoFactorController::class, 'setup'])->name('2fa.setup');
            Route::post('/2fa/confirm', [\App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->name('2fa.confirm');
            Route::post('/2fa/disable', [\App\Http\Controllers\Admin\TwoFactorController::class, 'disable'])->name('2fa.disable');

        // Notificacoes
        Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications');
        Route::get('/notifications/feed', [\App\Http\Controllers\Admin\NotificationController::class, 'feed'])->name('notifications.feed');
        Route::get('/notifications/{notification}', [\App\Http\Controllers\Admin\NotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Admin\NotificationController::class, 'read'])->name('notifications.read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Admin\NotificationController::class, 'readAll'])->name('notifications.readAll');
        Route::post('/notifications/sound', [\App\Http\Controllers\Admin\NotificationController::class, 'sound'])->name('notifications.sound');

        // Perfil do admin
        Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'index'])->name('profile');
        Route::post('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password', [\App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->name('profile.password');

        // Tour guiado (Intro.js) - marcar como concluido
        Route::post('/tour/complete', function () {
            $admin = \Illuminate\Support\Facades\Auth::guard('admin')->user();
            $admin->update(['tour_completed_at' => now()]);
            return response()->json(['ok' => true]);
        })->name('tour.complete');
        Route::post('/tour/reset', function () {
            $admin = \Illuminate\Support\Facades\Auth::guard('admin')->user();
            $admin->update(['tour_completed_at' => null]);
            return response()->json(['ok' => true]);
        })->name('tour.reset');

        // Upload global
        Route::post('/uploads/{context?}', [\App\Http\Controllers\Admin\UploadController::class, 'store'])->name('uploads.store');

        // Kanban generico
        Route::get('/kanban/{slug}', [\App\Http\Controllers\Admin\KanbanController::class, 'index'])->name('kanban.index');
        Route::post('/kanban/move', [\App\Http\Controllers\Admin\KanbanController::class, 'moveCard'])->name('kanban.move');
        Route::post('/kanban/{slug}/cards', [\App\Http\Controllers\Admin\KanbanController::class, 'storeCard'])->name('kanban.cards.store');
        Route::patch('/kanban/cards/{id}', [\App\Http\Controllers\Admin\KanbanController::class, 'updateCard'])->name('kanban.cards.update');
        Route::delete('/kanban/cards/{id}', [\App\Http\Controllers\Admin\KanbanController::class, 'destroyCard'])->name('kanban.cards.destroy');
        Route::post('/kanban/{slug}/columns', [\App\Http\Controllers\Admin\KanbanController::class, 'storeColumn'])->name('kanban.columns.store');
        Route::patch('/kanban/columns/{id}', [\App\Http\Controllers\Admin\KanbanController::class, 'updateColumn'])->name('kanban.columns.update');
        Route::delete('/kanban/columns/{id}', [\App\Http\Controllers\Admin\KanbanController::class, 'destroyColumn'])->name('kanban.columns.destroy');

        // Atalhos de kanban para contextos especificos
        Route::get('/menus',      fn () => redirect()->route('admin.kanban.index', 'menus'));
        Route::get('/categories', fn () => redirect()->route('admin.kanban.index', 'categorias'));
        Route::get('/cards',      fn () => redirect()->route('admin.kanban.index', 'cards'));

        // Preferencias (tema dark/light)
        Route::post('/preferences/theme', function (\Illuminate\Http\Request $r) {
            $theme = $r->input('theme') === 'dark' ? 'dark' : 'light';
            $user = \Illuminate\Support\Facades\Auth::guard('admin')->user();
            if ($user) { $user->update(['theme_dark' => $theme === 'dark']); }
            $r->session()->put('theme_mode', $theme);
            // Cookie persiste por 1 ano, evita piscar entre paginas
            return response()->json(['ok' => true])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('Vary', 'Cookie')
                ->cookie('admin_theme', $theme, 60 * 24 * 365, '/', null, false, false);
        })->name('preferences.theme');

        // Sistema (Cron, Addons)
        Route::get('/sidebar/reload', [\App\Http\Controllers\Admin\AddonController::class, 'reloadSidebar'])
            ->name('sidebar.reload');

        Route::get('/system/cron', function () {
            $jobs = \App\Services\CronManager::jobs();
            $state = is_file(storage_path('app/cron-state.json'))
                ? json_decode(file_get_contents(storage_path('app/cron-state.json')), true) : [];

            // KPIs
            $now = time();
            $stats = [
                'total'      => count($jobs),
                'ok'         => 0,
                'errors'     => 0,
                'pending'    => 0,
                'overdue'    => 0,
                'last_check' => $state['last_check'] ?? null,
            ];
            foreach ($jobs as $name => $job) {
                $s = $state['jobs'][$name] ?? null;
                if (! $s || empty($s['last_status'])) {
                    $stats['pending']++;
                    continue;
                }
                if ($s['last_status'] === 'ok') $stats['ok']++;
                if ($s['last_status'] === 'error') $stats['errors']++;
                $expectedNext = (int) ($s['last_run'] ?? 0) + (((int) ($job['interval'] ?? 5)) * 60);
                if ($expectedNext + 60 < $now && $s['last_status'] === 'ok' && !empty($s['last_run'])) {
                    $stats['overdue']++;
                }
            }

            return view('admin.system.cron', compact('jobs', 'state', 'stats'));
        })->name('system.cron');

        Route::post('/system/cron/{name}/run', function (string $name) {
            $result = app(\App\Services\CronManager::class)->runJob($name);
            return response()->json(['ok' => true, 'result' => $result]);
        })->name('system.cron.run');

        Route::post('/system/cron/run-main', function (\Illuminate\Http\Request $request) {
            $force = (bool) $request->input('force', false);
            $report = app(\App\Services\CronManager::class)->runMainCycle($force);
            return response()->json(['ok' => true, 'report' => $report]);
        })->name('system.cron.run-main');

        Route::get('/system/audit', [\App\Http\Controllers\Admin\SystemAuditController::class, 'index'])
            ->name('system.audit');
        Route::post('/system/audit/run', [\App\Http\Controllers\Admin\SystemAuditController::class, 'run'])
            ->name('system.audit.run');

        // Addons (gerenciamento pelo superadmin)
        Route::middleware('role:superadmin')->prefix('addons')->name('addons.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Admin\AddonController::class, 'index'])->name('index');
            Route::get('/marketplace',     [\App\Http\Controllers\Admin\AddonController::class, 'marketplace'])->name('marketplace');
            Route::get('/{slug}/detalhes', [\App\Http\Controllers\Admin\AddonController::class, 'show'])->name('show');
            Route::post('/marketplace/{slug}/download', [\App\Http\Controllers\Admin\AddonController::class, 'downloadFromMarketplace'])->name('marketplace.download');
            Route::post('/{slug}/install', [\App\Http\Controllers\Admin\AddonController::class, 'install'])->name('install');
            Route::post('/{slug}/activate', [\App\Http\Controllers\Admin\AddonController::class, 'activate'])->name('activate');
            Route::post('/{slug}/deactivate', [\App\Http\Controllers\Admin\AddonController::class, 'deactivate'])->name('deactivate');
            Route::post('/{slug}/migrate', [\App\Http\Controllers\Admin\AddonController::class, 'migrate'])->name('migrate');
            Route::post('/{slug}/uninstall', [\App\Http\Controllers\Admin\AddonController::class, 'uninstall'])->name('uninstall');
            Route::post('/{slug}/enable',  [\App\Http\Controllers\Admin\AddonController::class, 'enable'])->name('enable');
            Route::post('/{slug}/disable', [\App\Http\Controllers\Admin\AddonController::class, 'disable'])->name('disable');
            Route::post('/{slug}/update',  [\App\Http\Controllers\Admin\AddonController::class, 'update'])->name('update');
        });

        // Editor de Arquivos do Sistema (apenas superadmin)
        Route::middleware('role:superadmin')->prefix('file-editor')->name('file-editor.')->group(function () {
            $ctrl = \App\Http\Controllers\Admin\FileEditorController::class;
            Route::get('/',        [$ctrl, 'index'])->name('index');
            Route::post('/load',   [$ctrl, 'loadFile'])->name('load');
            Route::post('/save',   [$ctrl, 'saveFile'])->name('save');
        });

        // Configurações de Preload (tela de carregamento premium)
        Route::prefix('settings/preload')->name('settings.preload.')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Admin\PreloadController::class, 'index'])->name('index');
            Route::post('/update', [\App\Http\Controllers\Admin\PreloadController::class, 'update'])->name('update');
            Route::get('/preview', [\App\Http\Controllers\Admin\PreloadController::class, 'preview'])->name('preview');
        });

        // Permissoes (gerenciamento - apenas superadmin)
        // (registrado pelo PermissionsServiceProvider)

        // As rotas de cada modulo sao registradas pelos respectivos
        // ServiceProviders (modulo desacoplado).
        }); // fecha middleware('2fa')
    }); // fecha middleware('admin')
});

// Alias de compatibilidade de nomes de rota do cron (com e sem prefixo admin)
Route::middleware(['web', 'admin'])->group(function () {
    Route::post('/admin/system/cron/run-main-compat', function (\Illuminate\Http\Request $request) {
        $force = (bool) $request->input('force', false);
        $report = app(\App\Services\CronManager::class)->runMainCycle($force);
        return response()->json(['ok' => true, 'report' => $report]);
    })->name('system.cron.run-main');
});



/*
 |--------------------------------------------------------------------------
 | Compatibilidade /painel (dispatcher inteligente)
 |--------------------------------------------------------------------------
 |
 | O prefixo /painel e compartilhado com o painel do tenant.
 | NAO aplicamos middleware admin no grupo para nao bloquear tenants.
 | Cada rota verifica o contexto (admin vs tenant assistido vs tenant normal).
 |
 */



Route::fallback(function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::notice('Rota web nao encontrada', [
        'path' => $request->path(),
        'method' => $request->method(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    abort(404);
})->name('fallback.404');
