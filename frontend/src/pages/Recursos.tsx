import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { NeonButton } from '@/components/ui/NeonButton';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';
import { motion } from 'framer-motion';
import {
    Bot, CreditCard, Workflow, Activity, Shield, Smartphone,
    Database, Globe, LayoutDashboard, CheckCircle2, XCircle,
    Zap, Lock, BarChart3, Bell, FileText, Users, Webhook, Cloud,
    ArrowRight
} from 'lucide-react';

const recursos = [
    {
        icon: <Bot className="w-7 h-7" />,
        title: 'Cobrança no Piloto Automático',
        desc: 'Configure réguas de cobrança 100% automatizadas. O sistema envia a mensagem certa, no dia certo, sem você apertar um botão.',
        tags: ['Automação', 'Régua de Cobrança'],
        gradient: 'from-violet-500 to-purple-600',
        glow: 'rgba(139,92,246,0.3)',
    },
    {
        icon: <CreditCard className="w-7 h-7" />,
        title: 'PIX com Baixa Automática',
        desc: 'O cliente clica no link do WhatsApp, paga via PIX, e a fatura é dada como paga no seu painel em 3 segundos via webhook.',
        tags: ['PIX', 'Webhook', 'Instantâneo'],
        gradient: 'from-emerald-500 to-teal-600',
        glow: 'rgba(16,185,129,0.3)',
    },
    {
        icon: <Workflow className="w-7 h-7" />,
        title: 'Mensagens Personalizadas',
        desc: 'Use nosso editor para criar templates usando variáveis como o nome do cliente, saudação por horário, valor e link da fatura.',
        tags: ['Templates', 'Variáveis Dinâmicas'],
        gradient: 'from-blue-500 to-indigo-600',
        glow: 'rgba(59,130,246,0.3)',
    },
    {
        icon: <Activity className="w-7 h-7" />,
        title: 'Dashboard Financeiro',
        desc: 'Visão clara do que foi recebido, o que está em atraso e qual é a sua taxa de conversão diária em tempo real.',
        tags: ['Analytics', 'Tempo Real'],
        gradient: 'from-orange-500 to-amber-600',
        glow: 'rgba(249,115,22,0.3)',
    },
    {
        icon: <Shield className="w-7 h-7" />,
        title: 'Anti-fraude Nativo',
        desc: 'Bloqueie cadastros falsos. O sistema consulta automaticamente a situação cadastral do CPF/CNPJ e busca o endereço.',
        tags: ['Receita Federal', 'ViaCEP'],
        gradient: 'from-red-500 to-rose-600',
        glow: 'rgba(239,68,68,0.3)',
    },
    {
        icon: <Smartphone className="w-7 h-7" />,
        title: 'Aplicativo de Bolso (PWA)',
        desc: 'Acesse o painel do seu celular como um app nativo, receba notificações e gerencie suas cobranças de qualquer lugar.',
        tags: ['PWA', 'Notificações Push'],
        gradient: 'from-pink-500 to-fuchsia-600',
        glow: 'rgba(236,72,153,0.3)',
    },
    {
        icon: <Database className="w-7 h-7" />,
        title: 'Importação Simplificada',
        desc: 'Traga sua base de clientes do Excel ou do WHMCS. O importador mapeia colunas, remove duplicatas e ajusta formatação.',
        tags: ['CSV', 'Excel', 'WHMCS'],
        gradient: 'from-cyan-500 to-sky-600',
        glow: 'rgba(6,182,212,0.3)',
    },
    {
        icon: <Globe className="w-7 h-7" />,
        title: 'White Label Completo',
        desc: 'A plataforma roda no seu domínio. Tudo o que o cliente vê tem as suas cores institucionais e a logomarca da sua empresa.',
        tags: ['Domínio Próprio', 'Marca Própria'],
        gradient: 'from-indigo-500 to-violet-600',
        glow: 'rgba(99,102,241,0.3)',
    },
    {
        icon: <LayoutDashboard className="w-7 h-7" />,
        title: 'Múltiplos Números',
        desc: 'Tem vários setores ou marcas? Conecte várias instâncias do WhatsApp e separe os fluxos de cobrança por departamento.',
        tags: ['Multi-instância', 'Setores'],
        gradient: 'from-lime-500 to-green-600',
        glow: 'rgba(132,204,22,0.3)',
    },
];

