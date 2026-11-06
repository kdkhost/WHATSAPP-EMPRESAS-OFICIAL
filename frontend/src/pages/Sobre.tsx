import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { NeonButton } from '@/components/ui/NeonButton';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';
import { motion } from 'framer-motion';
import AnimatedCounter from '@/components/AnimatedCounter';
import { Shield, Zap, HeartHandshake, Target, Rocket, Trophy, Users, Building2, Globe2, ArrowRight } from 'lucide-react';

const timeline = [
    { ano: '2021', titulo: 'A Faísca', desc: 'Após ver dezenas de empresas fecharem as portas por inadimplência, decidimos construir algo diferente. Nascemos com um problema claro a resolver.', icon: <Target className="w-5 h-5" />, gradient: 'from-blue-500 to-indigo-600' },
    { ano: '2022', titulo: 'Primeiro Produto', desc: 'Lançamos o MVP com integração oficial ao WhatsApp Cloud API. Os primeiros 50 clientes relataram redução de inadimplência de até 38% no primeiro mês.', icon: <Rocket className="w-5 h-5" />, gradient: 'from-violet-500 to-purple-600' },
    { ano: '2023', titulo: 'Escala Nacional', desc: 'Cruzamos 500 empresas ativas e lançamos o módulo de PIX com baixa automática — hoje o mais usado, processando milhões por mês.', icon: <Trophy className="w-5 h-5" />, gradient: 'from-amber-500 to-orange-600' },
    { ano: '2024', titulo: 'Plataforma Completa', desc: 'WAF próprio, Analytics em tempo real, Backup automático e White Label. A plataforma virou a espinha dorsal financeira de ISPs, clínicas e e-commerces.', icon: <Building2 className="w-5 h-5" />, gradient: 'from-emerald-500 to-teal-600' },
    { ano: '2025', titulo: 'Hoje', desc: 'Mais de 3.500 empresas ativas. Mais de 2,5 milhões de mensagens enviadas por mês. Operação monitorada continuamente e evolução constante da plataforma.', icon: <Globe2 className="w-5 h-5" />, gradient: 'from-pink-500 to-rose-600' },
];

const valores = [
    { icon: <Shield className="w-8 h-8" />, title: 'Transparência Total', desc: 'Sem letras miúdas, sem taxas surpresa. O que você vê é exatamente o que você paga.', gradient: 'from-blue-500 to-indigo-500' },
    { icon: <Zap className="w-8 h-8" />, title: 'Inovação Contínua', desc: 'Atualizamos nossa engine toda semana com base no feedback real dos clientes. Sem parar.', gradient: 'from-amber-500 to-orange-500' },
    { icon: <HeartHandshake className="w-8 h-8" />, title: 'Sucesso do Cliente', desc: 'Se o cliente do nosso cliente paga, nós vencemos juntos. Toda decisão começa aqui.', gradient: 'from-pink-500 to-rose-500' },
];

const stats = [
    { value: 3500, suffix: '+', label: 'Empresas Ativas', icon: <Users className="w-5 h-5" /> },
    { value: 2.5, suffix: 'M', label: 'Mensagens/mês', decimals: 1, icon: <Globe2 className="w-5 h-5" /> },
    { value: 72, suffix: '%', label: 'Redução de Inadimplência', icon: <Trophy className="w-5 h-5" /> },
    { value: 99.9, suffix: '%', label: 'Uptime Monitorado', decimals: 1, icon: <Shield className="w-5 h-5" /> },
];

