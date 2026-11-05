<?php

namespace App\Services;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Str;

/**
 * Servico de Two-Factor Authentication (2FA) via TOTP (Time-based One-Time Password).
 *
 * Compativel com: Google Authenticator, Authy, Microsoft Authenticator, etc.
 *
 * Implementa RFC 6238 (TOTP) com HMAC-SHA1, 6 digitos, janela de 30 segundos.
 * NAO depende de pacotes externos (pure PHP).
 */
class TwoFactorService
{
    protected int $codeLength = 6;
    protected int $period = 30; // segundos
    protected int $window = 2;  // tolerancia: 2 periodos antes/depois (±60s)

    /** Gera um novo secret (base32, 160 bits). */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        return $this->base32Encode($bytes);
    }

    /** Gera URL para QR code (otpauth://). */
    public function getQrUrl(AuthenticatableUser $user, string $secret): string
    {
        $issuer = urlencode(config('app.name', 'CobrancaWhatsApp'));
        $account = urlencode($user->email);
        return "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits={$this->codeLength}&period={$this->period}";
    }

    /** Verifica um codigo TOTP. */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s/', '', $code);
        if (strlen($code) !== $this->codeLength) return false;

        $timestamp = time();
        $timeSlice = intdiv($timestamp, $this->period);

        // Verifica janela atual + tolerancia
        for ($i = -$this->window; $i <= $this->window; $i++) {
            $expected = $this->generateCode($secret, $timeSlice + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        \Illuminate\Support\Facades\Log::debug('[2FA] TOTP falhou', [
            'timestamp' => $timestamp,
            'timeSlice' => $timeSlice,
            'code' => $code,
            'secret_length' => strlen($secret),
        ]);

        return false;
    }

    /** Gera recovery codes (8 codigos de 10 chars cada). */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::random(5) . '-' . Str::random(5);
        }
        return $codes;
    }

    /** Verifica um recovery code (uso unico). */
    public function verifyRecoveryCode(AuthenticatableUser $user, string $code): bool
    {
        $rawCodes = $user->two_factor_recovery_codes ?? '[]';
        try {
            $rawCodes = decrypt($rawCodes);
        } catch (\Throwable) {
            // Bancos antigos podem ter salvo os codigos sem criptografia.
        }

        $codes = json_decode($rawCodes, true) ?: [];
        $code = strtolower(trim($code));

        $index = array_search($code, array_map('strtolower', $codes), true);
        if ($index === false) return false;

        // Remover codigo usado (uso unico)
        unset($codes[$index]);
        $user->update(['two_factor_recovery_codes' => encrypt(json_encode(array_values($codes)))]);

        return true;
    }

    /** Ativa 2FA para um usuario. */
    public function enable(AuthenticatableUser $user, string $secret): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        $user->update([
            'two_factor_secret'         => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_enabled'        => true,
            'two_factor_confirmed_at'   => now(),
        ]);
        return $recoveryCodes;
    }

    /** Desativa 2FA. */
    public function disable(AuthenticatableUser $user): void
    {
        $user->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled'        => false,
            'two_factor_confirmed_at'   => null,
        ]);
    }

    /** Recupera o secret descriptografado. */
    public function getSecret(AuthenticatableUser $user): ?string
    {
        if (! $user->two_factor_secret) return null;
        try {
            return decrypt($user->two_factor_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    // ============ TOTP Implementation (RFC 6238) ============

    protected function generateCode(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $otp = $binary % pow(10, $this->codeLength);
        return str_pad((string) $otp, $this->codeLength, '0', STR_PAD_LEFT);
    }

    protected function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $result .= $alphabet[bindec($chunk)];
        }
        return $result;
    }

    protected function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper($encoded);
        $binary = '';
        foreach (str_split($encoded) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) break;
            $result .= chr(bindec($byte));
        }
        return $result;
    }
}
