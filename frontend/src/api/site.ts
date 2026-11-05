import axios from 'axios';

export const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: { Accept: 'application/json' },
});

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description?: string;
    price: number | string;
    billing_cycle: 'monthly' | 'quarterly' | 'semiannual' | 'annual';
    trial_days: number;
    max_instances: number;
    max_customers: number;
    max_invoices_month: number;
    max_messages_month: number;
    custom_gateway: boolean;
    custom_templates: boolean;
    installment_enabled: boolean;
    fee_passthrough: boolean;
    features: string[];
    is_demo: boolean;
    highlight: boolean;
    badge?: string | null;
    sort: number;
}

export interface Testimonial {
    id: number;
    name: string;
    role?: string;
    avatar?: string;
    rating: number;
    content: string;
}

export interface Slide {
    id: number;
    title?: string;
    subtitle?: string;
    image: string;
    cta_label?: string;
    cta_url?: string;
}

export interface SiteData {
    app: { name: string; description?: string; logo?: string; favicon?: string; whatsapp?: string; email?: string };
    slides: Slide[];
    testimonials: Testimonial[];
    plans: Plan[];
    theme: { primary: string; secondary: string };
    pages?: Record<string, any>;
    pages_status?: Record<string, boolean>;
}

export function isPageActive(site: SiteData | null | undefined, slug: string): boolean {
    if (!site?.pages_status) return true;
    return site.pages_status[slug] !== false;
}

export async function fetchSiteData(): Promise<SiteData> {
    const r = await api.get<SiteData>('/api/site');
    return r.data;
}

export async function lookupCep(cep: string) {
    const digits = cep.replace(/\D/g, '');
    const r = await api.get(`/api/v1/cep/${digits}`);
    return r.data;
}
