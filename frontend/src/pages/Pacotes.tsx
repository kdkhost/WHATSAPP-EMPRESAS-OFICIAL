import { useState } from 'react';
import { useSite } from '@/context/SiteContext';
import type { Plan } from '@/api/site';
import { motion } from 'framer-motion';
import { NeonButton } from '@/components/ui/NeonButton';
import { Check, X, Shield, Zap, Sparkles } from 'lucide-react';

export default function Pacotes() {
    const site = useSite();
    const [cycle, setCycle] = useState<'monthly' | 'annual'>('annual');
    const page = site?.pages?.pacotes || {};
    const hero = page.hero || {};
    const comparison = page.comparison || {};
    const sandbox = page.sandbox || {};

    const plans = pickPublicPlans((site?.plans || []).filter((plan) => !plan.is_demo));
    const demoPlan = (site?.plans || []).find((plan) => plan.is_demo);

    const fmtPrice = (plan: Plan) => {
        const price = parseFloat(String(plan.price || 0));
        if (cycle === 'annual') {
            const annual = price * 12 * 0.8;
            return { value: annual / 12, original: price };
        }
        return { value: price, original: null };
    };

    return (
        <div className="bg-slate-950 text-white min-h-screen pt-24 overflow-hidden relative">
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 left-1/4 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[150px]" />
                <div className="absolute top-1/3 right-1/4 w-[600px] h-[600px] bg-indigo-600/10 rounded-full blur-[180px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff08_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <section className="relative py-20 px-4 text-center z-10">
                <div className="max-w-4xl mx-auto">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="inline-flex items-center gap-2 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider shadow-[0_0_15px_rgba(99,102,241,0.1)]"
                    >
                        <Sparkles className="w-3.5 h-3.5 animate-pulse text-indigo-400" /> {hero.kicker || 'Tres Planos, Uma Escada Clara'}
                    </motion.div>
                    <motion.h1
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.1 }}
                        className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-tight"
                    >
                        {hero.title_before || 'Cresca no seu ritmo.'}<br />
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400">
                            {hero.title_highlight || 'Sem complicar a escolha.'}
                        </span>
                    </motion.h1>
                    <motion.p
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.2 }}
                        className="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto mb-12 font-medium"
                    >
                        {hero.description || 'Uma estrutura simples de entrada, crescimento e escala. Sem taxas ocultas, sem fidelidade e sem excesso de opcoes.'}
                    </motion.p>

                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.3 }}
                        className="inline-flex bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-full p-1.5 relative z-10 shadow-2xl"
                    >
                        <button onClick={() => setCycle('monthly')} className={`px-8 py-3 rounded-full text-sm font-bold transition-all relative ${cycle === 'monthly' ? 'bg-indigo-600 text-white shadow-[0_0_20px_rgba(99,102,241,0.4)]' : 'text-slate-400 hover:text-white'}`}>Mensal</button>
                        <button onClick={() => setCycle('annual')} className={`px-8 py-3 rounded-full text-sm font-bold transition-all flex items-center gap-2 relative ${cycle === 'annual' ? 'bg-indigo-600 text-white shadow-[0_0_20px_rgba(99,102,241,0.4)]' : 'text-slate-400 hover:text-white'}`}>
                            Anual
                            <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-extrabold ${cycle === 'annual' ? 'bg-indigo-500/30 text-indigo-300 border border-indigo-400/20' : 'bg-emerald-500/10 text-emerald-400'}`}>-20%</span>
                        </button>
                    </motion.div>
                </div>
            </section>

            <section className="pb-32 relative z-10 px-4">
                <div className="container mx-auto">
                    <div className="grid lg:grid-cols-3 gap-6 max-w-6xl mx-auto items-stretch">
                        {plans.map((plan, i) => {
                            const price = fmtPrice(plan);
                            const isHighlight = plan.highlight;
                            const planFeatures = Array.isArray(plan.features) ? plan.features : JSON.parse(String((plan.features as any) || '[]'));
                            const displayName = getPlanDisplayName(plan, i);

                            return (
                                <motion.div key={plan.id} initial={{ opacity: 0, y: 40 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 + (i * 0.1) }} className="flex">
                                    <div className={`relative w-full rounded-[2.5rem] p-8 lg:p-10 flex flex-col justify-between transition-all duration-500 bg-slate-900/40 backdrop-blur-xl border ${isHighlight ? 'border-indigo-500/50 shadow-[0_20px_50px_rgba(99,102,241,0.2)] bg-gradient-to-b from-slate-900/60 to-indigo-950/20 scale-100 lg:scale-[1.03] z-10' : 'border-slate-800 hover:border-slate-700/80 shadow-2xl shadow-black/40 hover:shadow-black/60'}`}>
                                        {isHighlight && (
                                            <div className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2">
                                                <div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-1.5 rounded-full text-xs font-bold shadow-[0_0_20px_rgba(99,102,241,0.5)] flex items-center gap-1.5 tracking-wider uppercase border border-indigo-400/30">
                                                    <Zap className="w-3.5 h-3.5 text-yellow-300 fill-yellow-300" /> Melhor Equilibrio
                                                </div>
                                            </div>
                                        )}

                                        <div>
                                            <div className="flex justify-between items-start mb-6">
                                                <div>
                                                    <h3 className="text-2xl font-bold text-white mb-2">{displayName}</h3>
                                                    <p className="text-slate-400 text-sm font-medium leading-relaxed min-h-[40px]">{plan.description}</p>
                                                </div>
                                            </div>

                                            <div className="mb-8 flex items-baseline gap-2">
                                                <span className="text-slate-500 text-lg font-bold">R$</span>
                                                <span className="text-5xl lg:text-6xl font-extrabold tracking-tight text-white">
                                                    {price.value.toFixed(0)}<span className="text-2xl font-medium">,{price.value.toFixed(2).split('.')[1]}</span>
                                                </span>
                                                <span className="text-slate-500 font-medium text-sm">/mes</span>
                                            </div>

                                            <div className="mb-8">
                                                <NeonButton href={`/cadastro?plan=${plan.slug}`} color={isHighlight ? 'indigo' : 'blue'} className={`w-full py-4 text-base font-bold rounded-full ${!isHighlight && 'bg-slate-800 hover:bg-slate-700/80 border-slate-700'}`}>
                                                    {plan.trial_days > 0 ? `Testar Gratis por ${plan.trial_days} dias` : 'Comecar Agora'}
                                                </NeonButton>
                                            </div>

                                            <div className="py-5 border-y border-slate-800/80 space-y-3 mb-8">
                                                <div className="flex justify-between items-center text-sm font-semibold">
                                                    <span className="text-slate-400">Instancias WhatsApp</span>
                                                    <span className="text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1 rounded-full text-xs font-bold">{plan.max_instances}</span>
                                                </div>
                                                <div className="flex justify-between items-center text-sm font-semibold">
                                                    <span className="text-slate-400">Clientes Ativos</span>
                                                    <span className="text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1 rounded-full text-xs font-bold">{plan.max_customers >= 10000 ? 'Ilimitados' : plan.max_customers.toLocaleString('pt-BR')}</span>
                                                </div>
                                                <div className="flex justify-between items-center text-sm font-semibold">
                                                    <span className="text-slate-400">Mensagens WhatsApp/mes</span>
                                                    <span className="text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1 rounded-full text-xs font-bold">{plan.max_messages_month >= 100000 ? 'Ilimitadas' : plan.max_messages_month.toLocaleString('pt-BR')}</span>
                                                </div>
                                            </div>

                                            <div className="space-y-4">
                                                <p className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">O que voce leva</p>
                                                {planFeatures.map((feature: string, idx: number) => (
                                                    <div key={idx} className="flex items-start gap-3">
                                                        <div className="w-5 h-5 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0 mt-0.5 text-emerald-400">
                                                            <Check className="w-3.5 h-3.5" />
                                                        </div>
                                                        <span className="text-slate-300 font-medium text-sm leading-tight">{feature}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </motion.div>
                            );
                        })}
                    </div>

                    {demoPlan && (
                        <motion.div initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} className="mt-24 max-w-4xl mx-auto">
                            <div className="p-8 md:p-12 rounded-[2.5rem] text-center bg-gradient-to-br from-indigo-950/40 to-slate-900/40 border border-indigo-500/30 backdrop-blur-xl relative overflow-hidden shadow-2xl">
                                <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 rounded-full blur-[40px] pointer-events-none" />
                                <span className="bg-indigo-500/10 text-indigo-300 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider mb-6 inline-flex items-center gap-1.5 border border-indigo-500/20">
                                    <Shield className="w-3.5 h-3.5" /> {sandbox.kicker || 'Ambiente de Testes Livre'}
                                </span>
                                <h3 className="text-3xl font-extrabold text-white mb-4">{sandbox.title || demoPlan.name}</h3>
                                <p className="text-slate-400 max-w-2xl mx-auto mb-8 font-medium leading-relaxed">
                                    {sandbox.description || `${demoPlan.description} Experimente todas as funcionalidades em nosso ambiente de testes sem custo algum.`}
                                </p>

                                <div className="flex flex-col sm:flex-row items-center justify-center gap-8">
                                    <div className="text-center sm:text-left">
                                        <div className="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Preco</div>
                                        <div className="text-3xl font-extrabold text-indigo-400">Gratis</div>
                                    </div>
                                    <div className="hidden sm:block w-px h-12 bg-slate-800"></div>
                                    <NeonButton href={`/cadastro?plan=${demoPlan.slug}`} color="indigo" className="px-10 py-4 font-bold rounded-full">
                                        Acessar Sandbox Agora
                                    </NeonButton>
                                </div>
                            </div>
                        </motion.div>
                    )}
                </div>
            </section>

            <section className="py-32 bg-slate-950 border-t border-slate-900 relative z-10">
                <div className="container mx-auto px-4 max-w-6xl">
                    <div className="text-center mb-20">
                        <h2 className="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">{comparison.title || 'Visao Detalhada'}</h2>
                        <p className="text-lg text-slate-400 font-medium">{comparison.description || 'Compare cada detalhe e escolha o plano perfeito para o seu momento.'}</p>
                    </div>

                    <div className="overflow-x-auto rounded-[2rem] border border-slate-900 shadow-3xl bg-slate-900/20 backdrop-blur-xl">
                        <table className="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr>
                                    <th className="p-6 bg-slate-900/60 border-b border-slate-900 text-slate-400 font-bold uppercase tracking-widest text-xs w-1/3">Recursos Disponiveis</th>
                                    {plans.map((plan, index) => (
                                        <th key={plan.id} className="p-6 bg-slate-900/40 border-b border-l border-slate-900 text-center">
                                            <div className="text-lg font-bold text-white mb-1">{getPlanDisplayName(plan, index)}</div>
                                            <div className="text-xs font-bold text-indigo-400">R$ {fmtPrice(plan).value.toFixed(0)}/mes</div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-900/60">
                                {compareRows(plans).map((row, i) => (
                                    <tr key={i} className="hover:bg-slate-900/20 transition-colors">
                                        <td className="p-6 font-semibold text-slate-300 text-sm">{row.label}</td>
                                        {row.values.map((value, j) => (
                                            <td key={j} className="p-6 border-l border-slate-900 text-center">
                                                {value === true ? (
                                                    <div className="w-6 h-6 rounded-full bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center mx-auto text-indigo-400"><Check className="w-4 h-4" /></div>
                                                ) : value === false ? (
                                                    <div className="w-6 h-6 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto text-slate-600"><X className="w-4 h-4" /></div>
                                                ) : (
                                                    <span className="font-bold text-white text-sm">{value}</span>
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    );
}

function isInternalPlan(plan: Pick<Plan, 'name' | 'slug'>) {
    const value = `${plan.name} ${plan.slug}`.toLowerCase();
    return ['admin', 'operacional', 'sandbox', 'demo', 'interno', 'internal'].some((term) => value.includes(term));
}

function pickPublicPlans(plans: Plan[]) {
    return [...plans]
        .filter((plan) => !isInternalPlan(plan))
        .sort((a, b) => {
            const rankDiff = getPlanRank(a) - getPlanRank(b);
            if (rankDiff !== 0) return rankDiff;
            return (a.sort ?? 0) - (b.sort ?? 0);
        })
        .slice(0, 3);
}

function getPlanRank(plan: Pick<Plan, 'name' | 'slug' | 'highlight'>) {
    const value = `${plan.name} ${plan.slug}`.toLowerCase();
    if (value.includes('starter')) return 0;
    if (plan.highlight || value.includes('profissional') || value.includes('professional') || value.includes('pro')) return 1;
    if (value.includes('empresarial') || value.includes('enterprise') || value.includes('business')) return 2;
    return 9;
}

function getPlanDisplayName(plan: Pick<Plan, 'name' | 'slug'>, index: number) {
    const value = `${plan.name} ${plan.slug}`.toLowerCase();
    if (value.includes('starter')) return 'Starter';
    if (value.includes('profissional') || value.includes('professional') || value.includes('pro')) return 'Pro';
    if (value.includes('empresarial') || value.includes('enterprise') || value.includes('business')) return 'Enterprise';
    return ['Starter', 'Pro', 'Enterprise'][index] || plan.name;
}

function compareRows(plans: Plan[]) {
    return [
        { label: 'Instancias WhatsApp Conectadas', values: plans.map((plan) => plan.max_instances.toString()) },
        { label: 'Limite de Clientes Ativos', values: plans.map((plan) => plan.max_customers >= 10000 ? 'Ilimitados' : plan.max_customers.toLocaleString('pt-BR')) },
        { label: 'Mensagens enviadas/mes', values: plans.map((plan) => plan.max_messages_month >= 100000 ? 'Ilimitadas' : plan.max_messages_month.toLocaleString('pt-BR')) },
        { label: 'Faturas Recorrentes Geradas/mes', values: plans.map((plan) => plan.max_invoices_month.toLocaleString('pt-BR')) },
        { label: 'Multiplos Gateways de Pagamento', values: plans.map((plan) => plan.custom_gateway) },
        { label: 'Templates de Mensagem Dinamicos', values: plans.map((plan) => plan.custom_templates) },
        { label: 'Aceitar Parcelamento no Cartao', values: plans.map((plan) => plan.installment_enabled) },
        { label: 'Repasse de Taxas dos Gateways', values: plans.map((plan) => plan.fee_passthrough) },
    ];
}
