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

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            // Garante tamanho adequado da coluna category para acomodar novas categorias
            if (Schema::hasColumn('whatsapp_templates', 'category')) {
                $table->string('category', 60)->default('cobranca')->change();
            }
            if (! Schema::hasColumn('whatsapp_templates', 'scope')) {
                $table->string('scope', 20)->default('system');
            }
            if (! Schema::hasColumn('whatsapp_templates', 'is_system')) {
                $table->boolean('is_system')->default(true);
            }
            if (! Schema::hasColumn('whatsapp_templates', 'is_default')) {
                $table->boolean('is_default')->default(true);
            }
            if (! Schema::hasColumn('whatsapp_templates', 'locked')) {
                $table->boolean('locked')->default(false);
            }
        });

        foreach ($this->standardTemplates() as $template) {
            $slug = $template['slug'];
            $exists = DB::table('whatsapp_templates')->where('slug', $slug)->first();

            $data = array_merge($template, [
                'scope'      => 'system',
                'is_system'  => true,
                'is_default' => true,
                'active'     => true,
                'updated_at' => now(),
            ]);

            if ($exists) {
                // Preserva edições personalizadas caso o usuário já tenha customizado, mas garante colunas de sistema
                DB::table('whatsapp_templates')->where('id', $exists->id)->update([
                    'category'        => $data['category'],
                    'schedule_offset' => $data['schedule_offset'],
                    'scope'           => 'system',
                    'is_system'       => true,
                    'is_default'      => true,
                    'active'          => true,
                    'updated_at'      => now(),
                ]);
            } else {
                DB::table('whatsapp_templates')->insert(array_merge($data, [
                    'created_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Migração idempotente e progressiva.
    }

    private function standardTemplates(): array
    {
        return [
            [
                'name'            => 'Lembrete 3 dias antes',
                'slug'            => 'lembrete-3d-antes',
                'category'        => 'lembrete',
                'schedule_offset' => '-3d',
                'content_html'    => '<p>Olá <strong>{primeiro_nome}</strong>, tudo bem?</p><p>Passando para lembrar, com antecedência, que a sua fatura <strong>{numero_fatura}</strong> vence em <strong>{vencimento}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o link de pagamento:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Atenciosamente,<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{primeiro_nome}*, tudo bem?\n\nPassando para lembrar, com antecedência, que a sua fatura *{numero_fatura}* vence em *{vencimento}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o link de pagamento:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n*{empresa}*",
            ],
            [
                'name'            => 'Lembrete 1 dia antes',
                'slug'            => 'lembrete-1d-antes',
                'category'        => 'lembrete',
                'schedule_offset' => '-1d',
                'content_html'    => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Sua fatura <strong>{numero_fatura}</strong> vence amanhã. Para manter seus serviços sem interrupções, recomendamos concluir o pagamento até <strong>{vencimento}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o pagamento seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{primeiro_nome}*.\n\nSua fatura *{numero_fatura}* vence amanhã. Para manter seus serviços sem interrupções, recomendamos concluir o pagamento até *{vencimento}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o pagamento seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\n*{empresa}*",
            ],
            [
                'name'            => 'Dia do vencimento',
                'slug'            => 'vencimento-hoje',
                'category'        => 'cobranca',
                'schedule_offset' => '0d',
                'content_html'    => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Hoje é o vencimento da sua fatura <strong>{numero_fatura}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Pagamento seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se já realizou o pagamento, desconsidere este aviso. Obrigado!<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{primeiro_nome}*.\n\nHoje é o vencimento da sua fatura *{numero_fatura}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe já realizou o pagamento, desconsidere este aviso. Obrigado!\n*{empresa}*",
            ],
            [
                'name'            => '1 dia em atraso',
                'slug'            => 'atraso-1d',
                'category'        => 'atraso',
                'schedule_offset' => '+1d',
                'content_html'    => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Identificamos que a sua fatura <strong>{numero_fatura}</strong> venceu em <strong>{vencimento}</strong> e ainda consta pendente.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor atualizado:</strong> {valor_atualizado}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Regularize pelo link seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se precisar de ajuda ou negociar, fale conosco: {whatsapp_suporte}<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{primeiro_nome}*.\n\nIdentificamos que a sua fatura *{numero_fatura}* venceu em *{vencimento}* e ainda consta pendente.\n\n*Serviço:* {servico_principal}\n*Valor atualizado:* {valor_atualizado}\n*Parcelas:* {resumo_parcelas}\n\n*Regularize pelo link seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe precisar de ajuda ou negociar, fale conosco: {whatsapp_suporte}\n*{empresa}*",
            ],
            [
                'name'            => '3 dias em atraso',
                'slug'            => 'atraso-3d',
                'category'        => 'atraso',
                'schedule_offset' => '+3d',
                'content_html'    => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Constatamos que a sua fatura <strong>{numero_fatura}</strong> está com <strong>{dias_atraso} dias de atraso</strong> (vencida em {vencimento}).</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor atualizado:</strong> {valor_atualizado}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Evite restrições no serviço quitando pelo link:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Dúvidas? Estamos em {whatsapp_suporte}<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{primeiro_nome}*.\n\nConstatamos que a sua fatura *{numero_fatura}* está com *{dias_atraso} dias de atraso* (vencida em {vencimento}).\n\n*Serviço:* {servico_principal}\n*Valor atualizado:* {valor_atualizado}\n*Parcelas:* {resumo_parcelas}\n\nEvite restrições no serviço quitando pelo link:\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nDúvidas? Estamos em {whatsapp_suporte}\n*{empresa}*",
            ],
            [
                'name'            => '7 dias em atraso',
                'slug'            => 'atraso-7d',
                'category'        => 'atraso',
                'schedule_offset' => '+7d',
                'content_html'    => '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>A fatura <strong>{numero_fatura}</strong> permanece em aberto há <strong>{dias_atraso} dias</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor_atualizado}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Para evitar suspensão do serviço, regularize pelo link:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Estamos à disposição em {whatsapp_suporte}.<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{primeiro_nome}*.\n\nA fatura *{numero_fatura}* permanece em aberto há *{dias_atraso} dias*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor_atualizado}\n*Parcelas:* {resumo_parcelas}\n\nPara evitar suspensão do serviço, regularize pelo link:\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nEstamos à disposição em {whatsapp_suporte}.\n*{empresa}*",
            ],
            [
                'name'            => '15 dias em atraso',
                'slug'            => 'atraso-15d',
                'category'        => 'atraso',
                'schedule_offset' => '+15d',
                'content_html'    => '<p>Aviso importante, <strong>{primeiro_nome}</strong>.</p><p>Sua fatura <strong>{numero_fatura}</strong> está vencida há <strong>{dias_atraso} dias</strong> e o serviço vinculado está sujeito a bloqueio definitivo.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor total:</strong> {valor_atualizado}</p><p>Regularize imediatamente pelo link:<br>{link_pagamento}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Central de atendimento: {whatsapp_suporte}<br><strong>{empresa}</strong></p>',
                'content_text'    => "Aviso importante, *{primeiro_nome}*.\n\nSua fatura *{numero_fatura}* está vencida há *{dias_atraso} dias* e o serviço vinculado está sujeito a bloqueio definitivo.\n\n*Serviço:* {servico_principal}\n*Valor total:* {valor_atualizado}\n\n*Regularize imediatamente pelo link:*\n{link_pagamento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nCentral de atendimento: {whatsapp_suporte}\n*{empresa}*",
            ],
            [
                'name'            => 'Confirmação de Pagamento',
                'slug'            => 'pagamento-confirmado',
                'category'        => 'agradecimento',
                'schedule_offset' => null,
                'content_html'    => '<p>Olá <strong>{nome}</strong>! 👋</p><p>Recebemos a confirmação do seu pagamento com sucesso!</p><p><strong>Fatura:</strong> #{numero_fatura}<br><strong>Valor recebido:</strong> {valor}<br><strong>Forma:</strong> {forma_pagamento}<br><strong>Data:</strong> {data_pagamento}</p><p>Agradecemos pela preferência e confiança! Se precisar de suporte, estamos à disposição. 😊</p><p><strong>{empresa}</strong></p>',
                'content_text'    => "Olá *{nome}*! 👋\n\nRecebemos a confirmação do seu pagamento com sucesso!\n\n*Fatura:* #{numero_fatura}\n*Valor recebido:* {valor}\n*Forma:* {forma_pagamento}\n*Data:* {data_pagamento}\n\nAgradecemos pela preferência e confiança! Se precisar de suporte, estamos à disposição. 😊\n\n*{empresa}*",
            ],
            [
                'name'            => 'PIX Copia e Cola Avulso',
                'slug'            => 'pix-copia-cola',
                'category'        => 'pix',
                'schedule_offset' => null,
                'content_html'    => '<p><strong>Cobrança PIX Gerada</strong> 📱</p><p>Olá, <strong>{primeiro_nome}</strong>!</p><p><strong>Fatura:</strong> #{numero_fatura}<br><strong>Serviço:</strong> {nome_servico}<br><strong>Valor:</strong> {valor}<br><strong>Vencimento:</strong> {vencimento}</p><p><strong>Pix Copia e Cola:</strong><br>```{pix_copia_cola}```</p><p>Link de pagamento: {link_pagamento}</p><p>Após o pagamento a baixa é efetuada automaticamente.<br><strong>{empresa}</strong></p>',
                'content_text'    => "*Cobrança PIX Gerada* 📱\n\nOlá, *{primeiro_nome}*!\n\n*Fatura:* #{numero_fatura}\n*Serviço:* {nome_servico}\n*Valor:* {valor}\n*Vencimento:* {vencimento}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\n*Link de pagamento:* {link_pagamento}\n\nApós o pagamento a baixa é efetuada automaticamente.\n*{empresa}*",
            ],
            [
                'name'            => 'Envio de Boleto Bancário',
                'slug'            => 'boleto-fatura',
                'category'        => 'cobranca',
                'schedule_offset' => null,
                'content_html'    => '<p>Olá, <strong>{primeiro_nome}</strong>!</p><p>Segue o boleto da sua fatura <strong>#{numero_fatura}</strong> no valor de <strong>{valor}</strong> com vencimento em <strong>{vencimento}</strong>.</p><p><strong>Linha Digitável:</strong><br>```{linha_digitavel}```</p><p><strong>Link da 2ª via:</strong> {boleto_url}</p><p><strong>{empresa}</strong></p>',
                'content_text'    => "Olá, *{primeiro_nome}*!\n\nSegue o boleto da sua fatura *#{numero_fatura}* no valor de *{valor}* com vencimento em *{vencimento}*.\n\n*Linha Digitável:*\n```{linha_digitavel}```\n\n*Link da 2ª via:* {boleto_url}\n\n*{empresa}*",
            ],
            [
                'name'            => 'Contrato para Assinatura',
                'slug'            => 'contrato-assinatura',
                'category'        => 'contrato',
                'schedule_offset' => null,
                'content_html'    => '<p><strong>Contrato para Assinatura Eletrônica</strong> 📑</p><p>Olá, <strong>{nome}</strong>!</p><p>Você recebeu o documento para leitura e assinatura eletrônica:<br><strong>Contrato:</strong> {contrato_titulo}<br><strong>Número:</strong> #{contrato_numero}</p><p>Acesse o link seguro para assinar:<br>{link_assinatura}</p><p>Este link é seguro e pessoal.<br><strong>{empresa}</strong></p>',
                'content_text'    => "*Contrato para Assinatura Eletrônica* 📑\n\nOlá, *{nome}*!\n\nVocê recebeu o documento para leitura e assinatura eletrônica:\n*Contrato:* {contrato_titulo}\n*Número:* #{contrato_numero}\n\n*Acesse o link seguro para assinar:*\n{link_assinatura}\n\nEste link é seguro e pessoal.\n*{empresa}*",
            ],
            [
                'name'            => 'Contrato Assinado com Sucesso',
                'slug'            => 'contrato-assinado',
                'category'        => 'contrato_assinado',
                'schedule_offset' => null,
                'content_html'    => '<p><strong>Contrato Assinado com Sucesso!</strong> ✅</p><p>Olá, <strong>{nome}</strong>!</p><p>Confirmamos a conclusão da assinatura do documento <strong>{contrato_titulo}</strong> (Nº #{contrato_numero}) em <strong>{data_assinatura}</strong>.</p><p>Baixe sua via assinada no link abaixo:<br>{link_contrato}</p><p>Guarde este documento com você.<br><strong>{empresa}</strong></p>',
                'content_text'    => "*Contrato Assinado com Sucesso!* ✅\n\nOlá, *{nome}*!\n\nConfirmamos a conclusão da assinatura do documento *{contrato_titulo}* (Nº #{contrato_numero}) em *{data_assinatura}*.\n\n*Baixe sua via assinada no link abaixo:*\n{link_contrato}\n\nGuarde este documento com você.\n*{empresa}*",
            ],
            [
                'name'            => 'Cancelamento de Pacote',
                'slug'            => 'pacote-cancelamento',
                'category'        => 'pacote_cancelamento',
                'schedule_offset' => null,
                'content_html'    => '<p>Olá, <strong>{nome}</strong>!</p><p>Informamos que a contratação do serviço <strong>{nome_servico}</strong> foi cancelada.</p><p><strong>Valor:</strong> {valor}<br><strong>Status:</strong> Cancelado</p><p>Em caso de dúvidas ou para reativar seu serviço, fale conosco em {whatsapp_suporte}.<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá, *{nome}*!\n\nInformamos que a contratação do serviço *{nome_servico}* foi cancelada.\n\n*Valor:* {valor}\n*Status:* Cancelado\n\nEm caso de dúvidas ou para reativar seu serviço, fale conosco em {whatsapp_suporte}.\n*{empresa}*",
            ],
            [
                'name'            => 'Confirmação de Parcela de Pacote',
                'slug'            => 'pacote-pagamento-confirmado',
                'category'        => 'pacote_pagamento',
                'schedule_offset' => null,
                'content_html'    => '<p><strong>Pagamento Confirmado!</strong> 🎉</p><p>Olá, <strong>{nome}</strong>!</p><p>Identificamos o pagamento da sua parcela com sucesso:<br><strong>Serviço:</strong> {nome_servico}<br><strong>Parcela:</strong> {resumo_parcelas}<br><strong>Valor:</strong> {valor}<br><strong>Próximo vencimento:</strong> {proximo_vencimento}</p><p>Agradecemos pela preferência!<br><strong>{empresa}</strong></p>',
                'content_text'    => "*Pagamento Confirmado!* 🎉\n\nOlá, *{nome}*!\n\nIdentificamos o pagamento da sua parcela com sucesso:\n*Serviço:* {nome_servico}\n*Parcela:* {resumo_parcelas}\n*Valor:* {valor}\n*Próximo vencimento:* {proximo_vencimento}\n\nAgradecemos pela preferência!\n*{empresa}*",
            ],
            [
                'name'            => 'Comunicado do Sistema',
                'slug'            => 'notificacao-sistema',
                'category'        => 'notificacao',
                'schedule_offset' => null,
                'content_html'    => '<p><strong>Comunicado Importante</strong> 🔔</p><p>Olá, <strong>{primeiro_nome}</strong>!</p><p><strong>{titulo}</strong></p><p>{mensagem}</p><p>Estamos à disposição em {whatsapp_suporte}.<br><strong>{empresa}</strong></p>',
                'content_text'    => "*Comunicado Importante* 🔔\n\nOlá, *{primeiro_nome}*!\n\n*{titulo}*\n\n{mensagem}\n\nEstamos à disposição em {whatsapp_suporte}.\n*{empresa}*",
            ],
            [
                'name'            => 'Mensagem Avulsa Padrão',
                'slug'            => 'envio-manual-padrao',
                'category'        => 'manual',
                'schedule_offset' => null,
                'content_html'    => '<p>Olá, <strong>{primeiro_nome}</strong>! Tudo bem?</p><p>{mensagem}</p><p>Estamos à sua disposição.<br>Atenciosamente,<br><strong>{empresa}</strong></p>',
                'content_text'    => "Olá, *{primeiro_nome}*! Tudo bem?\n\n{mensagem}\n\nEstamos à sua disposição.\nAtenciosamente,\n*{empresa}*",
            ],
            [
                'name'            => 'Boas-vindas ao Cliente',
                'slug'            => 'boas-vindas',
                'category'        => 'boas_vindas',
                'schedule_offset' => null,
                'content_html'    => '<p>Seja muito bem-vindo(a), <strong>{primeiro_nome}</strong>! 🚀</p><p>É uma satisfação ter você como cliente na <strong>{empresa}</strong>.</p><p>Seu cadastro foi concluído com sucesso. Sempre que precisar de suporte ou tirar dúvidas, pode nos chamar diretamente por este WhatsApp ou pelo e-mail {email_suporte}.</p><p>Conte conosco!</p>',
                'content_text'    => "Seja muito bem-vindo(a), *{primeiro_nome}*! 🚀\n\nÉ uma satisfação ter você como cliente na *{empresa}*.\n\nSeu cadastro foi concluído com sucesso. Sempre que precisar de suporte ou tirar dúvidas, pode nos chamar diretamente por este WhatsApp ou pelo e-mail {email_suporte}.\n\nConte conosco!",
            ],
            [
                'name'            => 'Suporte e Atendimento',
                'slug'            => 'suporte-atendimento',
                'category'        => 'suporte',
                'schedule_offset' => null,
                'content_html'    => '<p>Olá, <strong>{primeiro_nome}</strong>!</p><p>Recebemos o seu contato no suporte.</p><p>{mensagem}</p><p>Basta responder a esta mensagem se precisar de esclarecimentos adicionais.<br>Equipe de Atendimento <strong>{empresa}</strong></p>',
                'content_text'    => "Olá, *{primeiro_nome}*!\n\nRecebemos o seu contato no suporte.\n\n{mensagem}\n\nBasta responder a esta mensagem se precisar de esclarecimentos adicionais.\nEquipe de Atendimento *{empresa}*",
            ],
        ];
    }
};
