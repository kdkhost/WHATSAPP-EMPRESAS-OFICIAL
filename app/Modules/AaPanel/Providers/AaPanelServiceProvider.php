<?php

namespace App\Modules\AaPanel\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AaPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'aapanel');

        $this->loadMigrationsFrom(database_path('migrations'));

        Route::middleware(['web', 'admin', '2fa', 'admin.cache'])
            ->prefix('admin/aapanel')
            ->name('admin.aapanel.')
            ->group(function () {
                $dashboard = \App\Modules\AaPanel\Http\Controllers\AaPanelDashboardController::class;
                $serverCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelServerController::class;
                $planCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelPlanController::class;
                $accountCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelAccountController::class;

                Route::get('/', [$dashboard, 'index'])->name('dashboard');

                Route::get('/servers', [$serverCtrl, 'index'])->name('servers.index');
                Route::post('/servers', [$serverCtrl, 'store'])->name('servers.store');
                Route::post('/servers/sync-all', [$serverCtrl, 'syncAll'])->name('servers.sync-all');
                Route::put('/servers/{server}', [$serverCtrl, 'update'])->name('servers.update');
                Route::delete('/servers/{server}', [$serverCtrl, 'destroy'])->name('servers.destroy');
                Route::post('/servers/{server}/test', [$serverCtrl, 'test'])->name('servers.test');
                Route::post('/servers/{server}/metrics', [$serverCtrl, 'metrics'])->name('servers.metrics');
                Route::post('/servers/test-connection', [$serverCtrl, 'testConnection'])->name('servers.test-connection');

                $siteCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelSiteController::class;
                Route::get('/servers/{server}/sites', [$siteCtrl, 'index'])->name('servers.sites.index');
                Route::post('/servers/{server}/sites/{siteId}/start', [$siteCtrl, 'start'])->name('servers.sites.start');
                Route::post('/servers/{server}/sites/{siteId}/stop', [$siteCtrl, 'stop'])->name('servers.sites.stop');
                Route::delete('/servers/{server}/sites/{siteId}', [$siteCtrl, 'destroy'])->name('servers.sites.destroy');
                Route::post('/servers/{server}/sites/sync', [$siteCtrl, 'sync'])->name('servers.sites.sync');
                Route::post('/servers/{server}/sites/{siteId}/import', [$siteCtrl, 'import'])->name('servers.sites.import');
                Route::get('/servers/{server}/php-versions', [$siteCtrl, 'phpVersions'])->name('servers.php-versions');

                Route::get('/plans', [$planCtrl, 'index'])->name('plans.index');
                Route::post('/plans', [$planCtrl, 'store'])->name('plans.store');
                Route::put('/plans/{plan}', [$planCtrl, 'update'])->name('plans.update');
                Route::delete('/plans/{plan}', [$planCtrl, 'destroy'])->name('plans.destroy');

                Route::get('/accounts', [$accountCtrl, 'index'])->name('accounts.index');
                Route::get('/accounts/{account}', [$accountCtrl, 'show'])->name('accounts.show');
                Route::post('/accounts', [$accountCtrl, 'store'])->name('accounts.store');
                Route::post('/accounts/{account}/provision', [$accountCtrl, 'provision'])->name('accounts.provision');
                Route::post('/accounts/{account}/suspend', [$accountCtrl, 'suspend'])->name('accounts.suspend');
                Route::post('/accounts/{account}/unsuspend', [$accountCtrl, 'unsuspend'])->name('accounts.unsuspend');
                Route::delete('/accounts/{account}/terminate', [$accountCtrl, 'terminate'])->name('accounts.terminate');
                Route::post('/accounts/{account}/sync', [$accountCtrl, 'sync'])->name('accounts.sync');
                Route::post('/accounts/{account}/ssl', [$accountCtrl, 'ssl'])->name('accounts.ssl');

                // SSL avancado
                Route::get('/accounts/{account}/ssl-status', [$accountCtrl, 'sslStatus'])->name('accounts.ssl-status');
                Route::post('/accounts/{account}/force-https', [$accountCtrl, 'forceHttps'])->name('accounts.force-https');
                Route::post('/accounts/{account}/renew-ssl', [$accountCtrl, 'renewSsl'])->name('accounts.renew-ssl');
                Route::post('/accounts/{account}/delete-ssl', [$accountCtrl, 'deleteSsl'])->name('accounts.delete-ssl');

                // Subdominios
                Route::get('/accounts/{account}/subdomains', [$accountCtrl, 'subdomains'])->name('accounts.subdomains');
                Route::post('/accounts/{account}/subdomains', [$accountCtrl, 'storeSubdomain'])->name('accounts.subdomains.store');
                Route::delete('/accounts/{account}/subdomains', [$accountCtrl, 'destroySubdomain'])->name('accounts.subdomains.destroy');

                // Bancos de dados
                Route::get('/accounts/{account}/databases', [$accountCtrl, 'databases'])->name('accounts.databases');
                Route::post('/accounts/{account}/databases/sync', [$accountCtrl, 'syncDatabases'])->name('accounts.databases.sync');
                Route::get('/accounts/{account}/phpmyadmin', [$accountCtrl, 'phpMyAdmin'])->name('accounts.phpmyadmin');
                Route::delete('/accounts/{account}/databases', [$accountCtrl, 'destroyDatabase'])->name('accounts.databases.destroy');

                // FTP
                Route::get('/accounts/{account}/ftps', [$accountCtrl, 'ftps'])->name('accounts.ftps');
                Route::post('/accounts/{account}/ftps', [$accountCtrl, 'storeFtp'])->name('accounts.ftps.store');
                Route::delete('/accounts/{account}/ftps', [$accountCtrl, 'destroyFtp'])->name('accounts.ftps.destroy');
                Route::post('/accounts/{account}/ftps/password', [$accountCtrl, 'changeFtpPassword'])->name('accounts.ftps.password');

                // PHP
                Route::get('/accounts/{account}/php-version', [$accountCtrl, 'phpVersion'])->name('accounts.php-version');
                Route::post('/accounts/{account}/php-version', [$accountCtrl, 'setPhpVersion'])->name('accounts.php-version.update');
                Route::get('/accounts/{account}/php-config', [$accountCtrl, 'phpConfig'])->name('accounts.php-config');
                Route::post('/accounts/{account}/php/restart', [$accountCtrl, 'restartPhp'])->name('accounts.php.restart');
                Route::get('/accounts/{account}/php/logs', [$accountCtrl, 'phpLogs'])->name('accounts.php.logs');
                Route::get('/accounts/{account}/php/extensions', [$accountCtrl, 'phpExtensions'])->name('accounts.php.extensions');

                // Backups
                Route::get('/accounts/{account}/backups', [$accountCtrl, 'backups'])->name('accounts.backups');
                Route::post('/accounts/{account}/backups', [$accountCtrl, 'storeBackup'])->name('accounts.backups.store');
                Route::delete('/accounts/{account}/backups', [$accountCtrl, 'destroyBackup'])->name('accounts.backups.destroy');
                Route::get('/accounts/{account}/backups/download', [$accountCtrl, 'downloadBackup'])->name('accounts.backups.download');

                // Logs
                Route::get('/accounts/{account}/access-log', [$accountCtrl, 'accessLog'])->name('accounts.access-log');
                Route::get('/accounts/{account}/error-log', [$accountCtrl, 'errorLog'])->name('accounts.error-log');

                // File Manager
                Route::get('/accounts/{account}/files', [$accountCtrl, 'files'])->name('accounts.files');
                Route::post('/accounts/{account}/files/create', [$accountCtrl, 'createFile'])->name('accounts.files.create');
                Route::post('/accounts/{account}/files/directory', [$accountCtrl, 'createDirectory'])->name('accounts.files.directory');
                Route::delete('/accounts/{account}/files', [$accountCtrl, 'destroyFile'])->name('accounts.files.destroy');
                Route::get('/accounts/{account}/files/content', [$accountCtrl, 'getFile'])->name('accounts.files.content');
                Route::post('/accounts/{account}/files/content', [$accountCtrl, 'saveFile'])->name('accounts.files.content.save');

                // Crons
                Route::get('/accounts/{account}/crons', [$accountCtrl, 'crons'])->name('accounts.crons');
                Route::put('/accounts/{account}/crons', [$accountCtrl, 'updateCron'])->name('accounts.crons.update');
                Route::delete('/accounts/{account}/crons', [$accountCtrl, 'destroyCron'])->name('accounts.crons.destroy');

                // Redirecionamentos
                Route::get('/accounts/{account}/redirects', [$accountCtrl, 'redirects'])->name('accounts.redirects');
                Route::delete('/accounts/{account}/redirects', [$accountCtrl, 'destroyRedirect'])->name('accounts.redirects.destroy');

                // Cache
                Route::post('/accounts/{account}/clear-cache', [$accountCtrl, 'clearCache'])->name('accounts.clear-cache');
            });

        // Rotas internas do addon (logs, configuracoes)
        Route::middleware(['web', 'auth', '2fa', 'tenant.addon:aapanel'])
            ->prefix('painel/addons/aapanel')
            ->name('tenant.addons.aapanel.')
            ->group(function () {
                $ctrl = \App\Modules\AaPanel\Http\Controllers\Tenant\AaPanelTenantController::class;
                Route::get('/logs', [$ctrl, 'logs'])->name('logs');
                Route::get('/configuracoes', [$ctrl, 'settings'])->name('settings');
            });

        Route::middleware(['web', 'auth', '2fa', 'tenant.addon:aapanel'])
            ->prefix('painel/aapanel')
            ->name('painel.aapanel.')
            ->group(function () {
                $dashboard = \App\Modules\AaPanel\Http\Controllers\AaPanelDashboardController::class;
                $serverCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelServerController::class;
                $planCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelPlanController::class;
                $accountCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelAccountController::class;
                $siteCtrl = \App\Modules\AaPanel\Http\Controllers\AaPanelSiteController::class;

                Route::get('/', [$dashboard, 'index'])->name('dashboard');

                Route::get('/servers', [$serverCtrl, 'index'])->name('servers.index');
                Route::post('/servers', [$serverCtrl, 'store'])->name('servers.store');
                Route::post('/servers/sync-all', [$serverCtrl, 'syncAll'])->name('servers.sync-all');
                Route::put('/servers/{server}', [$serverCtrl, 'update'])->name('servers.update');
                Route::delete('/servers/{server}', [$serverCtrl, 'destroy'])->name('servers.destroy');
                Route::post('/servers/{server}/test', [$serverCtrl, 'test'])->name('servers.test');
                Route::post('/servers/{server}/metrics', [$serverCtrl, 'metrics'])->name('servers.metrics');
                Route::post('/servers/test-connection', [$serverCtrl, 'testConnection'])->name('servers.test-connection');

                Route::get('/servers/{server}/sites', [$siteCtrl, 'index'])->name('servers.sites.index');
                Route::post('/servers/{server}/sites/{siteId}/start', [$siteCtrl, 'start'])->name('servers.sites.start');
                Route::post('/servers/{server}/sites/{siteId}/stop', [$siteCtrl, 'stop'])->name('servers.sites.stop');
                Route::delete('/servers/{server}/sites/{siteId}', [$siteCtrl, 'destroy'])->name('servers.sites.destroy');
                Route::post('/servers/{server}/sites/sync', [$siteCtrl, 'sync'])->name('servers.sites.sync');
                Route::post('/servers/{server}/sites/{siteId}/import', [$siteCtrl, 'import'])->name('servers.sites.import');
                Route::get('/servers/{server}/php-versions', [$siteCtrl, 'phpVersions'])->name('servers.php-versions');

                Route::get('/plans', [$planCtrl, 'index'])->name('plans.index');
                Route::post('/plans', [$planCtrl, 'store'])->name('plans.store');
                Route::put('/plans/{plan}', [$planCtrl, 'update'])->name('plans.update');
                Route::delete('/plans/{plan}', [$planCtrl, 'destroy'])->name('plans.destroy');

                Route::get('/accounts', [$accountCtrl, 'index'])->name('accounts.index');
                Route::get('/accounts/{account}', [$accountCtrl, 'show'])->name('accounts.show');
                Route::post('/accounts', [$accountCtrl, 'store'])->name('accounts.store');
                Route::post('/accounts/{account}/provision', [$accountCtrl, 'provision'])->name('accounts.provision');
                Route::post('/accounts/{account}/suspend', [$accountCtrl, 'suspend'])->name('accounts.suspend');
                Route::post('/accounts/{account}/unsuspend', [$accountCtrl, 'unsuspend'])->name('accounts.unsuspend');
                Route::delete('/accounts/{account}/terminate', [$accountCtrl, 'terminate'])->name('accounts.terminate');
                Route::post('/accounts/{account}/sync', [$accountCtrl, 'sync'])->name('accounts.sync');
                Route::post('/accounts/{account}/ssl', [$accountCtrl, 'ssl'])->name('accounts.ssl');
                Route::get('/accounts/{account}/ssl-status', [$accountCtrl, 'sslStatus'])->name('accounts.ssl-status');
                Route::post('/accounts/{account}/force-https', [$accountCtrl, 'forceHttps'])->name('accounts.force-https');
                Route::post('/accounts/{account}/renew-ssl', [$accountCtrl, 'renewSsl'])->name('accounts.renew-ssl');
                Route::post('/accounts/{account}/delete-ssl', [$accountCtrl, 'deleteSsl'])->name('accounts.delete-ssl');
                Route::get('/accounts/{account}/subdomains', [$accountCtrl, 'subdomains'])->name('accounts.subdomains');
                Route::post('/accounts/{account}/subdomains', [$accountCtrl, 'storeSubdomain'])->name('accounts.subdomains.store');
                Route::delete('/accounts/{account}/subdomains', [$accountCtrl, 'destroySubdomain'])->name('accounts.subdomains.destroy');
                Route::get('/accounts/{account}/databases', [$accountCtrl, 'databases'])->name('accounts.databases');
                Route::post('/accounts/{account}/databases/sync', [$accountCtrl, 'syncDatabases'])->name('accounts.databases.sync');
                Route::get('/accounts/{account}/phpmyadmin', [$accountCtrl, 'phpMyAdmin'])->name('accounts.phpmyadmin');
                Route::delete('/accounts/{account}/databases', [$accountCtrl, 'destroyDatabase'])->name('accounts.databases.destroy');
                Route::get('/accounts/{account}/ftps', [$accountCtrl, 'ftps'])->name('accounts.ftps');
                Route::post('/accounts/{account}/ftps', [$accountCtrl, 'storeFtp'])->name('accounts.ftps.store');
                Route::delete('/accounts/{account}/ftps', [$accountCtrl, 'destroyFtp'])->name('accounts.ftps.destroy');
                Route::post('/accounts/{account}/ftps/password', [$accountCtrl, 'changeFtpPassword'])->name('accounts.ftps.password');
                Route::get('/accounts/{account}/php-version', [$accountCtrl, 'phpVersion'])->name('accounts.php-version');
                Route::post('/accounts/{account}/php-version', [$accountCtrl, 'setPhpVersion'])->name('accounts.php-version.update');
                Route::get('/accounts/{account}/php-config', [$accountCtrl, 'phpConfig'])->name('accounts.php-config');
                Route::post('/accounts/{account}/php/restart', [$accountCtrl, 'restartPhp'])->name('accounts.php.restart');
                Route::get('/accounts/{account}/php/logs', [$accountCtrl, 'phpLogs'])->name('accounts.php.logs');
                Route::get('/accounts/{account}/php/extensions', [$accountCtrl, 'phpExtensions'])->name('accounts.php.extensions');
                Route::get('/accounts/{account}/backups', [$accountCtrl, 'backups'])->name('accounts.backups');
                Route::post('/accounts/{account}/backups', [$accountCtrl, 'storeBackup'])->name('accounts.backups.store');
                Route::delete('/accounts/{account}/backups', [$accountCtrl, 'destroyBackup'])->name('accounts.backups.destroy');
                Route::get('/accounts/{account}/backups/download', [$accountCtrl, 'downloadBackup'])->name('accounts.backups.download');
                Route::get('/accounts/{account}/access-log', [$accountCtrl, 'accessLog'])->name('accounts.access-log');
                Route::get('/accounts/{account}/error-log', [$accountCtrl, 'errorLog'])->name('accounts.error-log');
                Route::get('/accounts/{account}/files', [$accountCtrl, 'files'])->name('accounts.files');
                Route::post('/accounts/{account}/files/create', [$accountCtrl, 'createFile'])->name('accounts.files.create');
                Route::post('/accounts/{account}/files/directory', [$accountCtrl, 'createDirectory'])->name('accounts.files.directory');
                Route::delete('/accounts/{account}/files', [$accountCtrl, 'destroyFile'])->name('accounts.files.destroy');
                Route::get('/accounts/{account}/files/content', [$accountCtrl, 'getFile'])->name('accounts.files.content');
                Route::post('/accounts/{account}/files/content', [$accountCtrl, 'saveFile'])->name('accounts.files.content.save');
                Route::get('/accounts/{account}/crons', [$accountCtrl, 'crons'])->name('accounts.crons');
                Route::put('/accounts/{account}/crons', [$accountCtrl, 'updateCron'])->name('accounts.crons.update');
                Route::delete('/accounts/{account}/crons', [$accountCtrl, 'destroyCron'])->name('accounts.crons.destroy');
                Route::get('/accounts/{account}/redirects', [$accountCtrl, 'redirects'])->name('accounts.redirects');
                Route::delete('/accounts/{account}/redirects', [$accountCtrl, 'destroyRedirect'])->name('accounts.redirects.destroy');
                Route::post('/accounts/{account}/clear-cache', [$accountCtrl, 'clearCache'])->name('accounts.clear-cache');
            });
    }
}
