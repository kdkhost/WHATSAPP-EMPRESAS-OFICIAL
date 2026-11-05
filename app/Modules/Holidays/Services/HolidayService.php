<?php

namespace App\Modules\Holidays\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Servico de feriados brasileiros (com calculo de moveis: Pascoa, Carnaval, Corpus Christi).
 *
 * Logica de cobranca:
 *  - Nao cobra/suspende em sabados, domingos e feriados nacionais
 *  - Adia para o proximo dia util
 *  - Pro-rata calculado em dias corridos (mas respeitando dias uteis para vencimento)
 */
class HolidayService
{
    public function isNonBusinessDay(Carbon $date): bool
    {
        if (in_array($date->dayOfWeek, [0, 6], true)) return true;
        return $this->isHoliday($date);
    }

    public function isHoliday(Carbon $date): bool
    {
        $holidays = $this->getHolidaysForYear($date->year);
        $iso = $date->format('Y-m-d');
        return in_array($iso, $holidays, true);
    }

    /** Retorna proximo dia util (pulando fim de semana e feriados). */
    public function nextBusinessDay(Carbon $date, bool $ignore = false): Carbon
    {
        if ($ignore) return $date->copy();
        
        $next = $date->copy();
        while ($this->isNonBusinessDay($next)) {
            $next->addDay();
        }
        return $next;
    }

    /** Retorna dia util anterior. */
    public function previousBusinessDay(Carbon $date, bool $ignore = false): Carbon
    {
        if ($ignore) return $date->copy();
        
        $prev = $date->copy();
        while ($this->isNonBusinessDay($prev)) {
            $prev->subDay();
        }
        return $prev;
    }

    /** Lista feriados (cache 1 dia). */
    public function getHolidaysForYear(int $year): array
    {
        $tid = function_exists('tenant') ? tenant('id') : null;
        $tenantKey = $tid ?? 'global';
        return Cache::remember("holidays.{$tenantKey}.{$year}", 86400, function () use ($year, $tid) {
            try {
                $q = DB::table('holidays')
                    ->where('active', true)
                    ->where('block_billing', true)
                    ->whereYear('date', $year);
                
                if ($tid) {
                    $q->where(function($w) use ($tid) {
                        $w->whereNull('tenant_id')->orWhere('tenant_id', $tid);
                    });
                } else {
                    $q->whereNull('tenant_id');
                }

                $custom = $q->pluck('date')
                    ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                    ->all();
            } catch (\Throwable) {
                $custom = [];
            }

            $computed = $this->computeMoveableHolidays($year);
            return array_unique(array_merge($custom, $computed));
        });
    }

    /** Calcula feriados moveis (Pascoa, Carnaval, Sexta-Santa, Corpus Christi). */
    public function computeMoveableHolidays(int $year): array
    {
        // Algoritmo de Gauss para Pascoa
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

        $easter = Carbon::create($year, $month, $day);

        return [
            $easter->copy()->subDays(48)->format('Y-m-d'), // Carnaval (segunda)
            $easter->copy()->subDays(47)->format('Y-m-d'), // Carnaval (terca)
            $easter->copy()->subDays(2)->format('Y-m-d'),  // Sexta-Santa
            $easter->format('Y-m-d'),                       // Pascoa
            $easter->copy()->addDays(60)->format('Y-m-d'), // Corpus Christi
        ];
    }

    /** Eventos para FullCalendar 6. */
    public function calendarEvents(?int $year = null, ?int $tenantId = null): array
    {
        $year ??= now()->year;

        $events = [];

        // Banco
        try {
            $q = DB::table('holidays')->where('active', true)
                ->whereYear('date', $year);

            if ($tenantId !== null) {
                $q->where(function ($w) use ($tenantId) {
                    $w->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                });
            } else {
                $q->whereNull('tenant_id');
            }

            $rows = $q->get();
            foreach ($rows as $h) {
                $isGlobal = is_null($h->tenant_id);
                $color = $isGlobal ? '#6f42c1' : '#0dcaf0';
                $events[] = [
                    'id'              => 'holiday-' . $h->id,
                    'title'           => 'Feriado: ' . $h->name . ($h->uf ? ' (' . strtoupper($h->uf) . ')' : ''),
                    'start'           => $h->date,
                    'allDay'          => true,
                    'color'           => $color,
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => $isGlobal ? '#ffffff' : '#000000',
                    'classNames'      => ['holiday-event', $isGlobal ? 'holiday-global' : 'holiday-local'],
                    'extendedProps'   => [
                        'kind'           => 'holiday',
                        'holiday_id'     => $h->id,
                        'name'           => $h->name,
                        'type'           => $h->type,
                        'uf'             => $h->uf,
                        'is_global'      => $isGlobal,
                        'block_billing'  => (bool) $h->block_billing,
                    ],
                ];
            }
        } catch (\Throwable) {}

        // Moveis (sempre nacionais/globais)
        $names = [
            0 => 'Carnaval (segunda)', 1 => 'Carnaval (terça)',
            2 => 'Sexta-Santa', 3 => 'Páscoa', 4 => 'Corpus Christi',
        ];
        foreach ($this->computeMoveableHolidays($year) as $i => $date) {
            $events[] = [
                'id'              => "moveable-{$year}-{$i}",
                'title'           => 'Feriado: ' . $names[$i],
                'start'           => $date,
                'allDay'          => true,
                'color'           => '#6f42c1',
                'backgroundColor' => '#6f42c1',
                'borderColor'     => '#6f42c1',
                'textColor'       => '#ffffff',
                'classNames'      => ['holiday-event', 'moveable-holiday', 'holiday-global'],
                'extendedProps'   => [
                    'kind'          => 'holiday',
                    'holiday_id'    => null,
                    'name'          => $names[$i],
                    'type'          => 'national',
                    'is_global'     => true,
                    'block_billing' => true,
                ],
            ];
        }

        return $events;
    }

    /** Retorna feriados nacionais brasileiros fixos (popular no seeder). */
    public static function nationalHolidays(): array
    {
        return [
            ['date' => '01-01', 'name' => 'Confraternizacao Universal'],
            ['date' => '04-21', 'name' => 'Tiradentes'],
            ['date' => '05-01', 'name' => 'Dia do Trabalho'],
            ['date' => '09-07', 'name' => 'Independencia do Brasil'],
            ['date' => '10-12', 'name' => 'Nossa Senhora Aparecida'],
            ['date' => '11-02', 'name' => 'Finados'],
            ['date' => '11-15', 'name' => 'Proclamacao da Republica'],
            ['date' => '11-20', 'name' => 'Consciencia Negra'],
            ['date' => '12-25', 'name' => 'Natal'],
        ];
    }
}
