import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { PageHeader } from '@/components/page-header';
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
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { index as analyticsIndex } from '@/routes/analytics';
import {
    disable,
    index as customEventsIndex,
    store,
} from '@/routes/analytics/custom-events';
import type { AnalyticsCustomEventsProps } from '@/types/analytics';

export default function CustomAnalyticsEvents({
    definitions,
}: AnalyticsCustomEventsProps) {
    const form = useForm({ event_name: '' });
    const [disablingId, setDisablingId] = useState<number | null>(null);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function disableDefinition(id: number) {
        setDisablingId(id);
        router.patch(
            disable(id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setDisablingId(null),
            },
        );
    }

    return (
        <>
            <Head title="Custom analytics events" />

            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Custom analytics events"
                    description="Declare the event names your workspace may record. Only active names are accepted; existing analytics remain available after disabling one."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={analyticsIndex().url}>
                                <ArrowLeft aria-hidden="true" />
                                Back to analytics
                            </Link>
                        </Button>
                    }
                />

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Declare an event name</CardTitle>
                        <CardDescription>
                            Event properties, scripts, and arbitrary payloads
                            are not accepted or stored.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-5">
                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.event_name,
                                    )}
                                >
                                    <FieldLabel htmlFor="custom-event-name">
                                        Event name
                                    </FieldLabel>
                                    <Input
                                        id="custom-event-name"
                                        value={form.data.event_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'event_name',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={64}
                                        autoComplete="off"
                                        spellCheck={false}
                                        placeholder="subscription.started"
                                        disabled={form.processing}
                                        aria-invalid={Boolean(
                                            form.errors.event_name,
                                        )}
                                    />
                                    <FieldDescription>
                                        Start with a lowercase letter. Use
                                        lowercase letters, numbers, dots,
                                        hyphens, or underscores; up to 64
                                        characters.
                                    </FieldDescription>
                                    {form.errors.event_name && (
                                        <FieldError>
                                            {form.errors.event_name}
                                        </FieldError>
                                    )}
                                </Field>
                            </FieldGroup>
                            <div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Declare event
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Declared names</CardTitle>
                        <CardDescription>
                            Disabling a name blocks new events without deleting
                            its historical analytics.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {definitions.length === 0 ? (
                            <p className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                No custom event names yet. Declare one above to
                                enable it for this workspace.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {definitions.map((definition) => (
                                    <div
                                        key={definition.id}
                                        className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <code className="text-sm font-medium break-all">
                                                {definition.event_name}
                                            </code>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {definition.is_active
                                                    ? 'New events can be recorded.'
                                                    : 'New events are blocked; history is retained.'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            <Badge
                                                variant={
                                                    definition.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {definition.is_active
                                                    ? 'Active'
                                                    : 'Disabled'}
                                            </Badge>
                                            {definition.is_active && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={
                                                        disablingId !== null
                                                    }
                                                    onClick={() =>
                                                        disableDefinition(
                                                            definition.id,
                                                        )
                                                    }
                                                >
                                                    {disablingId ===
                                                    definition.id
                                                        ? 'Disabling…'
                                                        : 'Disable'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </section>
        </>
    );
}

CustomAnalyticsEvents.layout = {
    breadcrumbs: [
        {
            title: 'Analytics',
            href: analyticsIndex(),
        },
        {
            title: 'Custom events',
            href: customEventsIndex(),
        },
    ],
};
