<?php

namespace App\Modules\Contracts\Services;

use App\Modules\Contracts\Models\ContractTemplate;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

class ContractTemplateCatalog
{
    public function availableVariables(): array
    {
        return [
            '{nome}' => 'Nome completo do cliente',
            '{primeiro_nome}' => 'Primeiro nome do cliente',
            '{email}' => 'Email do cliente',
            '{documento}' => 'CPF ou CNPJ do cliente',
            '{telefone}' => 'Telefone do cliente',
            '{whatsapp}' => 'WhatsApp do cliente',
            '{cep}' => 'CEP do cliente',
            '{logradouro}' => 'Rua/Avenida do cliente',
            '{numero}' => 'Numero do endereco',
            '{complemento}' => 'Complemento do endereco',
            '{bairro}' => 'Bairro do cliente',
            '{cidade}' => 'Cidade do cliente',
            '{uf}' => 'UF do cliente',
            '{empresa}' => 'Nome da empresa do tenant',
            '{titulo_contrato}' => 'Titulo do contrato',
            '{numero_contrato}' => 'Numero do contrato',
            '{data_atual}' => 'Data atual',
            '{data_extenso}' => 'Data atual por extenso',
            '{valor}' => 'Valor da fatura vinculada',
            '{vencimento}' => 'Vencimento da fatura',
            '{numero_fatura}' => 'Numero da fatura',
            '{link_assinatura}' => 'Link publico de assinatura',
        ];
    }

    public function defaultLayout(): array
    {
        return [
            'page' => [
                'width' => 794,
                'height' => 1123,
                'padding' => 48,
            ],
            'content' => [
                'x' => 68,
                'y' => 168,
                'w' => 658,
                'h' => 560,
            ],
            'signature' => [
                'x' => 68,
                'y' => 778,
                'w' => 250,
                'h' => 110,
            ],
            'signer' => [
                'x' => 348,
                'y' => 778,
                'w' => 378,
                'h' => 110,
            ],
            'metadata' => [
                'x' => 68,
                'y' => 920,
                'w' => 658,
                'h' => 120,
            ],
        ];
    }

