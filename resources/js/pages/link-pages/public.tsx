import { Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { PublicTrackingPixels } from '@/components/analytics/public-tracking-pixels';
import { TrackingConsent } from '@/components/analytics/tracking-consent';
import type { LinkPagePublicProps } from '@/types/link-pages';

export default function PublicLinkPage({
    title,
    description,
    items,
    pixels,
    trackingConsent,
    privacyUrl,
}: LinkPagePublicProps) {
    return (
        <>
            <Head title={title} />

            <main className="link-page-shell">
                <div className="link-page-frame">
                    <header className="flex flex-col gap-2">
                        <h1 className="text-3xl font-semibold tracking-tight break-words">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-base leading-7 text-muted-foreground break-words">
                                {description}
                            </p>
                        )}
                    </header>

                    <nav aria-label="Campaign destinations" className="flex flex-col gap-3">
                        {items.map((item) => (
                            <a
                                key={item.id}
                                href={item.url}
                                className="group flex items-center justify-between gap-3 rounded-xl border bg-background px-4 py-3 text-base font-medium shadow-sm transition-colors hover:border-primary hover:bg-accent"
                            >
                                <span>{item.title}</span>
                                <ExternalLink
                                    className="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary"
                                    aria-hidden="true"
                                />
                            </a>
                        ))}
                    </nav>

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
