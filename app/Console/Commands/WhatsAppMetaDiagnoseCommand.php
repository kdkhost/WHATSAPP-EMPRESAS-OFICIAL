<?php

namespace App\Console\Commands;

use App\Modules\WhatsApp\Services\WhatsAppMetaClient;
use Illuminate\Console\Command;

class WhatsAppMetaDiagnoseCommand extends Command
{
    protected $signature = 'whatsapp:meta-diagnose';

    protected $description = 'Diagnostica a conexao com a WhatsApp Cloud API Oficial da Meta (Graph API v21.0).';

    public function handle(WhatsAppMetaClient $client): int
    {
        $this->info('Diagnostico WhatsApp Cloud API Oficial (Meta Graph API v21.0)');
        $this->line('------------------------------------------------------------');

        $diagnosis = $client->diagnoseConnection();

        $this->line('Graph API Versao: '.($diagnosis['graph_version'] ?? 'v21.0'));
        $this->line('Phone Number ID: '.($diagnosis['phone_number_id'] ?? 'pendente'));
        $this->line('WABA ID: '.($diagnosis['waba_id'] ?? 'pendente'));
        $this->line('Access Token Permanente: '.($diagnosis['token'] ?? 'ausente'));
        $this->newLine();

        $conn = $diagnosis['connection'] ?? [];
        $ok = (bool) ($conn['ok'] ?? false);

        $this->table(
            ['Parametro', 'Valor'],
            [
                ['Status da Conexao', $ok ? '<info>CONECTADO / OK</info>' : '<error>DESCONECTADO / PENDENTE</error>'],
                ['Nome Verificado Meta', $conn['verified_name'] ?? 'N/A'],
                ['Numero de Exibicao', $conn['display_phone_number'] ?? 'N/A'],
                ['Classificacao de Qualidade', $conn['quality_rating'] ?? 'N/A'],
                ['Mensagem de Diagnostico', $conn['message'] ?? ($ok ? 'Conexao com a Meta operacional.' : 'Configure as credenciais.')],
            ]
        );

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

