import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { useSite } from '@/context/SiteContext';
import { Link } from 'react-router-dom';

const defaultPosts = [
    {
        slug: 'como-a-automacao-do-whatsapp-salvou-30-das-faturas-vencidas-de-uma-isp',
        title: 'Como a automacao do WhatsApp salvou 30% das faturas vencidas de uma ISP',
        category: 'Estudo de Caso',
        date: '10 Mai 2026',
        image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop',
        readTime: '5 min',
        content: '<p>Exemplo de artigo padrao do portal.</p>',
    },
    {
        slug: 'pix-via-link-a-tendencia-irreversivel-para-o-varejo-online',
        title: 'PIX via Link: A tendencia irreversivel para o varejo online',
        category: 'Pagamentos',
        date: '05 Mai 2026',
        image: 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=2070&auto=format&fit=crop',
        readTime: '4 min',
        content: '<p>Exemplo de artigo padrao do portal.</p>',
    },
    {
        slug: 'lgpd-no-whatsapp-boas-praticas-na-hora-de-cobrar-o-seu-cliente',
        title: 'LGPD no WhatsApp: boas praticas na hora de cobrar o seu cliente',
        category: 'Seguranca',
        date: '28 Abr 2026',
        image: 'https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?q=80&w=2070&auto=format&fit=crop',
        readTime: '7 min',
        content: '<p>Exemplo de artigo padrao do portal.</p>',
    },
];

const defaultFeatured = {
    slug: 'apresentando-a-nova-versao-2',
    image: 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?q=80&w=1974&auto=format&fit=crop',
    badge: 'LANCAMENTO',
    title: 'Apresentando a nova Versao 2.0: multiplas instancias e WAF grafico',
    description: 'Descubra as novidades que preparamos para o maior update do ano.',
};

export default function Blog() {
    const site = useSite();
    const page = site?.pages?.blog || {};
    const hero = page.hero || {};
    const posts = Array.isArray(page.posts) && page.posts.length ? page.posts : defaultPosts;
    const featured = page.featured || defaultFeatured;

    return (
        <div className="bg-slate-950 min-h-screen pt-24 pb-32 relative overflow-hidden text-slate-300">
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[150px]" />
                <div className="absolute bottom-1/4 left-1/4 w-[600px] h-[600px] bg-blue-600/10 rounded-full blur-[180px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff08_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <div className="container mx-auto px-4 max-w-7xl relative z-10">
                <ScrollReveal className="text-center mb-20">
                    <span className="inline-block px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-400 font-bold tracking-widest uppercase text-xs mb-6 border border-indigo-500/20">
                        {hero.kicker || 'Blog e Artigos'}
                    </span>
                    <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 text-white">
                        {hero.title_before || 'Central de'}{' '}
                        <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">
                            {hero.title_highlight || 'Conhecimento'}
                        </span>
                    </h1>
                    <p className="text-xl text-slate-400 max-w-2xl mx-auto font-medium">
                        {hero.description || 'Artigos, dicas, estudos de caso e tutoriais sobre como escalar sua operacao de cobranca.'}
                    </p>
                </ScrollReveal>

                <ScrollReveal>
                    <Link to={`/blog/${featured.slug || defaultFeatured.slug}`} className="block mb-16 relative rounded-[2.5rem] overflow-hidden group cursor-pointer border border-slate-800 shadow-2xl">
                        <div className="absolute inset-0 bg-slate-900/60 group-hover:bg-slate-900/40 transition-colors z-10"></div>
                        <img
                            src={featured.image || defaultFeatured.image}
                            className="w-full h-[500px] object-cover group-hover:scale-105 transition-transform duration-700"
                            alt={featured.title || 'Destaque'}
                        />
                        <div className="absolute bottom-0 left-0 w-full p-10 z-20 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent">
                            <span className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider mb-4 inline-block shadow-[0_0_15px_rgba(99,102,241,0.5)]">
                                {featured.badge || defaultFeatured.badge}
                            </span>
                            <h2 className="text-3xl md:text-5xl font-extrabold text-white mb-4 group-hover:text-indigo-300 transition-colors">
                                {featured.title || defaultFeatured.title}
                            </h2>
                            <p className="text-slate-300 text-lg hidden md:block max-w-3xl">
                                {featured.description || defaultFeatured.description}
                            </p>
                        </div>
                    </Link>
                </ScrollReveal>

                <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {posts.map((post: any, i: number) => (
                        <ScrollReveal key={post.slug || i} delay={i * 0.1}>
                            <Link
                                to={`/blog/${post.slug || slugify(post.title || `post-${i}`)}`}
                                className="block h-full flex flex-col bg-slate-900/40 backdrop-blur-xl border border-slate-800 rounded-[2rem] overflow-hidden group cursor-pointer hover:border-slate-700 hover:bg-slate-800/60 transition-all shadow-lg"
                            >
                                <div className="h-56 overflow-hidden">
                                    <img
                                        src={post.image}
                                        alt={post.title}
                                        className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 opacity-90 group-hover:opacity-100"
                                    />
                                </div>
                                <div className="p-8 flex-1 flex flex-col">
                                    <div className="flex justify-between items-center text-xs font-bold uppercase tracking-wider text-indigo-400 mb-4">
                                        <span className="bg-indigo-500/10 px-3 py-1 rounded-full border border-indigo-500/20">{post.category}</span>
                                        <span className="text-slate-500">{post.readTime}</span>
                                    </div>
                                    <h3 className="text-2xl font-bold text-white mb-4 group-hover:text-indigo-400 transition-colors">
                                        {post.title}
                                    </h3>
                                    <div className="mt-auto text-slate-500 text-sm font-medium pt-6 border-t border-slate-800/50">
                                        Publicado em {post.date}
                                    </div>
                                </div>
                            </Link>
                        </ScrollReveal>
                    ))}
                </div>
            </div>
        </div>
    );
}

function slugify(value: string) {
    return String(value)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}
