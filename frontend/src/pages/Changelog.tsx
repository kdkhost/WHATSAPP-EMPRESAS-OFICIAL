import { motion } from 'framer-motion';
import { Rocket, Zap, Shield, Bug, Wrench, Sparkles, Tag } from 'lucide-react';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

const releasesDefault = [
    {
        version: '2.5.0',
        date: '14 Setembro, 2026',
        label: 'latest',
        labelColor: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
        entries: [
            { type: 'new', text: 'Painel do lojista: Nova seção de Segurança Anti-Ban para gerenciar fluxo de mensagens.' },
            { type: 'improvement', text: 'Pacing Inteligente: Motor de cobranças agora respeita intervalos de 3 a 5 minutos, protegendo contas contra banimento do WhatsApp.' },
            { type: 'improvement', text: 'Extração Automática de PIX: Detecção avançada do código Copia e Cola em faturas do MercadoPago integradas ao WHMCS.' },
            { type: 'fix', text: 'Preview de Templates: Correção da exibição do QR Code e PIX no simulador em tempo real.' },
        ],
    },
    {
        version: '2.4.0',
        date: '19 Maio, 2026',
        entries: [
            { type: 'new', text: 'Marketplace de Addons: instale extensoes em um clique diretamente do painel.' },
            { type: 'new', text: 'Pagina de Integracoes com documentacao da API REST e exemplos de codigo.' },
            { type: 'fix', text: 'Ajustes visuais e operacionais no frontend publico e no painel administrativo.' },
        ],
    },
    {
        version: '2.0.0',
        date: '02 Maio, 2026',
        label: 'major',
        labelColor: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
        entries: [
            { type: 'new', text: 'Reescrita completa da plataforma com arquitetura modular Laravel 13.' },
            { type: 'new', text: 'Multi-tenancy SaaS com planos, tenants e instâncias WhatsApp por cliente.' },
            { type: 'new', text: 'Envios automáticos de cobrança via WhatsApp Cloud API Oficial da Meta.' },
        ],
    },
];

const typeConfig = {
    new: { icon: Sparkles, label: 'Novo', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
    improvement: { icon: Zap, label: 'Melhoria', color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
    fix: { icon: Bug, label: 'Correcao', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
    security: { icon: Shield, label: 'Seguranca', color: 'bg-red-500/10 text-red-400 border-red-500/20' },
    breaking: { icon: Wrench, label: 'Mudanca', color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' },
} as const;

type EntryType = keyof typeof typeConfig;

export default function Changelog() {
    const site = useSite();
    const page = site?.pages?.changelog || {};
    const hero = page.hero || {};
    const releases = Array.isArray(page.releases) && page.releases.length ? page.releases : releasesDefault;

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Historico de Atualizacoes',
                    title: 'O que ha de novo?',
                    description: 'Acompanhe todas as novidades, melhorias e correcoes da plataforma em tempo real.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 text-white min-h-screen pt-24 overflow-hidden">
            <div className="pointer-events-none fixed inset-0 z-0">
                <div className="absolute top-0 left-1/3 w-[600px] h-[600px] bg-violet-600/8 rounded-full blur-[180px]" />
                <div className="absolute bottom-1/3 right-1/4 w-[400px] h-[400px] bg-indigo-600/8 rounded-full blur-[140px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff05_1px,transparent_1px)] [background-size:20px_20px]" />
            </div>

            <section className="relative z-10 py-24 px-4 text-center">
                <div className="max-w-3xl mx-auto">
                    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="inline-flex items-center gap-2 bg-violet-500/10 text-violet-400 border border-violet-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                        <Rocket className="w-3.5 h-3.5" /> {hero.kicker || 'Historico de Atualizacoes'}
                    </motion.div>
                    <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-5xl md:text-6xl font-extrabold tracking-tight mb-6 leading-tight">
                        {hero.title_before || 'O que ha de'}{' '}
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 via-indigo-400 to-blue-400">
                            {hero.title_highlight || 'novo?'}
                        </span>
                    </motion.h1>
                    <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }} className="text-lg text-slate-400 font-medium max-w-xl mx-auto">
                        {hero.description || 'Acompanhe todas as novidades, melhorias e correcoes da plataforma em tempo real.'}
                    </motion.p>
                </div>
            </section>

            <section className="relative z-10 pb-32 px-4">
                <div className="max-w-3xl mx-auto">
                    <div className="relative">
                        <div className="absolute left-6 top-0 bottom-0 w-px bg-gradient-to-b from-indigo-500/50 via-slate-700 to-transparent" />

                        <div className="space-y-16">
                            {releases.map((release: any, ri: number) => (
                                <motion.div
                                    key={`${release.version}-${ri}`}
                                    initial={{ opacity: 0, x: -20 }}
                                    whileInView={{ opacity: 1, x: 0 }}
                                    viewport={{ once: true }}
                                    transition={{ delay: ri * 0.05 }}
                                    className="relative pl-16"
                                >
                                    <div className="absolute left-3.5 top-1 w-5 h-5 rounded-full bg-slate-900 border-2 border-indigo-500 flex items-center justify-center">
                                        <div className="w-2 h-2 rounded-full bg-indigo-400" />
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 mb-6">
                                        <div className="flex items-center gap-2">
                                            <Tag className="w-4 h-4 text-slate-500" />
                                            <span className="text-2xl font-extrabold text-white">v{release.version}</span>
                                        </div>
                                        {release.label && (
                                            <span className={`text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full border ${release.labelColor || ''}`}>
                                                {release.label}
                                            </span>
                                        )}
                                        <span className="text-slate-500 text-sm font-medium ml-auto">{release.date}</span>
                                    </div>

                                    <div className="rounded-2xl bg-slate-900/50 backdrop-blur border border-slate-800 overflow-hidden">
                                        {(release.entries || []).map((entry: any, ei: number) => {
                                            const config = typeConfig[(entry.type as EntryType) || 'new'] || typeConfig.new;
                                            const Icon = config.icon;

                                            return (
                                                <div key={ei} className={`flex items-start gap-4 px-6 py-4 ${ei < release.entries.length - 1 ? 'border-b border-slate-800/60' : ''}`}>
                                                    <span className={`inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full border shrink-0 mt-0.5 ${config.color}`}>
                                                        <Icon className="w-3 h-3" />
                                                        {config.label}
                                                    </span>
                                                    <p className="text-slate-300 text-sm font-medium leading-relaxed">{entry.text}</p>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
