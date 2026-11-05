<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Gerenciador de cron interno.
 *
 * Modulos registram jobs via:
 *   CronManager::register('billing.send_due', fn() => app(Sender::class)->run(), 30);
 *
 * Persiste estado em storage/app/cron-state.json e historico em tabela cron_jobs.
 */
class CronManager
{
    /** @var array<string, array{name:string, callback:callable, interval:int, description:string}> */
    protected static array $jobs = [];

    protected string $stateFile;

    protected string $lockFile;

    public function __construct()
    {
        $this->stateFile = storage_path('app/cron-state.json');
        $this->lockFile  = storage_path('app/cron.lock');
    }

    public static function register(string $name, callable $callback, int $intervalMinutes = 5, string $description = ''): void
    {
        static::$jobs[$name] = [
            'name'        => $name,
            'callback'    => $callback,
            'interval'    => max(1, $intervalMinutes),
            'description' => $description,
        ];
    }

    public static function jobs(): array
    {
        return static::$jobs;
    }

    /** Heartbeat - chamado apos a resposta. Throttle global por config. */
    public function heartbeat(): array
    {
        $heartbeat = (int) config('modules.cron.heartbeat_seconds', 60);
        $state = $this->loadState();
        $now = time();

        if (($now - (int) ($state['last_check'] ?? 0)) < $heartbeat) {
            return [];
        }

        return $this->runWithLock(function () use (&$state, $now) {
            $state['last_check'] = $now;
            $report = $this->runDueJobs($state);
            $this->saveState($state);
            return $report;
        }) ?? [];
    }

    /** Executado pelo endpoint /cron (cPanel ou monitoramento externo). */
    public function runFromExternal(): array
    {
        return $this->runMainCycle(false);
    }

    /** Ciclo principal do cron: processa todos os jobs devidos (ou todos se $forceAll = true). */
    public function runMainCycle(bool $forceAll = false): array
    {
        return $this->runWithLock(function () use ($forceAll) {
            $state = $this->loadState();
            $state['jobs'] = $state['jobs'] ?? [];
            $report = [
                'executed' => [],
                'skipped'  => [],
                'total'    => count(static::$jobs),
                'ok'       => 0,
                'errors'   => 0,
                'started_at' => now()->toDateTimeString(),
            ];

            foreach (static::$jobs as $name => $job) {
                $result = $this->executeJob($job, $state, force: $forceAll);
                if ($result !== null) {
                    $report['executed'][$name] = $result;
                    if (($result['last_status'] ?? '') === 'ok') {
                        $report['ok']++;
                    } else {
                        $report['errors']++;
                    }
                } else {
                    $report['skipped'][$name] = 'aguardando_intervalo';
                }
            }

            $state['last_check'] = time();
            $this->saveState($state);

            $report['finished_at'] = now()->toDateTimeString();
            return $report;
        }) ?? ['skipped' => 'em_execucao'];
    }

    /** Token seguro para execucao externa do cron. */
    public static function resolvedExternalToken(): string
    {
        $configured = (string) config('modules.cron.external_token', '');
        if ($configured !== '' && $configured !== 'troque-este-token-32-chars') {
            return $configured;
        }

        $appKey = (string) config('app.key', 'cobranca_whatsapp_secret_key');
        return substr(hash_hmac('sha256', 'cron_external_token_seed', $appKey), 0, 32);
    }

    /** Forcar execucao de um job especifico (botao no painel). */
    public function runJob(string $name): array
    {
        if (! isset(static::$jobs[$name])) {
            throw new \InvalidArgumentException("Job nao registrado: {$name}");
        }

        return $this->runWithLock(function () use ($name) {
            $state = $this->loadState();
            $result = $this->executeJob(static::$jobs[$name], $state, force: true);
            $this->saveState($state);
            return $result;
        }) ?? ['skipped' => 'em_execucao'];
    }

    protected function runDueJobs(array &$state): array
    {
        $state['jobs'] = $state['jobs'] ?? [];
        $report = [];
        foreach (static::$jobs as $name => $job) {
            $result = $this->executeJob($job, $state, force: false);
            if ($result !== null) {
                $report[$name] = $result;
            }
        }
        return $report;
    }

    protected function executeJob(array $job, array &$state, bool $force): ?array
    {
        $name = $job['name'];
        $last = (int) ($state['jobs'][$name]['last_run'] ?? 0);
        $intervalSec = $job['interval'] * 60;
        $now = time();

        if (! $force && ($now - $last) < $intervalSec) {
            return null;
        }

        $start = microtime(true);
        $status = 'ok';
        $error = null;
        try {
            ($job['callback'])();
        } catch (\Throwable $e) {
            $status = 'error';
            $error = $e->getMessage();
            Log::error("[Cron::{$name}] {$error}", ['exception' => $e]);
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $entry = [
            'last_run'    => $now,
            'last_status' => $status,
            'last_error'  => $error,
            'duration_ms' => $duration,
        ];
        $state['jobs'][$name] = $entry;

        // Persiste historico (best-effort)
        try {
            DB::table('cron_jobs')->updateOrInsert(
                ['name' => $name],
                [
                    'description'  => $job['description'],
                    'interval_min' => $job['interval'],
                    'last_run_at'  => Carbon::createFromTimestamp($now)->toDateTimeString(),
                    'last_status'  => $status,
                    'last_error'   => $error,
                    'duration_ms'  => $duration,
                    'updated_at'   => now(),
                    'created_at'   => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        } catch (\Throwable $e) {
            // Tabela ainda nao migrada - ignora
        }

        return $entry;
    }

    protected function loadState(): array
    {
        if (! is_file($this->stateFile)) {
            return [];
        }
        $raw = @file_get_contents($this->stateFile);
        return $raw ? (json_decode($raw, true) ?: []) : [];
    }

    protected function saveState(array $state): void
    {
        File::ensureDirectoryExists(dirname($this->stateFile));
        @file_put_contents($this->stateFile, json_encode($state, JSON_UNESCAPED_UNICODE));
    }

    protected function runWithLock(callable $fn): mixed
    {
        File::ensureDirectoryExists(dirname($this->lockFile));

        // Auto-recuperacao de lock estagnado (stale lock > 10 minutos)
        if (is_file($this->lockFile)) {
            $mtime = @filemtime($this->lockFile);
            if ($mtime && (time() - $mtime) > 600) {
                @unlink($this->lockFile);
            }
        }

        $fp = @fopen($this->lockFile, 'c');
        if (! $fp) {
            return null;
        }
        if (! flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null; // outra requisicao esta processando
        }
        try {
            @touch($this->lockFile);
            return $fn();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($this->lockFile);
        }
    }
}
