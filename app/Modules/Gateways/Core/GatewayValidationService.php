<?php

namespace App\Modules\Gateways\Core;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class GatewayValidationService
{
    public function __construct(protected GatewayFeatureResolver $features)
    {
    }

    public function validate(string $gateway, array $payload): array
    {
        $methods = Arr::wrap($payload['payment_methods'] ?? []);
        $allowedMethods = $this->features->availablePaymentMethods($gateway);

        foreach ($methods as $method => $enabled) {
            if (! in_array($method, $allowedMethods, true)) {
                throw ValidationException::withMessages([
                    'payment_methods' => 'Metodo de pagamento nao suportado por este gateway.',
                ]);
            }
        }

        $environment = (string) ($payload['environment'] ?? 'production');
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw ValidationException::withMessages([
                'environment' => 'Ambiente invalido.',
            ]);
        }

        return $payload;
    }
}
