import { Head } from '@inertiajs/react';
import PublicAdSlot from '@/components/platform/public-ad-slot';
import MaintenanceBanner from '@/components/platform/maintenance-banner';
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
    branding,
}: BioPagePublicProps) {
    return (
        <>
            <Head
                title={`${presentation.displayName} (@${presentation.handle})`}
            />
            <PublicTrackingPixels pixels={pixels} consent={trackingConsent}>
                <main className="bio-page-shell">
                    <MaintenanceBanner />
                    <div className="bio-page-frame">
                        <BioPagePresentation presentation={presentation} />
                        <div className="mt-6">
                            <PublicAdSlot
                                slot="content"
                                consent={trackingConsent}
                            />
                        </div>
                        {branding ? (
                            <p className="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                                {branding.logo ? (
                                    <img
                                        src={branding.logo}
                                        alt=""
                                        className="size-4 object-contain"
                                    />
                                ) : null}
                                Made with {branding.label}
                            </p>
                        ) : null}
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
