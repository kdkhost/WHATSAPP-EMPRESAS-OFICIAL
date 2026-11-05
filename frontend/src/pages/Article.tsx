import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, Clock, User, Share2 } from 'lucide-react';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

const fallbackArticle = {
    title: 'Titulo do Artigo',
    category: 'Conteudo Exclusivo',
    date: '10 Mai 2026',
    author: 'Equipe Editorial',
    readTime: '5 min',
    image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop',
    content: `
        <p>Seja bem-vindo ao nosso blog oficial. Este e um artigo de demonstracao que mostra como o conteudo do seu blog ficara estruturado dentro do portal.</p>
        <h2>O poder da automacao no WhatsApp</h2>
        <p>Manter um fluxo de caixa saudavel exige cobranca previsivel, rastreavel e integrada ao canal mais rapido de resposta do cliente.</p>
        <blockquote>A maioria dos atrasos ocorre por esquecimento, nao por falta de fundos.</blockquote>
        <ul>
            <li>Reducao do tempo de recebimento.</li>
            <li>Taxa de abertura superior ao e-mail tradicional.</li>
            <li>Conforto para o cliente pagar com poucos cliques.</li>
        </ul>
    `,
};

export default function Article() {
    const { slug } = useParams();
    const site = useSite();
    const page = site?.pages?.blog || {};
    const posts = Array.isArray(page.posts) ? page.posts : [];
    const article = posts.find((post: any) => String(post.slug || '').trim() === String(slug || '').trim()) || {
        ...fallbackArticle,
        title: slug ? slug.replace(/-/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()) : fallbackArticle.title,
    };

    if (page.article_html) {
        return (
            <CmsHtmlPage
                page={{
                    hero: {
                        kicker: page.article_kicker || 'Conteudo Editorial',
                        title: page.article_title || article.title,
                        description: page.article_description || 'Conteudo dinamico gerenciado pelo painel administrativo.',
                    },
                    html: page.article_html,
                }}
                fallback={{
                    kicker: 'Conteudo Editorial',
                    title: article.title,
                    description: 'Conteudo dinamico gerenciado pelo painel administrativo.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 min-h-screen pt-32 pb-32 relative text-slate-300">
            <div className="absolute inset-0 z-0 pointer-events-none">
                <div className="absolute top-0 right-1/4 w-[400px] h-[400px] bg-indigo-600/10 rounded-full blur-[150px]" />
            </div>

            <div className="container mx-auto px-4 max-w-4xl relative z-10">
                <Link to="/blog" className="inline-flex items-center gap-2 text-indigo-400 hover:text-indigo-300 transition-colors font-bold mb-8">
                    <ArrowLeft className="w-5 h-5" /> Voltar para o Blog
                </Link>

                <div className="mb-12">
                    <div className="flex flex-wrap gap-4 items-center text-xs font-bold uppercase tracking-wider text-indigo-400 mb-6">
                        <span className="bg-indigo-500/10 px-3 py-1 rounded-full border border-indigo-500/20">{article.category}</span>
                        <div className="flex items-center gap-1.5 text-slate-500">
                            <Clock className="w-4 h-4" /> {article.readTime}
                        </div>
                        <div className="flex items-center gap-1.5 text-slate-500">
                            <User className="w-4 h-4" /> {article.author}
                        </div>
                    </div>

                    <h1 className="text-4xl md:text-6xl font-extrabold tracking-tight mb-8 text-white leading-tight">
                        {article.title}
                    </h1>

                    <div className="flex justify-between items-center border-y border-slate-800 py-4 mb-12">
                        <span className="text-slate-400 font-medium">Publicado em {article.date}</span>
                        <button className="flex items-center gap-2 text-slate-400 hover:text-indigo-400 transition-colors">
                            <Share2 className="w-5 h-5" /> Compartilhar
                        </button>
                    </div>

                    <img
                        src={article.image}
                        alt={article.title}
                        className="w-full h-[400px] md:h-[500px] object-cover rounded-3xl border border-slate-800 shadow-2xl mb-12"
                    />

                    <article
                        className="prose prose-lg prose-invert max-w-none prose-headings:text-white prose-a:text-indigo-400 prose-img:rounded-2xl"
                        dangerouslySetInnerHTML={{ __html: article.content || fallbackArticle.content }}
                    />
                </div>

                <div className="mt-16 pt-8 border-t border-slate-800 text-center">
                    <h3 className="text-2xl font-bold text-white mb-6">Gostou deste artigo?</h3>
                    <Link to="/cadastro" className="inline-block px-8 py-4 bg-indigo-600 text-white font-bold rounded-full hover:bg-indigo-700 transition shadow-[0_0_20px_rgba(99,102,241,0.3)]">
                        Comece a automatizar hoje mesmo
                    </Link>
                </div>
            </div>
        </div>
    );
}
