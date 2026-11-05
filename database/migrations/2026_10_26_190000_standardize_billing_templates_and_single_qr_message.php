<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            'vencimento-hoje' => [
                'name'            => 'Dia do vencimento',
                'category'        => 'cobranca',
                'schedule_offset' => '0d',
                'is_default'      => true,
                'content_html'    => '<p>Ola <strong>{primeiro_nome}</strong>.</p><p>Hoje e o vencimento da fatura <strong>{numero_fatura}</strong>.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p><strong>Pagamento seguro:</strong><br>{link_pagamento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Se ja pagou, pode desconsiderar esta mensagem. Obrigado!<br><strong>{empresa}</strong></p>',
                'content_text'    => "Ola {primeiro_nome}.\n\nHoje e o vencimento da fatura *{numero_fatura}*.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe ja pagou, pode desconsiderar esta mensagem. Obrigado!\n{empresa}",
            ],
            'lembrete-1d-antes' => [
                'name'            => 'Lembrete 1 dia antes',
                'category'        => 'lembrete',
                'schedule_offset' => '-1d',
                'is_default'      => true,
                'content_html'    => '<p>Ola <strong>{primeiro_nome}</strong>.</p><p>Sua fatura <strong>{numero_fatura}</strong> vence amanha (<strong>{vencimento}</strong>).</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p><strong>Pagamento seguro:</strong><br>{link_pagamento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p><strong>{empresa}</strong></p>',
                'content_text'    => "Ola {primeiro_nome}.\n\nSua fatura *{numero_fatura}* vence amanha ({vencimento}).\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\n{empresa}",
            ],
            'lembrete-3d-antes' => [
                'name'            => 'Lembrete 3 dias antes',
                'category'        => 'lembrete',
                'schedule_offset' => '-3d',
                'is_default'      => false,
                'content_html'    => '<p>Ola <strong>{primeiro_nome}</strong>, tudo bem?</p><p>Passando para lembrar que a fatura <strong>{numero_fatura}</strong> vence em <strong>{vencimento}</strong>.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p><strong>Pagamento seguro:</strong><br>{link_pagamento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Atenciosamente,<br><strong>{empresa}</strong></p>',
                'content_text'    => "Ola {primeiro_nome}, tudo bem?\n\nPassando para lembrar que a fatura *{numero_fatura}* vence em *{vencimento}*.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n{empresa}",
            ],
            'cobranca-gerada' => [
                'name'            => 'Nova Cobrança Gerada',
                'category'        => 'cobranca',
                'schedule_offset' => 'manual',
                'is_default'      => false,
                'content_html'    => '<p>Ola <strong>{primeiro_nome}</strong>, tudo bem?</p><p>Uma nova cobranca foi gerada para voce com vencimento em <strong>{vencimento}</strong>.</p><p><strong>Fatura:</strong> <strong>{numero_fatura}</strong><br><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p><strong>Pagamento seguro:</strong><br>{link_pagamento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Atenciosamente,<br><strong>{empresa}</strong></p>',
                'content_text'    => "Ola {primeiro_nome}, tudo bem?\n\nUma nova cobranca foi gerada para voce com vencimento em *{vencimento}*.\n\n*Fatura:* *{numero_fatura}*\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n{empresa}",
            ],
            'atraso-1d' => [
                'name'            => '1 dia em atraso',
                'category'        => 'atraso',
                'schedule_offset' => '+1d',
                'is_default'      => true,
                'content_html'    => '<p>Ola <strong>{primeiro_nome}</strong>.</p><p>Identificamos que a fatura <strong>{numero_fatura}</strong> venceu em <strong>{vencimento}</strong> e consta em aberto.</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p><strong>Pagamento seguro:</strong><br>{link_pagamento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Se precisar de ajuda, fale conosco.<br><strong>{empresa}</strong></p>',
                'content_text'    => "Ola {primeiro_nome}.\n\nIdentificamos que a fatura *{numero_fatura}* venceu em *{vencimento}* e consta em aberto.\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe precisar de ajuda, fale conosco.\n{empresa}",
            ],
            'atraso-7d' => [
                'name'            => '7 dias em atraso',
                'category'        => 'atraso',
                'schedule_offset' => '+7d',
                'is_default'      => false,
                'content_html'    => '<p>Ola <strong>{primeiro_nome}</strong>.</p><p>A fatura <strong>{numero_fatura}</strong> permanece em aberto ha <strong>{dias_atraso} dia(s)</strong> (venceu em {vencimento}).</p><p><strong>Servico:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p><strong>Pagamento seguro:</strong><br>{link_pagamento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Estamos a disposicao.<br><strong>{empresa}</strong></p>',
                'content_text'    => "Ola {primeiro_nome}.\n\nA fatura *{numero_fatura}* permanece em aberto ha *{dias_atraso} dia(s)* (venceu em {vencimento}).\n\n*Servico:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nEstamos a disposicao.\n{empresa}",
            ],
        ];

        foreach ($templates as $slug => $data) {
            $exists = DB::table('whatsapp_templates')
                ->whereNull('tenant_id')
                ->where('slug', $slug)
                ->first();

            if ($exists) {
                DB::table('whatsapp_templates')
                    ->where('id', $exists->id)
                    ->update(array_merge($data, [
                        'active'     => true,
                        'updated_at' => now(),
                    ]));
            } else {
                DB::table('whatsapp_templates')->insert(array_merge($data, [
                    'slug'       => $slug,
                    'tenant_id'  => null,
                    'scope'      => 'system',
                    'is_system'  => true,
                    'active'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // Garante que boleto-fatura fique categorizado como boleto e não como cobranca padrão
        DB::table('whatsapp_templates')
            ->where('slug', 'boleto-fatura')
            ->update([
                'category'   => 'boleto',
                'is_default' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Migração progressiva idempotente
    }
};
