import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
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
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type UnlockLinkProps = {
    slug: string;
    title: string | null;
};

export default function UnlockLink({ slug, title }: UnlockLinkProps) {
    const form = useForm({ access_code: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/${slug}/unlock`);
    }

    return (
        <>
            <Head title="Access required" />

            <main className="flex min-h-screen items-center justify-center bg-muted/30 p-4">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Access required</CardTitle>
                        <CardDescription>
                            {title
                                ? `Enter the access code to continue to ${title}.`
                                : 'Enter the access code to continue to this link.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit}>
                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.access_code,
                                    )}
                                >
                                    <FieldLabel htmlFor="access-code">
                                        Access code
                                    </FieldLabel>
                                    <Input
                                        id="access-code"
                                        type="password"
                                        value={form.data.access_code}
                                        onChange={(event) =>
                                            form.setData(
                                                'access_code',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="one-time-code"
                                        autoFocus
                                        required
                                        disabled={form.processing}
                                        aria-invalid={Boolean(
                                            form.errors.access_code,
                                        )}
                                    />
                                    {form.errors.access_code && (
                                        <FieldError>
                                            {form.errors.access_code}
                                        </FieldError>
                                    )}
                                </Field>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Continue
                                </Button>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
