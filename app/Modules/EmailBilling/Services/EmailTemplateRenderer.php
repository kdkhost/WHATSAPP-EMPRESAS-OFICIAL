<?php

namespace App\Modules\EmailBilling\Services;

use App\Modules\EmailBilling\Models\EmailTemplate;
use App\Modules\Settings\Services\SettingsManager;
use App\Support\Format;
use Illuminate\Support\Carbon;

class EmailTemplateRenderer
{
    public function __construct(
        protected SettingsManager $settings,
        protected EmailLayoutRenderer $layoutRenderer
    ) {}

    public function render(EmailTemplate $template, array $context): array
    {
        // Enriquecer contexto com Tenant e Branding
        if (empty($context['tenant']) && ! empty($template->tenant_id)) {
            $context['tenant_id'] = $template->tenant_id;
            try {
                $context['tenant'] = \App\Modules\Tenancy\Models\Tenant::find($template->tenant_id);
            } catch (\Throwable) {}
        }

        $branding = $this->layoutRenderer->brandingFromContext($context);
        $context['email_branding'] = $branding;
        $context['empresa'] = $context['empresa'] ?? $branding['brand_name'];
        $context['logo_empresa'] = $context['logo_empresa'] ?? $branding['logo_url'];

        $vars = $this->buildVariables($context);
        $htmlVars = $this->buildVariables($context, html: true);

        $bodyHtml = strtr($template->body_html, $htmlVars);
        $bodyHtml = $this->applyBrandingToHtml($bodyHtml, $branding);
        $bodyHtml = $this->normalizeContentHtml(
            mb_convert_encoding($bodyHtml, 'UTF-8', 'auto')
        );

        $hasOuterLayout = str_contains($bodyHtml, '<table role="presentation"') || str_contains($bodyHtml, '<!doctype html>') || str_contains($bodyHtml, '<body');
        $finalBodyHtml = $hasOuterLayout ? $bodyHtml : $this->layoutRenderer->wrap($bodyHtml, $branding);

        return [
            'subject' => mb_convert_encoding(strtr($template->subject, $vars), 'UTF-8', 'auto'),
            'body_html' => $finalBodyHtml,
            'body_text' => $template->body_text
                ? mb_convert_encoding(strtr($template->body_text, $vars), 'UTF-8', 'auto')
                : strip_tags($finalBodyHtml),
        ];
    }

