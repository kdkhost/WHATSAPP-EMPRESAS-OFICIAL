<?php

namespace App\Modules\EmailBilling\Services;

use App\Modules\EmailBilling\Models\EmailTemplate;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class EmailTemplateDefaults
{
    public function ensureForTenant(Tenant $tenant): void
    {
        foreach ($this->defaults() as $slug => $tpl) {
            EmailTemplate::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                $tpl + ['tenant_id' => $tenant->id, 'slug' => $slug]
            );
        }
    }

    public function defaults(): array
    {
        return [
            'cobranca-3-dias-antes' => $this->make(
                'Cobrança - 3 dias antes',
                'cobranca',
                '-3d',
                'Lembrete: fatura {numero_fatura} vence em breve',
                'Lembrete de vencimento',
                'Sua fatura vence em breve',
                'Olá {primeiro_nome}, tudo bem? Passando para lembrar que a fatura {numero_fatura}, no valor de {valor}, vence em {vencimento}.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Vencimento' => '{vencimento}'],
                'Pagar agora',
                '{link_pagamento}',
                'Se o pagamento já foi realizado, desconsidere este aviso.'
            ),
            'cobranca-1-dia-antes' => $this->make(
                'Cobrança - 1 dia antes',
                'lembrete',
                '-1d',
                'Lembrete importante: vencimento amanhã ({numero_fatura})',
                'Lembrete importante',
                'Sua fatura vence amanhã',
                'Olá {primeiro_nome}, este é um lembrete de que sua fatura {numero_fatura}, no valor de {valor}, vence amanhã, em {vencimento}.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Vencimento' => '{vencimento}'],
                'Acessar pagamento',
                '{link_pagamento}',
                'Qualquer dúvida, responda este email ou fale com nosso suporte.'
            ),
            'cobranca-no-vencimento' => $this->make(
                'Cobrança - no vencimento',
                'cobranca',
                '0d',
                'Vence hoje: fatura {numero_fatura}',
                'Vencimento hoje',
                'Sua fatura vence hoje',
                'Olá {primeiro_nome}, sua fatura {numero_fatura} vence hoje. Para manter seu serviço sem interrupções, recomendamos concluir o pagamento ainda hoje.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Vencimento' => '{vencimento}'],
                'Pagar fatura',
                '{link_pagamento}',
                'Após a confirmação, não é necessário enviar comprovante.'
            ),
            'cobranca-1-dia-atraso' => $this->make(
                'Cobrança - 1 dia de atraso',
                'atraso',
                '+1d',
                'Aviso de atraso: fatura {numero_fatura}',
                'Pagamento em atraso',
                'Identificamos uma fatura em aberto',
                'Olá {primeiro_nome}, identificamos que a fatura {numero_fatura}, no valor de {valor}, está em aberto há {dias_atraso} dia(s).',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Dias em atraso' => '{dias_atraso}'],
                'Regularizar agora',
                '{link_pagamento}',
                'Se você já realizou o pagamento, aguarde a compensação ou entre em contato conosco.'
            ),
            'cobranca-3-dias-atraso' => $this->make(
                'Cobrança - 3 dias de atraso',
                'atraso',
                '+3d',
                'Pendência financeira: {numero_fatura}',
                'Pendência financeira',
                'Precisamos da sua atenção',
                'Olá {primeiro_nome}, a fatura {numero_fatura} segue em aberto. Para evitar restrições de serviço, regularize a pendência o quanto antes.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Vencimento' => '{vencimento}'],
                'Regularizar pendência',
                '{link_pagamento}',
                'Nossa equipe está disponível para ajudar caso você tenha alguma dúvida.'
            ),
            'cobranca-7-dias-atraso' => $this->make(
                'Cobrança - 7 dias de atraso',
                'atraso',
                '+7d',
                'Último lembrete amigável: {numero_fatura}',
                'Lembrete de regularização',
                'Sua fatura ainda está pendente',
                'Olá {primeiro_nome}, estamos tentando contato sobre a fatura {numero_fatura}. Ela permanece em aberto há {dias_atraso} dias.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Atraso' => '{dias_atraso} dias'],
                'Pagar e regularizar',
                '{link_pagamento}',
                'Caso exista algum impedimento, fale conosco para avaliarmos a melhor forma de regularização.'
            ),
            'cobranca-15-dias-atraso' => $this->make(
                'Cobrança - 15 dias de atraso',
                'atraso',
                '+15d',
                'Notificação de débito em aberto: {numero_fatura}',
                'Notificação de débito',
                'Existe uma pendência importante na sua conta',
                'Olá {primeiro_nome}, até o momento não identificamos o pagamento da fatura {numero_fatura}. Para evitar novas restrições, regularize o débito pelo link abaixo.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Atraso' => '{dias_atraso} dias'],
                'Regularizar débito',
                '{link_pagamento}',
                'Se o pagamento já foi efetuado, por favor aguarde a confirmação automática.'
            ),
            'aniversario-cliente' => $this->make(
                'Aniversário do cliente',
                'agradecimento',
                null,
                'Feliz aniversário, {primeiro_nome}!',
                'Mensagem especial',
                'Hoje é um dia especial',
                'Olá {primeiro_nome}, em nome de toda a equipe {empresa}, queremos desejar um feliz aniversário. Que seu novo ciclo seja leve, próspero e cheio de boas conquistas.',
                ['Cliente' => '{nome}', 'Data' => '{data_evento}', 'Empresa' => '{empresa}'],
                null,
                null,
                'Obrigado por fazer parte da nossa história.'
            ),
            'boas-vindas' => $this->make(
                'Boas-vindas',
                'agradecimento',
                null,
                'Bem-vindo(a) à {empresa}, {primeiro_nome}!',
                'Boas-vindas',
                'É um prazer ter você conosco',
                'Olá {primeiro_nome}, sua conta foi recebida com sucesso. A partir de agora, você passa a contar com nossa estrutura para acompanhar seus serviços, pagamentos e comunicações importantes.',
                ['Cliente' => '{nome}', 'Serviço' => '{nome_servico}', 'Cadastro' => '{data_cadastro}'],
                'Acessar minha conta',
                '{link_reativacao}',
                'Guarde este email para consultar seus dados de acesso e canais de atendimento.'
            ),
            'cadastro-realizado-sucesso' => $this->make(
                'Cadastro realizado com sucesso',
                'agradecimento',
                null,
                'Cadastro realizado com sucesso',
                'Cadastro confirmado',
                'Seu cadastro foi concluído',
                'Olá {primeiro_nome}, confirmamos que seu cadastro foi realizado com sucesso em {data_cadastro}. Seus dados foram recebidos e sua conta já está pronta para uso.',
                ['Nome' => '{nome}', 'Email' => '{email}', 'Data do cadastro' => '{data_cadastro}'],
                'Acessar conta',
                '{link_reativacao}',
                'Se você não reconhece este cadastro, entre em contato imediatamente com nosso suporte.'
            ),
            'email-marketing-reativacao-cliente' => $this->make(
                'Email marketing - reativação de cliente',
                'custom',
                null,
                '{primeiro_nome}, sentimos sua falta',
                'Convite de retorno',
                'Tem uma condição especial esperando por você',
                'Olá {primeiro_nome}, percebemos que você está há algum tempo sem utilizar os serviços da {empresa}. Preparamos uma oportunidade para facilitar seu retorno com mais praticidade.',
                ['Benefício' => '{desconto}', 'Cupom' => '{cupom}', 'Válido até' => '{data_limite}'],
                'Reativar agora',
                '{link_reativacao}',
                'Esta condição pode ser limitada ao período informado e depende da disponibilidade do serviço.'
            ),
            'contrato-assinado-sucesso' => $this->make(
                'Contrato assinado com sucesso',
                'agradecimento',
                null,
                'Contrato {numero_contrato} assinado com sucesso',
                'Contrato assinado',
                'Assinatura confirmada com sucesso',
                'Olá {primeiro_nome}, confirmamos a assinatura do contrato {numero_contrato}. O documento foi registrado com sucesso e já está disponível para consulta.',
                ['Contrato' => '{numero_contrato}', 'Título' => '{titulo_contrato}', 'Data' => '{data_contrato}'],
                'Ver contrato assinado',
                '{link_contrato}',
                'Recomendamos guardar uma cópia deste email para seus registros.'
            ),
            'contrato-assinatura-pendente' => $this->make(
                'Contrato pendente de assinatura',
                'lembrete',
                null,
                'Assinatura pendente: contrato {numero_contrato}',
                'Assinatura pendente',
                'Seu contrato aguarda assinatura',
                'Olá {primeiro_nome}, o contrato {numero_contrato} ainda está pendente de assinatura. Para concluir o processo, revise o documento e finalize a assinatura pelo link seguro abaixo.',
                ['Contrato' => '{numero_contrato}', 'Título' => '{titulo_contrato}', 'Data' => '{data_contrato}'],
                'Revisar e assinar',
                '{link_contrato}',
                'A assinatura digital registra data, hora e informações de auditoria do aceite.'
            ),
            'contrato-nao-assinado' => $this->make(
                'Contrato não assinado',
                'atraso',
                null,
                'Contrato {numero_contrato} ainda não foi assinado',
                'Ação necessária',
                'Contrato não assinado',
                'Olá {primeiro_nome}, identificamos que o contrato {numero_contrato} ainda não foi assinado. Sem a assinatura, algumas etapas do atendimento podem permanecer pendentes.',
                ['Contrato' => '{numero_contrato}', 'Título' => '{titulo_contrato}', 'Motivo' => '{motivo}'],
                'Assinar contrato',
                '{link_contrato}',
                'Se você tiver alguma dúvida sobre o documento, entre em contato antes de assinar.'
            ),
            'servico-suspenso' => $this->make(
                'Serviço suspenso',
                'atraso',
                null,
                'Serviço suspenso: {nome_servico}',
                'Serviço suspenso',
                'Seu serviço foi suspenso temporariamente',
                'Olá {primeiro_nome}, informamos que o serviço {nome_servico} foi suspenso em {data_suspensao}. O motivo registrado foi: {motivo}.',
                ['Serviço' => '{nome_servico}', 'Status' => '{status_servico}', 'Data' => '{data_suspensao}'],
                'Regularizar serviço',
                '{link_reativacao}',
                'Após a regularização, a reativação poderá ocorrer automaticamente ou após análise da equipe.'
            ),
            'servico-cancelado' => $this->make(
                'Serviço cancelado',
                'custom',
                null,
                'Serviço cancelado: {nome_servico}',
                'Cancelamento confirmado',
                'Seu serviço foi cancelado',
                'Olá {primeiro_nome}, confirmamos o cancelamento do serviço {nome_servico} em {data_cancelamento}. O motivo registrado foi: {motivo}.',
                ['Serviço' => '{nome_servico}', 'Status' => '{status_servico}', 'Cancelamento' => '{data_cancelamento}'],
                'Falar com suporte',
                'mailto:{email_suporte}',
                'Caso o cancelamento tenha ocorrido por engano, entre em contato conosco o quanto antes.'
            ),
            'pagamento-confirmado' => $this->make(
                'Pagamento confirmado',
                'agradecimento',
                null,
                'Pagamento confirmado: fatura {numero_fatura}',
                'Pagamento confirmado',
                'Recebemos seu pagamento',
                'Olá {primeiro_nome}, confirmamos o pagamento da fatura {numero_fatura}. Obrigado por manter seus compromissos em dia.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Data' => '{data_pagamento}', 'Método' => '{metodo_pagamento}'],
                null,
                null,
                'Esta confirmação também ficará registrada no seu histórico financeiro.'
            ),
            'pagamento-pendente' => $this->make(
                'Pagamento pendente',
                'lembrete',
                null,
                'Pagamento pendente: fatura {numero_fatura}',
                'Pagamento pendente',
                'Ainda não identificamos a confirmação',
                'Olá {primeiro_nome}, seu pagamento da fatura {numero_fatura} está com status {status_pagamento}. Caso ainda não tenha concluído, utilize o link abaixo.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Status' => '{status_pagamento}'],
                'Concluir pagamento',
                '{link_pagamento}',
                'Alguns métodos podem levar alguns minutos para compensação.'
            ),
            'pagamento-estornado' => $this->make(
                'Pagamento estornado',
                'custom',
                null,
                'Pagamento estornado: fatura {numero_fatura}',
                'Estorno registrado',
                'Seu pagamento foi estornado',
                'Olá {primeiro_nome}, registramos o estorno do pagamento relacionado à fatura {numero_fatura}. O valor envolvido foi {valor}.',
                ['Fatura' => '{numero_fatura}', 'Valor' => '{valor}', 'Transação' => '{id_transacao}', 'Método' => '{metodo_pagamento}'],
                'Ver detalhes',
                '{link_pagamento}',
                'O prazo para o valor aparecer na conta pode variar conforme o método de pagamento e a instituição financeira.'
            ),
        ];
    }

    protected function make(
        string $name,
        string $category,
        ?string $scheduleOffset,
        string $subject,
        string $eyebrow,
        string $title,
        string $intro,
        array $highlights,
        ?string $ctaLabel,
        ?string $ctaUrl,
        ?string $note
    ): array {
        if (in_array($category, ['cobranca', 'lembrete', 'atraso'], true)) {
            $highlights = ['Servico' => '{servico_principal}'] + $highlights + ['Parcelas' => '{resumo_parcelas}'];
        }

        $html = $this->html($eyebrow, $title, $intro, $highlights, $ctaLabel, $ctaUrl, $note);

        $data = [
            'name' => $name,
            'category' => $category,
            'schedule_offset' => $scheduleOffset,
            'subject' => $subject,
            'body_html' => $html,
            'body_text' => $this->plainText($html),
            'variables' => array_keys(EmailTemplate::availableVariables()),
            'active' => true,
        ];

        if (Schema::hasColumn('email_templates', 'is_system')) {
            $data += [
                'scope' => 'system',
                'is_system' => true,
                'is_default' => true,
                'locked' => true,
            ];
        }

        return $data;
    }

    protected function html(
        string $eyebrow,
        string $title,
        string $intro,
        array $highlights,
        ?string $ctaLabel,
        ?string $ctaUrl,
        ?string $note
    ): string {
        $rows = '';
        foreach ($highlights as $label => $value) {
            $rows .= '<tr>'
                . '<td style="padding:10px 0;color:#64748b;font-size:13px;border-bottom:1px solid #e5e7eb;">'.$label.'</td>'
                . '<td style="padding:10px 0;color:#0f172a;font-size:13px;font-weight:700;text-align:right;border-bottom:1px solid #e5e7eb;">'.$value.'</td>'
                . '</tr>';
        }

        $cta = '';
        if ($ctaLabel && $ctaUrl) {
            $cta = '<p style="margin:26px 0 10px;text-align:center;">'
                . '<a href="'.$ctaUrl.'" style="display:inline-block;background:{cor_primaria};color:#ffffff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:8px;">'.$ctaLabel.'</a>'
                . '</p>';
        }

        $noteBlock = '';
        if ($note) {
            $noteBlock = '<div style="margin-top:22px;padding:14px 16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;color:#475569;font-size:13px;line-height:1.6;">'
                . '<strong style="color:#0f172a;">Observação:</strong> '.$note
                . '</div>';
        }

        return '<div style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f1f5f9;padding:0;margin:0;">'
            . '<tr><td align="center" style="padding:28px 14px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="padding:22px 26px;background:{cor_primaria};color:#ffffff;">'
            . '{logo_html}'
            . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:{cor_destaque};font-weight:700;">'.$eyebrow.'</div>'
            . '<div style="font-size:22px;line-height:1.3;font-weight:800;margin-top:7px;color:#ffffff;">'.$title.'</div>'
            . '</td></tr>'
            . '<tr><td style="padding:26px;">'
            . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#334155;">'.$intro.'</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0 4px;">'.$rows.'</table>'
            . $cta
            . '{qrcode_pix_html}'
            . $noteBlock
            . '<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#334155;">Atenciosamente,<br><strong style="color:#0f172a;">{empresa}</strong></p>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 26px;background:#f8fafc;border-top:1px solid #e5e7eb;color:#64748b;font-size:12px;line-height:1.6;">'
            . 'Este email foi enviado para {email}. Em caso de dúvidas, fale conosco pelo email {email_suporte} ou WhatsApp {whatsapp_suporte}.'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</div>';
    }

    protected function plainText(string $html): string
    {
        $withBreaks = str_replace(['</p>', '</tr>', '<br>', '<br/>', '<br />'], "\n", $html);
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $text)));
    }
}
