import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, useLocation } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Menu, X } from 'lucide-react';
import { cn } from '../lib/utils';
import { useSite } from '../context/SiteContext';

export default function Header() {
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const location = useLocation();
    const site = useSite();
    const [brandImageVisible, setBrandImageVisible] = useState(Boolean(site?.app?.logo?.trim()));
    const [installPrompt, setInstallPrompt] = useState<Event | null>(null);
    const [isStandalone, setIsStandalone] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', onScroll);
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        setBrandImageVisible(Boolean(site?.app?.logo?.trim()));
    }, [site?.app?.logo]);

    useEffect(() => {
        setMobileMenuOpen(false);
        setScrolled(window.scrollY > 20);
    }, [location.pathname]);

    useEffect(() => {
        if (!mobileMenuOpen) {
            return;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const onEsc = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMobileMenuOpen(false);
            }
        };
        window.addEventListener('keydown', onEsc);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', onEsc);
        };
    }, [mobileMenuOpen]);

    useEffect(() => {
        const handler = (event: Event) => {
            event.preventDefault();
            setInstallPrompt(event);
        };
        window.addEventListener('beforeinstallprompt', handler as EventListener);
        const standalone = window.matchMedia('(display-mode: standalone)').matches
            || (navigator as unknown as { standalone?: boolean }).standalone === true;
        setIsStandalone(standalone);
        return () => window.removeEventListener('beforeinstallprompt', handler as EventListener);
    }, []);

    const headerClass = scrolled
        ? 'bg-slate-950/80 backdrop-blur-xl border-b border-slate-900/50 shadow-lg text-white py-3'
        : 'bg-transparent text-white border-b border-transparent py-5';

    const rawLinks = [
        { name: 'Inicio', path: '/', slug: 'home' },
        { name: 'Planos', path: '/pacotes', slug: 'pacotes' },
        { name: 'Recursos', path: '/recursos', slug: 'recursos' },
        { name: 'Demonstracao', path: '/funil', slug: 'funil' },
        { name: 'Suporte', path: '/contato', slug: 'contato' },
    ];

    const links = rawLinks.filter(l => !l.slug || (site?.pages_status ? site.pages_status[l.slug] !== false : true));

    const logo = site?.app?.logo?.trim();
    const appName = site?.app?.name || 'Cobranca WP';

    const brand = (
        <>
            {logo && brandImageVisible ? (
                <img
                    src={logo}
                    alt={appName}
                    className="h-9 w-auto max-w-[168px] object-contain"
                    onError={() => setBrandImageVisible(false)}
                />
            ) : (
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg transition-transform group-hover:scale-105">
                    W
                </span>
            )}
            <span className="tracking-tight">{appName}</span>
        </>
    );

    const mobileBrand = (
        <>
            {logo && brandImageVisible ? (
                <img
                    src={logo}
                    alt={appName}
                    className="h-10 w-10 shrink-0 rounded-xl object-contain"
                    onError={() => setBrandImageVisible(false)}
                />
            ) : (
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-base font-extrabold text-white shadow-lg">
                    W
                </span>
            )}
            <span className="min-w-0 truncate text-lg font-extrabold leading-tight tracking-tight text-white">
                {appName}
            </span>
        </>
    );

    const openInstallPrompt = async () => {
        const deferred = installPrompt as (Event & { prompt?: () => Promise<void>; userChoice?: Promise<{ outcome: string }> }) | null;
        if (!deferred?.prompt) {
            if ((window as unknown as { toast?: { info?: (msg: string) => void } }).toast?.info) {
                (window as unknown as { toast: { info: (msg: string) => void } }).toast.info('Instalacao indisponivel agora. Use o menu do navegador: "Adicionar a tela inicial".');
            } else {
                console.warn('Instalacao indisponivel agora. Use o menu do navegador: "Adicionar a tela inicial".');
            }
            return;
        }
        await deferred.prompt();
        await deferred.userChoice;
        setInstallPrompt(null);
    };

    const mobileDrawer = (
        <AnimatePresence>
            {mobileMenuOpen && (
                <>
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={() => setMobileMenuOpen(false)}
                        className="fixed inset-0 z-[9990] bg-black/70 backdrop-blur-sm md:hidden"
                    />
                    <motion.aside
                        initial={{ x: '-100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '-100%' }}
                        transition={{ type: 'spring', damping: 25, stiffness: 200 }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Menu de navegacao"
                        className="fixed inset-y-0 left-0 z-[10000] flex h-dvh w-[calc(100vw-2rem)] max-w-80 flex-col overflow-hidden border-r border-slate-800 bg-slate-950/98 text-white shadow-2xl shadow-black/40 backdrop-blur-xl md:hidden"
                    >
                        <div className="flex items-center gap-3 border-b border-slate-800 p-4">
                            <Link
                                to="/"
                                className="group flex min-w-0 flex-1 items-center gap-3 overflow-hidden text-white"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                {mobileBrand}
                            </Link>
                            <button
                                type="button"
                                aria-label="Fechar menu"
                                className="ml-auto flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white transition hover:bg-white/10"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <nav className="flex flex-1 flex-col gap-2 overflow-y-auto px-4 py-6">
                            {links.map((link) => {
                                const isActive = location.pathname === link.path;

                                return (
                                    <Link
                                        key={link.path}
                                        to={link.path}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={cn(
                                            'rounded-xl px-4 py-3 text-lg font-bold transition-all',
                                            isActive
                                                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20'
                                                : 'text-slate-300 hover:bg-slate-900/70 hover:text-white',
                                        )}
                                    >
                                        {link.name}
                                    </Link>
                                );
                            })}
                        </nav>

                        <div className="flex flex-col gap-3 border-t border-slate-800 p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                            <a href="/login" className="rounded-xl bg-slate-900 py-3 text-center font-bold text-white transition-all hover:bg-slate-800">Entrar</a>
                            <a href="/cadastro" className="rounded-xl bg-indigo-600 py-3 text-center font-bold text-white shadow-lg shadow-indigo-500/25 transition-all hover:bg-indigo-500">Testar Gratis</a>
                            {!isStandalone && installPrompt ? (
                                <button type="button" onClick={openInstallPrompt} className="rounded-xl border border-slate-700 bg-slate-900/70 py-3 text-center font-bold text-white transition-all hover:bg-slate-800">
                                    Instalar aplicativo
                                </button>
                            ) : null}
                        </div>
                    </motion.aside>
                </>
            )}
        </AnimatePresence>
    );

    return (
        <header className={cn('fixed top-0 z-[9000] w-full transition-all duration-300', headerClass)}>
            <div className="container mx-auto flex items-center justify-between px-4">
                <Link to="/" className="group flex items-center gap-2 text-xl font-extrabold text-white">
                    {brand}
                </Link>

                <nav className="hidden items-center gap-8 md:flex">
                    {links.map((link) => {
                        const isActive = location.pathname === link.path;
                        return (
                            <Link
                                key={link.path}
                                to={link.path}
                                className={cn(
                                    'text-sm font-semibold transition-colors',
                                    isActive ? 'text-indigo-400' : 'text-slate-300 hover:text-white',
                                )}
                            >
                                {link.name}
                            </Link>
                        );
                    })}
                </nav>

                <div className="hidden items-center gap-4 md:flex">
                    <a href="/login" className="text-sm font-bold text-slate-300 transition-colors hover:text-white">Entrar</a>
                    <a href="/cadastro" className="rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/30 transition-all hover:scale-105 hover:bg-indigo-500">
                        Testar Gratis
                    </a>
                </div>

                <button
                    type="button"
                    aria-label={mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'}
                    aria-expanded={mobileMenuOpen}
                    className="relative z-[9001] flex h-10 w-10 items-center justify-center rounded-xl text-white opacity-90 transition hover:bg-white/10 hover:opacity-100 md:hidden"
                    onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                >
                    {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                </button>
            </div>

            {createPortal(mobileDrawer, document.body)}
        </header>
    );
}