    public function preview(string $subject, string $bodyHtml, array $sample = []): array
    {
        $branding = $this->layoutRenderer->brandingFromContext($sample);
        $sample['email_branding'] = $branding;
        $sample['empresa'] = $sample['empresa'] ?? $branding['brand_name'];
        $sample['logo_empresa'] = $sample['logo_empresa'] ?? $branding['logo_url'];

        $sample = array_merge([
            'nome' => 'Joao da Silva',
            'email' => 'cliente@exemplo.com',
            'valor' => 1234.56,
            'valor_original' => 1190.00,
            'valor_atualizado' => 1234.56,
            'vencimento' => now()->addDays(3)->toDateString(),
            'descricao' => 'Mensalidade do plano Pro - 05/2026',
            'link_pagamento' => 'https://pagar.exemplo.com/abc123',
            'boleto_url' => 'https://pagar.exemplo.com/boleto/abc123',
            'linha_digitavel' => '34191.79001 01043.510047 91020.150008 5 95870000123456',
            'pix_copia_cola' => '00020101021226880014br.gov.bcb.pix2566qrcode.exemplo.com/pix/abc12352040000530398654071234.565802BR5920EMPRESA EXEMPLO LTDA6009SAO PAULO62070503***6304ABCD',
            'qrcode_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=https%3A%2F%2Fpagar.exemplo.com%2Fabc123',
            'qrcode_instrucao' => 'Tambem enviamos o QR Code para facilitar o pagamento.',
            'numero_fatura' => 'INV-001234',
            'empresa' => $branding['brand_name'] ?: $this->settings->get('app.name', config('app.name')),
            'logo_empresa' => $branding['logo_url'] ?: $this->settings->get('app.logo', ''),
            'whatsapp_suporte' => '(11) 99999-0000',
            'email_suporte' => 'suporte@exemplo.com',
            'telefone_suporte' => '(11) 4002-8922',
            'data_cadastro' => now('America/Sao_Paulo')->toDateString(),
            'data_evento' => now('America/Sao_Paulo')->toDateString(),
            'data_contrato' => now('America/Sao_Paulo')->toDateString(),
            'numero_contrato' => 'CTR-2026-001',
            'titulo_contrato' => 'Contrato de prestacao de servicos',
            'link_contrato' => 'https://contratos.exemplo.com/abc123',
            'motivo' => 'pendencia financeira',
            'status_servico' => 'Ativo',
            'nome_servico' => 'Plano Pro',
            'servico_principal' => 'Plano Pro',
            'parcela_atual' => '2',
            'parcelas_total' => '6',
            'parcelas_pagas' => '1',
            'parcelas_pendentes' => '5',
            'resumo_parcelas' => 'Parcela 2/6 - 1 paga(s), 5 pendente(s)',
            'data_suspensao' => now('America/Sao_Paulo')->toDateString(),
            'data_cancelamento' => now('America/Sao_Paulo')->toDateString(),
            'link_reativacao' => 'https://app.exemplo.com/reativar',
            'cupom' => 'VOLTE10',
            'desconto' => '10%',
            'data_limite' => now('America/Sao_Paulo')->addDays(7)->toDateString(),
            'status_pagamento' => 'Confirmado',
            'data_pagamento' => now('America/Sao_Paulo')->toDateString(),
            'metodo_pagamento' => 'Pix',
            'id_transacao' => 'TX-ABC123',
        ], $sample);

        $vars = $this->buildVariables($sample);
        $htmlVars = $this->buildVariables($sample, html: true);
        $content = strtr($bodyHtml, $htmlVars);
        $content = $this->applyBrandingToHtml($content, $branding);
        $content = $this->normalizeContentHtml(
            mb_convert_encoding($content, 'UTF-8', 'auto')
        );

        $hasOuterLayout = str_contains($content, '<table role="presentation"') || str_contains($content, '<!doctype html>') || str_contains($content, '<body');
        $finalHtml = $hasOuterLayout ? $content : $this->layoutRenderer->wrap($content, $branding);

        return [
            'subject' => mb_convert_encoding(strtr($subject, $vars), 'UTF-8', 'auto'),
            'body_html' => $finalHtml,
        ];
    }

    public function applyBrandingToHtml(string $html, array $branding): string
    {
        $primary = $branding['primary_color'] ?? '#0f172a';
        $accent = $branding['accent_color'] ?? '#0d6efd';
        $brandName = $branding['brand_name'] ?? '';
        $logoUrl = $branding['logo_url'] ?? '';

        $logoHtml = EmailLayoutRenderer::logoHtml((string) $logoUrl, (string) $brandName);

        // 1. Substituição de variáveis diretas
        $html = str_replace(
            ['{cor_primaria}', '{primary_color}', '{cor_destaque}', '{accent_color}', '{cor_secundaria}', '{secondary_color}', '{logo_html}', '{logo_url}'],
            [$primary, $primary, $accent, $accent, $accent, $accent, $logoHtml, $logoUrl],
            $html
        );

        // 2. Substituição inteligente de estilos legados caso o tenant tenha cores customizadas
        if (! empty($primary) && strcasecmp($primary, '#0f172a') !== 0) {
            $html = preg_replace('/background:\s*#0f172a\b/i', 'background:' . $primary, $html);
            $html = preg_replace('/background:\s*#0d6efd\b/i', 'background:' . $primary, $html);
        }

        if (! empty($accent) && strcasecmp($accent, '#93c5fd') !== 0) {
            $html = preg_replace('/color:\s*#93c5fd\b/i', 'color:' . $accent, $html);
        }

        // 3. Injeção de logo no cabeçalho se o template ainda não a tiver
        if ($logoUrl !== '' && ! str_contains($html, $logoUrl)) {
            if (preg_match('/(<td[^>]*padding:\s*22px 26px[^>]*>)/i', $html, $matches)) {
                $html = str_replace($matches[1], $matches[1] . $logoHtml, $html);
            }
        }

        // 4. Padroniza logos legadas (img com a logo do tenant e tamanho pequeno)
        //    para o mesmo tamanho e alinhamento do bloco oficial.
        if ($logoUrl !== '') {
            $quoted = preg_quote(e($logoUrl), '/');
            $quotedRaw = preg_quote($logoUrl, '/');
            $html = preg_replace_callback(
                '/<img\b[^>]*src="(?:'.$quoted.'|'.$quotedRaw.')"[^>]*>/i',
                function ($m) use ($logoUrl, $brandName) {
                    return '<img src="'.e($logoUrl).'" alt="'.e($brandName).'" height="72" '
                        . 'style="display:inline-block;height:auto;max-height:72px;width:auto;max-width:220px;border:0;outline:none;text-decoration:none;object-fit:contain;vertical-align:middle;">';
                },
                $html
            ) ?? $html;
        }

        return $html;
    }

