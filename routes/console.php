<?php

use App\Services\CronManager;
use App\Services\RouteAuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Sistema de Cobranca WhatsApp - Laravel 13 + PHP 8.4');
})->purpose('Mensagem de inicializacao');

// Agendador principal do Laravel (acionado pelo crontab: * * * * * php artisan schedule:run)
Schedule::command('cron:run')->everyMinute()->withoutOverlapping(10);

Artisan::command('cron:run {--force : Forca a execucao de todos os jobs registrados ignorando o intervalo}', function (CronManager $cron) {
    $force = (bool) $this->option('force');
    $this->info('Iniciando ciclo do cron principal' . ($force ? ' (forcado)...' : '...'));
    $report = $cron->runMainCycle($force);
    if (($report['skipped'] ?? null) === 'em_execucao') {
        $this->warn('Ciclo do cron ignorado: outro processo já está em execucao.');
        return 0;
    }
    $executed = count($report['executed'] ?? []);
    $ok = $report['ok'] ?? 0;
    $errors = $report['errors'] ?? 0;
    $this->info("Ciclo concluido: {$executed} jobs executados ({$ok} sucesso, {$errors} falhas).");
    $this->line(json_encode($report, JSON_UNESCAPED_UNICODE));
    return 0;
})->purpose('Executa o ciclo principal do cron');

Artisan::command('cron:run-internal', function (CronManager $cron) {
    $report = $cron->runFromExternal();
    $this->line(json_encode($report, JSON_UNESCAPED_UNICODE));
})->purpose('Alias para cron:run (executa jobs do cron interno sem depender de trafego HTTP)');

Artisan::command('app:audit-routes {--json : Imprime o relatorio completo em JSON} {--fail-on-error : Retorna codigo 1 quando houver rotas ou menus quebrados}', function (RouteAuditService $auditor) {
    $report = $auditor->run();

    if ($this->option('json')) {
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->option('fail-on-error') && $report['summary']['critical'] > 0 ? 1 : 0;
    }

    $this->info('Auditoria de rotas concluida.');
    $this->line('Rotas verificadas: '.$report['summary']['routes']);
    $this->line('Achados criticos: '.$report['summary']['critical']);
    $this->line('Relatorio: '.$report['report_path']);

    foreach ([
        'controllers_missing' => 'Controllers ausentes',
        'methods_missing' => 'Metodos ausentes',
        'middleware_missing' => 'Middlewares ausentes',
        'views_missing' => 'Views ausentes',
        'named_routes_missing' => 'Rotas nomeadas ausentes',
        'addon_menus_invalid' => 'Menus de addons invalidos',
        'sidebar_links_invalid' => 'Links da sidebar invalidos',
    ] as $key => $label) {
        $items = $report[$key] ?? [];
        if ($items === []) {
            continue;
        }

        $this->warn($label.': '.count($items));
        $this->table(['item', 'erro'], collect($items)->take(20)->map(fn ($item) => [
            $item['route'] ?? $item['uri'] ?? $item['url'] ?? $item['controller'] ?? '-',
            $item['error'] ?? $item['middleware'] ?? $item['method'] ?? 'verificar',
        ])->all());
    }

    return $this->option('fail-on-error') && $report['summary']['critical'] > 0 ? 1 : 0;
})->purpose('Audita rotas, controllers, middlewares, sidebar e menus de addons');

Artisan::command('whatsapp:health-check', function () {
    $this->info('Iniciando verificacao da WhatsApp Cloud API Oficial da Meta...');
    $this->newLine();

    try {
        $client = app(\App\Modules\WhatsApp\Services\WhatsAppMetaClient::class);
        $status = $client->status();

        $connected = !empty($status['connected']);
        $this->line(sprintf(
            '[%s] Status: %s | Numero: %s | Qualidade: %s | Nome: %s',
            $connected ? 'OK' : 'FALHA',
            $status['status_label'] ?? '-',
            $status['number'] ?? '-',
            $status['quality'] ?? '-',
            $status['verified_name'] ?? '-'
        ));

        $this->newLine();
        $this->info('Checagem finalizada.');
        return $connected ? 0 : 1;
    } catch (\Throwable $e) {
        $this->error('Erro ao executar checagem Meta API: '.$e->getMessage());
        return 1;
    }
})->purpose('Verifica conectividade da WhatsApp Cloud API Oficial da Meta');

Artisan::command('system:safe-clear', function () {
    $this->info('Limpando caches do sistema...');
    Artisan::call('optimize:clear');
    Artisan::call('queue:restart');
    $this->info('Caches limpos com sucesso.');
})->purpose('Limpeza segura do sistema');

Artisan::command('whatsapp:full-diagnose', function () {
    $this->info('=============================================');
    $this->info('DIAGNOSTICO COMPLETO WHATSAPP CLOUD API (META)');
    $this->info('=============================================');

    try {
        $client = app(\App\Modules\WhatsApp\Services\WhatsAppMetaClient::class);
        $diagnosis = $client->diagnoseConnection();

        $this->newLine();
        $this->line('Versao Graph API: '.($diagnosis['graph_version'] ?? 'v21.0'));
        $this->line('Phone Number ID: '.($diagnosis['phone_number_id'] ?? 'pendente'));
        $this->line('WABA ID: '.($diagnosis['waba_id'] ?? 'pendente'));
        $this->line('Token Permanente: '.($diagnosis['token'] ?? 'ausente'));
        $this->newLine();

        $conn = $diagnosis['connection'] ?? [];
        $this->line('Resultado Conexao Meta: ' . (!empty($conn['ok']) ? 'OK / CONECTADO' : 'PENDENTE / FALHA'));
        if (!empty($conn['verified_name'])) {
            $this->line('Nome Verificado: '.$conn['verified_name']);
        }
        if (!empty($conn['display_phone_number'])) {
            $this->line('Numero Exibicao: '.$conn['display_phone_number']);
        }
        if (!empty($conn['message'])) {
            $this->line('Mensagem: '.$conn['message']);
        }

        $this->newLine();
        $this->info('Diagnostico concluido.');
    } catch (\Throwable $e) {
        $this->error('Falha critica: '.$e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Diagnostico completo da WhatsApp Cloud API Oficial da Meta');
