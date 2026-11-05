<?php

namespace App\Console\Commands;

use App\Modules\WhatsApp\Services\EvolutionClient;
use Illuminate\Console\Command;

class WhatsAppEvolutionDiagnoseCommand extends Command
{
    protected $signature = 'whatsapp:evolution-diagnose';

    protected $description = 'Diagnostica a conexao com Evolution API Go sem expor chaves sensiveis.';

    public function handle(EvolutionClient $client): int
    {
        $this->info('Diagnostico Evolution API Go');
        $this->line('----------------------------------------');

        $diagnosis = $client->diagnoseConnection();

        $this->line('Base URL: '.($diagnosis['base_url'] ?: 'nao configurada'));
        $this->line('Instancia: '.($diagnosis['instance'] ?: 'nao configurada'));
        $this->line('Instance Key configurada: '.($diagnosis['has_instance_key'] ? 'sim' : 'nao'));
        $this->line('Global Key configurada: '.($diagnosis['has_global_key'] ? 'sim' : 'nao'));
        $this->line('Configuracao da instancia: '.($diagnosis['instance_configured'] ? 'ok' : 'falha'));
        $this->line('Configuracao admin: '.($diagnosis['admin_configured'] ? 'ok' : 'falha'));
        $this->line('Conflito global de estado: '.(($diagnosis['global_conflict'] ?? false) ? 'sim' : 'nao'));
        $this->line('Confianca do status final: '.($diagnosis['status_confidence'] ?? 'n/a'));
        $this->newLine();

        $rows = [];

        $statusState = mb_strtolower((string) (data_get($diagnosis, 'tests.0.normalized_state', '')));

        foreach ($diagnosis['tests'] as $test) {
            $message = (string) ($test['message'] ?? '');
            if (
                ($test['name'] ?? '') === 'instance_all'
                && mb_strtolower((string) ($test['normalized_state'] ?? '')) === 'close'
                && $statusState === 'open'
            ) {
                $message = 'Estado CLOSE ignorado porque /instance/status confirmou OPEN diretamente.';
            }
            $rows[] = [
                $test['name'] ?? '',
                $test['endpoint'] ?? '',
                $test['key_type'] ?? '',
                ($test['ok'] ?? false) ? 'OK' : 'FALHA',
                $test['http_status'] ?? '-',
                $test['normalized_state'] ?? '-',
                $test['response_time_ms'] ?? '-',
                $message,
            ];
        }

        $this->table(
            ['Teste', 'Endpoint', 'Chave', 'Resultado', 'HTTP', 'Estado', 'ms', 'Mensagem/Observacao'],
            $rows
        );

        $hasFailure = collect($diagnosis['tests'])->contains(fn ($test) => ! ($test['ok'] ?? false));

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }
}
