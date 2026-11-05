import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { useSite } from '@/context/SiteContext';
import CmsHtmlPage from '@/components/CmsHtmlPage';

export default function Cookies() {
    const site = useSite();
    const page = site?.pages?.cookies || {};

    if (page.html) {
        return (
            <CmsHtmlPage
                page={page}
                fallback={{
                    kicker: 'Politica de Cookies',
                    title: 'Uso de Cookies',
                    description: 'Explicamos de forma transparente como nossa plataforma utiliza cookies e tecnologias semelhantes para fornecer, proteger e aprimorar nossos servicos.',
                }}
            />
        );
    }

    return (
        <div className="bg-slate-950 text-slate-300 min-h-screen pt-32 pb-32 relative overflow-hidden">
            {/* Background blobs */}
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-blue-600/5 rounded-full blur-[150px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff04_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none" />
            </div>

            <div className="container mx-auto px-4 max-w-4xl relative z-10">
                <ScrollReveal className="mb-16">
                    <span className="inline-block bg-blue-500/10 text-blue-400 border border-blue-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                        Política de Cookies
                    </span>
                    <h1 className="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-6">
                        Uso de Cookies
                    </h1>
                    <p className="text-slate-400 font-medium leading-relaxed text-lg">
                        Explicamos de forma transparente como nossa plataforma utiliza cookies e tecnologias semelhantes para fornecer, proteger e aprimorar nossos serviços.
                    </p>
                </ScrollReveal>

                <ScrollReveal className="space-y-12 prose prose-invert max-w-none">
                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">O que são cookies?</h2>
                        <p className="leading-relaxed font-medium text-slate-400">
                            Cookies são pequenos arquivos de texto armazenados no seu navegador ou dispositivo quando você visita um site. Eles ajudam a memorizar informações sobre a sua visita, como idioma preferido, preferências de login e outras configurações úteis para tornar sua próxima experiência mais rápida e integrada.
                        </p>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">Como utilizamos cookies?</h2>
                        <p className="leading-relaxed font-medium text-slate-400 mb-4">
                            Utilizamos cookies em diferentes categorias no nosso sistema:
                        </p>
                        <ul className="list-disc pl-6 space-y-3 text-slate-400 font-medium">
                            <li><strong>Estritamente Necessários:</strong> Essenciais para a segurança, controle de sessões ativas e proteção de formulários contra ataques Cross-Site Request Forgery (CSRF). Se desativados, partes essenciais do painel deixarão de funcionar.</li>
                            <li><strong>Funcionais:</strong> Lembram-se de escolhas que você faz (como tema de exibição escuro/claro, idioma ativo) para proporcionar uma experiência personalizada.</li>
                            <li><strong>Analíticos:</strong> Ajudam-nos a coletar estatísticas agregadas e anônimas sobre o tráfego do site para que possamos medir e otimizar o desempenho.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="text-2xl font-bold text-white mb-4">Como controlar as preferências?</h2>
                        <p className="leading-relaxed font-medium text-slate-400">
                            Ao acessar a plataforma pela primeira vez, nosso banner de conformidade com a LGPD solicitará seu consentimento. Você pode gerenciar e excluir cookies diretamente nas configurações do seu navegador. Lembre-se de que limitar o uso de cookies estritamente necessários pode prejudicar a sua capacidade de navegar e fazer login no painel administrativo.
                        </p>
                    </section>
                </ScrollReveal>
            </div>
        </div>
    );
}
