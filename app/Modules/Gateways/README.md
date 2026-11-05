# Modulos de Pagamento (Gateways)

Cada gateway de pagamento eh um **modulo independente e desacoplado**, seguindo
exatamente a mesma estrutura dos addons de terceiros.

## Arquitetura

```
app/Modules/Gateways/
├── Contracts/                      # Interface comum (PaymentGateway, ChargeResult, WebhookResult)
├── MercadoPago/                    # Modulo: Mercado Pago
│   ├── Providers/MercadoPagoServiceProvider.php
│   ├── Services/MercadoPagoGateway.php
│   ├── Http/Controllers/MercadoPagoConfigController.php
│   ├── Resources/views/
│   └── routes/
├── EfiPro/                         # Modulo: Efi Pro (Gerencianet)
│   ├── Providers/EfiProServiceProvider.php
│   ├── Services/EfiProGateway.php
│   ├── Http/Controllers/EfiProConfigController.php
│   ├── Resources/views/
│   └── routes/
└── SumUp/                          # Modulo: SumUp
    ├── Providers/SumUpServiceProvider.php
    ├── Services/SumUpGateway.php
    ├── Http/Controllers/SumUpConfigController.php
    ├── Resources/views/
    └── routes/
```

## Interface comum (Contracts)

Todos os gateways implementam `PaymentGateway`:

```php
interface PaymentGateway
{
    public function id(): string;
    public function createCharge(array $invoice): ChargeResult;
    public function handleWebhook(array $payload, string $token): WebhookResult;
    public function fetchStatus(string $chargeId): ?string;
    public function refund(string $chargeId, ?float $amount = null): bool;
}
```

## Como criar um novo gateway (addon de terceiro)

1. Crie a pasta em `app/Addons/meu-gateway/`
2. Implemente `PaymentGateway` no seu Service
3. Registre no container:
   ```php
   $this->app->singleton(\App\Addons\meu_gateway\MeuGateway::class);
   $this->app->tag([\App\Addons\meu_gateway\MeuGateway::class], 'payment.gateways');
   ```
4. Registre a rota de webhook:
   ```php
   Route::post('/webhooks/meu-gateway/{token}', ...)->name('webhook.meu-gateway');
   ```
5. Declare no addon.json:
   ```json
   {
       "slug": "meu-gateway",
       "requires": ["billing"],
       "permissions": [{"action": "configure", "label": "Configurar Meu Gateway"}]
   }
   ```

## Ativacao/Desativacao

Cada gateway pode ser ativado/desativado independentemente em:
- **config/modules.php** → `gateways.mercadopago => true/false`
- **Admin → Configuracoes → Gateways**
- Se for addon de terceiro: **Admin → Addons**

## Webhook dinamico por fatura

Cada fatura recebe um **token unico** no momento da criacao da cobranca.
O webhook URL fica: `/webhooks/<gateway>/<token-unico-da-fatura>`

Quando o gateway (MercadoPago, Efi, etc.) envia a notificacao de pagamento,
o sistema identifica a fatura pelo token e atualiza o status automaticamente.

## Checkout transparente

Todos os gateways usam **checkout transparente** (sem redirecionar para pagina externa).
O link gerado pelo `createCharge()` eh enviado ao cliente via WhatsApp e pode ser
aberto diretamente no celular sem sair da conversa.
