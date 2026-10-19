import { Head } from '@inertiajs/react';
import { Download, Link2, MousePointerClick, QrCode } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { qrCode } from '@/routes/links';
import { index } from '@/routes/qr-codes';
import type { QrCodeIndexProps, QrCodeLink } from '@/types/qr-codes';

function QrCodeCard({ link }: { link: QrCodeLink }) {
    const svgUrl = qrCode(link.id).url;

    return (
        <Card className="overflow-hidden">
            <CardHeader>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <CardTitle>{link.title || 'Untitled route'}</CardTitle>
                        <CardDescription className="truncate">
                            /{link.slug}
                        </CardDescription>
                    </div>
                    <div className="flex items-center gap-2 text-sm font-medium tabular-nums text-muted-foreground">
                        <MousePointerClick aria-hidden="true" />
                        {link.clicks_count}{' '}
                        {link.clicks_count === 1 ? 'click' : 'clicks'}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="grid gap-6 sm:grid-cols-[10rem_minmax(0,1fr)] sm:items-center">
                <div className="flex aspect-square w-full max-w-40 items-center justify-center rounded-lg border bg-white p-3 shadow-sm">
                    <img
                        src={svgUrl}
                        alt={`QR code for /${link.slug}`}
                        className="size-full"
                    />
                </div>
                <div className="flex min-w-0 flex-col gap-4">
                    <p className="truncate text-sm text-muted-foreground">
                        {link.destination_url}
                    </p>
                    <Button variant="outline" className="w-fit" asChild>
                        <a href={svgUrl} download={`${link.slug}.svg`}>
                            <Download data-icon="inline-start" />
                            Download SVG
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function QrCodes({ links }: QrCodeIndexProps) {
    return (
        <>
            <Head title="QR codes" />

            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="flex max-w-2xl flex-col gap-2">
                    <div className="flex items-center gap-2 text-linkpilot-creator">
                        <QrCode aria-hidden="true" />
                        <span className="text-sm font-medium">
                            Shareable routes
                        </span>
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        QR codes
                    </h1>
                    <p className="text-sm leading-6 text-muted-foreground">
                        Download a locally generated SVG for every short link
                        you own.
                    </p>
                </section>

                {links.length === 0 ? (
                    <Empty className="bg-muted/30">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Link2 aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>No QR codes yet</EmptyTitle>
                            <EmptyDescription>
                                Create a short link first. Its QR code will be
                                ready here immediately.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <section
                        aria-label="QR codes"
                        className="grid gap-4 xl:grid-cols-2"
                    >
                        {links.map((link) => (
                            <QrCodeCard key={link.id} link={link} />
                        ))}
                    </section>
                )}
            </main>
        </>
    );
}

QrCodes.layout = {
    breadcrumbs: [
        {
            title: 'QR codes',
            href: index(),
        },
    ],
};
