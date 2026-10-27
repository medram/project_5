import { Head, router } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type PublicLinkSplashProps = {
    slug: string;
    title: string | null;
};

export default function PublicLinkSplash({
    slug,
    title,
}: PublicLinkSplashProps) {
    const [processing, setProcessing] = useState(false);
    const heading = title || 'Continue to your destination';

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
                        disabled={processing}
                        className="w-full sm:w-auto"
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Continue
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                </div>
            </main>
        </>
    );
}
