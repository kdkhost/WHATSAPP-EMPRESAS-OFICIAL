import { useLocation } from 'react-router-dom';
import { CreditCard, Home, MessageCircle, QrCode, User } from 'lucide-react';

export default function PwaFooter() {
    const location = useLocation();

    return (
        <nav
            className="pwa-footer-nav fixed bottom-0 left-0 right-0 z-[1040] border-t border-slate-200 bg-white shadow-lg md:hidden"
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <ul className="flex justify-around text-[11px]">
                <FooterItem to="/" label="Inicio" icon={<Home className="h-4 w-4" />} active={location.pathname === '/'} />
                <FooterItem to="/painel/historico/whatsapp" label="Mensagens" icon={<MessageCircle className="h-4 w-4" />} active={location.pathname.startsWith('/painel/historico/whatsapp')} />
                <FooterItem to="/painel/cobranca" label="Cobrancas" icon={<CreditCard className="h-4 w-4" />} active={location.pathname.startsWith('/painel/cobranca')} />
                <FooterItem to="/painel/instancias" label="QR/Instancia" icon={<QrCode className="h-4 w-4" />} active={location.pathname.startsWith('/painel/instancias')} />
                <FooterItem to="/painel/perfil" label="Perfil" icon={<User className="h-4 w-4" />} active={location.pathname.startsWith('/painel/perfil')} />
            </ul>
        </nav>
    );
}

function FooterItem({ to, icon, label, active }: { to: string; icon: React.ReactNode; label: string; active: boolean }) {
    return (
        <li>
            <a href={to} className={`flex min-w-14 flex-col items-center gap-1 px-2 py-2 ${active ? 'text-blue-600' : 'text-slate-600 hover:text-blue-600'}`}>
                <span>{icon}</span>
                <span>{label}</span>
            </a>
        </li>
    );
}
