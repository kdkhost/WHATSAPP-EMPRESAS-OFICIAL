import { motion } from 'framer-motion';
import { Code2, Webhook, Link2, ArrowRight, Globe, RefreshCw, Database, Shield } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

const integrations = [
    {
        icon: '⚡',
        name: 'N8N',
        category: 'Automação',
        desc: 'Crie fluxos automatizados conectando nossa API diretamente ao N8N. Dispare cobranças, sincronize clientes e muito mais.',
        color: 'from-orange-500 to-red-500',
        badge: 'Popular',
    },
    {
        icon: '⚙️',
        name: 'Zapier',
        category: 'Automação',
        desc: 'Conecte nossa plataforma a mais de 6.000 apps via Zapier sem precisar escrever uma linha de código.',
        color: 'from-orange-400 to-yellow-500',
        badge: null,
    },
    {
        icon: '📦',
        name: 'WHMCS',
        category: 'Hospedagem',
        desc: 'Integração nativa com WHMCS para provedores de hospedagem e internet. Sincronize faturas automaticamente.',
        color: 'from-blue-500 to-cyan-500',
        badge: 'Nativo',
    },
    {
        icon: '💳',
        name: 'Mercado Pago',
        category: 'Pagamentos',
        desc: 'PIX, boleto e cartão com checkout transparente. Sem redirecionamentos externos.',
        color: 'from-sky-400 to-blue-600',
        badge: 'Nativo',
    },
    {
        icon: '🏦',
        name: 'Efí (Gerencianet)',
        category: 'Pagamentos',
        desc: 'Cobranças via PIX com QR Code dinâmico, boleto e parcelamento no cartão via Efí Bank.',
        color: 'from-green-400 to-emerald-600',
        badge: 'Nativo',
    },
    {
        icon: '💰',
        name: 'SumUp',
        category: 'Pagamentos',
        desc: 'Aceite pagamentos com SumUp. Ideal para pequenos negócios com taxas competitivas.',
        color: 'from-violet-500 to-purple-700',
        badge: null,
    },
    {
        icon: '🔗',
        name: 'Webhooks',
        category: 'Desenvolvedor',
        desc: 'Receba notificações em tempo real sobre pagamentos, vencimentos e status de envios de mensagens.',
        color: 'from-slate-400 to-slate-600',
        badge: null,
    },
    {
        icon: '📊',
        name: 'Google Analytics',
        category: 'Analytics',
        desc: 'Configure seu ID do GA4 e acompanhe conversões, origens de tráfego e comportamento no painel.',
        color: 'from-yellow-400 to-orange-500',
        badge: null,
    },
];

const apiFeatures = [
    { icon: Shield, label: 'Autenticação via Bearer Token' },
    { icon: RefreshCw, label: 'Rate limiting inteligente' },
    { icon: Globe, label: 'Endpoints RESTful JSON' },
    { icon: Database, label: 'Webhooks em tempo real' },
    { icon: Code2, label: 'Exemplos oficiais em JS e PHP' },
    { icon: Webhook, label: 'Retry automático em falhas' },
];

const fadeUp = {
    hidden: { opacity: 0, y: 30 },
    show: (i: number) => ({ opacity: 1, y: 0, transition: { delay: i * 0.08, duration: 0.5 } }),
};

