<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeWhatsAppTemplates();
        $this->upgradeEmailTemplates();
    }

    public function down(): void
    {
        // Migration intentionally forward-only: it only improves default message templates.
    }

    private function upgradeWhatsAppTemplates(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return;
        }

        foreach ($this->whatsAppTemplates() as $slug => $data) {
            DB::table('whatsapp_templates')
                ->where('slug', $slug)
                ->update($data + ['updated_at' => now()]);
        }
    }

    private function upgradeEmailTemplates(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $query = DB::table('email_templates')
            ->whereIn('category', ['cobranca', 'lembrete', 'atraso'])
            ->where('body_html', 'not like', '%{qrcode_pix_html}%');

        if (Schema::hasColumn('email_templates', 'is_system')) {
            $query->where(function ($builder) {
                $builder->where('is_system', true)->orWhere('locked', true);
            });
        }

        $query->orderBy('id')->chunkById(100, function ($templates) {
            foreach ($templates as $template) {
                $body = (string) $template->body_html;

                if (! str_contains($body, '{servico_principal}')) {
                    $rows = '<tr>'
                        . '<td style="padding:10px 0;color:#64748b;font-size:13px;border-bottom:1px solid #e5e7eb;">Servico</td>'
                        . '<td style="padding:10px 0;color:#0f172a;font-size:13px;font-weight:700;text-align:right;border-bottom:1px solid #e5e7eb;">{servico_principal}</td>'
                        . '</tr>'
                        . '<tr>'
                        . '<td style="padding:10px 0;color:#64748b;font-size:13px;border-bottom:1px solid #e5e7eb;">Parcelas</td>'
                        . '<td style="padding:10px 0;color:#0f172a;font-size:13px;font-weight:700;text-align:right;border-bottom:1px solid #e5e7eb;">{resumo_parcelas}</td>'
                        . '</tr>';

                    $body = preg_replace('/<\/table>/i', $rows.'</table>', $body, 1) ?: $body;
                }

                $body = str_replace(
                    '<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#334155;">',
                    '{qrcode_pix_html}<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#334155;">',
                    $body
                );

                DB::table('email_templates')->where('id', $template->id)->update([
                    'body_html' => $body,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function whatsAppTemplates(): array
    {
        return [
            'lembrete-3d-antes' => [
                'content_html' => '<p>Ola {primeiro_nome}, tudo bem?</p><p>Passando para lembrar, com antecedencia, que a fatura <strong>{numero_fatura}</strong> vence em <strong>{vencimento}</strong>.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Link de pagamento: {link_pagamento}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Atenciosamente,<br>{empresa}</p>',
                'content_text' => "Ola {primeiro_nome}, tudo bem?\n\nPassando para lembrar, com antecedencia, que a fatura *{numero_fatura}* vence em *{vencimento}*.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Link de pagamento:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n{empresa}",
            ],
            'lembrete-1d-antes' => [
                'content_html' => '<p>Ola {primeiro_nome}.</p><p>Sua fatura <strong>{numero_fatura}</strong> vence amanha. Para manter o servico sem interrupcoes, recomendamos concluir o pagamento ate <strong>{vencimento}</strong>.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o pagamento: {link_pagamento}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>{empresa}</p>',
                'content_text' => "Ola {primeiro_nome}.\n\nSua fatura *{numero_fatura}* vence amanha. Para manter o servico sem interrupcoes, recomendamos concluir o pagamento ate *{vencimento}*.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o pagamento:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\n{empresa}",
            ],
            'vencimento-hoje' => [
                'content_html' => '<p>Ola {primeiro_nome}.</p><p>Hoje e o vencimento da fatura <strong>{numero_fatura}</strong>.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Pagamento seguro: {link_pagamento}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se ja pagou, pode desconsiderar esta mensagem. Obrigado!<br>{empresa}</p>',
                'content_text' => "Ola {primeiro_nome}.\n\nHoje e o vencimento da fatura *{numero_fatura}*.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe ja pagou, pode desconsiderar esta mensagem. Obrigado!\n{empresa}",
            ],
            'atraso-1d' => [
                'content_html' => '<p>Ola {primeiro_nome}.</p><p>Identificamos que a fatura <strong>{numero_fatura}</strong> venceu em <strong>{vencimento}</strong> e ainda consta em aberto.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Regularize por aqui: {link_pagamento}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se precisar de ajuda, fale conosco: {whatsapp_suporte}</p>',
                'content_text' => "Ola {primeiro_nome}.\n\nIdentificamos que a fatura *{numero_fatura}* venceu em *{vencimento}* e ainda consta em aberto.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Regularize por aqui:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe precisar de ajuda, fale conosco: {whatsapp_suporte}",
            ],
            'atraso-7d' => [
                'content_html' => '<p>Ola {primeiro_nome}.</p><p>A fatura <strong>{numero_fatura}</strong> permanece em aberto ha <strong>{dias_atraso} dia(s)</strong>.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Para evitar restricoes no servico, regularize pelo link: {link_pagamento}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Estamos a disposicao em {whatsapp_suporte}.<br>{empresa}</p>',
                'content_text' => "Ola {primeiro_nome}.\n\nA fatura *{numero_fatura}* permanece em aberto ha *{dias_atraso} dia(s)*.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\nPara evitar restricoes no servico, regularize pelo link:\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nEstamos a disposicao em {whatsapp_suporte}.\n{empresa}",
            ],
        ];
    }
};
