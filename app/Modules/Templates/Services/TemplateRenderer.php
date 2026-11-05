<?php

namespace App\Modules\Templates\Services;

use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Support\Format;
use Illuminate\Support\Carbon;

/**
 * Renderiza um template substituindo variáveis pelos dados reais do contexto.
 * Usado tanto no envio operacional para WhatsApp quanto no preview ao vivo.
 */
class TemplateRenderer
{
    public function render(WhatsAppTemplate|string $template, array $context): string
    {
        $text = '';
        if ($template instanceof WhatsAppTemplate) {
            $text = $template->content_text ?: WhatsAppTemplate::htmlToWhatsApp($template->content_html);
        } else {
            $text = (string) $template;
            if (str_contains($text, '<') && str_contains($text, '>')) {
                $text = WhatsAppTemplate::htmlToWhatsApp($text);
            }
        }

        $vars = $this->buildVariables($context, forWhatsApp: true);
        $rendered = strtr($text, $vars);

        // Se não houver pix_copia_cola no contexto, remove blocos residuais de Pix
        if (empty($context['pix_copia_cola'])) {
            $rendered = preg_replace('/(\*Pix (?:[Cc]opia e [Cc]ola|[Cc]opia e cola):?\*[\r\n]+(```[\r\n]*```)?[\r\n]*)/u', '', $rendered);
        }

        // Sanitiza quebras excessivas de linha
        return preg_replace("/\n{3,}/", "\n\n", trim($rendered));
    }

    public function renderHtml(WhatsAppTemplate|string $template, array $context): string
    {
        $html = $template instanceof WhatsAppTemplate ? $template->content_html : (string) $template;
        return strtr($html, $this->buildVariables($context, forWhatsApp: false));
    }

