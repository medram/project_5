import { Head } from '@inertiajs/react';
import { PublicTrackingPixels } from '@/components/analytics/public-tracking-pixels';
import { TrackingConsent } from '@/components/analytics/tracking-consent';
import MaintenanceBanner from '@/components/platform/maintenance-banner';
import PublicAdSlot from '@/components/platform/public-ad-slot';
import { LinkPagePresentation } from '@/components/link-pages/presentation';
import type { LinkPagePublicProps } from '@/types/link-pages';

export default function PublicLinkPage({
    title,
    description,
    template,
    items,
    pixels,
    trackingConsent,
    privacyUrl,
    canGrantConsent,
}: LinkPagePublicProps) {
    return (
        <>
            <Head title={title} />

            <PublicTrackingPixels pixels={pixels} consent={trackingConsent}>
                <main className="link-page-shell">
                    <MaintenanceBanner />
                    <div className="link-page-frame">
                        <LinkPagePresentation
                            template={template}
                            title={title}
                            description={description}
                            items={items}
                            privacyUrl={privacyUrl}
                        />
                        <div className="mt-6">
                            <PublicAdSlot
                                slot="content"
                                consent={trackingConsent}
                            />
                        </div>

                        <TrackingConsent
                            consent={trackingConsent}
                            privacyUrl={privacyUrl}
                            canGrantConsent={canGrantConsent}
                        />
                    </div>
                </main>
            </PublicTrackingPixels>
        </>
    );
}
