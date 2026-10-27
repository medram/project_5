import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { KeyRound, QrCode } from 'lucide-react';
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
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { unlock } from '@/routes/qr-codes';

export default function QrCodeUnlock({
    code,
    title,
    description,
}: {
    code: string;
    title: string | null;
    description: string | null;
}) {
    const form = useForm({ access_code: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(unlock(code).url, { preserveScroll: true });
    }

    return (
        <main className="flex min-h-svh items-center justify-center bg-muted/30 p-4">
            <Head title={title || 'Protected QR campaign'} />
            <Card className="w-full max-w-md">
                <CardHeader className="items-center text-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <QrCode aria-hidden="true" />
                    </div>
                    <CardTitle>{title || 'Protected QR campaign'}</CardTitle>
                    <CardDescription>
                        {description ||
                            'Enter the access code to continue to this QR destination.'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-5">
                        <Field data-invalid={Boolean(form.errors.access_code)}>
                            <FieldLabel htmlFor="qr-unlock-code">
                                Access code
                            </FieldLabel>
                            <Input
                                id="qr-unlock-code"
                                type="password"
                                autoComplete="current-password"
                                value={form.data.access_code}
                                onChange={(event) =>
                                    form.setData(
                                        'access_code',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.access_code)}
                                disabled={form.processing}
                                autoFocus
                            />
                            <FieldDescription>
                                Unlocking does not count a scan. A scan is
                                recorded when the QR destination opens.
                            </FieldDescription>
                            {form.errors.access_code && (
                                <FieldError>
                                    {form.errors.access_code}
                                </FieldError>
                            )}
                        </Field>
                        <Button
                            className="w-full"
                            type="submit"
                            disabled={form.processing}
                        >
                            <KeyRound data-icon="inline-start" />
                            Unlock QR campaign
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </main>
    );
}
