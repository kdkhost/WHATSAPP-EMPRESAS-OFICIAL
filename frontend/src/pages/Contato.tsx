import { useSite } from '@/context/SiteContext';
import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { NeonButton } from '@/components/ui/NeonButton';
import { Mail, MessageCircle, MapPin, Send, Clock, CheckCircle2, Headphones } from 'lucide-react';
import Swal from 'sweetalert2';

const canais = [
    {
        icon: 'message-circle',
        title: 'WhatsApp de Vendas',
        sub: 'Atendimento imediato em horario comercial.',
        info: '+55 11 99999-8888',
        iconBg: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
        href: 'https://wa.me/5511999998888',
    },
    {
        icon: 'mail',
        title: 'Suporte por E-mail',
        sub: 'Respostas detalhadas em ate 24 horas uteis.',
        info: 'ajuda@empresa.com.br',
        iconBg: 'bg-blue-500/10 border-blue-500/20 text-blue-400',
        href: 'mailto:ajuda@empresa.com.br',
    },
    {
        icon: 'headphones',
        title: 'Central de Ajuda',
        sub: 'Documentacao e tutoriais em video.',
        info: 'docs.empresa.com.br',
        iconBg: 'bg-violet-500/10 border-violet-500/20 text-violet-400',
        href: '#',
    },
    {
        icon: 'map-pin',
        title: 'Sede Oficial',
        sub: 'Av. Paulista, 1000 - Bela Vista\nSao Paulo - SP, 01310-100',
        info: '',
        iconBg: 'bg-rose-500/10 border-rose-500/20 text-rose-400',
        href: '#',
    },
];

const horarios = [
    { day: 'Segunda a Sexta', time: '08h as 18h' },
    { day: 'Sabado', time: '09h as 13h' },
    { day: 'Domingo', time: 'Fechado' },
];

async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = e.currentTarget;

    await Swal.fire({
        title: 'Enviando mensagem...',
        didOpen: () => Swal.showLoading(),
        timer: 1500,
        showConfirmButton: false,
        background: '#0f172a',
        color: '#e2e8f0',
    });

    form.reset();

    await Swal.fire({
        icon: 'success',
        title: 'Mensagem Enviada!',
        html: 'Nossa equipe entrara em contato em <b>ate 24 horas uteis</b>.',
        confirmButtonText: 'Perfeito!',
        background: '#0f172a',
        color: '#e2e8f0',
        iconColor: '#10b981',
        confirmButtonColor: '#6366f1',
        customClass: {
            popup: 'rounded-3xl border border-slate-700',
            confirmButton: 'rounded-xl px-8 font-bold',
        },
    });
}

