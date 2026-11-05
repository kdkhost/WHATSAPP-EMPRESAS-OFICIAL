import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { fetchSiteData, SiteData } from '@/api/site';

const SiteContext = createContext<SiteData | null>(null);

export function SiteProvider({ children }: { children: ReactNode }) {
    const [data, setData] = useState<SiteData | null>(null);

    useEffect(() => {
        fetchSiteData()
            .then(setData)
            .catch(() => setData({
                app: { name: 'Cobranca WhatsApp' },
                slides: [],
                testimonials: [],
                plans: [],
                theme: { primary: '#0d6efd', secondary: '#6610f2' },
            }));
    }, []);

    useEffect(() => {
        if (!data?.app) {
            return;
        }

        if (data.app.name) {
            document.title = data.app.name;
        }

        if (data.app.favicon) {
            updateHeadLink('icon', data.app.favicon);
            updateHeadLink('shortcut icon', data.app.favicon);
            updateHeadLink('apple-touch-icon', data.app.favicon);
        }
    }, [data]);

    return <SiteContext.Provider value={data}>{children}</SiteContext.Provider>;
}

function updateHeadLink(rel: string, href: string) {
    let link = document.head.querySelector(`link[rel="${rel}"]`) as HTMLLinkElement | null;

    if (!link) {
        link = document.createElement('link');
        link.rel = rel;
        document.head.appendChild(link);
    }

    link.href = href;
}

export function useSite() {
    return useContext(SiteContext);
}
