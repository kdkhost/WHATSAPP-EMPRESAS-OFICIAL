<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Formatadores PT-BR. As mesmas mascaras sao aplicadas no front (data-mask=*).
 */
class Format
{
    public static function money(float|int|string|null $v): string
    {
        if ($v === null) return 'R$ 0,00';

        return 'R$ '.number_format(self::decimal($v), 2, ',', '.');
    }

    public static function decimal(float|int|string|null $v): float
    {
        if ($v === null || $v === '') return 0.0;
        if (is_int($v) || is_float($v)) return (float) $v;

        $value = trim((string) $v);
        if ($value === '') return 0.0;

        $value = preg_replace('/[^\d,.\-]/', '', $value) ?? '';
        if ($value === '' || $value === '-' || $value === ',' || $value === '.') return 0.0;

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($lastDot !== false) {
            $parts = explode('.', $value);
            $fraction = end($parts);
            $hasMultipleDots = substr_count($value, '.') > 1;
            $looksLikeThousandOnly = ! $hasMultipleDots
                && strlen((string) $fraction) === 3
                && strlen($parts[0]) <= 3;

            if ($hasMultipleDots || $looksLikeThousandOnly) {
                $value = str_replace('.', '', $value);
            }
        }

        $number = (float) $value;

        return $negative ? -$number : $number;
    }

    public static function cpf(?string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($cpf) !== 11) return (string) $cpf;
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    public static function cnpj(?string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', (string) $cnpj);
        if (strlen($cnpj) !== 14) return (string) $cnpj;
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }

    public static function document(?string $doc): string
    {
        if (empty($doc)) return '-';
        $d = preg_replace('/\D/', '', (string) $doc);
        if (strlen($d) === 11) {
            return self::cpf($d);
        }
        if (strlen($d) === 14) {
            return self::cnpj($d);
        }
        return (string) $doc;
    }

    public static function cep(?string $cep): string
    {
        $cep = preg_replace('/\D/', '', (string) $cep);
        if (strlen($cep) !== 8) return (string) $cep;
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }

    public static function phone(?string $phone, bool $withCountryCode = false): string
    {
        if (empty($phone)) return '-';
        $p = preg_replace('/\D/', '', (string) $phone);
        if (empty($p)) return '-';

        // Remove zero inicial se houver (ex: 021991556383)
        if (str_starts_with($p, '0') && (strlen($p) === 11 || strlen($p) === 12)) {
            $p = substr($p, 1);
        }

        // Se tem 13 dígitos começando com 55 (celular BR com DDI: 5521991556383)
        if (strlen($p) === 13 && str_starts_with($p, '55')) {
            $local = substr($p, 2);
            $formatted = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $local);
            return $withCountryCode ? '+55 ' . $formatted : $formatted;
        }

        // Se tem 12 dígitos começando com 55 (fixo BR com DDI: 552133445566)
        if (strlen($p) === 12 && str_starts_with($p, '55')) {
            $local = substr($p, 2);
            $formatted = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $local);
            return $withCountryCode ? '+55 ' . $formatted : $formatted;
        }

        return match (strlen($p)) {
            11 => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $p),
            10 => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $p),
            9  => preg_replace('/(\d{5})(\d{4})/', '$1-$2', $p),
            8  => preg_replace('/(\d{4})(\d{4})/', '$1-$2', $p),
            default => (string) $phone,
        };
    }

    public static function parseDate(string|Carbon|\DateTimeInterface|null $v): ?Carbon
    {
        if (! $v) return null;
        if ($v instanceof Carbon) return $v;
        if ($v instanceof \DateTimeInterface) return Carbon::instance($v);

        $str = trim((string) $v);
        if ($str === '') return null;

        // Trata formato brasileiro dd/mm/yyyy com ou sem hora
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $str, $m)) {
            $hour = $m[4] ?? 0;
            $minute = $m[5] ?? 0;
            $second = $m[6] ?? 0;
            return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1], (int) $hour, (int) $minute, (int) $second, 'America/Sao_Paulo');
        }

        try {
            return Carbon::parse($str)->setTimezone('America/Sao_Paulo');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function date(string|Carbon|\DateTimeInterface|null $v): string
    {
        if (! $v) return '';
        $c = self::parseDate($v);
        return $c ? $c->format('d/m/Y') : (string) $v;
    }

    public static function datetime(string|Carbon|\DateTimeInterface|null $v): string
    {
        if (! $v) return '';
        $c = self::parseDate($v);
        return $c ? $c->format('d/m/Y H:i') : (string) $v;
    }

    public static function time(string|Carbon|\DateTimeInterface|null $v): string
    {
        if (! $v) return '';
        $c = self::parseDate($v);
        return $c ? $c->format('H:i') : '';
    }

    public static function onlyDigits(?string $s): string
    {
        return preg_replace('/\D/', '', (string) $s);
    }

    /** Numero internacional para WhatsApp Meta API (E.164 sem +). Ex.: 5511987654321 */
    public static function whatsappNumber(string $phone): string
    {
        $p = self::onlyDigits($phone);
        if (str_starts_with($p, '0')) $p = ltrim($p, '0');
        if (strlen($p) <= 11 && ! str_starts_with($p, '55')) {
            $p = '55'.$p;
        }
        return $p;
    }
}
