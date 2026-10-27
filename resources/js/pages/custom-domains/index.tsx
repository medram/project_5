import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Globe2,
    Plus,
    RefreshCw,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type {
    CustomDomain,
    CustomDomainsPageProps,
} from '@/types/custom-domains';

function statusLabel(status: CustomDomain['status']): string {
    return {
        pending_dns: 'Waiting for DNS',
        pending_tls: 'Waiting for HTTPS',
        active: 'Active',
        failed: 'Verification failed',
    }[status];
}

function statusVariant(status: CustomDomain['status']) {
    if (status === 'active') {
        return 'default';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return 'secondary';
}

function DeleteDomainDialog({ domain }: { domain: CustomDomain }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ confirmation: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.delete(`/dashboard/custom-domains/${domain.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <Trash2 data-icon="inline-start" />
                    Remove
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove {domain.hostname}?</DialogTitle>
                    <DialogDescription>
                        This disconnects it from every short link. Those links
                        will use the default LinkPilot host again.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.confirmation)}>
                            <FieldLabel
                                htmlFor={`domain-confirmation-${domain.id}`}
                            >
                                Type {domain.hostname} to confirm
                            </FieldLabel>
                            <Input
                                id={`domain-confirmation-${domain.id}`}
                                value={form.data.confirmation}
                                onChange={(event) =>
                                    form.setData(
                                        'confirmation',
                                        event.target.value,
                                    )
                                }
                                autoComplete="off"
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.confirmation)}
                            />
                            {form.errors.confirmation && (
                                <FieldError>
                                    {form.errors.confirmation}
                                </FieldError>
                            )}
                        </Field>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing}
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={form.processing}
                            >
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Remove domain
                            </Button>
                        </DialogFooter>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DomainCard({
    domain,
    canManageIntegrations,
}: {
    domain: CustomDomain;
    canManageIntegrations: boolean;
}) {
    const [working, setWorking] = useState(false);

    function verify() {
        router.post(
            `/dashboard/custom-domains/${domain.id}/verify`,
            {},
            {
                preserveScroll: true,
                onStart: () => setWorking(true),
                onFinish: () => setWorking(false),
            },
        );
    }

    function reissue() {
        router.post(
            `/dashboard/custom-domains/${domain.id}/reissue`,
            {},
            {
                preserveScroll: true,
                onStart: () => setWorking(true),
                onFinish: () => setWorking(false),
            },
        );
    }

    return (
        <Card id={`custom-domain-${domain.id}`} className="scroll-mt-20">
            <CardHeader className="gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle className="break-all">
                                {domain.hostname}
                            </CardTitle>
                            <Badge variant={statusVariant(domain.status)}>
                                {statusLabel(domain.status)}
                            </Badge>
                        </div>
                        <CardDescription>
                            {domain.linksCount} assigned{' '}
                            {domain.linksCount === 1 ? 'link' : 'links'}
                            {domain.lastCheckedAt && (
                                <>
                                    {' '}
                                    · checked{' '}
                                    {new Date(
                                        domain.lastCheckedAt,
                                    ).toLocaleString()}
                                </>
                            )}
                        </CardDescription>
                    </div>
                    {domain.status === 'active' && (
                        <span className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            Delivery enabled
                        </span>
                    )}
                </div>
            </CardHeader>
            {canManageIntegrations && (
                <CardContent className="space-y-4">
                    <div className="rounded-lg border bg-muted/20 p-3 text-sm">
                        <p className="font-medium">DNS TXT record</p>
                        <dl className="mt-2 grid gap-2 sm:grid-cols-[auto_minmax(0,1fr)]">
                            <dt className="text-muted-foreground">Host</dt>
                            <dd className="font-mono text-xs break-all">
                                {domain.txtHost}
                            </dd>
                            <dt className="text-muted-foreground">Value</dt>
                            <dd className="font-mono text-xs break-all">
                                {domain.txtValue}
                            </dd>
                        </dl>
                    </div>
                    <div className="rounded-lg border bg-muted/20 p-3 text-sm">
                        <p className="font-medium">HTTPS challenge</p>
                        <p className="mt-1 font-mono text-xs break-all text-muted-foreground">
                            Serve exactly{' '}
                            <span className="font-semibold text-foreground">
                                {domain.verificationToken}
                            </span>{' '}
                            at {domain.challengeUrl}
                        </p>
                    </div>
                    {domain.failureReason && (
                        <p className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                            {domain.failureReason}
                        </p>
                    )}
                </CardContent>
            )}

            {canManageIntegrations && (
                <CardFooter className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        onClick={verify}
                        disabled={working}
                    >
                        {working ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <ShieldCheck data-icon="inline-start" />
                        )}
                        Check verification
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={reissue}
                        disabled={working}
                    >
                        <RefreshCw data-icon="inline-start" />
                        Reissue token
                    </Button>
                    <DeleteDomainDialog domain={domain} />
                </CardFooter>
            )}
        </Card>
    );
}

export default function CustomDomains({
    domains,
    canManageIntegrations,
}: CustomDomainsPageProps) {
    const [open, setOpen] = useState(false);
    const form = useForm({ hostname: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/dashboard/custom-domains', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    const planError = (form.errors as Record<string, string | undefined>).plan;

    return (
        <>
            <Head title="Custom domains" />
            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Custom domains"
                    eyebrow="Publishing"
                    icon={Globe2}
                    description="Verify a domain before assigning it to a short link. LinkPilot checks an exact DNS TXT record and a no-redirect HTTPS challenge before delivery starts."
                />
                <div className="flex justify-end">
                    {canManageIntegrations && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Add custom domain
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Add a custom domain
                                    </DialogTitle>
                                    <DialogDescription>
                                        Enter the hostname only. Add its DNS TXT
                                        and HTTPS challenge next.
                                    </DialogDescription>
                                </DialogHeader>
                                {planError && (
                                    <p className="text-sm text-destructive">
                                        {planError}
                                    </p>
                                )}
                                <form onSubmit={submit}>
                                    <FieldGroup>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.hostname,
                                            )}
                                        >
                                            <FieldLabel htmlFor="domain-hostname">
                                                Hostname
                                            </FieldLabel>
                                            <Input
                                                id="domain-hostname"
                                                value={form.data.hostname}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'hostname',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="go.example.com"
                                                autoComplete="url"
                                                disabled={form.processing}
                                                aria-invalid={Boolean(
                                                    form.errors.hostname,
                                                )}
                                            />
                                            <FieldDescription>
                                                Do not include https://, a path,
                                                or a port.
                                            </FieldDescription>
                                            {form.errors.hostname && (
                                                <FieldError>
                                                    {form.errors.hostname}
                                                </FieldError>
                                            )}
                                        </Field>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={form.processing}
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                disabled={form.processing}
                                            >
                                                {form.processing && (
                                                    <Spinner data-icon="inline-start" />
                                                )}
                                                Add domain
                                            </Button>
                                        </DialogFooter>
                                    </FieldGroup>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {domains.length === 0 ? (
                    <Empty className="border bg-muted/20">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Globe2 aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>No custom domains</EmptyTitle>
                            <EmptyDescription>
                                Add and verify a host to deliver one of your
                                short links from your own domain.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            {canManageIntegrations && (
                                <Button
                                    type="button"
                                    onClick={() => setOpen(true)}
                                >
                                    <Plus data-icon="inline-start" />
                                    Add custom domain
                                </Button>
                            )}
                        </EmptyContent>
                    </Empty>
                ) : (
                    <section
                        className="flex flex-col gap-4"
                        aria-label="Custom domains"
                    >
                        {domains.map((domain) => (
                            <DomainCard
                                key={domain.id}
                                domain={domain}
                                canManageIntegrations={canManageIntegrations}
                            />
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}
