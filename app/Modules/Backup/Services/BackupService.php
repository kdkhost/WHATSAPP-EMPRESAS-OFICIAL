<?php

namespace App\Modules\Backup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Backup automatico do banco de dados.
 *
 * Estrategia: streaming para arquivo .sql temporario, depois comprime com gz.
 * Evita memory_limit exhausted ao nao acumular todo o SQL em string.
 *
 * Saida: storage/app/backups/db_YYYY-MM-DD_HHmmss.sql.gz
 * Retencao: 30 dias (configuravel via settings)
 */
class BackupService
{
    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($this->backupDir);
    }

    /** Gera backup completo usando streaming (sem acumular SQL em memoria). */
    public function run(): array
    {
        $basename = 'db_' . now()->format('Y-m-d_His');
        $sqlPath  = $this->backupDir . '/' . $basename . '.sql';
        $gzPath   = $this->backupDir . '/' . $basename . '.sql.gz';

        try {
            // 1. Escreve SQL em arquivo temporario (streaming)
            $this->dumpViaPhpToFile($sqlPath);

            // 2. Comprime para .gz usando streams (sem carregar o arquivo inteiro)
            $this->gzipFile($sqlPath, $gzPath);

            // 3. Remove o .sql temporario
            @unlink($sqlPath);

            $size = filesize($gzPath);
            Log::info("[Backup] Criado: {$basename}.sql.gz ({$size} bytes)");

            $this->cleanOldBackups();

            return [
                'ok'   => true,
                'file' => $basename . '.sql.gz',
                'size' => $size,
                'path' => $gzPath,
            ];
        } catch (\Throwable $e) {
            @unlink($sqlPath);
            @unlink($gzPath);
            Log::error("[Backup] Falha: {$e->getMessage()}");
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Lista backups disponiveis. */
    public function list(): array
    {
        if (! is_dir($this->backupDir)) {
            return [];
        }

        $files = glob($this->backupDir . '/db_*.sql.gz') ?: [];
        rsort($files);

        return array_map(fn ($f) => [
            'name'    => basename($f),
            'size'    => filesize($f),
            'size_mb' => round(filesize($f) / 1048576, 2),
            'date'    => date('Y-m-d H:i:s', filemtime($f)),
        ], $files);
    }

    /** Remove backup especifico. */
    public function delete(string $filename): bool
    {
        if (! preg_match('/^db_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $filename)) {
            return false;
        }
        $path = $this->backupDir . '/' . $filename;
        if (! is_file($path)) {
            return false;
        }
        return @unlink($path);
    }

    /** Remove backups com mais de N dias (default 30). */
    protected function cleanOldBackups(int $keepDays = 30): int
    {
        $count  = 0;
        $cutoff = time() - ($keepDays * 86400);

        foreach (glob($this->backupDir . '/db_*.sql.gz') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Escreve o dump SQL diretamente em arquivo usando fwrite (streaming).
     * Nunca acumula todo o conteudo em memoria.
     */
    protected function dumpViaPhpToFile(string $filePath): void
    {
        $fh = fopen($filePath, 'wb');
        if (! $fh) {
            throw new \RuntimeException("Nao foi possivel criar o arquivo de backup: {$filePath}");
        }

        try {
            fwrite($fh, "-- Backup gerado em " . now()->toIso8601String() . "\n");
            fwrite($fh, "-- Sistema: " . config('app.name') . "\n");
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($fh, "SET NAMES utf8mb4;\n\n");

            $dbName = config('database.connections.mysql.database');
            $tables = DB::select('SHOW TABLES');
            $dbKey  = 'Tables_in_' . $dbName;

            foreach ($tables as $t) {
                $name = $t->{$dbKey} ?? array_values((array) $t)[0];

                // CREATE TABLE
                $createRow = DB::select("SHOW CREATE TABLE `{$name}`")[0] ?? null;
                if ($createRow) {
                    fwrite($fh, "DROP TABLE IF EXISTS `{$name}`;\n");
                    fwrite($fh, ($createRow->{'Create Table'} ?? '') . ";\n\n");
                }

                // INSERTs em chunks de 100 linhas (menor footprint)
                DB::table($name)->orderByRaw('1')->chunk(100, function ($rows) use ($fh, $name) {
                    if ($rows->isEmpty()) {
                        return;
                    }

                    $cols = '`' . implode('`,`', array_keys((array) $rows->first())) . '`';

                    foreach ($rows as $row) {
                        $vals = implode(',', array_map(function ($v) {
                            if (is_null($v)) {
                                return 'NULL';
                            }
                            if (is_int($v) || is_float($v)) {
                                return $v;
                            }
                            return "'" . addslashes((string) $v) . "'";
                        }, (array) $row));

                        fwrite($fh, "INSERT INTO `{$name}` ({$cols}) VALUES ({$vals});\n");
                    }

                    fwrite($fh, "\n");
                });
            }

            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($fh);
        }
    }

    /**
     * Comprime $source para $dest usando streams gz.
     * Processa em blocos de 512 KB — sem carregar o arquivo inteiro.
     */
    protected function gzipFile(string $source, string $dest): void
    {
        $in = fopen($source, 'rb');
        if (! $in) {
            throw new \RuntimeException("Nao foi possivel ler o arquivo SQL: {$source}");
        }

        $out = gzopen($dest, 'wb9');
        if (! $out) {
            fclose($in);
            throw new \RuntimeException("Nao foi possivel criar o arquivo gz: {$dest}");
        }

        try {
            while (! feof($in)) {
                gzwrite($out, fread($in, 524288)); // 512 KB por vez
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }
}
