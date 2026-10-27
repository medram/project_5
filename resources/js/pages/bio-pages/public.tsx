import { Head } from '@inertiajs/react';
import { PublicTrackingPixels } from '@/components/analytics/public-tracking-pixels';
import { TrackingConsent } from '@/components/analytics/tracking-consent';
import { BioPagePresentation } from '@/components/bio-pages/live-preview';
import type { BioPagePublicProps } from '@/types/bio-pages';

export default function PublicBioPage({
    presentation,
    pixels,
    trackingConsent,
    privacyUrl,
    canGrantConsent,
}: BioPagePublicProps) {
    return (
        <>
            <Head
                title={`${presentation.displayName} (@${presentation.handle})`}
            />
            <PublicTrackingPixels pixels={pixels} consent={trackingConsent}>
                <main className="bio-page-shell">
                    <div className="bio-page-frame">
                        <BioPagePresentation presentation={presentation} />
                    </div>
                    <TrackingConsent
                        consent={trackingConsent}
                        privacyUrl={privacyUrl}
                        canGrantConsent={canGrantConsent}
                    />
                </main>
            </PublicTrackingPixels>
        </>
    );
}
