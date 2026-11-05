import { useSite } from '@/context/SiteContext';
import AnimatedCounter from '@/components/AnimatedCounter';
import { motion } from 'framer-motion';
import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { NeonButton } from '@/components/ui/NeonButton';
import { useState } from 'react';
import {
  Bot, ShieldCheck, CreditCard, Smartphone, Plug,
  Code2, Globe2, Palette, ArrowRight, CheckCircle2, Zap,
  Sparkles, Check, X, TrendingUp, Users, MessageSquare, Shield,
} from 'lucide-react';
import type { Plan } from '@/api/site';

const defaultFeatures = [
  { icon: 'bot', title: 'Automacao 24/7', desc: 'Cobrancas automaticas antes, no dia e apos o vencimento. Seu sistema trabalha enquanto voce dorme.', gradient: 'from-blue-500 to-cyan-500' },
  { icon: 'credit-card', title: 'PIX com Baixa Instantanea', desc: 'Link de pagamento no WhatsApp e conciliacao automatica do recebimento.', gradient: 'from-green-500 to-emerald-500' },
  { icon: 'smartphone', title: 'WhatsApp com Protecao Operacional', desc: 'Integracao com warmup inteligente, cooldowns e controles anti-ban.', gradient: 'from-purple-500 to-pink-500' },
  { icon: 'shield-check', title: 'Anti-Fraude', desc: 'Validacao de CPF/CNPJ e enriquecimento automatico de endereco.', gradient: 'from-red-500 to-orange-500' },
  { icon: 'plug', title: 'Importacao Facil', desc: 'Importe clientes do Excel, CSV ou via API nativa.', gradient: 'from-orange-500 to-amber-500' },
  { icon: 'check-circle', title: 'Recuperacao de Receita', desc: 'Lembretes no momento certo ajudam a reduzir atrasos e acelerar a baixa.', gradient: 'from-cyan-500 to-blue-500' },
  { icon: 'globe', title: 'Multi-Gateway', desc: 'Mercado Pago, Efi Pro, SumUp ou PIX direto.', gradient: 'from-pink-500 to-rose-500' },
  { icon: 'code', title: 'Templates Personalizados', desc: 'Editor visual com variaveis dinamicas para mensagens e cobrancas.', gradient: 'from-indigo-500 to-blue-500' },
  { icon: 'palette', title: 'White Label Completo', desc: 'Sua logo, suas cores e seu dominio no painel.', gradient: 'from-yellow-500 to-orange-500' },
];

const staticPlans = [
  {
    name: 'Starter', slug: 'starter', price: 49.90, highlight: false, badge: null,
    trial_days: 7, max_instances: 1, max_customers: 100,
    description: 'Para quem quer sair do manual, organizar cobrancas e comecar com baixo custo.',
    features: ['1 numero WhatsApp', 'Ate 100 clientes', '200 faturas por mes', 'PIX automatico', 'Lembretes automaticos', 'Gestao basica da carteira'],
    notIncluded: ['API publica', 'White Label', 'Suporte prioritario'],
  },
  {
    name: 'Profissional', slug: 'profissional', price: 99.90, highlight: true, badge: 'Mais Popular',
    trial_days: 14, max_instances: 3, max_customers: 1000,
    description: 'A escolha certa para empresas que cresceram e agora precisam de mais automacao e conversao.',
    features: ['3 numeros WhatsApp', 'Ate 1.000 clientes', '2.000 faturas por mes', 'Multi-gateway', 'Parcelamento em 12x', 'Templates avancados', 'Relatorios completos', 'Suporte prioritario'],
    notIncluded: ['White Label personalizado'],
  },
  {
    name: 'Empresarial', slug: 'empresarial', price: 249.90, highlight: false, badge: null,
    trial_days: 14, max_instances: 10, max_customers: 10000,
    description: 'Para operacoes em escala que exigem alta capacidade, integracoes e suporte superior.',
    features: ['10 numeros WhatsApp', 'Ate 10.000 clientes', '20.000 faturas por mes', 'Multi-gateway', 'API publica', 'Integracao WHMCS e cPanel', 'White Label personalizado', 'Suporte 24/7 dedicado', 'SLA 99,9%'],
    notIncluded: [],
  },
];

const iconMap: Record<string, JSX.Element> = {
  'bot': <Bot className="w-7 h-7" />,
  'credit-card': <CreditCard className="w-7 h-7" />,
  'smartphone': <Smartphone className="w-7 h-7" />,
  'shield-check': <ShieldCheck className="w-7 h-7" />,
  'plug': <Plug className="w-7 h-7" />,
  'check-circle': <CheckCircle2 className="w-7 h-7" />,
  'globe': <Globe2 className="w-7 h-7" />,
  'code': <Code2 className="w-7 h-7" />,
  'palette': <Palette className="w-7 h-7" />,
};

