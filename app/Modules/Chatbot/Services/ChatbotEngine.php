<?php

namespace App\Modules\Chatbot\Services;

use App\Models\Customer;
use App\Modules\Tenancy\Services\TenantPackageService;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor de autoatendimento financeiro via WhatsApp.
 *
 * Resolve consultas comuns do cliente final sem abrir acesso administrativo:
 * faturas em aberto, segunda via, Pix copia e cola, parcelas, serviços e dados.
 */
class ChatbotEngine
{
    protected array $intents = [
        'pix_code' => ['codigo pix', 'pix copia', 'copia e cola', 'qr code', 'qrcode', 'chave pix', 'pix'],
        'second_copy' => ['2a via', '2 via', 'segunda via', 'segunda-via', 'reenviar', 'reenvio', 'copia da fatura', 'boleto'],
        'debt_query' => ['debitos', 'debito', 'divida', 'dividas', 'em aberto', 'em atraso', 'vencidas', 'pendentes', 'valor devido', 'quanto devo', 'consultar debito', 'consultar fatura'],
        'payment_link' => ['pagar', 'link', 'pagamento', 'quero pagar', 'paguei'],
        'due_date' => ['vencimento', 'vence', 'vencer', 'data de vencimento', 'proximo vencimento'],
        'status' => ['status', 'situacao', 'como esta', 'ja paguei', 'pago', 'paga'],
        'personal_data' => ['meus dados', 'dados cadastrais', 'cadastro', 'cpf', 'cnpj', 'telefone', 'email'],
        'services' => ['servico', 'pacote', 'plano contratado', 'plano', 'contratado', 'contrato'],
        'installments' => ['parcelas', 'prestacoes', 'pagas', 'em atraso', 'vencidas', 'pendentes'],
        'greeting' => ['oi', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'hello'],
        'support' => ['ajuda', 'suporte', 'falar', 'atendente', 'humano', 'problema'],
        'thanks' => ['obrigado', 'obrigada', 'valeu', 'agradeco'],
    ];

    public function processMessage(string $from, string $message, ?int $tenantId = null): ?string
    {
        $normalized = $this->normalizeText($message);
        $intent = $this->detectIntent($normalized);

        $this->logInteraction($from, $message, $intent, $tenantId);

        return match ($intent) {
            'pix_code' => $this->handlePixCode($from, $tenantId),
            'second_copy' => $this->handleSecondCopy($from, $tenantId),
            'debt_query' => $this->handleDebtQuery($from, $tenantId),
            'payment_link' => $this->handlePaymentLink($from, $tenantId),
            'due_date' => $this->handleDueDate($from, $tenantId),
            'status' => $this->handleStatus($from, $tenantId),
            'personal_data' => $this->handlePersonalData($from, $tenantId),
            'services' => $this->handleServices($from, $tenantId),
            'installments' => $this->handleInstallments($from, $tenantId),
            'greeting' => $this->handleGreeting(),
            'support' => $this->handleSupport(),
            'thanks' => 'De nada. Estamos à disposição.',
            default => $this->handleFallback(),
        };
    }

