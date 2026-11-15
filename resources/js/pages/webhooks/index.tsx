import { Head, router, useForm } from '@inertiajs/react';
import {
    Copy,
    History,
    KeyRound,
    Pencil,
    Plus,
    Trash2,
    Webhook as WebhookIcon,
} from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
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
import { destroy, rotate, store, update } from '@/routes/webhooks';
import {
    index as deliveriesIndex,
    redeliver,
} from '@/routes/webhooks/deliveries';
import type {
    Webhook,
    WebhookDelivery,
    WebhooksPageProps,
} from '@/types/webhooks';

type WebhookFormData = {
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
};

type WebhookFormFieldsProps = {
    data: WebhookFormData;
    errors: Partial<Record<keyof WebhookFormData, string>>;
    supportedEvents: string[];
    processing: boolean;
    setData: (
        key: keyof WebhookFormData,
        value: string | boolean | string[],
    ) => void;
};

function WebhookFormFields({
    data,
    errors,
    supportedEvents,
    processing,
    setData,
}: WebhookFormFieldsProps) {
    function toggleEvent(event: string, checked: boolean) {
        setData(
            'events',
            checked
                ? [...data.events, event]
                : data.events.filter((candidate) => candidate !== event),
        );
    }

    return (
        <FieldGroup>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="webhook-name">Name</FieldLabel>
                <Input
                    id="webhook-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    maxLength={100}
                    autoComplete="off"
                    disabled={processing}
                    aria-invalid={Boolean(errors.name)}
                />
                {errors.name && <FieldError>{errors.name}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.url)}>
                <FieldLabel htmlFor="webhook-url">Endpoint URL</FieldLabel>
                <Input
                    id="webhook-url"
                    value={data.url}
                    onChange={(event) => setData('url', event.target.value)}
                    placeholder="https://hooks.example.com/linkpilot"
                    maxLength={2048}
                    autoComplete="off"
                    disabled={processing}
                    aria-invalid={Boolean(errors.url)}
                />
                <FieldDescription>
                    Public HTTPS endpoints only. LinkPilot rejects credentials
                    in the URL and endpoints that resolve to private or reserved
                    addresses.
                </FieldDescription>
                {errors.url && <FieldError>{errors.url}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.events)}>
                <FieldLabel>Events</FieldLabel>
                <div className="grid gap-2">
                    {supportedEvents.map((event) => (
                        <label
                            key={event}
                            className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
                        >
                            <Checkbox
                                checked={data.events.includes(event)}
                                onCheckedChange={(checked) =>
                                    toggleEvent(event, checked === true)
                                }
                                disabled={processing}
                            />
                            <span className="font-mono text-sm">{event}</span>
                        </label>
                    ))}
                </div>
                <FieldDescription>
                    Only consented analytics events for this workspace are
                    delivered.
                </FieldDescription>
                {errors.events && <FieldError>{errors.events}</FieldError>}
            </Field>

            <Field className="flex-row items-start gap-3">
                <Checkbox
                    id="webhook-active"
                    checked={data.is_active}
                    onCheckedChange={(checked) =>
                        setData('is_active', checked === true)
                    }
                    disabled={processing}
                />
                <div className="grid gap-1.5 leading-none">
                    <FieldLabel htmlFor="webhook-active">Active</FieldLabel>
                    <FieldDescription>
                        Inactive endpoints stay configured but receive no new
                        deliveries.
                    </FieldDescription>
                </div>
            </Field>
        </FieldGroup>
    );
}

