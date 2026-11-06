import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

export default function Privacidade() {
    const site = useSite();
    const page = site?.pages?.privacidade || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Atualizado em Maio de 2026',
                    title: 'Politica de Privacidade',
                    description: 'Sua privacidade e extremamente importante para nos. Esta politica descreve como coletamos, usamos, armazenamos e protegemos seus dados em nossa plataforma de cobranca automatizada via WhatsApp.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 text-slate-300 min-h-screen pt-32 pb-32 relative overflow-hidden">
            {/* Background blobs */}
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-blue-600/5 rounded-full blur-[150px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff04_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <div className="container mx-auto px-4 max-w-4xl relative z-10">
                <ScrollReveal className="mb-16">
                    <span className="inline-block bg-blue-500/10 text-blue-400 border border-blue-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                        Atualizado em Maio de 2026
                    </span>
                    <h1 className="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-6">
                        Política de Privacidade
                    </h1>
                    <p className="text-slate-400 font-medium leading-relaxed text-lg">
                        Sua privacidade é extremamente importante para nós. Esta política descreve como coletamos, usamos, armazenamos e protegemos seus dados em nossa plataforma de cobrança automatizada via WhatsApp.
                    </p>
                </ScrollReveal>

                <ScrollReveal className="space-y-12 prose prose-invert max-w-none">
                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">1. Coleta de Informações</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Coletamos informações necessárias para a prestação de serviços de cobrança e gerenciamento de faturas, incluindo:
                        </p>
                        <ul className="list-disc pl-6 space-y-2 text-slate-400 font-medium">
                            <li><strong>Dados Cadastrais:</strong> Nome, CPF/CNPJ, Razão Social, e-mail, telefone e endereço.</li>
                            <li><strong>Dados Financeiros:</strong> Chaves PIX, credenciais encriptadas de gateways integrados por você, faturas geradas e histórico de transações.</li>
                            <li><strong>Dados de Integração:</strong> Credenciais da WhatsApp Cloud API da Meta conectadas à sua conta para envio oficial de mensagens.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">2. Uso dos Dados</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Seus dados e de seus clientes finais são utilizados estritamente para:
                        </p>
                        <ul className="list-disc pl-6 space-y-2 text-slate-400 font-medium">
                            <li>Garantir o processamento correto e automação dos envios de faturas no WhatsApp.</li>
                            <li>Processar transações e confirmar pagamentos via webhooks em tempo real.</li>
                            <li>Melhorar continuamente a estabilidade, segurança e experiência da nossa plataforma.</li>
                            <li>Garantir a conformidade legal com a Lei Geral de Proteção de Dados (LGPD).</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">3. Compartilhamento com Terceiros</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Não vendemos, trocamos ou transferimos dados para terceiros para fins de marketing. O compartilhamento ocorre única e exclusivamente com parceiros tecnológicos essenciais (gateways de pagamentos configurados por você, infraestrutura de nuvem segura e serviços de mensageria contratados pelo tenant).
                        </p>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">4. Segurança de Dados</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Adotamos as melhores práticas técnicas de segurança do mercado:
                        </p>
                        <ul className="list-disc pl-6 space-y-2 text-slate-400 font-medium">
                            <li>Encriptação de ponta a ponta em trânsito (SSL/TLS).</li>
                            <li>Criptografia de chaves sensíveis e tokens de API no banco de dados.</li>
                            <li>Firewall de aplicação Web (WAF) ativo contra invasões e robôs maliciosos.</li>
                            <li>Backups periódicos e isolamento de banco de dados por tenant.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">5. Seus Direitos (LGPD)</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Você, como titular de dados pessoais, tem o direito de solicitar a qualquer momento a confirmação de existência de tratamento, acesso aos dados, correção de dados incompletos ou inexatos, e a exclusão definitiva dos seus dados de nossa base de dados ativa.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">6. Contato</h2>
                        <p className="leading-relaxed font-medium text-slate-400">
                            Em caso de dúvidas ou solicitações referentes à sua privacidade, por favor, entre em contato diretamente pelo nosso canal oficial de suporte em <a href="mailto:privacidade@seudominio.com" className="text-indigo-400 hover:underline">privacidade@seudominio.com</a>.
                        </p>
                    </section>
                </ScrollReveal>
            </div>
        </div>
    );
}
