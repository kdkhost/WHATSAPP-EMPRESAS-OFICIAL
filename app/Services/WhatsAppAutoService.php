<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WhatsAppCustomerServiceLog;
use App\Modules\Chatbot\Services\ChatbotEngine;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Http\Request;

class WhatsAppAutoService
{
    public function __construct(
        protected ChatbotEngine $chatbot,
        protected WhatsAppDailyLimitService $limits,
        protected StructuredLogSanitizer $sanitizer,
    ) {}

    public function reply(Tenant $tenant, ?TenantInstance $instance, string $phone, string $message, array $payload = [], ?Request $request = null): ?string
    {
        $customer = $this->findCustomer($tenant, $phone);
        $action = $this->actionFromMessage($message);
        $limit = $this->limits->hit($tenant, $customer, $phone, $action);

        if (! $limit['allowed']) {
            $reply = $limit['message'];
            $this->log($tenant, $customer, $instance, $phone, $action, $message, $reply, 'limited', $payload, $request);

            return $reply;
        }

        $reply = $this->menuReply($message) ?: $this->chatbot->processMessage($phone, $this->translateMenuOption($message), $tenant->id);
        $this->log($tenant, $customer, $instance, $phone, $action, $message, $reply, 'processed', $payload, $request);

        return $reply;
    }

    public function menu(): string
    {
        return "Olá, sou o atendimento automático.\n\n"
            ."1 - Consultar débitos\n"
            ."2 - Segunda via de fatura\n"
            ."3 - Código Pix\n"
            ."4 - Consultar parcelas\n"
            ."5 - Serviços/planos ativos\n"
            ."6 - Falar com atendente\n"
            .'0 - Encerrar';
    }

    protected function menuReply(string $message): ?string
    {
        $normalized = $this->normalizeText($message);

        if (in_array($normalized, ['menu', 'inicio', 'comecar', 'opcoes', 'opcoes do menu'], true)) {
            return $this->menu();
        }

        if ($normalized === '0') {
            return 'Atendimento encerrado. Quando precisar, envie *menu* para reabrir as opções.';
        }

        return null;
    }

    protected function translateMenuOption(string $message): string
    {
        return match (trim($message)) {
            '1' => 'consultar débitos em aberto',
            '2' => 'segunda via de fatura boleto link pagamento',
            '3' => 'código pix copia e cola',
            '4' => 'consultar parcelas pagas pendentes vencidas',
            '5' => 'serviço plano contratado pacote',
            '6' => 'suporte falar atendente humano',
            default => $message,
        };
    }

    protected function actionFromMessage(string $message): string
    {
        $trimmed = trim($message);
        $normalized = $this->normalizeText($message);

        return match ($trimmed) {
            '1' => 'debt_query',
            '2' => 'second_copy',
            '3' => 'pix_code',
            '4' => 'installments',
            '5' => 'plan_query',
            '6' => 'human_support',
            default => match (true) {
                str_contains($normalized, 'pix') => 'pix_code',
                str_contains($normalized, 'segunda via') || str_contains($normalized, '2a via') || str_contains($normalized, 'boleto') => 'second_copy',
                str_contains($normalized, 'debito') || str_contains($normalized, 'quanto devo') || str_contains($normalized, 'em aberto') => 'debt_query',
                str_contains($normalized, 'parcela') || str_contains($normalized, 'prestacao') => 'installments',
                str_contains($normalized, 'plano') || str_contains($normalized, 'servico') || str_contains($normalized, 'pacote') => 'plan_query',
                str_contains($normalized, 'suporte') || str_contains($normalized, 'atendente') || str_contains($normalized, 'humano') => 'human_support',
                str_contains($normalized, 'vencimento') || str_contains($normalized, 'vence') => 'due_date',
                str_contains($normalized, 'link') || str_contains($normalized, 'pagar') || str_contains($normalized, 'pagamento') => 'payment_link',
                str_contains($normalized, 'cadastro') || str_contains($normalized, 'meus dados') => 'profile_update',
                default => 'fallback',
            },
        };
    }

    protected function findCustomer(Tenant $tenant, string $phone): ?Customer
    {
        $digits = preg_replace('/\D/', '', $phone) ?: $phone;

        return Customer::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($digits) {
                $query->where('whatsapp', 'LIKE', '%'.substr($digits, -8))
                    ->orWhere('phone', 'LIKE', '%'.substr($digits, -8));
            })
            ->latest('id')
            ->first();
    }

    public function syncCustomerPushName(Tenant $tenant, string $phone, string $pushName): void
    {
        $customer = $this->findCustomer($tenant, $phone);
        if ($customer && trim($pushName) !== '') {
            $meta = $customer->meta ?: [];
            if (($meta['whatsapp_name'] ?? '') !== $pushName) {
                $meta['whatsapp_name'] = $pushName;
                $customer->forceFill(['meta' => $meta])->save();
            }
        }
    }

    protected function normalizeText(string $message): string
    {
        $message = mb_strtolower(trim($message), 'UTF-8');
        $message = strtr($message, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $message) ?: $message;
    }

    protected function log(
        Tenant $tenant,
        ?Customer $customer,
        ?TenantInstance $instance,
        string $phone,
        string $action,
        string $message,
        ?string $reply,
        string $status,
        array $payload,
        ?Request $request
    ): void {
        try {
            WhatsAppCustomerServiceLog::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer?->id,
                'tenant_instance_id' => $instance?->id,
                'phone' => preg_replace('/\D/', '', $phone) ?: $phone,
                'action' => $action,
                'direction' => 'in',
                'message' => mb_substr($message, 0, 60000),
                'response' => $reply ? mb_substr($reply, 0, 60000) : null,
                'status' => $status,
                'payload' => $this->sanitizer->sanitize($payload),
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable) {
            // O atendimento não pode falhar por indisponibilidade de log.
        }
    }
}