export default function Integracoes() {
    const site = useSite();
    const page = site?.pages?.integracoes || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Ecossistema de Integracoes',
                    title: 'Conecte tudo. Automatize tudo.',
                    description: 'Nossa API aberta e integracoes nativas permitem conectar a plataforma ao seu ecossistema existente em minutos.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 text-white min-h-screen pt-24 overflow-hidden">
            {/* Background */}
            <div className="pointer-events-none fixed inset-0 z-0">
                <div className="absolute top-0 left-1/3 w-[600px] h-[600px] bg-indigo-600/8 rounded-full blur-[160px]" />
                <div className="absolute bottom-0 right-1/4 w-[500px] h-[500px] bg-purple-600/8 rounded-full blur-[150px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff06_1px,transparent_1px)] [background-size:20px_20px]" />
            </div>

            {/* HERO */}
            <section className="relative z-10 py-24 px-4 text-center">
                <div className="max-w-4xl mx-auto">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}
                        className="inline-flex items-center gap-2 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider"
                    >
                        <Link2 className="w-3.5 h-3.5" /> Ecossistema de Integrações
                    </motion.div>
                    <motion.h1
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}
                        className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-tight"
                    >
                        Conecte tudo.<br />
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400">
                            Automatize tudo.
                        </span>
                    </motion.h1>
                    <motion.p
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }}
                        className="text-lg text-slate-400 max-w-2xl mx-auto mb-12 font-medium"
                    >
                        Nossa API aberta e integrações nativas permitem conectar a plataforma ao seu ecossistema existente em minutos, não semanas.
                    </motion.p>
                    <motion.div
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }}
                        className="flex flex-wrap gap-4 justify-center"
                    >
                        <Link to="/cadastro" className="px-8 py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-full shadow-[0_0_30px_rgba(99,102,241,0.3)] hover:shadow-[0_0_40px_rgba(99,102,241,0.5)] transition-all flex items-center gap-2">
                            Começar Grátis <ArrowRight className="w-4 h-4" />
                        </Link>
                        <a href="#api" className="px-8 py-4 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-full border border-slate-700 transition-all flex items-center gap-2">
                            <Code2 className="w-4 h-4" /> Ver Documentação
                        </a>
                    </motion.div>
                </div>
            </section>

            {/* INTEGRAÇÕES GRID */}
            <section className="relative z-10 py-24 px-4">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-16">
                        <h2 className="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Integrações Disponíveis</h2>
                        <p className="text-slate-400 text-lg font-medium">Conecte-se às ferramentas que você já usa no seu dia a dia.</p>
                    </div>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {integrations.map((item, i) => (
                            <motion.div
                                key={item.name}
                                custom={i} variants={fadeUp} initial="hidden" whileInView="show" viewport={{ once: true }}
                                className="relative rounded-3xl p-6 bg-slate-900/50 backdrop-blur border border-slate-800 hover:border-slate-600 transition-all group"
                            >
                                {item.badge && (
                                    <span className="absolute top-4 right-4 text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        {item.badge}
                                    </span>
                                )}
                                <div className={`w-14 h-14 rounded-2xl bg-gradient-to-br ${item.color} flex items-center justify-center text-2xl mb-5 group-hover:scale-110 transition-transform shadow-lg`}>
                                    {item.icon}
                                </div>
                                <div className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">{item.category}</div>
                                <h3 className="text-xl font-bold text-white mb-3">{item.name}</h3>
                                <p className="text-slate-400 text-sm leading-relaxed font-medium">{item.desc}</p>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* API SECTION */}
            <section id="api" className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-6xl mx-auto">
                    <div className="grid lg:grid-cols-2 gap-16 items-center">
                        <div>
                            <div className="inline-flex items-center gap-2 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                                <Code2 className="w-3.5 h-3.5" /> API REST Pública
                            </div>
                            <h2 className="text-4xl md:text-5xl font-extrabold tracking-tight mb-6">
                                Construa em cima<br />
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">da nossa plataforma</span>
                            </h2>
                            <p className="text-slate-400 text-lg font-medium mb-10 leading-relaxed">
                                Nossa API RESTful é documentada, versionada e segura. Gerencie clientes, faturas, mensagens e muito mais programaticamente.
                            </p>
                            <div className="grid grid-cols-2 gap-4">
                                {apiFeatures.map(({ icon: Icon, label }) => (
                                    <div key={label} className="flex items-center gap-3 text-sm font-semibold text-slate-300">
                                        <div className="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
                                            <Icon className="w-4 h-4 text-emerald-400" />
                                        </div>
                                        {label}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Code block */}
                        <div className="rounded-3xl bg-slate-900 border border-slate-800 overflow-hidden shadow-2xl">
                            <div className="flex items-center gap-2 px-5 py-4 border-b border-slate-800 bg-slate-900/80">
                                <div className="w-3 h-3 rounded-full bg-red-500/60" />
                                <div className="w-3 h-3 rounded-full bg-yellow-500/60" />
                                <div className="w-3 h-3 rounded-full bg-emerald-500/60" />
                                <span className="ml-3 text-xs font-mono text-slate-500">POST /api/v1/invoices</span>
                            </div>
                            <pre className="p-6 text-sm font-mono text-slate-300 overflow-x-auto leading-relaxed">
{`curl -X POST https://app.com/api/v1/invoices \\
  -H "Authorization: Bearer {seu_token}" \\
  -H "Content-Type: application/json" \\
  -d '{
    "customer_id": 42,
    "amount": 297.00,
    "due_date": "2025-06-15",
    "description": "Mensalidade Pro",
    "send_whatsapp": true
  }'

// Resposta 201 Created
{
  "id": 1847,
  "status": "pending",
  "payment_url": "https://..."
}`}
                            </pre>
                        </div>
                    </div>
                </div>
            </section>

            {/* WEBHOOKS */}
            <section className="relative z-10 py-24 px-4">
                <div className="max-w-4xl mx-auto text-center">
                    <div className="inline-flex items-center gap-2 bg-purple-500/10 text-purple-400 border border-purple-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                        <Webhook className="w-3.5 h-3.5" /> Webhooks em Tempo Real
                    </div>
                    <h2 className="text-4xl font-extrabold mb-6">Seja notificado instantaneamente</h2>
                    <p className="text-slate-400 text-lg font-medium mb-12 max-w-2xl mx-auto">
                        Configure um endpoint e receba eventos em tempo real: pagamento confirmado, mensagem entregue, fatura vencida e muito mais.
                    </p>
                    <div className="grid sm:grid-cols-3 gap-6 text-left">
                        {[
                            { event: 'invoice.paid', desc: 'Dispara quando um pagamento é confirmado pelo gateway.' },
                            { event: 'message.delivered', desc: 'Confirma que a mensagem WhatsApp chegou ao destinatário.' },
                            { event: 'invoice.overdue', desc: 'Alerta quando uma fatura passa da data de vencimento.' },
                        ].map(({ event, desc }) => (
                            <div key={event} className="p-6 rounded-2xl bg-slate-900/60 border border-slate-800">
                                <code className="text-indigo-400 text-sm font-mono font-bold">{event}</code>
                                <p className="text-slate-400 text-sm mt-2 leading-relaxed">{desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-3xl mx-auto text-center">
                    <h2 className="text-4xl font-extrabold mb-4">Pronto para integrar?</h2>
                    <p className="text-slate-400 text-lg font-medium mb-10">Crie sua conta grátis e obtenha suas credenciais de API em segundos.</p>
                    <div className="flex flex-wrap gap-4 justify-center">
                        <Link to="/cadastro" className="px-8 py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-full shadow-[0_0_30px_rgba(99,102,241,0.3)] transition-all flex items-center gap-2">
                            Criar Conta Grátis <ArrowRight className="w-4 h-4" />
                        </Link>
                        <Link to="/contato" className="px-8 py-4 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-full border border-slate-700 transition-all">
                            Falar com Especialista
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}
