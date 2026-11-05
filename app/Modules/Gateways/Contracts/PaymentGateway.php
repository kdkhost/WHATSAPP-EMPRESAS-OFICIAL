<?php

namespace App\Modules\Gateways\Contracts;

/**
 * Contrato comum a todos os gateways de pagamento.
 * Permite que o modulo Billing trabalhe agnostic ao gateway escolhido.
 */
interface PaymentGateway
{
    /** Cria uma cobranca (checkout transparente) e retorna URL + ID. */
    public function createCharge(array $invoice): ChargeResult;

    /** Trata um webhook recebido do gateway (assinatura ja validada). */
    public function handleWebhook(array $payload, string $token): WebhookResult;

    /** Consulta status de uma cobranca (fallback ao webhook). */
    public function fetchStatus(string $chargeId): ?string;

    /** Estorno parcial ou total. */
    public function refund(string $chargeId, ?float $amount = null): bool;

    /** Identificador deste gateway: 'mercadopago' | 'efipro' | 'sumup' */
    public function id(): string;

    /** Valida as credenciais/configuracoes atuais. */
    public function validateCredentials(): bool;

    /** Calcula taxas previstas para o valor informado. */
    public function calculateFees(float $amount, array $context = []): array;

    /** Lista os metodos de pagamento suportados. */
    public function availablePaymentMethods(): array;

    /** Monta o webhook dinamico a ser usado na cobranca. */
    public function buildDynamicWebhook(array $context = []): array;

    /** Informa se um recurso especifico e suportado. */
    public function supports(string $feature): bool;
}
