import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const selectClassName =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 aria-invalid:border-destructive h-9 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function Profile({
    mustVerifyEmail,
    status,
    avatarUrl,
    profile,
    options,
    google,
}: Props) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
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
                                <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted">
                                    {avatarUrl ? (
                                        <img
                                            src={avatarUrl}
                                            alt="Your avatar"
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <span
                                            className="text-lg font-medium text-muted-foreground"
                                            aria-hidden="true"
                                        >
                                            {(auth.user?.name ?? '?')
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </span>
                                    )}
                                </div>

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
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="remove_avatar"
                                                value="1"
                                                className="size-4 rounded border"
                                            />
                                            Remove current avatar
                                        </label>
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

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="locale">Language</Label>
                                    <select
                                        id="locale"
                                        name="locale"
                                        defaultValue={profile.locale ?? ''}
                                        className={selectClassName}
                                    >
                                        <option value="">
                                            Use platform default
                                        </option>
                                        {options.locales.map((locale) => (
                                            <option
                                                key={locale.code}
                                                value={locale.code}
                                            >
                                                {locale.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.locale} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="country_code">
                                        Country
                                    </Label>
                                    <select
                                        id="country_code"
                                        name="country_code"
                                        defaultValue={profile.countryCode ?? ''}
                                        className={selectClassName}
                                    >
                                        <option value="">Not specified</option>
                                        {options.countries.map((country) => (
                                            <option
                                                key={country.code}
                                                value={country.code}
                                            >
                                                {country.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.country_code} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <select
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={profile.timezone ?? ''}
                                        className={selectClassName}
                                    >
                                        <option value="">
                                            Use platform default
                                        </option>
                                        {options.timezones.map((timezone) => (
                                            <option
                                                key={timezone}
                                                value={timezone}
                                            >
                                                {timezone}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.timezone} />
                                </div>
                            </div>

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
