<?php

namespace App\Modules\Gateways\Core;

class GatewayConfigSchema
{
    public function all(): array
    {
        return [
            'mercadopago' => [
                'methods' => ['pix', 'credit', 'debit', 'boleto'],
                'supports' => ['fees', 'installments', 'split', 'webhook', 'sandbox', 'allowlist'],
            ],
            'efipro' => [
                'methods' => ['pix', 'credit', 'boleto'],
                'supports' => ['fees', 'installments', 'split', 'webhook', 'sandbox', 'allowlist'],
            ],
            'sumup' => [
                'methods' => ['credit', 'debit'],
                'supports' => ['fees', 'installments', 'webhook', 'sandbox', 'allowlist'],
            ],
        ];
    }

    public function for(string $gateway): array
    {
        return $this->all()[$gateway] ?? ['methods' => [], 'supports' => []];
    }
}