function CreateWebhookDialog({
    supportedEvents,
    children,
}: {
    supportedEvents: string[];
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<WebhookFormData>({
        name: '',
        url: '',
        events: [],
        is_active: true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    // Plan enforcement answers with a `plan` validation error rather than a
    // field error, so surface it where the form can be seen.
    const planError = (form.errors as Record<string, string | undefined>).plan;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add webhook</DialogTitle>
                    <DialogDescription>
                        LinkPilot signs every delivery with a secret that is
                        shown once, when the webhook is created.
                    </DialogDescription>
                </DialogHeader>
                {planError && (
                    <p className="text-sm text-destructive">{planError}</p>
                )}
                <form onSubmit={submit}>
                    <WebhookFormFields
                        data={form.data}
                        errors={form.errors}
                        supportedEvents={supportedEvents}
                        processing={form.processing}
                        setData={form.setData}
                    />
                    <DialogFooter className="mt-6">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Add webhook
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditWebhookDialog({
    webhook,
    supportedEvents,
}: {
    webhook: Webhook;
    supportedEvents: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<WebhookFormData>({
        name: webhook.name,
        url: webhook.url ?? '',
        events: webhook.events,
        is_active: webhook.isActive,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(update(webhook).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <Pencil data-icon="inline-start" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {webhook.name}</DialogTitle>
                    <DialogDescription>
                        Saving changes cancels deliveries that are still waiting
                        to be retried.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <WebhookFormFields
                        data={form.data}
                        errors={form.errors}
                        supportedEvents={supportedEvents}
                        processing={form.processing}
                        setData={form.setData}
                    />
                    <DialogFooter className="mt-6">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Save webhook
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RotateSecretDialog({ webhook }: { webhook: Webhook }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function rotateSecret() {
        router.post(
            rotate(webhook).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setOpen(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <KeyRound data-icon="inline-start" />
                    Rotate secret
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rotate the signing secret?</DialogTitle>
                    <DialogDescription>
                        The new secret is shown once. Deliveries signed with the
                        old secret are rejected immediately, so update the
                        receiving endpoint first.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={rotateSecret}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Rotate secret
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeleteWebhookDialog({ webhook }: { webhook: Webhook }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function remove() {
        router.delete(destroy(webhook).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setOpen(false),
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
                    <DialogTitle>Remove {webhook.name}?</DialogTitle>
                    <DialogDescription>
                        Queued deliveries are cancelled and no further events
                        are sent to this endpoint.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={remove}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Remove webhook
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeliveryHistoryDialog({ webhook }: { webhook: Webhook }) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [redeliveringId, setRedeliveringId] = useState<number | null>(null);
    const [deliveries, setDeliveries] = useState<WebhookDelivery[] | null>(
        null,
    );

    function load(nextOpen: boolean) {
        setOpen(nextOpen);

        if (!nextOpen) {
            return;
        }

        setLoading(true);
        fetch(deliveriesIndex(webhook).url, {
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json())
            .then((payload) => setDeliveries(payload.deliveries ?? []))
            .catch(() => setDeliveries([]))
            .finally(() => setLoading(false));
    }

    function queueRedelivery(delivery: WebhookDelivery) {
        setRedeliveringId(delivery.id);
        router.post(
            redeliver({
                webhook: webhook.id,
                delivery: delivery.id,
            }).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setRedeliveringId(null);
                    load(true);
                },
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={load}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <History data-icon="inline-start" />
                    History
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{webhook.name} deliveries</DialogTitle>
                    <DialogDescription>
                        Delivery history for this endpoint. A bounded excerpt
                        from the latest failed response may be retained.
                    </DialogDescription>
                </DialogHeader>
                {loading ? (
                    <p className="text-sm text-muted-foreground">Loading…</p>
                ) : deliveries === null || deliveries.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No deliveries recorded yet.
                    </p>
                ) : (
                    <ul className="grid max-h-96 gap-2 overflow-y-auto">
                        {deliveries.map((delivery) => {
                            const canRedeliver =
                                webhook.isActive &&
                                (delivery.state === 'failed' ||
                                    delivery.state === 'cancelled');

                            return (
                                <li
                                    key={delivery.id}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-mono">
                                            {delivery.eventName}
                                        </span>
                                        <Badge
                                            variant={
                                                delivery.state === 'delivered'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {delivery.state}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        {delivery.attemptCount} attempt
                                        {delivery.attemptCount === 1 ? '' : 's'}
                                        {delivery.responseCode
                                            ? ` · HTTP ${delivery.responseCode}`
                                            : ''}
                                        {delivery.failureDetail
                                            ? ` · ${delivery.failureDetail}`
                                            : ''}
                                    </p>
                                    {delivery.responseExcerpt ? (
                                        <div className="mt-3">
                                            <p className="mb-1 font-medium">
                                                Latest response excerpt
                                            </p>
                                            <pre className="max-h-32 overflow-auto rounded-md bg-muted p-2 text-xs break-words whitespace-pre-wrap">
                                                {delivery.responseExcerpt}
                                            </pre>
                                        </div>
                                    ) : null}
                                    {delivery.sourceDeliveryId !== null ||
                                    canRedeliver ? (
                                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            {delivery.sourceDeliveryId !==
                                            null ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Redelivery of #
                                                    {delivery.sourceDeliveryId}
                                                </span>
                                            ) : null}
                                            {canRedeliver ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        redeliveringId !== null
                                                    }
                                                    onClick={() =>
                                                        queueRedelivery(
                                                            delivery,
                                                        )
                                                    }
                                                >
                                                    {redeliveringId ===
                                                    delivery.id ? (
                                                        <Spinner data-icon="inline-start" />
                                                    ) : null}
                                                    Redeliver
                                                </Button>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}

function OneTimeSecret({ secret }: { secret: string }) {
    const [copied, setCopied] = useState(false);

    function copy() {
        navigator.clipboard
            .writeText(secret)
            .then(() => {
                setCopied(true);
                window.setTimeout(() => setCopied(false), 2000);
            })
            .catch(() => setCopied(false));
    }

    return (
        <Card className="border-primary/40">
            <CardHeader>
                <div className="flex items-center gap-2 text-muted-foreground">
                    <KeyRound className="size-4" aria-hidden="true" />
                    <span className="text-sm font-medium">New secret</span>
                </div>
                <CardTitle>Copy this secret now</CardTitle>
                <CardDescription>
                    LinkPilot stores secrets encrypted and never shows them
                    again. Requests carry an X-LinkPilot-Signature header of
                    sha256=&lt;hmac&gt; over the raw JSON body.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap items-center gap-2">
                <code className="flex-1 overflow-x-auto rounded-lg border bg-muted px-3 py-2 font-mono text-sm">
                    {secret}
                </code>
                <Button type="button" variant="outline" onClick={copy}>
                    <Copy data-icon="inline-start" />
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </CardContent>
        </Card>
    );
}

export default function WebhooksIndex({
    webhooks,
    supportedEvents,
    canManageIntegrations,
    oneTimeSecret,
}: WebhooksPageProps) {
    return (
        <>
            <Head title="Webhooks" />

            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Webhooks"
                    description="Receive consented analytics events for this workspace. Failed deliveries retry automatically; owners and admins can redeliver eligible attempts."
                    actions={
                        canManageIntegrations ? (
                            <CreateWebhookDialog
                                supportedEvents={supportedEvents}
                            >
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Add webhook
                                </Button>
                            </CreateWebhookDialog>
                        ) : undefined
                    }
                />

                {canManageIntegrations && oneTimeSecret && (
                    <OneTimeSecret secret={oneTimeSecret} />
                )}

                {webhooks.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <WebhookIcon />
                            </EmptyMedia>
                            <EmptyTitle>No webhooks yet</EmptyTitle>
                            <EmptyDescription>
                                Add an HTTPS endpoint to start receiving signed
                                event deliveries.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            {canManageIntegrations && (
                                <CreateWebhookDialog
                                    supportedEvents={supportedEvents}
                                >
                                    <Button>
                                        <Plus data-icon="inline-start" />
                                        Add your first webhook
                                    </Button>
                                </CreateWebhookDialog>
                            )}
                        </EmptyContent>
                    </Empty>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {webhooks.map((webhook) => (
                            <Card
                                id={`webhook-${webhook.id}`}
                                key={webhook.id}
                                className="scroll-mt-20"
                            >
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <CardTitle>
                                                {webhook.name}
                                            </CardTitle>
                                            <CardDescription className="break-all">
                                                {webhook.url ??
                                                    'Endpoint details are visible to workspace owners and admins.'}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                webhook.isActive
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {webhook.isActive
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex flex-wrap gap-2">
                                        {webhook.events.map((event) => (
                                            <Badge
                                                key={event}
                                                variant="outline"
                                                className="font-mono"
                                            >
                                                {event}
                                            </Badge>
                                        ))}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {webhook.deliveriesCount}{' '}
                                        {webhook.deliveriesCount === 1
                                            ? 'delivery'
                                            : 'deliveries'}{' '}
                                        · revision {webhook.revision}
                                        {webhook.secretRotatedAt
                                            ? ` · secret rotated ${new Date(
                                                  webhook.secretRotatedAt,
                                              ).toLocaleDateString()}`
                                            : ''}
                                    </p>
                                    {canManageIntegrations && webhook.health ? (
                                        <div className="space-y-2 rounded-lg border bg-muted/20 p-3 text-sm">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-medium">
                                                    Current revision health
                                                </p>
                                                <Badge
                                                    variant={
                                                        webhook.health
                                                            .latestState ===
                                                        'failed'
                                                            ? 'destructive'
                                                            : webhook.health
                                                                    .latestState ===
                                                                'delivered'
                                                              ? 'default'
                                                              : 'secondary'
                                                    }
                                                >
                                                    {webhook.health
                                                        .latestState ??
                                                        'No attempts'}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground">
                                                {
                                                    webhook.health.counts
                                                        .delivered
                                                }{' '}
                                                delivered ·{' '}
                                                {webhook.health.counts.failed}{' '}
                                                failed ·{' '}
                                                {webhook.health.counts.pending}{' '}
                                                pending ·{' '}
                                                {
                                                    webhook.health.counts
                                                        .cancelled
                                                }{' '}
                                                cancelled
                                            </p>
                                        </div>
                                    ) : null}
                                </CardContent>
                                {canManageIntegrations && (
                                    <CardFooter className="flex flex-wrap gap-2">
                                        <DeliveryHistoryDialog
                                            webhook={webhook}
                                        />
                                        <EditWebhookDialog
                                            webhook={webhook}
                                            supportedEvents={supportedEvents}
                                        />
                                        <RotateSecretDialog webhook={webhook} />
                                        <DeleteWebhookDialog
                                            webhook={webhook}
                                        />
                                    </CardFooter>
                                )}
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
