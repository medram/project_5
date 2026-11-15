import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type AdSlot = 'header' | 'content';

export default function PublicAdSlot({
    slot,
    consent,
}: {
    slot: AdSlot;
    consent: 'granted' | 'denied' | null;
}) {
    const ads = usePage().props.platform?.ads;
    const element = useRef<HTMLModElement | null>(null);
    const slotId = slot === 'header' ? ads?.headerSlot : ads?.contentSlot;
    const allowed =
        ads?.provider === 'adsense' &&
        typeof ads.publisherId === 'string' &&
        ads.publisherId.startsWith('ca-pub-') &&
        typeof slotId === 'string' &&
        slotId !== '' &&
        (!ads.consentRequired || consent === 'granted');

    useEffect(() => {
        if (!allowed || element.current === null) {
            return;
        }

        const source =
            'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' +
            encodeURIComponent(ads?.publisherId ?? '');
        let script = document.querySelector<HTMLScriptElement>(
            'script[data-linkpilot-ads]',
        );

        if (!script) {
            script = document.createElement('script');
            script.async = true;
            script.src = source;
            script.dataset.linkpilotAds = 'true';
            script.crossOrigin = 'anonymous';
            document.head.appendChild(script);
        }

        const timer = window.setTimeout(() => {
            const queue = window.adsbygoogle;
            if (Array.isArray(queue)) {
                queue.push({});
            }
        }, 0);

        return () => window.clearTimeout(timer);
    }, [allowed, ads?.publisherId]);

    if (!allowed || !slotId) {
        return null;
    }

    return (
        <ins
            ref={element}
            className="adsbygoogle block min-h-24 w-full"
            data-ad-client={ads.publisherId}
            data-ad-slot={slotId}
            data-ad-format="auto"
            data-full-width-responsive="true"
            aria-label="Sponsored content"
        />
    );
}