const comparativo = [
    { feature: 'Automação de cobrança por WhatsApp', nos: true, outros: false },
    { feature: 'PIX com baixa automática via webhook', nos: true, outros: false },
    { feature: 'WAF próprio com anti-fraude', nos: true, outros: false },
    { feature: 'Dashboard analítico em tempo real', nos: true, outros: true },
    { feature: 'White label no seu domínio', nos: true, outros: false },
    { feature: 'Importação de base via CSV/WHMCS', nos: true, outros: true },
    { feature: 'Editor de templates com variáveis', nos: true, outros: true },
    { feature: 'PWA com notificações push', nos: true, outros: false },
    { feature: 'Múltiplas instâncias de WhatsApp', nos: true, outros: false },
    { feature: 'Backup automático diário', nos: true, outros: false },
];

const destaques = [
    { icon: <Zap className="w-6 h-6" />, label: '3s', sub: 'Confirmação de pagamento' },
    { icon: <Lock className="w-6 h-6" />, label: 'WAF', sub: 'Proteção anti-fraude' },
    { icon: <BarChart3 className="w-6 h-6" />, label: '99.9%', sub: 'Uptime monitorado' },
    { icon: <Bell className="w-6 h-6" />, label: '2.5M', sub: 'Mensagens/mês' },
    { icon: <FileText className="w-6 h-6" />, label: '72%', sub: 'Redução de inadimplência' },
    { icon: <Users className="w-6 h-6" />, label: '3.500+', sub: 'Empresas ativas' },
];

