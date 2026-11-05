import { useEffect, useState } from 'react';

/**
 * Detecta se o app esta rodando como PWA instalado (vs. browser comum).
 * Usado para mostrar o menu de footer apenas no PWA instalado.
 */
export function usePwaInstalled(): boolean {
    const [installed, setInstalled] = useState(false);

    useEffect(() => {
        const check = () => {
            const standalone = window.matchMedia('(display-mode: standalone)').matches;
            // iOS Safari
            const iosStandalone = (navigator as unknown as { standalone?: boolean }).standalone === true;
            setInstalled(standalone || iosStandalone);
        };
        check();
        const mq = window.matchMedia('(display-mode: standalone)');
        mq.addEventListener?.('change', check);
        return () => mq.removeEventListener?.('change', check);
    }, []);

    return installed;
}
