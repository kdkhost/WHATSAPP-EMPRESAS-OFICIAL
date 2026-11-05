import { motion } from 'framer-motion';
import { Star, Download, Shield, Zap, Package, BarChart2, MessageSquare, CreditCard, FileText, ArrowRight, Search } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

const categories = ['Todos', 'Pagamentos', 'WhatsApp', 'Relatórios', 'Segurança', 'CRM', 'Automação'];

const addons = [
    {
        id: 1,
        icon: CreditCard,
        name: 'Gateway Multi-Split',
        category: 'Pagamentos',
        version: '2.1.0',
        author: 'Cobrança WP Team',
        rating: 4.9,
        installs: '2.4k',
        price: 'Incluso no Pro',
        desc: 'Divida recebimentos entre múltiplas contas automaticamente. Ideal para marketplaces e multi-estabelecimentos.',
        tags: ['split', 'pagamentos', 'marketplace'],
        featured: true,
        color: 'from-blue-500 to-indigo-600',
    },
    {
        id: 2,
        icon: MessageSquare,
        name: 'Templates Avançados',
        category: 'WhatsApp',
        version: '1.8.2',
        author: 'Cobrança WP Team',
        rating: 4.8,
        installs: '5.1k',
        price: 'Grátis',
        desc: 'Biblioteca com mais de 50 templates profissionais de cobrança, welcome message e recuperação de inadimplência.',
        tags: ['templates', 'whatsapp', 'mensagens'],
        featured: true,
        color: 'from-emerald-500 to-teal-600',
    },
    {
        id: 3,
        icon: BarChart2,
        name: 'Analytics Pro',
        category: 'Relatórios',
        version: '3.0.1',
        author: 'DevPartner',
        rating: 4.7,
        installs: '1.8k',
        price: 'R$ 49/mês',
        desc: 'Dashboards avançados com cohort analysis, churn prediction e relatórios de inadimplência com IA.',
        tags: ['analytics', 'relatórios', 'ia'],
        featured: false,
        color: 'from-violet-500 to-purple-700',
    },
    {
        id: 4,
        icon: Shield,
        name: 'WAF Enterprise',
        category: 'Segurança',
        version: '1.3.0',
        author: 'Cobrança WP Team',
        rating: 5.0,
        installs: '890',
        price: 'Incluso no Enterprise',
        desc: 'Firewall de aplicação web com detecção de bots, rate limiting inteligente e proteção DDoS.',
        tags: ['segurança', 'waf', 'ddos'],
        featured: false,
        color: 'from-red-500 to-rose-700',
    },
    {
        id: 5,
        icon: FileText,
        name: 'Contratos Digitais',
        category: 'CRM',
        version: '2.0.0',
        author: 'LegalTech Partner',
        rating: 4.6,
        installs: '1.2k',
        price: 'R$ 79/mês',
        desc: 'Assinatura digital de contratos integrada ao fluxo de cobrança. Validade jurídica garantida.',
        tags: ['contratos', 'assinatura', 'crm'],
        featured: false,
        color: 'from-amber-500 to-orange-600',
    },
    {
        id: 6,
        icon: Zap,
        name: 'Automação Inteligente',
        category: 'Automação',
        version: '1.5.3',
        author: 'AutoFlow Labs',
        rating: 4.8,
        installs: '3.7k',
        price: 'Grátis',
        desc: 'Regras de automação visuais para programar envios, escalada de cobranças e notificações baseadas em comportamento.',
        tags: ['automação', 'workflow', 'regras'],
        featured: true,
        color: 'from-sky-500 to-blue-700',
    },
];

const fadeUp = {
    hidden: { opacity: 0, y: 30 },
    show: (i: number) => ({ opacity: 1, y: 0, transition: { delay: i * 0.07, duration: 0.45 } }),
};

