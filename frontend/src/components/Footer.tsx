import { Link } from 'react-router-dom';
import { useSite } from '../context/SiteContext';
import { Send, Mail, MessageCircle } from 'lucide-react';

export default function Footer() {
    const site = useSite();
    const logo = site?.app?.logo?.trim();
    const appName = site?.app?.name || 'Cobranca WP';

    const isPageActive = (slug: string) => {
        if (!site?.pages_status) return true;
        return site.pages_status[slug] !== false;
    };

    const produtoLinks = [
        { label: 'Recursos globais', path: '/recursos', slug: 'recursos' },
        { label: 'Planos e precos', path: '/pacotes', slug: 'pacotes' },
        { label: 'Integracoes API', path: '/integracoes', slug: 'integracoes' },
        { label: 'Marketplace', path: '/marketplace', slug: 'marketplace' },
        { label: 'O que ha de novo', path: '/changelog', slug: 'changelog' },
    ].filter(l => isPageActive(l.slug));

    const empresaLinks = [
        { label: 'Nossa historia', path: '/sobre', slug: 'sobre' },
        { label: 'Blog e artigos', path: '/blog', slug: 'blog' },
        { label: 'Fale conosco', path: '/contato', slug: 'contato' },
        { label: 'Central de ajuda', path: '/faq', slug: 'faq' },
    ].filter(l => isPageActive(l.slug));

    const legalLinks = [
        { label: 'Privacidade', path: '/privacidade', slug: 'privacidade' },
        { label: 'Termos de uso', path: '/termos', slug: 'termos' },
        { label: 'Cookies', path: '/cookies', slug: 'cookies' },
    ].filter(l => isPageActive(l.slug));

    return (
        <footer className="border-t border-slate-800 bg-slate-900 pb-12 pt-24 text-slate-300">
            <div className="container mx-auto px-4">
                <div className="mb-16 grid gap-12 md:grid-cols-2 lg:grid-cols-4">
                    <div className="lg:col-span-1">
                        <Link to="/" className="group mb-6 flex items-center gap-3 text-2xl font-extrabold text-white">
                            {logo ? (
                                <img src={logo} alt={appName} className="h-10 w-auto max-w-[190px] object-contain" />
                            ) : (
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg transition-transform group-hover:scale-105">
                                    W
                                </span>
                            )}
                            <span className="tracking-tight">{appName}</span>
                        </Link>
                        <p className="mb-6 font-medium leading-relaxed text-slate-400">
                            A plataforma definitiva para reduzir inadimplencia com automacoes inteligentes no WhatsApp.
                        </p>
                        <div className="flex gap-4">
                            <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-white transition-colors hover:bg-indigo-600">
                                <MessageCircle className="h-5 w-5" />
                            </a>
                            <a href="#" className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-white transition-colors hover:bg-indigo-600">
                                <Mail className="h-5 w-5" />
                            </a>
                        </div>
                    </div>

                    {produtoLinks.length > 0 && (
                        <div>
                            <h3 className="mb-6 text-sm font-bold uppercase tracking-wider text-white">Produto</h3>
                            <ul className="space-y-4 font-medium text-slate-400">
                                {produtoLinks.map(link => (
                                    <li key={link.path}>
                                        <Link to={link.path} className="transition-colors hover:text-indigo-400">
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {empresaLinks.length > 0 && (
                        <div>
                            <h3 className="mb-6 text-sm font-bold uppercase tracking-wider text-white">Empresa</h3>
                            <ul className="space-y-4 font-medium text-slate-400">
                                {empresaLinks.map(link => (
                                    <li key={link.path}>
                                        <Link to={link.path} className="transition-colors hover:text-indigo-400">
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div>
                        <h3 className="mb-6 text-sm font-bold uppercase tracking-wider text-white">Fique por dentro</h3>
                        <p className="mb-4 text-sm font-medium text-slate-400">Assine nossa newsletter para receber novidades exclusivas.</p>
                        <form className="relative mb-8 flex items-center" onSubmit={(e) => e.preventDefault()}>
                            <input
                                type="email"
                                placeholder="Seu e-mail profissional"
                                className="w-full rounded-full border-none bg-slate-800 py-3 pl-5 pr-12 text-sm text-white outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <button type="submit" className="absolute right-2 rounded-full bg-indigo-600 p-2 text-white transition-colors hover:bg-indigo-500">
                                <Send className="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                </div>

                <div className="flex flex-col items-center justify-between gap-4 border-t border-slate-800 pt-8 text-sm font-medium text-slate-500 md:flex-row">
                    <div>&copy; {new Date().getFullYear()} {appName}. Todos os direitos reservados.</div>
                    {legalLinks.length > 0 && (
                        <div className="flex gap-6">
                            {legalLinks.map(link => (
                                <Link key={link.path} to={link.path} className="hover:text-slate-300">
                                    {link.label}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </footer>
    );
}
