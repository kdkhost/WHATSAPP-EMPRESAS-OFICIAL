<?php

namespace App\Modules\HealthCheck\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Services\EvolutionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Endpoint /health para monitoring (Uptime Robot, Pingdom, etc).
 *
 * Retorna JSON com status de cada servico:
 *   - database: conexao + tempo de query
 *   - cache: get/set funcionando
 *   - storage: writable
 *   - evolution: API respondendo
 *   - cron: ultimo heartbeat
 *   - disk: espaco em disco
 */
class HealthController extends Controller
{
    public function __invoke(EvolutionClient $evolution): JsonResponse
    {
        $checks = [
            'database'  => $this->checkDatabase(),
            'cache'     => $this->checkCache(),
            'storage'   => $this->checkStorage(),
            'evolution' => $this->checkEvolution($evolution),
            'cron'      => $this->checkCron(),
            'disk'      => $this->checkDisk(),
        ];

        $allHealthy = collect($checks)->every(fn($c) => $c['ok']);
        $status = $allHealthy ? 200 : 503;

        return response()->json([
            'status'    => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'version'   => '1.4.0',
            'checks'    => $checks,
        ], $status);
    }

    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 2);
            return ['ok' => true, 'response_ms' => $ms];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health_check_' . random_int(1000, 9999);
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            return ['ok' => $value === 'ok', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $writable = is_writable(storage_path());
            return ['ok' => $writable, 'path' => storage_path()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkEvolution(EvolutionClient $client): array
    {
        if (!$client->isConfigured()) {
            return ['ok' => true, 'configured' => false];
        }
        try {
            $status = $client->status();
            return ['ok' => $status['ok'] ?? false, 'configured' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkCron(): array
    {
        $stateFile = storage_path('app/cron-state.json');
        if (!is_file($stateFile)) {
            return ['ok' => false, 'error' => 'Cron nunca executou'];
        }
        $state = json_decode(file_get_contents($stateFile), true) ?: [];
        $lastCheck = (int) ($state['last_check'] ?? 0);
        $secondsAgo = time() - $lastCheck;
        $stale = $secondsAgo > 600; // 10 minutos
        return [
            'ok'           => !$stale,
            'last_run_ago' => $secondsAgo . 's',
        ];
    }

    protected function checkDisk(): array
    {
        try {
            $free  = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $usedPct = $total > 0 ? round((($total - $free) / $total) * 100, 1) : 0;
            return [
                'ok'         => $usedPct < 90,
                'used_pct'   => $usedPct,
                'free_gb'    => round($free / 1073741824, 2),
                'total_gb'   => round($total / 1073741824, 2),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
