<?php

namespace App\Modules\Backup\Providers;

use App\Modules\Backup\Services\BackupService;
use App\Services\CronManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupService::class);
    }

    public function boot(): void
    {
        // Cron: backup diario as 3h da manha (configuravel via heartbeat)
        CronManager::register(
            name: 'backup.database',
            callback: fn () => app(BackupService::class)->run(),
            intervalMinutes: 1440, // 24h
            description: 'Backup automatico do banco de dados (.sql.gz). Mantem 30 dias.'
        );

        // Painel admin
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/backups')->name('admin.backups.')->group(function () {
            Route::get('/', function (BackupService $svc) {
                $backups = $svc->list();
                return view('admin.backups.index', compact('backups'));
            })->name('index');

            Route::post('/', function (BackupService $svc) {
                $result = $svc->run();
                return response()->json($result, $result['ok'] ? 200 : 500);
            })->name('create');

            Route::get('/{filename}/download', function (string $filename, BackupService $svc) {
                if (!preg_match('/^db_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $filename)) {
                    abort(404);
                }
                $path = storage_path('app/backups/' . $filename);
                if (!is_file($path)) abort(404);
                return response()->download($path);
            })->name('download');

            Route::delete('/{filename}', function (string $filename, BackupService $svc) {
                $ok = $svc->delete($filename);
                return response()->json(['ok' => $ok]);
            })->name('destroy');
        });
    }
}
