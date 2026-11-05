<?php

namespace App\Services;

class StructuredLogSanitizer
{
    protected array $sensitiveKeys = [
        'password',
        'senha',
        'api_key',
        'apikey',
        'apiKey',
        'authorization',
        'cookie',
        'set-cookie',
        'token',
        'instance_token',
        'webhook_secret',
        'qrcode',
        'qr',
        'base64',
        'card',
        'cartao',
        'cartão',
        'cvv',
        'security_code',
    ];

    public function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[$key] = $this->isSensitiveKey((string) $key)
                    ? '[mascarado]'
                    : $this->sanitize($item);
            }

            return $clean;
        }

        if (is_string($value)) {
            return $this->maskPhoneNumbers($this->maskLongSecrets($value));
        }

        return $value;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $key));

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            $needle = strtolower(str_replace(['-', '_', ' '], '', $sensitiveKey));
            if ($normalized === $needle || str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function maskLongSecrets(string $value): string
    {
        if (preg_match('/^Bearer\s+\S{10,}$/i', $value)) {
            return 'Bearer [mascarado]';
        }

        if (preg_match('/^data:image\/[^;]+;base64,/i', $value)) {
            return '[qrcode/base64 mascarado]';
        }

        if (mb_strlen($value) > 1800) {
            return mb_substr($value, 0, 400).'... [conteudo reduzido]';
        }

        return $value;
    }

    protected function maskPhoneNumbers(string $value): string
    {
        return preg_replace_callback('/(?<!\d)(\d{10,14})(?!\d)/', function (array $matches) {
            $digits = $matches[1];
            $length = mb_strlen($digits);

            if ($length <= 6) {
                return '[telefone]';
            }

            return mb_substr($digits, 0, 2).str_repeat('*', max(0, $length - 6)).mb_substr($digits, -4);
        }, $value) ?? $value;
    }
}
