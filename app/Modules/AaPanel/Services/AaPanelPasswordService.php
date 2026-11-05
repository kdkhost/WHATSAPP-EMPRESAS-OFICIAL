<?php

namespace App\Modules\AaPanel\Services;

use Illuminate\Support\Str;

class AaPanelPasswordService
{
    public function generate(int $length = 18): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_+';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    public function usernameFromDomain(string $domain): string
    {
        $base = preg_replace('/[^a-z0-9]/i', '', explode('.', $domain)[0] ?? 'site');
        $base = strtolower(substr($base, 0, 10));

        return $base.'_'.strtolower(Str::random(4));
    }

    public function databaseName(string $username): string
    {
        return substr(preg_replace('/[^a-z0-9_]/i', '', $username.'_db'), 0, 32);
    }
}
