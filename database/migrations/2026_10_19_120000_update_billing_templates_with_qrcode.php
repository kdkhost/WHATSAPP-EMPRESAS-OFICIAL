<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return;
        }

        $templates = [
            'lembrete-3d-antes' => [
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>, tudo bem?</p><p>Passando para lembrar, com antecedência, que a sua fatura <strong>{numero_fatura}</strong> vence em <strong>{vencimento}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o link de pagamento:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Atenciosamente,<br><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*, tudo bem?\n\nPassando para lembrar, com antecedência, que a sua fatura *{numero_fatura}* vence em *{vencimento}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o link de pagamento:*\n{link_pagamento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n*{empresa}*",
            ],
            'lembrete-1d-antes' => [
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Sua fatura <strong>{numero_fatura}</strong> vence amanhã. Para manter seus serviços sem interrupções, recomendamos concluir o pagamento até <strong>{vencimento}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o pagamento seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nSua fatura *{numero_fatura}* vence amanhã. Para manter seus serviços sem interrupções, recomendamos concluir o pagamento até *{vencimento}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o pagamento seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\n*{empresa}*",
            ],
            'vencimento-hoje' => [
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Hoje é o vencimento da sua fatura <strong>{numero_fatura}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Pagamento seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se já realizou o pagamento, desconsidere este aviso. Obrigado!<br><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nHoje é o vencimento da sua fatura *{numero_fatura}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe já realizou o pagamento, desconsidere este aviso. Obrigado!\n*{empresa}*",
            ],
            'atraso-1d' => [
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Identificamos que a sua fatura <strong>{numero_fatura}</strong> venceu em <strong>{vencimento}</strong> e ainda consta pendente.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor atualizado:</strong> {valor_atualizado}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Regularize pelo link seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se precisar de ajuda ou negociar, fale conosco: {whatsapp_suporte}<br><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nIdentificamos que a sua fatura *{numero_fatura}* venceu em *{vencimento}* e ainda consta pendente.\n\n*Serviço:* {servico_principal}\n*Valor atualizado:* {valor_atualizado}\n*Parcelas:* {resumo_parcelas}\n\n*Regularize pelo link seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe precisar de ajuda ou negociar, fale conosco: {whatsapp_suporte}\n*{empresa}*",
            ],
            'atraso-3d' => [
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Constatamos que a sua fatura <strong>{numero_fatura}</strong> está com <strong>{dias_atraso} dias de atraso</strong> (vencida em {vencimento}).</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor atualizado:</strong> {valor_atualizado}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Evite restrições no serviço quitando pelo link:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Dúvidas? Estamos em {whatsapp_suporte}<br><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nConstatamos que a sua fatura *{numero_fatura}* está com *{dias_atraso} dias de atraso* (vencida em {vencimento}).\n\n*Serviço:* {servico_principal}\n*Valor atualizado:* {valor_atualizado}\n*Parcelas:* {resumo_parcelas}\n\nEvite restrições no serviço quitando pelo link:\n{link_pagamento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nDúvidas? Estamos em {whatsapp_suporte}\n*{empresa}*",
            ],
            'atraso-7d' => [
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>A fatura <strong>{numero_fatura}</strong> permanece em aberto há <strong>{dias_atraso} dias</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor_atualizado}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Para evitar suspensão do serviço, regularize pelo link:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Estamos à disposição em {whatsapp_suporte}.<br><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nA fatura *{numero_fatura}* permanece em aberto há *{dias_atraso} dias*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor_atualizado}\n*Parcelas:* {resumo_parcelas}\n\nPara evitar suspensão do serviço, regularize pelo link:\n{link_pagamento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nEstamos à disposição em {whatsapp_suporte}.\n*{empresa}*",
            ],
            'pix-copia-cola' => [
                'content_html' => '<p><strong>Cobrança PIX Gerada</strong> 📱</p><p>Olá, <strong>{primeiro_nome}</strong>!</p><p><strong>Fatura:</strong> #{numero_fatura}<br><strong>Serviço:</strong> {nome_servico}<br><strong>Valor:</strong> {valor}<br><strong>Vencimento:</strong> {vencimento}</p><p>{qrcode_instrucao}</p>{qrcode_img}<p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Link de pagamento: {link_pagamento}</p><p>Após o pagamento a baixa é efetuada automaticamente.<br><strong>{empresa}</strong></p>',
                'content_text' => "*Cobrança PIX Gerada* 📱\n\nOlá, *{primeiro_nome}*!\n\n*Fatura:* #{numero_fatura}\n*Serviço:* {nome_servico}\n*Valor:* {valor}\n*Vencimento:* {vencimento}\n\n{qrcode_instrucao}\n📱 *[QR Code Pix enviado em anexo]*\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\n*Link de pagamento:* {link_pagamento}\n\nApós o pagamento a baixa é efetuada automaticamente.\n*{empresa}*",
            ],
        ];

        foreach ($templates as $slug => $data) {
            DB::table('whatsapp_templates')
                ->where('slug', $slug)
                ->whereNull('tenant_id')
                ->update([
                    'content_html' => $data['content_html'],
                    'content_text' => $data['content_text'],
                    'updated_at'   => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Migração idempotente
    }
};
