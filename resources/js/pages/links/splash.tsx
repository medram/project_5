import { Head, router } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type PublicLinkSplashProps = {
    slug: string;
    title: string | null;
    seconds: number;
};

export default function PublicLinkSplash({
    slug,
    title,
    seconds,
}: PublicLinkSplashProps) {
    const [processing, setProcessing] = useState(false);
    const [remaining, setRemaining] = useState(Math.max(0, seconds));
    const heading = title || 'Continue to your destination';

    useEffect(() => {
        if (remaining <= 0) {
            return;
        }

        const timer = window.setTimeout(
            () => setRemaining((current) => Math.max(0, current - 1)),
            1000,
        );

        return () => window.clearTimeout(timer);
    }, [remaining]);

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
            <Head title={heading} />
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
                            {heading}
                        </h1>
                        <p className="text-base leading-7 text-muted-foreground">
                            Continue when you are ready to open the destination.
                        </p>
                    </header>
                    <Button
                        size="lg"
                        onClick={continueToDestination}
                        disabled={processing || remaining > 0}
                        className="w-full sm:w-auto"
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        {remaining > 0 ? `Continue in ${remaining}s` : 'Continue'}
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                    <p className="text-sm text-muted-foreground" role="status" aria-live="polite">
                        {remaining > 0
                            ? `The continue button unlocks in ${remaining} seconds.`
                            : 'You can continue to the destination now.'}
                    </p>
                </div>
            </main>
        </>
    );
}
