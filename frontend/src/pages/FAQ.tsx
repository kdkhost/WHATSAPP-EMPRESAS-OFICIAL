import { useState } from 'react';
import { useSite } from '@/context/SiteContext';
import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { motion, AnimatePresence } from 'framer-motion';
import { Search, ChevronDown } from 'lucide-react';

export default function FAQ() {
    const [search, setSearch] = useState('');
    const site = useSite();
    const page = site?.pages?.faq || {};
    const hero = page.hero || {};

    const faqs = Array.isArray(page.items) && page.items.length ? page.items : [
        { q: 'Como funciona o periodo de teste?', a: 'Voce tem ate 14 dias para testar todos os recursos do plano escolhido. Sem cartao de credito. Cancelamento facil pelo painel.' },
        { q: 'Posso mudar de plano depois?', a: 'Sim. Voce pode fazer upgrade ou downgrade a qualquer momento. A diferenca e calculada proporcionalmente.' },
        { q: 'Como funciona a cobranca?', a: 'A cobranca e mensal ou anual conforme o ciclo escolhido. Aceitamos PIX, boleto e cartao de credito.' },
        { q: 'Tem fidelidade?', a: 'Nao. Voce pode cancelar a qualquer momento sem multa pelo proprio painel.' },
        { q: 'Os dados sao seguros?', a: 'Sim. Usamos criptografia de ponta, WAF proprio com bloqueio de bots e backups diarios automaticos.' },
        { q: 'Posso usar minha API do WhatsApp?', a: 'Sim. Suportamos Evolution API Go. Voce conecta sua propria instancia rapidamente no painel de integracoes.' },
        { q: 'Voces cobram taxa por transacao?', a: 'Nao. O sistema e uma plataforma SaaS. Se voce usa um gateway, paga apenas a taxa dele. Nos cobramos a mensalidade do software.' },
    ];

    const filtered = faqs.filter((item: any) => {
        const q = String(item.q || '').toLowerCase();
        const a = String(item.a || '').toLowerCase();
        const term = search.toLowerCase();

        return q.includes(term) || a.includes(term);
    });

    return (
        <div className="bg-slate-950 min-h-screen pt-24 pb-32 relative overflow-hidden text-slate-300">
            <div className="absolute inset-0 z-0">
                <div className="absolute top-1/4 left-1/4 w-[400px] h-[400px] bg-blue-600/10 rounded-full blur-[150px]" />
                <div className="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[180px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff08_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <div className="container mx-auto px-4 max-w-3xl relative z-10">
                <ScrollReveal className="text-center mb-16">
                    <span className="inline-block px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-400 font-bold tracking-widest uppercase text-xs mb-6 border border-indigo-500/20">
                        {hero.kicker || 'Central de Ajuda'}
                    </span>
                    <h1 className="text-5xl font-extrabold tracking-tight mb-6 text-white">
                        {hero.title_before || 'Como podemos'}{' '}
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">
                            {hero.title_highlight || 'ajudar?'}
                        </span>
                    </h1>
                    <div className="relative max-w-xl mx-auto mt-8">
                        <Search className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5" />
                        <input
                            type="text"
                            placeholder={hero.search_placeholder || 'Busque por termos, duvidas ou palavras-chave...'}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full bg-slate-900/60 backdrop-blur-md border border-slate-700 rounded-full py-4 pl-14 pr-6 text-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xl transition-all"
                        />
                    </div>
                </ScrollReveal>

                <div className="space-y-4">
                    {filtered.map((faq: any, i: number) => (
                        <FaqItem key={i} q={String(faq.q || '')} a={String(faq.a || '')} idx={i} />
                    ))}
                    {filtered.length === 0 && (
                        <div className="text-center text-slate-500 py-10 bg-slate-900/40 border border-slate-800 rounded-[2rem]">
                            {hero.empty_message || 'Nenhuma resposta encontrada para sua busca. Tente outros termos.'}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function FaqItem({ q, a, idx }: { q: string; a: string; idx: number }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <ScrollReveal delay={idx * 0.05}>
            <div className="bg-slate-900/40 backdrop-blur-xl border border-slate-800 rounded-2xl overflow-hidden shadow-lg hover:border-slate-700 transition-all">
                <button
                    onClick={() => setIsOpen(!isOpen)}
                    className="w-full text-left px-6 py-5 flex items-center justify-between focus:outline-none"
                >
                    <span className="font-bold text-white text-lg">{q}</span>
                    <motion.div animate={{ rotate: isOpen ? 180 : 0 }}>
                        <ChevronDown className={`w-6 h-6 transition-colors ${isOpen ? 'text-indigo-400' : 'text-slate-500'}`} />
                    </motion.div>
                </button>
                <AnimatePresence>
                    {isOpen && (
                        <motion.div
                            initial={{ height: 0, opacity: 0 }}
                            animate={{ height: 'auto', opacity: 1 }}
                            exit={{ height: 0, opacity: 0 }}
                            className="overflow-hidden"
                        >
                            <div className="px-6 pb-6 pt-2 text-slate-400 font-medium leading-relaxed border-t border-slate-800/50 mx-6">
                                {a}
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </ScrollReveal>
    );
}
