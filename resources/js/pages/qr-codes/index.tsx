import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Download,
    ExternalLink,
    Link2,
    Pencil,
    Plus,
    QrCode,
    ScanLine,
    Trash2,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { toast } from 'sonner';
import { StatCard } from '@/components/dashboard/stat-card';
import { PageHeader } from '@/components/page-header';
import { QrCodeFormDialog } from '@/components/qr-codes/qr-code-form-dialog';
import {
    qrCampaignBadgeVariant,
    qrCampaignState,
} from '@/components/qr-codes/qr-code-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    campaignInsights,
    destroy,
    download,
    exportMethod,
    index,
    insights,
    preview,
    publicMethod,
} from '@/routes/qr-codes';
import type { QrCodeCampaign, QrCodeIndexProps } from '@/types/qr-codes';

function DeleteQrCampaignDialog({
    campaign,
    children,
}: {
    campaign: QrCodeCampaign;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    function remove() {
        router.delete(destroy(campaign.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this QR campaign?</DialogTitle>
                    <DialogDescription>
                        This permanently removes the QR code and its scan
                        history. The linked short link, if any, remains
                        unchanged.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={remove}>
                        Delete campaign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function QrCampaignCard({
    campaign,
    availableLinks,
}: {
    campaign: QrCodeCampaign;
    availableLinks: QrCodeIndexProps['availableLinks'];
}) {
    const state = qrCampaignState(campaign);
    const target = campaign.link
        ? `/${campaign.link.slug}`
        : campaign.destination_url || 'Unavailable target';
    const publicUrl = publicMethod(campaign.code).url;
    const formats = [
        ['svg', 'SVG'],
        ['png', 'PNG'],
        ['jpg', 'JPEG'],
        ['webp', 'WebP'],
    ] as const;

    async function copyQrUrl() {
        try {
            await navigator.clipboard.writeText(
                new URL(publicUrl, window.location.origin).toString(),
            );
            toast.success('QR URL copied');
        } catch {
            toast.error('Could not copy the QR URL');
        }
    }

    return (
        <Card id={`qr-campaign-${campaign.id}`} className="overflow-hidden">
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <CardTitle className="[overflow-wrap:anywhere] break-words">
                        {campaign.title ||
                            campaign.link?.title ||
                            'Untitled QR campaign'}
                    </CardTitle>
                    <CardDescription className="break-all">
                        /q/{campaign.code}
                    </CardDescription>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant={qrCampaignBadgeVariant(state)}>
                        {state}
                    </Badge>
                    <Badge variant="outline">
                        {campaign.source === 'linked'
                            ? 'Linked short link'
                            : 'Campaign'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-5 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <div className="flex aspect-square w-full max-w-40 items-center justify-center rounded-lg border bg-white p-2">
                    <img
                        src={preview(campaign.id).url}
                        alt={[
                            `QR code for ${campaign.title || campaign.link?.slug || 'campaign'}`,
                            campaign.has_logo && campaign.logo_alt
                                ? `Includes logo: ${campaign.logo_alt}`
                                : null,
                        ]
                            .filter(Boolean)
                            .join('. ')}
                        className="size-full"
                    />
                </div>
                <div className="min-w-0 space-y-4">
                    <div className="space-y-1 text-sm">
                        <p className="font-medium">Target</p>
                        <p className="break-all text-muted-foreground">
                            {target}
                        </p>
                    </div>
                    {campaign.share_title && (
                        <div className="space-y-1 text-sm">
                            <p className="font-medium">Sharing title</p>
                            <p className="break-words text-muted-foreground">
                                {campaign.share_title}
                            </p>
                        </div>
                    )}
                    {campaign.share_description && (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {campaign.share_description}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5">
                            <Activity aria-hidden="true" className="size-4" />
                            {campaign.scan_count}{' '}
                            {campaign.scan_count === 1 ? 'scan' : 'scans'}
                        </span>
                        {campaign.max_scans !== null && (
                            <span>Limit {campaign.max_scans}</span>
                        )}
                        {campaign.has_access_code && (
                            <span>Access code protected</span>
                        )}
                        {campaign.has_logo && <span>Logo included</span>}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {formats.map(([format, label]) => (
                            <Button
                                key={format}
                                variant="outline"
                                size="sm"
                                asChild
                            >
                                <a
                                    href={
                                        download({
                                            qrCode: campaign.id,
                                            format,
                                        }).url
                                    }
                                    download={`qr-${campaign.code}.${format}`}
                                >
                                    <Download data-icon="inline-start" />
                                    {label}
                                </a>
                            </Button>
                        ))}
                    </div>
                    <div className="flex flex-wrap gap-2 border-t pt-3">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={campaignInsights(campaign.id).url}>
                                <BarChart3 data-icon="inline-start" />
                                Insights
                            </Link>
                        </Button>
                        <Button variant="ghost" size="sm" onClick={copyQrUrl}>
                            <Link2 data-icon="inline-start" />
                            Copy QR URL
                        </Button>
                        <Button variant="ghost" size="sm" asChild>
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink data-icon="inline-start" />
                                Open
                            </a>
                        </Button>
                        <QrCodeFormDialog
                            campaign={campaign}
                            availableLinks={availableLinks}
                        >
                            <Button variant="ghost" size="sm">
                                <Pencil data-icon="inline-start" />
                                Edit
                            </Button>
                        </QrCodeFormDialog>
                        {campaign.source === 'campaign' && (
                            <DeleteQrCampaignDialog campaign={campaign}>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 data-icon="inline-start" />
                                    Delete
                                </Button>
                            </DeleteQrCampaignDialog>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function QrCodes({
    campaigns,
    availableLinks,
}: QrCodeIndexProps) {
    const totalScans = campaigns.reduce(
        (sum, campaign) => sum + campaign.scan_count,
        0,
    );
    const directCampaigns = campaigns.filter(
        (campaign) =>
            campaign.source === 'campaign' && campaign.destination_url !== null,
    ).length;

    return (
        <>
            <Head title="QR campaigns" />
            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="QR campaigns"
                    description="Manage short-link QR codes and direct-destination campaigns. QR scans stay separate from short-link clicks."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={exportMethod().url}>
                                    <Download data-icon="inline-start" />
                                    Export CSV
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={insights().url}>
                                    <BarChart3 data-icon="inline-start" />
                                    Scan insights
                                </Link>
                            </Button>
                            <QrCodeFormDialog availableLinks={availableLinks}>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create QR code
                                </Button>
                            </QrCodeFormDialog>
                        </>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <StatCard
                        icon={QrCode}
                        label="QR codes"
                        value={campaigns.length.toLocaleString()}
                    />
                    <StatCard
                        icon={ScanLine}
                        label="Total recorded scans"
                        value={totalScans.toLocaleString()}
                    />
                    <StatCard
                        icon={Link2}
                        label="Direct campaigns"
                        value={directCampaigns.toLocaleString()}
                        className="col-span-2 sm:col-span-1"
                    />
                </div>

                {campaigns.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Link2 aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>No QR campaigns yet</EmptyTitle>
                            <EmptyDescription>
                                Create a QR campaign for a direct URL, or create
                                a short link to get a linked QR code
                                automatically.
                            </EmptyDescription>
                        </EmptyHeader>
                        <QrCodeFormDialog availableLinks={availableLinks}>
                            <Button>
                                <Plus data-icon="inline-start" />
                                Create QR code
                            </Button>
                        </QrCodeFormDialog>
                    </Empty>
                ) : (
                    <section
                        aria-label="QR campaigns"
                        className="grid gap-4 xl:grid-cols-2"
                    >
                        {campaigns.map((campaign) => (
                            <QrCampaignCard
                                key={campaign.id}
                                campaign={campaign}
                                availableLinks={availableLinks}
                            />
                        ))}
                    </section>
                )}
            </section>
        </>
    );
}

QrCodes.layout = {
    breadcrumbs: [
        {
            title: 'QR campaigns',
            href: index(),
        },
    ],
};
