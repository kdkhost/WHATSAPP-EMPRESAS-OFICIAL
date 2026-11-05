<?php
/**
 * Hook WHMCS: Proteção de Suspensão em Finais de Semana e Feriados Bancários Nacionais
 *
 * Instruções de Instalação no WHMCS:
 * 1. Copie este arquivo para o diretório de hooks da sua instalação do WHMCS:
 *    /includes/hooks/protect_weekends_holidays.php
 *
 * Funcionamento:
 * - Intercepta tentativas de suspensão automática ou manual durante sábados, domingos e feriados nacionais.
 * - Cancela o comando de suspensão no servidor/módulo (cPanel, DirectAdmin, etc.) garantindo conformidade Febraban.
 * - Registra no log de atividade do WHMCS que a suspensão foi abortada para proteção legal.
 */

if (! defined('WHMCS')) {
    die('Acesso direto não permitido.');
}

add_hook('PreModuleSuspend', 1, function ($vars) {
    $serviceId = (int) ($vars['serviceid'] ?? 0);
    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $dayOfWeek = (int) $now->format('w'); // 0 = Domingo, 6 = Sábado

    if ($dayOfWeek === 0 || $dayOfWeek === 6) {
        $dayName = $dayOfWeek === 0 ? 'domingo' : 'sábado';
        logActivity("[Sistema de Cobrança] Suspensão abortada para o serviço #{$serviceId}: suspensoes sao proibidas em finais de semana ({$dayName}).");
        return ['abort' => true, 'abortcmd' => true];
    }

    $year = (int) $now->format('Y');

    // Feriados nacionais bancários fixos no Brasil
    $holidays = [
        "{$year}-01-01", // Confraternização Universal
        "{$year}-04-21", // Tiradentes
        "{$year}-05-01", // Dia do Trabalho
        "{$year}-09-07", // Independência do Brasil
        "{$year}-10-12", // Nossa Senhora Aparecida
        "{$year}-11-02", // Finados
        "{$year}-11-15", // Proclamação da República
        "{$year}-11-20", // Consciência Negra
        "{$year}-12-25", // Natal
    ];

    // Cálculo dos feriados móveis (Gauss para Páscoa)
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;

    $easter = new DateTime("{$year}-{$month}-{$day}", new DateTimeZone('America/Sao_Paulo'));
    $carnavalSeg = (clone $easter)->modify('-48 days')->format('Y-m-d');
    $carnavalTer = (clone $easter)->modify('-47 days')->format('Y-m-d');
    $sextaSanta = (clone $easter)->modify('-2 days')->format('Y-m-d');
    $corpusChristi = (clone $easter)->modify('+60 days')->format('Y-m-d');

    $holidays[] = $carnavalSeg;
    $holidays[] = $carnavalTer;
    $holidays[] = $sextaSanta;
    $holidays[] = $corpusChristi;

    $todayIso = $now->format('Y-m-d');
    if (in_array($todayIso, $holidays, true)) {
        logActivity("[Sistema de Cobrança] Suspensão abortada para o serviço #{$serviceId}: suspensoes sao proibidas em feriados bancarios nacionais ({$todayIso}).");
        return ['abort' => true, 'abortcmd' => true];
    }

    return [];
});
