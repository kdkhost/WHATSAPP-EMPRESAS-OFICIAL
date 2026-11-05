<?php

namespace App\Modules\Settings\Services;

class PublicPageContentService
{
    public function __construct(protected SettingsManager $settings) {}

    public function all(): array
    {
        $pages = [];

        foreach (array_keys($this->defaults()) as $page) {
            $pages[$page] = $this->page($page);
        }

        return $pages;
    }

    public function page(string $page): array
    {
        $defaults = $this->defaults()[$page] ?? [];
        $stored = $this->settings->get('pages.'.$page, []);

        return $this->mergeRecursive($defaults, is_array($stored) ? $stored : []);
    }

    public function save(string $page, array $payload): void
    {
        $defaults = $this->defaults()[$page] ?? [];
        $clean = $this->mergeRecursive($defaults, $payload);
        $this->settings->set('pages.'.$page, $clean, 'json');
    }

    public function defaults(): array
    {
        return [
            'home' => [
                'hero' => [
                    'kicker' => 'Sistema oficial de cobranca via WhatsApp',
                    'title_before' => 'Reduza a inadimplencia',
                    'title_highlight' => 'com automacao inteligente',
                    'title_after' => 'com WhatsApp',
                    'description' => 'Plataforma completa de cobranca automatizada. Envie faturas, lembretes e links de pagamento PIX diretamente no WhatsApp do seu cliente.',
                    'primary_cta_label' => 'Testar Gratis por 7 dias',
                    'primary_cta_url' => '/cadastro',
                    'secondary_cta_label' => 'Ver Planos e Precos',
                    'secondary_cta_url' => '/pacotes',
                ],
                'features_header' => [
                    'kicker' => 'Tudo que voce precisa',
                    'title' => 'Um arsenal completo',
                    'highlight' => 'contra a inadimplencia',
                    'description' => 'Cada ferramenta foi pensada para que voce receba mais, gaste menos tempo e nunca perca uma cobranca.',
                ],
                'metrics' => [
                    ['label' => 'Reducao de inadimplencia', 'value' => 72, 'suffix' => '%'],
                    ['label' => 'Empresas usando', 'value' => 3500, 'suffix' => '+'],
                    ['label' => 'Mensagens/mes', 'value' => 2.5, 'suffix' => 'M'],
                    ['label' => 'Uptime monitorado', 'value' => 99.9, 'suffix' => '%'],
                ],
                'features' => [
                    ['icon' => 'bot', 'title' => 'Automacao 24/7', 'desc' => 'Cobrancas automaticas antes, no dia e apos o vencimento.', 'gradient' => 'from-blue-500 to-cyan-500'],
                    ['icon' => 'credit-card', 'title' => 'PIX com baixa instantanea', 'desc' => 'Cliente paga e a fatura pode ser conciliada automaticamente.', 'gradient' => 'from-green-500 to-emerald-500'],
                    ['icon' => 'shield-check', 'title' => 'Protecao operacional', 'desc' => 'Warmup, cooldown e controles anti-ban para reduzir risco operacional.', 'gradient' => 'from-purple-500 to-pink-500'],
                ],
                'testimonials_title' => 'Amado por empresas de todo o Brasil',
                'final_cta' => [
                    'title' => 'Pronto para acabar com a inadimplencia?',
                    'description' => 'Junte-se a mais de 3.500 empresas que automatizaram suas cobrancas. Comece agora, sem cartao de credito.',
                    'primary_label' => 'Comecar teste gratis agora',
                    'primary_url' => '/cadastro',
                    'secondary_label' => 'Falar com vendas',
                    'secondary_url' => '/contato',
                ],
            ],
            'pacotes' => [
                'hero' => [
                    'kicker' => 'Tres planos, uma escada clara',
                    'title_before' => 'Cresca no seu ritmo.',
                    'title_highlight' => 'Sem complicar a escolha.',
                    'description' => 'Uma estrutura simples de entrada, crescimento e escala. Sem taxas ocultas, sem fidelidade e sem excesso de opcoes.',
                ],
                'comparison' => [
                    'title' => 'Visao detalhada',
                    'description' => 'Compare cada detalhe e escolha o plano perfeito para o seu momento.',
                ],
                'sandbox' => [
                    'kicker' => 'Ambiente de testes livre',
                    'title' => 'Acesso sandbox',
                    'description' => 'Experimente todas as funcionalidades em nosso ambiente de testes sem custo algum.',
                ],
            ],
            'faq' => [
                'hero' => [
                    'kicker' => 'Central de ajuda',
                    'title_before' => 'Como podemos',
                    'title_highlight' => 'ajudar?',
                    'search_placeholder' => 'Busque por termos, duvidas ou palavras-chave...',
                    'empty_message' => 'Nenhuma resposta encontrada para sua busca.',
                ],
                'items' => [
                    ['q' => 'Como funciona o periodo de teste?', 'a' => 'Voce pode testar todos os recursos do plano sem cartao de credito.'],
                    ['q' => 'Posso mudar de plano depois?', 'a' => 'Sim. Upgrade e downgrade podem ser feitos conforme a necessidade da conta.'],
                ],
            ],
            'contato' => [
                'hero' => [
                    'kicker' => 'Fale com a equipe',
                    'title_before' => 'Fale com',
                    'title_highlight' => 'especialistas',
                    'description' => 'Estamos aqui para escalar o seu negocio e tirar todas as suas duvidas.',
                ],
                'channels' => [
                    ['icon' => 'message-circle', 'title' => 'WhatsApp de vendas', 'sub' => 'Atendimento imediato em horario comercial.', 'info' => '+55 11 99999-8888', 'href' => 'https://wa.me/5511999998888'],
                    ['icon' => 'mail', 'title' => 'Suporte por e-mail', 'sub' => 'Respostas detalhadas em ate 24 horas uteis.', 'info' => 'ajuda@empresa.com.br', 'href' => 'mailto:ajuda@empresa.com.br'],
                ],
                'schedules' => [
                    ['day' => 'Segunda a Sexta', 'time' => '08h as 18h'],
                    ['day' => 'Sabado', 'time' => '09h as 13h'],
                    ['day' => 'Domingo', 'time' => 'Fechado'],
                ],
                'response' => [
                    'title' => 'Tempo medio de resposta: 2 horas',
                    'hint' => 'Medido em dias uteis, horario comercial.',
                ],
            ],
            'sobre' => [],
            'recursos' => [],
            'integracoes' => [],
            'marketplace' => [],
            'funil' => [],
            'blog' => [],
            'changelog' => [],
            'privacidade' => [],
            'termos' => [],
            'cookies' => [],
        ];
    }

