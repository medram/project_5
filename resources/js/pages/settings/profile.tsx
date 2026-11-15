import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type Option = { code: string; name: string };

type PageProps = {
    auth: Auth;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    avatarUrl: string | null;
    profile: {
        locale: string | null;
        countryCode: string | null;
        timezone: string | null;
    };
    options: {
        locales: Option[];
        countries: Option[];
        timezones: string[];
    };
    google: {
        enabled: boolean;
        connected: boolean;
        email: string | null;
    };
};

const PROFILE_DEFAULT = '__linkpilot_profile_default__';
const COUNTRY_UNSPECIFIED = '__linkpilot_country_unspecified__';

export default function Profile({
    mustVerifyEmail,
    status,
    avatarUrl,
    profile,
    options,
    google,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const [regionalValues, setRegionalValues] = useState(() => ({
        locale: profile.locale ?? '',
        country_code: profile.countryCode ?? '',
        timezone: profile.timezone ?? '',
    }));

    return (
        <>
            <Head title="Profile settings" />

            <div className="space-y-6">
                <Heading
                    variant="page"
                    title="Profile"
                    description="Update your avatar, identity, and regional preferences"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <UserAvatar
                                    name={auth.user?.name ?? '?'}
                                    src={avatarUrl}
                                    seed={auth.user?.email}
                                    className="size-16"
                                />

                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="avatar">Avatar</Label>
                                    <Input
                                        id="avatar"
                                        name="avatar"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="max-w-sm"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        JPEG, PNG, or WebP up to 5 MB. Images
                                        are re-encoded to remove metadata.
                                    </p>
                                    <InputError message={errors.avatar} />

                                    {avatarUrl && (
                                        <Field className="flex-row items-center gap-2">
                                            <Checkbox
                                                id="remove-avatar"
                                                name="remove_avatar"
                                                value="1"
                                            />
                                            <FieldLabel
                                                htmlFor="remove-avatar"
                                                className="font-normal"
                                            >
                                                Remove current avatar
                                            </FieldLabel>
                                        </Field>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user?.name ?? ''}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user?.email ?? ''}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={Boolean(errors.locale)}>
                                    <FieldLabel htmlFor="locale">
                                        Language
                                    </FieldLabel>
                                    <input
                                        type="hidden"
                                        name="locale"
                                        value={regionalValues.locale}
                                    />
                                    <Select
                                        value={
                                            regionalValues.locale ||
                                            PROFILE_DEFAULT
                                        }
                                        onValueChange={(value) =>
                                            setRegionalValues((current) => ({
                                                ...current,
                                                locale:
                                                    value === PROFILE_DEFAULT
                                                        ? ''
                                                        : value,
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            id="locale"
                                            aria-invalid={Boolean(
                                                errors.locale,
                                            )}
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={PROFILE_DEFAULT}>
                                                Use platform default
                                            </SelectItem>
                                            {options.locales.map((locale) => (
                                                <SelectItem
                                                    key={locale.code}
                                                    value={locale.code}
                                                >
                                                    {locale.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.locale} />
                                </Field>

                                <Field
                                    data-invalid={Boolean(errors.country_code)}
                                >
                                    <FieldLabel htmlFor="country_code">
                                        Country
                                    </FieldLabel>
                                    <input
                                        type="hidden"
                                        name="country_code"
                                        value={regionalValues.country_code}
                                    />
                                    <Select
                                        value={
                                            regionalValues.country_code ||
                                            COUNTRY_UNSPECIFIED
                                        }
                                        onValueChange={(value) =>
                                            setRegionalValues((current) => ({
                                                ...current,
                                                country_code:
                                                    value ===
                                                    COUNTRY_UNSPECIFIED
                                                        ? ''
                                                        : value,
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            id="country_code"
                                            aria-invalid={Boolean(
                                                errors.country_code,
                                            )}
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                value={COUNTRY_UNSPECIFIED}
                                            >
                                                Not specified
                                            </SelectItem>
                                            {options.countries.map(
                                                (country) => (
                                                    <SelectItem
                                                        key={country.code}
                                                        value={country.code}
                                                    >
                                                        {country.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.country_code} />
                                </Field>

                                <Field
                                    className="sm:col-span-2"
                                    data-invalid={Boolean(errors.timezone)}
                                >
                                    <FieldLabel htmlFor="timezone">
                                        Timezone
                                    </FieldLabel>
                                    <input
                                        type="hidden"
                                        name="timezone"
                                        value={regionalValues.timezone}
                                    />
                                    <Select
                                        value={
                                            regionalValues.timezone ||
                                            PROFILE_DEFAULT
                                        }
                                        onValueChange={(value) =>
                                            setRegionalValues((current) => ({
                                                ...current,
                                                timezone:
                                                    value === PROFILE_DEFAULT
                                                        ? ''
                                                        : value,
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            id="timezone"
                                            aria-invalid={Boolean(
                                                errors.timezone,
                                            )}
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={PROFILE_DEFAULT}>
                                                Use platform default
                                            </SelectItem>
                                            {options.timezones.map(
                                                (timezone) => (
                                                    <SelectItem
                                                        key={timezone}
                                                        value={timezone}
                                                    >
                                                        {timezone}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.timezone} />
                                </Field>
                            </FieldGroup>

                            {mustVerifyEmail &&
                                auth.user?.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {google.enabled && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Google account</CardTitle>
                            <CardDescription>
                                {google.connected
                                    ? `Connected as ${google.email ?? 'your Google account'}.`
                                    : 'Connect Google to sign in without a password.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3">
                            {google.connected ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(
                                            '/account-settings/google',
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Disconnect Google
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" asChild>
                                    <a href="/account-settings/google/connect">
                                        Connect Google
                                    </a>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
