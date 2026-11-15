import { Head, router, useForm } from '@inertiajs/react';
import { Check, Copy, KeyRound, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyApiKey,
    store as storeApiKey,
} from '@/routes/api-keys';
import type { ApiKey, ApiKeysPageProps } from '@/types/api-keys';

type ApiKeyForm = {
    name: string;
    abilities: string[];
    expires_at: string;
};

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '—' : dateFormatter.format(date);
}

function OneTimeToken({ token }: { token: string }) {
    const [copied, setCopied] = useState(false);
    const [copyError, setCopyError] = useState(false);

    async function copyToken() {
        try {
            await navigator.clipboard.writeText(token);
            setCopied(true);
            setCopyError(false);
        } catch {
            setCopied(false);
            setCopyError(true);
        }
    }

    return (
        <Alert className="border-primary/40">
            <KeyRound aria-hidden="true" />
            <AlertTitle>Copy this API key now</AlertTitle>
            <AlertDescription className="mt-2 grid gap-3">
                <p>
                    LinkPilot stores only a hash. This key will not be shown
                    again after you leave this page.
                </p>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <code className="min-w-0 flex-1 rounded-md border bg-muted px-3 py-2 font-mono text-sm break-all">
                        {token}
                    </code>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={copyToken}
                    >
                        {copied ? (
                            <Check
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                        ) : (
                            <Copy data-icon="inline-start" aria-hidden="true" />
                        )}
                        {copied ? 'Copied' : 'Copy key'}
                    </Button>
                </div>
                <span aria-live="polite" className="text-sm">
                    {copyError
                        ? 'Copy failed. Select and copy the key manually.'
                        : copied
                          ? 'API key copied to clipboard.'
                          : ''}
                </span>
            </AlertDescription>
        </Alert>
    );
}