    public function pageCatalog(): array
    {
        return [
            'home' => [
                'name'        => 'Início',
                'route'       => '/',
                'category'    => 'Principal',
                'icon'        => 'bi-house-door',
                'description' => 'Página principal com hero, recursos, métricas e planos.',
            ],
            'pacotes' => [
                'name'        => 'Planos e Preços',
                'route'       => '/pacotes',
                'category'    => 'Vendas',
                'icon'        => 'bi-tags',
                'description' => 'Tabela de preços, comparativo detalhado e contratação.',
            ],
            'recursos' => [
                'name'        => 'Recursos e Ferramentas',
                'route'       => '/recursos',
                'category'    => 'Produto',
                'icon'        => 'bi-stars',
                'description' => 'Lista completa de recursos e diferenciais da plataforma.',
            ],
            'funil' => [
                'name'        => 'Demonstração / Funil',
                'route'       => '/funil',
                'category'    => 'Vendas',
                'icon'        => 'bi-funnel',
                'description' => 'Página interativa com funil de conversão e demonstração guiada.',
            ],
            'sobre' => [
                'name'        => 'Sobre Nós',
                'route'       => '/sobre',
                'category'    => 'Institucional',
                'icon'        => 'bi-building',
                'description' => 'História da empresa, missão, valores e equipe.',
            ],
            'contato' => [
                'name'        => 'Contato e Suporte',
                'route'       => '/contato',
                'category'    => 'Suporte',
                'icon'        => 'bi-chat-dots',
                'description' => 'Formulário de contato, canais de atendimento e horários.',
            ],
            'faq' => [
                'name'        => 'Perguntas Frequentes (FAQ)',
                'route'       => '/faq',
                'category'    => 'Ajuda',
                'icon'        => 'bi-question-circle',
                'description' => 'Central de ajuda com busca interativa e dúvidas comuns.',
            ],
            'blog' => [
                'name'        => 'Blog e Artigos',
                'route'       => '/blog',
                'category'    => 'Conteúdo',
                'icon'        => 'bi-newspaper',
                'description' => 'Artigos educativos, notícias do mercado e dicas financeiras.',
            ],
            'integracoes' => [
                'name'        => 'Integrações',
                'route'       => '/integracoes',
                'category'    => 'Técnico',
                'icon'        => 'bi-puzzle',
                'description' => 'Integrações com WHMCS, cPanel, aaPanel, Webhooks e APIs.',
            ],
            'marketplace' => [
                'name'        => 'Marketplace de Addons',
                'route'       => '/marketplace',
                'category'    => 'Extensões',
                'icon'        => 'bi-shop',
                'description' => 'Catálogo de módulos e extensões disponíveis para o sistema.',
            ],
            'changelog' => [
                'name'        => 'Atualizações (Changelog)',
                'route'       => '/changelog',
                'category'    => 'Institucional',
                'icon'        => 'bi-clock-history',
                'description' => 'Histórico de versões, novidades e melhorias implementadas.',
            ],
            'termos' => [
                'name'        => 'Termos de Uso',
                'route'       => '/termos',
                'category'    => 'Legal',
                'icon'        => 'bi-file-earmark-text',
                'description' => 'Termos de serviço e condições de uso da plataforma.',
            ],
            'privacidade' => [
                'name'        => 'Política de Privacidade',
                'route'       => '/privacidade',
                'category'    => 'Legal',
                'icon'        => 'bi-shield-check',
                'description' => 'Política de privacidade e conformidade com a LGPD.',
            ],
            'cookies' => [
                'name'        => 'Política de Cookies',
                'route'       => '/cookies',
                'category'    => 'Legal',
                'icon'        => 'bi-cookie',
                'description' => 'Informações sobre uso de cookies e rastreadores.',
            ],
        ];
    }

