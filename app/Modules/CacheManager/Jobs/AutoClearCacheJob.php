<?php

namespace App\Modules\CacheManager\Jobs;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Job de limpeza automatica de cache.
 * Roda via CronManager se o registro cache:auto-clear estiver ativo.
 */
class AutoClearCacheJob
{
    public static function schedule(): array
    {
        return [
            'name' => 'Limpeza Automatica de Cache',
            'key' => 'cache:auto-clear',
            'interval_min' => 1440,
            'description' => 'Limpa caches do Laravel automaticamente',
        ];
    }

    public function handle(): void
    {
        // Verificar se esta habilitado
        $enabled = DB::table('cron_jobs')
            ->where('name', 'cache:auto-clear')
            ->value('enabled');

        if ($enabled === null) {
            $enabled = Cache::get('cache_manager.auto_enabled', false);
        } else {
            Cache::forever('cache_manager.auto_enabled', (bool) $enabled);
        }

        if (!$enabled) {
            return;
        }

        $start = microtime(true);
        $commands = ['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'event:clear'];

        $results = [];
        foreach ($commands as $cmd) {
            try {
                Artisan::call($cmd);
                $results[$cmd] = 'ok';
            } catch (\Throwable $e) {
                $results[$cmd] = 'fail: ' . $e->getMessage();
            }
        }

        $duration = round((microtime(true) - $start) * 1000, 2);

        // Logar no historico
        $history = Cache::get('cache_manager.history', []);
        array_unshift($history, [
            'type' => 'auto_clear',
            'at' => now()->toDateTimeString(),
            'user' => 'cron',
            'duration_ms' => $duration,
            'results' => $results,
        ]);
        Cache::put('cache_manager.history', array_slice($history, 0, 20), now()->addDays(30));
        Cache::put('cache_manager.last_clear', now()->toDateTimeString(), now()->addYears(1));

        // Atualizar registro do job
        DB::table('cron_jobs')->where('name', 'cache:auto-clear')->update([
            'last_run_at' => now(),
            'last_status' => 'ok',
            'updated_at' => now(),
        ]);
    }
}
