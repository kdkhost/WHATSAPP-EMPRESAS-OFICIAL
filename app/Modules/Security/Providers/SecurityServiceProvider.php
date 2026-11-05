<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\Security\Providers;

use App\Modules\Security\Http\Middleware\WafMiddleware;
use App\Services\CronManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Modulo Security - WAF enterprise (apenas superadmin):
 *  - Web Application Firewall com 7+ regras
 *  - Painel grafico (Chart.js) com Graphify
 *  - Auditoria, blocklist/allowlist, eventos
 *  - Geo-blocking, monitoramento em tempo real
 *  - Login attempts, brute-force detection
 */
class SecurityServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel): void
    {
        // WAF aplicado globalmente em todas as requisicoes
        $kernel->prependMiddleware(WafMiddleware::class);

        // Rotas admin - APENAS SUPERADMIN
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/security')->name('admin.security.')->group(function () {
            Route::get('/',          [\App\Modules\Security\Http\Controllers\SecurityDashboardController::class, 'index'])->name('dashboard');
            Route::get('/events',    [\App\Modules\Security\Http\Controllers\SecurityEventsController::class, 'index'])->name('events');
            Route::get('/charts',    [\App\Modules\Security\Http\Controllers\SecurityChartsController::class, 'data'])->name('charts');
            Route::get('/rules',     [\App\Modules\Security\Http\Controllers\SecurityRulesController::class, 'index'])->name('rules');
            Route::post('/rules/{rule}', [\App\Modules\Security\Http\Controllers\SecurityRulesController::class, 'update'])->name('rules.update');
            Route::get('/blocklist', [\App\Modules\Security\Http\Controllers\BlocklistController::class, 'index'])->name('blocklist');
            Route::post('/blocklist',[\App\Modules\Security\Http\Controllers\BlocklistController::class, 'store'])->name('blocklist.store');
            Route::delete('/blocklist/{id}', [\App\Modules\Security\Http\Controllers\BlocklistController::class, 'destroy'])->name('blocklist.destroy');
            Route::get('/allowlist', [\App\Modules\Security\Http\Controllers\AllowlistController::class, 'index'])->name('allowlist');
            Route::post('/allowlist',[\App\Modules\Security\Http\Controllers\AllowlistController::class, 'store'])->name('allowlist.store');
            Route::post('/allowlist/bulk', [\App\Modules\Security\Http\Controllers\AllowlistController::class, 'bulkAdd'])->name('allowlist.bulk');
            Route::delete('/allowlist/{id}', [\App\Modules\Security\Http\Controllers\AllowlistController::class, 'destroy'])->name('allowlist.destroy');
            Route::get('/audit',     [\App\Modules\Security\Http\Controllers\AuditController::class, 'index'])->name('audit');

            // Endpoints estendidos do WAF enterprise
            Route::get('/login-attempts',  [\App\Modules\Security\Http\Controllers\SecurityDashboardController::class, 'loginAttempts'])->name('login-attempts');
            Route::get('/realtime',        [\App\Modules\Security\Http\Controllers\SecurityDashboardController::class, 'realtime'])->name('realtime');
            Route::post('/blocklist/bulk', [\App\Modules\Security\Http\Controllers\BlocklistController::class, 'bulkAdd'])->name('blocklist.bulk');
            Route::get('/export',          [\App\Modules\Security\Http\Controllers\SecurityDashboardController::class, 'export'])->name('export');

            // Configurações globais do WAF (modo off/detect/block)
            Route::get('/waf/settings',    [\App\Modules\Security\Http\Controllers\WafSettingsController::class, 'index'])->name('waf.settings');
            Route::post('/waf/settings',   [\App\Modules\Security\Http\Controllers\WafSettingsController::class, 'update'])->name('waf.settings.update');
            Route::post('/waf/cache/clear',[\App\Modules\Security\Http\Controllers\WafSettingsController::class, 'clearCache'])->name('waf.cache.clear');
        });

        $this->loadViewsFrom(app_path('Modules/Security/Resources/views'), 'security');

        CronManager::register(
            name: 'security.cleanup',
            callback: fn () => app(\App\Modules\Security\Services\WafService::class)->cleanup(),
            intervalMinutes: 60,
            description: 'Limpa eventos de seguranca antigos (>30 dias) e expira blocklist temporaria.'
        );

        CronManager::register(
            name: 'security.brute_force_check',
            callback: fn () => app(\App\Modules\Security\Services\WafService::class)->detectBruteForce(),
            intervalMinutes: 5,
            description: 'Detecta brute-force e bloqueia IPs com >10 falhas de login em 15min.'
        );
    }
}