    /**
     * Retorna o status de ativacao de todas as paginas gerenciadas.
     * @return array<string, bool>
     */
    public function pageStatuses(): array
    {
        $stored = $this->settings->get('pages.status', []);
        $stored = is_array($stored) ? $stored : [];
        $statuses = [];

        foreach (array_keys($this->pageCatalog()) as $slug) {
            $statuses[$slug] = isset($stored[$slug]) ? (bool) $stored[$slug] : true;
        }

        return $statuses;
    }

    /**
     * Checa se uma pagina especifica esta ativa.
     */
    public function isPageActive(string $page): bool
    {
        $statuses = $this->pageStatuses();
        return $statuses[$page] ?? true;
    }

    /**
     * Alterna o status ativo/desativo de uma pagina.
     */
    public function togglePageStatus(string $page): bool
    {
        $current = $this->isPageActive($page);
        $new = ! $current;
        $this->setPageStatus($page, $new);
        return $new;
    }

    /**
     * Define o status de uma pagina.
     */
    public function setPageStatus(string $page, bool $active): void
    {
        $stored = $this->settings->get('pages.status', []);
        $stored = is_array($stored) ? $stored : [];
        $stored[$page] = $active;
        $this->settings->set('pages.status', $stored, 'json');
    }

    /**
     * Ativa ou desativa todas as paginas de uma vez.
     */
    public function setAllPageStatuses(bool $active): void
    {
        $statuses = [];
        foreach (array_keys($this->pageCatalog()) as $slug) {
            $statuses[$slug] = $active;
        }
        $this->settings->set('pages.status', $statuses, 'json');
    }

    protected function mergeRecursive(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($value) && ! array_is_list($defaults[$key])) {
                $defaults[$key] = $this->mergeRecursive($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
