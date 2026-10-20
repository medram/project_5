import { useEffect, useRef } from 'react';
import type {
    PublicTrackingPixel,
    TrackingPixelProvider,
} from '@/types/link-pages';

type MetaPixelFunction = ((...args: unknown[]) => void) & {
    loaded?: boolean;
    queue?: unknown[][];
    version?: string;
};

type PixelRuntime = {
    initializedMeta: Set<string>;
    initializedGoogle: Set<string>;
};

declare global {
    interface Window {
        __linkpilotPixelRuntime?: PixelRuntime;
        dataLayer?: unknown[];
        fbq?: MetaPixelFunction;
        gtag?: (...args: unknown[]) => void;
        _fbq?: MetaPixelFunction;
    }
}

function providerIdIsSafe(provider: TrackingPixelProvider, identifier: string): boolean {
    return provider === 'meta_pixel'
        ? /^\d{5,20}$/.test(identifier)
        : /^G-[A-Z0-9]{6,20}$/.test(identifier);
}

function appendScript(id: string, source: string): void {
    if (document.getElementById(id) !== null) {
        return;
    }

    const script = document.createElement('script');
    script.id = id;
    script.async = true;
    script.src = source;
    document.head.appendChild(script);
}

function runtime(): PixelRuntime {
    window.__linkpilotPixelRuntime ??= {
        initializedMeta: new Set<string>(),
        initializedGoogle: new Set<string>(),
    };

    return window.__linkpilotPixelRuntime;
}

function metaPixel(): MetaPixelFunction {
    if (window.fbq) {
        return window.fbq;
    }

    const fbq = ((...args: unknown[]) => {
        fbq.queue?.push(args);
    }) as MetaPixelFunction;

    fbq.queue = [];
    fbq.loaded = true;
    fbq.version = '2.0';
    window.fbq = fbq;
    window._fbq = fbq;

    return fbq;
}

function googleTag(): (...args: unknown[]) => void {
    window.dataLayer ??= [];
    window.gtag ??= (...args: unknown[]) => {
        window.dataLayer?.push(args);
    };

    return window.gtag;
}

export function PublicTrackingPixels({
    pixels,
}: {
    pixels: PublicTrackingPixel[];
}) {
    const emittedViews = useRef(new Set<string>());

    useEffect(() => {
        const safePixels = pixels.filter((pixel) =>
            providerIdIsSafe(pixel.provider, pixel.identifier),
        );
        const viewKey = `${window.location.pathname}:${safePixels
            .map((pixel) => `${pixel.provider}:${pixel.identifier}`)
            .sort()
            .join(',')}`;

        if (safePixels.length === 0 || emittedViews.current.has(viewKey)) {
            return;
        }

        emittedViews.current.add(viewKey);

        for (const pixel of safePixels) {
            if (pixel.provider === 'meta_pixel') {
                appendScript(
                    'linkpilot-meta-pixel',
                    'https://connect.facebook.net/en_US/fbevents.js',
                );

                const fbq = metaPixel();
                if (!runtime().initializedMeta.has(pixel.identifier)) {
                    fbq('init', pixel.identifier);
                    runtime().initializedMeta.add(pixel.identifier);
                }

                fbq('track', 'PageView');
                continue;
            }

            appendScript(
                `linkpilot-google-analytics-${pixel.identifier}`,
                `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(pixel.identifier)}`,
            );

            const gtag = googleTag();
            if (!runtime().initializedGoogle.has(pixel.identifier)) {
                gtag('js', new Date());
                runtime().initializedGoogle.add(pixel.identifier);
            }

            gtag('config', pixel.identifier, {
                page_path: window.location.pathname,
            });
        }
    }, [pixels]);

    return null;
}