    protected function detectIntent(string $message): string
    {
        foreach ($this->intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    return $intent;
                }
            }
        }

        return 'fallback';
    }

    protected function handlePaymentLink(string $from, ?int $tenantId): string
    {
        $invoice = $this->findPendingInvoice($from, $tenantId);
        if (! $invoice) {
            return 'Não encontrei faturas pendentes para o seu número. Se acredita que há um erro, digite *suporte*.';
        }

        $link = $this->paymentLink($invoice);
        $pix = $this->pixCopyPaste($invoice);

        $message = $this->invoiceHeader('*Link de pagamento*', $invoice);
        $message .= "\n\n";
        $message .= $link
            ? "Pague por aqui:\n{$link}"
            : 'Ainda não há link de pagamento gravado para esta fatura. Digite *suporte* para atendimento.';

        if ($pix) {
            $message .= "\n\nPix copia e cola disponível. Digite *Pix* para receber o código.";
        }

        return $message;
    }

    protected function handleSecondCopy(string $from, ?int $tenantId): string
    {
        $invoice = $this->findPendingInvoice($from, $tenantId);
        if (! $invoice) {
            return 'Não encontrei faturas pendentes para reenviar.';
        }

        $link = $this->paymentLink($invoice);
        $pix = $this->pixCopyPaste($invoice);

        $message = $this->invoiceHeader('*2ª via da fatura*', $invoice);
        $message .= "\n\n";
        $message .= $link
            ? "Link seguro:\n{$link}"
            : 'Entre em contato com o suporte para obter o link desta fatura.';

        if ($pix) {
            $message .= "\n\nSe preferir Pix, digite *Pix* que eu envio o copia e cola.";
        }

        return $message;
    }

    protected function handlePixCode(string $from, ?int $tenantId): string
    {
        $invoice = $this->findPendingInvoice($from, $tenantId);
        if (! $invoice) {
            return 'Não encontrei fatura em aberto para gerar Pix. Digite *débitos* para consultar sua situação.';
        }

        $pix = $this->pixCopyPaste($invoice);
        $link = $this->paymentLink($invoice);

        if (! $pix) {
            return $this->invoiceHeader('*Pix não disponível automaticamente*', $invoice)
                ."\n\n"
                .($link
                    ? "Você pode gerar ou pagar por Pix neste link:\n{$link}"
                    : 'Esta fatura ainda não possui código Pix salvo. Digite *suporte* para atendimento.');
        }

        $message = $this->invoiceHeader('*Código Pix copia e cola*', $invoice);
        $message .= "\n\n{$pix}";

        if ($link) {
            $message .= "\n\nLink de pagamento:\n{$link}";
        }

        return $message;
    }

    protected function handleDebtQuery(string $from, ?int $tenantId): string
    {
        $invoices = $this->findPendingInvoices($from, $tenantId, 5);

        if ($invoices->isEmpty()) {
            return $this->packageStatusFallback($from, $tenantId);
        }

        $total = $invoices->sum(fn ($invoice) => (float) $invoice->amount);
        $lines = $invoices->map(function ($invoice) {
            $service = $this->serviceName($invoice);
            $status = $this->statusLabel((string) $invoice->status);
            $installments = $this->installmentTextForInvoice($invoice);

            return "- {$invoice->number} | {$status}\n"
                ."  Valor: ".Format::money($invoice->amount)."\n"
                ."  Vencimento: ".Format::date($invoice->due_date)."\n"
                ."  Serviço: {$service}\n"
                ."  Parcelas: {$installments}";
        })->implode("\n\n");

        return "*Débitos em aberto*\n\n"
            ."Encontramos {$invoices->count()} fatura(s) em aberto.\n"
            ."Total: *".Format::money($total)."*\n\n"
            ."{$lines}\n\n"
            ."Digite *2ª via* para receber o link ou *Pix* para receber o código copia e cola.";
    }

    protected function handleDueDate(string $from, ?int $tenantId): string
    {
        $invoice = $this->findPendingInvoice($from, $tenantId);
        if (! $invoice) {
            return 'Não encontrei vencimentos em aberto para este número.';
        }

        return $this->invoiceHeader('*Próximo vencimento*', $invoice)
            ."\n\nDigite *2ª via* para receber o link ou *Pix* para receber o código copia e cola.";
    }

    protected function handleStatus(string $from, ?int $tenantId): string
    {
        $invoice = $this->findLatestInvoice($from, $tenantId);

        if (! $invoice) {
            return $this->packageStatusFallback($from, $tenantId);
        }

        return "*Status da sua última fatura*\n\n"
            ."Fatura: *{$invoice->number}*\n"
            ."Status: *".$this->statusLabel((string) $invoice->status)."*\n"
            ."Serviço: *".$this->serviceName($invoice)."*\n"
            ."Valor: *".Format::money($invoice->amount)."*\n"
            ."Vencimento: *".Format::date($invoice->due_date)."*";
    }

    protected function handlePersonalData(string $from, ?int $tenantId): string
    {
        $customer = $this->findCustomer($from, $tenantId);
        if (! $customer) {
            return 'Não encontrei seu cadastro por este número. Digite *suporte* para falar com atendimento.';
        }

        $city = trim(($customer->cidade ?: '').'/'.($customer->uf ?: ''), '/');

        return "*Seus dados cadastrais*\n\n"
            ."Nome: *{$customer->name}*\n"
            ."Documento: *".($customer->document ?: 'não informado')."*\n"
            ."Email: *".($customer->email ?: 'não informado')."*\n"
            ."Telefone: *".Format::phone($customer->phone ?: $customer->whatsapp)."*\n"
            ."Cidade/UF: *".($city ?: 'não informado')."*";
    }

    protected function handleServices(string $from, ?int $tenantId): string
    {
        $customer = $this->findCustomer($from, $tenantId);
        if (! $customer) {
            return 'Não encontrei serviços vinculados a este número.';
        }

        $summary = app(TenantPackageService::class)->customerSummary($customer);
        if ($summary['subscriptions']->isEmpty()) {
            $invoice = $this->findLatestInvoice($from, $tenantId);

            return $invoice
                ? "*Serviço vinculado à última fatura*\n\n".$this->serviceName($invoice)
                : 'Não encontrei pacote contratado no seu cadastro.';
        }

        return $summary['subscriptions']->take(3)->map(function ($subscription) {
            $nextDue = $subscription->next_due_date ? Format::date($subscription->next_due_date) : 'quitado';

            return "*{$subscription->package->name}*\n"
                ."Status: {$subscription->status}\n"
                ."Valor contratado: ".Format::money($subscription->final_amount)."\n"
                ."Próximo vencimento: {$nextDue}";
        })->implode("\n\n");
    }

    protected function handleInstallments(string $from, ?int $tenantId): string
    {
        $customer = $this->findCustomer($from, $tenantId);
        if (! $customer) {
            return $this->installmentsFromInvoices($from, $tenantId)
                ?: 'Não encontrei parcelas vinculadas a este número.';
        }

        $summary = app(TenantPackageService::class)->customerSummary($customer);

        if ($summary['subscriptions']->isEmpty()) {
            return $this->installmentsFromInvoices($from, $tenantId)
                ?: 'Não encontrei parcelas vinculadas a este número.';
        }

        $lines = $summary['subscriptions']->take(3)->map(function ($subscription) {
            $installments = $subscription->installments;
            $total = max((int) $subscription->installments_count, $installments->count());
            $paid = $installments->where('status', 'paid')->count();
            $pending = $installments->whereIn('status', ['pending', 'overdue'])->count();
            $overdue = $installments->filter(function ($installment) {
                return $installment->status === 'overdue'
                    || ($installment->status === 'pending' && $installment->due_date < now('America/Sao_Paulo')->toDateString());
            })->count();
            $nextDue = $subscription->next_due_date ? Format::date($subscription->next_due_date) : 'quitado';

            return "*{$subscription->package->name}*\n"
                ."Pagas: {$paid}/{$total}\n"
                ."Pendentes: {$pending}\n"
                ."Em atraso: {$overdue}\n"
                ."Próximo vencimento: {$nextDue}\n"
                ."Valor contratado: ".Format::money($subscription->final_amount);
        })->implode("\n\n");

        return "*Parcelas do seu cadastro*\n\n"
            ."Total pago: ".Format::money($summary['paid_amount'])."\n"
            ."Em aberto: ".Format::money($summary['pending_amount'])."\n"
            ."Parcelas pendentes: {$summary['pending_count']}\n"
            ."Parcelas em atraso: {$summary['overdue_count']}\n\n"
            .$lines;
    }

    protected function handleGreeting(): string
    {
        $hour = (int) now('America/Sao_Paulo')->format('H');
        $period = match (true) {
            $hour < 12 => 'Bom dia',
            $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };

        return "{$period}!\n\n"
            ."Posso ajudar com:\n\n"
            ."*Débitos* - consultar faturas em aberto\n"
            ."*2ª via* - receber link da fatura\n"
            ."*Pix* - receber código copia e cola\n"
            ."*Parcelas* - resumo de pagas e pendentes\n"
            ."*Vencimento* - próximo vencimento\n"
            ."*Meus dados* - dados cadastrais\n"
            ."*Pacote* - serviço contratado\n"
            ."*Suporte* - atendimento humano";
    }

    protected function handleSupport(): string
    {
        return "*Atendimento humano*\n\n"
            ."Um atendente entrará em contato em breve.\n"
            ."Horário de atendimento: Seg-Sex, 8h às 18h (São Paulo).";
    }

    protected function handleFallback(): string
    {
        return "Não entendi sua mensagem.\n\n"
            ."Tente uma destas opções:\n"
            ."*Débitos*\n"
            ."*2ª via*\n"
            ."*Pix*\n"
            ."*Parcelas*\n"
            ."*Vencimento*\n"
            ."*Meus dados*\n"
            ."*Pacote*\n"
            ."*Suporte*";
    }

    protected function packageStatusFallback(string $from, ?int $tenantId): string
    {
        $customer = $this->findCustomer($from, $tenantId);
        if (! $customer) {
            return 'Não encontrei faturas registradas para o seu número.';
        }

        $summary = app(TenantPackageService::class)->customerSummary($customer);
        if ($summary['pending_count'] < 1) {
            return 'Não encontrei débitos em aberto para o seu número.';
        }

        return "*Resumo financeiro*\n\n"
            ."Valor em aberto: *".Format::money($summary['pending_amount'])."*\n"
            ."Parcelas pendentes: *{$summary['pending_count']}*\n"
            ."Parcelas em atraso: *{$summary['overdue_count']}*";
    }

    protected function findPendingInvoice(string $from, ?int $tenantId): ?object
    {
        return $this->findPendingInvoices($from, $tenantId, 1)->first();
    }

    protected function findPendingInvoices(string $from, ?int $tenantId, int $limit = 5): Collection
    {
        return $this->invoiceQueryForPhone($from, $tenantId)
            ->whereIn('invoices.status', ['pending', 'sent', 'overdue'])
            ->orderBy('invoices.due_date')
            ->orderBy('invoices.id')
            ->limit($limit)
            ->get();
    }

    protected function findLatestInvoice(string $from, ?int $tenantId): ?object
    {
        return $this->invoiceQueryForPhone($from, $tenantId)
            ->orderByDesc('invoices.id')
            ->first();
    }

    protected function invoiceQueryForPhone(string $from, ?int $tenantId)
    {
        $tail = substr($this->digits($from), -8);

        return DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->when($tenantId, fn ($q) => $q->where('invoices.tenant_id', $tenantId))
            ->where(function ($query) use ($tail) {
                $query->where('customers.whatsapp', 'LIKE', '%' . $tail)
                    ->orWhere('customers.phone', 'LIKE', '%' . $tail);
            })
            ->select('invoices.*');
    }

    protected function findCustomer(string $from, ?int $tenantId): ?Customer
    {
        $tail = substr($this->digits($from), -8);

        return Customer::query()
            ->where(function ($query) use ($tail) {
                $query->where('whatsapp', 'LIKE', '%' . $tail)
                    ->orWhere('phone', 'LIKE', '%' . $tail);
            })
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('id')
            ->first();
    }

    protected function invoiceHeader(string $title, object $invoice): string
    {
        return "{$title}\n\n"
            ."Fatura: *{$invoice->number}*\n"
            ."Serviço: *".$this->serviceName($invoice)."*\n"
            ."Parcelas: *".$this->installmentTextForInvoice($invoice)."*\n"
            ."Valor: *".Format::money($invoice->amount)."*\n"
            ."Vencimento: *".Format::date($invoice->due_date)."*\n"
            ."Status: *".$this->statusLabel((string) $invoice->status)."*";
    }

    protected function installmentsFromInvoices(string $from, ?int $tenantId): ?string
    {
        $invoices = $this->findPendingInvoices($from, $tenantId, 5);
        if ($invoices->isEmpty()) {
            return null;
        }

        $lines = $invoices->map(function ($invoice) {
            return "{$invoice->number}: ".$this->installmentTextForInvoice($invoice)
                .' - '.Format::money($invoice->amount)
                .' - vence '.Format::date($invoice->due_date);
        })->implode("\n");

        return "*Parcelas em aberto*\n\n{$lines}";
    }

    protected function paymentLink(object $invoice): ?string
    {
        $meta = $this->invoiceMeta($invoice);

        return $this->firstFilled(
            $invoice->payment_url ?? null,
            data_get($meta, 'payment_url'),
            data_get($meta, 'link_pagamento'),
            data_get($meta, 'checkout_url'),
            data_get($meta, 'boleto_url'),
            data_get($meta, 'gateway_payment.payment_url'),
            data_get($meta, 'gateway_payment.invoice_url'),
            data_get($meta, 'gateway_payment.checkout_url'),
            data_get($meta, 'gateway_raw.payment_url'),
            data_get($meta, 'gateway_raw.init_point'),
            data_get($meta, 'gateway_raw.ticket_url'),
        );
    }

    protected function pixCopyPaste(object $invoice): ?string
    {
        $meta = $this->invoiceMeta($invoice);

        return $this->firstFilled(
            data_get($meta, 'pix_copia_cola'),
            data_get($meta, 'pix_copy_paste'),
            data_get($meta, 'pix_payload'),
            data_get($meta, 'pix_code'),
            data_get($meta, 'qrcode'),
            data_get($meta, 'qr_code'),
            data_get($meta, 'qrcode_pix'),
            data_get($meta, 'pix.qrcode'),
            data_get($meta, 'gateway_payment.pix_copia_cola'),
            data_get($meta, 'gateway_payment.pix_copy_paste'),
            data_get($meta, 'gateway_payment.pix_payload'),
            data_get($meta, 'gateway_payment.qrcode'),
            data_get($meta, 'gateway_payment.qr_code'),
            data_get($meta, 'gateway_payment.qrcode_pix'),
            data_get($meta, 'gateway_raw.qrcode.qrcode'),
            data_get($meta, 'gateway_raw.qrcode'),
            data_get($meta, 'gateway_raw.point_of_interaction.transaction_data.qr_code'),
            data_get($meta, 'raw.point_of_interaction.transaction_data.qr_code'),
            data_get($meta, 'raw.qrcode.qrcode'),
        );
    }

    protected function serviceName(object $invoice): string
    {
        $meta = $this->invoiceMeta($invoice);
        $description = trim((string) ($invoice->description ?? ''));
        $descriptionLine = trim(strtok($description, "\n") ?: $description);

        return $this->firstFilled(
            data_get($meta, 'servico_principal'),
            data_get($meta, 'service_name'),
            data_get($meta, 'nome_servico'),
            data_get($meta, 'package_name'),
            data_get($meta, 'gateway_payment.service_name'),
            data_get($meta, 'whmcs.service_name'),
            data_get($meta, 'whmcs.product_name'),
            data_get($meta, 'whmcs_service_name'),
            data_get($meta, 'whmcs_product_name'),
            $descriptionLine,
        ) ?: 'Serviço não informado';
    }

    protected function installmentTextForInvoice(object $invoice): string
    {
        $meta = $this->invoiceMeta($invoice);

        $summary = $this->firstFilled(
            data_get($meta, 'resumo_parcelas'),
            data_get($meta, 'installment_summary'),
        );

        if ($summary) {
            return $summary;
        }

        $current = (int) ($this->firstFilled(
            data_get($meta, 'parcela_atual'),
            data_get($meta, 'installment_number'),
            data_get($meta, 'installment.current'),
            data_get($meta, 'current_installment'),
        ) ?: 0);

        $total = (int) ($this->firstFilled(
            data_get($meta, 'parcelas_total'),
            data_get($meta, 'installments_count'),
            data_get($meta, 'installment.total'),
            data_get($meta, 'total_installments'),
        ) ?: 0);

        $paid = $this->firstFilled(data_get($meta, 'parcelas_pagas'), data_get($meta, 'paid_installments'));
        $pending = $this->firstFilled(data_get($meta, 'parcelas_pendentes'), data_get($meta, 'pending_installments'));

        if ($current > 0 && $total > 0) {
            $text = "Parcela {$current}/{$total}";

            if ($paid !== null || $pending !== null) {
                $text .= ' - '.($paid ?? '0').' paga(s), '.($pending ?? max(0, $total - (int) ($paid ?? 0))).' pendente(s)';
            }

            return $text;
        }

        if ($total > 1) {
            return "Parcelamento em {$total}x";
        }

        return 'Fatura única';
    }

    protected function invoiceMeta(object $invoice): array
    {
        $meta = $invoice->meta ?? [];

        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    protected function statusLabel(string $status): string
    {
        return [
            'pending' => 'Pendente',
            'sent' => 'Enviada',
            'paid' => 'Paga',
            'overdue' => 'Vencida',
            'canceled' => 'Cancelada',
            'cancelled' => 'Cancelada',
            'refunded' => 'Estornada',
        ][$status] ?? ucfirst($status);
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

    protected function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?: $value;
    }

    protected function logInteraction(string $from, string $message, string $intent, ?int $tenantId): void
    {
        try {
            DB::table('chatbot_interactions')->insert([
                'tenant_id' => $tenantId,
                'from_number' => $from,
                'message' => mb_substr($message, 0, 1000),
                'intent' => $intent,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Logs de chatbot não devem interromper a resposta ao cliente.
        }
    }
}
