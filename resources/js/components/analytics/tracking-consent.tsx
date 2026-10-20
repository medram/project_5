import { router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type TrackingConsentProps = {
    consent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
};

export function TrackingConsent({ consent, privacyUrl }: TrackingConsentProps) {
    const [processing, setProcessing] = useState(false);

    if (consent !== null) {
        return null;
    }

    function choose(choice: 'granted' | 'denied') {
        router.post(
            '/tracking-consent',
            {
                consent: choice,
                return_path: `${window.location.pathname}${window.location.search}`,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <aside
            aria-label="Privacy choices"
            className="mx-auto mt-6 flex w-full max-w-xl flex-col gap-3 rounded-xl border bg-background/95 p-4 text-left shadow-sm"
        >
            <div className="flex gap-3">
                <ShieldCheck
                    className="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div className="space-y-1">
                    <p className="text-sm font-medium">Privacy choices</p>
                    <p className="text-sm leading-5 text-muted-foreground">
                        Allow privacy-minimal visit analytics for this public
                        page. Your choice is kept for 180 days.
                        {privacyUrl && (
                            <>
                                {' '}
                                <a
                                    href={privacyUrl}
                                    className="underline underline-offset-4"
                                >
                                    Read the privacy notice.
                                </a>
                            </>
                        )}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-2 pl-8">
                <Button
                    type="button"
                    size="sm"
                    onClick={() => choose('granted')}
                    disabled={processing}
                >
                    {processing && <Spinner data-icon="inline-start" />}
                    Allow analytics
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => choose('denied')}
                    disabled={processing}
                >
                    Decline
                </Button>
            </div>
        </aside>
    );
}
