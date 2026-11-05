import { Route, Routes } from 'react-router-dom';
import { useEffect } from 'react';
import Header from './components/Header';
import Footer from './components/Footer';
import PwaFooter from './components/PwaFooter';
import BackToTop from './components/BackToTop';
import { usePwaInstalled } from './pwa/usePwaInstalled';
import { useSite } from './context/SiteContext';
import { Lock, ArrowLeft } from 'lucide-react';

// Páginas
import Home from './pages/Home';
import Sobre from './pages/Sobre';
import Recursos from './pages/Recursos';
import Pacotes from './pages/Pacotes';
import Contato from './pages/Contato';
import FAQ from './pages/FAQ';
import Blog from './pages/Blog';
import Article from './pages/Article';
import Funil from './pages/Funil';
import Privacidade from './pages/Privacidade';
import Termos from './pages/Termos';
import Cookies from './pages/Cookies';
import Integracoes from './pages/Integracoes';
import Marketplace from './pages/Marketplace';
import Changelog from './pages/Changelog';

function ServerRedirect({ to }: { to: string }) {
    useEffect(() => { window.location.href = to; }, [to]);
    return null;
}

function PageGuard({ slug, children }: { slug: string; children: React.ReactNode }) {
    const site = useSite();
    if (site?.pages_status && site.pages_status[slug] === false) {
        return (
            <div className="min-h-[75vh] flex flex-col items-center justify-center p-8 text-center bg-slate-900 text-white">
                <div className="w-20 h-20 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6 shadow-xl shadow-amber-500/5">
                    <Lock className="w-10 h-10" />
                </div>
                <h1 className="text-3xl sm:text-4xl font-black mb-3 text-white tracking-tight">
                    Página Temporariamente Indisponível
                </h1>
                <p className="text-slate-400 max-w-md mx-auto mb-8 text-base leading-relaxed">
                    Esta seção foi pausada temporariamente para manutenção pela administração. Retorne em breve ou explore outras áreas.
                </p>
                {slug !== 'home' ? (
                    <a
                        href="/"
                        className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/25 transition-all hover:scale-105"
                    >
                        <ArrowLeft className="w-4 h-4" /> Voltar para o Início
                    </a>
                ) : (
                    <a
                        href="/login"
                        className="inline-flex items-center gap-2 px-6 py-3 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl border border-slate-700 transition"
                    >
                        Acessar Área do Cliente
                    </a>
                )}
            </div>
        );
    }
    return <>{children}</>;
}

export default function App() {
    const isPwa = usePwaInstalled();
    useEffect(() => {
        document.body.classList.toggle('pwa-mobile', isPwa);
        return () => document.body.classList.remove('pwa-mobile');
    }, [isPwa]);

    return (
        <div className="min-h-screen flex flex-col bg-slate-50 text-slate-900 font-sans selection:bg-indigo-500/30 selection:text-indigo-900">
            {/* Header fixo no topo ao rolar */}
            <Header />

            <main className="flex-1">
                <Routes>
                    <Route path="/" element={<PageGuard slug="home"><Home /></PageGuard>} />
                    <Route path="/sobre" element={<PageGuard slug="sobre"><Sobre /></PageGuard>} />
                    <Route path="/recursos" element={<PageGuard slug="recursos"><Recursos /></PageGuard>} />
                    <Route path="/pacotes" element={<PageGuard slug="pacotes"><Pacotes /></PageGuard>} />
                    <Route path="/contato" element={<PageGuard slug="contato"><Contato /></PageGuard>} />
                    <Route path="/faq" element={<PageGuard slug="faq"><FAQ /></PageGuard>} />
                    <Route path="/blog" element={<PageGuard slug="blog"><Blog /></PageGuard>} />
                    <Route path="/blog/:slug" element={<PageGuard slug="blog"><Article /></PageGuard>} />
                    <Route path="/funil" element={<PageGuard slug="funil"><Funil /></PageGuard>} />
                    <Route path="/privacidade" element={<PageGuard slug="privacidade"><Privacidade /></PageGuard>} />
                    <Route path="/termos" element={<PageGuard slug="termos"><Termos /></PageGuard>} />
                    <Route path="/cookies" element={<PageGuard slug="cookies"><Cookies /></PageGuard>} />
                    <Route path="/integracoes" element={<PageGuard slug="integracoes"><Integracoes /></PageGuard>} />
                    <Route path="/marketplace" element={<PageGuard slug="marketplace"><Marketplace /></PageGuard>} />
                    <Route path="/changelog" element={<PageGuard slug="changelog"><Changelog /></PageGuard>} />

                    {/* PWA footer redirects - full page navigation to server routes */}
                    <Route path="/cobrancas" element={<ServerRedirect to="/painel/cobranca" />} />
                    <Route path="/notificacoes" element={<ServerRedirect to="/painel/notificacoes" />} />
                    <Route path="/perfil" element={<ServerRedirect to="/painel/perfil" />} />
                    
                    <Route path="*" element={
                        <div className="min-h-[70vh] flex flex-col items-center justify-center p-8 text-center">
                            <h1 className="text-6xl font-extrabold text-slate-300 mb-4">404</h1>
                            <h2 className="text-2xl font-bold text-slate-900 mb-2">Página não encontrada</h2>
                            <p className="text-slate-600 mb-8">A página que você está procurando não existe ou foi movida.</p>
                            <a href="/" className="px-6 py-3 bg-indigo-600 text-white font-bold rounded-full hover:bg-indigo-700 transition">
                                Voltar para o Início
                            </a>
                        </div>
                    } />
                </Routes>
            </main>

            {/* Footer normal sempre */}
            <Footer />

            {/* Footer adicional - aparece SOMENTE quando o site esta rodando como PWA instalado */}
            {isPwa && <PwaFooter />}

            <BackToTop />
        </div>
    );
}