    public function ensureDefaultsForTenant(?Tenant $tenant): void
    {
        $tenantId = $tenant?->id;
        $companyName = $tenant?->company_name ?: config('app.name');

        foreach ($this->defaultTemplates($companyName) as $presetKey => $template) {
            $exists = ContractTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($presetKey, $template) {
                    $query->where('preset_key', $presetKey)
                        ->orWhere('slug', $template['slug']);
                })
                ->exists();

            if ($exists) {
                continue;
            }

            ContractTemplate::create([
                'tenant_id' => $tenantId,
                'preset_key' => $presetKey,
                'name' => $template['name'],
                'slug' => $template['slug'],
                'category' => $template['category'],
                'description' => $template['description'],
                'content_html' => $template['content_html'],
                'variables' => array_keys($this->availableVariables()),
                'background_type' => 'none',
                'background_path' => null,
                'layout_json' => $this->defaultLayout(),
                'require_witness' => false,
                'active' => true,
            ]);
        }
    }

    protected function defaultTemplates(string $companyName): array
    {
        return [
            'prestacao-servicos-premium' => $this->makeTemplate(
                'Prestação de Serviços',
                'Serviços',
                'Modelo base profissional para serviços recorrentes, consultoria e entregas sob demanda.',
                'prestacao-servicos-premium',
                [
                    '1. Objeto',
                    'A CONTRATADA, {empresa}, prestará ao(à) CONTRATANTE {nome} os serviços descritos neste instrumento, conforme escopo comercial previamente alinhado entre as partes.',
                    '2. Entregas e execução',
                    'As atividades serão realizadas de forma contínua, observando cronograma, prioridades, prazos e canais de comunicação definidos durante o atendimento.',
                    '3. Investimento e pagamento',
                    'O investimento contratado será aquele informado na proposta comercial, pedido, orçamento ou fatura vinculada a este instrumento. Quando houver cobrança vinculada, o valor de referência será {valor}, com vencimento em {vencimento}.',
                    '4. Vigência',
                    'Este contrato passa a produzir efeitos a partir de {data_atual} e permanecerá válido até a conclusão do objeto contratado, podendo ser renovado por comum acordo.',
                    '5. Obrigações do contratante',
                    'O(A) CONTRATANTE compromete-se a fornecer informações, acessos, aprovações e materiais necessários para a boa execução dos serviços dentro dos prazos acordados.',
                    '6. Obrigações da contratada',
                    'A CONTRATADA deverá executar os serviços com diligência, profissionalismo, confidencialidade e observância das boas práticas técnicas aplicáveis.',
                    '7. Rescisão',
                    'O descumprimento contratual poderá ensejar suspensão, readequação do escopo ou rescisão, mediante comunicação formal.',
                    '8. Foro',
                    'Fica eleito o foro da comarca do domicílio da CONTRATADA, salvo disposição legal em sentido diverso.',
                ]
            ),
            'marketing-digital-360' => $this->makeTemplate(
                'Marketing Digital 360',
                'Marketing',
                'Contrato voltado para planejamento, campanhas, branding e execução de marketing digital.',
                'marketing-digital-360',
                [
                    '1. Escopo estratégico',
                    '{empresa} atuará no planejamento, coordenação e execução de ações de marketing digital para o(a) cliente {nome}, incluindo comunicação, posicionamento, calendário promocional e relatórios gerenciais.',
                    '2. Canais atendidos',
                    'O atendimento poderá envolver site, landing pages, email marketing, CRM, automações, social media, mídia paga e materiais de apoio.',
                    '3. Aprovações',
                    'Peças, campanhas e cronogramas dependerão de validação do(a) CONTRATANTE sempre que houver necessidade operacional ou legal.',
                    '4. Resultados e métricas',
                    'Os resultados serão acompanhados por indicadores de desempenho, sem promessa de faturamento mínimo, uma vez que dependem de múltiplos fatores externos e internos.',
                    '5. Investimento',
                    'O investimento mensal, setup ou verba pontual observará a negociação comercial vigente entre as partes, sem prejuízo das cobranças formalizadas em fatura.',
                    '6. Propriedade intelectual',
                    'Materiais produzidos no escopo contratado poderão ser utilizados pela CONTRATADA em portfólio, salvo cláusula específica de confidencialidade ou vedação expressa.',
                ]
            ),
            'gestao-social-media' => $this->makeTemplate(
                'Gestão de Social Media',
                'Redes Sociais',
                'Modelo para gestão de Instagram, Facebook, LinkedIn, TikTok e operação editorial recorrente.',
                'gestao-social-media',
                [
                    '1. Objeto',
                    'A CONTRATADA realizará a gestão editorial e operacional das redes sociais do(a) CONTRATANTE, incluindo planejamento de pautas, criação de legendas, publicação, monitoramento e relatórios.',
                    '2. Produção e aprovação',
                    'O calendário de conteúdo será submetido para aprovação conforme rotina definida entre as partes. Alterações extraordinárias poderão impactar o cronograma.',
                    '3. Ativos e acessos',
                    'O(A) CONTRATANTE fornecerá credenciais, materiais institucionais, identidade visual e referências necessárias para a execução do serviço.',
                    '4. Atendimento e moderação',
                    'A atuação em comentários, directs e interações será limitada ao escopo comercial contratado.',
                    '5. Vigência e renovação',
                    'A vigência observará a contratação comercial e poderá ser renovada por períodos sucessivos mediante continuidade operacional.',
                ]
            ),
            'trafego-pago-performance' => $this->makeTemplate(
                'Tráfego Pago e Performance',
                'Mídia Paga',
                'Contrato para gestão de campanhas em Meta Ads, Google Ads e plataformas correlatas.',
                'trafego-pago-performance',
                [
                    '1. Objeto',
                    'A CONTRATADA ficará responsável pela configuração, gestão, otimização e acompanhamento de campanhas de tráfego pago para o(a) CONTRATANTE.',
                    '2. Verba de mídia',
                    'A verba destinada às plataformas de anúncio não se confunde com a remuneração da CONTRATADA, salvo previsão comercial expressa em sentido diverso.',
                    '3. Contas e acessos',
                    'O(A) CONTRATANTE deverá disponibilizar acessos administrativos ou convites necessários às contas de anúncio, pixel, conversões e analytics.',
                    '4. Limitação de responsabilidade',
                    'Não há garantia de resultado financeiro específico, pois o desempenho depende de mercado, oferta, jornada do cliente, sazonalidade, concorrência e estrutura comercial.',
                    '5. Relatórios',
                    'A CONTRATADA poderá apresentar relatórios com alcance, cliques, leads, CPA, ROAS ou outros indicadores pertinentes à estratégia.',
                ]
            ),
            'consultoria-e-mentoria' => $this->makeTemplate(
                'Consultoria e Mentoria',
                'Consultoria',
                'Modelo para consultorias, mentorias, reuniões estratégicas e acompanhamento executivo.',
                'consultoria-e-mentoria',
                [
                    '1. Objeto',
                    'Este contrato tem por objeto a prestação de consultoria/mentoria especializada por {empresa} ao(à) cliente {nome}, com foco em diagnóstico, direcionamento estratégico e plano de ação.',
                    '2. Formato de atendimento',
                    'Os encontros poderão ocorrer de forma online ou presencial, conforme disponibilidade e contratação comercial.',
                    '3. Responsabilidade sobre execução',
                    'As recomendações apresentadas possuem caráter técnico-consultivo. A implementação operacional dependerá da estrutura, validação e decisão do(a) CONTRATANTE.',
                    '4. Materiais de apoio',
                    'Documentos, apresentações e orientações fornecidos pela CONTRATADA destinam-se exclusivamente ao uso do(a) CONTRATANTE no contexto contratado.',
                ]
            ),
            'manutencao-e-suporte' => $this->makeTemplate(
                'Manutenção e Suporte Técnico',
                'Suporte',
                'Contrato para manutenção evolutiva, corretiva e suporte mensal.',
                'manutencao-e-suporte',
                [
                    '1. Objeto',
                    'A CONTRATADA prestará suporte técnico e manutenção sobre os ativos digitais ou sistemas definidos em contratação comercial, em favor do(a) cliente {nome}.',
                    '2. Chamados e prioridades',
                    'Demandas serão registradas por canal oficial e tratadas conforme prioridade, complexidade e janela operacional disponível.',
                    '3. Exclusões',
                    'Não integram este contrato serviços de desenvolvimento fora do escopo, reconstrução integral, suporte a terceiros sem documentação ou demandas emergenciais não previstas.',
                    '4. Pagamento e suspensão',
                    'A inadimplência poderá implicar suspensão temporária dos atendimentos, preservados os direitos das partes e as obrigações legais aplicáveis.',
                ]
            ),
        ];
    }

    protected function makeTemplate(
        string $name,
        string $category,
        string $description,
        string $slugBase,
        array $clauses
    ): array {
        $html = collect($clauses)
            ->map(function (string $line): string {
                if (preg_match('/^\d+\./', $line) === 1) {
                    return '<h4 style="margin:24px 0 10px;font-size:18px;font-weight:700;color:#162033;">' . e($line) . '</h4>';
                }

                return '<p style="margin:0 0 14px;font-size:14px;line-height:1.9;color:#344054;text-align:justify;">' . e($line) . '</p>';
            })
            ->implode("\n");

        $titleBlock = '<div style="text-align:center;margin-bottom:24px;padding-bottom:14px;border-bottom:2px solid #e2e8f0;">'
            . '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin-bottom:4px;">' . e($category) . ' &bull; {numero_contrato}</div>'
            . '<h1 style="margin:0;font-size:20px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;color:#0f172a;line-height:1.3;">' . e($name) . '</h1>'
            . ($description ? '<div style="font-size:12px;color:#64748b;margin-top:4px;font-style:italic;">Motivo / Objeto: ' . e($description) . '</div>' : '')
            . '</div>';

        $header = $titleBlock . '<p style="margin:0 0 18px;font-size:14px;line-height:1.9;color:#344054;text-align:justify;">'
            . 'Pelo presente instrumento particular, de um lado <strong>' . e('{empresa}') . '</strong>, doravante denominada <strong>CONTRATADA</strong>, '
            . 'e, de outro, <strong>' . e('{nome}') . '</strong>, inscrito(a) sob o documento <strong>' . e('{documento}') . '</strong>, '
            . 'doravante denominado(a) <strong>CONTRATANTE</strong>, ajustam o presente contrato nos termos abaixo.'
            . '</p>';

        $footer = '<p style="margin:28px 0 0;font-size:14px;line-height:1.9;color:#344054;text-align:justify;">'
            . 'E, por estarem de pleno acordo, as partes confirmam este instrumento eletronicamente em <strong>' . e('{data_extenso}') . '</strong>.'
            . '</p>';

        return [
            'name' => $name,
            'slug' => Str::slug($slugBase),
            'category' => $category,
            'description' => $description,
            'content_html' => $header . "\n" . $html . "\n" . $footer,
        ];
    }
}
