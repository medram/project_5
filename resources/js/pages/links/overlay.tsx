import { Head, router } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { PublicTrackingPixels } from '@/components/analytics/public-tracking-pixels';
import { TrackingConsent } from '@/components/analytics/tracking-consent';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { OverlayPublicProps } from '@/types/overlays';

export default function PublicLinkOverlay({
    slug,
    overlay,
    pixels,
    trackingConsent,
    privacyUrl,
}: OverlayPublicProps) {
    const [processing, setProcessing] = useState(false);

    function continueToDestination() {
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
                            <p className="text-base leading-7 text-muted-foreground break-words">
                                {overlay.body}
                            </p>
                        )}
                    </header>

                    <Button
                        size="lg"
                        onClick={continueToDestination}
                        disabled={processing}
                        className="w-full sm:w-auto"
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        {overlay.ctaLabel}
                        <ArrowRight data-icon="inline-end" />
                    </Button>

                    <TrackingConsent
                        consent={trackingConsent}
                        privacyUrl={privacyUrl}
                    />
                </div>

                <PublicTrackingPixels pixels={pixels} />
            </main>
        </>
    );
}