    public function buildVariables(array $c, bool $forWhatsApp = false): array
    {
        $name      = (string) ($c['nome'] ?? $c['customer_name'] ?? $c['client_name'] ?? '');
        $first     = trim(explode(' ', $name)[0] ?? '');
        $amount    = $c['valor'] ?? $c['amount'] ?? 0;
        $due       = $c['vencimento'] ?? $c['due_date'] ?? null;
        $paidAt    = $c['data_pagamento'] ?? $c['paid_at'] ?? null;
        $signedAt  = $c['data_assinatura'] ?? $c['signed_at'] ?? null;
        $nextDue   = $c['proximo_vencimento'] ?? $c['next_due_date'] ?? null;

        $daysLate = (int) ($c['dias_atraso'] ?? 0);
        if (! $daysLate && $due) {
            try {
                $parsedDue = Format::parseDate($due);
                if ($parsedDue) {
                    $diff = $parsedDue->startOfDay()->diffInDays(now('America/Sao_Paulo')->startOfDay(), false);
                    $daysLate = max(0, (int) $diff);
                }
            } catch (\Throwable) {
                $daysLate = 0;
            }
        }

        $formattedPaidAt   = Format::datetime($paidAt);
        $formattedSignedAt = Format::datetime($signedAt);
        $formattedNextDue  = Format::date($nextDue);

        $paymentUrl = (string) ($c['link_pagamento'] ?? $c['payment_url'] ?? $c['checkout_url'] ?? '');
        $token = (string) ($c['webhook_token'] ?? '');
        if ($token !== '') {
            $platformInvoiceUrl = url('/fatura/' . $token);
            if (empty($paymentUrl) || str_contains($paymentUrl, 'mercadopago.com') || str_contains($paymentUrl, '/ticket')) {
                $paymentUrl = $platformInvoiceUrl;
            }
        } elseif (str_contains($paymentUrl, 'mercadopago.com') || str_contains($paymentUrl, '/ticket')) {
            $invId = $c['invoice_id'] ?? $c['id'] ?? null;
            if ($invId) {
                $tok = \Illuminate\Support\Facades\DB::table('invoices')->where('id', $invId)->value('webhook_token');
                if ($tok) {
                    $paymentUrl = url('/fatura/' . $tok);
                }
            }
        }
        $contractUrl = (string) ($c['link_contrato'] ?? $c['contract_url'] ?? $c['public_url'] ?? '');
        $signatureUrl = (string) ($c['link_assinatura'] ?? $c['signature_url'] ?? $c['public_url'] ?? $contractUrl);

        // Resolução inteligente do QR Code e Pix
        $pixCopyCola = trim((string) ($c['pix_copia_cola'] ?? ''));
        $rawQr = (string) ($c['qrcode_url'] ?? $c['qrcode_pix'] ?? $c['qrcode_media_url'] ?? $c['qrcode'] ?? '');

        $qrDataUri = '';
        $qrBase64  = '';
        $qrHttpUrl = '';

        if (! empty($rawQr)) {
            if (str_starts_with($rawQr, 'data:image/')) {
                $qrDataUri = $rawQr;
                $commaPos = strpos($rawQr, ',');
                $qrBase64 = $commaPos !== false ? substr($rawQr, $commaPos + 1) : '';
            } elseif (preg_match('/^https?:\/\//i', $rawQr)) {
                $qrHttpUrl = $rawQr;
                $qrDataUri = $rawQr;
            } elseif (strlen($rawQr) > 60 && ! preg_match('/[\s<>"\'}{]/', $rawQr)) {
                // String em Base64 pura (comum na API do Mercado Pago)
                $qrBase64  = $rawQr;
                $qrDataUri = 'data:image/png;base64,' . $rawQr;
            }
        }

        // Se não temos imagem de QR Code mas temos o Pix Copia e Cola, gera URL dinâmica
        if (empty($qrDataUri) && ! empty($pixCopyCola)) {
            $qrHttpUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($pixCopyCola);
            $qrDataUri = $qrHttpUrl;
        }

        // Tag HTML da imagem para renderização em e-mail, faturas e preview visual
        $qrImgHtml = '';
        if (! empty($qrDataUri)) {
            $qrImgHtml = '<img src="' . htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8') . '" alt="QR Code Pix" class="img-fluid qrcode-pix-img" style="max-width:220px;width:100%;height:auto;display:block;margin:12px auto;border-radius:10px;border:1px solid #e2e8f0;padding:8px;background:#ffffff;box-shadow:0 2px 6px rgba(0,0,0,0.06);">';
        }

        $instruction = (string) ($c['qrcode_instrucao'] ?? '');
        if (empty($instruction) && (! empty($pixCopyCola) || ! empty($qrDataUri))) {
            $instruction = 'Aponte a câmera do aplicativo do seu banco para o QR Code ou utilize a opção Pix Copia e Cola.';
        }

        // Para WhatsApp em texto puro, não expor base64 de 30KB que estoura limites ou causa ban
        if ($forWhatsApp) {
            $waQrUrl = $qrHttpUrl ?: ($qrDataUri ? '📱 *[QR Code Pix enviado em anexo]*' : '');
            $waQrBase64 = $qrBase64 ? '📱 *[QR Code Pix enviado em anexo]*' : '';
            $waQrImg = $qrDataUri ? "📱 *[QR Code Pix enviado em anexo]*" : '';
        } else {
            $waQrUrl = $qrDataUri;
            $waQrBase64 = $qrBase64 ?: $qrDataUri;
            $waQrImg = $qrImgHtml;
        }

        return [
            // Dados do Cliente
            '{nome}'               => $name,
            '{primeiro_nome}'      => $first,
            '{email}'              => (string) ($c['email'] ?? $c['customer_email'] ?? ''),
            '{telefone}'           => (string) ($c['telefone'] ?? $c['phone'] ?? $c['whatsapp'] ?? ''),
            '{documento}'          => (string) ($c['documento'] ?? $c['document'] ?? $c['cpf_cnpj'] ?? $c['cpf'] ?? $c['cnpj'] ?? ''),
            '{cpf}'                => (string) ($c['cpf'] ?? ''),
            '{cnpj}'               => (string) ($c['cnpj'] ?? ''),
            '{nascimento}'         => (string) ($c['formatted_birth_date'] ?? $c['birth_date'] ?? $c['nascimento'] ?? $c['data_nascimento'] ?? ''),
            '{data_nascimento}'    => (string) ($c['formatted_birth_date'] ?? $c['birth_date'] ?? $c['data_nascimento'] ?? $c['nascimento'] ?? ''),
            '{tipo_pessoa}'        => (string) ($c['person_type'] ?? $c['tipo_pessoa'] ?? ''),

            // Fatura e Cobrança
            '{numero_fatura}'      => (string) ($c['numero_fatura'] ?? $c['invoice_number'] ?? ''),
            '{valor}'              => Format::money($c['valor_atualizado'] ?? $amount),
            '{valor_original}'     => Format::money($c['valor_original'] ?? $amount),
            '{valor_atualizado}'   => Format::money($c['valor_atualizado'] ?? $amount),
            '{multa}'              => Format::money($c['multa'] ?? $c['multa_atraso'] ?? 0),
            '{valor_multa}'        => Format::money($c['multa'] ?? $c['multa_atraso'] ?? 0),
            '{juros}'              => Format::money($c['juros'] ?? $c['juros_atraso'] ?? 0),
            '{valor_juros}'        => Format::money($c['juros'] ?? $c['juros_atraso'] ?? 0),
            '{juros_multa}'        => Format::money(($c['multa'] ?? $c['multa_atraso'] ?? 0) + ($c['juros'] ?? $c['juros_atraso'] ?? 0)),
            '{total_com_juros}'    => Format::money($c['valor_atualizado'] ?? $amount),
            '{valor_com_juros}'    => Format::money($c['valor_atualizado'] ?? $amount),
            '{vencimento}'         => Format::date($due),
            '{dias_atraso}'        => (string) $daysLate,
            '{descricao}'          => (string) ($c['descricao'] ?? $c['description'] ?? ''),
            '{link_pagamento}'     => $paymentUrl,
            '{boleto_url}'         => (string) ($c['boleto_url'] ?? ''),
            '{linha_digitavel}'    => (string) ($c['linha_digitavel'] ?? ''),
            '{metodo_pagamento}'   => (string) ($c['metodo_pagamento'] ?? $c['payment_method'] ?? ''),
            '{forma_pagamento}'    => (string) ($c['forma_pagamento'] ?? ucfirst((string) ($c['gateway'] ?? $c['metodo_pagamento'] ?? 'PIX'))),
            '{data_pagamento}'     => $formattedPaidAt ?: now('America/Sao_Paulo')->format('d/m/Y H:i'),

            // Pix e QR Code
            '{pix_copia_cola}'     => $pixCopyCola,
            '{qrcode_url}'         => $waQrUrl,
            '{qrcode_pix}'         => $waQrUrl,
            '{qrcode_base64}'      => $waQrBase64,
            '{qrcode_img}'         => $waQrImg,
            '{qrcode_instrucao}'   => $instruction,

            // Serviços e Pacotes
            '{nome_servico}'       => (string) ($c['nome_servico'] ?? $c['servico_principal'] ?? $c['package_name'] ?? ''),
            '{servico_principal}'  => (string) ($c['servico_principal'] ?? $c['nome_servico'] ?? $c['package_name'] ?? ''),
            '{servicos}'           => (string) ($c['servicos'] ?? ''),
            '{servicos_ativos}'    => (string) ($c['servicos_ativos'] ?? ''),
            '{servicos_suspensos}' => (string) ($c['servicos_suspensos'] ?? ''),
            '{status_servico}'     => (string) ($c['status_servico'] ?? ''),
            '{parcela_atual}'      => (string) ($c['parcela_atual'] ?? $c['installment_number'] ?? ''),
            '{parcelas_total}'     => (string) ($c['parcelas_total'] ?? $c['installments_count'] ?? ''),
            '{parcelas_pagas}'     => (string) ($c['parcelas_pagas'] ?? ''),
            '{parcelas_pendentes}' => (string) ($c['parcelas_pendentes'] ?? ''),
            '{resumo_parcelas}'    => (string) ($c['resumo_parcelas'] ?? ''),
            '{proximo_vencimento}' => $formattedNextDue ?: 'quitado',

            // Contratos
            '{contrato_numero}'    => (string) ($c['contrato_numero'] ?? $c['contract_number'] ?? ''),
            '{contrato_titulo}'    => (string) ($c['contrato_titulo'] ?? $c['contract_title'] ?? ''),
            '{link_contrato}'      => $contractUrl,
            '{link_assinatura}'    => $signatureUrl,
            '{data_assinatura}'    => $formattedSignedAt ?: now('America/Sao_Paulo')->format('d/m/Y H:i'),

            // Notificações e Avisos Gerais
            '{titulo}'             => (string) ($c['titulo'] ?? $c['title'] ?? ''),
            '{mensagem}'           => (string) ($c['mensagem'] ?? $c['message'] ?? ''),

            // Empresa e Suporte
            '{empresa}'            => (string) ($c['empresa'] ?? config('app.name')),
            '{whatsapp_suporte}'   => (string) ($c['whatsapp_suporte'] ?? ''),
            '{email_suporte}'      => (string) ($c['email_suporte'] ?? ''),
            '{telefone_suporte}'   => (string) ($c['telefone_suporte'] ?? ''),

            // Integrações WHMCS
            '{whmcs_cliente_id}'   => (string) ($c['whmcs_cliente_id'] ?? ''),
            '{whmcs_fatura_id}'    => (string) ($c['whmcs_fatura_id'] ?? ''),
        ];
    }
}