export default function Contato() {
    const site = useSite();
    const page = site?.pages?.contato || {};
    const hero = page.hero || {};
    const response = page.response || {};
    const dynamicChannels = Array.isArray(page.channels) && page.channels.length ? page.channels : canais;
    const dynamicSchedules = Array.isArray(page.schedules) && page.schedules.length ? page.schedules : horarios;
    const iconMap: Record<string, JSX.Element> = {
        'message-circle': <MessageCircle className="w-6 h-6" />,
        'mail': <Mail className="w-6 h-6" />,
        'headphones': <Headphones className="w-6 h-6" />,
        'map-pin': <MapPin className="w-6 h-6" />,
    };

    return (
        <div className="bg-slate-950 min-h-screen text-slate-300 overflow-hidden">
            <div className="fixed inset-0 z-0 pointer-events-none">
                <div className="absolute top-1/4 left-1/4 w-[400px] h-[400px] bg-blue-600/8 rounded-full blur-[150px]" />
                <div className="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] bg-indigo-600/8 rounded-full blur-[180px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff06_1px,transparent_1px)] [background-size:20px_20px]" />
            </div>

            <section className="relative z-10 pt-32 pb-16 px-4 text-center">
                <div className="max-w-3xl mx-auto">
                    <ScrollReveal>
                        <span className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-400 font-bold tracking-widest uppercase text-xs mb-8 border border-indigo-500/20">
                            <Headphones className="w-3.5 h-3.5" />
                            {hero.kicker || 'Fale com a Equipe'}
                        </span>
                        <h1 className="text-5xl md:text-6xl font-black tracking-tight mb-4 text-white">
                            {hero.title_before || 'Fale com'}{' '}
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400">
                                {hero.title_highlight || 'Especialistas'}
                            </span>
                        </h1>
                        <p className="text-xl text-slate-400 font-medium">
                            {hero.description || 'Estamos aqui para escalar o seu negocio e tirar todas as suas duvidas.'}
                        </p>
                    </ScrollReveal>
                </div>
            </section>

            <div className="relative z-10 pb-32 px-4">
                <div className="max-w-6xl mx-auto">
                    <div className="grid lg:grid-cols-2 gap-12 items-start">
                        <ScrollReveal direction="left">
                            <div className="p-8 bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-[2rem] shadow-2xl">
                                <h3 className="text-2xl font-bold mb-2 text-white">Envie uma mensagem</h3>
                                <p className="text-slate-500 text-sm mb-8">Retornamos em ate 24 horas uteis. Sem robos, sem scripts.</p>

                                <form className="space-y-5" onSubmit={handleSubmit}>
                                    <div className="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">Nome Completo</label>
                                            <input type="text" className="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition-all placeholder-slate-600" placeholder="Joao da Silva" required />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">WhatsApp</label>
                                            <input type="tel" className="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition-all placeholder-slate-600" placeholder="(11) 99999-8888" />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">E-mail Corporativo</label>
                                        <input type="email" className="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition-all placeholder-slate-600" placeholder="joao@empresa.com.br" required />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">Assunto</label>
                                        <select className="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition-all appearance-none cursor-pointer">
                                            <option>Duvida Comercial</option>
                                            <option>Demonstracao da Plataforma</option>
                                            <option>Suporte Tecnico</option>
                                            <option>Parceria Institucional</option>
                                            <option>Outro</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">Mensagem</label>
                                        <textarea rows={5} className="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition-all resize-none placeholder-slate-600" placeholder="Descreva sua necessidade e como podemos ajudar..." required />
                                    </div>
                                    <NeonButton type="submit" color="indigo" className="w-full py-4 font-bold text-base">
                                        <Send className="w-4 h-4 mr-2" /> Enviar Mensagem
                                    </NeonButton>
                                    <p className="text-center text-xs text-slate-600">Seus dados estao protegidos conforme a LGPD.</p>
                                </form>
                            </div>
                        </ScrollReveal>

                        <div className="space-y-5">
                            {dynamicChannels.map((channel: any, i: number) => (
                                <ScrollReveal key={i} delay={i * 0.1}>
                                    <a href={channel.href} className="flex items-start gap-5 p-6 bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-2xl hover:bg-slate-800/60 hover:border-slate-700 transition-all group">
                                        <div className={`w-14 h-14 ${channel.iconBg || 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400'} border rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform shrink-0`}>
                                            {iconMap[channel.icon] || <MessageCircle className="w-6 h-6" />}
                                        </div>
                                        <div>
                                            <h4 className="text-lg font-bold text-white mb-1">{channel.title}</h4>
                                            <p className="text-slate-500 text-sm whitespace-pre-line">{channel.sub}</p>
                                            {channel.info && <p className="font-bold mt-2 text-slate-300 text-sm">{channel.info}</p>}
                                        </div>
                                    </a>
                                </ScrollReveal>
                            ))}

                            <ScrollReveal delay={0.4}>
                                <div className="p-6 bg-slate-900/50 backdrop-blur-xl border border-slate-800 rounded-2xl">
                                    <div className="flex items-center gap-3 mb-5">
                                        <div className="w-10 h-10 bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-xl flex items-center justify-center">
                                            <Clock className="w-5 h-5" />
                                        </div>
                                        <h4 className="text-lg font-bold text-white">Horario de Atendimento</h4>
                                    </div>
                                    {dynamicSchedules.map((item: any, i: number) => {
                                        const label = item.day || item.dia;
                                        const value = item.time || item.hora;
                                        return (
                                            <div key={i} className={`flex justify-between py-3 ${i < dynamicSchedules.length - 1 ? 'border-b border-slate-800' : ''}`}>
                                                <span className="text-slate-400 text-sm font-medium">{label}</span>
                                                <span className={`text-sm font-bold ${value === 'Fechado' ? 'text-red-400' : 'text-emerald-400'}`}>{value}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </ScrollReveal>

                            <ScrollReveal delay={0.5}>
                                <div className="flex items-center gap-4 p-5 bg-emerald-500/5 border border-emerald-500/20 rounded-2xl">
                                    <CheckCircle2 className="w-8 h-8 text-emerald-400 shrink-0" />
                                    <div>
                                        <p className="text-white font-bold text-sm">{response.title || 'Tempo medio de resposta: 2 horas'}</p>
                                        <p className="text-slate-500 text-xs mt-0.5">{response.hint || 'Medido em dias uteis, horario comercial.'}</p>
                                    </div>
                                </div>
                            </ScrollReveal>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