    protected function normalizeContentHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $hasBlockTags = (bool) preg_match('/<(p|div|table|ul|ol|li|h[1-6]|section|article|blockquote|br)\b/i', $html);

        if (! $hasBlockTags) {
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', trim($text));
            $text = preg_replace('/([\p{Ll}])([\p{Lu}])/u', "$1\n$2", $text);

            $breakBefore = [
                'Fatura', 'Valor', 'Transacao', 'Transação', 'Metodo', 'Método',
                'Observacao', 'Observação', 'Atenciosamente', 'Este email foi enviado',
                'Em caso de duvidas', 'Em caso de dúvidas', 'Seu pagamento',
            ];

            foreach ($breakBefore as $marker) {
                $text = preg_replace('/(?<!^)\s*(' . preg_quote($marker, '/') . ')/u', "\n$1", $text);
            }

            $text = preg_replace('/\b(Fatura|Valor|Transacao|Transação|Metodo|Método|Observacao|Observação)(?!\s*:)\s*/u', '$1: ', $text);

            $parts = array_filter(array_map('trim', preg_split('/\n+/', $text) ?: []));

            return collect($parts)
                ->map(fn (string $part) => '<p style="margin:0 0 16px;font-size:15px;line-height:1.75;color:#334155;">' . e($part) . '</p>')
                ->implode('');
        }

