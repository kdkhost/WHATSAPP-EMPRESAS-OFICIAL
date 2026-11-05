<?php

namespace App\Modules\Gateways\Core;

class GatewayFeatureResolver
{
    public function __construct(protected GatewayConfigSchema $schema)
    {
    }

    public function availablePaymentMethods(string $gateway): array
    {
        return $this->schema->for($gateway)['methods'] ?? [];
    }

    public function supports(string $gateway, string $feature): bool
    {
        return in_array($feature, $this->schema->for($gateway)['supports'] ?? [], true);
    }
}
