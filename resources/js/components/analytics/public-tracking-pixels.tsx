import { usePage } from '@inertiajs/react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    type ReactNode,
} from 'react';
import type {
    PublicTrackingPixel,
    PublicTrackingPixelsByTrigger,
    TrackingPixelProvider,
} from '@/types/link-pages';

type TrackingTrigger = keyof PublicTrackingPixelsByTrigger;
type MetaPixelFunction = ((...args: unknown[]) => void) & {
    callMethod?: (...args: unknown[]) => void;
    loaded?: boolean;
    push?: MetaPixelFunction;
    queue?: unknown[][];
    version?: string;
};
type PixelRuntime = {
    initializedMeta: Set<string>;
    initializedGoogle: Set<string>;
};
type PublicTrackingContextValue = (trigger: TrackingTrigger) => void;

const PublicTrackingContext = createContext<PublicTrackingContextValue>(
    () => {},
);

const eventNames: Record<TrackingTrigger, { meta: string; google: string }> = {
    page_view: { meta: 'PageView', google: 'page_view' },
    button_click: { meta: 'ButtonClick', google: 'select_content' },
    lead_submit: { meta: 'Lead', google: 'generate_lead' },
};

declare global {
    interface Window {
        fbq?: MetaPixelFunction;
        _fbq?: MetaPixelFunction;
        gtag?: (...args: unknown[]) => void;
        dataLayer?: unknown[][];
        __linkPilotPixelRuntime?: PixelRuntime;
    }
}

function providerIdIsSafe(
    provider: TrackingPixelProvider,
    identifier: string,
): boolean {
    return provider === 'meta_pixel'
        ? /^\d{5,20}$/.test(identifier)
        : /^G-[A-Z0-9]{6,20}$/.test(identifier);
}

function appendScript(id: string, source: string): void {
    if (document.getElementById(id) !== null) return;
    const script = document.createElement('script');
    script.id = id;
    script.async = true;
    script.src = source;
    document.head.appendChild(script);
}

function runtime(): PixelRuntime {
    window.__linkPilotPixelRuntime ??= {
        initializedMeta: new Set(),
        initializedGoogle: new Set(),
    };
    return window.__linkPilotPixelRuntime;
}

function metaPixel(): MetaPixelFunction {
    if (window.fbq) return window.fbq;
    const fbq = ((...args: unknown[]) => {
        if (fbq.callMethod) {
            fbq.callMethod.apply(fbq, args);
        } else {
            fbq.queue?.push(args);
        }
    }) as MetaPixelFunction;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.push = fbq;
    fbq.queue = [];
    window.fbq = window._fbq = fbq;
    return fbq;
}

function googleTag(): (...args: unknown[]) => void {
    window.dataLayer ??= [];
    window.gtag ??= (...args: unknown[]) => window.dataLayer?.push(args);
    return window.gtag;
}

function initializeGoogleAndTrack(identifier: string, eventName: string): void {
    const gtag = googleTag();
    appendScript(
        `linkpilot-google-analytics-${identifier}`,
        `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(identifier)}`,
    );
    if (!runtime().initializedGoogle.has(identifier)) {
        gtag('js', new Date());
        gtag('config', identifier, { send_page_view: false });
        runtime().initializedGoogle.add(identifier);
    }
    gtag('event', eventName, {
        page_path: window.location.pathname,
        send_to: identifier,
    });
}

function uniqueSafePixels(
    pixels: PublicTrackingPixel[],
): PublicTrackingPixel[] {
    const seen = new Set<string>();
    return pixels.filter((pixel) => {
        if (!providerIdIsSafe(pixel.provider, pixel.identifier)) return false;
        const key = `${pixel.provider}:${pixel.identifier}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

function initializeAndTrack(
    pixel: PublicTrackingPixel,
    trigger: TrackingTrigger,
): void {
    const event = eventNames[trigger];
    if (pixel.provider === 'meta_pixel') {
        const fbq = metaPixel();
        appendScript(
            'linkpilot-meta-pixel',
            'https://connect.facebook.net/en_US/fbevents.js',
        );
        if (!runtime().initializedMeta.has(pixel.identifier)) {
            fbq('init', pixel.identifier);
            runtime().initializedMeta.add(pixel.identifier);
        }
        if (trigger === 'button_click') {
            fbq('trackSingleCustom', pixel.identifier, event.meta);
        } else {
            fbq('trackSingle', pixel.identifier, event.meta);
        }
        return;
    }

    initializeGoogleAndTrack(pixel.identifier, event.google);
}

export function usePublicTrackingEvent(): PublicTrackingContextValue {
    return useContext(PublicTrackingContext);
}

export function PublicTrackingPixels({
    pixels,
    consent,
    children,
}: {
    pixels: PublicTrackingPixelsByTrigger;
    consent: 'granted' | 'denied' | null;
    children: ReactNode;
}) {
    const platformGa4 = usePage().props.platform?.analytics?.ga4;
    const safePlatformGa4 =
        typeof platformGa4 === 'string' &&
        /^G-[A-Z0-9]{4,20}$/.test(platformGa4)
            ? platformGa4
            : null;
    const emittedViews = useRef(new Set<string>());
    const safePixels = {
        page_view: uniqueSafePixels(pixels.page_view),
        button_click: uniqueSafePixels(pixels.button_click),
        lead_submit: uniqueSafePixels(pixels.lead_submit),
    };

    const emit = useCallback<PublicTrackingContextValue>(
        (trigger) => {
            if (consent !== 'granted') return;
            for (const pixel of safePixels[trigger]) {
                initializeAndTrack(pixel, trigger);
            }
        },
        [consent, pixels],
    );

    useEffect(() => {
        if (consent !== 'granted') return;
        const pageViewPixels = safePixels.page_view;
        const viewKey = `${window.location.pathname}:${pageViewPixels
            .map((pixel) => `${pixel.provider}:${pixel.identifier}`)
            .sort()
            .join(',')}:${safePlatformGa4 ?? ''}`;
        if (
            (pageViewPixels.length === 0 && safePlatformGa4 === null) ||
            emittedViews.current.has(viewKey)
        ) {
            return;
        }
        emittedViews.current.add(viewKey);
        for (const pixel of pageViewPixels) {
            initializeAndTrack(pixel, 'page_view');
        }
        if (
            safePlatformGa4 !== null &&
            !pageViewPixels.some(
                (pixel) =>
                    pixel.provider === 'google_analytics' &&
                    pixel.identifier === safePlatformGa4,
            )
        ) {
            initializeGoogleAndTrack(safePlatformGa4, 'page_view');
        }
    }, [consent, pixels, safePlatformGa4]);

    return (
        <PublicTrackingContext.Provider value={emit}>
            {children}
        </PublicTrackingContext.Provider>
    );
}