        return $html;
    }

    protected function buildVariables(array $c, bool $html = false): array
    {
        $name = (string) ($c['nome'] ?? $c['customer_name'] ?? '');
        $first = trim(explode(' ', $name)[0] ?? '');
        $amount = $c['valor'] ?? $c['amount'] ?? 0;
        $due = $c['vencimento'] ?? $c['due_date'] ?? null;
        $daysLate = 0;

        if ($due) {
            try {
                $dueDate = Carbon::parse($due)->setTimezone('America/Sao_Paulo')->startOfDay();
                $today = now('America/Sao_Paulo')->startOfDay();
                if ($dueDate->isBefore($today)) {
                    $daysLate = $dueDate->diffInDays($today);
                }
            } catch (\Throwable) {
            }
        }

        $qrUrl = (string) ($c['qrcode_url'] ?? $c['qrcode_pix'] ?? '');
        $pixCopy = (string) ($c['pix_copia_cola'] ?? '');
        $qrText = $qrUrl !== '' ? $qrUrl : $pixCopy;

        $branding = $c['email_branding'] ?? $this->layoutRenderer->brandingFromContext($c);
        $brandName = (string) ($branding['brand_name'] ?? $c['empresa'] ?? $this->settings->get('app.name', config('app.name')));
        $logoUrl = (string) ($branding['logo_url'] ?? $c['logo_empresa'] ?? $this->settings->get('app.logo', ''));
        $primaryColor = (string) ($branding['primary_color'] ?? '#0f172a');
        $accentColor = (string) ($branding['accent_color'] ?? '#0d6efd');
        $footerSig = (string) ($branding['footer_signature'] ?? $brandName);
        $footerTxt = (string) ($branding['footer_text'] ?? '');
        $footerSub = (string) ($branding['footer_subtext'] ?? '');

        $logoHtml = EmailLayoutRenderer::logoHtml($logoUrl, $brandName);

        return [
            '{nome}' => $name,
            '{primeiro_nome}' => $first,
            '{email}' => (string) ($c['email'] ?? ''),
            '{valor}' => Format::money($amount),
            '{valor_original}' => Format::money($c['valor_original'] ?? $amount),
            '{valor_atualizado}' => Format::money($c['valor_atualizado'] ?? $amount),
            '{vencimento}' => Format::date($due),
            '{dias_atraso}' => (string) $daysLate,
            '{link_pagamento}' => (string) ($c['link_pagamento'] ?? $c['payment_url'] ?? ''),
            '{boleto_url}' => (string) ($c['boleto_url'] ?? ''),
            '{linha_digitavel}' => (string) ($c['linha_digitavel'] ?? ''),
            '{pix_copia_cola}' => $pixCopy,
            '{qrcode_url}' => $qrUrl,
            '{qrcode_pix}' => $qrUrl,
            '{qrcode_pix_html}' => $html ? $this->qrCodeHtml($qrUrl, $pixCopy, (string) ($c['link_pagamento'] ?? $c['payment_url'] ?? ''), $accentColor) : $qrText,
            '{qrcode_instrucao}' => (string) ($c['qrcode_instrucao'] ?? ''),
            '{numero_fatura}' => (string) ($c['numero_fatura'] ?? $c['invoice_number'] ?? ''),
            '{descricao}' => (string) ($c['descricao'] ?? $c['description'] ?? ''),
            '{empresa}' => $brandName,
            '{brand_name}' => $brandName,
            '{logo_empresa}' => $logoUrl,
            '{logo_url}' => $logoUrl,
            '{logo_html}' => $html ? $logoHtml : '',
            '{cor_primaria}' => $primaryColor,
            '{primary_color}' => $primaryColor,
            '{cor_destaque}' => $accentColor,
            '{cor_secundaria}' => $accentColor,
            '{accent_color}' => $accentColor,
            '{secondary_color}' => $accentColor,
            '{footer_signature}' => $footerSig,
            '{footer_text}' => $footerTxt,
            '{footer_subtext}' => $footerSub,
            '{whatsapp_suporte}' => (string) ($c['whatsapp_suporte'] ?? ''),
            '{email_suporte}' => (string) ($c['email_suporte'] ?? ''),
            '{telefone_suporte}' => (string) ($c['telefone_suporte'] ?? $c['phone_support'] ?? ''),
            '{data_cadastro}' => Format::date($c['data_cadastro'] ?? $c['registered_at'] ?? null),
            '{data_evento}' => Format::date($c['data_evento'] ?? $c['event_date'] ?? null),
            '{data_contrato}' => Format::date($c['data_contrato'] ?? $c['contract_date'] ?? null),
            '{numero_contrato}' => (string) ($c['numero_contrato'] ?? $c['contract_number'] ?? ''),
            '{titulo_contrato}' => (string) ($c['titulo_contrato'] ?? $c['contract_title'] ?? ''),
            '{link_contrato}' => (string) ($c['link_contrato'] ?? $c['contract_url'] ?? ''),
            '{motivo}' => (string) ($c['motivo'] ?? $c['reason'] ?? ''),
            '{status_servico}' => (string) ($c['status_servico'] ?? $c['service_status'] ?? ''),
            '{nome_servico}' => (string) ($c['nome_servico'] ?? $c['service_name'] ?? $c['plan_name'] ?? ''),
            '{servicos}' => (string) ($c['servicos'] ?? ''),
            '{servicos_ativos}' => (string) ($c['servicos_ativos'] ?? ''),
            '{servicos_suspensos}' => (string) ($c['servicos_suspensos'] ?? ''),
            '{servico_principal}' => (string) ($c['servico_principal'] ?? ''),
            '{whmcs_cliente_id}' => (string) ($c['whmcs_cliente_id'] ?? ''),
            '{whmcs_fatura_id}' => (string) ($c['whmcs_fatura_id'] ?? ''),
            '{parcela_atual}' => (string) ($c['parcela_atual'] ?? ''),
            '{parcelas_total}' => (string) ($c['parcelas_total'] ?? ''),
            '{parcelas_pagas}' => (string) ($c['parcelas_pagas'] ?? ''),
            '{parcelas_pendentes}' => (string) ($c['parcelas_pendentes'] ?? ''),
            '{resumo_parcelas}' => (string) ($c['resumo_parcelas'] ?? ''),
            '{data_suspensao}' => Format::date($c['data_suspensao'] ?? $c['suspended_at'] ?? null),
            '{data_cancelamento}' => Format::date($c['data_cancelamento'] ?? $c['canceled_at'] ?? null),
            '{link_reativacao}' => (string) ($c['link_reativacao'] ?? $c['reactivation_url'] ?? ''),
            '{cupom}' => (string) ($c['cupom'] ?? $c['coupon'] ?? ''),
            '{desconto}' => (string) ($c['desconto'] ?? $c['discount'] ?? ''),
            '{data_limite}' => Format::date($c['data_limite'] ?? $c['deadline'] ?? null),
            '{status_pagamento}' => (string) ($c['status_pagamento'] ?? $c['payment_status'] ?? ''),
            '{data_pagamento}' => Format::date($c['data_pagamento'] ?? $c['paid_at'] ?? null),
            '{metodo_pagamento}' => (string) ($c['metodo_pagamento'] ?? $c['payment_method'] ?? ''),
            '{id_transacao}' => (string) ($c['id_transacao'] ?? $c['transaction_id'] ?? ''),
            '{ano_atual}' => (string) now('America/Sao_Paulo')->year,
        ];
    }

    protected function qrCodeHtml(string $qrUrl, string $pixCopy = '', string $payUrl = '', string $accent = '#2563eb'): string
    {
        if ($qrUrl === '' && $pixCopy === '') {
            return '';
        }

        $accent = preg_match('/^#[0-9a-f]{3,8}$/i', $accent) ? $accent : '#2563eb';

        $html = '<div style="margin:22px 0;padding:18px;border:1px solid #dbeafe;border-radius:16px;background:#eff6ff;text-align:center;">'
            . '<div style="font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:'.$accent.';font-weight:700;margin-bottom:10px;">Pagamento por QR Code</div>';

        if ($qrUrl !== '') {
            $html .= '<img src="'.e($qrUrl).'" alt="QR Code de pagamento" width="210" height="210" style="display:block;margin:0 auto 12px;border-radius:12px;background:#ffffff;border:1px solid #bfdbfe;padding:8px;">';
        }

        if ($pixCopy !== '') {
            $html .= '<div style="font-size:12px;line-height:1.55;color:#334155;margin-top:8px;">Pix copia e cola disponivel abaixo:</div>'
                . '<div style="margin-top:8px;padding:10px;border-radius:10px;background:#ffffff;border:1px solid #bfdbfe;color:#0f172a;font-size:11px;line-height:1.45;word-break:break-all;">'.e($pixCopy).'</div>';
        }

        // Contingência: alguns clientes (Gmail/Outlook) bloqueiam imagens do QR Code.
        if ($payUrl !== '' && preg_match('#^https?://#i', $payUrl)) {
            $html .= '<div style="margin-top:14px;"><a href="'.e($payUrl).'" target="_blank" '
                . 'style="display:inline-block;padding:11px 22px;border-radius:10px;background:'.$accent.';color:#ffffff;font-weight:700;font-size:14px;text-decoration:none;">Abrir fatura e pagar</a></div>'
                . '<div style="font-size:11px;color:#64748b;margin-top:8px;">Não está vendo o QR Code? Use o botão acima.</div>';
        }

        return $html.'</div>';
    }
}
