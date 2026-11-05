import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

export default function Termos() {
    const site = useSite();
    const page = site?.pages?.termos || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Atualizado em Maio de 2026',
                    title: 'Termos de Uso',
                    description: 'Ao acessar e utilizar nossa plataforma de cobranca automatizada via WhatsApp, voce concorda e se compromete a cumprir integralmente os seguintes Termos de Uso.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 text-slate-300 min-h-screen pt-32 pb-32 relative overflow-hidden">
            {/* Background blobs */}
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[150px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff04_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <div className="container mx-auto px-4 max-w-4xl relative z-10">
                <ScrollReveal className="mb-16">
                    <span className="inline-block bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                        Atualizado em Maio de 2026
                    </span>
                    <h1 className="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-6">
                        Termos de Uso
                    </h1>
                    <p className="text-slate-400 font-medium leading-relaxed text-lg">
                        Ao acessar e utilizar nossa plataforma de cobrança automatizada via WhatsApp, você concorda e se compromete a cumprir integralmente os seguintes Termos de Uso. Por favor, leia-os atentamente.
                    </p>
                </ScrollReveal>

                <ScrollReveal className="space-y-12 prose prose-invert max-w-none">
                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">1. Aceitação dos Termos</h2>
                        <p className="leading-relaxed font-medium text-slate-400">
                            A utilização do nosso software constitui sua aceitação tácita a estes Termos e a nossa Política de Privacidade. Reservamo-nos o direito de alterar estes termos periodicamente, notificando os usuários ativos por e-mail ou banner na plataforma.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">2. Cadastro e Acesso à Plataforma</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Para usufruir de nossos planos e funcionalidades:
                        </p>
                        <ul className="list-disc pl-6 space-y-2 text-slate-400 font-medium">
                            <li>Você é responsável por fornecer informações cadastrais verídicas e atualizadas.</li>
                            <li>A segurança da senha da sua conta é de sua inteira responsabilidade, devendo nos alertar caso suspeite de vazamentos.</li>
                            <li>O acesso é pessoal e intransferível (limitado ao escopo de usuários de cada plano contratado).</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">3. Limites de Uso e Abuso de Envio</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Como o sistema realiza disparos de mensagens automatizadas no WhatsApp através de conexões API:
                        </p>
                        <ul className="list-disc pl-6 space-y-2 text-slate-400 font-medium">
                            <li><strong>Proibição de SPAM:</strong> É expressamente proibido o uso da plataforma para o disparo em massa de mensagens não solicitadas (SPAM), venda de listas de contatos ou conteúdo ilegal.</li>
                            <li><strong>Responsabilidade da Conexão:</strong> O usuário é o único responsável pela saúde de sua linha telefônica junto ao WhatsApp Inc. O sistema não se responsabiliza por eventuais banimentos causados por mau uso da API.</li>
                            <li>O desrespeito a esta regra causará a rescisão imediata do contrato, sem direito a reembolsos.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">4. Faturamento, Planos e Cancelamento</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Nosso faturamento ocorre em regime pré-pago (mensal ou anual):
                        </p>
                        <ul className="list-disc pl-6 space-y-2 text-slate-400 font-medium">
                            <li>O acesso às instâncias e disparo de mensagens é mantido enquanto a assinatura do plano estiver ativa e adimplente.</li>
                            <li>O cancelamento pode ser efetuado a qualquer momento diretamente pela área do cliente, sem incidência de multas de fidelidade.</li>
                            <li>Reembolsos serão processados exclusivamente nos primeiros 7 dias após a compra inicial, em conformidade com o Código de Defesa do Consumidor (CDC).</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">5. Propriedade Intelectual</h2>
                        <p className="leading-relaxed font-medium text-slate-400">
                            A estrutura, código fonte, design das interfaces, logos, marcas e fluxos de trabalho são propriedade intelectual exclusiva de nossa empresa. É vedada qualquer engenharia reversa, cópia ou distribuição do código fonte proprietário, resguardado os limites legais das licenças adicionais.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">6. Limitação de Responsabilidade</h2>
                        <p className="leading-relaxed font-medium text-slate-400">
                            Não seremos responsáveis por danos indiretos, lucros cessantes, interrupção de negócios ou perda de dados resultantes do mau funcionamento temporário do WhatsApp, atrasos de webhooks de pagamento de terceiros ou falhas gerais de conectividade do servidor do cliente.
                        </p>
                    </section>
                </ScrollReveal>
            </div>
        </div>
    );
}
