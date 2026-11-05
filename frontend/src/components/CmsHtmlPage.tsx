import { ScrollReveal } from '@/components/ui/ScrollReveal';
import { NeonButton } from '@/components/ui/NeonButton';

type CmsHtmlPageProps = {
    page?: Record<string, any>;
    fallback: {
        kicker: string;
        title: string;
        description?: string;
    };
};

export default function CmsHtmlPage({ page = {}, fallback }: CmsHtmlPageProps) {
    const hero = page.hero || {};
    const kicker = hero.kicker || fallback.kicker;
    const title = hero.title || fallback.title;
    const description = hero.description || fallback.description || '';
    const primaryLabel = hero.primary_cta_label || '';
    const primaryUrl = hero.primary_cta_url || '/cadastro';
    const secondaryLabel = hero.secondary_cta_label || '';
    const secondaryUrl = hero.secondary_cta_url || '/contato';
    const contentHtml = String(page.html || '').trim();
    const updatedLabel = page.updated_label || '';

    return (
        <div className="bg-slate-950 text-slate-300 min-h-screen pt-32 pb-32 relative overflow-hidden">
            <div className="absolute inset-0 z-0">
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-indigo-600/8 rounded-full blur-[150px]" />
                <div className="absolute bottom-1/4 left-1/4 w-[500px] h-[500px] bg-blue-600/8 rounded-full blur-[180px]" />
                <div className="absolute inset-0 bg-[radial-gradient(#ffffff06_1px,transparent_1px)] [background-size:18px_18px] pointer-events-none" />
            </div>

            <div className="container mx-auto px-4 max-w-5xl relative z-10">
                <ScrollReveal className="text-center mb-16">
                    <span className="inline-block bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-4 py-1.5 rounded-full text-xs font-bold mb-6 uppercase tracking-wider">
                        {kicker}
                    </span>
                    <h1 className="text-4xl md:text-6xl font-extrabold text-white tracking-tight mb-6">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-slate-400 font-medium leading-relaxed text-lg max-w-3xl mx-auto">
                            {description}
                        </p>
                    )}
                    {updatedLabel && (
                        <div className="mt-5 text-sm text-slate-500">{updatedLabel}</div>
                    )}
                    {(primaryLabel || secondaryLabel) && (
                        <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            {primaryLabel && (
                                <NeonButton href={primaryUrl} color="indigo" className="public-cta-button px-8 py-4 font-bold">
                                    {primaryLabel}
                                </NeonButton>
                            )}
                            {secondaryLabel && (
                                <a
                                    href={secondaryUrl}
                                    className="public-cta-button inline-flex min-h-14 items-center justify-center whitespace-nowrap rounded-full border border-white/20 px-8 py-4 font-bold text-white transition-all hover:bg-white/10"
                                >
                                    {secondaryLabel}
                                </a>
                            )}
                        </div>
                    )}
                </ScrollReveal>

                <ScrollReveal>
                    <div className="rounded-[2rem] border border-slate-800 bg-slate-900/50 p-8 shadow-2xl backdrop-blur-xl md:p-12">
                        <article
                            className="prose prose-invert max-w-none prose-headings:text-white prose-p:text-slate-400 prose-li:text-slate-400 prose-strong:text-white prose-a:text-indigo-400"
                            dangerouslySetInnerHTML={{ __html: contentHtml }}
                        />
                    </div>
                </ScrollReveal>
            </div>
        </div>
    );
}