const metricIcons = [
  <TrendingUp className="w-6 h-6" key="trend" />,
  <Users className="w-6 h-6" key="users" />,
  <MessageSquare className="w-6 h-6" key="messages" />,
  <Shield className="w-6 h-6" key="shield" />,
];

const metricColors = ['text-emerald-400', 'text-blue-400', 'text-purple-400', 'text-cyan-400'];

function isInternalPlan(plan: Pick<Plan, 'name' | 'slug'>) {
  const value = `${plan.name} ${plan.slug}`.toLowerCase();
  return ['admin', 'operacional', 'sandbox', 'demo', 'interno', 'internal'].some((term) => value.includes(term));
}

function pickPublicPlans<T extends Pick<Plan, 'name' | 'slug' | 'highlight' | 'sort'>>(items: T[]) {
  return [...items]
    .filter((item) => !isInternalPlan(item))
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

export default function Home() {
  const site = useSite();
  const [cycle, setCycle] = useState<'monthly' | 'annual'>('annual');
  const page = site?.pages?.home || {};
  const hero = page.hero || {};
  const featuresHeader = page.features_header || {};
  const finalCta = page.final_cta || {};
  const metrics = Array.isArray(page.metrics) && page.metrics.length
    ? page.metrics
    : [
        { value: 72, suffix: '%', label: 'Reducao de inadimplencia', decimals: 0 },
        { value: 3500, suffix: '+', label: 'Empresas usando', decimals: 0 },
        { value: 2.5, suffix: 'M', label: 'Mensagens/mes', decimals: 1 },
        { value: 99.9, suffix: '%', label: 'Uptime monitorado', decimals: 1 },
      ];
  const features = (Array.isArray(page.features) && page.features.length ? page.features : defaultFeatures).map((item: any) => ({
    icon: iconMap[item.icon] || <Bot className="w-7 h-7" />,
    title: item.title,
    desc: item.desc,
    gradient: item.gradient || 'from-blue-500 to-cyan-500',
  }));

  const plans: typeof staticPlans = (() => {
    const apiPlans = pickPublicPlans((site?.plans || []).filter((plan: Plan) => !plan.is_demo));
    if (apiPlans.length >= 3) {
      return apiPlans.map((plan: Plan) => ({
        name: getPlanDisplayName(plan, apiPlans.indexOf(plan)),
        slug: plan.slug,
        price: parseFloat(String(plan.price || 0)),
        highlight: !!plan.highlight,
        badge: plan.badge || null,
        trial_days: plan.trial_days || 7,
        max_instances: plan.max_instances || 1,
        max_customers: plan.max_customers || 100,
        description: plan.description || '',
        features: Array.isArray(plan.features) ? plan.features : JSON.parse(String((plan.features as any) || '[]')),
        notIncluded: [],
      }));
    }
    return staticPlans.map((plan, index) => ({ ...plan, name: getPlanDisplayName(plan, index) }));
  })();

  const getPrice = (price: number) => cycle === 'annual' ? (price * 0.8).toFixed(2) : price.toFixed(2);
  const testimonials = site?.testimonials || [];

  return (
    <div className="bg-slate-950 text-slate-300">
      <section className="relative overflow-hidden min-h-screen flex items-center">
        <div className="absolute inset-0 z-0">
          <motion.div animate={{ scale: [1, 1.3, 1], opacity: [0.3, 0.6, 0.3] }} transition={{ duration: 8, repeat: Infinity }} className="absolute top-1/4 left-1/4 w-[600px] h-[600px] bg-blue-700/20 rounded-full blur-[140px]" />
          <motion.div animate={{ scale: [1, 1.5, 1], opacity: [0.2, 0.5, 0.2] }} transition={{ duration: 12, repeat: Infinity, delay: 2 }} className="absolute bottom-1/4 right-1/4 w-[700px] h-[700px] bg-purple-700/20 rounded-full blur-[180px]" />
          <div className="absolute inset-0 bg-[radial-gradient(#ffffff06_1px,transparent_1px)] [background-size:20px_20px]" />
        </div>

        <div className="container mx-auto px-4 py-32 relative z-10">
          <div className="max-w-4xl mx-auto text-center">
            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 backdrop-blur-md mb-8">
              <span className="flex h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-sm font-medium text-slate-300">{hero.kicker || 'Sistema Oficial de Cobranca via WhatsApp'}</span>
            </motion.div>

            <motion.h1 initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-5xl md:text-7xl font-extrabold mb-6 tracking-tight leading-[1.08] text-white">
              {hero.title_before || 'Reduza a inadimplencia'}<br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-400">{hero.title_highlight || 'com automacao inteligente'}</span><br />
              {hero.title_after || 'com WhatsApp'}
            </motion.h1>

            <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }} className="text-xl md:text-2xl mb-10 text-slate-400 max-w-3xl mx-auto leading-relaxed">
              {hero.description || 'Plataforma completa de cobranca automatizada. Envie faturas, lembretes e links de pagamento PIX diretamente no WhatsApp do seu cliente.'}
            </motion.p>

            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }} className="flex flex-col items-center justify-center gap-4 sm:flex-row mb-12">
              <NeonButton color="blue" href={hero.primary_cta_url || '/cadastro'} className="public-cta-button w-full max-w-[18rem] px-6 py-4 font-bold sm:w-auto sm:max-w-none sm:px-10 sm:py-5">
                {hero.primary_cta_label || 'Testar Gratis por 7 dias'} <ArrowRight className="w-5 h-5 ml-2" />
              </NeonButton>
              <motion.a whileHover={{ scale: 1.02 }} href={hero.secondary_cta_url || '/pacotes'} className="public-cta-button inline-flex min-h-12 w-full max-w-[18rem] items-center justify-center gap-2 whitespace-nowrap px-6 py-4 rounded-full bg-white/5 border border-white/10 text-white font-bold hover:bg-white/10 backdrop-blur-md transition-all sm:w-auto sm:max-w-none sm:px-10 sm:py-5">
                {hero.secondary_cta_label || 'Ver Planos e Precos'}
              </motion.a>
            </motion.div>

            <div className="flex flex-wrap justify-center gap-6 text-sm text-slate-400 font-medium">
              {['Sem fidelidade', 'Setup em 5 minutos', 'Cancele quando quiser', 'Suporte em Portugues'].map((text) => (
                <div key={text} className="flex items-center gap-2"><CheckCircle2 className="w-4 h-4 text-emerald-400" />{text}</div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="py-16 border-y border-slate-800 relative z-10">
        <div className="container mx-auto px-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            {metrics.map((metric: any, i: number) => (
              <div key={i} className="p-4">
                <div className={`flex justify-center mb-3 ${metric.color || metricColors[i % metricColors.length]}`}>{metricIcons[i % metricIcons.length]}</div>
                <div className="text-4xl md:text-5xl font-extrabold text-white tracking-tighter">
                  <AnimatedCounter end={Number(metric.value || 0)} suffix={metric.suffix || ''} decimals={metric.decimals || 0} />
                </div>
                <div className="text-slate-500 mt-2 font-medium text-sm uppercase tracking-wider">{metric.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-32 relative">
        <div className="container mx-auto px-4 max-w-7xl">
          <ScrollReveal className="text-center mb-20">
            <span className="inline-block px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-400 font-bold tracking-widest uppercase text-xs mb-6 border border-indigo-500/20">
              {featuresHeader.kicker || 'Tudo que voce precisa'}
            </span>
            <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tight mb-4">
              {featuresHeader.title || 'Um arsenal completo'}<br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">{featuresHeader.highlight || 'contra a inadimplencia'}</span>
            </h2>
            <p className="text-xl text-slate-400 max-w-2xl mx-auto">{featuresHeader.description || 'Cada ferramenta foi pensada para que voce receba mais, gaste menos tempo e nunca perca uma cobranca.'}</p>
          </ScrollReveal>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map((feature: any, i: number) => (
              <ScrollReveal key={i} delay={i * 0.07}>
                <div className="h-full bg-slate-900/50 border border-slate-800 p-8 rounded-3xl hover:border-slate-600 hover:bg-slate-800/60 transition-all group">
                  <div className={`w-14 h-14 rounded-2xl bg-gradient-to-br ${feature.gradient} flex items-center justify-center text-white mb-6 group-hover:scale-110 transition-transform shadow-lg`}>
                    {feature.icon}
                  </div>
                  <h3 className="text-xl font-bold text-white mb-3">{feature.title}</h3>
                  <p className="text-slate-400 leading-relaxed">{feature.desc}</p>
                </div>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      <section className="py-32 relative border-t border-slate-800/50">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-950 via-indigo-950/10 to-slate-950 pointer-events-none" />
        <div className="container mx-auto px-4 relative z-10">
          <ScrollReveal className="text-center mb-12">
            <span className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-400 font-bold tracking-widest uppercase text-xs mb-6 border border-indigo-500/20">
              <Sparkles className="w-3.5 h-3.5 animate-pulse" /> Planos e Precos
            </span>
            <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tight mb-4">
              Precos transparentes.<br /><span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">Sem surpresas.</span>
            </h2>
            <p className="text-xl text-slate-400 max-w-2xl mx-auto">Escolha o plano ideal para o seu volume de cobrancas. Todos com teste gratuito, sem cartao de credito.</p>
          </ScrollReveal>

          <div className="flex justify-center mb-12">
            <div className="inline-flex bg-slate-900/60 backdrop-blur border border-slate-800 rounded-full p-1.5 shadow-xl">
              {(['monthly', 'annual'] as const).map((currentCycle) => (
                <button key={currentCycle} onClick={() => setCycle(currentCycle)} className={`px-8 py-3 rounded-full text-sm font-bold transition-all flex items-center gap-2 ${cycle === currentCycle ? 'bg-indigo-600 text-white shadow-[0_0_20px_rgba(99,102,241,0.4)]' : 'text-slate-400 hover:text-white'}`}>
                  {currentCycle === 'monthly' ? 'Mensal' : 'Anual'}
                  {currentCycle === 'annual' && <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-extrabold ${cycle === 'annual' ? 'bg-indigo-500/30 text-indigo-300' : 'bg-emerald-500/10 text-emerald-400'}`}>-20%</span>}
                </button>
              ))}
            </div>
          </div>

          <div className="grid lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            {plans.map((plan, i) => (
              <motion.div key={plan.slug} initial={{ opacity: 0, y: 40 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 + i * 0.1 }} className={`relative rounded-3xl flex flex-col p-8 border transition-all duration-500 ${plan.highlight ? 'border-indigo-500/60 bg-gradient-to-b from-indigo-950/40 to-slate-900/40 shadow-[0_0_60px_rgba(99,102,241,0.15)] lg:scale-[1.03] z-10' : 'border-slate-800 bg-slate-900/40 hover:border-slate-700'}`}>
                {plan.highlight && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                    <div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-5 py-1.5 rounded-full text-xs font-bold shadow-xl flex items-center gap-1.5 border border-indigo-400/30">
                      <Zap className="w-3.5 h-3.5 text-yellow-300 fill-yellow-300" /> Mais Popular
                    </div>
                  </div>
                )}
                <div className="mb-6">
                  <h3 className="text-2xl font-extrabold text-white mb-2">{plan.name}</h3>
                  <p className="text-slate-400 text-sm leading-relaxed min-h-[40px]">{plan.description}</p>
                </div>

                <div className="mb-8">
                  <div className="flex items-baseline gap-1">
                    <span className="text-slate-500 text-base font-bold">R$</span>
                    <span className="text-5xl font-extrabold text-white tracking-tight">{getPrice(plan.price).split('.')[0]}</span>
                    <span className="text-2xl font-medium text-slate-300">,{getPrice(plan.price).split('.')[1]}</span>
                  </div>
                  <div className="text-slate-500 text-sm mt-1">/mes {cycle === 'annual' && <span className="text-emerald-400 font-bold">(20% off no anual)</span>}</div>
                </div>

                <a href={`/cadastro?plan=${plan.slug}`} className={`w-full text-center py-4 px-6 rounded-2xl font-bold text-base transition-all mb-8 block ${plan.highlight ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:shadow-[0_0_30px_rgba(99,102,241,0.5)] shadow-lg' : 'bg-slate-800 text-white hover:bg-slate-700 border border-slate-700'}`}>
                  {plan.trial_days > 0 ? `Testar ${plan.trial_days} dias gratis` : 'Comecar agora'}
                </a>

                <div className="space-y-3 flex-1">
                  {plan.features.map((feature: string, j: number) => (
                    <div key={j} className="flex items-start gap-3 text-sm">
                      <Check className="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" />
                      <span className="text-slate-300">{feature}</span>
                    </div>
                  ))}
                  {plan.notIncluded?.map((feature: string, j: number) => (
                    <div key={j} className="flex items-start gap-3 text-sm opacity-40">
                      <X className="w-4 h-4 text-slate-500 flex-shrink-0 mt-0.5" />
                      <span className="text-slate-500">{feature}</span>
                    </div>
                  ))}
                </div>
              </motion.div>
            ))}
          </div>

          <ScrollReveal className="text-center mt-12">
            <p className="text-slate-500 text-sm">Todos os planos incluem: SSL, LGPD, backups diarios e suporte em portugues.</p>
            <NeonButton color="indigo" href="/pacotes" className="mt-6 px-8 py-3 text-sm">
              Ver comparativo completo de planos
            </NeonButton>
          </ScrollReveal>
        </div>
      </section>

      {testimonials.length > 0 && (
        <section className="py-32 border-t border-slate-800/50 overflow-hidden">
          <div className="container mx-auto px-4 mb-12 text-center">
            <ScrollReveal>
              <h2 className="text-4xl font-extrabold text-white tracking-tight">{page.testimonials_title || 'Amado por empresas de todo o Brasil'}</h2>
            </ScrollReveal>
          </div>
          <div className="testimonial-marquee relative overflow-hidden group">
            <div className="testimonial-marquee-track flex w-max py-4">
              {[0, 1].map((dup) => (
                <div key={dup} className="testimonial-marquee-set flex shrink-0 gap-6 pr-6 md:gap-8 md:pr-8" aria-hidden={dup === 1}>
                  {testimonials.map((testimonial: any, i: number) => (
                    <div key={`${testimonial.id ?? testimonial.name ?? 'testimonial'}-${dup}-${i}`} className="testimonial-card bg-slate-900/60 border border-slate-800 p-6 whitespace-normal rounded-2xl shadow-lg flex flex-col">
                      <div className="flex gap-1 mb-4 text-yellow-400">
                        {Array.from({ length: 5 }).map((_, starIndex) => <span key={starIndex}>{starIndex < testimonial.rating ? '★' : '☆'}</span>)}
                      </div>
                      <p className="text-slate-300 mb-6 line-clamp-4 leading-relaxed">"{testimonial.content}"</p>
                      <div className="flex items-center gap-3 mt-auto">
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-white text-sm">{testimonial.name?.[0]}</div>
                        <div className="min-w-0">
                          <div className="font-bold text-white text-sm truncate">{testimonial.name}</div>
                          <div className="text-xs text-slate-500 truncate">{testimonial.role}</div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      <section className="py-32 border-t border-slate-800/50">
        <div className="container mx-auto px-4">
          <ScrollReveal>
            <div className="max-w-4xl mx-auto text-center bg-gradient-to-br from-blue-900/40 to-indigo-900/40 border border-indigo-500/20 rounded-3xl p-12 md:p-20 shadow-[0_0_80px_rgba(99,102,241,0.1)] backdrop-blur">
              <h2 className="text-4xl md:text-6xl font-extrabold mb-6 text-white tracking-tight">{finalCta.title || 'Pronto para acabar com a inadimplencia?'}</h2>
              <p className="text-xl text-slate-400 mb-10 max-w-2xl mx-auto">{finalCta.description || 'Junte-se a mais de 3.500 empresas que automatizaram suas cobrancas. Comece agora, sem cartao de credito.'}</p>
              <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                <motion.a whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.97 }} href={finalCta.primary_url || '/cadastro'} className="public-cta-button inline-flex min-h-14 w-full max-w-[18rem] items-center justify-center whitespace-nowrap bg-white text-indigo-900 font-extrabold px-6 py-4 rounded-full shadow-2xl hover:shadow-white/20 transition-shadow sm:w-auto sm:max-w-none sm:px-10 sm:py-5">
                  {finalCta.primary_label || 'Comecar teste gratis agora'}
                </motion.a>
                <motion.a whileHover={{ scale: 1.02 }} href={finalCta.secondary_url || '/contato'} className="public-cta-button inline-flex min-h-14 w-full max-w-[18rem] items-center justify-center whitespace-nowrap border border-white/20 text-white font-bold px-6 py-4 rounded-full hover:bg-white/10 transition-all sm:w-auto sm:max-w-none sm:px-10 sm:py-5">
                  {finalCta.secondary_label || 'Falar com vendas'}
                </motion.a>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </section>

      <style dangerouslySetInnerHTML={{ __html: `
        @keyframes testimonialMarquee {
          0% { transform: translate3d(0, 0, 0); }
          100% { transform: translate3d(-50%, 0, 0); }
        }

        .testimonial-marquee {
          --testimonial-gap: 1.5rem;
          mask-image: linear-gradient(90deg, transparent 0, #000 5rem, #000 calc(100% - 5rem), transparent 100%);
        }

        .testimonial-marquee-track {
          animation: testimonialMarquee 55s linear infinite;
          will-change: transform;
        }

        .testimonial-marquee:hover .testimonial-marquee-track {
          animation-play-state: paused;
        }

        .testimonial-card {
          width: min(380px, calc(100vw - 3rem));
          min-height: 260px;
        }

        @media (min-width: 768px) {
          .testimonial-marquee {
            --testimonial-gap: 2rem;
          }
        }

        @media (prefers-reduced-motion: reduce) {
          .testimonial-marquee-track {
            animation: none;
          }
        }
      ` }} />
    </div>
  );
}