export default function Recursos() {
    const site = useSite();
    const page = site?.pages?.recursos || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Arsenal Completo de Cobranca',
                    title: 'Tudo que voce precisa para receber mais rapido',
                    description: 'Nossa plataforma concentra os modulos essenciais de cobranca, pagamento, analytics e seguranca em um unico lugar.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 min-h-screen text-slate-300 overflow-hidden">
            {/* Background */}
            <div className="fixed inset-0 z-0 pointer-events-none">
                <div className="absolute top-0 left-1/3 w-[700px] h-[700px] bg-violet-600/8 rounded-full blur-[200px]" />
                <div className="absolute bottom-1/4 right-1/4 w-[600px] h-[600px] bg-blue-600/8 rounded-full blur-[180px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff06_1px,transparent_1px)] [background-size:20px_20px]" />
            </div>

            {/* HERO */}
            <section className="relative z-10 pt-32 pb-24 px-4">
                <div className="max-w-5xl mx-auto text-center">
                    <ScrollReveal>
                        <span className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/10 text-violet-400 font-bold tracking-widest uppercase text-xs mb-8 border border-violet-500/20">
                            <Zap className="w-3.5 h-3.5" />
                            Arsenal Completo de Cobrança
                        </span>
                        <h1 className="text-5xl md:text-7xl font-black tracking-tight mb-6 text-white leading-tight">
                            Tudo que você precisa<br />
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 via-purple-400 to-pink-400">
                                para receber mais rápido
                            </span>
                        </h1>
                        <p className="text-xl md:text-2xl text-slate-400 max-w-3xl mx-auto leading-relaxed font-medium mb-12">
                            9 módulos integrados, prontos para uso. Sem configuração complexa, sem integrações quebradas — só resultado na conta.
                        </p>

                        {/* Stats rápidos */}
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 max-w-5xl mx-auto">
                            {destaques.map((d, i) => (
                                <ScrollReveal key={i} delay={i * 0.08}>
                                    <div className="bg-slate-900/60 backdrop-blur-xl border border-slate-800 rounded-2xl p-4 text-center hover:border-violet-500/30 transition-all">
                                        <div className="flex justify-center text-violet-400 mb-2">{d.icon}</div>
                                        <div className="text-2xl font-black text-white">{d.label}</div>
                                        <div className="text-xs text-slate-500 font-medium mt-1">{d.sub}</div>
                                    </div>
                                </ScrollReveal>
                            ))}
                        </div>
                    </ScrollReveal>
                </div>
            </section>

            {/* GRID DE RECURSOS */}
            <section className="relative z-10 py-24 px-4">
                <div className="max-w-7xl mx-auto">
                    <ScrollReveal className="text-center mb-16">
                        <h2 className="text-4xl md:text-5xl font-black text-white mb-4">
                            Módulos da Plataforma
                        </h2>
                        <p className="text-xl text-slate-400 max-w-2xl mx-auto">
                            Cada módulo foi desenhado para resolver um problema real de quem cobra.
                        </p>
                    </ScrollReveal>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {recursos.map((r, i) => (
                            <ScrollReveal key={i} delay={i * 0.07}>
                                <motion.div
                                    transition={{ type: 'spring', stiffness: 300 }}
                                    className="min-h-[380px] bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-[2rem] p-8 group relative overflow-hidden hover:border-slate-600 transition-all flex flex-col"
                                    style={{ boxShadow: `0 0 0 0 ${r.glow}` }}
                                    whileHover={{ boxShadow: `0 20px 60px -15px ${r.glow}`, y: -6, scale: 1.01 } as never}
                                >
                                    {/* Glow de fundo */}
                                    <div className={`absolute -top-10 -right-10 w-40 h-40 bg-gradient-to-br ${r.gradient} rounded-full blur-[80px] opacity-0 group-hover:opacity-20 transition-opacity duration-500`} />

                                    <div className={`w-14 h-14 rounded-2xl bg-gradient-to-br ${r.gradient} flex items-center justify-center text-white mb-6 shadow-lg group-hover:scale-110 transition-transform duration-300`}>
                                        {r.icon}
                                    </div>
                                    <h3 className="text-xl font-bold text-white mb-3">{r.title}</h3>
                                    <p className="text-slate-400 leading-relaxed text-sm mb-6">{r.desc}</p>
                                    <div className="flex flex-wrap gap-2">
                                        {r.tags.map((tag, j) => (
                                            <span key={j} className="text-xs font-bold bg-slate-800 text-slate-400 px-3 py-1 rounded-full border border-slate-700">
                                                {tag}
                                            </span>
                                        ))}
                                    </div>
                                </motion.div>
                            </ScrollReveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* COMPARATIVO */}
            <section className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-4xl mx-auto">
                    <ScrollReveal className="text-center mb-16">
                        <span className="inline-block px-4 py-1.5 rounded-full bg-emerald-500/10 text-emerald-400 font-bold tracking-widest uppercase text-xs mb-6 border border-emerald-500/20">
                            Comparativo honesto
                        </span>
                        <h2 className="text-4xl md:text-5xl font-black text-white mb-4">
                            Nós vs. Concorrentes
                        </h2>
                        <p className="text-xl text-slate-400">
                            Veja o que ninguém mais oferece no mesmo pacote.
                        </p>
                    </ScrollReveal>

                    <ScrollReveal>
                        <div className="bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-[2.5rem] overflow-hidden shadow-2xl">
                            {/* Header */}
                            <div className="grid grid-cols-3 bg-slate-800/50 px-8 py-5 border-b border-slate-700/50">
                                <div className="text-slate-400 font-bold text-sm uppercase tracking-wider">Funcionalidade</div>
                                <div className="text-center">
                                    <span className="inline-flex items-center gap-2 bg-gradient-to-r from-violet-600 to-purple-600 text-white px-4 py-1.5 rounded-full text-sm font-black shadow-[0_0_20px_rgba(139,92,246,0.4)]">
                                        <Zap className="w-3.5 h-3.5" /> Nossa Plataforma
                                    </span>
                                </div>
                                <div className="text-center text-slate-500 font-bold text-sm">Concorrentes</div>
                            </div>
                            {comparativo.map((item, i) => (
                                <div key={i} className={`grid grid-cols-3 px-8 py-4 border-b border-slate-800/50 ${i % 2 === 0 ? 'bg-transparent' : 'bg-slate-900/30'} hover:bg-slate-800/30 transition-colors`}>
                                    <div className="text-slate-300 font-medium text-sm flex items-center">{item.feature}</div>
                                    <div className="flex justify-center items-center">
                                        {item.nos
                                            ? <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                                            : <XCircle className="w-5 h-5 text-red-500" />}
                                    </div>
                                    <div className="flex justify-center items-center">
                                        {item.outros
                                            ? <CheckCircle2 className="w-5 h-5 text-slate-500" />
                                            : <XCircle className="w-5 h-5 text-red-500/50" />}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </ScrollReveal>
                </div>
            </section>

            {/* INTEGRAÇÕES */}
            <section className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-5xl mx-auto text-center">
                    <ScrollReveal className="mb-16">
                        <span className="inline-block px-4 py-1.5 rounded-full bg-blue-500/10 text-blue-400 font-bold tracking-widest uppercase text-xs mb-6 border border-blue-500/20">
                            Ecossistema aberto
                        </span>
                        <h2 className="text-4xl md:text-5xl font-black text-white mb-4">
                            Integra com o que você já usa
                        </h2>
                        <p className="text-xl text-slate-400 max-w-2xl mx-auto">
                            API aberta e webhooks prontos para conectar qualquer ferramenta do seu stack.
                        </p>
                    </ScrollReveal>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {[
                            { icon: <Webhook className="w-8 h-8" />, label: 'Evolution API', sub: 'WhatsApp oficial' },
                            { icon: <CreditCard className="w-8 h-8" />, label: 'Mercado Pago', sub: 'PIX e cartões' },
                            { icon: <Cloud className="w-8 h-8" />, label: 'N8N / Zapier', sub: 'Automações' },
                            { icon: <Database className="w-8 h-8" />, label: 'WHMCS', sub: 'Importação direta' },
                        ].map((int, i) => (
                            <ScrollReveal key={i} delay={i * 0.1}>
                                <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6 flex flex-col items-center gap-3 hover:border-slate-600 hover:bg-slate-800/50 transition-all group">
                                    <div className="text-blue-400 group-hover:scale-110 transition-transform">{int.icon}</div>
                                    <div className="font-bold text-white text-sm">{int.label}</div>
                                    <div className="text-slate-500 text-xs">{int.sub}</div>
                                </div>
                            </ScrollReveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="relative z-10 py-24 px-4">
                <div className="max-w-4xl mx-auto">
                    <ScrollReveal>
                        <div className="relative rounded-[2.5rem] overflow-hidden p-12 md:p-20 text-center border border-violet-500/20 bg-gradient-to-br from-violet-900/30 via-slate-900 to-purple-900/20">
                            <div className="absolute inset-0 bg-gradient-to-br from-violet-600/10 to-purple-600/5 pointer-events-none" />
                            <div className="absolute inset-0 bg-[radial-gradient(#8b5cf620_1px,transparent_1px)] [background-size:20px_20px] pointer-events-none" />
                            <div className="relative z-10">
                                <h2 className="text-4xl md:text-5xl font-black text-white mb-6">
                                    Pronto para parar de perseguir pagamentos?
                                </h2>
                                <p className="text-xl text-slate-400 mb-10 max-w-2xl mx-auto">
                                    Teste grátis por 14 dias. Sem cartão de crédito. Cancele quando quiser.
                                </p>
                                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                    <NeonButton href="/pacotes" color="indigo">
                                        <Zap className="w-4 h-4 mr-2" />
                                        Começar Teste Grátis
                                    </NeonButton>
                                    <NeonButton href="/contato" color="blue">
                                        <ArrowRight className="w-4 h-4 mr-2" />
                                        Falar com Vendas
                                    </NeonButton>
                                </div>
                            </div>
                        </div>
                    </ScrollReveal>
                </div>
            </section>
        </div>
    );
}
