import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';
import { motion } from 'framer-motion';
import { Target, CheckCircle2, FileText, Smartphone, CreditCard, BarChart3 } from 'lucide-react';

export default function Funil() {
    const site = useSite();
    const page = site?.pages?.funil || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Funil de Cobranca Automatico',
                    title: 'Do lead ao pagamento, sem perder ninguem.',
                    description: 'Acompanhe cada etapa do seu funil em tempo real, identifique gargalos e aumente a conversao com automacoes.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 min-h-screen pt-24 pb-32 text-slate-300 relative overflow-hidden">
            {/* Background design elements */}
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-[500px] bg-purple-600/10 blur-[150px] rounded-full" />
                <div className="absolute bottom-1/4 right-0 w-[500px] h-[500px] bg-indigo-600/10 blur-[180px] rounded-full" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff08_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <section className="relative py-20 px-4 text-center z-10">
                <div className="max-w-4xl mx-auto">
                    <ScrollReveal>
                        <span className="inline-block px-4 py-1.5 rounded-full bg-purple-500/10 text-purple-400 font-bold tracking-widest uppercase text-xs mb-6 border border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                            Funil de Cobrança Automático
                        </span>
                        <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 text-white">
                            Do lead ao pagamento,<br/>
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-pink-400 to-rose-400">sem perder ninguém.</span>
                        </h1>
                        <p className="text-xl text-slate-400 max-w-2xl mx-auto mb-10 font-medium leading-relaxed">
                            Acompanhe cada etapa do seu funil em tempo real, identifique gargalos e aumente a conversão com automações imbatíveis.
                        </p>
                    </ScrollReveal>
                </div>
            </section>

            <section className="py-20 relative z-10">
                <div className="container mx-auto px-4 max-w-4xl">
                    <ScrollReveal className="text-center mb-16">
                        <h2 className="text-3xl md:text-5xl font-extrabold text-white">Como o sistema funciona em 6 etapas</h2>
                    </ScrollReveal>

                    <div className="space-y-6">
                        {stages.map((s, i) => (
                            <ScrollReveal key={i} delay={i * 0.1}>
                                <div className="p-8 border border-slate-800 bg-slate-900/40 backdrop-blur-xl rounded-[2rem] hover:border-slate-700 hover:bg-slate-800/60 transition-all shadow-lg group">
                                    <div className="flex flex-col md:flex-row items-start gap-8">
                                        <div className={`w-20 h-20 shrink-0 rounded-2xl bg-gradient-to-br ${s.gradient} text-white flex items-center justify-center shadow-[0_0_20px_rgba(0,0,0,0.5)] group-hover:scale-110 transition-transform duration-500`}>
                                            {s.icon}
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-3">
                                                <span className="text-slate-500 font-bold text-xl">0{i+1}.</span>
                                                <h3 className="font-bold text-2xl text-white group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-white group-hover:to-slate-400 transition-all">{s.title}</h3>
                                            </div>
                                            <p className="text-slate-400 font-medium leading-relaxed mb-6">{s.desc}</p>
                                            <div className="flex flex-wrap gap-2">
                                                {s.tags.map((t, j) => (
                                                    <span key={j} className="text-xs font-bold bg-slate-800/50 text-slate-300 px-3 py-1.5 rounded-full border border-slate-700 uppercase tracking-wider">
                                                        {t}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </ScrollReveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* CONVERSAO POR ETAPA */}
            <section className="py-32 relative z-10 border-t border-slate-900 bg-slate-900/20">
                <div className="container mx-auto px-4">
                    <ScrollReveal className="text-center mb-20">
                        <h2 className="text-4xl md:text-5xl font-extrabold tracking-tight mb-4 text-white">Taxa média de conversão</h2>
                        <p className="text-xl text-slate-400 max-w-2xl mx-auto">Nossos clientes reportam esses números após 30 dias de uso focado na recuperação ativa.</p>
                    </ScrollReveal>
                    
                    <div className="max-w-4xl mx-auto space-y-8 bg-slate-900/40 backdrop-blur-md border border-slate-800 p-8 md:p-12 rounded-[2.5rem]">
                        {[
                            { label: 'Lead capturado', pct: 100, color: 'from-blue-400 to-blue-600' },
                            { label: 'Qualificação', pct: 78, color: 'from-cyan-400 to-cyan-600' },
                            { label: 'Proposta enviada', pct: 52, color: 'from-teal-400 to-teal-600' },
                            { label: 'Negociação', pct: 38, color: 'from-emerald-400 to-emerald-600' },
                            { label: 'Fechamento', pct: 24, color: 'from-yellow-400 to-orange-500' },
                            { label: 'Pagamento confirmado', pct: 22, color: 'from-pink-500 to-rose-600' },
                        ].map((s, i) => (
                            <ScrollReveal key={i} delay={i * 0.1}>
                                <div>
                                    <div className="flex justify-between mb-3 text-lg">
                                        <span className="font-bold text-white">{s.label}</span>
                                        <span className="text-slate-400 font-bold">{s.pct}%</span>
                                    </div>
                                    <div className="h-6 bg-slate-950 rounded-full overflow-hidden border border-slate-800 shadow-inner relative">
                                        <motion.div 
                                            initial={{ width: 0 }}
                                            whileInView={{ width: `${s.pct}%` }}
                                            viewport={{ once: true }}
                                            transition={{ duration: 1.5, delay: 0.2 + (i * 0.1), ease: "easeOut" }}
                                            className={`h-full bg-gradient-to-r ${s.color} relative shadow-[0_0_10px_rgba(255,255,255,0.2)]`}
                                        >
                                            <div className="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/diagonal-stripes.png')] opacity-20 mix-blend-overlay"></div>
                                        </motion.div>
                                    </div>
                                </div>
                            </ScrollReveal>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}

const stages = [
    {
        icon: <Target className="w-8 h-8"/>, title: 'Captação do lead',
        desc: 'O cliente preenche um formulário, importa do WHMCS ou você cadastra manualmente. O sistema valida CPF/CNPJ instantaneamente com a Receita Federal.',
        tags: ['Formulários', 'Importação CSV', 'Integração WHMCS'],
        gradient: 'from-blue-500 to-indigo-600',
    },
    {
        icon: <CheckCircle2 className="w-8 h-8"/>, title: 'Qualificação automática',
        desc: 'Sistema verifica os dados, valida o número de WhatsApp ativo através da Evolution API e enriquece o endereço via ViaCEP de forma invisível.',
        tags: ['Validação Anti-Fraude', 'Evolution API', 'Enriquecimento de Dados'],
        gradient: 'from-indigo-500 to-purple-600',
    },
    {
        icon: <FileText className="w-8 h-8"/>, title: 'Geração da Fatura',
        desc: 'Crie faturas avulsas ou configure planos recorrentes com nosso editor rico. Multi-gateway ativado nativamente para Pix e Cartões.',
        tags: ['Fatura Avulsa', 'Assinaturas', 'Multi-gateway'],
        gradient: 'from-purple-500 to-pink-600',
    },
    {
        icon: <Smartphone className="w-8 h-8"/>, title: 'Envio inteligente via WhatsApp',
        desc: 'Utilize templates personalizáveis com variáveis dinâmicas. Disparos programados com antecedência para lembretes ativos e notificações de atraso.',
        tags: ['Templates Dinâmicos', 'Variáveis Livres', 'Cron Job Interno'],
        gradient: 'from-pink-500 to-rose-600',
    },
    {
        icon: <CreditCard className="w-8 h-8"/>, title: 'Pagamento Frictionless',
        desc: 'O cliente recebe o link no WhatsApp, escolhe a forma de pagamento e pronto. O Webhook interno confirma o pagamento e atualiza o status na hora.',
        tags: ['PIX Instantâneo', 'Cartão em 12x', 'Webhooks WebSockets'],
        gradient: 'from-rose-500 to-orange-500',
    },
    {
        icon: <BarChart3 className="w-8 h-8"/>, title: 'Análise e Otimização',
        desc: 'Acesse o dashboard completo com gráficos de faturamento em tempo real, relatórios de conversão e identifique os gargalos exatos da sua operação.',
        tags: ['Analytics em Tempo Real', 'Relatórios Detalhados', 'KPIs de Funil'],
        gradient: 'from-orange-500 to-amber-500',
    },
];