function ApiKeyTable({ keys }: { keys: ApiKey[] }) {
    function revoke(key: ApiKey) {
        if (
            !window.confirm(
                `Revoke “${key.name}”? Integrations using this key will stop working.`,
            )
        ) {
            return;
        }

        router.delete(destroyApiKey({ token: key.id }).url, {
            preserveScroll: true,
        });
    }

    if (keys.length === 0) {
        return (
            <div className="rounded-lg border border-dashed px-4 py-8 text-center">
                <p className="font-medium">No API keys yet</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Create a key with only the access your integration needs.
                </p>
            </div>
        );
    }

    return (
        <div className="lp-table-shell overflow-x-auto">
            <table
                data-table-surface="populated"
                className="w-full min-w-[680px] text-left text-sm"
            >
                <caption className="sr-only">
                    Workspace API keys and their lifecycle details
                </caption>
                <thead className="bg-surface-secondary text-muted-foreground">
                    <tr>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Name
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Permissions
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Created
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Expires
                        </th>
                        <th scope="col" className="px-4 py-3 font-medium">
                            Last used
                        </th>
                        <th scope="col" className="px-4 py-3">
                            <span className="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {keys.map((key) => (
                        <tr
                            key={key.id}
                            className="border-t align-top first:border-t-0"
                        >
                            <th
                                scope="row"
                                className="max-w-48 px-4 py-4 font-medium break-words"
                            >
                                {key.name}
                            </th>
                            <td className="max-w-64 px-4 py-4">
                                <div className="flex flex-wrap gap-1.5">
                                    {key.abilities.map((ability) => (
                                        <Badge
                                            key={ability}
                                            variant="outline"
                                            className="font-mono"
                                        >
                                            {ability}
                                        </Badge>
                                    ))}
                                </div>
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                {formatDate(key.createdAt)}
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                {formatDate(key.expiresAt)}
                            </td>
                            <td className="px-4 py-4 whitespace-nowrap text-muted-foreground">
                                {formatDate(key.lastUsedAt)}
                            </td>
                            <td className="px-4 py-3">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    className="min-h-11"
                                    onClick={() => revoke(key)}
                                >
                                    <Trash2
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Revoke
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function ApiKeysIndex({
    keys,
    availableAbilities,
    oneTimeToken,
}: ApiKeysPageProps) {
    const form = useForm<ApiKeyForm>({
        name: '',
        abilities: ['links:read'],
        expires_at: '',
    });
    const abilityError =
        form.errors.abilities ??
        Object.entries(form.errors).find(([field]) =>
            field.startsWith('abilities.'),
        )?.[1];

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            expires_at: data.expires_at
                ? new Date(data.expires_at).toISOString()
                : null,
        }));
        form.post(storeApiKey().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: (errors) => {
                const firstError = Object.keys(errors)[0];
                const targetId =
                    firstError === 'name'
                        ? 'api-key-name'
                        : firstError === 'expires_at'
                          ? 'api-key-expires-at'
                          : `api-key-${availableAbilities[0]?.value.replace(':', '-') ?? 'links-read'}`;
                document.getElementById(targetId)?.focus();
            },
        });
    }

    function toggleAbility(
        ability: string,
        checked: boolean | 'indeterminate',
    ) {
        if (checked) {
            form.setData('abilities', [...form.data.abilities, ability]);
            return;
        }

        form.setData(
            'abilities',
            form.data.abilities.filter((selected) => selected !== ability),
        );
    }

    return (
        <>
            <Head title="API keys" />

            <div className="space-y-6">
                <Heading
                    variant="page"
                    title="API keys"
                    description="Create and revoke workspace-bound credentials for integrations."
                />

                {oneTimeToken ? <OneTimeToken token={oneTimeToken} /> : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Create an API key</CardTitle>
                        <CardDescription>
                            Choose specific permissions. Every key is limited to
                            the current workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="api-key-name">Key name</Label>
                                <Input
                                    id="api-key-name"
                                    name="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    autoComplete="off"
                                    required
                                    maxLength={100}
                                    aria-invalid={Boolean(form.errors.name)}
                                    aria-describedby={
                                        form.errors.name
                                            ? 'api-key-name-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="api-key-name-error"
                                    message={form.errors.name}
                                />
                            </div>

                            <fieldset
                                className="grid gap-3"
                                aria-invalid={Boolean(abilityError)}
                                aria-describedby={
                                    abilityError
                                        ? 'api-key-abilities-error'
                                        : undefined
                                }
                            >
                                <legend className="text-sm font-medium">
                                    Permissions
                                </legend>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {availableAbilities.map((ability) => {
                                        const id = `api-key-${ability.value.replace(':', '-')}`;

                                        return (
                                            <label
                                                key={ability.value}
                                                htmlFor={id}
                                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                                            >
                                                <Checkbox
                                                    id={id}
                                                    checked={form.data.abilities.includes(
                                                        ability.value,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleAbility(
                                                            ability.value,
                                                            checked,
                                                        )
                                                    }
                                                />
                                                <span>{ability.label}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                                <InputError
                                    id="api-key-abilities-error"
                                    message={abilityError}
                                />
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor="api-key-expires-at">
                                    Expiration (optional)
                                </Label>
                                <Input
                                    id="api-key-expires-at"
                                    name="expires_at"
                                    type="datetime-local"
                                    value={form.data.expires_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'expires_at',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.expires_at,
                                    )}
                                    aria-describedby={
                                        form.errors.expires_at
                                            ? 'api-key-expiry-help api-key-expiry-error'
                                            : 'api-key-expiry-help'
                                    }
                                />
                                <p
                                    id="api-key-expiry-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    The selected time uses your browser’s local
                                    time zone.
                                </p>
                                <InputError
                                    id="api-key-expiry-error"
                                    message={form.errors.expires_at}
                                />
                            </div>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="min-h-11"
                                >
                                    <Plus
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    {form.processing
                                        ? 'Creating key…'
                                        : 'Create API key'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Workspace API keys</CardTitle>
                        <CardDescription>
                            Keys are stored as hashes. Last-used time updates
                            after a successful authenticated API request.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ApiKeyTable keys={keys} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