export default function Sobre() {
    const site = useSite();
    const page = site?.pages?.sobre || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Nossa Missao',
                    title: 'Nascemos para erradicar a inadimplencia',
                    description: 'Ajudamos empresas a receberem o que e delas, no prazo, com respeito e tecnologia de ponta.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 min-h-screen text-slate-300 overflow-hidden">
            <div className="fixed inset-0 z-0 pointer-events-none">
                <div className="absolute top-0 left-1/4 w-[600px] h-[600px] bg-blue-600/8 rounded-full blur-[200px]" />
                <div className="absolute bottom-1/4 right-1/4 w-[700px] h-[700px] bg-indigo-600/8 rounded-full blur-[220px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff06_1px,transparent_1px)] [background-size:20px_20px]" />
            </div>

            {/* HERO */}
            <section className="relative z-10 pt-32 pb-24 px-4 text-center">
                <div className="max-w-5xl mx-auto">
                    <ScrollReveal>
                        <span className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-500/10 text-blue-400 font-bold tracking-widest uppercase text-xs mb-8 border border-blue-500/20">
                            <HeartHandshake className="w-3.5 h-3.5" /> Nossa Missão
                        </span>
                        <h1 className="text-5xl md:text-7xl font-black tracking-tight mb-6 text-white leading-tight">
                            Nascemos para{' '}
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-violet-400">
                                erradicar a inadimplência
                            </span>
                        </h1>
                        <p className="text-xl md:text-2xl text-slate-400 max-w-3xl mx-auto leading-relaxed font-medium">
                            Ajudamos empresas a receberem o que é delas, no prazo, com respeito e tecnologia de ponta —
                            sem constrangimentos e sem processos manuais.
                        </p>
                    </ScrollReveal>
                </div>
            </section>

            {/* STATS */}
            <section className="relative z-10 py-16 px-4 border-y border-slate-800/50">
                <div className="max-w-5xl mx-auto">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                        {stats.map((s, i) => (
                            <ScrollReveal key={i} delay={i * 0.1}>
                                <div className="text-center p-6 bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-2xl hover:border-indigo-500/30 transition-all group">
                                    <div className="flex justify-center text-indigo-400 mb-3 group-hover:scale-110 transition-transform">{s.icon}</div>
                                    <div className="text-3xl md:text-4xl font-black text-white mb-1">
                                        <AnimatedCounter end={s.value} decimals={(s as {decimals?: number}).decimals ?? 0} />{s.suffix}
                                    </div>
                                    <div className="text-sm text-slate-500 font-medium">{s.label}</div>
                                </div>
                            </ScrollReveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* HISTÓRIA */}
            <section className="relative z-10 py-32 px-4">
                <div className="max-w-6xl mx-auto">
                    <div className="grid lg:grid-cols-2 gap-16 items-center">
                        <ScrollReveal direction="left">
                            <div className="relative">
                                <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 to-blue-500 rounded-3xl blur-3xl opacity-15 animate-pulse" />
                                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2070&auto=format&fit=crop" alt="Equipe" className="relative rounded-3xl shadow-2xl border border-slate-800 w-full" />
                                <motion.div animate={{ y: [-8, 8, -8] }} transition={{ duration: 4, repeat: Infinity, ease: 'easeInOut' }}
                                    className="absolute -bottom-6 -right-6 bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 shadow-2xl border border-indigo-500/30">
                                    <div className="text-white text-center">
                                        <div className="text-3xl font-black">4+</div>
                                        <div className="text-indigo-200 text-xs font-bold mt-1">Anos de mercado</div>
                                    </div>
                                </motion.div>
                            </div>
                        </ScrollReveal>
                        <ScrollReveal direction="right">
                            <span className="inline-block px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-400 font-bold tracking-widest uppercase text-xs mb-6 border border-indigo-500/20">Nossa História</span>
                            <h2 className="text-3xl md:text-4xl font-black mb-6 text-white leading-tight">A base de uma revolução no faturamento brasileiro</h2>
                            <p className="text-lg text-slate-400 mb-6 leading-relaxed">Após anos vendo PMEs fecharem as portas por inadimplência, construímos uma solução para automatizar o processo mais chato do mês: a <span className="text-white font-bold">cobrança pelo WhatsApp</span>.</p>
                            <p className="text-lg text-slate-400 mb-8 leading-relaxed">Hoje, processamos pagamentos via PIX em tempo real e ajudamos nossos parceiros a ganhar previsibilidade, acelerar recebimentos e reduzir atrasos já nos primeiros ciclos de cobrança.</p>
                            <NeonButton href="/pacotes" color="indigo"><Rocket className="w-4 h-4 mr-2" />Começar agora — é grátis</NeonButton>
                        </ScrollReveal>
                    </div>
                </div>
            </section>

            {/* TIMELINE */}
            <section className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-4xl mx-auto">
                    <ScrollReveal className="text-center mb-20">
                        <span className="inline-block px-4 py-1.5 rounded-full bg-amber-500/10 text-amber-400 font-bold tracking-widest uppercase text-xs mb-6 border border-amber-500/20">Nossa Jornada</span>
                        <h2 className="text-4xl md:text-5xl font-black text-white mb-4">De startup a referência nacional</h2>
                    </ScrollReveal>
                    <div className="relative">
                        <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-blue-500/50 via-violet-500/50 to-pink-500/50 -translate-x-1/2 hidden md:block" />
                        <div className="space-y-10">
                            {timeline.map((item, i) => (
                                <ScrollReveal key={i} delay={i * 0.1} direction={i % 2 === 0 ? 'left' : 'right'}>
                                    <div className={`relative flex flex-col md:flex-row items-start gap-8 ${i % 2 !== 0 ? 'md:flex-row-reverse' : ''}`}>
                                        <div className="hidden md:flex absolute left-1/2 -translate-x-1/2 top-6 z-10">
                                            <div className={`w-12 h-12 rounded-full bg-gradient-to-br ${item.gradient} flex items-center justify-center text-white shadow-lg border-2 border-slate-950`}>{item.icon}</div>
                                        </div>
                                        <div className={`md:w-1/2 ${i % 2 === 0 ? 'md:pr-16' : 'md:pl-16'}`}>
                                            <div className="bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-2xl p-8 hover:border-slate-700 transition-all">
                                                <div className={`inline-flex items-center gap-2 bg-gradient-to-r ${item.gradient} text-white text-xs font-black px-4 py-1.5 rounded-full mb-4 shadow-md`}>{item.ano}</div>
                                                <h3 className="text-xl font-bold text-white mb-3">{item.titulo}</h3>
                                                <p className="text-slate-400 leading-relaxed text-sm">{item.desc}</p>
                                            </div>
                                        </div>
                                        <div className="hidden md:block md:w-1/2" />
                                    </div>
                                </ScrollReveal>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* VALORES */}
            <section className="relative z-10 py-24 px-4 border-t border-slate-900">
                <div className="max-w-5xl mx-auto">
                    <ScrollReveal className="text-center mb-16">
                        <h2 className="text-4xl md:text-5xl font-black text-white mb-4">Nossos Pilares</h2>
                        <p className="text-xl text-slate-400 max-w-2xl mx-auto">Os valores que guiam cada linha de código e cada decisão de produto.</p>
                    </ScrollReveal>
                    <div className="grid md:grid-cols-3 gap-8">
                        {valores.map((v, i) => (
                            <ScrollReveal key={i} delay={i * 0.1}>
                                <motion.div whileHover={{ y: -6 }} className="p-10 text-center h-full bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-[2rem] hover:border-slate-700 transition-all group">
                                    <div className={`w-16 h-16 rounded-2xl bg-gradient-to-br ${v.gradient} flex items-center justify-center text-white mx-auto mb-6 shadow-lg group-hover:scale-110 transition-transform`}>{v.icon}</div>
                                    <h3 className="text-xl font-bold text-white mb-4">{v.title}</h3>
                                    <p className="text-slate-400 leading-relaxed">{v.desc}</p>
                                </motion.div>
                            </ScrollReveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="relative z-10 py-24 px-4">
                <div className="max-w-4xl mx-auto">
                    <ScrollReveal>
                        <div className="relative rounded-[2.5rem] overflow-hidden p-12 md:p-20 text-center border border-indigo-500/20 bg-gradient-to-br from-indigo-900/30 via-slate-900 to-blue-900/20">
                            <div className="absolute inset-0 bg-[radial-gradient(#6366f120_1px,transparent_1px)] [background-size:20px_20px] pointer-events-none" />
                            <div className="relative z-10">
                                <h2 className="text-4xl md:text-5xl font-black text-white mb-6">Faça parte da revolução</h2>
                                <p className="text-xl text-slate-400 mb-10 max-w-2xl mx-auto">Junte-se a mais de 3.500 empresas que já recebem mais rápido e com menos esforço.</p>
                                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                    <NeonButton href="/pacotes" color="indigo"><Zap className="w-4 h-4 mr-2" />Testar Grátis por 14 dias</NeonButton>
                                    <NeonButton href="/contato" color="blue"><ArrowRight className="w-4 h-4 mr-2" />Falar com a Equipe</NeonButton>
                                </div>
                            </div>
                        </div>
                    </ScrollReveal>
                </div>
            </section>
        </div>
    );
}
