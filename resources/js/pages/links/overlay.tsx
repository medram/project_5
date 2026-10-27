import { usePublicTrackingEvent } from '@/components/analytics/public-tracking-pixels';
import { Head, router } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PublicTrackingPixels } from '@/components/analytics/public-tracking-pixels';
import { TrackingConsent } from '@/components/analytics/tracking-consent';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { OverlayPublicProps } from '@/types/overlays';

function PublicLinkOverlayContent({
    slug,
    overlay,
    trackingConsent,
    privacyUrl,
    canGrantConsent,
    remainingDelaySeconds,
}: OverlayPublicProps) {
    const trackEvent = usePublicTrackingEvent();
    const [processing, setProcessing] = useState(false);
    const [secondsRemaining, setSecondsRemaining] = useState(
        remainingDelaySeconds,
    );

    useEffect(() => {
        setSecondsRemaining(remainingDelaySeconds);
    }, [remainingDelaySeconds]);

    useEffect(() => {
        if (secondsRemaining <= 0) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setSecondsRemaining((current) => Math.max(0, current - 1));
        }, 1000);

        return () => window.clearTimeout(timeout);
    }, [secondsRemaining]);

    function continueToDestination() {
        trackEvent('button_click');
        router.post(
            `/${slug}/continue`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title={overlay.heading} />
            <main className="link-page-shell">
                <div className="link-page-frame items-center text-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <Sparkles
                            className="size-6 text-primary"
                            aria-hidden="true"
                        />
                    </div>

                    <header className="flex flex-col gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight break-words">
                            {overlay.heading}
                        </h1>
                        {overlay.body && (
                            <p className="text-base leading-7 break-words text-muted-foreground">
                                {overlay.body}
                            </p>
                        )}
                        {secondsRemaining > 0 && (
                            <p role="status" aria-live="polite">
                                Continue available in {secondsRemaining}{' '}
                                {secondsRemaining === 1 ? 'second' : 'seconds'}.
                            </p>
                        )}
                    </header>

                    <Button
                        size="lg"
                        onClick={continueToDestination}
                        disabled={processing || secondsRemaining > 0}
                        className="w-full sm:w-auto"
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        {overlay.ctaLabel}
                        <ArrowRight data-icon="inline-end" />
                    </Button>

                    <TrackingConsent
                        consent={trackingConsent}
                        privacyUrl={privacyUrl}
                        canGrantConsent={canGrantConsent}
                    />
                </div>
            </main>
        </>
    );
}

export default function PublicLinkOverlay(props: OverlayPublicProps) {
    return (
        <PublicTrackingPixels
            pixels={props.pixels}
            consent={props.trackingConsent}
        >
            <PublicLinkOverlayContent {...props} />
        </PublicTrackingPixels>
    );
}
