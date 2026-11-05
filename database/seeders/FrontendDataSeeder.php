<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FrontendDataSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('testimonials')->truncate();
        DB::table('slides')->truncate();
        DB::table('popups')->truncate();
        DB::table('plans')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // 4 Planos Gourmet
        $plans = [
            [
                'name' => 'Sandbox / Demo',
                'slug' => 'sandbox',
                'description' => 'Ambiente seguro para testar a plataforma completa.',
                'price' => 0.00,
                'extra_instance_price' => 0.00,
                'trial_days' => 7,
                'max_instances' => 1,
                'max_customers' => 10,
                'max_invoices_month' => 50,
                'max_messages_month' => 100,
                'custom_gateway' => false,
                'custom_templates' => false,
                'installment_enabled' => false,
                'fee_passthrough' => false,
                'features' => json_encode(['Dashboard completo', 'Relatórios básicos', 'Cobrança manual', 'Suporte via email']),
                'active' => true,
                'sort' => 0,
                'is_demo' => true,
                'highlight' => false,
                'badge' => null
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfeito para autônomos e pequenos negócios.',
                'price' => 97.00,
                'extra_instance_price' => 39.90,
                'trial_days' => 7,
                'max_instances' => 1,
                'max_customers' => 500,
                'max_invoices_month' => 1000,
                'max_messages_month' => 2000,
                'custom_gateway' => true,
                'custom_templates' => false,
                'installment_enabled' => false,
                'fee_passthrough' => false,
                'features' => json_encode(['Gateway PIX nativo', 'Lembretes automáticos', 'Gestão de clientes', 'Suporte prioritário']),
                'active' => true,
                'sort' => 1,
                'is_demo' => false,
                'highlight' => false,
                'badge' => null
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Para negócios que estão acelerando.',
                'price' => 197.00,
                'extra_instance_price' => 39.90,
                'trial_days' => 0,
                'max_instances' => 2,
                'max_customers' => 2000,
                'max_invoices_month' => 5000,
                'max_messages_month' => 10000,
                'custom_gateway' => true,
                'custom_templates' => true,
                'installment_enabled' => true,
                'fee_passthrough' => true,
                'features' => json_encode(['API e Webhooks', 'Múltiplas conexões WhatsApp', 'Templates dinâmicos', 'Checkout transparente no Cartão']),
                'active' => true,
                'sort' => 2,
                'is_demo' => false,
                'highlight' => true,
                'badge' => 'Mais Popular'
            ],
            [
                'name' => 'Advanced',
                'slug' => 'advanced',
                'description' => 'A escolha definitiva para empresas de médio porte.',
                'price' => 297.00,
                'extra_instance_price' => 49.90,
                'trial_days' => 0,
                'max_instances' => 3,
                'max_customers' => 5000,
                'max_invoices_month' => 15000,
                'max_messages_month' => 30000,
                'custom_gateway' => true,
                'custom_templates' => true,
                'installment_enabled' => true,
                'fee_passthrough' => true,
                'features' => json_encode(['Multi-conexões avançadas', 'Prioridade de envio ultra rápida', 'Suporte VIP via WhatsApp', 'Integrações nativas robustas']),
                'active' => true,
                'sort' => 3,
                'is_demo' => false,
                'highlight' => false,
                'badge' => null
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solução definitiva para grandes operações.',
                'price' => 497.00,
                'extra_instance_price' => 59.90,
                'trial_days' => 0,
                'max_instances' => 5,
                'max_customers' => 10000,
                'max_invoices_month' => 50000,
                'max_messages_month' => 100000,
                'custom_gateway' => true,
                'custom_templates' => true,
                'installment_enabled' => true,
                'fee_passthrough' => true,
                'features' => json_encode(['WAF Security Enterprise', 'Conta multi-usuário', 'Personalização White-label (sua logo)', 'Gerente de contas dedicado']),
                'active' => true,
                'sort' => 4,
                'is_demo' => false,
                'highlight' => false,
                'badge' => null
            ]
        ];

        foreach ($plans as $p) {
            DB::table('plans')->insert(array_merge($p, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 10 Testimonials
        $testimonials = [
            ['name' => 'Carlos Almeida', 'role' => 'CEO da TechNova', 'rating' => 5, 'content' => 'O sistema revolucionou nossa forma de cobranca. A reducao de inadimplencia foi visivel no primeiro mes!', 'avatar' => 'https://ui-avatars.com/api/?name=Carlos+Almeida&background=random'],
            ['name' => 'Mariana Souza', 'role' => 'Gestora Financeira', 'rating' => 5, 'content' => 'Interface incrivel e facil de usar. Nossos clientes elogiam a facilidade do checkout transparente.', 'avatar' => 'https://ui-avatars.com/api/?name=Mariana+Souza&background=random'],
            ['name' => 'Roberto Justos', 'role' => 'Empreendedor Digital', 'rating' => 4, 'content' => 'Excelente plataforma. Os lembretes automaticos via WhatsApp me economizam horas de trabalho manual.', 'avatar' => 'https://ui-avatars.com/api/?name=Roberto+Justos&background=random'],
            ['name' => 'Fernanda Silva', 'role' => 'Diretora Comercial', 'rating' => 5, 'content' => 'O suporte e excepcional e a plataforma extremamente estavel. Recomendo para qualquer negocio.', 'avatar' => 'https://ui-avatars.com/api/?name=Fernanda+Silva&background=random'],
            ['name' => 'Lucas Peixoto', 'role' => 'Dono de Agencia', 'rating' => 5, 'content' => 'Integramos com nosso WHMCS e foi perfeito. Faturamento automatico rodando liso sem travamentos.', 'avatar' => 'https://ui-avatars.com/api/?name=Lucas+Peixoto&background=random'],
            ['name' => 'Amanda Costa', 'role' => 'Advogada', 'rating' => 5, 'content' => 'Garante pontualidade nos honorarios. Os templates de mensagens personalizaveis sao otimos e muito profissionais.', 'avatar' => 'https://ui-avatars.com/api/?name=Amanda+Costa&background=random'],
            ['name' => 'Thiago Reis', 'role' => 'Dono de Provedor ISP', 'rating' => 5, 'content' => 'Lida com milhares de boletos mensais sem suar. A integracao multi-gateway salvou nossas operacoes.', 'avatar' => 'https://ui-avatars.com/api/?name=Thiago+Reis&background=random'],
            ['name' => 'Renata Lima', 'role' => 'Contadora', 'rating' => 4, 'content' => 'Muito bom. Senti falta de alguns relatorios no inicio, mas as ultimas atualizacoes trouxeram tudo que eu precisava.', 'avatar' => 'https://ui-avatars.com/api/?name=Renata+Lima&background=random'],
            ['name' => 'Gabriel Farias', 'role' => 'Consultor de TI', 'rating' => 5, 'content' => 'A API publica e muito bem documentada. Integrei com N8N em questao de minutos.', 'avatar' => 'https://ui-avatars.com/api/?name=Gabriel+Farias&background=random'],
            ['name' => 'Juliana Martins', 'role' => 'Designer Freelancer', 'rating' => 5, 'content' => 'Receber por PIX automatico direto na minha conta e confirmar na hora para o cliente... nao tem preco!', 'avatar' => 'https://ui-avatars.com/api/?name=Juliana+Martins&background=random'],
        ];

        foreach ($testimonials as $i => $t) {
            DB::table('testimonials')->insert(array_merge($t, [
                'source' => 'manual',
                'active' => true,
                'sort' => $i,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 10 Slides (Usando Unsplash para placeholders premium)
        $slides = [
            ['title' => 'Revolucione suas Cobrancas', 'subtitle' => 'Automação inteligente via WhatsApp para reduzir a inadimplencia a zero.', 'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop', 'cta_label' => 'Comece Agora', 'cta_url' => '/cadastro'],
            ['title' => 'Checkout Transparente', 'subtitle' => 'Mercado Pago, Efí e Pix. Tudo integrado sem redirecionamentos.', 'image' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=2070&auto=format&fit=crop', 'cta_label' => 'Ver Integrações', 'cta_url' => '/recursos'],
            ['title' => 'Gestão Centralizada', 'subtitle' => 'Acompanhe métricas, relatórios e envios de mensagens em tempo real.', 'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=2015&auto=format&fit=crop', 'cta_label' => 'Nossos Planos', 'cta_url' => '/pacotes'],
            ['title' => 'WAF de Segurança', 'subtitle' => 'Seus dados protegidos contra ameaças virtuais 24/7.', 'image' => 'https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?q=80&w=2070&auto=format&fit=crop', 'cta_label' => 'Saber Mais', 'cta_url' => '/sobre'],
            ['title' => 'API Pública e Webhooks', 'subtitle' => 'Conecte com Zapier, N8N, ou seu sistema próprio facilmente.', 'image' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=2070&auto=format&fit=crop', 'cta_label' => 'Documentação', 'cta_url' => '/docs'],
            ['title' => 'Multi-Instâncias WhatsApp', 'subtitle' => 'Opere múltiplos números e filiais na mesma plataforma.', 'image' => 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?q=80&w=1974&auto=format&fit=crop', 'cta_label' => 'Fale Conosco', 'cta_url' => '/contato'],
            ['title' => 'Notificações Instantâneas', 'subtitle' => 'Você e seus clientes sempre informados de qualquer movimentação.', 'image' => 'https://images.unsplash.com/photo-1512428559087-560fa5ceab42?q=80&w=2070&auto=format&fit=crop', 'cta_label' => 'Recursos', 'cta_url' => '/recursos'],
            ['title' => 'Personalização White Label', 'subtitle' => 'Sua marca, suas cores. Entregue valor com a sua identidade.', 'image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=2072&auto=format&fit=crop', 'cta_label' => 'Ver Casos', 'cta_url' => '/sobre'],
            ['title' => 'Templates Inteligentes', 'subtitle' => 'Variáveis dinâmicas para criar mensagens que convertem.', 'image' => 'https://images.unsplash.com/photo-1432888622747-4eb9a8f2c1d9?q=80&w=2074&auto=format&fit=crop', 'cta_label' => 'Testar Grátis', 'cta_url' => '/cadastro'],
            ['title' => 'App PWA Nativo', 'subtitle' => 'Instale e use direto do seu smartphone, com notificações push.', 'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?q=80&w=2070&auto=format&fit=crop', 'cta_label' => 'Instalar App', 'cta_url' => '/'],
        ];

        foreach ($slides as $i => $s) {
            DB::table('slides')->insert(array_merge($s, [
                'animation' => 'fade',
                'active' => true,
                'sort' => $i,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 10 Popups
        $popups = [
            ['name' => 'Desconto de Boas-vindas', 'trigger' => 'time', 'trigger_value' => 10, 'html' => '<h2>10% OFF!</h2><p>Assine hoje e ganhe 10% de desconto no plano Anual.</p>', 'cta_label' => 'Garantir Desconto', 'cta_url' => '/pacotes'],
            ['name' => 'Ebook Grátis', 'trigger' => 'exit', 'trigger_value' => 0, 'html' => '<h2>Nao va ainda!</h2><p>Baixe nosso guia gratuito de como reduzir a inadimplencia em 50%.</p>', 'cta_label' => 'Baixar Ebook', 'cta_url' => '/blog'],
            ['name' => 'Webinar Amanhã', 'trigger' => 'scroll', 'trigger_value' => 50, 'html' => '<h2>Participe do nosso Webinar!</h2><p>Descubra como integrar APIs no N8N com o nosso sistema.</p>', 'cta_label' => 'Inscrever-se', 'cta_url' => '/contato'],
            ['name' => 'Black Friday', 'trigger' => 'time', 'trigger_value' => 5, 'html' => '<h2>Oferta Black Friday</h2><p>Todos os planos com 50% de desconto no primeiro mes!</p>', 'cta_label' => 'Ver Oferta', 'cta_url' => '/pacotes'],
            ['name' => 'Suporte Prioritário', 'trigger' => 'click', 'trigger_value' => 0, 'html' => '<h2>Precisa de Ajuda?</h2><p>Nossa equipe esta pronta para ajudar na sua migracao.</p>', 'cta_label' => 'Falar no WhatsApp', 'cta_url' => '#'],
            ['name' => 'Novos Recursos', 'trigger' => 'time', 'trigger_value' => 15, 'html' => '<h2>Novidade!</h2><p>Agora aceitamos parcelamento em ate 12x no cartao.</p>', 'cta_label' => 'Saber Mais', 'cta_url' => '/recursos'],
            ['name' => 'Feedback', 'trigger' => 'exit', 'trigger_value' => 0, 'html' => '<h2>Deixe sua opiniao</h2><p>Ajude-nos a melhorar. Leva so um minuto.</p>', 'cta_label' => 'Dar Feedback', 'cta_url' => '/contato'],
            ['name' => 'Parcerias', 'trigger' => 'scroll', 'trigger_value' => 80, 'html' => '<h2>Seja um parceiro</h2><p>Agencias e Contadores tem beneficios exclusivos.</p>', 'cta_label' => 'Seja Parceiro', 'cta_url' => '/sobre'],
            ['name' => 'Demo Exclusiva', 'trigger' => 'time', 'trigger_value' => 30, 'html' => '<h2>Agende uma Demo</h2><p>Vamos te mostrar como o sistema se adapta ao seu negocio.</p>', 'cta_label' => 'Agendar', 'cta_url' => '/contato'],
            ['name' => 'Alerta de Manutencao', 'trigger' => 'time', 'trigger_value' => 2, 'html' => '<h2>Aviso</h2><p>Teremos manutencao programada no sabado a noite.</p>', 'cta_label' => 'Ler Mais', 'cta_url' => '/blog'],
        ];

        foreach ($popups as $p) {
            DB::table('popups')->insert(array_merge($p, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