export default function Marketplace() {
    const site = useSite();
    const page = site?.pages?.marketplace || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Marketplace de Addons',
                    title: 'Expanda sua plataforma.',
                    description: 'Addons, extensoes e integracoes criados pela equipe e pela comunidade.',
                }}
            />
        );
    }

    const [activeCategory, setActiveCategory] = useState('Todos');
    const [search, setSearch] = useState('');

    const filtered = addons.filter(a => {
        const matchCat = activeCategory === 'Todos' || a.category === activeCategory;
        const matchSearch = !search || a.name.toLowerCase().includes(search.toLowerCase()) || a.desc.toLowerCase().includes(search.toLowerCase());
        return matchCat && matchSearch;
    });

    const featured = addons.filter(a => a.featured);

    return (
        <div className="bg-slate-950 text-white min-h-screen pt-24 overflow-hidden">
            {/* Background */}
            <div className="pointer-events-none fixed inset-0 z-0">
                <div className="absolute top-0 right-1/4 w-[700px] h-[700px] bg-purple-600/8 rounded-full blur-[180px]" />
                <div className="absolute bottom-0 left-1/3 w-[500px] h-[500px] bg-indigo-600/8 rounded-full blur-[150px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff05_1px,transparent_1px)] [background-size:22px_22px]" />
            </div>

            {/* HERO */}
            <section className="relative z-10 py-24 px-4 text-center">
                <div className="max-w-4xl mx-auto">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}
                        className="inline-flex items-center gap-2 bg-purple-500/10 text-purple-400 border border-purple-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider"
                    >
                        <Package className="w-3.5 h-3.5" /> Marketplace de Addons
                    </motion.div>
                    <motion.h1
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}
                        className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-tight"
                    >
                        Expanda sua<br />
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-violet-400 to-indigo-400">
                            plataforma.
                        </span>
                    </motion.h1>
                    <motion.p
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }}
                        className="text-lg text-slate-400 max-w-2xl mx-auto mb-12 font-medium"
                    >
                        Addons, extensões e integrações criados pela equipe e pela comunidade. Instale em um clique diretamente do painel.
                    </motion.p>

                    {/* Search */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }}
                        className="relative max-w-lg mx-auto"
                    >
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                        <input
                            type="text"
                            placeholder="Buscar addons..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full bg-slate-900/80 backdrop-blur border border-slate-700 rounded-full py-4 pl-12 pr-6 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
                        />
                    </motion.div>
                </div>
            </section>

            {/* FEATURED */}
            {!search && activeCategory === 'Todos' && (
                <section className="relative z-10 py-8 px-4">
                    <div className="max-w-7xl mx-auto">
                        <h2 className="text-xl font-bold text-slate-300 mb-6 flex items-center gap-2">
                            <Star className="w-5 h-5 text-yellow-400 fill-yellow-400" /> Em Destaque
                        </h2>
                        <div className="grid sm:grid-cols-3 gap-6">
                            {featured.map((item, i) => {
                                const Icon = item.icon;
                                return (
                                    <motion.div
                                        key={item.id}
                                        custom={i} variants={fadeUp} initial="hidden" animate="show"
                                        className="relative rounded-3xl overflow-hidden bg-gradient-to-br from-slate-900/80 to-slate-900/40 border border-slate-700/60 hover:border-slate-500 transition-all group cursor-pointer"
                                    >
                                        <div className={`absolute inset-0 bg-gradient-to-br ${item.color} opacity-5 group-hover:opacity-10 transition-opacity`} />
                                        <div className="relative p-7">
                                            <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${item.color} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg`}>
                                                <Icon className="w-6 h-6 text-white" />
                                            </div>
                                            <div className="flex justify-between items-start mb-3">
                                                <div>
                                                    <h3 className="text-lg font-bold text-white">{item.name}</h3>
                                                    <div className="text-xs text-slate-500 font-medium">{item.author} · v{item.version}</div>
                                                </div>
                                                <span className="text-xs font-bold text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-1 rounded-full">{item.price}</span>
                                            </div>
                                            <p className="text-slate-400 text-sm leading-relaxed mb-5">{item.desc}</p>
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-1 text-yellow-400 text-sm font-bold">
                                                    <Star className="w-4 h-4 fill-yellow-400" /> {item.rating}
                                                    <span className="text-slate-500 font-normal ml-1 text-xs">({item.installs} inst.)</span>
                                                </div>
                                                <button className="flex items-center gap-1 text-sm font-bold text-indigo-400 hover:text-indigo-300 transition-colors">
                                                    <Download className="w-4 h-4" /> Instalar
                                                </button>
                                            </div>
                                        </div>
                                    </motion.div>
                                );
                            })}
                        </div>
                    </div>
                </section>
            )}

            {/* CATEGORY FILTER + ALL ADDONS */}
            <section className="relative z-10 py-16 px-4">
                <div className="max-w-7xl mx-auto">
                    <div className="flex gap-2 flex-wrap mb-10">
                        {categories.map(cat => (
                            <button
                                key={cat}
                                onClick={() => setActiveCategory(cat)}
                                className={`px-4 py-2 rounded-full text-sm font-bold transition-all ${
                                    activeCategory === cat
                                        ? 'bg-indigo-600 text-white shadow-[0_0_15px_rgba(99,102,241,0.3)]'
                                        : 'bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 border border-slate-700'
                                }`}
                            >
                                {cat}
                            </button>
                        ))}
                    </div>

                    {filtered.length === 0 ? (
                        <div className="text-center py-24 text-slate-500">
                            <Package className="w-16 h-16 mx-auto mb-4 opacity-30" />
                            <p className="text-lg font-medium">Nenhum addon encontrado.</p>
                        </div>
                    ) : (
                        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {filtered.map((item, i) => {
                                const Icon = item.icon;
                                return (
                                    <motion.div
                                        key={item.id}
                                        custom={i} variants={fadeUp} initial="hidden" whileInView="show" viewport={{ once: true }}
                                        className="rounded-3xl p-6 bg-slate-900/50 backdrop-blur border border-slate-800 hover:border-slate-600 transition-all group"
                                    >
                                        <div className="flex items-start gap-4 mb-4">
                                            <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${item.color} flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform shadow-lg`}>
                                                <Icon className="w-6 h-6 text-white" />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <h3 className="text-base font-bold text-white truncate">{item.name}</h3>
                                                <div className="text-xs text-slate-500">{item.author} · v{item.version}</div>
                                            </div>
                                            <span className="text-xs font-bold text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 rounded-full shrink-0">{item.price}</span>
                                        </div>
                                        <p className="text-slate-400 text-sm leading-relaxed mb-5">{item.desc}</p>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-1 text-yellow-400 text-sm font-bold">
                                                <Star className="w-3.5 h-3.5 fill-yellow-400" /> {item.rating}
                                                <span className="text-slate-500 font-normal ml-1 text-xs">{item.installs} instalações</span>
                                            </div>
                                            <button className="flex items-center gap-1.5 text-sm font-bold text-indigo-400 hover:text-indigo-300 transition-colors bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 px-3 py-1.5 rounded-full">
                                                <Download className="w-3.5 h-3.5" /> Instalar
                                            </button>
                                        </div>
                                    </motion.div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </section>

            {/* CTA PARA DESENVOLVEDORES */}
            <section className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-4xl mx-auto text-center">
                    <h2 className="text-4xl font-extrabold mb-4">Você é desenvolvedor?</h2>
                    <p className="text-slate-400 text-lg font-medium mb-10 max-w-2xl mx-auto">
                        Publique seus addons no Marketplace e alcance milhares de clientes. Monetize sua expertise com revenue share.
                    </p>
                    <div className="flex flex-wrap gap-4 justify-center">
                        <Link to="/contato" className="px-8 py-4 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-full shadow-[0_0_30px_rgba(139,92,246,0.3)] transition-all flex items-center gap-2">
                            Publicar meu Addon <ArrowRight className="w-4 h-4" />
                        </Link>
                        <Link to="/integracoes" className="px-8 py-4 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-full border border-slate-700 transition-all">
                            Ver Documentação API
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}
